<?php
/**
 * Tracking beacon endpoint.
 *
 * Reached as POST /t. nginx maps that URI straight at this file through
 * SCRIPT_FILENAME, so the request never enters the WordPress rewrite rules.
 *
 * This file deliberately does NOT load WordPress. A page view must not cost a
 * full bootstrap to run a single Redis command: going through admin-ajax.php
 * pulls in ~1,700 files and ~68 MB to do the work this does in ~2 MB.
 *
 * Counts land in a Redis hash and reach the database in batches via flush.php.
 * Checking that the post exists happens there, where a database connection is
 * already open; doing it here would reintroduce the cost this endpoint exists
 * to avoid, and an unknown id is harmless until it is written.
 *
 * @package romsvault
 * @since 3.0
 */

require_once __DIR__ . '/config.php';

/*
 * Answer before doing any work. The browser sends this with
 * navigator.sendBeacon() and ignores the response body, and a dropped count
 * must never surface to the visitor as an error.
 */
http_response_code( 204 );

$post_id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
if ( $post_id < 1 ) {
    exit;
}

$key = ( isset( $_POST['a'] ) && 'd' === $_POST['a'] )
    ? RFTRK_KEY_DOWNLOADS
    : RFTRK_KEY_VIEWS;

try {
    $redis = new Redis();

    $connected = $redis->pconnect(
        RFTRK_REDIS_HOST,
        RFTRK_REDIS_PORT,
        RFTRK_REDIS_TIMEOUT,
        RFTRK_REDIS_PERSISTENT_ID,
        0,                      // Retry interval.
        RFTRK_REDIS_TIMEOUT     // Read timeout.
    );

    if ( $connected ) {
        $redis->hIncrBy( $key, $post_id, 1 );
    }
} catch ( \Throwable $e ) {
    /*
     * Tracking is best effort. Losing a count is preferable to holding a
     * PHP-FPM worker, or to falling back to a direct database write, which
     * would turn a Redis outage into a write storm on postmeta.
     */
}
