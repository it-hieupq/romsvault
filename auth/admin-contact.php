<?php
/**
 * Contact admin — wp-admin inbox for member contact messages (member_contact). Native
 * top-level admin page (not an ACF options page). Renders a paginated table with a
 * WP-native .subsubsub filter (All / Awaiting reply / Replied). Each row shows the
 * member + their message and an inline reply textarea + Save; a saved reply is what the
 * member reads back on their /profile page (a non-NULL response means "replied", so
 * there is no status column). Row/bulk delete via admin-ajax. Schema lives in
 * setting-member.php; front-end submission is the REST endpoint rf/v1/contact
 * (auth/actions/contact.php). Admin-only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Admin reply cap (response is a TEXT column; admin is trusted but still bounded).
if ( ! defined( 'RF_CONTACT_RESPONSE_MAXLEN' ) ) {
	define( 'RF_CONTACT_RESPONSE_MAXLEN', 1000 );
}

/**
 * Register the top-level "Contact" admin menu (native page + own render callback).
 */
function app_contact_menu(): void {
	add_submenu_page(
		'options', // Configs (inc/options.php)
		__( 'Contact', 'rom' ),
		__( 'Contact', 'rom' ),
		'manage_options',
		'contact',
		'app_contact_render_page'
	);
}
// After ACF adds the Configs menu at 99 (see auth/admin-ratings.php).
add_action( 'admin_menu', 'app_contact_menu', 100 );

/**
 * Save (or clear) the admin reply for one message. An empty reply stores NULL, which
 * reverts the row to "awaiting reply".
 */
function app_contact_save_reply(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'rom' ) ) );
	}
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'contact_update_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'rom' ) ) );
	}
	$id       = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	$response = isset( $_POST['response'] ) ? trim( wp_strip_all_tags( wp_unslash( $_POST['response'] ) ) ) : '';
	if ( $id <= 0 ) {
		wp_send_json_error( array( 'message' => __( 'Invalid ID.', 'rom' ) ) );
	}
	if ( mb_strlen( $response ) > RF_CONTACT_RESPONSE_MAXLEN ) {
		$response = mb_substr( $response, 0, RF_CONTACT_RESPONSE_MAXLEN );
	}

	global $wpdb;
	$table   = rf_member_contact_table();
	$now     = current_time( 'mysql' );
	$updated = $wpdb->query( $wpdb->prepare(
		"UPDATE `{$table}` SET response = %s, updated_at = %s WHERE id = %d",
		( $response === '' ? null : $response ), $now, $id
	) );

	if ( false === $updated ) {
		wp_send_json_error( array( 'message' => __( 'Error. Please contact administrator.', 'rom' ) ) );
	}
	if ( function_exists( 'rf_contact_flush_history' ) ) {
		rf_contact_flush_history( (int) $wpdb->get_var( $wpdb->prepare( "SELECT member_id FROM `{$table}` WHERE id = %d", $id ) ) );
	}
	wp_send_json_success( array( 'message' => __( 'Saved.', 'rom' ) ) );
}
add_action( 'wp_ajax_app_contact_save_reply', 'app_contact_save_reply' );

/**
 * Delete a single message row.
 */
function app_contact_delete_row(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'rom' ) ) );
	}
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'contact_delete_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'rom' ) ) );
	}
	$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	if ( $id <= 0 ) {
		wp_send_json_error( array( 'message' => __( 'Invalid ID.', 'rom' ) ) );
	}
	global $wpdb;
	$table  = rf_member_contact_table();
	$owner  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT member_id FROM `{$table}` WHERE id = %d", $id ) );
	$result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	if ( $result ) {
		if ( function_exists( 'rf_contact_flush_history' ) ) { rf_contact_flush_history( $owner ); }
		wp_send_json_success( array( 'message' => __( 'Deleted.', 'rom' ) ) );
	}
	wp_send_json_error( array( 'message' => __( 'Error. Please contact administrator.', 'rom' ) ) );
}
add_action( 'wp_ajax_app_contact_delete_row', 'app_contact_delete_row' );

/**
 * Bulk delete message rows.
 */
