<?php
/**
 * Permanent delete of a post together with the media it owns.
 *
 * Three entry points share one core: a row action on the list table, a bulk
 * action for clearing in batches, and a button in the Actions box at the top of
 * the edit screen sidebar.
 *
 * The media half is the risky one. An attachment is only removed when the posts
 * being deleted are the last thing pointing at it, so an image reused by a term,
 * an option or another post survives. That check runs once per batch rather than
 * once per post, which is what keeps a 100-post bulk inside a single request.
 */

defined( 'ABSPATH' ) || exit;

/** Post types that get the delete action. */
function rom_deletable_post_types(): array {
	return (array) apply_filters( 'rom_deletable_post_types', array( 'rom', 'emulator' ) );
}

/** Term meta keys whose value is an attachment id — other term meta holds counters. */
function rom_media_termmeta_keys(): array {
	return (array) apply_filters( 'rom_media_termmeta_keys', array(
		'cat_image',
		'emulator_console_image',
		'collection_banner',
		'region_image',
	) );
}

/** Option names whose value is an attachment id. */
function rom_media_option_keys(): array {
	return (array) apply_filters( 'rom_media_option_keys', array( 'options_logo', 'options_favicon' ) );
}

/**
 * Attachment ids the given posts point at: featured image, anything uploaded to
 * the post, and every id inside the ACF gallery field.
 */
function rom_collect_post_attachment_ids( array $post_ids ): array {
	global $wpdb;

	$post_ids = array_values( array_filter( array_map( 'intval', $post_ids ) ) );
	if ( ! $post_ids ) {
		return array();
	}
	$ph = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND post_id IN ($ph)",
		$post_ids
	) );

	$ids = array_merge( $ids, $wpdb->get_col( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_parent IN ($ph)",
		$post_ids
	) ) );

	$galleries = $wpdb->get_col( $wpdb->prepare(
		"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'image' AND post_id IN ($ph)",
		$post_ids
	) );
	foreach ( $galleries as $blob ) {
		$list = maybe_unserialize( $blob );
		if ( is_array( $list ) ) {
			$ids = array_merge( $ids, $list );
		}
	}

	return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
}

/**
 * Of the candidates, the ones something outside this batch still points at.
 *
 * Gallery values are serialised so they cannot be matched in SQL; they are read
 * once for the whole batch instead of once per candidate.
 */
function rom_attachment_ids_in_use_elsewhere( array $candidates, array $deleting ): array {
	global $wpdb;

	$candidates = array_values( array_filter( array_map( 'intval', $candidates ) ) );
	$deleting   = array_values( array_filter( array_map( 'intval', $deleting ) ) );
	if ( ! $candidates ) {
		return array();
	}
	if ( ! $deleting ) {
		$deleting = array( 0 );
	}

	$cand_ph = implode( ',', array_fill( 0, count( $candidates ), '%d' ) );
	$del_ph  = implode( ',', array_fill( 0, count( $deleting ), '%d' ) );
	$in_use  = array();

	// Featured image of a post that is staying.
	$in_use = array_merge( $in_use, $wpdb->get_col( $wpdb->prepare(
		"SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
		 WHERE meta_key = '_thumbnail_id' AND meta_value IN ($cand_ph) AND post_id NOT IN ($del_ph)",
		array_merge( $candidates, $deleting )
	) ) );

	// Term images.
	$term_keys = rom_media_termmeta_keys();
	if ( $term_keys ) {
		$key_ph = implode( ',', array_fill( 0, count( $term_keys ), '%s' ) );
		$in_use = array_merge( $in_use, $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT meta_value FROM {$wpdb->termmeta}
			 WHERE meta_key IN ($key_ph) AND meta_value IN ($cand_ph)",
			array_merge( $term_keys, $candidates )
		) ) );
	}

	// Site level images (logo, favicon).
	foreach ( rom_media_option_keys() as $option ) {
		$value = (int) get_option( $option );
		if ( $value ) {
			$in_use[] = $value;
		}
	}

	// Galleries of surviving posts. Most 'image' rows are an empty string rather
	// than a gallery, so the LIKE keeps this scan to the rows worth unserialising
	// (roughly 17k instead of 50k on this install).
	$rows   = $wpdb->get_col( $wpdb->prepare(
		"SELECT meta_value FROM {$wpdb->postmeta}
		 WHERE meta_key = 'image' AND meta_value LIKE 'a:%%' AND post_id NOT IN ($del_ph)",
		$deleting
	) );
	$lookup = array_flip( $candidates );
	foreach ( $rows as $blob ) {
		$list = maybe_unserialize( $blob );
		if ( ! is_array( $list ) ) {
			continue;
		}
		foreach ( $list as $id ) {
			$id = (int) $id;
			if ( isset( $lookup[ $id ] ) ) {
				$in_use[] = $id;
			}
		}
	}

	return array_values( array_unique( array_map( 'intval', $in_use ) ) );
}

