<?php
/**
 * Member action — wishlist. Members favourite downloadable posts (capped per
 * member). Per-user + dynamic, so it lives on rf/v1 (cache-safe) with a
 * per-member transient cache; the /wishlist page renders it client-side.
 * Depends on member-core.php (origin guard, current member, action throttle).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', function () {
	register_rest_route( RF_REST_NS, '/wishlist', array(
		array(
			'methods'             => 'GET',
			'callback'            => 'rf_rest_wishlist_list',
			'permission_callback' => '__return_true',
		),
		array(
			'methods'             => 'POST', // idempotent set: { post, on:0|1 }
			'callback'            => 'rf_rest_wishlist_set',
			'permission_callback' => '__return_true',
		),
	) );
} );

if ( ! defined( 'RF_WISHLIST_MAX' ) ) {
	define( 'RF_WISHLIST_MAX', 30 ); // max wishlist items per member
}

function rf_wishlist_count( $member_id ): int {
	global $wpdb;
	// JOIN publish: the cap counts only published items, matching the displayed
	// count. Trashed rows are already deleted (hook); draft rows are kept but do
	// not count toward the 30 cap.
	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*)
		 FROM `" . rf_member_wishlist_table() . "` w
		 JOIN `{$wpdb->posts}` p ON p.ID = w.post_id AND p.post_status = 'publish'
		 WHERE w.member_id = %d",
		(int) $member_id
	) );
}

function rf_wishlist_has( $member_id, $post_id ) {
	global $wpdb;
	return (bool) $wpdb->get_var( $wpdb->prepare(
		"SELECT 1 FROM `" . rf_member_wishlist_table() . "` WHERE member_id = %d AND post_id = %d",
		(int) $member_id, (int) $post_id
	) );
}

/**
 * POST /wp-json/rf/v1/wishlist  { post, on:0|1 }
 * Idempotent set — on=1 ensures the post is in the wishlist, on=0 ensures it is
 * not. Idempotent so the client can safely debounce/coalesce rapid clicks and
 * only send the final desired state (repeated same-state calls are no-ops).
 * Adding enforces the RF_WISHLIST_MAX cap (409 when full). Counts toward the
 * shared per-member action throttle (§16). Returns { active, count }.
 */
function rf_rest_wishlist_set( WP_REST_Request $request ) {
	if ( ! rf_verify_request_origin( $request ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'bad_origin' ), 403 );
	}
	$member = rf_current_member();
	if ( ! $member ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'auth_required' ), 401 );
	}
	if ( (int) $member['blocked'] === 1 ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'blocked', 'message' => __( 'Your account has been blocked.', 'romsfun' ) ), 403 );
	}

	$member_id = (int) $member['id'];

	// Bulk remove — { remove: [id, ...] }. The wishlist page coalesces several
	// deletes into ONE request so rapid removals don't hit the action throttle.
	$remove = $request->get_param( 'remove' );
	if ( is_array( $remove ) && $remove ) {
		if ( ! rf_check_action_rate( $member_id ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'rate_limited' ), 429 );
		}
		global $wpdb;
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $remove ) ) ) );
		if ( $ids ) {
			$in = implode( ',', $ids ); // all absint -> safe to inline
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM `" . rf_member_wishlist_table() . "` WHERE member_id = %d AND post_id IN ($in)",
				$member_id
			) );
			rf_wishlist_flush_member( $member_id );
		}
		return new WP_REST_Response( array( 'ok' => true, 'count' => rf_wishlist_count( $member_id ) ), 200 );
	}

	$on = $request->get_param( 'on' );
	if ( $on === null ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'bad_request' ), 400 );
	}
	$on = (int) $on === 1;

	$post_id = absint( $request->get_param( 'post' ) );
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post || $post->post_status !== 'publish' || ! valid_downloadable_post_type( $post->post_type ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'not_found' ), 404 );
	}

	if ( ! rf_check_action_rate( (int) $member['id'] ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'rate_limited' ), 429 );
	}

	global $wpdb;
	$table     = rf_member_wishlist_table();
	$member_id = (int) $member['id'];
	$has       = rf_wishlist_has( $member_id, $post_id );

	if ( $on ) {
		// Ensure present. No-op if already there; enforce the cap only when adding.
		if ( ! $has ) {
			if ( rf_wishlist_count( $member_id ) >= RF_WISHLIST_MAX ) {
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'limit_reached', 'max' => RF_WISHLIST_MAX ), 409 );
			}
			$wpdb->query( $wpdb->prepare(
				"INSERT IGNORE INTO `{$table}` (member_id, post_id, created_at) VALUES (%d, %d, %s)",
				$member_id, $post_id, current_time( 'mysql' )
			) );
			rf_wishlist_flush_member( $member_id );
		}
		return new WP_REST_Response( array( 'ok' => true, 'active' => true, 'count' => rf_wishlist_count( $member_id ) ), 200 );
	}

	// Ensure absent. No-op if already gone.
	if ( $has ) {
		$wpdb->delete( $table, array( 'member_id' => $member_id, 'post_id' => $post_id ), array( '%d', '%d' ) );
		rf_wishlist_flush_member( $member_id );
	}
	return new WP_REST_Response( array( 'ok' => true, 'active' => false, 'count' => rf_wishlist_count( $member_id ) ), 200 );
}

