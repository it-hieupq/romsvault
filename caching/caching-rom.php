<?php
/**
 * ROM Caching Functions
 * 
 * This file contains all caching functions and cache invalidation hooks
 * for the ROM post type to optimize performance and reduce database queries.
 * 
 * @package romsvault
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param $limit
 * @return mixed
 */
function get_top_type_terms_home($limit = 5) {
    return get_terms([
        'taxonomy'   => 'type',
        'hide_empty' => true,
        'orderby'    => 'count',
        'order'      => 'DESC',
        'number'     => $limit,
    ]);
}


/**
 * Get term counts với filter conditions
 *
 * @param string $target_taxonomy Taxonomy need to count ('type', 'genre', etc.)
 * @param array $filters Array filters selected: ['collection' => [1,2], 'genre' => [5]]
 * @return array Array of objects: [{term_id, name, count}, ...]
 */
function get_filtered_counts(string $target_taxonomy, $filters = array() ): array {

    // check  is home or is page template home
    if (is_home() || is_front_page() || is_page_template('index.php') || empty($filters)) {
        return get_top_type_terms_home();
    }

	// Build tax_query from ALL selected filters. NOTE: the target taxonomy is
	// intentionally INCLUDED (not excluded) — 'type' is multi-valued per rom, so
	// constraining by the selected type(s) still returns posts that also carry other
	// types, letting those co-occurring terms surface (with counts) as suggestions.
	$tax_query = array( 'relation' => 'AND' );
	$has_tax_filter = false;

	if (!empty($filters['consoles'])) {
		$tax_query[] = array(
			'taxonomy' => 'console',
			'field'    => 'term_id',
			'terms'    => array_map( 'absint', $filters['consoles'] ),
		);
		$has_tax_filter = true;
	}

	if (!empty($filters['genres'])) {
		$tax_query[] = array(
			'taxonomy' => 'genre',
			'field'    => 'term_id',
			'terms'    => array_map( 'absint', $filters['genres'] ),
		);
		$has_tax_filter = true;
	}

	if (!empty($filters['collections'])) {
		$tax_query[] = array(
			'taxonomy' => 'collection',
			'field'    => 'term_id',
			'terms'    => array_map( 'absint', $filters['collections'] ),
		);
		$has_tax_filter = true;
	}

	if (!empty($filters['types'])) {
		$tax_query[] = array(
			'taxonomy' => 'type',
			'field'    => 'term_id',
			'terms'    => array_map( 'absint', $filters['types'] ),
		);
		$has_tax_filter = true;
	}

	// Build WP_Query args
	$args = array(
		'post_type'      => 'rom',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'cache_results'  => false,
		'post_status'    => 'publish',
	);

	if ( $has_tax_filter ) {
		$args['tax_query'] = $tax_query;
	}

	if ( ! empty( $filters['search'] ) ) {
		$args['s'] = sanitize_text_field( $filters['search'] );
	}

	$query = new WP_Query( $args );
	$post_ids = $query->posts;

	if ( empty( $post_ids ) ) {
		return array();
	}

	// Count target-taxonomy terms across the filtered posts with ONE GROUP BY,
	// instead of hydrating N×M WP_Term objects via wp_get_object_terms() and looping
	// in PHP. MySQL does the counting on indexed columns and returns only the top rows.
	// $post_ids come from fields=ids (pure ints), so they are inlined safely — no
	// per-id placeholder explosion; only $target_taxonomy is bound via prepare().
	global $wpdb;
	$id_list = implode( ',', array_map( 'absint', $post_ids ) );

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT t.term_id, t.name, t.slug, COUNT(*) AS count
		 FROM {$wpdb->term_relationships} tr
		 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
		 INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
		 WHERE tt.taxonomy = %s
		   AND tr.object_id IN ( $id_list )
		 GROUP BY t.term_id
		 ORDER BY count DESC
		 LIMIT 6",
		$target_taxonomy
	) );

	if ( empty( $rows ) ) {
		return array();
	}

	// Match the previous return shape (int term_id/count; DB returns them as strings).
	foreach ( $rows as $row ) {
		$row->term_id = (int) $row->term_id;
		$row->count   = (int) $row->count;
	}

	return $rows;
}


function get_filter_base_url() : string {
	if (is_tax('console') || is_tax('type')) {
		$browse_page_url = get_term_link(get_queried_object());
	} else {
		$browse_page_url = get_brows_page_url();
	}
	return $browse_page_url;
}

