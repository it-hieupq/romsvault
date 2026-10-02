<?php

/**
 * Update post views
 */
function k_update_post_views() {
    AppSecurity::set_security_headers();
	$_POST   = AppSecurity::validate_input( $_POST );
	$post_id = AppSecurity::validate_number( $_POST['post_id'] ?? 0 );

	$count = (int) get_field('views', $post_id);
    update_field('views', ++$count, $post_id);
    die();
}
add_action( 'wp_ajax_nopriv_k_update_post_views', 'k_update_post_views' );
add_action( 'wp_ajax_k_update_post_views', 'k_update_post_views' );

/**
 * Get download - Improved version with validation and JSON response
 */
function k_get_download() {
	AppSecurity::set_security_headers();
	// Parse URL from HTTP_REFERER or REQUEST_URI
	$url_to_parse = '';
	if ( isset( $_SERVER['HTTP_REFERER'] ) && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
		$url_to_parse = $_SERVER['HTTP_REFERER'];
	} elseif ( isset( $_SERVER['REQUEST_URI'] ) && ! empty( $_SERVER['REQUEST_URI'] ) ) {
		$url_to_parse = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	}
	
	// Validate URL exists
	if ( empty( $url_to_parse ) ) {
		wp_send_json_error( array( 'message' => __( 'Unable to parse download URL.', 'romsfun' ) ) );
	}
	
	// Extract download slug from URL
	$prefix = '/download/';
	$prefix_pos = strpos( $url_to_parse, $prefix );
	
	if ( $prefix_pos === false ) {
		wp_send_json_error( array( 'message' => __( 'Invalid download URL format.', 'romsfun' ) ) );
	}
	
	$download_slug = substr( $url_to_parse, $prefix_pos + strlen( $prefix ) );
	$download_slugs = explode( '/', $download_slug );
	
	// Validate and extract post ID from slug
	if ( empty( $download_slugs[0] ) || ! preg_match( '/-([0-9]+)$/', $download_slugs[0], $matches ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid post ID in URL.', 'romsfun' ) ) );
	}
	
	$post_id = absint( $matches[1] );
	
	// Validate post_id is positive
	if ( $post_id <= 0 ) {
		wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'romsfun' ) ) );
	}
	
	// Validate post exists and is published
	$post = get_post( $post_id );
	if ( ! $post || $post->post_status !== 'publish' ) {
		wp_send_json_error( array( 'message' => __( 'Post not found or not published.', 'romsfun' ) ) );
	}
	
	// Validate post type
	if ( ! valid_downloadable_post_type( $post->post_type ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid post type for download.', 'romsfun' ) ) );
	}

	// Members-only posts (approach C) are NEVER served via this ungated admin-ajax
	// path. The URL must come from /wp-json/rf/v1/download-url after the member
	// session is verified. Refuse here so a guest can't bypass the gate.
	if ( rf_is_restricted( $post_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Please sign in to download.', 'romsfun' ), 'code' => 'members_only' ) );
	}
	
	// Get download lists
	$normal_downloads = get_field( 'download_list', $post_id );
	$aff_downloads    = get_field( 'download_list_aff', $post_id );
	
	// Merge and validate downloads array
	$downloads = array();
	if ( is_array( $normal_downloads ) && is_array( $aff_downloads ) ) {
		$downloads = array_merge( $normal_downloads, $aff_downloads );
	} elseif ( is_array( $normal_downloads ) ) {
		$downloads = $normal_downloads;
	} elseif ( is_array( $aff_downloads ) ) {
		$downloads = $aff_downloads;
	}
	
	// Validate downloads array is not empty
	if ( empty( $downloads ) ) {
		wp_send_json_error( array( 'message' => __( 'No download links available.', 'romsfun' ) ) );
	}
	
	// Extract file index from URL
	if ( empty( $download_slugs[1] ) ) {
		wp_send_json_error( array( 'message' => __( 'File index not specified in URL.', 'romsfun' ) ) );
	}
	
	$file_index = absint( $download_slugs[1] ) - 1;
	
	// Validate file index is in range
	if ( ! isset( $downloads[ $file_index ] ) || empty( $downloads[ $file_index ]['url'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid file index.', 'romsfun' ) ) );
	}
	
	$link = $downloads[ $file_index ];
	
	// Validate link data
	if ( empty( $link['name'] ) || empty( $link['url'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid download link data.', 'romsfun' ) ) );
	}
	
	// Apply affiliate codes if applicable
	$affs = get_field( 'affs', 'option' );
	if ( is_array( $affs ) && ! empty( $affs ) ) {
		$link_url = esc_url_raw( $link['url'] );
		foreach ( $affs as $aff ) {
			if ( ! empty( $aff['aff_host'] ) && ! empty( $aff['aff_code'] ) ) {
				if ( strpos( $link_url, $aff['aff_host'] ) !== false ) {
					$separator = parse_url( $link_url, PHP_URL_QUERY ) ? '&' : '?';
					$link_url  .= $separator . sanitize_text_field( $aff['aff_code'] );
					break; // Only apply first matching affiliate
				}
			}
		}
		$link['url'] = $link_url;
	}
	
	// Generate download URL with token
	$download_url = add_query_arg( 'token', urlencode( get_download_token() ), $link['url'] );
	$file_name    = esc_html( $link['name'] );
	$file_type    = isset( $link['file_type'] ) ? esc_html( $link['file_type'] ) : '';
	$file_size    = isset( $link['file_size'] ) ? esc_html( $link['file_size'] ) : '';
	
	// Build HTML for download button (matching download.php structure)
	ob_start();
	?>
	<div id="download-button">
		<a href="<?php echo esc_url( $download_url ); ?>"
		   id="download-link"
		   class="inline-flex items-center justify-center px-6 py-4 bg-romfun-pink text-white rounded-full hover:bg-romfun-pink-dark transition font-semibold shadow-lg hover:shadow-xl transform hover:scale-105"
		   rel="nofollow">
			<?php echo get_svg_icon( 'save' ) . ' ' . __( 'Download Now', 'romsfun' ) . ' ' . ' (' . $file_size . ')'; ?>
		</a>
		<p class="text-sm text-gray-500 mt-4">
			<?php esc_html_e( 'If the download doesn\'t start automatically, click the button above', 'romsfun' ); ?>
		</p>
	</div>
	<?php
	$html = ob_get_clean();
	
	// Return JSON response
	wp_send_json_success( array(
		'html'         => $html,
		'download_url' => $download_url,
		'file_name'    => $file_name,
		'file_size'    => $file_size,
	) );
}
add_action( 'wp_ajax_nopriv_k_get_download', 'k_get_download' );
add_action( 'wp_ajax_k_get_download', 'k_get_download' );

function handle_get_download_token() {
    // Return the token as JSON
    wp_send_json_success(array('token' => get_download_token()));
}
add_action('wp_ajax_get_download_token', 'handle_get_download_token');
add_action('wp_ajax_nopriv_get_download_token', 'handle_get_download_token');