<?php
/**
 * Member auth — Google Sign-In controller for /wp-json/rf/v1 (login/logout).
 * Verifies the Google ID token (RS256 via JWKS, dependency-free), upserts the
 * member row, and mints the Redis session. Shared primitives (session, origin
 * guard, current-member, throttle) live in member-core.php; per-feature member
 * actions (comment, wishlist, download) live in auth/actions/*.
 *
 * SECURITY MODEL (plan §4.2 / §12.F / §13.O):
 * - No WP nonce (front-end is Cloudflare-cached -> a baked nonce goes stale/shared).
 * - CSRF defense = same-origin check + required custom header X-RF-Auth + the
 *   Google ID-token signature (rf_verify_request_origin lives in member-core.php).
 *
 * OPS NOTE (COOP): the Google GSI popup uses window.postMessage between popup and
 * opener. Serve the front-end with `Cross-Origin-Opener-Policy: same-origin-allow-popups`
 * (set in nginx; static header, cache-safe). A `same-origin` value breaks the popup
 * callback in strict browsers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register auth REST routes (login / logout).
 */
add_action( 'rest_api_init', function () {
	register_rest_route( RF_REST_NS, '/login', array(
		'methods'             => 'POST',
		'callback'            => 'rf_rest_login',
		'permission_callback' => '__return_true', // public endpoint; CSRF handled in-handler
	) );
	register_rest_route( RF_REST_NS, '/logout', array(
		'methods'             => 'POST',
		'callback'            => 'rf_rest_logout',
		'permission_callback' => '__return_true',
	) );
} );

// =============================================================================
// P2.3 — Google ID token verification (RS256 via Google JWKS). Dependency-free.
// =============================================================================

if ( ! defined( 'RF_GOOGLE_JWKS_URL' ) ) {
	define( 'RF_GOOGLE_JWKS_URL', 'https://www.googleapis.com/oauth2/v3/certs' );
}
if ( ! defined( 'RF_IAT_LEEWAY' ) ) {
	define( 'RF_IAT_LEEWAY', 300 ); // reject tokens issued more than 5 min ago (replay window)
}

/** base64url decode. */
function rf_b64url_decode( $data ) {
	$rem = strlen( $data ) % 4;
	if ( $rem ) {
		$data .= str_repeat( '=', 4 - $rem );
	}
	return base64_decode( strtr( $data, '-_', '+/' ) );
}

/** DER length prefix. */
function rf_der_len( $len ) {
	if ( $len < 128 ) {
		return chr( $len );
	}
	$bytes = '';
	while ( $len > 0 ) {
		$bytes = chr( $len & 0xff ) . $bytes;
		$len >>= 8;
	}
	return chr( 0x80 | strlen( $bytes ) ) . $bytes;
}

/** DER INTEGER from raw big-endian bytes (kept positive). */
function rf_der_uint( $bytes ) {
	$bytes = ltrim( $bytes, "\x00" );
	if ( $bytes === '' ) {
		$bytes = "\x00";
	}
	if ( ord( $bytes[0] ) & 0x80 ) {
		$bytes = "\x00" . $bytes; // leading zero so it isn't read as negative
	}
	return "\x02" . rf_der_len( strlen( $bytes ) ) . $bytes;
}

/**
 * Convert an RSA JWK (n, e as base64url) into a PEM public key so openssl_verify
 * can use it. Builds a standard SubjectPublicKeyInfo DER by hand.
 */
function rf_jwk_rsa_to_pem( $n_b64, $e_b64 ) {
	$n = rf_b64url_decode( $n_b64 );
	$e = rf_b64url_decode( $e_b64 );
	if ( $n === false || $e === false || $n === '' || $e === '' ) {
		return false;
	}
	$rsa = rf_der_uint( $n ) . rf_der_uint( $e );
	$rsa = "\x30" . rf_der_len( strlen( $rsa ) ) . $rsa;                 // SEQUENCE (RSAPublicKey)
	$alg = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00"; // AlgId rsaEncryption + NULL
	$bit = "\x03" . rf_der_len( strlen( $rsa ) + 1 ) . "\x00" . $rsa;    // BIT STRING
	$spki = "\x30" . rf_der_len( strlen( $alg ) + strlen( $bit ) ) . $alg . $bit;
	return "-----BEGIN PUBLIC KEY-----\r\n"
		. chunk_split( base64_encode( $spki ), 64, "\r\n" )
		. "-----END PUBLIC KEY-----\r\n";
}

/**
 * Google's signing keys as [ kid => ['n','e','alg'] ]. Cached in Redis (reusing
 * rf_redis()); on Redis-down it fetches live (no cache). Returns null when the
 * keys cannot be obtained at all (caller returns a soft 503 — never a fallback
 * that would trust an unverified token).
 */
