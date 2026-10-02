<?php

function get_s3_roms($url)
{

    $cache_key = 's3_' . sanitize_title($url);

    $result = get_post_meta(2, $cache_key, true);

    // Disable cache
    $result = false;

    if (is_array($result)) {

        return $result;

    } else {

        $response = wp_remote_get($url, array(
            'timeout' => 120,
        ));

        $responseBody = wp_remote_retrieve_body($response);

        if (preg_match_all('/<tr>(.*?)<\/tr>/ui', $responseBody, $matches)) {

            $result = array();

            foreach ($matches[1] as $html_item) {
                
                // For link https://ratbip.liteapks.com/r2/list.php?folder=NEC%20PC-9801/Redump/
                if (preg_match('/<td class="name"><a href="(.*?)">(.*)<\/a><\/td><td>(.*)<\/td><td class="size">(.*)<\/td>/ui', $html_item, $item_matches)) {
                    
                    $result[] = array(
                        'name' => $item_matches[2],
                        'url' => $item_matches[1],
                        'size' => $item_matches[4],
                        'linked' => false,
                    );
                    
                }
                // For link https://romsfun.com/finder-v2?folder=3DO-CHD
                else if (preg_match('/<td class="action"><a class="download-link" href="(.*?)">(.*)<\/a><\/td><td class="size">(.*)<\/td>/ui', $html_item, $item_matches)) {
                    
                    $result[] = array(
                        'name' => trim(strip_tags($item_matches[2])),
                        'url' => $item_matches[1],
                        'size' => trim(strip_tags($item_matches[3])),
                        'linked' => false,
                    );
                    
                }
                
            }

            update_post_meta(2, $cache_key, $result);

            return $result;

        }
    }

    return false;

}

function compare_slug($post_slug, $file_slug) {

    $post_slug_array = explode('-', $post_slug);
    $file_slug_array = explode('-', $file_slug);

    $roman_numerals = array('I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X');
    $numbers = array('1', '2', '3', '4', '5', '6', '7', '8', '9', '10');

    $match = false;

    if ( str_contains($file_slug, $post_slug)) {

        $match = true;

        // Exclude -2 -3 -4...
        // Eg: pokemon and pokemon-2
        foreach ($numbers as $num) {

            if (str_contains($file_slug, $post_slug . '-' . $num . '-' )) {
                $match = false;
            }

        }

        // Exclude title + bla bla
        // Eg: pokemon-iii and pokemon-i
        if (count(array_diff($post_slug_array, $file_slug_array)) > 0) {
            $match = false;
        }

    }

    return $match;
}

function compare_array($post_array, $file_array) {

    $match = false;

    if (count($post_array) > 2 && count($file_array) > 3 && count(array_diff($post_array, $file_array)) == 0 ) {

        $match = true;

        $post_slug = implode('-', $post_array);
        $file_slug = implode('-', $file_array);

        $numbers = array('1', '2', '3', '4', '5', '6', '7', '8', '9', '10');

        foreach ($numbers as $num) {

            if (str_contains($file_slug, $post_slug . '-' . $num . '-' )) {
                $match = false;
            }

        }

    }

    return $match;

}