/**
 * Delete attachments in bulk.
 *
 * wp_delete_attachment() costs ~90ms each on this install because it walks the
 * meta, terms and comments of one attachment at a time and clears its cache;
 * that is the whole cost of a large batch. Measurement showed the hooks hanging
 * off it are nearly free, so this keeps firing them — plugins stay in sync — and
 * only moves the row work into one query per table.
 *
 * @return int Attachments removed.
 */
function rom_bulk_delete_attachments( array $ids ): int {
	global $wpdb;

	$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
	if ( ! $ids ) {
		return 0;
	}

	// Prime the post objects with one query so the hook loop below adds none.
	// This also drops every id that is not an attachment row. A gallery or
	// _thumbnail_id value left pointing at media that no longer exists would
	// otherwise reach the hooks as a null post, which core callbacks such as
	// _wp_after_delete_font_family() dereference, and an id that turned out to
	// be an ordinary post would have its rows deleted unchecked.
	$posts = get_posts( array(
		'post__in'               => $ids,
		'post_type'              => 'attachment',
		'post_status'            => 'any',
		'posts_per_page'         => -1,
		'no_found_rows'          => true,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
	) );
	$by_id = array();
	foreach ( $posts as $p ) {
		$by_id[ $p->ID ] = $p;
	}
	$ids = array_keys( $by_id );
	if ( ! $ids ) {
		return 0;
	}
	$ph = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

	// Everything needed to find the files on disk, in one query.
	$uploads  = wp_get_upload_dir();
	$basedir  = trailingslashit( $uploads['basedir'] );
	$rows     = $wpdb->get_results( $wpdb->prepare(
		"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
		 WHERE post_id IN ($ph) AND meta_key IN ('_wp_attached_file', '_wp_attachment_metadata')",
		$ids
	) );
	$attached = array();
	$metas    = array();
	foreach ( $rows as $row ) {
		if ( '_wp_attached_file' === $row->meta_key ) {
			$attached[ (int) $row->post_id ] = $row->meta_value;
		} else {
			$metas[ (int) $row->post_id ] = maybe_unserialize( $row->meta_value );
		}
	}

	$files = array();
	foreach ( $ids as $id ) {
		if ( empty( $attached[ $id ] ) ) {
			continue;
		}
		$main    = $basedir . $attached[ $id ];
		$files[] = $main;
		$dir     = trailingslashit( dirname( $main ) );
		$meta    = isset( $metas[ $id ] ) ? $metas[ $id ] : null;
		if ( is_array( $meta ) ) {
			if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
				foreach ( $meta['sizes'] as $size ) {
					if ( ! empty( $size['file'] ) ) {
						$files[] = $dir . $size['file'];
					}
				}
			}
			// Kept beside the scaled copy when WP shrinks a big upload.
			if ( ! empty( $meta['original_image'] ) ) {
				$files[] = $dir . $meta['original_image'];
			}
		}
	}

	foreach ( $ids as $id ) {
		do_action( 'delete_post', $id, $by_id[ $id ] );
		do_action( 'delete_attachment', $id, $by_id[ $id ] );
	}

	// One query per table instead of per attachment.
	$wpdb->query( $wpdb->prepare(
		"DELETE FROM {$wpdb->commentmeta} WHERE comment_id IN
		 (SELECT comment_ID FROM {$wpdb->comments} WHERE comment_post_ID IN ($ph))",
		$ids
	) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->comments} WHERE comment_post_ID IN ($ph)", $ids ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ($ph)", $ids ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ($ph)", $ids ) );
	$deleted = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->posts} WHERE ID IN ($ph)", $ids ) );

	foreach ( array_unique( $files ) as $path ) {
		if ( is_file( $path ) ) {
			@unlink( $path );
		}
	}

	foreach ( $ids as $id ) {
		clean_post_cache( $id );
		do_action( 'deleted_post', $id, $by_id[ $id ] );
	}

	return $deleted;
}

