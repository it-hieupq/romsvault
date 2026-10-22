<?php
/**
 * Quick search index: static JSON shards in uploads/search-index/, one per
 * leading-letters key of the words in rom titles, plus a manifest.json that
 * lists every key. The browser loads one shard per query and filters in RAM,
 * so typing never reaches PHP. Design: .docs/search-index-plan.md.
 *
 * Every constant can be overridden from wp-config.php.
 */

// Defaults for the Configs > Live search fields, used while a field is empty.
defined( 'ROM_SEARCH_CRON_INTERVAL' ) || define( 'ROM_SEARCH_CRON_INTERVAL', HOUR_IN_SECONDS );
defined( 'ROM_SEARCH_BUILD_BATCH' ) || define( 'ROM_SEARCH_BUILD_BATCH', 500 );
// A two-letter shard holding more records than this splits into three-letter shards.
defined( 'ROM_SEARCH_SHARD_LIMIT' ) || define( 'ROM_SEARCH_SHARD_LIMIT', 1500 );
// Words too common to be worth a key. Records stay reachable through their other words.
defined( 'ROM_SEARCH_STOPWORDS' ) || define( 'ROM_SEARCH_STOPWORDS', 'the,of,and,no,de,la,le,el,an,in,to' );
// Dropdown behaviour, handed to js/main.js as window.romSearch.
defined( 'ROM_SEARCH_MAX_RESULTS' ) || define( 'ROM_SEARCH_MAX_RESULTS', 12 );
defined( 'ROM_SEARCH_QUERY_MIN' ) || define( 'ROM_SEARCH_QUERY_MIN', 2 );
defined( 'ROM_SEARCH_DEBOUNCE_MS' ) || define( 'ROM_SEARCH_DEBOUNCE_MS', 100 );

const ROM_SEARCH_VERSION_OPTION = 'rom_search_index_version';
const ROM_SEARCH_DIR            = 'search-index';
const ROM_SEARCH_MANIFEST       = 'manifest.json';
const ROM_SEARCH_JSON_FLAGS     = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
const ROM_SEARCH_CRON_HOOK      = 'rom_search_index_rebuild';
const ROM_SEARCH_SCHEDULE       = 'rom_search_index';
const ROM_SEARCH_REBUILD_ACTION = 'rom_search_rebuild';

/**
 * A Configs option, or $default while it is unset or empty.
 */
function rom_search_option( string $name, $default ) {
	$value = function_exists( 'get_field' ) ? get_field( $name, 'option' ) : null;

	return ( null === $value || '' === $value || false === $value ) ? $default : $value;
}

/**
 * Seconds between full rebuilds.
 */
function rom_search_cron_interval(): int {
	$minutes = (int) rom_search_option( 'search_cron_minutes', 0 );

	return $minutes > 0 ? min( $minutes, 1440 ) * MINUTE_IN_SECONDS : (int) ROM_SEARCH_CRON_INTERVAL;
}

/**
 * Posts per query during a full rebuild.
 */
function rom_search_build_batch(): int {
	$batch = (int) rom_search_option( 'search_build_batch', 0 );

	return $batch > 0 ? max( 50, min( $batch, 5000 ) ) : (int) ROM_SEARCH_BUILD_BATCH;
}

/**
 * Absolute path of the index directory, without a trailing slash.
 */
function rom_search_index_dir(): string {
	return wp_get_upload_dir()['basedir'] . '/' . ROM_SEARCH_DIR;
}

/**
 * Folded, de-duplicated words of a title. The JS fold must produce the same
 * words, or a query can pick a shard the record is not in.
 *
 * Besides the plain words, the title is read once more with every joiner
 * dropped, so "F.E.A.R." also yields "fear" and "Pac-Man" also "pacman".
 *
 * @return string[]
 */
