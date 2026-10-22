<?php
/**
 * Member core — shared foundation for the rf/v1 member API: request-origin
 * (CSRF) guard, the shared Redis connection, Redis-backed session
 * (create/get/destroy + cookie), current member resolver, per-member action
 * throttle, and the restricted-post helper.
 * Loaded before auth-google.php and every auth/actions/* module, which all
 * depend on these primitives. NO WP auth cookie is ever set, so Cloudflare APO
 * keeps the front-end cached (plan §12.D).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'RF_REST_NS' ) ) {
	define( 'RF_REST_NS', 'rf/v1' );
}

/**
 * Our rf/v1 endpoints authenticate members via the rf_member session, never WP
 * cookies. But a visitor who is ALSO logged into wp-admin sends the WP auth
 * cookie to /wp-json (fetch credentials: include); core then rejects the request
 * with `rest_cookie_invalid_nonce` (no X-WP-Nonce) before our handler runs — so
 * a staff-who-is-also-a-member gets a bogus 401/403 and looks logged out. Clear
 * that specific error for our namespace only (runs after core's check at 100).
 *
 * The namespace is matched on the dispatched route, never on a substring of the
 * URI: "rf/v1/" in a query string used to wave a core route through with a bad
 * nonce while still logged in as the WP user, which voided the REST API's CSRF
 * protection. The WP user is also dropped, since our handlers authenticate by
 * the member session and never read it.
 */
add_filter( 'rest_authentication_errors', function ( $result ) {
	if ( ! is_wp_error( $result ) || $result->get_error_code() !== 'rest_cookie_invalid_nonce' ) {
		return $result;
	}
	$route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? '/' . ltrim( (string) $GLOBALS['wp']->query_vars['rest_route'], '/' ) : '';
	if ( strpos( $route, '/' . RF_REST_NS . '/' ) !== 0 ) {
		return $result;
	}
	wp_set_current_user( 0 );
	return true;
}, 101 );

/**
 * True only for same-origin requests carrying our custom header.
 * Cross-site JS cannot set X-RF-Auth, and the Origin host must match this site.
 */
function rf_verify_request_origin( WP_REST_Request $request ): bool {
	if ( $request->get_header( 'x-rf-auth' ) !== '1' ) {
		return false;
	}
	$origin = $request->get_header( 'origin' );
	if ( empty( $origin ) ) {
		// Some same-origin POSTs omit Origin; fall back to the Referer host.
		$origin = $request->get_header( 'referer' );
	}
	if ( empty( $origin ) ) {
		return false;
	}
	$origin_host = wp_parse_url( $origin, PHP_URL_HOST );
	$site_host   = wp_parse_url( home_url(), PHP_URL_HOST );
	return $origin_host && $site_host && strcasecmp( $origin_host, $site_host ) === 0;
}

/**
 * Get a dedicated phpredis connection, reusing the site's WP_REDIS_* config.
 *
 * Every session below depends on this, so removing it logs members out. It is
 * intentionally independent of the Redis Object Cache plugin: enabling or
 * disabling that plugin does not affect sessions. Returns null (never throws)
 * when phpredis is missing or Redis is unreachable, so callers can fall back.
 *
 * @return \Redis|null
 */
function rf_redis() {
	static $redis = null;
	static $tried = false;

	if ( $tried ) {
		return $redis;
	}
	$tried = true;

	if ( ! class_exists( 'Redis' ) ) {
		return $redis = null;
	}

	try {
		$r       = new Redis();
		$host    = defined( 'WP_REDIS_HOST' ) ? WP_REDIS_HOST : '127.0.0.1';
		$port    = defined( 'WP_REDIS_PORT' ) ? (int) WP_REDIS_PORT : 6379;
		$timeout = defined( 'WP_REDIS_TIMEOUT' ) ? (float) WP_REDIS_TIMEOUT : 1.0;

		// Persistent connection so we don't reconnect on every request.
		if ( ! $r->pconnect( $host, $port, $timeout, 'rf_track' ) ) {
			return $redis = null;
		}
		if ( defined( 'WP_REDIS_PASSWORD' ) && WP_REDIS_PASSWORD ) {
			$r->auth( WP_REDIS_PASSWORD );
		}
		if ( defined( 'WP_REDIS_DATABASE' ) && WP_REDIS_DATABASE ) {
			$r->select( (int) WP_REDIS_DATABASE );
		}
		$redis = $r;
	} catch ( \Throwable $e ) {
		error_log( '[RF] Redis connect failed: ' . $e->getMessage() );
		$redis = null;
	}

	return $redis;
}

if ( ! defined( 'RF_SESSION_TTL' ) ) {
	define( 'RF_SESSION_TTL', 7 * DAY_IN_SECONDS ); // override in wp-config.php if desired
}
if ( ! defined( 'RF_SESSION_COOKIE' ) ) {
	define( 'RF_SESSION_COOKIE', 'rf_member' );
}
if ( ! defined( 'RF_SESSION_PATH' ) ) {
	define( 'RF_SESSION_PATH', '/wp-json/rf/v1' ); // cookie scope: dynamic REST only, never front-end HTML
}

/**
 * Redis key of a session.
 *
 * Deliberately outside WP_REDIS_PREFIX: the Redis Object Cache plugin flushes by
 * SCAN MATCH '<prefix>*', which would log every member out on a cache flush. It
 * still names the site, so a session id minted by another site sharing this
 * Redis cannot resolve to a member id here.
 */
function rf_session_key( $id ) {
	$site = defined( 'WP_REDIS_PREFIX' ) && WP_REDIS_PREFIX ? rtrim( WP_REDIS_PREFIX, '_:' ) : (string) wp_parse_url( home_url(), PHP_URL_HOST );
	return 'rfsess:' . $site . ':' . $id;
}

