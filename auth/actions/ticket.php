<?php
/**
 * Member action — request tickets. A member submits a "Request" (add a new game, or
 * add/update a file for an existing game); admins process it and reply. Member-only
 * REST (never admin-ajax): identity comes from the session. Three creation limits:
 * the shared burst throttle (rf_check_action_rate), at most one OPEN ticket per member
 * at a time (status NOT IN 3,4), and a soft cap of RF_TICKET_DAILY_MAX creations per
 * calendar day (anti open/close spam). Rows land in {prefix}member_ticket for the
 * wp-admin Requests page (auth/admin-ticket.php). Depends on member-core.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'RF_TICKET_DAILY_MAX' ) ) {
	define( 'RF_TICKET_DAILY_MAX', 10 ); // max ticket creations per member per calendar day
}
if ( ! defined( 'RF_TICKET_DESC_MAXLEN' ) ) {
	define( 'RF_TICKET_DESC_MAXLEN', 500 ); // hard cap for the request description (chars); matches the VARCHAR(500) column
}

/**
 * Per-member history cache. The terminal-request list (GET /my-tickets?scope=history) is
 * cached for a day; the current/open request is never cached so admin progress shows live.
 * Invalidated whenever a ticket becomes terminal (member close/cancel, admin close) or is
 * deleted — i.e. whenever the member's finished-request list actually changes.
 */
function rf_ticket_history_key( $member_id ): string {
	return rf_member_cache_key( 'tickets', $member_id );
}
function rf_ticket_flush_history( $member_id ): void {
	if ( (int) $member_id > 0 ) {
		delete_transient( rf_ticket_history_key( $member_id ) );
	}
}

/**
 * Ticket error response — carries a human message (shown as-is by the client toast).
 */
function rf_ticket_error( $code, $status ): WP_REST_Response
{
	$messages = array(
		'blocked'       => __( 'Your account is locked and cannot submit requests.', 'romsfun' ),
		'rate_limited'  => __( 'You are doing that too fast. Please wait a moment.', 'romsfun' ),
		'auth_required' => __( 'Please sign in to submit a request.', 'romsfun' ),
		'bad_type'      => __( 'Please select a valid request type.', 'romsfun' ),
		'missing_title' => __( 'Please enter the game title.', 'romsfun' ),
		'open_exists'   => __( 'You already have an open request. Please close it before creating a new one.', 'romsfun' ),
		'daily_limit'   => __( 'You have reached today\'s request limit. Please try again tomorrow.', 'romsfun' ),
		'not_open'      => __( 'This request can no longer be changed.', 'romsfun' ),
	);
	$message = $messages[$code] ?? __('Could not process your request. Please try again.', 'romsfun');
	return new WP_REST_Response( array( 'ok' => false, 'error' => $code, 'message' => $message ), $status );
}