function rom_search_words( string $title ): array {
	$folded = strtolower( remove_accents( html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
	preg_match_all( '/[a-z0-9]+/', $folded, $plain );
	preg_match_all( '/[a-z0-9]+/', preg_replace( '/[^a-z0-9\s]+/u', '', $folded ), $compact );

	return array_values( array_unique( array_merge( $plain[0], $compact[0] ) ) );
}

/**
 * Shard keys a title belongs to.
 *
 * @param array<string, true> $split Two-letter keys that are split into three-letter shards.
 * @return string[]
 */
function rom_search_keys( string $title, array $split ): array {
	static $stopwords = null;
	$stopwords ??= array_flip( explode( ',', ROM_SEARCH_STOPWORDS ) );

	$keys = array();
	foreach ( rom_search_words( $title ) as $word ) {
		if ( strlen( $word ) < 2 || isset( $stopwords[ $word ] ) ) {
			continue;
		}
		$key = substr( $word, 0, 2 );
		// Under a split key the two-letter shard holds only the bare word itself.
		if ( isset( $split[ $key ] ) && strlen( $word ) > 2 ) {
			$key = substr( $word, 0, 3 );
		}
		$keys[ $key ] = true;
	}

	return array_map( 'strval', array_keys( $keys ) );
}

/**
 * One index record: [id, title, path, thumb, console, size].
 *
 * @param string $img_base Uploads base URL with a trailing slash, stripped from thumbnails.
 */
function rom_search_record( WP_Post $post, string $img_base ): array {
	$consoles = get_the_terms( $post, 'console' );
	$console  = ( ! is_wp_error( $consoles ) && ! empty( $consoles ) ) ? html_entity_decode( $consoles[0]->name, ENT_QUOTES, 'UTF-8' ) : '';

	$size = get_post_meta( $post->ID, 'file_size', true );
	if ( empty( $size ) ) {
		$size = get_post_meta( $post->ID, 'download_list_0_file_size', true );
	}

	$thumb    = '';
	$thumb_id = get_post_thumbnail_id( $post );
	$src      = $thumb_id ? wp_get_attachment_image_src( $thumb_id, 'thumbnail' ) : false;
	if ( $src ) {
		$thumb = 0 === strpos( $src[0], $img_base ) ? substr( $src[0], strlen( $img_base ) ) : $src[0];
	}

	return array(
		$post->ID,
		html_entity_decode( $post->post_title, ENT_QUOTES, 'UTF-8' ),
		wp_make_link_relative( get_permalink( $post ) ),
		$thumb,
		$console,
		trim( (string) $size ),
	);
}

/**
 * Write a file so readers never see it half-written: temp file, then rename.
 */
function rom_search_write_file( string $file, string $data ): bool {
	$tmp = $file . '.' . uniqid( '', true ) . '.tmp';
	if ( false === file_put_contents( $tmp, $data, LOCK_EX ) || ! rename( $tmp, $file ) ) {
		@unlink( $tmp );
		return false;
	}

	return true;
}

/**
 * Run $fn while holding the index lock, so a full build and a patch never
 * interleave their read-modify-write of the same shard or manifest.
 *
 * @return mixed Whatever $fn returns.
 */
function rom_search_with_lock( callable $fn ) {
	$handle = fopen( rom_search_index_dir() . '/.lock', 'c' );
	if ( $handle ) {
		flock( $handle, LOCK_EX );
	}
	try {
		return $fn();
	} finally {
		if ( $handle ) {
			flock( $handle, LOCK_UN );
			fclose( $handle );
		}
	}
}

/**
 * The current manifest, or null when there is none or it does not decode.
 */
function rom_search_read_manifest(): ?array {
	$file = rom_search_index_dir() . '/' . ROM_SEARCH_MANIFEST;
	if ( ! is_readable( $file ) ) {
		return null;
	}
	$manifest = json_decode( (string) file_get_contents( $file ), true );

	return is_array( $manifest ) && isset( $manifest['keys'] ) ? $manifest : null;
}

/**
 * Rebuild every shard and the manifest from the published roms.
 *
 * Shards whose content hash is unchanged are left alone, so their cached
 * copies stay valid. The manifest is written last and the version only moves
 * when every write succeeded.
 *
 * @return int Number of roms indexed, or -1 on a write failure.
 */
function rom_search_index_build(): int {
	$dir = rom_search_index_dir();
	if ( ! wp_mkdir_p( $dir ) ) {
		return -1;
	}

	global $wpdb;
	$img_base = trailingslashit( wp_get_upload_dir()['baseurl'] );
	$records  = array(); // id => encoded record, newest first
	$titles   = array(); // id => title

	// All ids up front, then batches by id: paging with an offset skips a rom
	// whenever another one leaves publish while the build is running.
	$all_ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s ORDER BY post_date DESC, ID DESC",
		'rom',
		'publish'
	) ) );

	foreach ( array_chunk( $all_ids, rom_search_build_batch() ) as $chunk ) {
		$query = new WP_Query( array(
			'post_type'              => 'rom',
			'post_status'            => 'publish',
			'post__in'               => $chunk,
			'orderby'                => 'post__in',
			'posts_per_page'         => count( $chunk ),
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		) );
		update_post_thumbnail_cache( $query );

		foreach ( $query->posts as $post ) {
			$encoded = wp_json_encode( rom_search_record( $post, $img_base ), ROM_SEARCH_JSON_FLAGS );
			if ( false !== $encoded ) {
				$records[ $post->ID ] = $encoded;
				$titles[ $post->ID ]  = $post->post_title;
			}
		}

		// Keep memory flat across 100+ batches; the persistent cache is untouched.
		if ( function_exists( 'wp_cache_flush_runtime' ) ) {
			wp_cache_flush_runtime();
		}
	}

	// Decide which two-letter keys are too big, then assign the final keys.
	$counts = array();
	foreach ( $titles as $title ) {
		foreach ( rom_search_keys( $title, array() ) as $key ) {
			$counts[ $key ] = ( $counts[ $key ] ?? 0 ) + 1;
		}
	}
	$split = array();
	foreach ( $counts as $key => $count ) {
		if ( $count > ROM_SEARCH_SHARD_LIMIT ) {
			$split[ (string) $key ] = true;
		}
	}

	$shards = array(); // key => ids, newest first
	foreach ( $titles as $id => $title ) {
		foreach ( rom_search_keys( $title, $split ) as $key ) {
			$shards[ $key ][] = $id;
		}
	}
	ksort( $shards, SORT_STRING );

	// Only the write phase is locked: a patch landing while the DB was being
	// read is overwritten by this build and comes back on the next one.
	return rom_search_with_lock( static function () use ( $dir, $img_base, $records, $split, $shards ): int {
		$old_manifest = rom_search_read_manifest();
		$previous     = $old_manifest['keys'] ?? array();
		$keys         = array();
		foreach ( $shards as $key => $ids ) {
			$key  = (string) $key; // numeric keys like "20" come back as ints
			$json = '[' . implode( ',', array_map( static fn( $id ) => $records[ $id ], $ids ) ) . ']';
			$hash = hash( 'crc32b', $json );
			$file = $dir . '/' . $key . '.json';

			$unchanged = isset( $previous[ $key ][1] ) && $previous[ $key ][1] === $hash && is_file( $file );
			if ( ! $unchanged && ! rom_search_write_file( $file, $json ) ) {
				return -1;
			}
			$keys[ $key ] = array( count( $ids ), $hash );
		}

		$version = rom_search_next_version( $old_manifest );
		if ( ! rom_search_write_manifest( $version, count( $records ), $img_base, array_keys( $split ), $keys ) ) {
			return -1;
		}

		// Shards whose key is gone. Removed after the new manifest stops naming them.
		foreach ( glob( $dir . '/*.json' ) ?: array() as $file ) {
			$key = basename( $file, '.json' );
			if ( 'manifest' !== $key && ! isset( $keys[ $key ] ) ) {
				@unlink( $file );
			}
		}

		update_option( ROM_SEARCH_VERSION_OPTION, $version, true );

		return count( $records );
	} );
}

