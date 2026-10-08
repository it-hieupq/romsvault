<?php
/**
 * Member action — contact messages. A signed-in member sends a short message (title +
 * content) to the site admin from the /contact-us page; the admin reads and replies in
 * the Contact inbox (auth/admin-contact.php). Member-only REST (never admin-ajax):
 * identity comes from the session. Two creation limits: the shared burst throttle
 * (rf_check_action_rate) and a soft cap of RF_CONTACT_DAILY_MAX messages per calendar
 * day. Rows land in {prefix}member_contact; the member reads any admin reply back on the
 * /profile page (GET /my-contacts). Depends on member-core.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'RF_CONTACT_TITLE_MAXLEN' ) ) {
	define( 'RF_CONTACT_TITLE_MAXLEN', 64 ); // hard cap for title (chars); matches VARCHAR(64)
}
if ( ! defined( 'RF_CONTACT_CONTENT_MAXLEN' ) ) {
	define( 'RF_CONTACT_CONTENT_MAXLEN', 512 ); // hard cap for content (chars); matches VARCHAR(512)
}
if ( ! defined( 'RF_CONTACT_DAILY_MAX' ) ) {
	define( 'RF_CONTACT_DAILY_MAX', 5 ); // max messages per member per calendar day
}

/**
 * Per-member contact-history cache. Only the first page (offset 0, default limit) of
 * GET /my-contacts is cached for a day, under the shared member-scoped key convention
 * (member:{id}:contacts). Invalidated whenever the member's list changes: they send a new
 * message, or the admin replies to / deletes one of theirs. Later pages (offset > 0) are
 * read live and never cached.
 */
function rf_contact_history_key( $member_id ): string {
	return rf_member_cache_key( 'contacts', $member_id );
}
function rf_contact_flush_history( $member_id ): void {
	if ( (int) $member_id > 0 ) {
		delete_transient( rf_contact_history_key( (int) $member_id ) );
	}
}

/**
 * Contact error response — carries a human message (shown as-is by the client toast).
 */
function rf_contact_error( $code, $status ): WP_REST_Response {
	$messages = array(
		'blocked'         => __( 'Your account is locked and cannot send messages.', 'rom' ),
		'rate_limited'    => __( 'You are doing that too fast. Please wait a moment.', 'rom' ),
		'auth_required'   => __( 'Please sign in to send a message.', 'rom' ),
		'missing_title'   => __( 'Please enter a title.', 'rom' ),
		'missing_content' => __( 'Please enter your message.', 'rom' ),
		'daily_limit'     => __( 'You have reached today\'s message limit. Please try again tomorrow.', 'rom' ),
	);
	$message = $messages[ $code ] ?? __( 'Could not send your message. Please try again.', 'rom' );
	return new WP_REST_Response( array( 'ok' => false, 'error' => $code, 'message' => $message ), $status );
}

add_action( 'rest_api_init', function () {
	register_rest_route( RF_REST_NS, '/contact', array(
		'methods'             => 'POST',
		'callback'            => 'rf_rest_contact_create',
		'permission_callback' => '__return_true', // member session + origin checked in-handler
	) );
	register_rest_route( RF_REST_NS, '/my-contacts', array(
		'methods'             => 'GET',
		'callback'            => 'rf_rest_my_contacts',
		'permission_callback' => '__return_true',
	) );
} );

/**
 * POST /wp-json/rf/v1/contact  { title, content }
 * Verify member + origin + block + throttle, validate/trim title & content, then INSERT
 * while today's message count is under RF_CONTACT_DAILY_MAX.
 */