/**
 * Of the given ids, the ones that really are attachment rows.
 *
 * A _thumbnail_id or a gallery entry can outlive the attachment it points at,
 * and the in-use scan is far too expensive to run for an id that no longer
 * exists.
 */
function rom_existing_attachment_ids( array $ids ): array {
	global $wpdb;

	$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
	if ( ! $ids ) {
		return array();
	}
	$ph = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

	return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND ID IN ($ph)",
		$ids
	) ) );
}

/**
 * Running total of what the before_delete_post hook had to remove.
 *
 * Whatever the hook mops up belongs in the same count the batch reports, or the
 * notice would claim fewer attachments than were actually deleted.
 */
function rom_hook_deleted_media( int $add = 0, bool $reset = false ): int {
	static $count = 0;

	if ( $reset ) {
		$count = 0;
	}
	$count += $add;

	return $count;
}

/**
 * Take a post's media with it on every permanent delete.
 *
 * The batch below is the fast path, but it is not the only way a rom leaves:
 * WP's own Delete permanently, the trash emptying itself after
 * EMPTY_TRASH_DAYS, WP-CLI, the REST API and plugins all reach wp_delete_post()
 * directly. Hooking here makes the media cleanup an invariant of deleting the
 * post rather than a property of one button, and it doubles as the batch's
 * safety net: anything that path leaves behind is still caught on the way out.
 *
 * When there is nothing to do this costs one indexed query, because by the time
 * the batch reaches wp_delete_post() the attachments are already gone and the
 * existence filter turns the hook back before the in-use scan.
 *
 * rom_bulk_delete_attachments() never calls wp_delete_post(), so this cannot
 * re-enter itself.
 */
add_action( 'before_delete_post', 'rom_delete_media_with_post', 10, 2 );
function rom_delete_media_with_post( $post_id, $post = null ) {
	if ( ! $post instanceof WP_Post ) {
		$post = get_post( $post_id );
	}
	if ( ! $post || ! in_array( $post->post_type, rom_deletable_post_types(), true ) ) {
		return;
	}

	$candidates = rom_existing_attachment_ids( rom_collect_post_attachment_ids( array( $post->ID ) ) );
	if ( ! $candidates ) {
		return;
	}

	$removable = array_values( array_diff(
		$candidates,
		rom_attachment_ids_in_use_elsewhere( $candidates, array( $post->ID ) )
	) );
	if ( ! $removable ) {
		return;
	}

	rom_hook_deleted_media( rom_bulk_delete_attachments( $removable ) );
}

/**
 * Delete the posts and the media only they were using.
 *
 * @return array{posts:int,attachments:int,kept:int,denied:int}
 */