/**
 * Version for the next manifest. Strictly above the current one, so two
 * writes inside the same second still get distinct cache-busting URLs.
 * Called under the lock with the manifest read there.
 */
function rom_search_next_version( ?array $manifest ): int {
	return max( time(), (int) ( $manifest['v'] ?? 0 ) + 1 );
}

/**
 * Encode and write the manifest.
 *
 * @param string[]                          $split Split two-letter keys.
 * @param array<string, array{int, string}> $keys  key => [record count, content hash].
 */
function rom_search_write_manifest( int $version, int $count, string $img_base, array $split, array $keys ): bool {
	$manifest = wp_json_encode( array(
		'v'     => $version,
		'n'     => $count,
		'img'   => $img_base,
		'split' => array_map( 'strval', $split ),
		'keys'  => (object) $keys,
	), ROM_SEARCH_JSON_FLAGS );

	return false !== $manifest && rom_search_write_file( rom_search_index_dir() . '/' . ROM_SEARCH_MANIFEST, $manifest );
}

/**
 * Queue a rom for the end-of-request patch.
 *
 * @param string $title A title the rom may be indexed under. Several can pile
 *                      up for one id (the title before an edit, and after).
 */
function rom_search_index_queue( int $id, string $title ): void {
	global $rom_search_queue;
	if ( null === $rom_search_queue ) {
		$rom_search_queue = array();
		// shutdown passes no arguments here, so accept none.
		add_action( 'shutdown', 'rom_search_index_flush', 10, 0 );
	}
	$rom_search_queue[ $id ][] = $title;
}