function compare_name($origin_post_title, $origin_file_title) {

    $roman_numerals = array('I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X');
    $numbers = array('1', '2', '3', '4', '5', '6', '7', '8', '9', '10');

    $match = false;

    $post_title = sanitize_title($origin_post_title);
    $file_title = sanitize_title($origin_file_title);

    $post_title_array = explode('-', $post_title);
    $file_title_array = explode('-', $file_title);

    if ( compare_slug($post_title, $file_title)) {
        $match = true;
    }
    else {

        if (compare_array($post_title_array, $file_title_array) ) {
            $match = true;
        }

        $i = 1;
        foreach ($roman_numerals as $roman_numeral) {

            if ( preg_match('/ ' . $roman_numeral . '$/ui', $origin_post_title, $roman_numeral_matches) ) {

                $post_title = sanitize_title(str_replace(' ' . $roman_numeral, ' ' . $i, $origin_post_title));

                if ( str_contains($file_title, $post_title)) {
                    $match = true;
                }

            }
            $i++;
        }

        if (str_contains($origin_post_title, ' and ')) {

            $origin_post_title = str_replace(' and ', ' ', $origin_post_title);
            $post_title = sanitize_title($origin_post_title);

            if ( compare_slug($post_title, $file_title)) {
                $match = true;
            }
        }

        if (str_contains($origin_post_title, '’s ')) {

            $origin_post_title = str_replace('’s ', ' ', $origin_post_title);
            $post_title = sanitize_title($origin_post_title);

            if ( compare_slug($post_title, $file_title)) {
                $match = true;
            }
        }

    }

    return $match;

}

function k_insert_link_s3_init()
{
    /**
     * Create insert_link_s3 page
     */
    if (function_exists('acf_add_options_page')) {
        acf_add_options_page(array(
            'page_title' => 'Insert Link S3',
            'menu_title' => 'Insert Link S3',
            'menu_slug' => 'insert_link_s3',
            'icon_url' => 'dashicons-insert',
            'capability' => 'manage_options',
            'id' => 'insert_link_s3',
            'post_id' => 'insert_link_s3',
        ));
    }

    /**
     * Add insert_link_s3 field group
     */
    if (function_exists('acf_add_local_field_group')) {
        acf_add_local_field_group(array(
            'key' => 'insert_link_s3_settings',
            'title' => __('Settings', 'romsfun'),
            'fields' => array(
                array(
                    'label' => __('Console', 'romsfun'),
                    'name' => 'insert_link_s3_console',
                    'key' => 'insert_link_s3_console',
                    'type' => 'taxonomy',
                    'taxonomy' => 'console',
                    'field_type' => 'select',
                    'allow_null' => 1,
                ),
                array(
                    'label' => __('Url', 'romsfun'),
                    'name' => 'insert_link_s3_url',
                    'key' => 'insert_link_s3_url',
                    'type' => 'text',
                ),
                array(
                    'label' => __('Note', 'romsfun'),
                    'name' => 'insert_link_s3_note',
                    'key' => 'insert_link_s3_note',
                    'type' => 'text',
                ),
                array(
                    'label' => __('Post Status', 'romsfun'),
                    'name' => 'insert_link_s3_post_status',
                    'key' => 'insert_link_s3_post_status',
                    'type' => 'radio',
                    'choices' => array(
                        'any' => 'All',
                        'draft' => 'Draft',
                        'publish' => 'Publish',
                    ),
                    'layout' => 'horizontal',
                ),
                array(
                    'label' => __('Post Download', 'romsfun'),
                    'name' => 'insert_link_s3_post_download',
                    'key' => 'insert_link_s3_post_download',
                    'type' => 'radio',
                    'choices' => array(
                        'default' => 'Default',
                        'no_link_only' => 'No Link Only',
                    ),
                    'layout' => 'horizontal',
                ),
                array(
                    'label' => __('Remove', 'romsfun'),
                    'name' => 'insert_link_s3_remove',
                    'key' => 'insert_link_s3_remove',
                    'type' => 'true_false',
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'options_page',
                        'operator' => '==',
                        'value' => 'insert_link_s3',
                    ),
                ),
            ),
        ));
    }
}

add_action('init', 'k_insert_link_s3_init');

/**
 * Insert Link S3
 */
