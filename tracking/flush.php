<?php
/**
 * Drain the Redis counter buffers into the database.
 *
 * Runs from system cron every 10 minutes:
 *
 *   cd /path/to/theme/tracking && php flush.php
 *
 * One summary line goes to stdout and errors to stderr, so the panel running the
 * task captures both — do not redirect them to a file, or its own log stays
 * empty. Add --verbose for one line per post and per console term.
 *
 * Runs never overlap: two concurrent runs would each read the same buffer and
 * double every count, so a run that finds the lock held exits at once.
 *
 * Ordering matters throughout: the buffer is moved aside with RENAME so counts
 * arriving mid-flush accumulate in a fresh key, and the moved copy is deleted
 * only after the database write succeeds. A run that dies in between leaves the
 * copy behind for the next one to pick up.
 *
 * @package romsvault
 * @since 3.0
 */

if ( 'cli' !== PHP_SAPI ) {
    http_response_code( 403 );
    exit( 1 );
}

// Held until the process exits. Without a temp dir to lock in, run unguarded
// rather than never flush.
$rftrk_lock = fopen( sys_get_temp_dir() . '/rftrk-flush.lock', 'c' );
if ( ! $rftrk_lock ) {
    fwrite( STDERR, "[rf-flush] Could not open the lock file, running without it\n" );
} elseif ( ! flock( $rftrk_lock, LOCK_EX | LOCK_NB ) ) {
    fwrite( STDERR, "[rf-flush] Another flush is still running, skipped\n" );
    exit( 0 );
}

define( 'RFTRK_VERBOSE', in_array( '--verbose', $argv ?? array(), true ) );

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../../../../wp-load.php';

/**
 * Open the Redis connection used by the flush.
 *
 * @return \Redis|null Null when Redis cannot be reached.
 */
function rftrk_flush_redis(): ?Redis {
    try {
        $redis = new Redis();

        $connected = $redis->pconnect(
            RFTRK_REDIS_HOST,
            RFTRK_REDIS_PORT,
            RFTRK_REDIS_TIMEOUT_CLI,
            RFTRK_REDIS_PERSISTENT_ID . '_cli',
            0,
            RFTRK_REDIS_TIMEOUT_CLI
        );

        return $connected ? $redis : null;
    } catch ( \Throwable $e ) {
        fwrite( STDERR, '[rf-flush] Redis connect failed: ' . $e->getMessage() . "\n" );
        return null;
    }
}

/**
 * Keep only the IDs that still resolve to a published post.
 *
 * This is the validation the beacon skips. Doing it here costs one query per
 * chunk instead of one per page view. The title is only fetched with --verbose,
 * for the per-post log line.
 *
 * @param int[] $post_ids IDs to check.
 * @return array<int,array{type:string,title:string}> Map of post ID to type and title.
 */
function rftrk_published_posts( array $post_ids ) {
    global $wpdb;

    $posts   = array();
    $columns = RFTRK_VERBOSE ? 'ID, post_type, post_title' : 'ID, post_type';

    foreach ( array_chunk( $post_ids, RFTRK_SQL_CHUNK ) as $chunk ) {
        $in   = implode( ',', array_map( 'intval', $chunk ) );
        $rows = $wpdb->get_results( "SELECT {$columns} FROM {$wpdb->posts} WHERE ID IN ({$in}) AND post_status = 'publish'" );

        foreach ( $rows as $row ) {
            $posts[ (int) $row->ID ] = array(
                'type'  => $row->post_type,
                'title' => $row->post_title ?? '',
            );
        }
    }

    return $posts;
}

/**
 * Add the pending deltas to a post meta counter.
 *
 * One UPDATE and at most one INSERT per chunk, rather than the read-modify-write
 * pair update_post_meta() performs per post. The rows change behind WordPress's
 * back, so the cached meta is dropped for every post touched.
 *
 * The starting values come with the row check — the same query either way — so
 * --verbose can report every post on its own line.
 *
 * @param array<int,int>                            $deltas   Map of post ID to delta, already validated.
 * @param string                                    $meta_key Counter to add to.
 * @param array<int,array{type:string,title:string}> $posts   Type and title per post, for the log.
 * @return void
 */