// Anything entering or leaving publish, including an edit of a published rom.
add_action( 'transition_post_status', function ( $new_status, $old_status, $post ) {
	if ( 'rom' === $post->post_type && ( 'publish' === $new_status || 'publish' === $old_status ) ) {
		rom_search_index_queue( $post->ID, $post->post_title );
	}
}, 10, 3 );

// The title before the edit names the shards the rom has to leave.
add_action( 'post_updated', function ( $post_id, $post_after, $post_before ) {
	if ( 'rom' === $post_before->post_type && 'publish' === $post_before->post_status ) {
		rom_search_index_queue( (int) $post_id, $post_before->post_title );
	}
}, 10, 3 );

// A permanent delete never fires a transition.
add_action( 'deleted_post', function ( $post_id, $post = null ) {
	if ( $post instanceof WP_Post && 'rom' === $post->post_type && 'publish' === $post->post_status ) {
		rom_search_index_queue( (int) $post_id, $post->post_title );
	}
}, 10, 2 );

/**
 * Apply every queued rom to the index in one pass: each touched shard and the
 * manifest are read once and written once, however many roms changed.
 *
 * Posts are read back from the DB, so what lands is their final state in this
 * request. When there is no usable index to patch, the full rebuild is moved
 * up to now instead of running here, so a publish never waits on it.
 */
function rom_search_index_flush(): void {
	global $rom_search_queue;
	$queue            = $rom_search_queue ?: array();
	$rom_search_queue = null;
	if ( ! $queue ) {
		return;
	}

	if ( ! rom_search_read_manifest() || ! rom_search_with_lock( static fn(): bool => rom_search_index_apply( $queue ) ) ) {
		rom_search_index_reschedule( time() );
	}
}

/**
 * Patch the shards for the queued roms. Runs under the index lock.
 *
 * @param array<int, string[]> $queue id => titles the rom may be indexed under.
 * @return bool False when the index is unreadable and needs a full build.
 */