function rf_rest_contact_create( WP_REST_Request $request ): WP_REST_Response {
	if ( ! rf_verify_request_origin( $request ) ) {
		return rf_contact_error( 'bad_origin', 403 );
	}
	$member = rf_current_member();
	if ( ! $member ) {
		return rf_contact_error( 'auth_required', 401 );
	}
	if ( (int) $member['blocked'] === 1 ) {
		return rf_contact_error( 'blocked', 403 );
	}
	if ( ! rf_check_action_rate( (int) $member['id'] ) ) {
		return rf_contact_error( 'rate_limited', 429 );
	}

	$raw_title = $request->get_param( 'title' );
	$title     = is_scalar( $raw_title ) ? trim( wp_strip_all_tags( (string) $raw_title ) ) : '';
	if ( $title === '' ) {
		return rf_contact_error( 'missing_title', 400 );
	}
	if ( mb_strlen( $title ) > RF_CONTACT_TITLE_MAXLEN ) {
		$title = mb_substr( $title, 0, RF_CONTACT_TITLE_MAXLEN );
	}

	$raw_content = $request->get_param( 'content' );
	$content     = is_scalar( $raw_content ) ? trim( wp_strip_all_tags( (string) $raw_content ) ) : '';
	if ( $content === '' ) {
		return rf_contact_error( 'missing_content', 400 );
	}
	if ( mb_strlen( $content ) > RF_CONTACT_CONTENT_MAXLEN ) {
		$content = mb_substr( $content, 0, RF_CONTACT_CONTENT_MAXLEN );
	}

	global $wpdb;
	$table       = rf_member_contact_table();
	$member_id   = (int) $member['id'];
	$now         = current_time( 'mysql' );
	$today_start = date( 'Y-m-d 00:00:00', current_time( 'timestamp' ) );

	// Soft daily cap (anti-spam). idx_member_created serves this COUNT. A rare race could
	// let one extra through; harmless for a soft limit.
	$today_count = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM `{$table}` WHERE member_id = %d AND created_at >= %s",
		$member_id, $today_start
	) );
	if ( $today_count >= RF_CONTACT_DAILY_MAX ) {
		return rf_contact_error( 'daily_limit', 429 );
	}

	$inserted = $wpdb->insert(
		$table,
		array(
			'member_id'  => $member_id,
			'title'      => $title,
			'content'    => $content,
			'created_at' => $now,
			'updated_at' => $now,
		),
		array( '%d', '%s', '%s', '%s', '%s' )
	);
	if ( false === $inserted ) {
		return rf_contact_error( 'insert_failed', 500 );
	}

	rf_contact_flush_history( $member_id ); // a new message changed the member's history

	return new WP_REST_Response( array(
		'ok'      => true,
		'message' => __( 'Your message has been sent. Thank you!', 'rom' ),
	), 200 );
}

/**
 * GET /wp-json/rf/v1/my-contacts?offset=0&limit=10 — the member's own messages, newest
 * first, for the /profile Contact history panel. Read live (no cache): a member's own
 * list changes on their own action, and the admin reply must show as soon as it lands.
 */
function rf_rest_my_contacts( WP_REST_Request $request ): WP_REST_Response {
	if ( ! rf_verify_request_origin( $request ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'bad_origin' ), 403 );
	}
	$member = rf_current_member();
	if ( ! $member ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'auth_required' ), 401 );
	}

	// Never trust client paging: limit clamped to [1,50], offset >= 0; the query is always
	// member-scoped and prepared, so a crafted limit/offset can neither over-fetch nor
	// reach another member's rows.
	$default_limit = 10;
	$limit_raw     = absint( $request->get_param( 'limit' ) );
	$limit         = $limit_raw > 0 ? min( 50, $limit_raw ) : $default_limit; // default 10 when omitted; hard-capped at 50
	$offset        = absint( $request->get_param( 'offset' ) );

	global $wpdb;
	$table     = rf_member_contact_table();
	$member_id = (int) $member['id'];

	// Cache only the canonical first page (offset 0, default limit) — the exact request the
	// profile panel makes. Any other paging reads live. On a hit, has_more is recomputed from
	// the cached slice (a full page implies there may be more).
	$use_cache = ( 0 === $offset && $limit === $default_limit );
	if ( $use_cache ) {
		$cached = get_transient( rf_contact_history_key( $member_id ) );
		if ( is_array( $cached ) ) {
			return new WP_REST_Response( array(
				'ok'       => true,
				'contacts' => $cached,
				'has_more' => ( count( $cached ) === $limit ),
			), 200 );
		}
	}

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, title, content, response, created_at
		 FROM `{$table}`
		 WHERE member_id = %d
		 ORDER BY created_at DESC, id DESC
		 LIMIT %d OFFSET %d",
		$member_id, $limit, $offset
	), ARRAY_A );

	$out = array();
	foreach ( (array) $rows as $r ) {
		$response = (string) $r['response'];
		$out[]    = array(
			'id'         => (int) $r['id'],
			'title'      => $r['title'],
			'content'    => $r['content'],
			'response'   => $response,
			'is_replied' => ( $response !== '' ),
			'created_at' => $r['created_at'],
		);
	}

	if ( $use_cache ) {
		set_transient( rf_contact_history_key( $member_id ), $out, DAY_IN_SECONDS );
	}

	// A full page implies there may be more; the loader stops when a later page is short
	// or empty. Cheap and correct enough for a member's own (small) message list.
	$has_more = ( count( $out ) === $limit );

	return new WP_REST_Response( array(
		'ok'       => true,
		'contacts' => $out,
		'has_more' => $has_more,
	), 200 );
}
