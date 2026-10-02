<?php


function k_grabber_igdb_init()
{
    /**
     * Create grabber_igdb page
     */
    if (function_exists('acf_add_options_page')) {
        acf_add_options_page(array(
            'page_title'       => 'Grabber igdb',
            'menu_title'      => 'Grabber igdb',
            'menu_slug'       => 'grabber_igdb',
            'icon_url'          => 'dashicons-download',
            'capability'      => 'manage_options',
            'id'              => 'grabber_igdb',
            'post_id'           => 'grabber_igdb',
            'parent'          => 'edit.php?post_type=rom',
        ));
    }

    /**
     * Add grabber_igdb field group
     */
    if (function_exists('acf_add_local_field_group')) {
        acf_add_local_field_group(array(
            'key'        => 'grabber_igdb_settings',
            'title'     => __('Settings', 'romsfun'),
            'fields'     => array(
                array(
                    'label'      => __('Console', 'romsfun'),
                    'name'       => 'grabber_igdb_console',
                    'key'        => 'grabber_igdb_console',
                    'type'       => 'taxonomy',
                    'taxonomy'   => 'console',
                    'field_type' => 'select',
                    'allow_null' => 1,
                ),
                array(
                    'label' => __('Type', 'romsfun'),
                    'name' => 'grabber_type',
                    'key' => 'grabber_type',
                    'type' => 'taxonomy',
                    'taxonomy' => 'type',
                    'field_type' => 'select',
                    'allow_null' => 1,
                ),
                array(
                    'label' => __('Url', 'romsfun'),
                    'name'  => 'grabber_igdb_url',
                    'key'   => 'grabber_igdb_url',
                    'type'  => 'url',
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param'    => 'options_page',
                        'operator' => '==',
                        'value'    => 'grabber_igdb',
                    ),
                ),
            ),
        ));
    }
}

add_action('init', 'k_grabber_igdb_init');

function k_grabber_igdb()
{
    try {
        // Login into igdb API
        $grant_type = 'client_credentials';
        $client_id = 'w38dtppzl9ms96wfq40dm8zuwqtue3';
        $client_secret = '8bf0jeapi6o5gufogwmkux4mgn4dmb';

        $requestURL = "https://id.twitch.tv/oauth2/token?client_id=$client_id&client_secret=$client_secret&grant_type=$grant_type&redirect_uri=https://romsfun.com/";

        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => '',
            ),
        );

        $context = stream_context_create($options);
        $loginResponse = json_decode(file_get_contents($requestURL, false, $context));
        $authorization = $loginResponse->token_type . ' ' . $loginResponse->access_token;

        // Validate input
        $params = $_REQUEST['acf'];
        if (empty($params['grabber_igdb_console'])) {
            _e('Please select console.', 'romsfun');
            exit;
        }

    

        if (empty($params['grabber_igdb_url'])) {
            _e('Please input url.', 'romsfun');
            exit;
        }

        $console = $params['grabber_igdb_console'];
        $type = $params['grabber_type'];
        $url = $params['grabber_igdb_url'];

        update_field('grabber_igdb_console', $console, 'grabber_igdb');
        update_field('grabber_igdb_url', $url, 'grabber_igdb');
        update_field('grabber_type', $type, 'grabber_rom');

        // Get data
        $gameData = getGameDataAPI($url, $authorization, $client_id)[0];
        $developedData = getCompanyDataAPI($gameData->id, $authorization, $client_id, 'developed') ?? [];
        $publishedData = getCompanyDataAPI($gameData->id, $authorization, $client_id, 'published') ?? [];

        // Check exist Post
        $title = $gameData->name;

        $title_1 = str_replace('’', '\'', $title);
        $title_2 = str_replace('\'', '’', $title);
        $title_3 = str_replace('&#x27;', '\'', $title);

        $post_id = rom_exists($title, $console);
        $post_id_1 = rom_exists($title_1, $console);
        $post_id_2 = rom_exists($title_2, $console);
        $post_id_3 = rom_exists($title_3, $console);

        if ($post_id > 0) {
            echo __('Post exists:', 'romsfun') . ' ' . '<a href="' . get_edit_post_link($post_id) . '" target="_blank">' . get_edit_post_link($post_id) . '</a>';
            exit;
        } elseif ($post_id_1 > 0) {
            echo __('Post exists:', 'romsfun') . ' ' . '<a href="' . get_edit_post_link($post_id_1) . '" target="_blank">' . get_edit_post_link($post_id_1) . '</a>';
            exit;
        } elseif ($post_id_2 > 0) {
            echo __('Post exists:', 'romsfun') . ' ' . '<a href="' . get_edit_post_link($post_id_2) . '" target="_blank">' . get_edit_post_link($post_id_2) . '</a>';
            exit;
        } elseif ($post_id_3 > 0) {
            echo __('Post exists:', 'romsfun') . ' ' . '<a href="' . get_edit_post_link($post_id_3) . '" target="_blank">' . get_edit_post_link($post_id_3) . '</a>';
            exit;
        } else {
        }

        // Break down image
        // https://api-docs.igdb.com/#images // Fully document
        $hash = $gameData->cover->image_id;
        $size = '1080p';
        $image = "https://images.igdb.com/igdb/image/upload/t_$size/$hash.jpg";

        // Options
        $release_date =  date("F j, Y", $gameData->first_release_date) ?? '';

        $genres = [];
        foreach ($gameData->genres as $genre) {
            $genres[] = $genre->name;
        }

        $devs = [];
        foreach ($developedData as $developer) {
            $devs[] = $developer->name;
        }

        $pubs = [];
        foreach ($publishedData as $publisher) {
            $pubs[] = $publisher->name;
        }

        // Insert new post
        $content = $gameData->summary;
        $post_id = wp_insert_post(array(
            'post_type'    => 'rom',
            'post_title'   => $title,
            'post_content' => $content
        ));

        $terms = array();
        $terms[] = $console;
        wp_set_post_terms($post_id, $terms, 'console');
        update_field('released', $release_date, $post_id);