function rom_search_index_apply( array $queue ): bool {
	$manifest = rom_search_read_manifest();
	if ( ! $manifest ) {
		return false;
	}
	$dir      = rom_search_index_dir();
	$split    = array_fill_keys( array_map( 'strval', $manifest['split'] ), true );
	$keys     = $manifest['keys'];
	$img_base = trailingslashit( wp_get_upload_dir()['baseurl'] );

	$touched = array(); // key => true
	$add     = array(); // key => records to prepend
	$listed  = array(); // id => still searchable after the patch
	foreach ( $queue as $id => $titles ) {
		$post = get_post( $id );
		if ( $post && 'rom' === $post->post_type && 'publish' === $post->post_status ) {
			$record        = rom_search_record( $post, $img_base );
			$post_keys     = rom_search_keys( $post->post_title, $split );
			$listed[ $id ] = (bool) $post_keys;
			foreach ( $post_keys as $key ) {
				$add[ $key ][]   = $record;
				$touched[ $key ] = true;
			}
		} else {
			$listed[ $id ] = false;
		}
		foreach ( $titles as $title ) {
			foreach ( rom_search_keys( $title, $split ) as $key ) {
				$touched[ $key ] = true;
			}
		}
	}

	$was_listed = array(); // id => found in a shard before the patch
	$emptied    = array();
	$changed    = false;
	foreach ( array_keys( $touched ) as $key ) {
		$key  = (string) $key;
		$file = $dir . '/' . $key . '.json';
		$list = array();
		if ( isset( $keys[ $key ] ) ) {
			$list = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;
			if ( ! is_array( $list ) ) {
				return false;
			}
		}

		$kept = array();
		foreach ( $list as $record ) {
			if ( isset( $queue[ $record[0] ] ) ) {
				$was_listed[ $record[0] ] = true;
			} else {
				$kept[] = $record;
			}
		}
		$kept = array_merge( $add[ $key ] ?? array(), $kept );

		if ( ! $kept ) {
			if ( isset( $keys[ $key ] ) ) {
				unset( $keys[ $key ] );
				$emptied[] = $file;
				$changed   = true;
			}
			continue;
		}

		$json = wp_json_encode( $kept, ROM_SEARCH_JSON_FLAGS );
		if ( false === $json ) {
			return false;
		}
		$hash = hash( 'crc32b', $json );
		if ( isset( $keys[ $key ][1] ) && $keys[ $key ][1] === $hash ) {
			continue;
		}
		if ( ! rom_search_write_file( $file, $json ) ) {
			return false;
		}
		$keys[ $key ] = array( count( $kept ), $hash );
		$changed      = true;
	}

	if ( ! $changed ) {
		return true;
	}

	$count = (int) $manifest['n'];
	foreach ( $listed as $id => $now ) {
		$count += (int) $now - (int) isset( $was_listed[ $id ] );
	}
	$version = rom_search_next_version( $manifest );
	if ( ! rom_search_write_manifest( $version, $count, $img_base, $manifest['split'], $keys ) ) {
		return false;
	}
	foreach ( $emptied as $file ) {
		@unlink( $file );
	}
	update_option( ROM_SEARCH_VERSION_OPTION, $version, true );

	return true;
}

/*
 * Full rebuild on wp-cron. The rebuild is the source of truth: it repairs
 * whatever the patch cannot see (direct SQL, meta-only updates, term renames,
 * regenerated thumbnails), so the interval is the worst-case staleness.
 */

add_filter( 'cron_schedules', function ( $schedules ) {
	$schedules[ ROM_SEARCH_SCHEDULE ] = array(
		'interval' => rom_search_cron_interval(),
		'display'  => __( 'Search index rebuild', 'rom' ),
	);

	return $schedules;
} );

add_action( ROM_SEARCH_CRON_HOOK, 'rom_search_index_run' );

/**
 * Full rebuild with room to finish: tens of seconds and ~150 MB on 52k roms.
 */
