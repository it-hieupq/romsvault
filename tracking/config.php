<?php
/**
 * Shared constants for the view / download tracking subsystem.
 *
 * track.php loads this file and nothing else, so it must stay free of logic,
 * includes and function definitions: every byte here is paid once per page
 * view. Keep it to define() calls.
 *
 * @package romsvault
 * @since 3.0
 */

/**
 * Redis connection. Production keeps Redis on the web host, so the beacon
 * (PHP-FPM), the flush (CLI) and the admin status all connect to 127.0.0.1.
 */
define( 'RFTRK_REDIS_HOST', '127.0.0.1' );
define( 'RFTRK_REDIS_PORT', 6379 );

/**
 * Connect and read timeouts for the beacon, in seconds.
 *
 * Both matter. Without a read timeout, a Redis that accepts connections but
 * stops answering pins the PHP-FPM worker until max_execution_time.
 */
define( 'RFTRK_REDIS_TIMEOUT', 1 );

/**
 * The flush runs from cron and can afford to wait longer than a visitor-facing
 * request.
 */
define( 'RFTRK_REDIS_TIMEOUT_CLI', 2.0 );

/**
 * Persistent connection id. phpredis keeps one socket per worker per id, so the
 * hot path never pays a TCP handshake.
 */
define( 'RFTRK_REDIS_PERSISTENT_ID', 'rftrk' );

/**
 * Buffer keys, both hashes of {post_id => pending delta}.
 *
 * The prefix deliberately does NOT start with WP_REDIS_PREFIX ('romsvault_'):
 * the Redis Object Cache plugin flushes with SCAN MATCH 'romsvault_*' + DEL,
 * which would wipe counts that have not reached the database yet.
 *
 * They carry no TTL on purpose. An abandoned buffer is bounded by the number of
 * posts (a few MB), whereas an expiring one drops counts with no trace. A dead
 * cron is caught by the heartbeat below, not by letting Redis discard data.
 */
define( 'RFTRK_KEY_VIEWS', 'rftrk:pending_views' );
define( 'RFTRK_KEY_DOWNLOADS', 'rftrk:pending_downloads' );

/** Suffix for the copy a flush is currently draining. */
define( 'RFTRK_STASH_SUFFIX', ':flushing' );

/** Unix timestamp of the last successful flush. Monitor it to detect a dead cron. */
define( 'RFTRK_KEY_HEARTBEAT', 'rftrk:last_flush' );

/** Rows per SQL statement when writing a batch back to the database. */
define( 'RFTRK_SQL_CHUNK', 2000 );