function rf_google_jwks( $force = false ) {
	$cache_key = ( defined( 'WP_REDIS_PREFIX' ) ? WP_REDIS_PREFIX : '' ) . 'rf_google_jwks';
	$redis     = function_exists( 'rf_redis' ) ? rf_redis() : null;

	// At most one forced refresh a minute. A forged token only has to carry an
	// unknown kid to force one, and without this every such login attempt would
	// cost a request to Google.
	if ( $force && $redis ) {
		try {
			if ( ! $redis->set( $cache_key . ':refresh', 1, array( 'nx', 'ex' => 60 ) ) ) {
				$force = false;
			}
		} catch ( \Throwable $e ) {}
	}

	if ( ! $force && $redis ) {
		try {
			$cached = $redis->get( $cache_key );
			if ( $cached ) {
				$keys = json_decode( $cached, true );
				if ( is_array( $keys ) && $keys ) {
					return $keys;
				}
			}
		} catch ( \Throwable $e ) {}
	}

	$resp = wp_remote_get( RF_GOOGLE_JWKS_URL, array( 'timeout' => 5 ) );
	if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
		return null;
	}
	$body = json_decode( wp_remote_retrieve_body( $resp ), true );
	if ( empty( $body['keys'] ) || ! is_array( $body['keys'] ) ) {
		return null;
	}

	$keys = array();
	foreach ( $body['keys'] as $k ) {
		if ( isset( $k['kid'], $k['n'], $k['e'] ) ) {
			$keys[ $k['kid'] ] = array( 'n' => $k['n'], 'e' => $k['e'], 'alg' => $k['alg'] ?? 'RS256' );
		}
	}

	if ( $redis && $keys ) {
		try {
			$ttl = 3600;
			$cc  = wp_remote_retrieve_header( $resp, 'cache-control' );
			if ( $cc && preg_match( '/max-age=(\d+)/', $cc, $m ) ) {
				$ttl = max( 300, min( (int) $m[1], 86400 ) );
			}
			$redis->setex( $cache_key, $ttl, wp_json_encode( $keys ) );
		} catch ( \Throwable $e ) {}
	}
	return $keys;
}

/**
 * Verify a Google GSI ID token. Returns the payload (claims) on success or a
 * WP_Error on failure. Enforces: RS256 signature against Google JWKS, aud ===
 * GOOGLE_CLIENT_ID, valid iss, exp in the future, iat fresh, email_verified,
 * and (when supplied) the client-generated nonce.
 */