function rom_search_index_run(): int {
	wp_raise_memory_limit( 'admin' );
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 );
	}

	return rom_search_index_build();
}

/**
 * Restart the recurring rebuild, first run at $timestamp.
 */
function rom_search_index_reschedule( int $timestamp ): void {
	wp_clear_scheduled_hook( ROM_SEARCH_CRON_HOOK );
	wp_schedule_event( $timestamp, ROM_SEARCH_SCHEDULE, ROM_SEARCH_CRON_HOOK );
}

/**
 * Keep the event in step with the configured interval.
 *
 * WordPress stores the interval inside the queued event, so a new value never
 * reaches it on its own. This compares and reschedules only on a mismatch:
 * rescheduling on every request would keep pushing the run back forever.
 * Priority 20 so the ACF fields (registered on init) can be read.
 */
add_action( 'init', function () {
	$interval = rom_search_cron_interval();
	$event    = wp_get_scheduled_event( ROM_SEARCH_CRON_HOOK );
	if ( $event && (int) $event->interval === $interval ) {
		return;
	}

	// Never built: run on the first cron tick. Otherwise one interval out.
	$first = ( ! $event && ! get_option( ROM_SEARCH_VERSION_OPTION ) ) ? time() : time() + $interval;
	rom_search_index_reschedule( $first );
}, 20 );

/*
 * Rebuild now: Configs > Live search. Result goes through a short-lived
 * transient rather than the query string, which can be copied and shared.
 */

add_action( 'admin_post_' . ROM_SEARCH_REBUILD_ACTION, function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'rom' ), 403 );
	}
	check_admin_referer( ROM_SEARCH_REBUILD_ACTION );

	$count = rom_search_index_run();
	// A good build just met the freshness ceiling, so the next one is a full
	// interval away. A failed one keeps its schedule: the retry may fix it.
	if ( $count >= 0 ) {
		rom_search_index_reschedule( time() + rom_search_cron_interval() );
	}
	set_transient( 'rom_search_rebuild_notice_' . get_current_user_id(), $count, MINUTE_IN_SECONDS );

	wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=options' ) );
	exit;
} );

add_action( 'admin_notices', function () {
	$key   = 'rom_search_rebuild_notice_' . get_current_user_id();
	$count = get_transient( $key );
	if ( false === $count ) {
		return;
	}
	delete_transient( $key );

	if ( (int) $count >= 0 ) {
		/* translators: %s: number of games */
		$message = sprintf( __( 'Search index rebuilt: %s games.', 'rom' ), number_format_i18n( (int) $count ) );
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $message ) );
	} else {
		$message = __( 'Search index rebuild failed. Check that wp-content/uploads is writable.', 'rom' );
		printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $message ) );
	}
} );

/**
 * Status of the index and its schedule, rendered at view time so the numbers
 * and the nonce are always fresh.
 */
add_action( 'acf/render_field/key=search_index_status', function () {
	$manifest = rom_search_read_manifest();
	$lines    = array();
	if ( ! $manifest ) {
		$lines[] = __( 'No index yet. It is built on the next cron run, or with Rebuild now.', 'rom' );
	} else {
		/* translators: 1: number of games, 2: number of shards, 3: time since the build */
		$lines[] = sprintf( __( '%1$s games in %2$s shards, built %3$s ago.', 'rom' ),
			number_format_i18n( (int) $manifest['n'] ),
			number_format_i18n( count( $manifest['keys'] ) ),
			human_time_diff( (int) $manifest['v'] )
		);
	}

	$next = wp_next_scheduled( ROM_SEARCH_CRON_HOOK );
	if ( ! $next ) {
		$lines[] = __( 'No rebuild is scheduled.', 'rom' );
	} elseif ( $next < time() - MINUTE_IN_SECONDS ) {
		/* translators: %s: how long the rebuild is overdue */
		$lines[] = sprintf( __( 'Rebuild is %s overdue — wp-cron may not be running.', 'rom' ), human_time_diff( $next ) );
	} else {
		/* translators: %s: time until the next rebuild */
		$lines[] = sprintf( __( 'Next scheduled rebuild in %s.', 'rom' ), human_time_diff( $next ) );
	}

	foreach ( $lines as $line ) {
		echo '<p>' . esc_html( $line ) . '</p>';
	}
	if ( current_user_can( 'manage_options' ) ) {
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=' . ROM_SEARCH_REBUILD_ACTION ), ROM_SEARCH_REBUILD_ACTION );
		echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Rebuild now', 'rom' ) . '</a></p>';
	}
} );