function k_insert_link_s3()
{

    $posts_per_page = 100;

    $params = $_REQUEST['acf'];
    if (empty($params['insert_link_s3_console'])) {
        header('Content-Type: application/json');
        echo json_encode(array('status' => false, 'message' => __('Please select console.', 'romsfun')));
        exit;
    }
    if (empty($params['insert_link_s3_url'])) {
        header('Content-Type: application/json');
        echo json_encode(array('status' => false, 'message' => __('Please input url.', 'romsfun')));
        exit;
    }

    $console = $params['insert_link_s3_console'];
    $url = $params['insert_link_s3_url'];
	// Replace spaces to %20 in the URL, same with manual add link
    $url = str_replace(' ', '%20', $url);
    $note = $params['insert_link_s3_note'];
    $post_status = $params['insert_link_s3_post_status'];
    $post_download = $params['insert_link_s3_post_download'];
    $remove = $params['insert_link_s3_remove'];
    update_field('insert_link_s3_console', $console, 'insert_link_s3');
    update_field('insert_link_s3_url', $url, 'insert_link_s3');
    update_field('insert_link_s3_note', $note, 'insert_link_s3');

    if ($remove) {

        $args = array(
            'post_type' => 'rom',
            'post_status' => $post_status,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'title',
            'order' => 'DESC',
            'tax_query' => array(
                array(
                    'taxonomy' => 'console',
                    'field' => 'term_id',
                    'terms' => $console,
                ),
            ),
        );
        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                delete_field('download_list');
            }
        }

        header('Content-Type: application/json');
        echo json_encode(array('status' => false, 'message' => __('Remove Link S3 is completed.', 'romsfun')));

    } else {

        $offset = $_REQUEST['offset'];

        $args = array(
            'post_type' => 'rom',
            'post_status' => $post_status,
            'posts_per_page' => $posts_per_page,
            'offset' => $offset,
            'fields' => 'ids',
            'orderby' => 'title',
            'order' => 'DESC',
            'tax_query' => array(
                array(
                    'taxonomy' => 'console',
                    'field' => 'term_id',
                    'terms' => $console,
                ),
            ),
        );

        $query = new WP_Query($args);

        $s3_roms = get_s3_roms($url);

        if ($s3_roms) {

            if ($query->have_posts()) {

                $message = '';

                while ($query->have_posts()) {

                    $query->the_post();

                    $links = '';

                    $title = get_the_title();

                    //echo '<strong style="color:red">' . $title . ' </strong><br>';

                    $i = 0;
                    foreach ($s3_roms as $s3_rom) {

                        if ($s3_rom['linked'] === false) {

                            $s3_rom['name'] = str_replace(array('.zip', '.chd', '.7z'), array('', '', ''), $s3_rom['name']);

                            if (compare_name($title, $s3_rom['name'])) {

                                $should_add = true;

                                //echo $s3_rom['name'] . '<br>';

                                if ($post_download == 'no_link_only' && have_rows('download_list')) {

                                }
                                else {

                                    if (have_rows('download_list')) {

                                        while (have_rows('download_list')) {

                                            the_row();

                                            if (get_sub_field('url') == $s3_rom['url']) {

                                                $links .= '<br>Existed: ' . $s3_rom['name'];

                                                $should_add = false;

                                            }

                                        }

                                    }

                                    if ($should_add) {

                                        $new_row = array(
                                            'name' => $s3_rom['name'],
                                            'url' => $s3_rom['url'],
                                            'file_size' => $s3_rom['size'],
                                            'file_type' => $note
                                        );

                                        add_row('download_list', $new_row);

                                        $links .= '<br>Added: ' . $new_row['name'];

                                    }

                                }

                                $s3_roms[$i]['linked'] = true;

                            }

                        }

                        $i++;
                    }

                    //echo '<br>';

                    if ($links) {
                        $message .= '<li><a href="' . get_edit_post_link() . '" target="_blank">' . get_the_title() . '</a> ' . $links . '</li>';
                    }
                }

                header('Content-Type: application/json');
                echo json_encode(array('status' => true, 'offset' => $offset + $posts_per_page, 'message' => $message));

            } else {

                header('Content-Type: application/json');
                echo json_encode(array('status' => false, 'message' => __('Insert Link S3 is completed.', 'romsfun')));

            }
        }

    }

    exit;
}

add_action('wp_ajax_k_insert_link_s3', 'k_insert_link_s3');