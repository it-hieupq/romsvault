<?php
/**
 * Member action — post reports. Members flag a problem with a downloadable post
 * (broken link, wrong file, …). Member-only REST endpoint (never admin-ajax):
 * identity comes from the session (no email). The reported URL is the client's
 * current page — it may be a download page, not the permalink — trusted only when
 * same-site (else it falls back to the permalink). Rate-limited via the shared
 * member bucket and capped at RF_REPORT_DAILY_MAX per member per day (a member
 * may report the same post more than once). Rows land in `member_report` for
 * the wp-admin Report page (auth/admin-report.php). Depends on
 * member-core.php (origin guard, current member, action throttle).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'RF_REPORT_MAXLEN' ) ) {
	define( 'RF_REPORT_MAXLEN', 255 ); // hard server-side cap for the description (chars)
}
if ( ! defined( 'RF_REPORT_DAILY_MAX' ) ) {
	define( 'RF_REPORT_DAILY_MAX', 30 ); // max reports a member can submit per calendar day
}

add_action( 'rest_api_init', function () {
	register_rest_route( RF_REST_NS, '/report', array(
		'methods'             => 'POST',
		'callback'            => 'rf_rest_report',
		'permission_callback' => '__return_true', // member session + origin checked in-handler
	) );
} );

/**
 * Report error response — carries a human message (shown as-is by the client).
 */
function rf_report_error( $code, $status ) {
	$messages = array(
		'blocked'       => __( 'Your account is locked and cannot submit reports.', 'rom' ),
		'rate_limited'  => __( 'You are sending reports too fast. Please wait a moment.', 'rom' ),
		'auth_required' => __( 'Please sign in to send a report.', 'rom' ),
		'not_found'     => __( 'This post is not available for reporting.', 'rom' ),
		'bad_type'      => __( 'Please select a valid issue type.', 'rom' ),
		'daily_limit'   => __( 'You have reached today\'s report limit. Please try again tomorrow.', 'rom' ),
	);
	$message = isset( $messages[ $code ] ) ? $messages[ $code ] : __( 'Could not send your report. Please try again.', 'rom' );
	return new WP_REST_Response( array( 'ok' => false, 'error' => $code, 'message' => $message ), $status );
}

/**
 * POST /wp-json/rf/v1/report  { post, type, url, content? }
 * Verify member session + block + throttle, validate the issue type against the
 * admin-configured options (ACF page_report_issues), enforce the per-member daily
 * cap, then store the row. `url` is the client's reported page (validated same-site,
 * permalink fallback). A member may report the same post multiple times.
 */
