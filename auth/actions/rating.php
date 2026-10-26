<?php
/**
 * Member action — post ratings (Play Store style). A member rates a post ONCE:
 * a whole-star score 1..5 plus an optional short review. The review row lives in
 * `member_rating` (for the public reviews list + the profile "my ratings" and to
 * enforce one-per-post). `rating_avg`/`rating_count` post_meta cache the visible
 * reviews' average and count so listings never aggregate the table: every write
 * (a new rating here, hide/unhide/delete in auth/admin-ratings.php) recounts them
 * with rf_rating_recount(). Member-only REST (never admin-ajax); ratings are
 * immutable (a second attempt → 409).
 * Depends on member-core.php (origin guard, current member, throttle).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'RF_RATINGS_CACHE_TTL' ) ) {
	// The first page of a post's public reviews is cached (Redis-backed transient) for
	// this long. rf_rating_recount() drops it on every write, so the TTL only bounds how
	// stale a reviewer's name / avatar can get.
	define( 'RF_RATINGS_CACHE_TTL', HOUR_IN_SECONDS );
}

if ( ! defined( 'RF_RATING_MAXLEN' ) ) {
	define( 'RF_RATING_MAXLEN', 164 ); // hard server-side cap for the review text (chars)
}

add_action( 'rest_api_init', function () {
	register_rest_route( RF_REST_NS, '/rating', array(
		'methods'             => 'POST',
		'callback'            => 'rf_rest_rating_submit',
		'permission_callback' => '__return_true', // member session + origin checked in-handler
	) );
	// Public (same-origin) reviews list for a post; also returns the caller's own
	// rating when a member session is present.
	register_rest_route( RF_REST_NS, '/ratings', array(
		'methods'             => 'GET',
		'callback'            => 'rf_rest_ratings_list',
		'permission_callback' => '__return_true',
	) );
	// The member's own ratings across posts (profile dashboard).
	register_rest_route( RF_REST_NS, '/my-ratings', array(
		'methods'             => 'GET',
		'callback'            => 'rf_rest_my_ratings',
		'permission_callback' => '__return_true',
	) );
} );

/**
 * Rating error response — carries a human message (shown as-is by the client).
 */
function rf_rating_error( $code, $status ) {
	$messages = array(
		'blocked'       => __( 'Your account is locked and cannot rate.', 'rom' ),
		'rate_limited'  => __( 'You are doing that too fast. Please wait a moment.', 'rom' ),
		'auth_required' => __( 'Please sign in to rate.', 'rom' ),
		'not_found'     => __( 'This post is not available for rating.', 'rom' ),
		'bad_score'     => __( 'Please choose a rating between 1 and 5 stars.', 'rom' ),
		'already_rated' => __( 'You have already rated this post.', 'rom' ),
	);
	$message = isset( $messages[ $code ] ) ? $messages[ $code ] : __( 'Could not save your rating. Please try again.', 'rom' );
	return new WP_REST_Response( array( 'ok' => false, 'error' => $code, 'message' => $message ), $status );
}

/**
 * POST /wp-json/rf/v1/rating  { post, score, content? }
 * Validate member + post + score/content, insert ONE immutable review row (a repeat
 * is a 409), then fold the score into rating_avg/rating_count with the classic
 * running-average. A member may rate each post only once — enforced at the DB via
 * UNIQUE(member_id, post_id), not just here.
 */
