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
	$_POST = AppSecurity::validate_input( $_POST );

	// The download page posts its own target: post id + 1-based file index. Both come
	// from data attributes on #download-container, not from the referer.
	$post_id     = AppSecurity::validate_number( $_POST['post_id'] ?? 0 );
	$file_number = absint( $_POST['file'] ?? 0 );

	// Validate post exists and is published
	$post = get_post( $post_id );
	if ( ! $post || $post->post_status !== 'publish' ) {
		wp_send_json_error( array( 'message' => __( 'Post not found or not published.', 'rom' ) ) );
	}
	
	// Validate post type
	if ( ! valid_downloadable_post_type( $post->post_type ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid post type for download.', 'rom' ) ) );
	}

	// Members-only posts (approach C) are NEVER served via this ungated admin-ajax
	// path. The URL must come from /wp-json/rf/v1/download-url after the member
	// session is verified. Refuse here so a guest can't bypass the gate.
	if ( rf_is_restricted( $post_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Please sign in to download.', 'rom' ), 'code' => 'members_only' ) );
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
		wp_send_json_error( array( 'message' => __( 'No download links available.', 'rom' ) ) );
	}
	
	if ( $file_number <= 0 ) {
		wp_send_json_error( array( 'message' => __( 'File index not specified.', 'rom' ) ) );
	}

	$file_index = $file_number - 1;
	
	// Validate file index is in range
	if ( ! isset( $downloads[ $file_index ] ) || empty( $downloads[ $file_index ]['url'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid file index.', 'rom' ) ) );
	}
	
	$link = $downloads[ $file_index ];
	
	// Validate link data
	if ( empty( $link['name'] ) || empty( $link['url'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid download link data.', 'rom' ) ) );
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
		   class="inline-flex items-center justify-center px-6 py-4 bg-rom-pink text-white rounded-full hover:bg-rom-pink-dark transition font-semibold shadow-lg hover:shadow-xl transform hover:scale-105"
		   rel="nofollow">
			<?php echo get_svg_icon( 'save' ) . ' ' . __( 'Download Now', 'rom' ) . ' ' . ' (' . $file_size . ')'; ?>
		</a>
		<p class="text-sm text-gray-500 mt-4">
			<?php esc_html_e( 'If the download doesn\'t start automatically, click the button above', 'rom' ); ?>
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