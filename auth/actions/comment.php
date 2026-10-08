<?php
/**
 * Member action — comments. Members are NOT wp_users, so comments go through
 * this REST endpoint (never wp-comments-post.php): the row is created with
 * user_id = 0 and comment_meta[rf_member_id] as the durable owner link, plus a
 * fast index row in `member_comments` (plan §4.7 / §14.3). Depends on
 * member-core.php (origin guard, current member, action throttle).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', function () {
	register_rest_route( RF_REST_NS, '/comment', array(
		'methods'             => 'POST',
		'callback'            => 'rf_rest_comment',
		'permission_callback' => '__return_true', // member session + origin checked in-handler
	) );
	register_rest_route( RF_REST_NS, '/my-comments', array(
		'methods'             => 'GET',
		'callback'            => 'rf_rest_my_comments',
		'permission_callback' => '__return_true',
	) );
} );

if ( ! defined( 'RF_COMMENT_MAXLEN' ) ) {
	define( 'RF_COMMENT_MAXLEN', 240 ); // hard server-side cap (chars)
}

/**
 * Comment error response — carries a human message (shown as-is by the client).
 */
function rf_comment_error( $code, $status ) {
	$messages = array(
		'blocked'         => __( 'Your account is locked and cannot comment.', 'rom' ),
		'comments_closed' => __( 'Comments are closed for this post.', 'rom' ),
		'rate_limited'    => __( 'You are commenting too fast. Please wait a moment.', 'rom' ),
		'empty'           => __( 'Please enter a comment.', 'rom' ),
		'not_found'       => __( 'This post is not available.', 'rom' ),
		'bad_parent'      => __( 'Invalid reply target.', 'rom' ),
		'duplicate'       => __( 'It looks like you already posted this comment.', 'rom' ),
		'auth_required'   => __( 'Please sign in to comment.', 'rom' ),
	);
	$message = isset( $messages[ $code ] ) ? $messages[ $code ] : __( 'Could not post your comment. Please try again.', 'rom' );
	return new WP_REST_Response( array( 'ok' => false, 'error' => $code, 'message' => $message ), $status );
}

/**
 * POST /wp-json/rf/v1/comment  { post, content, parent? }
 * Verify member session + block + throttle, then create the comment via
 * wp_new_comment() (keeps Akismet/moderation/dedup/flood — §12.B). Content is
 * raw text: all HTML is stripped and it is never auto-linked (§14.7).
 */
function rf_rest_comment( WP_REST_Request $request ) {
	if ( ! rf_verify_request_origin( $request ) ) {
		return rf_comment_error( 'bad_origin', 403 );
	}

	$member = rf_current_member();
	if ( ! $member ) {
		return rf_comment_error( 'auth_required', 401 );
	}
	// Refuse blocked members BEFORE any processing (validation, throttle, insert).
	if ( (int) $member['blocked'] === 1 ) {
		return rf_comment_error( 'blocked', 403 );
	}
	if ( ! rf_check_action_rate( (int) $member['id'] ) ) {
		return rf_comment_error( 'rate_limited', 429 );
	}

	$post_id = absint( $request->get_param( 'post' ) );
	$parent  = absint( $request->get_param( 'parent' ) );
	$content = wp_strip_all_tags( (string) $request->get_param( 'content' ) ); // raw text, keep newlines
	$content = trim( $content );

	if ( $content === '' ) {
		return rf_comment_error( 'empty', 400 );
	}
	if ( mb_strlen( $content ) > RF_COMMENT_MAXLEN ) {
		$content = mb_substr( $content, 0, RF_COMMENT_MAXLEN );
	}
	$post = get_post( $post_id );
	if ( ! $post || $post->post_status !== 'publish' ) {
		return rf_comment_error( 'not_found', 404 );
	}
	if ( ! comments_open( $post_id ) ) {
		return rf_comment_error( 'comments_closed', 403 );
	}
	if ( $parent > 0 ) {
		$parent_comment = get_comment( $parent );
		if ( ! $parent_comment || (int) $parent_comment->comment_post_ID !== $post_id ) {
			return rf_comment_error( 'bad_parent', 400 );
		}
		// Max 2 levels (0 and 1): replying to a level-1 comment re-parents to its
		// top-level ancestor so nothing ever nests deeper than level 1.
		if ( (int) $parent_comment->comment_parent > 0 ) {
			$parent = (int) $parent_comment->comment_parent;
		}
	}

	$commentdata = array(
		'comment_post_ID'      => $post_id,
		'comment_author'       => $member['name'] ? $member['name'] : __( 'Member', 'rom' ),
		'comment_author_email' => $member['email'],
		'comment_author_url'   => '',
		'comment_content'      => $content,
		'comment_parent'       => $parent,
		'comment_type'         => 'comment',
		'user_id'              => 0, // member != wp_user; owner link lives in comment_meta (§14.6 inv.1)
	);

	// Force every member comment to hold for moderation, regardless of WP's
	// "comment author previously approved" auto-approval. Registered at priority 99 so
	// it runs AFTER Akismet: a 'spam'/'trash' verdict (or WP_Error) is preserved, and
	// only an 'approved' result is downgraded to 0 (hold).
	$rf_hold_comment = function ( $approved ) {
		return ( 1 === $approved || '1' === $approved || 'approve' === $approved || 'approved' === $approved ) ? 0 : $approved;
	};
	add_filter( 'pre_comment_approved', $rf_hold_comment, 99 );
	// wp_new_comment() runs moderation/dedup/flood; true => WP_Error on failure.
	$comment_id = wp_new_comment( wp_slash( $commentdata ), true );
	remove_filter( 'pre_comment_approved', $rf_hold_comment, 99 );
	if ( is_wp_error( $comment_id ) ) {
		// Map WP core comment errors onto our client-facing codes. comment_flood is
		// WP's built-in 15s-between-comments guard — surface it as our rate_limited
		// message instead of the generic "could not post" fallback.
		switch ( $comment_id->get_error_code() ) {
			case 'comment_duplicate':
				return rf_comment_error( 'duplicate', 409 );
			case 'comment_flood':
				return rf_comment_error( 'rate_limited', 429 );
			default:
				return rf_comment_error( 'insert_failed', 400 );
		}
	}

	// Durable owner link (source of truth; index below is rebuildable from it).
	add_comment_meta( $comment_id, 'rf_member_id', (int) $member['id'] );
	// Avatar snapshot at post time so rendering this comment is a pure meta read
	// (no per-render member lookup). Not refreshed later — see the get_avatar_data
	// filter below.
	add_comment_meta( $comment_id, 'rf_member_avatar', (string) $member['avatar_url'] );

	$status = wp_get_comment_status( $comment_id ); // 'approved' | 'unapproved' | 'spam'
	if ( $status !== 'spam' ) {
		rf_member_comment_index_upsert( $comment_id, (int) $member['id'], $post_id, $status );
	}

	return new WP_REST_Response( array(
		'ok'      => true,
		'status'  => $status === 'approved' ? 'approved' : 'pending',
		'comment' => array(
			'id'      => (int) $comment_id,
			'author'  => $commentdata['comment_author'],
			'content' => $content, // raw text; the client escapes on insert
			'parent'  => $parent,
		),
	), 200 );
}