/**
 * Manifest URL with the build version as cache-buster, or '' before the first build.
 */
function rom_search_index_url(): string {
	$version = (int) get_option( ROM_SEARCH_VERSION_OPTION, 0 );
	if ( ! $version || ! is_file( rom_search_index_dir() . '/' . ROM_SEARCH_MANIFEST ) ) {
		return '';
	}

	return wp_get_upload_dir()['baseurl'] . '/' . ROM_SEARCH_DIR . '/' . ROM_SEARCH_MANIFEST . '?v=' . $version;
}

/**
 * Whether a search box gets suggestions: 'header' or 'filter'. Turning one off
 * only drops the dropdown; the box stays a plain GET search form.
 */
function rom_search_location_enabled( string $where ): bool {
	static $enabled = array();
	if ( ! isset( $enabled[ $where ] ) ) {
		$value = function_exists( 'get_field' ) ? get_field( 'search_live_' . $where, 'option' ) : null;
		// Never saved reads as null, and the field defaults to on.
		$enabled[ $where ] = null === $value || (bool) $value;
	}

	return $enabled[ $where ];
}

/**
 * window.romSearch for js/main.js, or [] when there is nothing to attach to:
 * no index yet, or both boxes turned off. URLs are built per request rather
 * than taken from the manifest, which records the host of whoever built it.
 */
function rom_search_js_config(): array {
	$at       = array(
		'header' => rom_search_location_enabled( 'header' ),
		'filter' => rom_search_location_enabled( 'filter' ),
	);
	$manifest = rom_search_index_url();
	if ( ! $manifest || ! array_filter( $at ) ) {
		return array();
	}
	$base = wp_get_upload_dir()['baseurl'];

	// Console name => badge style, the same colours as the rom card. Kept out of
	// the shards so a colour change shows without a rebuild.
	$consoles = array();
	$terms    = get_terms( array( 'taxonomy' => 'console', 'hide_empty' => true ) );
	foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
		$style = rom_console_badge_style( $term );
		if ( $style ) {
			$consoles[ html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) ] = $style;
		}
	}

	return array(
		'manifest' => $manifest,
		'shards'   => $base . '/' . ROM_SEARCH_DIR . '/',
		'img'      => $base . '/',
		'min'      => (int) ROM_SEARCH_QUERY_MIN,
		'max'      => (int) ROM_SEARCH_MAX_RESULTS,
		'debounce' => (int) ROM_SEARCH_DEBOUNCE_MS,
		'stop'     => explode( ',', ROM_SEARCH_STOPWORDS ),
		'consoles' => (object) $consoles,
		// On a console archive the filter box only suggests that console's roms,
		// like its Search button. Matched against the record's console name.
		'scope'    => is_tax( 'console' ) ? html_entity_decode( get_queried_object()->name, ENT_QUOTES, 'UTF-8' ) : '',
		'at'       => $at,
		'i18n'     => array(
			/* translators: %s: the search query */
			'none' => __( 'No games found for "%s"', 'rom' ),
			'more' => __( 'Keep typing to see more games…', 'rom' ),
			/* translators: %s: the search query */
			'all'  => __( 'View all results for "%s"', 'rom' ),
		),
	);
}
