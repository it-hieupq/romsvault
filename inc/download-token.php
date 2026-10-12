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

add_filter('cloudflare_purge_by_url', 'purge_cloudflare_url', 10, 2);
function purge_cloudflare_url($urls, $post_id) {

    $download_url = trailingslashit( get_permalink( $post_id ) ) . 'download/';

    // Add download url

    $urls[] = $download_url;

    // Add download files url

    $normal_downloads = get_field('download_list', $post_id);
    $aff_downloads = get_field('download_list_aff', $post_id);

    if ( $normal_downloads && $aff_downloads ) {
        $downloads = array_merge($normal_downloads, $aff_downloads);
    }
    elseif ( $normal_downloads ) {
        $downloads = $normal_downloads;
    }
    elseif ( $aff_downloads ) {
        $downloads = $aff_downloads;
    }
    else {
        $downloads = array();
    }

    foreach ( $downloads as $key => $value ) {
        $urls[] = $download_url . ($key + 1) . '/';
    }

    // send to cloudflare
    return $urls;

}