function rf_rest_report( WP_REST_Request $request ) {
	if ( ! rf_verify_request_origin( $request ) ) {
		return rf_report_error( 'bad_origin', 403 );
	}

	$member = rf_current_member();
	if ( ! $member ) {
		return rf_report_error( 'auth_required', 401 );
	}
	if ( (int) $member['blocked'] === 1 ) {
		return rf_report_error( 'blocked', 403 );
	}
	if ( ! rf_check_action_rate( (int) $member['id'] ) ) {
		return rf_report_error( 'rate_limited', 429 );
	}

	$post_id = absint( $request->get_param( 'post' ) );
	$type    = sanitize_text_field( (string) $request->get_param( 'type' ) );
	$content = trim( wp_strip_all_tags( (string) $request->get_param( 'content' ) ) );

	$post = get_post( $post_id );
	if ( ! $post || $post->post_status !== 'publish' || ! valid_post_type( $post->post_type ) ) {
		return rf_report_error( 'not_found', 404 );
	}

	// The issue type must be one of the admin-configured options (ACF option).
	$valid  = false;
	$issues = get_field( 'page_report_issues', 'option' );
	if ( is_array( $issues ) ) {
		foreach ( $issues as $issue ) {
			if ( isset( $issue['page_report_issue'] ) && $issue['page_report_issue'] === $type ) {
				$valid = true;
				break;
			}
		}
	}
	if ( ! $valid ) {
		return rf_report_error( 'bad_type', 400 );
	}

	if ( mb_strlen( $content ) > RF_REPORT_MAXLEN ) {
		$content = mb_substr( $content, 0, RF_REPORT_MAXLEN );
	}

	// Reported page URL comes from the client — it may be a download page, not the
	// canonical permalink. Trust it only when same-site; otherwise fall back to the
	// permalink so we never store an off-site URL.
	$url       = esc_url_raw( (string) $request->get_param( 'url' ) );
	$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
	$url_host  = $url ? wp_parse_url( $url, PHP_URL_HOST ) : '';
	// Trust only a same-site URL that fits the column (url_report is VARCHAR 128);
	// otherwise fall back to the canonical permalink (always short + valid).
	if ( ! $url_host || strcasecmp( $url_host, (string) $site_host ) !== 0 || strlen( $url ) > 128 ) {
		$url = get_permalink( $post_id );
	}

	global $wpdb;
	$table     = rf_member_report_table();
	$member_id = (int) $member['id'];

	// Atomic daily cap + insert in ONE statement: the row is written only while the
	// member has fewer than RF_REPORT_DAILY_MAX reports today (local midnight → now),
	// so concurrent submits can't overshoot the cap. The COUNT is wrapped in a
	// derived table so MySQL/MariaDB allows reading the same table we insert into
	// (avoids error 1093). Served by idx_member_created (member_id, created_at).
	// rows-affected: 1 = inserted, 0 = cap reached, false = SQL error.
	$today_start = date( 'Y-m-d 00:00:00', current_time( 'timestamp' ) );
	$inserted = $wpdb->query( $wpdb->prepare(
		"INSERT INTO `{$table}` (member_id, post_id, report_type, report_content, url_report, created_at)
		 SELECT %d, %d, %s, %s, %s, %s FROM ( SELECT 1 ) d
		 WHERE ( SELECT c FROM ( SELECT COUNT(*) c FROM `{$table}`
		         WHERE member_id = %d AND created_at >= %s ) t ) < %d",
		$member_id, $post_id, $type, $content, $url, current_time( 'mysql' ),
		$member_id, $today_start, RF_REPORT_DAILY_MAX
	) );
	if ( false === $inserted ) {
		return rf_report_error( 'insert_failed', 500 );
	}
	if ( 0 === $inserted ) {
		return rf_report_error( 'daily_limit', 429 );
	}

	return new WP_REST_Response( array(
		'ok'      => true,
		'message' => __( 'Your report has been sent. Thank you!', 'rom' ),
	), 200 );
}

add_action( 'rest_api_init', function () {
	register_rest_route( RF_REST_NS, '/my-reports', array(
		'methods'             => 'GET',
		'callback'            => 'rf_rest_my_reports',
		'permission_callback' => '__return_true', // member session + origin checked in-handler
	) );
} );

/**
 * GET /wp-json/rf/v1/my-reports?offset=0&limit=10 — the member's own reports,
 * newest first, for the profile dashboard (P7). Paginated via offset/limit (the
 * client appends pages with "Load more"). Served by idx_member_created. The post
 * title comes from a LEFT JOIN so a report on a since-deleted post still lists
 * (its stored url_report keeps the link working).
 */
function rf_rest_my_reports( WP_REST_Request $request ) {
	if ( ! rf_verify_request_origin( $request ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'bad_origin' ), 403 );
	}
	$member = rf_current_member();
	if ( ! $member ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'auth_required' ), 401 );
	}
	// Blocked members may still VIEW their own reports (read-only); only submitting
	// (rf_rest_report) is refused, so no blocked check here.

	$limit = absint( $request->get_param( 'limit' ) );
	if ( $limit < 1 || $limit > 50 ) {
		$limit = 10;
	}
	$offset = absint( $request->get_param( 'offset' ) );

	global $wpdb;
	$table     = rf_member_report_table();
	$posts     = $wpdb->prefix . 'posts';
	$member_id = (int) $member['id'];

	$total = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM `{$table}` WHERE member_id = %d",
		$member_id
	) );

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT r.id, r.post_id, r.report_type, r.report_content, r.url_report, r.created_at, p.post_title
		 FROM `{$table}` r
		 LEFT JOIN `{$posts}` p ON p.ID = r.post_id
		 WHERE r.member_id = %d
		 ORDER BY r.created_at DESC, r.id DESC
		 LIMIT %d OFFSET %d",
		$member_id, $limit, $offset
	), ARRAY_A );

	$out = array();
	foreach ( (array) $rows as $r ) {
		$out[] = array(
			'id'      => (int) $r['id'],
			'type'    => $r['report_type'],
			'content' => $r['report_content'],
			'url'     => $r['url_report'],
			'date'    => $r['created_at'],
			'post'    => array(
				'id'    => (int) $r['post_id'],
				'title' => $r['post_title'], // null when the post was deleted
			),
		);
	}

	return new WP_REST_Response( array(
		'ok'       => true,
		'reports'  => $out,
		'has_more' => ( $offset + count( $out ) ) < $total,
		'total'    => $total,
	), 200 );
}