/**
 * Create a Redis-backed session for a member. Returns the opaque 256-bit session
 * id, or null when Redis is unavailable (caller surfaces a soft error — never a
 * WP-cookie fallback). No session index (plan §13.T): block relies on the
 * per-endpoint member.blocked re-check + TTL expiry.
 */
function rf_session_create( $member_id ): ?string {
	$redis = function_exists( 'rf_redis' ) ? rf_redis() : null;
	if ( ! $redis ) {
		return null;
	}
	try {
		$id  = bin2hex( random_bytes( 32 ) );
		$exp = time() + RF_SESSION_TTL;
		$redis->setex(
			rf_session_key( $id ),
			RF_SESSION_TTL,
			wp_json_encode( array( 'member_id' => (int) $member_id, 'exp' => $exp ) )
		);
		return $id;
	} catch ( \Throwable $e ) {
		error_log( '[RF Auth] session create failed: ' . $e->getMessage() );
		return null;
	}
}

/**
 * Resolve a session id -> ['member_id'=>int,'exp'=>int], or null if
 * missing/expired/invalid. Used by every dynamic endpoint (P3+).
 */
function rf_session_get( $id ): ?array {
	if ( ! is_string( $id ) || ! ctype_xdigit( $id ) ) {
		return null;
	}
	$redis = function_exists( 'rf_redis' ) ? rf_redis() : null;
	if ( ! $redis ) {
		return null;
	}
	try {
		$raw = $redis->get( rf_session_key( $id ) );
		if ( ! $raw ) {
			return null;
		}
		$data = json_decode( $raw, true );
		return ( is_array( $data ) && isset( $data['member_id'] ) ) ? $data : null;
	} catch ( \Throwable $e ) {
		return null;
	}
}

/**
 * Destroy a session (logout — P2.6).
 */
function rf_session_destroy( $id ) {
	if ( ! is_string( $id ) || ! ctype_xdigit( $id ) ) {
		return;
	}
	$redis = function_exists( 'rf_redis' ) ? rf_redis() : null;
	if ( ! $redis ) {
		return;
	}
	try {
		$redis->del( rf_session_key( $id ) );
	} catch ( \Throwable $e ) {}
}

/**
 * Send (or, with $expire in the past, clear) the rf_member cookie. HttpOnly +
 * SameSite=Lax, scoped to RF_SESSION_PATH so it NEVER reaches cached front-end
 * HTML. Secure whenever the request is over https.
 */
function rf_set_session_cookie( $session_id, $expire = null ): void
{
	$expires = ( $expire === null ) ? ( time() + RF_SESSION_TTL ) : $expire;
	setcookie( RF_SESSION_COOKIE, $session_id, array(
		'expires'  => $expires,
		'path'     => RF_SESSION_PATH,
		'secure'   => is_ssl(),
		'httponly' => true,
		'samesite' => 'Lax',
	) );
}

/**
 * A downloadable post is "restricted" (login required) only when `hide` is
 * exactly 'restricted'. 2-value model: public | restricted. Any other stored
 * value (empty, 'public', or legacy values still in the DB) is treated as
 * public so old data can't accidentally gate a post.
 */
function rf_is_restricted( $post_id ): bool {
	$hide = get_field( 'hide', $post_id );
	return $hide === 'restricted';
}

/**
 * Resolve the current member from the rf_member cookie, or null. Returns an
 * assoc row [id, blocked, name, email, avatar_url]. Used by all member endpoints.
 */
function rf_current_member() {
	$sid = isset( $_COOKIE[ RF_SESSION_COOKIE ] ) ? (string) $_COOKIE[ RF_SESSION_COOKIE ] : '';
	if ( $sid === '' ) {
		return null;
	}
	$sess = rf_session_get( $sid );
	if ( ! $sess || empty( $sess['member_id'] ) ) {
		return null;
	}
	global $wpdb;
	$table = rf_member_table();
	$row   = $wpdb->get_row(
		$wpdb->prepare( "SELECT id, blocked, name, email, avatar_url FROM `{$table}` WHERE id = %d", (int) $sess['member_id'] ),
		ARRAY_A
	);
	return $row ?: null;
}

if ( ! defined( 'RF_ACTION_LIMIT' ) ) {
	define( 'RF_ACTION_LIMIT', 30 ); // max member actions per window (comment/report/wishlist/login share one bucket — §16)
}
if ( ! defined( 'RF_ACTION_WINDOW' ) ) {
	define( 'RF_ACTION_WINDOW', 180 ); // seconds (30 actions / 3 min ≈ 10/min sustained)
}

/**
 * Per-member fixed-window action throttle (plan §16). One shared bucket for all
 * member actions, keyed by member_id (not IP — CGNAT). Returns true if the
 * action is allowed. TTL is set only on the first hit so it does not slide.
 * Fail-open when Redis is down (throttle is best-effort, never blocks a real
 * member because the cache is unavailable).
 */
function rf_check_action_rate( $member_id ): bool {
	$redis = function_exists( 'rf_redis' ) ? rf_redis() : null;
	if ( ! $redis ) {
		return true;
	}
	try {
		$key = ( defined( 'WP_REDIS_PREFIX' ) ? WP_REDIS_PREFIX : '' ) . 'rf_action_rl:' . (int) $member_id;
		$n   = (int) $redis->incr( $key );
		if ( $n === 1 ) {
			$redis->expire( $key, RF_ACTION_WINDOW ); // set TTL once; never slide it
		}
		return $n <= RF_ACTION_LIMIT;
	} catch ( \Throwable $e ) {
		return true;
	}
}