function get_brows_page_url() {
	$cache_key = 'browse_page_url';
	$cache_group = 'config';
	$cached = wp_cache_get($cache_key, $cache_group);
	if ($cached !== false) {
		return $cached;
	}

	$browse_page_args = array(
		'post_type' => 'page',
		'posts_per_page' => 1,
		'no_found_rows' => true,
		'meta_key' => '_wp_page_template',
		'meta_value' => 'browse-roms.php'
	);

	$browse_query = new WP_Query($browse_page_args);
	$browse_page_url = '';
	if ($browse_query->have_posts()) {
		$browse_page_url = get_permalink($browse_query->posts[0]->ID);
		wp_reset_postdata();
		wp_cache_set($cache_key, $browse_page_url, $cache_group, WEEK_IN_SECONDS);
	}
	return $browse_page_url;
}

/**
 * Get cached terms that belong to the posts of a given term.
 *
 * INVARIANT: the cache key is ($taxonomy + $term_id) ONLY — $post_ids is NOT part
 * of it. Safe because callers pass $post_ids = every published rom of $term_id (see
 * rom-filter.php `post_ids_of_{term_id}`), so $term_id fully determines $post_ids and
 * $term_id is globally unique in WP. Do NOT call with a $post_ids that is a subset of
 * / not derived from $term_id — you would get another caller's cached result.
 *
 * @param string $taxonomy Taxonomy name
 * @param array  $post_ids Posts of $term_id (must be the FULL set for that term)
 * @param int    $term_id  Term ID — the sole cache-key discriminator besides taxonomy
 * @return array Array of terms in format id=>name
 */
function get_cached_terms(string $taxonomy, array $post_ids, int $term_id, $force_load = false): array
{
	$cache_key = $taxonomy . '_terms_of_' . $term_id;
	
	$terms = get_transient($cache_key);
	
	if ($terms === false || $force_load) {
		$terms = get_terms(array(
			'taxonomy' => $taxonomy,
			'object_ids' => $post_ids,
			'fields' => 'id=>name',
			'hide_empty' => true
		));
		
		// Handle WP_Error or empty result
		if (is_wp_error($terms) || empty($terms)) {
			$terms = array();
		}
		
		set_transient($cache_key, $terms, DAY_IN_SECONDS);
	}
	
	return $terms;
}

/**
 * Get cached all terms of a taxonomy
 * 
 * Cache all terms of a taxonomy using transient
 *
 * @param string $taxonomy Taxonomy name
 * @return array Array of terms in format id=>name
 */
function get_cached_all_terms($taxonomy, $force_load = false): array
{
	$cache_key = $taxonomy . '_terms';
	
	$terms = get_transient($cache_key);
	
	if ($terms === false || $force_load) {
		$terms = get_terms(array(
			'taxonomy' => $taxonomy,
			'fields' => 'id=>name',
			'hide_empty' => true
		));
		
		if (is_wp_error($terms) || empty($terms)) {
			$terms = array();
		}
		
		set_transient($cache_key, $terms, DAY_IN_SECONDS);
	}

	return $terms;
}

/**
 * Get cached terms by IDs with transient caching
 * 
 * Cache terms by their IDs using transient for optimized performance.
 * This function sanitizes and normalizes term IDs to create consistent cache keys.
 *
 * @param string $taxonomy Taxonomy name
 * @param array $term_ids Array of term IDs
 * @return array|WP_Error Array of term objects or WP_Error on failure
 */
function get_cached_terms_by_ids(string $taxonomy, array $term_ids, $force_load = false ): WP_Error|array
{
	if (empty( $taxonomy ) || empty( $term_ids )) {
		return array();
	}
	
	$term_ids = array_map( 'intval', $term_ids );
	$term_ids = array_filter( $term_ids ); 
	sort( $term_ids ); // Sort for consistent cache key
	
	if ( empty( $term_ids ) ) {
		return array();
	}
	
	// Create cache key based on taxonomy and term IDs
	$cache_key = 'terms_' . $taxonomy . '_' . md5( implode( ',', $term_ids ) );
	
	$terms = get_transient( $cache_key );
	
	if ( $terms === false || $force_load ) {
		$terms = get_terms( array(
			'taxonomy'   => $taxonomy,
			'include'    => $term_ids,
			'hide_empty' => false,
		) );
		
		// Handle WP_Error
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
		
		set_transient( $cache_key, $terms, DAY_IN_SECONDS );
	}
	
	return $terms;
}