function rftrk_add_post_meta( array $deltas, $meta_key, array $posts ) :void {
    global $wpdb;

    foreach ( array_chunk( $deltas, RFTRK_SQL_CHUNK, true ) as $chunk ) {
        $ids = array_map( 'intval', array_keys( $chunk ) );
        $in  = implode( ',', $ids );

        // Ordered by meta_id so a post carrying duplicate rows reports the same
        // value get_post_meta() returns — the lowest one.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, meta_value FROM {$wpdb->postmeta}
                  WHERE meta_key = %s AND post_id IN ({$in})
                  ORDER BY meta_id ASC",
                $meta_key
            )
        );

        $current = array();
        foreach ( $rows as $row ) {
            $post_id = (int) $row->post_id;
            if ( ! isset( $current[ $post_id ] ) ) {
                $current[ $post_id ] = (int) $row->meta_value;
            }
        }
        $existing = array_keys( $current );

        if ( RFTRK_VERBOSE ) {
            foreach ( $ids as $post_id ) {
                printf(
                    "  %s | %d | %d | +%d | %s\n",
                    $meta_key,
                    $post_id,
                    $current[ $post_id ] ?? 0,
                    (int) $chunk[ $post_id ],
                    $posts[ $post_id ]['title'] ?? ''
                );
            }
        }

        if ( $existing ) {
            $cases = '';
            foreach ( $existing as $post_id ) {
                $cases .= sprintf( ' WHEN %d THEN %d', $post_id, (int) $chunk[ $post_id ] );
            }

            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->postmeta}
                        SET meta_value = meta_value + CASE post_id{$cases} ELSE 0 END
                      WHERE meta_key = %s AND post_id IN (" . implode( ',', $existing ) . ')',
                    $meta_key
                )
            );
        }

        $missing = array_diff( $ids, $existing );
        if ( $missing ) {
            $values = array();
            foreach ( $missing as $post_id ) {
                $values[] = $wpdb->prepare( '(%d, %s, %d)', $post_id, $meta_key, (int) $chunk[ $post_id ] );
            }

            $wpdb->query( "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES " . implode( ',', $values ) );
        }

        foreach ( $ids as $post_id ) {
            wp_cache_delete( $post_id, 'post_meta' );
        }
    }
}

/**
 * Add the download deltas to the per-console term counters.
 *
 * The aggregate is accumulated rather than recomputed from the posts that
 * currently exist: it counts downloads that happened, so deleting or
 * unpublishing a post must not make it go backwards.
 *
 * @param array<int,int>                             $deltas Map of post ID to delta.
 * @param array<int,array{type:string,title:string}> $posts  Type and title per post.
 * @return void
 */
function rftrk_add_term_meta( array $deltas, array $posts ) : void {
    global $wpdb;

    // Post type => the taxonomy holding its console, and the term meta counter.
    $taxonomies = array(
        'rom'      => array( 'console', 'console_rom_downloads' ),
        'emulator' => array( 'emulator_console', 'emulator_console_downloads' ),
    );

    $totals = array();

    foreach ( array_chunk( $deltas, RFTRK_SQL_CHUNK, true ) as $chunk ) {
        $in = implode( ',', array_map( 'intval', array_keys( $chunk ) ) );

        // Lowest term id wins when a post sits in several console terms, so the
        // aggregate a post feeds stays stable between runs.
        $rows = $wpdb->get_results(
            "SELECT tr.object_id, tt.term_id, tt.taxonomy
               FROM {$wpdb->term_relationships} tr
               INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
              WHERE tr.object_id IN ({$in})
                AND tt.taxonomy IN ('console', 'emulator_console')
              ORDER BY tt.term_id ASC"
        );

        $chosen = array();
        foreach ( $rows as $row ) {
            $post_id = (int) $row->object_id;

            $post_type = $posts[ $post_id ]['type'] ?? '';

            if ( ! isset( $taxonomies[ $post_type ] ) ) {
                continue;
            }

            list( $taxonomy, $meta_key ) = $taxonomies[ $post_type ];

            if ( $row->taxonomy !== $taxonomy || isset( $chosen[ $post_id ] ) ) {
                continue;
            }

            $chosen[ $post_id ] = true;

            $term_id = (int) $row->term_id;
            $totals[ $meta_key ][ $term_id ] = ( $totals[ $meta_key ][ $term_id ] ?? 0 ) + (int) $chunk[ $post_id ];
        }
    }

    // Console terms are few, so the meta API is cheap enough here and keeps the
    // object cache correct for us.
    foreach ( $totals as $meta_key => $terms ) {
        foreach ( $terms as $term_id => $delta ) {
            $current = (int) get_term_meta( $term_id, $meta_key, true );
            update_term_meta( $term_id, $meta_key, $current + $delta );

            if ( RFTRK_VERBOSE ) {
                $term = get_term( $term_id );

                printf(
                    "  %s | %d | %d | +%d | %s\n",
                    $meta_key,
                    $term_id,
                    $current,
                    $delta,
                    ( $term && ! is_wp_error( $term ) ) ? $term->name : ''
                );
            }
        }
    }
}