function app_contact_delete_rows(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'rom' ) ) );
	}
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'contact_delete_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'rom' ) ) );
	}
	if ( ! isset( $_POST['ids'] ) || ! is_array( $_POST['ids'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid IDs.', 'rom' ) ) );
	}
	$ids = array_filter( array_map( 'absint', $_POST['ids'] ) );
	if ( empty( $ids ) ) {
		wp_send_json_error( array( 'message' => __( 'No valid IDs provided.', 'rom' ) ) );
	}
	global $wpdb;
	$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
	$table        = rf_member_contact_table();
	$owners       = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT member_id FROM {$table} WHERE id IN({$placeholders})", ...$ids ) );
	$result       = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN({$placeholders})", ...$ids ) );
	if ( $result !== false ) {
		if ( function_exists( 'rf_contact_flush_history' ) ) {
			foreach ( (array) $owners as $o ) { rf_contact_flush_history( (int) $o ); }
		}
		wp_send_json_success( array( 'message' => sprintf( __( '%d item(s) deleted.', 'rom' ), $result ) ) );
	}
	wp_send_json_error( array( 'message' => __( 'Error. Please contact administrator.', 'rom' ) ) );
}
add_action( 'wp_ajax_app_contact_delete_rows', 'app_contact_delete_rows' );

/**
 * Render the Contact admin page: .subsubsub reply-state filter + paginated table.
 */
function app_contact_render_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	global $wpdb;

	$per_page     = rf_admin_per_page( 10 );
	$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	$offset       = ( $current_page - 1 ) * $per_page;

	$table        = rf_member_contact_table();
	$member_table = rf_member_table();

	// Reply-state filter (a non-NULL, non-empty response == replied).
	$filter = isset( $_GET['state'] ) ? sanitize_key( $_GET['state'] ) : '';
	if ( 'awaiting' === $filter ) {
		$where = "WHERE (t.response IS NULL OR t.response = '')";
	} elseif ( 'replied' === $filter ) {
		$where = "WHERE (t.response IS NOT NULL AND t.response <> '')";
	} else {
		$filter = '';
		$where  = '';
	}

	$total_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} t " . $where );

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT t.id, t.title, t.content, t.response, t.created_at, t.updated_at,
				m.email AS member_email, m.name AS member_name
		 FROM {$table} t
		 LEFT JOIN {$member_table} m ON m.id = t.member_id
		 {$where}
		 ORDER BY t.created_at DESC
		 LIMIT %d OFFSET %d",
		$per_page, $offset
	) );

	// Filter counts (one aggregate pass).
	$counts = $wpdb->get_row(
		"SELECT COUNT(*) AS total,
			SUM(response IS NULL OR response = '') AS awaiting,
			SUM(response IS NOT NULL AND response <> '') AS replied
		 FROM {$table}",
		ARRAY_A
	);
	$views     = array(
		''         => array( __( 'All', 'rom' ), (int) $counts['total'] ),
		'awaiting' => array( __( 'Awaiting reply', 'rom' ), (int) $counts['awaiting'] ),
		'replied'  => array( __( 'Replied', 'rom' ), (int) $counts['replied'] ),
	);
	$view_link = function ( $val, $label, $count ) use ( $filter, $per_page ) {
		$args = array( 'page' => 'contact', 'per_page' => $per_page );
		if ( $val !== '' ) {
			$args['state'] = $val;
		}
		$is_cur = ( $val === $filter );
		$class  = $is_cur ? ' class="current"' : '';
		return '<a href="' . esc_url( add_query_arg( $args, admin_url( 'admin.php' ) ) ) . '"' . $class . '>'
			. esc_html( $label ) . ' <span class="count">(' . (int) $count . ')</span></a>';
	};
	$view_keys = array_keys( $views );
	$last_key  = end( $view_keys );

	$update_nonce = wp_create_nonce( 'contact_update_nonce' );
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Contact', 'rom' ); ?></h1>
		<hr class="wp-header-end">

		<style>
			.rf-contact-wrap { overflow-x: auto; margin: 16px 0; width: 100%; }
			.rf-contact-wrap table { min-width: 900px; width: 100%; }
			.rf-contact-wrap th, .rf-contact-wrap td { padding: 10px; vertical-align: top; word-wrap: break-word; }
			.rf-contact-wrap textarea { width: 100%; min-height: 54px; }
			.rf-contact-wrap .col-title { min-width: 160px; }
			.rf-contact-wrap .col-msg, .rf-contact-wrap .col-resp { min-width: 220px; max-width: 340px; white-space: pre-line; }
			.rf-contact-badge { display:inline-block; padding:2px 10px; border-radius:9999px; font-size:11px; font-weight:600; }
			.rf-contact-pagination { margin: 20px 0; display: flex; align-items: center; gap: 10px; }
			.rf-contact-pagination .page-numbers { padding: 5px 10px; border: 1px solid #ccc; border-radius: 3px; text-decoration: none; }
			.rf-contact-pagination .page-numbers.current { background: #0073aa; color: #fff; border-color: #0073aa; }
		</style>

		<ul class="subsubsub" style="margin:8px 0 4px">
			<?php foreach ( $views as $rf_val => $rf_v ) : ?>
				<li><?php echo $view_link( (string) $rf_val, $rf_v[0], $rf_v[1] ); ?><?php echo $rf_val === $last_key ? '' : ' |'; ?></li>
			<?php endforeach; ?>
		</ul>

		<form method="get" style="margin:8px 0; clear:both">
			<input type="hidden" name="page" value="contact" />
			<?php if ( $filter !== '' ) : ?><input type="hidden" name="state" value="<?php echo esc_attr( $filter ); ?>" /><?php endif; ?>
			<?php rf_admin_per_page_field( rf_admin_today_badge( rf_admin_today_count( rf_member_contact_table(), false ) ) ); ?>
		</form>

		<?php if ( $rows ) : ?>
		<div class="rf-contact-wrap" data-nonce="<?php echo esc_attr( wp_create_nonce( 'contact_delete_nonce' ) ); ?>" data-update-nonce="<?php echo esc_attr( $update_nonce ); ?>">
			<table class="widefat striped">
				<thead>
				<tr>
					<th><?php esc_html_e( 'Member', 'rom' ); ?></th>
					<th class="col-title"><?php esc_html_e( 'Title', 'rom' ); ?></th>
					<th class="col-msg"><?php esc_html_e( 'Message', 'rom' ); ?></th>
					<th class="col-resp"><?php esc_html_e( 'Reply', 'rom' ); ?></th>
					<th><?php esc_html_e( 'Time', 'rom' ); ?></th>
					<th>
						<button class="button button-small delete-rows" type="button"><?php esc_html_e( 'Delete', 'rom' ); ?></button>
						<button class="button button-small button-primary" id="contactCheckAll" type="button"><?php esc_html_e( 'Check', 'rom' ); ?></button>
					</th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ( $rows as $row ) :
					$replied = ( null !== $row->response && '' !== $row->response );
				?>
					<tr data-id="<?php echo esc_attr( $row->id ); ?>">
						<td>
							<?php echo esc_html( $row->member_email ?: '—' ); ?>
							<?php if ( ! empty( $row->member_name ) ) : ?><br><small><?php echo esc_html( $row->member_name ); ?></small><?php endif; ?>
						</td>
						<td class="col-title">
							<strong><?php echo esc_html( $row->title ); ?></strong>
							<br>
							<span class="rf-contact-badge" style="background:<?php echo $replied ? '#dcfce7' : '#fef3c7'; ?>;color:<?php echo $replied ? '#166534' : '#b45309'; ?>">
								<?php echo $replied ? esc_html__( 'Replied', 'rom' ) : esc_html__( 'Awaiting', 'rom' ); ?>
							</span>
						</td>
						<td class="col-msg"><?php echo esc_html( $row->content ); ?></td>
						<td class="col-resp">
							<textarea class="rf-contact-response" maxlength="<?php echo esc_attr( RF_CONTACT_RESPONSE_MAXLEN ); ?>"><?php echo esc_textarea( (string) $row->response ); ?></textarea>
							<button type="button" class="button button-small button-primary rf-contact-save"><?php esc_html_e( 'Save', 'rom' ); ?></button>
						</td>
						<td style="white-space:nowrap">
							<?php echo rf_admin_time_ago( (string) $row->created_at, false ); // returns pre-escaped HTML ?>
							<?php if ( $row->updated_at && $row->updated_at !== $row->created_at ) : ?>
								<br><small style="color:#888"><?php esc_html_e( 'upd', 'rom' ); ?> <?php echo rf_admin_time_ago( (string) $row->updated_at, false ); // pre-escaped HTML ?></small>
							<?php endif; ?>
						</td>
						<td style="text-align:center"><input class="check-item" type="checkbox" value="<?php echo esc_attr( $row->id ); ?>"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<script>
		(function ($) {
			var wrap = document.querySelector('.rf-contact-wrap');
			if (!wrap) return;
			var delNonce = wrap.getAttribute('data-nonce');
			var updNonce = wrap.getAttribute('data-update-nonce');

			wrap.addEventListener('click', function (e) {
				var save = e.target.closest('.rf-contact-save');
				if (save) {
					var tr = save.closest('tr');
					var id = tr.getAttribute('data-id');
					var response = tr.querySelector('.rf-contact-response').value;
					save.disabled = true;
					$.post(ajaxurl, { action: 'app_contact_save_reply', nonce: updNonce, id: id, response: response })
						.done(function (r) { if (r && r.success) { location.reload(); } else { alert(r && r.data ? r.data.message : 'Save failed'); save.disabled = false; } })
						.fail(function () { alert('Request failed'); save.disabled = false; });
					return;
				}
				var del = e.target.closest('.delete-rows');
				if (del) {
					var ids = Array.prototype.map.call(wrap.querySelectorAll('.check-item:checked'), function (c) { return c.value; });
					if (!ids.length) { alert('Select at least one row.'); return; }
					if (!confirm('Delete ' + ids.length + ' item(s)?')) return;
					$.post(ajaxurl, { action: 'app_contact_delete_rows', nonce: delNonce, ids: ids })
						.done(function (r) { alert(r && r.data ? r.data.message : 'Done'); if (r && r.success) location.reload(); })
						.fail(function () { alert('Request failed'); });
				}
			});
			var checkAll = document.getElementById('contactCheckAll');
			if (checkAll) checkAll.addEventListener('click', function () {
				wrap.querySelectorAll('.check-item').forEach(function (c) { c.checked = !c.checked; });
			});
		})(jQuery);
		</script>

		<?php
		$total_pages = ceil( $total_count / $per_page );
		if ( $total_pages > 1 ) :
			$base_url = add_query_arg( array( 'per_page' => $per_page, 'state' => $filter ), admin_url( 'admin.php?page=contact' ) );
			?>
			<div class="rf-contact-pagination">
				<span class="displaying-num">
					<?php
					$start = ( $current_page - 1 ) * $per_page + 1;
					$end   = min( $current_page * $per_page, $total_count );
					printf( esc_html__( 'Showing %1$d-%2$d of %3$d messages', 'rom' ), $start, $end, $total_count );
					?>
				</span>
				<div class="pagination-links">
					<?php
					if ( $current_page > 1 ) {
						echo '<a class="page-numbers" href="' . esc_url( add_query_arg( 'paged', $current_page - 1, $base_url ) ) . '">« Prev</a>';
					}
					for ( $i = 1; $i <= $total_pages; $i++ ) {
						if ( $i == 1 || $i == $total_pages || ( $i >= $current_page - 2 && $i <= $current_page + 2 ) ) {
							$cls = ( $i == $current_page ) ? 'current' : '';
							echo '<a class="page-numbers ' . esc_attr( $cls ) . '" href="' . esc_url( add_query_arg( 'paged', $i, $base_url ) ) . '">' . (int) $i . '</a>';
						} elseif ( $i == $current_page - 3 || $i == $current_page + 3 ) {
							echo '<span class="page-numbers dots">…</span>';
						}
					}
					if ( $current_page < $total_pages ) {
						echo '<a class="page-numbers" href="' . esc_url( add_query_arg( 'paged', $current_page + 1, $base_url ) ) . '">Next »</a>';
					}
					?>
				</div>
			</div>
		<?php endif; ?>

		<?php else : ?>
			<div class="notice notice-info"><p><?php esc_html_e( 'No messages yet.', 'rom' ); ?></p></div>
		<?php endif; ?>
	</div>
	<?php
}
