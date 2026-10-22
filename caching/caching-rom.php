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
	// No roms, no terms. get_terms() reads an empty object_ids as "no filter"
	// and would hand back the whole taxonomy, cached for a day.
	if (!$post_ids) {
		return array();
	}

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
 * Only the term IDs are cached. The objects are resolved through get_term(),
 * which reads WP's own term cache and is invalidated on every term change, so a
 * term deleted after the transient was written drops out here instead of reaching
 * the caller as a stale object whose get_term_link() answers WP_Error.
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
	
	// Create cache key based on taxonomy and term IDs. The v2 segment retires the
	// transients that still hold whole term objects under the old key.
	$cache_key = 'terms_v2_' . $taxonomy . '_' . md5( implode( ',', $term_ids ) );
	
	$found_ids = get_transient( $cache_key );
	
	if ( $found_ids === false || $force_load ) {
		$found_ids = get_terms( array(
			'taxonomy'   => $taxonomy,
			'include'    => $term_ids,
			'hide_empty' => false,
			'fields'     => 'ids',
		) );
		
		// Handle WP_Error
		if ( is_wp_error( $found_ids ) ) {
			return $found_ids;
		}
		
		set_transient( $cache_key, $found_ids, DAY_IN_SECONDS );
	}
	
	$found_ids = array_map( 'intval', (array) $found_ids );
	if ( empty( $found_ids ) ) {
		return array();
	}
	
	// One query for whatever the object cache is missing, instead of one per term.
	// Term meta is primed with it because the callers read cat_image off these terms.
	_prime_term_caches( $found_ids );
	
	$terms = array();
	foreach ( $found_ids as $found_id ) {
		$term = get_term( $found_id, $taxonomy );
		if ( $term instanceof WP_Term ) {
			$terms[] = $term;
		}
	}
	
	return $terms;
}