add_action( 'rest_api_init', function () {
	register_rest_route( RF_REST_NS, '/ticket', array(
		'methods'             => 'POST',
		'callback'            => 'rf_rest_ticket_create',
		'permission_callback' => '__return_true', // member session + origin checked in-handler
	) );
	register_rest_route( RF_REST_NS, '/my-tickets', array(
		'methods'             => 'GET',
		'callback'            => 'rf_rest_my_tickets',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( RF_REST_NS, '/ticket/close', array(
		'methods'             => 'POST',
		'callback'            => 'rf_rest_ticket_close',
		'permission_callback' => '__return_true',
	) );
} );

/**
 * POST /wp-json/rf/v1/ticket  { type:int(0|1), game_title, reference_url?, description? }
 * Verify member + origin + block + throttle, validate type/title, then INSERT atomically
 * only while the member has NO open ticket (status NOT IN 3,4) AND fewer than
 * RF_TICKET_DAILY_MAX creations today. New tickets start at status 0 (Init).
 */
function rf_rest_ticket_create( WP_REST_Request $request ): WP_REST_Response {
	if ( ! rf_verify_request_origin( $request ) ) {
		return rf_ticket_error( 'bad_origin', 403 );
	}
	$member = rf_current_member();
	if ( ! $member ) {
		return rf_ticket_error( 'auth_required', 401 );
	}
	if ( (int) $member['blocked'] === 1 ) {
		return rf_ticket_error( 'blocked', 403 );
	}
	if ( ! rf_check_action_rate( (int) $member['id'] ) ) {
		return rf_ticket_error( 'rate_limited', 429 );
	}

	$type_raw = $request->get_param( 'type' );
	$types    = rf_ticket_types();
	// Required: reject a missing / empty / non-numeric / unknown type so the member must
	// make an explicit valid choice — no silent 0 default, and any value outside the
	// configured list is rejected.
	if ( $type_raw === null || $type_raw === '' || ! is_numeric( $type_raw ) || ! isset( $types[ (int) $type_raw ] ) ) {
		return rf_ticket_error( 'bad_type', 400 );
	}
	$type = (int) $type_raw;

	$game_title = trim( wp_strip_all_tags( (string) $request->get_param( 'game_title' ) ) );
	if ( $game_title === '' ) {
		return rf_ticket_error( 'missing_title', 400 );
	}
	if ( mb_strlen( $game_title ) > 255 ) {
		$game_title = mb_substr( $game_title, 0, 255 );
	}

	$reference_url = esc_url_raw( (string) $request->get_param( 'reference_url' ) );
	if ( mb_strlen( $reference_url ) > 512 ) {
		$reference_url = ''; // over-long / malformed -> drop the optional link (char count matches VARCHAR(512))
	}

	$description = trim( wp_strip_all_tags( (string) $request->get_param( 'description' ) ) );
	if ( mb_strlen( $description ) > RF_TICKET_DESC_MAXLEN ) {
		$description = mb_substr( $description, 0, RF_TICKET_DESC_MAXLEN );
	}

	global $wpdb;
	$table       = rf_ticket_table();
	$member_id   = (int) $member['id'];
	$now         = current_time( 'mysql' );
	$today_start = date( 'Y-m-d 00:00:00', current_time( 'timestamp' ) );

	// Insert only while there is NO open ticket for this member AND today's creation
	// count is under the cap. The WHERE guard is the fast, friendly path; the DB's
	// uq_open_slot unique key is the race-proof backstop — two concurrent creates make
	// the 2nd raise a duplicate key (handled below). Sub-selects are wrapped in derived
	// tables so MySQL/MariaDB allows reading the same table we insert into (error 1093).
	// A duplicate here is an expected outcome, not a fault, so suppress its logging while
	// keeping last_error set for the check below. rows-affected: 1 = inserted, 0 = a
	// guard blocked it, false = SQL error (including the expected duplicate).
	$suppress = $wpdb->suppress_errors( true );
	$inserted = $wpdb->query( $wpdb->prepare(
		"INSERT INTO `{$table}`
			(member_id, request_type, game_title, reference_url, description, status, created_at, updated_at)
		 SELECT %d, %d, %s, %s, %s, 0, %s, %s FROM ( SELECT 1 ) d
		 WHERE NOT EXISTS (
			SELECT 1 FROM ( SELECT id FROM `{$table}` WHERE member_id = %d AND status NOT IN (3,4) LIMIT 1 ) o
		 )
		 AND ( SELECT c FROM ( SELECT COUNT(*) c FROM `{$table}`
				WHERE member_id = %d AND created_at >= %s ) t ) < %d",
		$member_id, $type, $game_title, $reference_url, $description, $now, $now,
		$member_id, $member_id, $today_start, RF_TICKET_DAILY_MAX
	) );
	$wpdb->suppress_errors( $suppress );

	if ( false === $inserted ) {
		// A concurrent create may have grabbed this member's open slot (uq_open_slot).
		// That unique-key violation is the race-proof backstop for the WHERE guard above:
		// report it as "you already have an open request", not a 500.
		if ( ! empty( $wpdb->last_error ) && stripos( $wpdb->last_error, 'Duplicate' ) !== false ) {
			return rf_ticket_error( 'open_exists', 409 );
		}
		return rf_ticket_error( 'insert_failed', 500 );
	}
	if ( 0 === $inserted ) {
		// Distinguish the two block reasons (cheap, only on the rejected path).
		$open = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM `{$table}` WHERE member_id = %d AND status NOT IN (3,4)",
			$member_id
		) );
		return $open > 0 ? rf_ticket_error( 'open_exists', 409 ) : rf_ticket_error( 'daily_limit', 429 );
	}

	return new WP_REST_Response( array(
		'ok'      => true,
		'message' => __( 'Your request has been submitted. Thank you!', 'romsfun' ),
	), 200 );
}

/**
 * GET /wp-json/rf/v1/my-tickets?offset=0&limit=10 — the member's own tickets, newest
 * first, for the /ticket page. Each row carries both the code and the resolved label
 * for status/type, plus is_open so the client knows whether to show Close/Cancel.
 */
