<?php

const AVAILABLE_POST_TYPES = array( 'rom', 'emulator' );
function valid_downloadable_post_type( string $post_type, array $valid_arr = AVAILABLE_POST_TYPES ): bool {
	return in_array( $post_type, AVAILABLE_POST_TYPES, true );
}

function valid_post_type( string $post_type, array $valid_arr = AVAILABLE_POST_TYPES ): bool {
	return in_array( $post_type, AVAILABLE_POST_TYPES, true );
}

function get_custom_console_name( string $post_type ): string {
	return match ( $post_type ) {
		'rom' => 'console',
		'emulator' => 'emulator_console',
		default => '',
	};
}

/**
 * Get taxonomy template-part slug by post type
 *
 * Dùng cho trang taxonomy để quyết định template item tương ứng.
 */
function get_taxonomy_template_slug_by_post_type( string $post_type ): string {
	if (!$post_type) return 'rom-card';

	$map = array(
		'rom'      => 'rom-card',
		'emulator' => 'emu-item',
	);

	// Fallback: rom-card
	return $map[ $post_type ] ?? 'rom-card';
}

/**
 * Get taxonomy title suffix (ROM / Emulator) by post type
 *
 * Dùng cho heading của trang taxonomy.
 */
function get_taxonomy_title_suffix_by_post_type( string $post_type ): string {
	$map = array(
		'rom'      => 'ROM',
		'emulator' => 'Emulator',
	);

	return $map[ $post_type ] ?? '';
}


/**
 * Check if current page should load dynamic nonces
 * 
 * Returns true for:
 * - Single pages of post types: rom, emulator, page
 * - Download page (download.php template)
 *
 * @return bool
 */
function should_load_dynamic_nonce() {
	global $wp_query;
	$post_type = get_post_type();
	$target_post_types = array( 'rom', 'emulator', 'page' );
	$is_download_page = ! empty( $wp_query->query_vars['download'] );
	
	return ( is_singular() && in_array( $post_type, $target_post_types, true ) ) || $is_download_page;
}