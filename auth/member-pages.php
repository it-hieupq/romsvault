<?php
/**
 * Member front-end pages: /wishlist and /profile (plan P7).
 *
 * These are cache-safe shells — the HTML is identical for everyone and the
 * per-user content is fetched client-side from rf/v1 (auth.js). Routing mirrors
 * the /download endpoint: a rewrite rule sets a query var, and template_redirect
 * loads the matching template from auth/templates/.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Allowed member page slugs (template file lives in auth/templates/<slug>.php). */
function rf_member_pages() {
	return array( 'wishlist', 'profile', 'ticket' );
}

add_action( 'init', function () {
	add_rewrite_rule( '^wishlist/?$', 'index.php?rf_page=wishlist', 'top' );
	add_rewrite_rule( '^profile/?$', 'index.php?rf_page=profile', 'top' );
	add_rewrite_rule( '^request/?$', 'index.php?rf_page=ticket', 'top' ); // public slug /request; internal page key stays 'ticket'
} );

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'rf_page';
	return $vars;
} );

// Flush rewrite rules once after these routes are added (guarded by an option).
add_action( 'admin_init', function () {
	if ( get_option( 'rf_member_pages_rw' ) !== '3' ) {
		flush_rewrite_rules();
		update_option( 'rf_member_pages_rw', '3', false );
	}
} );

add_action( 'template_redirect', function () {
	$page = get_query_var( 'rf_page' );
	if ( ! $page || ! in_array( $page, rf_member_pages(), true ) ) {
		return;
	}
	$file = get_template_directory() . '/auth/templates/' . $page . '.php';
	if ( ! file_exists( $file ) ) {
		return;
	}
	global $wp_query;
	$wp_query->is_404 = false;
	status_header( 200 );
	add_filter( 'wp_robots', 'wp_robots_no_robots' ); // per-user pages -> never index
	require $file;
	exit;
} );