/**
 * Flush one counter from Redis to the database.
 *
 * Two passes on purpose. A previous run may have died after moving the buffer
 * aside but before finishing its writes, and the RENAME below would overwrite
 * that copy, so it is drained first.
 *
 * @param \Redis $redis    Connection.
 * @param string $live_key Buffer key.
 * @param string $meta_key Post meta counter the buffer feeds.
 * @return array{posts:int,counts:int,skipped:int}
 */
function rftrk_flush_counter( $redis, $live_key, $meta_key ): array {
    $stash  = $live_key . RFTRK_STASH_SUFFIX;
    $totals = array(
        'posts'   => 0,
        'counts'  => 0,
        'skipped' => 0,
    );

    if ( $redis->exists( $stash ) ) {
        rftrk_write_stash( $redis, $stash, $meta_key, $totals );
    }

    // RENAME is atomic, so counts arriving from here on land in a fresh key and
    // none can go missing.
    if ( $redis->exists( $live_key ) ) {
        $redis->rename( $live_key, $stash );
        rftrk_write_stash( $redis, $stash, $meta_key, $totals );
    }

    return $totals;
}

/**
 * Write one stashed buffer to the database, then drop it.
 *
 * @param \Redis $redis    Connection.
 * @param string $stash    Key holding the buffer being written.
 * @param string $meta_key Post meta counter the buffer feeds.
 * @param array  $totals   Running report, updated in place.
 * @return void
 */
function rftrk_write_stash( $redis, $stash, $meta_key, array &$totals ): void {
    $buffer = $redis->hGetAll( $stash );

    if ( $buffer ) {
        $posts  = rftrk_published_posts( array_map( 'intval', array_keys( $buffer ) ) );
        $deltas = array();

        foreach ( $buffer as $post_id => $delta ) {
            $post_id = (int) $post_id;
            $delta   = (int) $delta;

            if ( $delta > 0 && isset( $posts[ $post_id ] ) ) {
                $deltas[ $post_id ] = $delta;
            }
        }

        if ( $deltas ) {
            rftrk_add_post_meta( $deltas, $meta_key, $posts );

            if ( 'downloads' === $meta_key ) {
                rftrk_add_term_meta( $deltas, $posts );
            }
        }

        $totals['posts']   += count( $deltas );
        $totals['counts']  += array_sum( $deltas );
        $totals['skipped'] += count( $buffer ) - count( $deltas );
    }

    // Safe to drop the copy only now that the writes have gone through.
    $redis->del( $stash );
}

$redis = rftrk_flush_redis();
if ( ! $redis ) {
    fwrite( STDERR, "[rf-flush] Redis unavailable, nothing flushed\n" );
    exit( 1 );
}

$counters = array(
    'views'     => RFTRK_KEY_VIEWS,
    'downloads' => RFTRK_KEY_DOWNLOADS,
);

$started = microtime( true );
$report  = array();

foreach ( $counters as $meta_key => $live_key ) {
    $result = rftrk_flush_counter( $redis, $live_key, $meta_key );

    $report[] = sprintf(
        '%s: %d posts, +%d (%d skipped)',
        $meta_key,
        $result['posts'],
        $result['counts'],
        $result['skipped']
    );
}

// Heartbeat: alert when this stops moving, instead of discovering a dead cron
// from a flat chart days later.
$redis->set( RFTRK_KEY_HEARTBEAT, time() );

printf(
    "[rf-flush] %s | %s | %.2fs\n",
    gmdate( 'Y-m-d H:i:s' ),
    implode( ' | ', $report ),
    microtime( true ) - $started
);