/**
 * GET /wp-json/rf/v1/my-comments — the member's own comments (pending +
 * approved; spam/trash are removed from the index). Rendered client-side on the
 * account page (P7); per-user + dynamic, so it never touches the HTML cache.
 */
function rf_rest_my_comments( WP_REST_Request $request ) {
	if ( ! rf_verify_request_origin( $request ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'bad_origin' ), 403 );
	}
	$member = rf_current_member();
	if ( ! $member ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'auth_required' ), 401 );
	}
	// Blocked members may still VIEW their own comments (read-only); only posting
	// (rf_rest_comment) is refused, so no blocked check here.

	$limit = absint( $request->get_param( 'limit' ) );
	if ( $limit < 1 || $limit > 50 ) {
		$limit = 10;
	}
	$offset = absint( $request->get_param( 'offset' ) );

	global $wpdb;
	$idx       = rf_member_comments_table();
	$comments  = $wpdb->prefix . 'comments';
	$posts     = $wpdb->prefix . 'posts';
	$member_id = (int) $member['id'];

	// Total counts the index (kept in sync by transition_comment_status), so it
	// matches the JOINed rows and drives has_more for the "Load more" button.
	$total = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM `{$idx}` WHERE member_id = %d",
		$member_id
	) );

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT i.comment_id, i.post_id, i.status, i.created_at,
		        c.comment_content, p.post_title
		 FROM `{$idx}` i
		 JOIN `{$comments}` c ON c.comment_ID = i.comment_id
		 JOIN `{$posts}` p ON p.ID = i.post_id
		 WHERE i.member_id = %d
		 ORDER BY i.created_at DESC, i.comment_id DESC
		 LIMIT %d OFFSET %d",
		$member_id, $limit, $offset
	), ARRAY_A );

	$out = array();
	foreach ( (array) $rows as $r ) {
		$out[] = array(
			'id'      => (int) $r['comment_id'],
			'post'    => array( 'id' => (int) $r['post_id'], 'title' => $r['post_title'], 'url' => get_permalink( (int) $r['post_id'] ) ),
			'content' => $r['comment_content'],
			'status'  => $r['status'] === 'approved' ? 'approved' : 'pending',
			'date'    => $r['created_at'],
		);
	}
	return new WP_REST_Response( array(
		'ok'       => true,
		'comments' => $out,
		'has_more' => ( $offset + count( $out ) ) < $total,
		'total'    => $total,
	), 200 );
}

/**
 * Insert/update the fast "my comments" index row (plan §14.3.3).
 */