function rom_delete_posts_with_media( array $post_ids ): array {
	$result = array( 'posts' => 0, 'attachments' => 0, 'kept' => 0, 'denied' => 0 );

	$post_ids = array_values( array_unique( array_filter( array_map( 'intval', $post_ids ) ) ) );
	if ( ! $post_ids ) {
		return $result;
	}

	// One capability check for the whole batch. Both deletable post types map
	// onto the 'post' capability set, so delete_posts is the gate. The per-post
	// 'delete_post' meta cap would also weigh each post's status and author,
	// which starts to matter the day a role other than administrator exists
	// here — make this delete_others_posts then.
	if ( ! current_user_can( 'delete_posts' ) ) {
		$result['denied'] = count( $post_ids );
		return $result;
	}
	$allowed = $post_ids;

	$candidates     = rom_collect_post_attachment_ids( $allowed );
	$in_use         = rom_attachment_ids_in_use_elsewhere( $candidates, $allowed );
	$removable      = array_values( array_diff( $candidates, $in_use ) );
	$result['kept'] = count( $candidates ) - count( $removable );

	// Term and comment counts are recounted once at the end instead of on every
	// single delete, which is most of the cost on a large batch.
	wp_defer_term_counting( true );
	wp_defer_comment_counting( true );

	rom_hook_deleted_media( 0, true );
	$result['attachments'] = rom_bulk_delete_attachments( $removable );

	foreach ( $allowed as $id ) {
		if ( wp_delete_post( $id, true ) ) {
			$result['posts']++;
		}
	}

	// Anything the batch missed was picked up by the hook on the way out.
	$result['attachments'] += rom_hook_deleted_media();

	wp_defer_term_counting( false );
	wp_defer_comment_counting( false );

	return $result;
}

/* -------------------------------------------------------------------------
 * Row action
 * ---------------------------------------------------------------------- */

add_filter( 'post_row_actions', 'rom_add_delete_row_action', 10, 2 );
function rom_add_delete_row_action( $actions, $post ) {
	if ( ! in_array( $post->post_type, rom_deletable_post_types(), true ) ) {
		return $actions;
	}
	if ( ! current_user_can( 'delete_post', $post->ID ) ) {
		return $actions;
	}

	$url = wp_nonce_url(
		admin_url( 'admin-post.php?action=rom_delete_with_media&post=' . $post->ID ),
		'rom_delete_with_media_' . $post->ID
	);

	$actions['rom_delete_with_media'] = sprintf(
		'<a href="%s" style="color:#b32d2e" onclick="return confirm(%s)"><strong>%s</strong></a>',
		esc_url( $url ),
		esc_attr( wp_json_encode( __( 'Delete this post and the files only it uses? This cannot be undone.', 'rom' ) ) ),
		esc_html__( 'DELETE', 'rom' )
	);

	return $actions;
}

add_action( 'admin_post_rom_delete_with_media', 'rom_handle_delete_row_action' );
function rom_handle_delete_row_action() {
	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
	check_admin_referer( 'rom_delete_with_media_' . $post_id );

	$post = get_post( $post_id );
	if ( ! $post || ! current_user_can( 'delete_post', $post_id ) ) {
		wp_die( esc_html__( 'You are not allowed to delete this post.', 'rom' ) );
	}
	$post_type = $post->post_type;

	$result = rom_delete_posts_with_media( array( $post_id ) );

	wp_safe_redirect( add_query_arg(
		array_merge( array( 'post_type' => $post_type ), rom_delete_notice_args( $result ) ),
		admin_url( 'edit.php' )
	) );
	exit;
}

/* -------------------------------------------------------------------------
 * Bulk action
 * ---------------------------------------------------------------------- */

add_action( 'admin_init', 'rom_register_bulk_delete_action' );
function rom_register_bulk_delete_action() {
	foreach ( rom_deletable_post_types() as $post_type ) {
		add_filter( "bulk_actions-edit-{$post_type}", 'rom_add_bulk_delete_action' );
		add_filter( "handle_bulk_actions-edit-{$post_type}", 'rom_handle_bulk_delete_action', 10, 3 );
	}
}

function rom_add_bulk_delete_action( $actions ) {
	// Plain text: a <select> option cannot carry markup.
	$actions['rom_delete_with_media'] = __( 'DELETE', 'rom' );
	return $actions;
}

function rom_handle_bulk_delete_action( $redirect_to, $doaction, $post_ids ) {
	if ( 'rom_delete_with_media' !== $doaction ) {
		return $redirect_to;
	}

	// A batch walks far more rows than a normal admin request, so lift the cap
	// where the host allows it rather than dying half way through.
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 0 );
	}

	$result = rom_delete_posts_with_media( (array) $post_ids );

	return add_query_arg( rom_delete_notice_args( $result ), $redirect_to );
}

