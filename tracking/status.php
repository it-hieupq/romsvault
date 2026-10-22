<?php
/**
 * Tracking health in wp-admin: Configs > General shows the last flush and what
 * is still waiting in Redis, and admins get a notice once the flush cron has
 * gone quiet. Loaded in admin only (functions.php).
 *
 * @package romsvault
 */

require_once __DIR__ . '/config.php';

// flush.php runs every 10 minutes; this long without one means the cron is down.
const RFTRK_STALE_AFTER = 30 * MINUTE_IN_SECONDS;

/**
 * Last flush time and pending buffer sizes, read live from Redis.
 *
 * @return array{last:int,views:int,downloads:int}|null Null when Redis cannot be reached.
 */
function rftrk_status(): ?array {
	if ( ! class_exists( 'Redis' ) ) {
		return null;
	}

	try {
		$redis = new Redis();
		if ( ! $redis->connect( RFTRK_REDIS_HOST, RFTRK_REDIS_PORT, RFTRK_REDIS_TIMEOUT, null, 0, RFTRK_REDIS_TIMEOUT ) ) {
			return null;
		}
		$status = array(
			'last'      => (int) $redis->get( RFTRK_KEY_HEARTBEAT ),
			'views'     => (int) $redis->hLen( RFTRK_KEY_VIEWS ),
			'downloads' => (int) $redis->hLen( RFTRK_KEY_DOWNLOADS ),
		);
		$redis->close();
	} catch ( \Throwable $e ) {
		return null;
	}

	set_transient( 'rftrk_status', $status, 5 * MINUTE_IN_SECONDS );

	return $status;
}

/**
 * rftrk_status() reused for 5 minutes, so the notice below does not open a
 * Redis connection on every admin page.
 *
 * @return array{last:int,views:int,downloads:int}|null
 */
function rftrk_status_cached(): ?array {
	$status = get_transient( 'rftrk_status' );

	return is_array( $status ) ? $status : rftrk_status();
}

/*
 * Only a flush that used to run and stopped raises the notice. A flush that has
 * never run (fresh install, cron not set up yet) and an unreachable Redis show
 * on the Tracking tab instead, so a dev box without either stays quiet.
 */
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$status = rftrk_status_cached();
	if ( ! $status || ! $status['last'] || time() - $status['last'] < RFTRK_STALE_AFTER ) {
		return;
	}

	/* translators: %s: time since the last flush */
	$message = sprintf( __( 'View and download counts last reached the database %s ago. Check the cron running tracking/flush.php.', 'rom' ), human_time_diff( $status['last'] ) );
	printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( $message ) );
} );

add_action( 'acf/render_field/key=tracking_status', function () {
	$status = rftrk_status();
	$lines  = array();

	if ( ! $status ) {
		/* translators: 1: Redis host, 2: Redis port */
		$lines[] = sprintf( __( 'Redis is not reachable at %1$s:%2$d: beacons are dropped and nothing can be flushed.', 'rom' ), RFTRK_REDIS_HOST, RFTRK_REDIS_PORT );
	} else {
		if ( ! $status['last'] ) {
			$lines[] = __( 'No flush has run yet. Schedule tracking/flush.php in the system cron every 10 minutes.', 'rom' );
		} else {
			/* translators: 1: time since the last flush, 2: its date and time */
			$lines[] = sprintf( __( 'Last flush %1$s ago (%2$s).', 'rom' ), human_time_diff( $status['last'] ), wp_date( 'Y-m-d H:i', $status['last'] ) );
			if ( time() - $status['last'] >= RFTRK_STALE_AFTER ) {
				$lines[] = __( 'That is overdue: the flush cron has probably stopped.', 'rom' );
			}
		}
		/* translators: 1: posts with new views, 2: posts with new downloads */
		$lines[] = sprintf( __( 'Waiting in Redis: %1$s posts with new views, %2$s with new downloads.', 'rom' ), number_format_i18n( $status['views'] ), number_format_i18n( $status['downloads'] ) );
	}

	foreach ( $lines as $line ) {
		echo '<p>' . esc_html( $line ) . '</p>';
	}
} );
