<?php
/**
 * Member action — gated download URL. Restricted (members-only) posts return
 * their real file URL only here, after the rf_member session is verified;
 * non-restricted posts pass through for guests. k_get_download refuses
 * restricted posts. Depends on member-core.php (current member, rf_is_restricted).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', function () {
	register_rest_route( RF_REST_NS, '/download-url', array(
		'methods'             => 'GET',
		'callback'            => 'rf_rest_download_url',
		'permission_callback' => '__return_true', // session checked in-handler (SameSite=Lax blocks cross-site)
	) );
} );

/**
 * Build the final external download URL for a post + 1-based file index (same
 * resolution + affiliate + token logic as download.php / k_get_download). Null
 * if the file doesn't exist.
 */
function rf_build_download_url( $post_id, $file_index_1based ) {
	$normal = get_field( 'download_list', $post_id );
	$aff    = get_field( 'download_list_aff', $post_id );
	$downloads = array();
	if ( is_array( $normal ) && is_array( $aff ) ) {
		$downloads = array_merge( $normal, $aff );
	} elseif ( is_array( $normal ) ) {
		$downloads = $normal;
	} elseif ( is_array( $aff ) ) {
		$downloads = $aff;
	}
	$i = (int) $file_index_1based - 1;
	if ( $i < 0 || empty( $downloads[ $i ]['url'] ) ) {
		return null;
	}
	$link_url = esc_url_raw( $downloads[ $i ]['url'] );
	$affs     = get_field( 'affs', 'option' );
	if ( is_array( $affs ) ) {
		foreach ( $affs as $a ) {
			if ( ! empty( $a['aff_host'] ) && ! empty( $a['aff_code'] ) && strpos( $link_url, $a['aff_host'] ) !== false ) {
				$sep       = parse_url( $link_url, PHP_URL_QUERY ) ? '&' : '?';
				$link_url .= $sep . sanitize_text_field( $a['aff_code'] );
				break;
			}
		}
	}
	return add_query_arg( 'token', urlencode( get_download_token() ), $link_url );
}

/**
 * GET /wp-json/rf/v1/download-url?post=ID&file=IDX
 * For members-only posts: require a valid, non-blocked member session, then
 * return { url }. Non-members posts pass through (guests allowed).
 */
function rf_rest_download_url( WP_REST_Request $request ) {
	$post_id = absint( $request->get_param( 'post' ) );
	$file    = absint( $request->get_param( 'file' ) );
	if ( ! $post_id || ! $file ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'bad_request' ), 400 );
	}
	$post = get_post( $post_id );
	if ( ! $post || $post->post_status !== 'publish' ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'not_found' ), 404 );
	}

	if ( rf_is_restricted( $post_id ) ) {
		$member = rf_current_member();
		if ( ! $member ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'auth_required' ), 401 );
		}
		if ( (int) $member['blocked'] === 1 ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'blocked', 'message' => __( 'Your account has been blocked.', 'rom' ) ), 403 );
		}
	}

	$url = rf_build_download_url( $post_id, $file );
	if ( ! $url ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'no_file' ), 404 );
	}
	return new WP_REST_Response( array( 'ok' => true, 'url' => $url ), 200 );
}
