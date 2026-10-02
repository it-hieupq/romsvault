<?php
/**
 * Member profile dashboard data. GET rf/v1/my-stats returns the at-a-glance counts
 * the /profile page shows before the member opens any category (wishlist, comments,
 * reports) plus the join date and blocked flag. Read-only over the member's own
 * rows, so — unlike the write endpoints — a blocked member still gets a 200 (with
 * blocked=1) so the page can render the "Blocked" badge instead of an error.
 * Cross-domain (counts live in three tables), which is why it sits here rather than
 * in any single action module. Depends on member-core.php (origin guard, current
 * member) and the table helpers in setting-member.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', function () {
	register_rest_route( RF_REST_NS, '/my-stats', array(
		'methods'             => 'GET',
		'callback'            => 'rf_rest_my_stats',
		'permission_callback' => '__return_true', // member session + origin checked in-handler
	) );
} );

/**
 * GET /wp-json/rf/v1/my-stats — per-category counts for the profile dashboard.
 * Every COUNT is served by an existing (member_id, …) index, so this stays cheap
 * and is queried live (no cache): a member's own counts change on their own actions.
 */
function rf_rest_my_stats( WP_REST_Request $request ) {
	if ( ! rf_verify_request_origin( $request ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'bad_origin' ), 403 );
	}
	$member = rf_current_member();
	if ( ! $member ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'auth_required' ), 401 );
	}

	global $wpdb;
	$member_id = (int) $member['id'];
	$wishlist  = rf_member_wishlist_table();
	$comments  = rf_member_comments_table();
	$reports   = rf_member_report_table();
	$ratings   = rf_member_rating_table();
	$contacts  = rf_member_contact_table();

	$wishlist_count = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM `{$wishlist}` WHERE member_id = %d",
		$member_id
	) );

	// One row: total + pending (member_comments.status is 'approved' | 'hold').
	$crow = $wpdb->get_row( $wpdb->prepare(
		"SELECT COUNT(*) AS total, SUM( status = 'hold' ) AS pending
		 FROM `{$comments}` WHERE member_id = %d",
		$member_id
	), ARRAY_A );

	$reports_total = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM `{$reports}` WHERE member_id = %d",
		$member_id
	) );
	$today_start   = date( 'Y-m-d 00:00:00', current_time( 'timestamp' ) );
	$reports_today = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM `{$reports}` WHERE member_id = %d AND created_at >= %s",
		$member_id, $today_start
	) );

	$ratings_count = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM `{$ratings}` WHERE member_id = %d",
		$member_id
	) );

	$contacts_count = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM `{$contacts}` WHERE member_id = %d",
		$member_id
	) );

	// Note: "member since" is NOT returned here — created_at is carried in the login
	// display payload (localStorage) and formatted client-side, so the dashboard
	// needs no per-view join-date query.
	return new WP_REST_Response( array(
		'ok'       => true,
		'blocked'  => ( (int) $member['blocked'] === 1 ) ? 1 : 0,
		'wishlist' => $wishlist_count,
		'comments' => array(
			'total'   => (int) $crow['total'],
			'pending' => (int) $crow['pending'],
		),
		'reports'  => array(
			'total' => $reports_total,
			'today' => $reports_today,
		),
		'ratings'  => $ratings_count,
		'contacts' => $contacts_count,
	), 200 );
}