/**
 * Transient cache key for a member's rendered wishlist payload.
 */
function rf_wishlist_cache_key( $member_id ): string {
	return rf_member_cache_key( 'wishlist', $member_id );
}

/**
 * Build the cacheable wishlist payload from the DB (source of truth): published
 * only, newest first, capped at RF_WISHLIST_MAX. Stores type_key (not the display
 * label) so the label/cap constants can change without a cache rebuild; url is
 * absolute (backward-compatible with the pre-cache response). Empty member →
 * { ids:[], items:[] } sentinel (still cached → next read is 0 DB).
 */
function rf_wishlist_build_payload( $member_id ): array {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT w.post_id, p.post_title, p.post_type
		 FROM `" . rf_member_wishlist_table() . "` w
		 JOIN `{$wpdb->posts}` p ON p.ID = w.post_id AND p.post_status = 'publish'
		 WHERE w.member_id = %d
		 ORDER BY w.created_at DESC
		 LIMIT %d",
		(int) $member_id, RF_WISHLIST_MAX
	), ARRAY_A );

	$ids   = array();
	$items = array();
	foreach ( (array) $rows as $r ) {
		$pid     = (int) $r['post_id'];
		$meta    = rf_post_type_meta( $r['post_type'] );
		$console = '';
		if ( $meta['tax'] ) {
			$terms = get_the_terms( $pid, $meta['tax'] );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$console = $terms[0]->name;
			}
		}
		$ids[]   = $pid;
		$items[] = array(
			'id'       => $pid,
			'title'    => $r['post_title'],
			'url'      => get_permalink( $pid ),
			'thumb'    => get_the_post_thumbnail_url( $pid, 'medium' ) ?: '',
			'type_key' => $r['post_type'],
			'console'  => $console,
		);
	}
	return array( 'ids' => $ids, 'items' => $items );
}

/**
 * Wrap a cached payload into the full API response: inject the display label
 * (type) from type_key, plus count/max. Runs on every read (hit or miss) — the
 * cache stores only the parts that can't be derived cheaply.
 */
function rf_wishlist_serve( $payload ): array {
	$ids   = array_map( 'intval', (array) ( $payload['ids'] ?? array() ) );
	$items = array();
	foreach ( (array) ( $payload['items'] ?? array() ) as $it ) {
		$meta       = rf_post_type_meta( isset( $it['type_key'] ) ? $it['type_key'] : '' );
		$it['type'] = $meta['label'];
		$items[]    = $it;
	}
	return array(
		'ok'    => true,
		'ids'   => $ids,
		'count' => count( $ids ),
		'max'   => RF_WISHLIST_MAX,
		'items' => $items,
	);
}

/**
 * Invalidate a member's cached wishlist payload. Called after every write that
 * changes their list. DEL only — never mutate/SET from the write path, so
 * concurrent toggles can't lose an item; the next read rebuilds from the DB.
 */
function rf_wishlist_flush_member( $member_id ): void {
	delete_transient( rf_wishlist_cache_key( (int) $member_id ) );
}

/**
 * GET /wp-json/rf/v1/wishlist — the current member's wishlist, newest first.
 * Cache-aside: served from a per-member transient (TTL 4h ± jitter), rebuilt from
 * the DB on miss. Response { ok, ids, items:[{ id, title, url, thumb, type,
 * type_key, console }], count, max }. Per-member → private, no-store.
 */