function rf_rest_rating_submit( WP_REST_Request $request ) {
	if ( ! rf_verify_request_origin( $request ) ) {
		return rf_rating_error( 'bad_origin', 403 );
	}
	$member = rf_current_member();
	if ( ! $member ) {
		return rf_rating_error( 'auth_required', 401 );
	}
	if ( (int) $member['blocked'] === 1 ) {
		return rf_rating_error( 'blocked', 403 );
	}
	if ( ! rf_check_action_rate( (int) $member['id'] ) ) {
		return rf_rating_error( 'rate_limited', 429 );
	}

	$post_id = absint( $request->get_param( 'post' ) );
	$score   = (int) $request->get_param( 'score' );
	$content = trim( wp_strip_all_tags( (string) $request->get_param( 'content' ) ) );

	$post = get_post( $post_id );
	if ( ! $post || $post->post_status !== 'publish' || ! valid_post_type( $post->post_type ) ) {
		return rf_rating_error( 'not_found', 404 );
	}
	if ( $score < 1 || $score > 5 ) {
		return rf_rating_error( 'bad_score', 400 );
	}
	if ( mb_strlen( $content ) > RF_RATING_MAXLEN ) {
		$content = mb_substr( $content, 0, RF_RATING_MAXLEN );
	}

	global $wpdb;
	$table     = rf_member_rating_table();
	$member_id = (int) $member['id'];
	$now       = current_time( 'mysql' );

	// Insert-only: UNIQUE(member_id, post_id) makes a duplicate a no-op (0 rows).
	// rows-affected: 1 = inserted, 0 = already rated, false = SQL error.
	$inserted = $wpdb->query( $wpdb->prepare(
		"INSERT IGNORE INTO `{$table}` (member_id, post_id, score, content, status, created_at, updated_at)
		 VALUES (%d, %d, %d, %s, 1, %s, %s)",
		$member_id, $post_id, $score, $content, $now, $now
	) );
	if ( false === $inserted ) {
		return rf_rating_error( 'insert_failed', 500 );
	}
	if ( 0 === $inserted ) {
		return rf_rating_error( 'already_rated', 409 );
	}

	$totals = rf_rating_recount( $post_id );

	return new WP_REST_Response( array(
		'ok'      => true,
		'mine'    => array( 'score' => $score, 'content' => $content, 'created_at' => $now ),
		'post'    => array( 'avg' => $totals['avg'], 'count' => $totals['count'], 'score' => rom_rating( $post_id )['score'] ),
		'dist'    => $totals['dist'],
		// Server-rendered so the hero ratings on the page match a fresh load exactly.
		'summary' => array(
			'hero'    => rom_rating_summary_html( $post_id, 'hero' ),
			'compact' => rom_rating_summary_html( $post_id, 'compact' ),
		),
		'message' => __( 'Thank you for your rating!', 'rom' ),
	), 200 );
}

/**
 * Recount a post's rating from its visible reviews and store it in the
 * rating_avg / rating_count post_meta the templates read. Recounting (rather
 * than folding one score into a running average) keeps the cache exact after
 * hides and deletes too. Read and write run under a per-post MySQL lock: without
 * it a recount that read before another vote committed could write its stale
 * total last. A post with no visible review loses both meta rows: missing reads as 0.
 *
 * @return array{count:int, avg:float, dist:array<string,int>}
 */
function rf_rating_recount( $post_id ) {
	global $wpdb;
	$post_id = (int) $post_id;
	$table   = rf_member_rating_table();
	$lock    = 'rf_rating_' . $post_id;

	// If the lock times out the recount still runs; the next write corrects it.
	$wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) );
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT score, COUNT(*) AS c FROM `{$table}` WHERE post_id = %d AND status = 1 GROUP BY score",
		$post_id
	), ARRAY_A );

	$dist  = array( '1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0 );
	$count = 0;
	$sum   = 0;
	foreach ( (array) $rows as $row ) {
		$star = (int) $row['score'];
		$c    = (int) $row['c'];
		if ( $star >= 1 && $star <= 5 ) {
			$dist[ (string) $star ] = $c;
			$count += $c;
			$sum   += $star * $c;
		}
	}

	$avg = $count ? round( $sum / $count, 2 ) : 0.0;
	if ( $count ) {
		update_post_meta( $post_id, 'rating_count', $count );
		update_post_meta( $post_id, 'rating_avg', $avg );
	} else {
		delete_post_meta( $post_id, 'rating_count' );
		delete_post_meta( $post_id, 'rating_avg' );
	}
	$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
	delete_transient( rf_ratings_cache_key( $post_id ) );

	return array( 'count' => $count, 'avg' => $avg, 'dist' => $dist );
}

/**
 * Transient key for the cached first page of a post's public reviews.
 */
function rf_ratings_cache_key( $post_id ) {
	return 'rf_ratings_p1_' . (int) $post_id;
}

/**
 * GET /wp-json/rf/v1/ratings?post=&offset=&limit=
 * Public (same-origin) list of VISIBLE reviews for a post, newest-first, paginated
 * (served by idx_post_status_created). Also returns the post's current avg/count and,
 * when a member session is present, the caller's own rating (`mine`) so the UI shows
 * it read-only instead of the form.
 */