function rf_rest_my_tickets( WP_REST_Request $request ): WP_REST_Response {
	if ( ! rf_verify_request_origin( $request ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'bad_origin' ), 403 );
	}
	$member = rf_current_member();
	if ( ! $member ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'auth_required' ), 401 );
	}
	// Blocked members may still VIEW their own tickets (read-only).

	// Defense: never trust client paging params. limit is clamped to [1,50] and offset to
	// >= 0; the query is always member-scoped (WHERE member_id = current) and prepared, so a
	// crafted limit/offset can neither over-fetch nor reach another member's rows.
	$limit  = max( 1, min( 50, absint( $request->get_param( 'limit' ) ) ) );
	$offset = absint( $request->get_param( 'offset' ) );

	global $wpdb;
	$table     = rf_ticket_table();
	$member_id = (int) $member['id'];

	// History (finished list) is cached per member for a day; loadHistory tags scope=history.
	// The current/open request (no scope) is never cached — it must reflect admin edits live.
	$is_history = ( 'history' === $request->get_param( 'scope' ) );
	if ( $is_history ) {
		$cached = get_transient( rf_ticket_history_key( $member_id ) );
		if ( is_array( $cached ) ) {
			return new WP_REST_Response( array( 'ok' => true, 'tickets' => $cached ), 200 );
		}
	}

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, request_type, game_title, reference_url, description, response_content,
				status, closed_by, created_at, updated_at
		 FROM `{$table}`
		 WHERE member_id = %d
		 ORDER BY created_at DESC, id DESC
		 LIMIT %d OFFSET %d",
		$member_id, $limit, $offset
	), ARRAY_A );

	$statuses = rf_ticket_statuses();
	$types    = rf_ticket_types();
	$out      = array();
	foreach ( (array) $rows as $r ) {
		$st = (int) $r['status'];
		$ty = (int) $r['request_type'];
		$out[] = array(
			'id'            => (int) $r['id'],
			'type'          => $ty,
			'type_label'    => $types[$ty] ?? '',
			'status'        => $st,
			'status_label'  => $statuses[$st] ?? '',
			'is_open'       => ! in_array( $st, array( 3, 4 ), true ),
			'game_title'    => $r['game_title'],
			'reference_url' => $r['reference_url'],
			'description'   => $r['description'],
			'response'      => $r['response_content'],
			'created_at'    => $r['created_at'],
			'updated_at'    => $r['updated_at'],
		);
	}

	if ( $is_history ) {
		set_transient( rf_ticket_history_key( $member_id ), $out, DAY_IN_SECONDS );
	}

	return new WP_REST_Response( array(
		'ok'      => true,
		'tickets' => $out,
	), 200 );
}

/**
 * POST /wp-json/rf/v1/ticket/close  { id:int, mode:'close'|'cancel' }
 * Member closes (status 3) or cancels (status 4) their OWN ticket while it is still
 * open. One UPDATE guarded by member_id + status NOT IN (3,4): rows-affected 0 means
 * not owned / not found / already terminal.
 */
function rf_rest_ticket_close( WP_REST_Request $request ): WP_REST_Response {
	if ( ! rf_verify_request_origin( $request ) ) {
		return rf_ticket_error( 'bad_origin', 403 );
	}
	$member = rf_current_member();
	if ( ! $member ) {
		return rf_ticket_error( 'auth_required', 401 );
	}
	if ( (int) $member['blocked'] === 1 ) {
		return rf_ticket_error( 'blocked', 403 );
	}
	if ( ! rf_check_action_rate( (int) $member['id'] ) ) {
		return rf_ticket_error( 'rate_limited', 429 );
	}

	$id         = absint( $request->get_param( 'id' ) );
	$mode       = sanitize_key( (string) $request->get_param( 'mode' ) );
	$new_status = ( $mode === 'cancel' ) ? 4 : 3; // anything but 'cancel' => close

	global $wpdb;
	$table     = rf_ticket_table();
	$member_id = (int) $member['id'];

	$updated = $wpdb->query( $wpdb->prepare(
		"UPDATE `{$table}` SET status = %d, closed_by = 1, updated_at = %s
		 WHERE id = %d AND member_id = %d AND status NOT IN (3,4)",
		$new_status, current_time( 'mysql' ), $id, $member_id
	) );

	if ( false === $updated ) {
		return rf_ticket_error( 'update_failed', 500 );
	}
	if ( 0 === $updated ) {
		return rf_ticket_error( 'not_open', 409 );
	}

	rf_ticket_flush_history( $member_id ); // a new terminal ticket changed the finished list

	$message = ( $new_status === 4 )
		? __( 'Your request has been cancelled.', 'romsfun' )
		: __( 'Your request has been closed.', 'romsfun' );

	return new WP_REST_Response( array( 'ok' => true, 'status' => $new_status, 'message' => $message ), 200 );
}