function rf_rest_wishlist_list( WP_REST_Request $request ): WP_REST_Response {
	if ( ! rf_verify_request_origin( $request ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'bad_origin' ), 403 );
	}
	$member = rf_current_member();
	if ( ! $member ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'auth_required' ), 401 );
	}
	// Blocked members may still VIEW their wishlist (read-only). Only writes
	// (rf_rest_wishlist_set) are refused, so no blocked check here.

	$member_id = (int) $member['id'];
	$key       = rf_wishlist_cache_key( $member_id );

	$payload = get_transient( $key );
	if ( false === $payload ) {
		$payload = rf_wishlist_build_payload( $member_id );
		// Flat 4h TTL, no jitter: per-member reads are sparse and uncorrelated, so
		// entries are created (and expire) at different times — there is no
		// synchronized mass-expiry to smooth out.
		set_transient( $key, $payload, 4 * HOUR_IN_SECONDS );
	}

	$resp = new WP_REST_Response( rf_wishlist_serve( $payload ), 200 );
	$resp->header( 'Cache-Control', 'private, no-store' );
	return $resp;
}

/**
 * Post lifecycle → invalidate every member whose wishlist holds this post, and
 * delete the rows when the post is gone (trash/hard-delete) so the cap can't
 * overflow via unpublish→add→restore. Members are read BEFORE deleting rows.
 */
function rf_wishlist_invalidate_post( $post_id, $delete_rows ): void {
	global $wpdb;
	$table   = rf_member_wishlist_table();
	$members = $wpdb->get_col( $wpdb->prepare(
		"SELECT member_id FROM `{$table}` WHERE post_id = %d", (int) $post_id
	) );
	if ( ! $members ) {
		return; // not wishlisted by anyone → nothing to do
	}
	if ( $delete_rows ) {
		$wpdb->delete( $table, array( 'post_id' => (int) $post_id ), array( '%d' ) );
	}
	// Batch the fan-out: one object-cache delete_multiple (a single Redis pipeline
	// when the dropin supports it) instead of N sequential delete_transient
	// round-trips — matters when a hot post is wishlisted by many members. Falls
	// back to per-key when no persistent object cache (transients live in options).
	$keys = array();
	foreach ( $members as $m ) {
		$keys[] = rf_wishlist_cache_key( (int) $m );
	}
	if ( wp_using_ext_object_cache() && function_exists( 'wp_cache_delete_multiple' ) ) {
		wp_cache_delete_multiple( $keys, 'transient' );
	} else {
		foreach ( $members as $m ) {
			rf_wishlist_flush_member( (int) $m );
		}
	}
}

add_action( 'transition_post_status', 'rf_wishlist_on_transition', 10, 3 );
function rf_wishlist_on_transition( $new_status, $old_status, $post ) {
	if ( ! $post || $new_status === $old_status || ! valid_downloadable_post_type( $post->post_type ) ) {
		return;
	}
	// Row-delete only when leaving to trash; draft/pending keep rows (edit-in-progress).
	rf_wishlist_invalidate_post( (int) $post->ID, $new_status === 'trash' );
}

add_action( 'deleted_post', 'rf_wishlist_on_deleted', 10, 1 );
function rf_wishlist_on_deleted( $post_id ) {
	rf_wishlist_invalidate_post( (int) $post_id, true );
}

/**
 * Display label + console taxonomy for a downloadable post type (wishlist badges).
 */
function rf_post_type_meta( $post_type ) {
	$map = array(
		'rom'      => array( 'label' => 'ROM',  'tax' => 'console' ),
		'pack'     => array( 'label' => 'Hack', 'tax' => 'pack_console' ),
		'emulator' => array( 'label' => 'Emu',  'tax' => 'emulator_console' ),
		'library'  => array( 'label' => 'Lib',  'tax' => 'lib_console' ),
	);
	return isset( $map[ $post_type ] ) ? $map[ $post_type ] : array( 'label' => ucfirst( (string) $post_type ), 'tax' => '' );
}

/**
 * Wishlist toggle button (heart) for a downloadable post. Cache-safe: renders
 * the same for everyone; auth.js marks it active from the member's wishlist and
 * toggles via POST /rf/v1/wishlist (guests get the login modal).
 */
function rf_render_wishlist_button( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! $post_id ) {
		return;
	}
	echo '<button type="button" class="rf-wish-btn" data-rf-wish data-post="' . esc_attr( $post_id ) . '"'
		. ' aria-pressed="false" aria-label="' . esc_attr__( 'Add to wishlist', 'romsfun' ) . '"'
		. ' title="' . esc_attr__( 'Add to wishlist', 'romsfun' ) . '">'
		. '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 5.6a5.4 5.4 0 0 0-7.7 0L12 6.7l-1.1-1.1a5.4 5.4 0 1 0-7.7 7.7L12 22l8.8-8.7a5.4 5.4 0 0 0 0-7.7z"/></svg>'
		. '</button>';
}