function rf_rest_ratings_list( WP_REST_Request $request ) {
	if ( ! rf_verify_request_origin( $request ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'bad_origin' ), 403 );
	}
	$post_id = absint( $request->get_param( 'post' ) );
	if ( ! $post_id ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'bad_request' ), 400 );
	}

	$limit = absint( $request->get_param( 'limit' ) );
	if ( $limit < 1 || $limit > 50 ) {
		$limit = 10;
	}
	$offset = absint( $request->get_param( 'offset' ) );

	global $wpdb;
	$table   = rf_member_rating_table();
	$members = rf_member_table();

	// The first page is the same for everyone and is what every single page view asks
	// for, so its public part (not `mine`) is cached until the next rating write.
	$cacheable = ( 0 === $offset && 10 === $limit );
	$public    = $cacheable ? get_transient( rf_ratings_cache_key( $post_id ) ) : false;
	if ( is_array( $public ) ) {
		$total = (int) $public['total'];
		$out   = $public['reviews'];
		$dist  = $public['dist'];
	} else {
		$total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM `{$table}` WHERE post_id = %d AND status = 1",
			$post_id
		) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT r.id, r.score, r.content, r.created_at, m.name AS member_name, m.avatar_url
			 FROM `{$table}` r
			 JOIN `{$members}` m ON m.id = r.member_id
			 WHERE r.post_id = %d AND r.status = 1
			 ORDER BY r.created_at DESC, r.id DESC
			 LIMIT %d OFFSET %d",
			$post_id, $limit, $offset
		), ARRAY_A );

		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array(
				'id'            => (int) $r['id'],
				'score'         => (int) $r['score'],
				'content'       => $r['content'],
				'member_name'   => $r['member_name'],
				'member_avatar' => $r['avatar_url'],
				'date'          => $r['created_at'],
			);
		}

		// Per-star histogram of visible member reviews — the Play-Store "analysis" bars.
		$dist_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT score, COUNT(*) AS c FROM `{$table}` WHERE post_id = %d AND status = 1 GROUP BY score",
			$post_id
		), ARRAY_A );
		$dist = array( '1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0 );
		foreach ( (array) $dist_rows as $dr ) {
			$dist[ (string) (int) $dr['score'] ] = (int) $dr['c'];
		}

		if ( $cacheable ) {
			set_transient( rf_ratings_cache_key( $post_id ), array( 'total' => $total, 'reviews' => $out, 'dist' => $dist ), RF_RATINGS_CACHE_TTL );
		}
	}

	$resp = array(
		'ok'       => true,
		'reviews'  => $out,
		'has_more' => ( $offset + count( $out ) ) < $total,
		'total'    => $total,
		'dist'     => $dist,
		'post'     => array(
			'avg'   => (float) get_post_meta( $post_id, 'rating_avg', true ),
			'count' => (int) get_post_meta( $post_id, 'rating_count', true ),
			'score' => rom_rating( $post_id )['score'], // rounded for display, as the templates show it
		),
	);

	// The caller's own rating (when signed in) — lets the UI show it read-only.
	$member = rf_current_member();
	if ( $member ) {
		$mine = $wpdb->get_row( $wpdb->prepare(
			"SELECT score, content, created_at FROM `{$table}` WHERE member_id = %d AND post_id = %d",
			(int) $member['id'], $post_id
		), ARRAY_A );
		$resp['mine'] = $mine
			? array( 'score' => (int) $mine['score'], 'content' => $mine['content'], 'created_at' => $mine['created_at'] )
			: null;
	}

	return new WP_REST_Response( $resp, 200 );
}

/**
 * GET /wp-json/rf/v1/my-ratings?offset=&limit=
 * The member's own ratings across posts (profile dashboard), newest-first, paginated.
 * Allowed while blocked (read-only, like my-reports). LEFT JOIN posts so a rating on a
 * deleted post still lists.
 */
function rf_rest_my_ratings( WP_REST_Request $request ) {
	if ( ! rf_verify_request_origin( $request ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'bad_origin' ), 403 );
	}
	$member = rf_current_member();
	if ( ! $member ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'auth_required' ), 401 );
	}

	$limit = absint( $request->get_param( 'limit' ) );
	if ( $limit < 1 || $limit > 50 ) {
		$limit = 10;
	}
	$offset = absint( $request->get_param( 'offset' ) );

	global $wpdb;
	$table     = rf_member_rating_table();
	$posts     = $wpdb->prefix . 'posts';
	$member_id = (int) $member['id'];

	$total = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM `{$table}` WHERE member_id = %d",
		$member_id
	) );

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT r.id, r.post_id, r.score, r.content, r.created_at, p.post_title
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
			'score'   => (int) $r['score'],
			'content' => $r['content'],
			'date'    => $r['created_at'],
			'post'    => array(
				'id'    => (int) $r['post_id'],
				'title' => $r['post_title'], // null when the post was deleted
			),
		);
	}

	return new WP_REST_Response( array(
		'ok'       => true,
		'ratings'  => $out,
		'has_more' => ( $offset + count( $out ) ) < $total,
		'total'    => $total,
	), 200 );
}
