<?php

function get_user_IP() {

    $user_ip = '0.0.0.0';

    if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $user_ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    }

    return $user_ip;

}

function get_timestamp($x) {

    $currentUtcTime = new DateTime("now", new DateTimeZone("UTC"));

    $currentUtcTime->modify("+$x minutes");

    $futureTimestamp = $currentUtcTime->getTimestamp();

    return $futureTimestamp;
}

function get_xor_string($input, $key) {

    $output = '';
    for ($i = 0; $i < strlen($input); $i++) {
        $output .= chr(ord($input[$i]) ^ ord($key[$i % strlen($key)]));
    }

    return $output;

}

function get_download_token() {

    $password = 'ADiDaPhat!';
    $duration = 60;

    $input = get_user_IP() . '|' . get_timestamp($duration);

    $output = base64_encode(get_xor_string($input, $password));

    return $output;

}

/**
 * Every download page of a post: the file list, then one page per file
 * (1-based, normal links first, then the affiliate ones, as the endpoint numbers them).
 *
 * @return string[]
 */
function rom_download_urls( $post_id ) {
    $normal = get_field( 'download_list', $post_id );
    $aff    = get_field( 'download_list_aff', $post_id );
    $files  = array_merge( is_array( $normal ) ? $normal : array(), is_array( $aff ) ? $aff : array() );

    $urls = array( rom_download_url( $post_id ) );
    foreach ( array_keys( $files ) as $index ) {
        $urls[] = rom_download_url( $post_id, $index + 1 );
    }

    return $urls;
}

add_filter('cloudflare_purge_by_url', 'purge_cloudflare_url', 10, 2);
function purge_cloudflare_url($urls, $post_id) {
    return array_merge( $urls, rom_download_urls( $post_id ) );
}

/**
 * Whenever WP Rocket purges a rom or emulator (post update, status change, a rating
 * via rf_rating_recount()), purge its download pages too: they repeat the title,
 * rating and file list.
 */
add_filter( 'rocket_post_purge_urls', function ( $urls, $post ) {
    if ( $post instanceof WP_Post && in_array( $post->post_type, array( 'rom', 'emulator' ), true ) ) {
        $urls = array_merge( $urls, rom_download_urls( $post->ID ) );
    }
    return $urls;
}, 10, 2 );