/* -------------------------------------------------------------------------
 * Actions box on the edit screen
 * ---------------------------------------------------------------------- */

add_action( 'add_meta_boxes', 'rom_register_actions_meta_box' );
function rom_register_actions_meta_box() {
	foreach ( rom_deletable_post_types() as $post_type ) {
		add_meta_box(
			'rom_post_actions',
			__( 'Actions', 'rom' ),
			'rom_render_actions_meta_box',
			$post_type,
			'side',
			'high'
		);
	}
}

/**
 * Sits at the top of the sidebar. Further buttons can hook 'rom_post_actions_box'
 * to join it without touching this file.
 */
function rom_render_actions_meta_box( $post ) {
	do_action( 'rom_post_actions_box', $post );

	if ( ! current_user_can( 'delete_post', $post->ID ) ) {
		return;
	}

	$url = wp_nonce_url(
		admin_url( 'admin-post.php?action=rom_delete_with_media&post=' . $post->ID ),
		'rom_delete_with_media_' . $post->ID
	);
	?>
	<p style="margin:0">
		<a href="<?php echo esc_url( $url ); ?>"
		   class="button button-small"
		   style="color:#b32d2e;border-color:#b32d2e"
		   onclick="return confirm(<?php echo esc_attr( wp_json_encode( __( 'Delete this post and the files only it uses? This cannot be undone.', 'rom' ) ) ); ?>)">
			<strong><?php esc_html_e( 'DELETE', 'rom' ); ?></strong>
		</a>
	</p>
	<p class="description" style="margin:.5rem 0 0">
		<?php esc_html_e( 'Removes the post and the attachments nothing else points at.', 'rom' ); ?>
	</p>
	<?php
}

/* -------------------------------------------------------------------------
 * Result notice
 * ---------------------------------------------------------------------- */

function rom_delete_notice_args( array $result ): array {
	return array(
		'rom_deleted'    => (int) $result['posts'],
		'rom_media'      => (int) $result['attachments'],
		'rom_media_kept' => (int) $result['kept'],
		'rom_denied'     => (int) $result['denied'],
	);
}

add_action( 'admin_notices', 'rom_render_delete_notice' );
function rom_render_delete_notice() {
	if ( ! isset( $_GET['rom_deleted'] ) ) {
		return;
	}

	$posts  = (int) $_GET['rom_deleted'];
	$media  = isset( $_GET['rom_media'] ) ? (int) $_GET['rom_media'] : 0;
	$kept   = isset( $_GET['rom_media_kept'] ) ? (int) $_GET['rom_media_kept'] : 0;
	$denied = isset( $_GET['rom_denied'] ) ? (int) $_GET['rom_denied'] : 0;

	// Two counts in one sentence need two _n() calls: a single _n() would pick the
	// plural form from one of them and mislabel the other.
	$message = sprintf(
		/* translators: 1: post count phrase, 2: attachment count phrase. */
		__( 'Deleted %1$s and %2$s.', 'rom' ),
		sprintf(
			/* translators: %s: post count. */
			_n( '%s post', '%s posts', $posts, 'rom' ),
			number_format_i18n( $posts )
		),
		sprintf(
			/* translators: %s: attachment count. */
			_n( '%s attachment', '%s attachments', $media, 'rom' ),
			number_format_i18n( $media )
		)
	);
	if ( $kept ) {
		$message .= ' ' . sprintf(
			/* translators: %s: attachment count. */
			_n( '%s attachment was kept because something else still uses it.', '%s attachments were kept because something else still uses them.', $kept, 'rom' ),
			number_format_i18n( $kept )
		);
	}
	if ( $denied ) {
		$message .= ' ' . sprintf(
			/* translators: %s: post count. */
			_n( '%s post was skipped for lack of permission.', '%s posts were skipped for lack of permission.', $denied, 'rom' ),
			number_format_i18n( $denied )
		);
	}

	printf(
		'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
		esc_html( $message )
	);
}