function rf_verify_google_id_token( $jwt, $expected_nonce = '' ) {
	if ( ! is_string( $jwt ) || substr_count( $jwt, '.' ) !== 2 ) {
		return new WP_Error( 'bad_token', 'Malformed token' );
	}
	list( $h64, $p64, $s64 ) = explode( '.', $jwt );
	$header  = json_decode( rf_b64url_decode( $h64 ), true );
	$payload = json_decode( rf_b64url_decode( $p64 ), true );
	$sig     = rf_b64url_decode( $s64 );
	if ( ! is_array( $header ) || ! is_array( $payload ) || $sig === false || $sig === '' ) {
		return new WP_Error( 'bad_token', 'Undecodable token' );
	}
	if ( ( $header['alg'] ?? '' ) !== 'RS256' || empty( $header['kid'] ) ) {
		return new WP_Error( 'bad_alg', 'Unexpected token algorithm' );
	}

	$keys = rf_google_jwks();
	if ( null === $keys ) {
		return new WP_Error( 'jwks_unavailable', 'Auth temporarily unavailable', array( 'status' => 503 ) );
	}
	$kid = $header['kid'];
	if ( ! isset( $keys[ $kid ] ) ) {
		$keys = rf_google_jwks( true ); // key rotation: force one refresh
		if ( null === $keys || ! isset( $keys[ $kid ] ) ) {
			return new WP_Error( 'unknown_kid', 'Unknown signing key' );
		}
	}

	$pem = rf_jwk_rsa_to_pem( $keys[ $kid ]['n'], $keys[ $kid ]['e'] );
	if ( ! $pem ) {
		return new WP_Error( 'key_error', 'Could not build verify key' );
	}
	if ( 1 !== openssl_verify( $h64 . '.' . $p64, $sig, $pem, OPENSSL_ALGO_SHA256 ) ) {
		return new WP_Error( 'bad_signature', 'Signature verification failed' );
	}

	$client_id = defined( 'GOOGLE_CLIENT_ID' ) ? GOOGLE_CLIENT_ID : '';
	$now       = time();
	$valid_iss = array( 'accounts.google.com', 'https://accounts.google.com' );

	if ( $client_id === '' || ( $payload['aud'] ?? '' ) !== $client_id ) {
		return new WP_Error( 'bad_aud', 'Wrong audience' );
	}
	if ( ! in_array( $payload['iss'] ?? '', $valid_iss, true ) ) {
		return new WP_Error( 'bad_iss', 'Wrong issuer' );
	}
	if ( (int) ( $payload['exp'] ?? 0 ) <= $now ) {
		return new WP_Error( 'expired', 'Token expired' );
	}
	if ( (int) ( $payload['iat'] ?? 0 ) < ( $now - RF_IAT_LEEWAY ) ) {
		return new WP_Error( 'stale', 'Token too old' );
	}
	if ( empty( $payload['email_verified'] ) || empty( $payload['email'] ) ) {
		return new WP_Error( 'unverified_email', 'Email not verified' );
	}
	// The nonce ties the token to the sign-in the client started, so a token minted
	// for another flow is refused. It is not replay protection: the client picks
	// the nonce, and anyone holding the token can read it from the payload.
	if ( ! empty( $payload['nonce'] ) && ! hash_equals( (string) $payload['nonce'], (string) $expected_nonce ) ) {
		return new WP_Error( 'bad_nonce', 'Nonce mismatch' );
	}
	if ( empty( $payload['sub'] ) ) {
		return new WP_Error( 'no_sub', 'Missing subject' );
	}
	// Replay protection: each token signs in once. Keyed by its signature, which
	// is unique per token, and kept until the token would expire anyway.
	$redis = function_exists( 'rf_redis' ) ? rf_redis() : null;
	if ( $redis ) {
		try {
			$used_key = ( defined( 'WP_REDIS_PREFIX' ) ? WP_REDIS_PREFIX : '' ) . 'rf_jti:' . hash( 'sha256', $s64 );
			$ttl      = max( 60, (int) $payload['exp'] - $now );
			if ( ! $redis->set( $used_key, 1, array( 'nx', 'ex' => $ttl ) ) ) {
				return new WP_Error( 'token_reused', 'Token already used' );
			}
		} catch ( \Throwable $e ) {}
	}
	return $payload;
}

/**
 * Login error response — carries a human message so the client shows it as-is
 * (no user-facing strings defined client-side). Unknown codes get a generic one.
 */
function rf_login_error( $code, $status ) {
	$messages = array(
		'blocked'             => __( 'Your account has been blocked.', 'rom' ),
		'rate_limited'        => __( 'Too many attempts. Please wait a moment and try again.', 'rom' ),
		'session_unavailable' => __( 'Sign-in is temporarily unavailable. Please try again shortly.', 'rom' ),
	);
	$message = isset( $messages[ $code ] ) ? $messages[ $code ] : __( 'Sign-in failed. Please try again.', 'rom' );
	return new WP_REST_Response( array( 'ok' => false, 'error' => $code, 'message' => $message ), $status );
}

/**
 * POST /wp-json/rf/v1/login   body: { credential, nonce }
 */
function rf_rest_login( WP_REST_Request $request ) {
	if ( ! rf_verify_request_origin( $request ) ) {
		return rf_login_error( 'bad_origin', 403 );
	}

	$params     = $request->get_json_params();
	$credential = isset( $params['credential'] ) ? (string) $params['credential'] : '';
	$nonce      = isset( $params['nonce'] ) ? (string) $params['nonce'] : '';

	if ( $credential === '' ) {
		return rf_login_error( 'missing_credential', 400 );
	}

	$payload = rf_verify_google_id_token( $credential, $nonce );
	if ( is_wp_error( $payload ) ) {
		$data   = $payload->get_error_data();
		$status = ( is_array( $data ) && isset( $data['status'] ) ) ? (int) $data['status'] : 401;
		return rf_login_error( $payload->get_error_code(), $status );
	}

	$member = rf_member_upsert( $payload );
	if ( is_wp_error( $member ) ) {
		$data   = $member->get_error_data();
		$status = ( is_array( $data ) && isset( $data['status'] ) ) ? (int) $data['status'] : 500;
		return rf_login_error( $member->get_error_code(), $status );
	}

	// Login counts toward the shared per-member action throttle (§16, 6/min): a
	// re-login is an action too. Keyed by member_id (resolved above), so repeated
	// logins of the same account are capped. (Pre-auth/invalid-token flooding is a
	// separate per-IP concern — §7.B.)
	if ( ! rf_check_action_rate( (int) $member['id'] ) ) {
		return rf_login_error( 'rate_limited', 429 );
	}

	// Re-login from the same browser (e.g. after localStorage was cleared) still
	// sends the previous rf_member cookie here (path matches). Destroy that stale
	// Redis session before minting a new one so orphan sessions don't pile up.
	$old_sid = isset( $_COOKIE[ RF_SESSION_COOKIE ] ) ? (string) $_COOKIE[ RF_SESSION_COOKIE ] : '';
	if ( $old_sid !== '' ) {
		rf_session_destroy( $old_sid );
	}

	$sid = rf_session_create( $member['id'] );
	if ( ! $sid ) {
		// Redis down: soft error. NEVER fall back to wp_set_auth_cookie (would set a
		// WP cookie on the front-end and break Cloudflare APO caching — plan §12.D).
		return rf_login_error( 'session_unavailable', 503 );
	}
	rf_set_session_cookie( $sid );

	return new WP_REST_Response( array(
		'ok'      => true,
		'stage'   => 'session',
		'display' => array(
			'name'       => $member['name'],
			'email'      => $member['email'],
			'avatar'     => $member['avatar_url'],
			'created_at' => $member['created_at'], // GMT; client formats "Member since" (no per-view query)
			'exp'        => time() + RF_SESSION_TTL,
		),
	), 200 );
}