if (!empty($type)) {
    wp_set_post_terms($post_id, [$type], 'type');
}
		
        // Set Post data
        if (!empty($image)) {
            $attach_id = Generate_Featured_Image($image, $title, $post_id);
            set_post_thumbnail($post_id, $attach_id);
        }

        if (!empty($genres)) {
            $terms = array();
            foreach ($genres as $genre) {
                if (term_exists($genre, 'genre')) {
                    $term = term_exists($genre, 'genre');
                } else {
                    $term = wp_insert_term($genre, 'genre');
                }
                $terms[] = $term['term_id'];
            }
            wp_set_post_terms($post_id, $terms, 'genre');
        }

        if (!empty($devs)) {
            $terms = array();
            foreach ($devs as $dev) {
                if (term_exists($dev, 'dev')) {
                    $term = term_exists($dev, 'dev');
                } else {
                    $term = wp_insert_term($dev, 'dev');
                }
                $terms[] = $term['term_id'];
            }
            wp_set_post_terms($post_id, $terms, 'dev');
        }

        if (!empty($pubs)) {
            $terms = array();
            foreach ($pubs as $pub) {
                if (term_exists($pub, 'pub')) {
                    $term = term_exists($pub, 'pub');
                } else {
                    $term = wp_insert_term($pub, 'pub');
                }
                $terms[] = $term['term_id'];
            }
            wp_set_post_terms($post_id, $terms, 'pub');
        }

        echo __('Post created:', 'romsfun') . ' ' . '<a href="' . get_edit_post_link($post_id) . '" target="_blank">' . get_edit_post_link($post_id) . '</a>';
    } catch (\Throwable $th) {
        _e('Grabber igdb catch ERROR.' . $th, 'romsfun');
        exit;
    }
}

function getGameDataAPI($url, $authorization, $client_id)
{
    $urlParts = explode('/', $url);
    $gameSlug = end($urlParts);

    $requestURL = "https://api.igdb.com/v4/games";
    $data = "'
            fields cover, cover.*;
            fields genres, genres.*;
            fields genres, genres.*;
            fields screenshots, screenshots.*;
            fields involved_companies, involved_companies.* ;
            fields *;
            where slug = \"" . $gameSlug . "\";'";

    $headers = [
        "Content-Type:application/json",
        "Authorization:$authorization",
        "Client-ID:$client_id"
    ];

    $ch = curl_init($requestURL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Check success response
    if ($http_status != 200) {
        _e('Grabber igdb getGameDataAPI ERROR.', 'romsfun');
        exit;
    }

    return json_decode($response);
}

function getCompanyDataAPI($gameId, $authorization, $client_id, $type)
{
    $requestURL = "https://api.igdb.com/v4/companies";
    $data = "'
            fields name;
            where $type = [$gameId];";

    $headers = [
        "Content-Type:application/json",
        "Authorization:$authorization",
        "Client-ID:$client_id"
    ];

    $ch = curl_init($requestURL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);

    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Check success response
    if ($http_status != 200) {
        _e('Grabber igdb getCompanyDataAPI ERROR.', 'romsfun');
        exit;
    }

    return json_decode($response);
}

add_action('wp_ajax_k_grabber_igdb', 'k_grabber_igdb');