function rf_member_comment_index_upsert( $comment_id, $member_id, $post_id, $wp_status ) {
	global $wpdb;
	$status = ( $wp_status === 'approved' || $wp_status === '1' || $wp_status === 1 ) ? 'approved' : 'hold';
	$wpdb->query( $wpdb->prepare(
		"INSERT INTO `" . rf_member_comments_table() . "` (comment_id, member_id, post_id, status, created_at)
		 VALUES (%d, %d, %d, %s, %s)
		 ON DUPLICATE KEY UPDATE status = VALUES(status)",
		(int) $comment_id, (int) $member_id, (int) $post_id, $status, current_time( 'mysql' )
	) );
}

/**
 * Keep the index in sync with moderation (plan §14.3.4): update status on
 * hold/approved, remove the row on spam/trash so the member no longer sees it.
 * Only touches comments that carry rf_member_id (member-authored).
 */
function rf_sync_member_comment_status( $new_status, $old_status, $comment ) {
	$member_id = (int) get_comment_meta( $comment->comment_ID, 'rf_member_id', true );
	if ( $member_id <= 0 ) {
		return; // not a member comment
	}
	global $wpdb;
	$idx = rf_member_comments_table();
	if ( $new_status === 'approved' || $new_status === 'unapproved' ) {
		rf_member_comment_index_upsert(
			(int) $comment->comment_ID,
			$member_id,
			(int) $comment->comment_post_ID,
			$new_status === 'approved' ? 'approved' : 'hold'
		);
	} else { // spam | trash | delete
		$wpdb->delete( $idx, array( 'comment_id' => (int) $comment->comment_ID ), array( '%d' ) );
	}
}
add_action( 'transition_comment_status', 'rf_sync_member_comment_status', 10, 3 );

/**
 * Permanent deletion does NOT fire transition_comment_status (only moderation does),
 * so prune the index here as well. Without this the row orphans: my-stats still counts
 * it (COUNT over the index) while my-comments can't return it (INNER JOIN wp_comments
 * finds nothing), inflating the count/pending and looping "Load more" forever. Fires
 * for post deletion too (wp_delete_post cascades into comment deletion). No-op for
 * staff/guest comments (their comment_id isn't in the index).
 */
function rf_member_comment_index_on_delete( $comment_id ) {
	global $wpdb;
	$wpdb->delete( rf_member_comments_table(), array( 'comment_id' => (int) $comment_id ), array( '%d' ) );
}
add_action( 'deleted_comment', 'rf_member_comment_index_on_delete', 10, 1 );

/**
 * Resolve who authored a comment across the two independent id namespaces
 * (plan §14.6). A comment is a member's iff it carries rf_member_id; else a
 * staff comment iff user_id > 0; else guest. NEVER cross the namespaces.
 */
function rf_resolve_comment_author( $comment ): array {
	$mid = (int) get_comment_meta( $comment->comment_ID, 'rf_member_id', true );
	if ( $mid > 0 ) {
		return array( 'type' => 'member', 'id' => $mid );
	}
	if ( (int) $comment->user_id > 0 ) {
		return array( 'type' => 'staff', 'id' => (int) $comment->user_id );
	}
	return array( 'type' => 'guest', 'id' => 0 );
}

/**
 * True when a comment was authored by a member (carries rf_member_id). All
 * front-end comments are member-authored (guest path removed); staff replies via
 * wp-admin are the only non-member rows.
 */
function rf_comment_is_member( $comment ) {
	return (int) get_comment_meta( $comment->comment_ID, 'rf_member_id', true ) > 0;
}

/**
 * Render comment content for display: raw text — escaped, never auto-linked so
 * URLs stay plain text (§14.7). Newlines are preserved by the caller's
 * `white-space: pre-wrap` (do NOT nl2br here — that would double the breaks).
 * Applies to every comment (data is member-only from launch).
 */
function rf_render_comment_content( $comment ): string {
	return esc_html( $comment->comment_content );
}

/**
 * Use a member's avatar for their comment avatars. Members are not wp_users
 * (user_id = 0), so core get_avatar() would fall back to the email Gravatar
 * (the "mystery person" default) and never show the Google picture.
 *
 * The avatar is snapshotted onto the comment at post time (rf_member_avatar
 * meta), so rendering is a pure meta read with NO extra query — the meta is
 * already in the comment-meta cache filled by the rf_member_id read above. An
 * empty snapshot means the member had no avatar: leave $args untouched so WP's
 * default shows. The snapshot is intentionally not refreshed afterwards — a
 * member changing their Google picture is rare and an old comment keeping the
 * avatar it was posted with is harmless.
 */
function rf_member_comment_avatar_data( $args, $id_or_email ) {
	if ( ! ( $id_or_email instanceof WP_Comment ) ) {
		return $args;
	}
	$cid = (int) $id_or_email->comment_ID;
	if ( (int) get_comment_meta( $cid, 'rf_member_id', true ) <= 0 ) {
		return $args;
	}
	$url = (string) get_comment_meta( $cid, 'rf_member_avatar', true );
	if ( $url !== '' ) {
		$args['url']          = $url;
		$args['found_avatar'] = true;
	}
	return $args;
}
add_filter( 'get_avatar_data', 'rf_member_comment_avatar_data', 10, 2 );