/**
 * Find-or-create a member by google_sub (stable identity). Returns an assoc row
 * [id, blocked, email, name, avatar_url, created_at] or WP_Error (blocked -> 403). Refreshes
 * email/name/avatar + last_login_at on each login; handles the UNIQUE(sub) race.
 */
function rf_member_upsert( array $claims ) {
	global $wpdb;
	$table = rf_member_table();
	$sub   = (string) ( $claims['sub'] ?? '' );
	if ( $sub === '' ) {
		return new WP_Error( 'no_sub', 'Missing subject' );
	}
	$email  = sanitize_email( $claims['email'] ?? '' );
	$name   = isset( $claims['name'] ) ? sanitize_text_field( $claims['name'] ) : '';
	$avatar = isset( $claims['picture'] ) ? esc_url_raw( $claims['picture'] ) : '';
	$now    = current_time( 'mysql', true ); // GMT

	$existing = $wpdb->get_row(
		$wpdb->prepare( "SELECT id, blocked, created_at FROM `{$table}` WHERE google_sub = %s", $sub ),
		ARRAY_A
	);

	if ( $existing ) {
		$id = (int) $existing['id'];
		if ( (int) $existing['blocked'] === 1 ) {
			return new WP_Error( 'blocked', 'Account is blocked', array( 'status' => 403 ) );
		}
		$wpdb->update(
			$table,
			array( 'email' => $email, 'name' => $name, 'avatar_url' => $avatar, 'last_login_at' => $now ),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
		return array( 'id' => $id, 'blocked' => 0, 'email' => $email, 'name' => $name, 'avatar_url' => $avatar, 'created_at' => $existing['created_at'] );
	}

	$ok = $wpdb->insert(
		$table,
		array(
			'google_sub'    => $sub,
			'email'         => $email,
			'name'          => $name,
			'avatar_url'    => $avatar,
			'blocked'       => 0,
			'created_at'    => $now,
			'last_login_at' => $now,
		),
		array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
	);
	if ( ! $ok ) {
		// UNIQUE(google_sub) race: another request created it between SELECT and INSERT.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, created_at FROM `{$table}` WHERE google_sub = %s", $sub ), ARRAY_A );
		if ( $row ) {
			return array( 'id' => (int) $row['id'], 'blocked' => 0, 'email' => $email, 'name' => $name, 'avatar_url' => $avatar, 'created_at' => $row['created_at'] );
		}
		return new WP_Error( 'db_error', 'Could not create member', array( 'status' => 500 ) );
	}
	return array( 'id' => (int) $wpdb->insert_id, 'blocked' => 0, 'email' => $email, 'name' => $name, 'avatar_url' => $avatar, 'created_at' => $now );
}

/**
 * POST /wp-json/rf/v1/logout — destroy the current session and clear the cookie.
 * (localStorage is cleared client-side; server just kills the Redis session.)
 */
function rf_rest_logout( WP_REST_Request $request ) {
	if ( ! rf_verify_request_origin( $request ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'bad_origin' ), 403 );
	}
	$sid = isset( $_COOKIE[ RF_SESSION_COOKIE ] ) ? (string) $_COOKIE[ RF_SESSION_COOKIE ] : '';
	if ( $sid !== '' ) {
		rf_session_destroy( $sid );
	}
	rf_set_session_cookie( '', time() - 3600 ); // expire at the same path
	return new WP_REST_Response( array( 'ok' => true ), 200 );
}
