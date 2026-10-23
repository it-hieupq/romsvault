<?php

function k_grabber_multi_init()
{
    /**
     * Create grabber_multi page
     */
    if (function_exists('acf_add_options_page')) {
        acf_add_options_page(array(
            'page_title' => 'Multi Grabber',
            'menu_title' => 'Multi Grabber',
            'menu_slug' => 'grabber_multi',
            'icon_url' => 'dashicons-download',
            'capability' => 'manage_options',
            'id' => 'grabber_multi',
            'post_id' => 'grabber_multi',
            'parent' => 'edit.php?post_type=rom',
        ));
    }

    /**
     * Add grabber_multi field group
     */
    if (function_exists('acf_add_local_field_group')) {
        acf_add_local_field_group(array(
            'key' => 'grabber_multi_settings',
            'title' => __('Settings', 'rom'),
            'fields' => array(
                array(
                    'label' => __('Console', 'rom'),
                    'name' => 'grabber_multi_console',
                    'key' => 'grabber_multi_console',
                    'type' => 'taxonomy',
                    'taxonomy' => 'console',
                    'field_type' => 'select',
                    'allow_null' => 1,
                ),
                array(
                    'label' => __('Type', 'rom'),
                    'name' => 'grabber_type',
                    'key' => 'grabber_type',
                    'type' => 'taxonomy',
                    'taxonomy' => 'type',
                    'field_type' => 'select',
                    'allow_null' => 1,
                ),
                array(
                    'label' => __('URLs', 'rom'),
                    'name' => 'grabber_multi_urls',
                    'key' => 'grabber_multi_urls',
                    'type' => 'textarea',
                    'placeholder' => 'Enter URLs, one per line',
                    'rows' => 10,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'options_page',
                        'operator' => '==',
                        'value' => 'grabber_multi',
                    ),
                ),
            ),
        ));
    }
}

add_action('init', 'k_grabber_multi_init');

function k_grabber_multi()
{
    $params = $_REQUEST['acf'];

    if (empty($params['grabber_multi_console'])) {
        _e('Please select console.', 'rom');
        exit;
    }

    // if (empty($params['grabber_type'])) {
    // 	_e('Please select type.', 'rom');
    // 	exit;
    // }

    if (empty($params['grabber_multi_urls'])) {
        _e('Please input url.', 'rom');
        exit;
    }

    $index = $params['index'];
    $message = "";
    $type = $params['grabber_type'];
    $console = $params['grabber_multi_console'];
    require_once get_template_directory() . '/grabber/simple_html_dom.php';

    $urls_array = [$params['request_url']];
    if (empty($urls_array)) {
        _e('Please input valid URLs.', 'rom');
        exit;
    }

    // Refill field
    update_field('grabber_multi_urls', $params['grabber_multi_urls'], 'grabber_multi');
    update_field('grabber_multi_console', $console, 'grabber_multi');
    update_field('grabber_type', $type, 'grabber_multi');

    foreach ($urls_array as $url) {
        switch (true) {
            case strpos($url, 'mobygames.com') !== false:
                grabber_mobygames_ac($url, $console, $type, $index);
                break;
            case strpos($url, 'retrostic.com') !== false:
                grabber_retrostic_ac($url, $console, $type, $index);
                break;
            case strpos($url, 'igdb.com') !== false:
                grabber_igdb_ac($url, $console, $type, $index);
                break;
            case strpos($url, 'romstation.fr') !== false:
                grabber_romstation_ac($url, $console, $type, $index);
                break;
            case strpos($url, 'gamesdb.launchbox-app.com') !== false:
                grabber_launchbox_ac($url, $console, $type, $index);
                break;
            case strpos($url, 'gog.com') !== false:
                grabber_gog_ac($url, $console, $type, $index);
                break;
            case strpos($url, 'backloggd.com') !== false:
                grabber_backloggd_ac($url, $console, $type, $index);
                break;
            case strpos($url, 'romhackplaza.org') !== false:
                grabber_romhackplaza_ac($url, $console, $type, $index);
                break;
            case strpos($url, 'nintendolife.com') !== false:
                grabber_nintendolife_ac($url, $console, $type, $index);
                break;
            case strpos($url, 'pushsquare.com') !== false:
                grabber_pushsquare_ac($url, $console, $type, $index);
                break;
            default:
                echo json_encode(array('status' => false, 'message' => $index . '. ' . __('The URL must belong to one of the following domains: <strong>mobygames.com, retrostic.com, igdb.com, romstation.fr, gamesdb.launchbox-app.com, gog.com, backloggd.com, romhackplaza.org</strong>' . '<br>', 'rom')));
                break;
        }
    }
    exit;
}

add_action('wp_ajax_k_grabber_multi', 'k_grabber_multi');

function grabber_mobygames_ac($url, $console, $type, $stt)
{
    try {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, $url);
        $str = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($httpCode == 200) {
            $html = new simple_html_dom();
            $html->load($str);
            $title = trim($html->find('h1', 0)->plaintext);

            $temp_post_id = 0;

            // Tìm xem có post trùng tên vs console không

            $title_variants = array(
                $title,                                 // Tiêu đề gốc
                str_replace("\u{2019}", "'", $title),   // Nháy đơn Unicode sang ASCII (cách 1)
                str_replace("'", "'", $title),          // Nháy đơn ASCII sang Unicode (cách 1)
                str_replace('&#x27;', "'", $title),     // HTML entity sang ASCII
                str_replace('&#39;', "'", $title),      // HTML entity khác sang ASCII
                html_entity_decode($title)              // Giải mã tất cả HTML entity
            );

            foreach ($title_variants as $variant) {

                $temp_post_id = rom_exists($variant, $console);

                if ($temp_post_id > 0) {
                    break;
                }
            }
            if ($temp_post_id > 0) {
                echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Existed:', 'rom') . ' <a href="' . get_edit_post_link($temp_post_id) . '">' . get_the_title($temp_post_id) . '</a><br>'));
                exit();
            } else {

                if ($temp = $html->find('#cover img', 0)) {
                    $image = $temp->src;
                    $image = str_replace('.webp', '.jpg', $image);
                }

                $options = $html->find('#infoBlock dt');
                if ($options) {
                    foreach ($options as $option) {
                        if (trim($option->plaintext) == 'Released') {
                            if ($value = $option->next_sibling()->find('a', 0)) {
                                $release_date = trim($value->plaintext);
                            }
                        }

                        if (trim($option->plaintext) == 'Genre') {
                            if ($values = $option->next_sibling()->find('a')) {
                                foreach ($values as $value) {
                                    $genres[] = trim($value->plaintext);
                                }
                            }
                        }

                        if (trim($option->plaintext) == 'Developers') {
                            if ($values = $option->next_sibling()->find('a')) {
                                foreach ($values as $value) {
                                    $devs[] = trim($value->plaintext);
                                }
                            }
                        }

                        if (trim($option->plaintext) == 'Publishers') {
                            if ($values = $option->next_sibling()->find('a')) {
                                foreach ($values as $value) {
                                    $pubs[] = trim($value->plaintext);
                                }
                            }
                        }
                    }
                }

                $temp_e = $html->find('#description-text a', 0);
                if ($temp_e !== null) {
                    $temp_e->outertext = '';
                }
                $content = $html->find('#description-text', 0)->innertext;

                $post_id = wp_insert_post(array(
                    'post_type' => 'rom',
                    'post_title' => $title,
                    'post_content' => $content
                ));

                $terms = array();
                $terms[] = $console;
                wp_set_post_terms($post_id, $terms, 'console');
                if (!empty($type)) {
                    wp_set_post_terms($post_id, [$type], 'type');
                }

                update_field('released', $release_date, $post_id);

                if (!empty($image)) {
                    $attach_id = Generate_Featured_Image($image, $title, $post_id);
                    set_post_thumbnail($post_id, $attach_id);
                }

                if (!empty($genres)) {
                    $terms = array();
                    foreach (k_grabber_genre_names($genres) as $genre) {
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
                    foreach (k_grabber_company_names($devs) as $dev) {
                        if (term_exists($dev, 'dev')) {
                            $term = term_exists($dev, 'dev');
                        } else {
                            $term = wp_insert_term($dev, 'dev');
                        }
                        $terms[] = $term['term_id'];
                    }
                    wp_set_post_terms($post_id, $terms, 'dev');
                }

               wp_set_post_terms($post_id, k_grabber_company_names($pubs), 'pub');
				
                echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Created:', 'rom') . ' <a href="' . get_edit_post_link($post_id) . '">' . get_the_title($post_id) . '</a><br>'));
            }
            $html->clear();
            unset($html);
        } else {
            echo json_encode(array('status' => true, 'message' => $stt . '. ' . 'Grabber Mobygames ERROR.' . '<br>', 'rom'));
        }
    } catch (\Throwable $th) {
        echo json_encode(array('status' => true, 'message' => $stt . '. ' . 'Grabber Mobygames ERROR.' . '<br>', 'rom'));
    }
}

function grabber_retrostic_ac($url, $console, $type, $stt)
{
    try {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, $url);
        $str = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($httpCode == 200) {

            $html = new simple_html_dom();
            $html->load($str);

            if (strpos($url, '/romhacks/') != false) {
                $title = trim($html->find('span', 4)->plaintext);
            }
            if (strpos($url, '/roms/') != false) {
                $title = trim($html->find('span', 5)->plaintext);
            }

            $temp_post_id = 0;

            // Tìm xem có post trùng tên vs console không

            $title_variants = array(
                $title,                                 // Tiêu đề gốc
                str_replace("\u{2019}", "'", $title),   // Nháy đơn Unicode sang ASCII (cách 1)
                str_replace("'", "'", $title),          // Nháy đơn ASCII sang Unicode (cách 1)
                str_replace('&#x27;', "'", $title),     // HTML entity sang ASCII
                str_replace('&#39;', "'", $title),      // HTML entity khác sang ASCII
                html_entity_decode($title)              // Giải mã tất cả HTML entity
            );
			
            foreach ($title_variants as $variant) {

                $temp_post_id = rom_exists($variant, $console);

                if ($temp_post_id > 0) {
                    break;
                }
            }
            if ($temp_post_id > 0) {
                echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Existed:', 'rom') . ' <a href="' . get_edit_post_link($temp_post_id) . '">' . get_the_title($temp_post_id) . '</a><br>'));
                exit();
            } else {

                // Image
                if ($temp = $html->find('.img-fluid')[0]->src) {
                    $image = "https://www.retrostic.com$temp";
                    // $image = str_replace('.webp', '.jpg', $image);
                }

                // Option
                $options = $html->find('#tableRegisterKids tbody tr');
                if ($options) {
                    foreach ($options as $option) {
                        $td = $option->find('td');
                        // Release date
                        if ($td[0]->plaintext == 'Date Created:') {
                            $release_date = trim($td[1]->plaintext);
                        }
                        // Genre
                        if ($td[0]->plaintext == 'Genre:' || $td[0]->plaintext == 'Genre/s:') {
                            $genreString = trim($td[1]->plaintext);

                            $genres = explode('>', $genreString);
                            foreach ($genres as $key => $genre) {
                                $genres[$key] = trim($genre);
                            }
                        }
                    }
                }

                // download file
                if (strpos($url, '/romhacks/') != false) {
                    $tempType = 'romhacks';
                    $romHackData = $html->find('#tableRomhackData tbody tr');
                }
                if (strpos($url, '/roms/') != false) {
                    $tempType = 'roms';
                    $romHackData = $html->find('#tableRegisterKids tbody tr');
                }

                foreach ($romHackData as $romHack) {
                    $t = $romHack->find('td');
                    if (trim($t[0]->plaintext) == 'File Name:') {
                        // $file_url = "https://downloads.retrostic.com/$tempType/" . urlencode($t[1]->plaintext);
                        $file_url = "https://downloads.retrostic.com/$tempType/" . str_replace(
                            [' ', '(', ')', '.'],
                            ['%20', '%28', '%29', '%2E'],
                            $t[1]->plaintext
                        );

                        break;
                    }
                }

                $content = $html->find('.card p', 0);
                $post_id = wp_insert_post(array(
                    'post_type' => 'rom',
                    'post_title' => $title,
                    'post_content' => $content
                ));


                $terms = array();
                $terms[] = $console;
                wp_set_post_terms($post_id, $terms, 'console');
                if (!empty($type)) {
                    wp_set_post_terms($post_id, [$type], 'type');
                }

                update_field('released', $release_date, $post_id);

                if (!empty($image)) {
                    $attach_id = Generate_Featured_Image($image, $title, $post_id);
                    set_post_thumbnail($post_id, $attach_id);
                }

                if (!empty($genres)) {
                    $terms = array();
                    foreach (k_grabber_genre_names($genres) as $genre) {
                        if (term_exists($genre, 'genre')) {
                            $term = term_exists($genre, 'genre');
                        } else {
                            $term = wp_insert_term($genre, 'genre');
                        }
                        $terms[] = $term['term_id'];
                    }
                    wp_set_post_terms($post_id, $terms, 'genre');
                }

                if (!empty($file_url)) {
                    $download_data = save_file_from_url($file_url);
                    add_row('download_list', $download_data, $post_id);
                }

                echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Created:', 'rom') . ' <a href="' . get_edit_post_link($post_id) . '">' . get_the_title($post_id) . '</a><br>'));
            }
            $html->clear();
            unset($html);
            return;
        }
        echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Grabber Retrostic ERROR.<br>', 'rom')));
    } catch (\Throwable $th) {
        echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Grabber Retrostic ERROR.<br>', 'rom')));
    }
}

function grabber_igdb_ac($url, $console, $type, $stt)
{
    try {
        // Login into igdb API
        $grant_type = 'client_credentials';
        $client_id = 'w38dtppzl9ms96wfq40dm8zuwqtue3';
        $client_secret = '8bf0jeapi6o5gufogwmkux4mgn4dmb';

        $redirect_uri = site_url( '/' );
        $requestURL = "https://id.twitch.tv/oauth2/token?client_id=$client_id&client_secret=$client_secret&grant_type=$grant_type&redirect_uri=$redirect_uri";

        $options = array(
            'http' => array(
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => '',
            ),
        );

        $context = stream_context_create($options);
        $loginResponse = json_decode(file_get_contents($requestURL, false, $context));
        $authorization = $loginResponse->token_type . ' ' . $loginResponse->access_token;

        // Get data
        $gameData = getGameDataAPI($url, $authorization, $client_id);
        $gameData = json_decode($gameData)[0];
        $developedData = getCompanyDataAPI($gameData->id, $authorization, $client_id, 'developed') ?? [];
        $publishedData = getCompanyDataAPI($gameData->id, $authorization, $client_id, 'published') ?? [];

        // Check exist Post
        $title = $gameData->name;

        $temp_post_id = 0;

        // Tìm xem có post trùng tên vs console không

        $title_variants = array(
            $title,                                 // Tiêu đề gốc
            str_replace("\u{2019}", "'", $title),   // Nháy đơn Unicode sang ASCII (cách 1)
            str_replace("'", "'", $title),          // Nháy đơn ASCII sang Unicode (cách 1)
            str_replace('&#x27;', "'", $title),     // HTML entity sang ASCII
            str_replace('&#39;', "'", $title),      // HTML entity khác sang ASCII
            html_entity_decode($title)              // Giải mã tất cả HTML entity
        );

        foreach ($title_variants as $variant) {

            $temp_post_id = rom_exists($variant, $console);

            if ($temp_post_id > 0) {
                break;
            }
        }
        if ($temp_post_id > 0) {
            echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Existed:', 'rom') . ' <a href="' . get_edit_post_link($temp_post_id) . '">' . get_the_title($temp_post_id) . '</a><br>'));
            exit();
        } else {
        }

        // Break down image
        // https://api-docs.igdb.com/#images // Fully document
        // A game with no cover has no image_id. Building the URL anyway asked
        // IGDB for ".../t_1080p/.jpg", whose error page was stored as the cover.
        $hash = $gameData->cover->image_id ?? '';
        $size = '1080p';
        $image = '' !== $hash ? "https://images.igdb.com/igdb/image/upload/t_$size/$hash.jpg" : '';

        // Options
        $release_date = date("F j, Y", $gameData->first_release_date) ?? '';

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
            'post_type' => 'rom',
            'post_title' => $title,
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
            foreach (k_grabber_genre_names($genres) as $genre) {
                if (term_exists($genre, 'genre')) {
                    $term = term_exists($genre, 'genre');
                } else {
                    $term = wp_insert_term($genre, 'genre');
                }
                $terms[] = $term['term_id'];
            }
            wp_set_post_terms($post_id, $terms, 'genre');
        }

wp_set_post_terms($post_id, k_grabber_company_names($devs), 'dev');
wp_set_post_terms($post_id, k_grabber_company_names($pubs), 'pub');

        //Screenshot
        $screenshots = [];
        if (!empty($gameData->screenshots)) {
            foreach ($gameData->screenshots as $screenshot) {
                $image_id = $screenshot->image_id;
                $image_url = "https://images.igdb.com/igdb/image/upload/t_screenshot_huge/{$image_id}.jpg";
                $screenshots[] = $image_url;
            }
        }
        $attach_id = [];
        $count = 0;
        foreach ($screenshots as $screenshot) {
            if ($count >= 5) {
                break;
            }
            $attach_id[] = Generate_Featured_Image($screenshot, $title, $post_id);
            $count++;
        }

        if (!empty($attach_id)) {
            update_field('image', $attach_id, $post_id);
        }



        echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Created:', 'rom') . ' <a href="' . get_edit_post_link($post_id) . '">' . get_the_title($post_id) . '</a><br>'));
    } catch (\Throwable $th) {
        echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Grabber Igdb ERROR.' . '<br>', 'rom')));
    }
}

function grabber_romstation_ac($url, $console, $type, $stt)
{
    try {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, $url);
        $str = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($httpCode == 200) {

            $html = new simple_html_dom();
            $html->load($str);

            if ($temp = $html->find('#game_page_jaquette img', 0)) {
                $image = 'https://www.romstation.fr' . $temp->src;
            }

            $title = trim($html->find('.ipsType_pageTitle', 0)->plaintext);

            $options = $html->find('#option_game_all p');
            if ($options) {
                foreach ($options as $option) {
                    if (trim($option->find('b', 0)->plaintext) == 'Console :') {
                        if ($values = $option->find('a')) {
                            foreach ($values as $value) {
                                $consoles[] = trim($value->plaintext);
                            }
                        }
                    }

                    if (trim($option->find('b', 0)->plaintext) == 'Language :') {
                        if ($values = $option->find('a img')) {
                            foreach ($values as $value) {
                                $regions[] = trim($value->title);
                            }
                        }
                    }

                    if (trim($option->find('b', 0)->plaintext) == 'Genre :') {
                        if ($values = $option->find('a')) {
                            foreach ($values as $value) {
                                $genres[] = trim($value->plaintext);
                            }
                        }
                    }

                    if (trim($option->find('b', 0)->plaintext) == 'Developer :') {
                        if ($values = $option->find('a')) {
                            foreach ($values as $value) {
                                $devs[] = trim($value->plaintext);
                            }
                        }
                    }
                }
            }

            $html->find('.game_page_description a', 0)->outertext = '';
            $content = $html->find('.game_page_description', 0)->innertext;

            $temp_post_id = 0;

            $title_variants = array(
                $title,
                str_replace('’', '\'', $title),
                str_replace('\'', '’', $title),
                str_replace('&#x27;', '\'', $title)
            );

            foreach ($title_variants as $variant) {

                $temp_post_id = rom_exists($variant, $console);

                if ($temp_post_id > 0) {
                    break;
                }
            }
            if ($temp_post_id > 0) {
                echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Existed:', 'rom') . ' <a href="' . get_edit_post_link($temp_post_id) . '">' . get_the_title($temp_post_id) . '</a><br>'));
                exit();
            } else {

                $post_id = wp_insert_post(array(
                    'post_type' => 'rom',
                    'post_title' => $title,
                    'post_content' => $content
                ));

                if (!empty($image)) {
                    $attach_id = Generate_Featured_Image($image, $title, $post_id);
                    set_post_thumbnail($post_id, $attach_id);
                }

                wp_set_post_terms($post_id, array($console), 'console');
                if (!empty($type)) {
                    wp_set_post_terms($post_id, [$type], 'type');
                }

                if (!empty($regions)) {
                    $terms = array();
                    foreach (k_grabber_region_names($regions) as $region) {
                        if (term_exists($region, 'region')) {
                            $term = term_exists($region, 'region');
                        } else {
                            $term = wp_insert_term($region, 'region');
                        }
                        $terms[] = $term['term_id'];
                    }
                    wp_set_post_terms($post_id, $terms, 'region');
                }

                if (!empty($genres)) {
                    $terms = array();
                    foreach (k_grabber_genre_names($genres) as $genre) {
                        if (term_exists($genre, 'genre')) {
                            $term = term_exists($genre, 'genre');
                        } else {
                            $term = wp_insert_term($genre, 'genre');
                        }
                        $terms[] = $term['term_id'];
                    }
                    wp_set_post_terms($post_id, $terms, 'genre');
                }

                wp_set_post_terms($post_id, k_grabber_company_names($devs), 'dev');

                echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Created:', 'rom') . ' <a href="' . get_edit_post_link($post_id) . '">' . get_the_title($post_id) . '</a><br>'));
            }
        } else {
            echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Grabber Romstation ERROR.' . '<br>', 'rom')));
        }
    } catch (\Throwable $th) {
        echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Grabber Romstation ERROR.' . '<br>', 'rom')));
    }
}


function grabber_launchbox_ac($url, $console, $type, $stt)
{
    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        echo json_encode(array('status' => false, 'message' => $stt . '. ' . __('Error fetching URL:', 'rom') . ' ' . $response->get_error_message()));
        exit();
    }

    $html = wp_remote_retrieve_body($response);

    //Suppress warnings and load the HTML document
    libxml_use_internal_errors(true);
    $dom = new DOMDocument;
    $dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    // title - với kiểm tra lỗi
    $title_element = $xpath->query('//h1')->item(0);
    if (!$title_element) {
        echo json_encode(array('status' => false, 'message' => $stt . '. ' . __('Could not find title', 'rom')));
        exit();
    }
    $title = trim($title_element->nodeValue);

    $devs = array();
    $pubs = array();
    $genres = array();
    $overview = '';

    $temp_post_id = 0;

    // Tìm xem có post trùng tên vs console không

            $title_variants = array(
                $title,                                 // Tiêu đề gốc
                str_replace("\u{2019}", "'", $title),   // Nháy đơn Unicode sang ASCII (cách 1)
                str_replace("'", "'", $title),          // Nháy đơn ASCII sang Unicode (cách 1)
                str_replace('&#x27;', "'", $title),     // HTML entity sang ASCII
                str_replace('&#39;', "'", $title),      // HTML entity khác sang ASCII
                html_entity_decode($title)              // Giải mã tất cả HTML entity
            );

    foreach ($title_variants as $variant) {
        $temp_post_id = rom_exists($variant, $console);
        if ($temp_post_id > 0) {
            break;
        }
    }

    if ($temp_post_id > 0) {
        echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Existed:', 'rom') . ' <a href="' . get_edit_post_link($temp_post_id) . '">' . get_the_title($temp_post_id) . '</a><br>'));
        exit();
    } else {
        try {
            // Lấy overview với kiểm tra lỗi
            $overview_card = $xpath->query('//div[@class="detailCard"][h5[text()="Overview"]]')->item(0);
            if ($overview_card) {
                $overview_p = $xpath->query('.//p', $overview_card)->item(0);
                if ($overview_p) {
                    $overview = $overview_p->nodeValue;
                }
            }

            // Lấy genres với kiểm tra lỗi
            $genres_card = $xpath->query('//dt[text()="Genre"]/following-sibling::dd')->item(0);
            if ($genres_card) {
                $genres_a_nodes = $xpath->query('.//a', $genres_card);
                $genres = [];
                foreach ($genres_a_nodes as $aNode) {
                    $genres[] = trim($aNode->nodeValue);
                }
            }

            // Lấy developers với kiểm tra lỗi
            $devs_card = $xpath->query('//dt[text()="Developers"]/following-sibling::dd')->item(0);
            if ($devs_card) {
                $devs_a_nodes = $xpath->query('.//a', $devs_card);
                foreach ($devs_a_nodes as $aNode) {
                    $devs[] = trim($aNode->nodeValue);
                }
            }

            // Lấy publishers với kiểm tra lỗi
            $pubs_card = $xpath->query('//dt[text()="Publishers"]/following-sibling::dd')->item(0);
            if ($pubs_card) {
                $pubs_a_nodes = $xpath->query('.//a', $pubs_card);
                $pubs = [];
                foreach ($pubs_a_nodes as $aNode) {
                    $pubs[] = trim($aNode->nodeValue);
                }
            }
        } catch (Exception $e) {
            error_log('Launchbox Grabber Error: ' . $e->getMessage());
            echo json_encode(array('status' => false, 'message' => $stt . '. ' . __('Grabber Launchbox ERROR: ', 'rom') . $e->getMessage() . '<br>'));
            exit;
        }

        // Tạo post
        $post_id = wp_insert_post(array(
            'post_type' => 'rom',
            'post_title' => $title,
            'post_content' => $overview,
            'post_status' => 'draft'
        ));

        if (is_wp_error($post_id)) {
            echo json_encode(array('status' => false, 'message' => $stt . '. ' . __('Error creating post: ', 'rom') . $post_id->get_error_message()));
            exit();
        }

        // Thiết lập console
        $terms = array();
        $terms[] = $console;
        wp_set_post_terms($post_id, $terms, 'console');

        // Thiết lập type nếu có
        if (!empty($type)) {
            wp_set_post_terms($post_id, [$type], 'type');
        }

        // Thiết lập genres nếu có
        if (!empty($genres)) {
            $terms = array();
            foreach (k_grabber_genre_names($genres) as $genre) {
                if (term_exists($genre, 'genre')) {
                    $term = term_exists($genre, 'genre');
                } else {
                    $term = wp_insert_term($genre, 'genre');
                }
                if (!is_wp_error($term)) {
                    $terms[] = $term['term_id'];
                }
            }
            if (!empty($terms)) {
                wp_set_post_terms($post_id, $terms, 'genre');
            }
        }

wp_set_post_terms($post_id, k_grabber_company_names($devs), 'dev');
wp_set_post_terms($post_id, k_grabber_company_names($pubs), 'pub');

        // Xử lý gallery
        $count = 0;
        $images_url = str_replace('/details/', '/images/', $url);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, $images_url);
        $str = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode == 200 && $str !== false) {
            $attach_id = [];
            include_once('simple_html_dom.php');
            $html = new simple_html_dom();
            $html->load($str);
            $image_container = $html->find("body > div.mainContainer > div.gameTabs > div.tab-content > div > div.imageContainerGrid.image-list");

            if (!empty($image_container)) {
                $html = new simple_html_dom();
                $html->load($image_container[0]);
                $image_list = $html->find("a");

                foreach ($image_list as $image) {
                    if ($count >= 5) {
                        break;
                    }

                    $html = new simple_html_dom();
                    $html->load($image);
                    $img_src = $image->href;
                    $img_title_element = $html->find(".imgCardText > p");

                    if (empty($img_title_element)) {
                        continue;
                    }

                    $img_title = $img_title_element[0]->innertext;
                    $image_title = $title . '_' . $count;

                    if (strpos($img_title, 'Front') !== false) {
                        $featured_attach_id = Generate_Featured_Image($img_src, $image_title, $post_id);
                        if ($featured_attach_id && !is_wp_error($featured_attach_id)) {
                            set_post_thumbnail($post_id, $featured_attach_id);
                        }
                        $count++;
                        continue;
                    }

                    if (strpos($img_title, 'Screenshot') === false) {
                        continue;
                    }

                    $new_attachment = Generate_Featured_Image($img_src, $image_title, $post_id);
                    if ($new_attachment && !is_wp_error($new_attachment)) {
                        $attach_id[] = $new_attachment;
                    }
                    $count++;
                }

                if (!empty($attach_id)) {
                    update_field('image', $attach_id, $post_id);
                }

                $html->clear();
                unset($html);
            }
        }

        echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Created:', 'rom') . ' <a href="' . get_edit_post_link($post_id) . '">' . get_the_title($post_id) . '</a><br>'));
    }
}


function grabber_gog_ac($url, $console, $type, $stt)
{
    try {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, $url);
        $str = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($httpCode == 200) {
            $html = new simple_html_dom();
            $html->load($str);

            $title = $html->find('.productcard-basics__title', 0)->plaintext;

            $temp_post_id = 0;

            // Tìm xem có post trùng tên vs console không

            $title_variants = array(
                $title,                                 // Tiêu đề gốc
                str_replace("\u{2019}", "'", $title),   // Nháy đơn Unicode sang ASCII (cách 1)
                str_replace("'", "'", $title),          // Nháy đơn ASCII sang Unicode (cách 1)
                str_replace('&#x27;', "'", $title),     // HTML entity sang ASCII
                str_replace('&#39;', "'", $title),      // HTML entity khác sang ASCII
                html_entity_decode($title)              // Giải mã tất cả HTML entity
            );

            foreach ($title_variants as $variant) {

                $temp_post_id = rom_exists($variant, $console);

                if ($temp_post_id > 0) {
                    break;
                }
            }
            if ($temp_post_id > 0) {
                echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Existed:', 'rom') . ' <a href="' . get_edit_post_link($temp_post_id) . '">' . get_the_title($temp_post_id) . '</a><br>'));
                exit();
            } else {

                // Get background image
                // $styleTag = $html->find('style', 1);
                // if ($styleTag) {
                // 	$cssContent = $styleTag->innertext;
                // 	if (preg_match('/\.productcard-image\s*{[^}]*background-image\s*:\s*url\((\'|\")?(.*?)(\'|\")?\)/i', $cssContent, $matches)) {
                // 		$image = $matches[2] . ' ';
                // 	}
                // }

                // Released date
                $releaseDateElement = $html->find('div.table__row.details__rating.details__row', 1);
                $releaseDateContent = $releaseDateElement ? $releaseDateElement->find('div.details__content.table__row-content', 0) : null;
                $release_date_text = $releaseDateContent ? trim($releaseDateContent->plaintext) : '';
                $release_date = substr($release_date_text, 8, 10);
                $date = DateTime::createFromFormat('Y-m-d', $release_date);
                $release_date = $date->format('F j, Y');

                // Genres
                $genresElement = $html->find('div.table__row.details__row', 0);
                $genresContent = $genresElement ? $genresElement->find('div.details__content.table__row-content', 0) : null;
                if ($values = $genresContent->find('a')) {
                    foreach ($values as $value) {
                        $genres[] = trim($value->plaintext);
                    }
                }

                // Publishers
                $publishersElement = $html->find('div.table__row.details__rating.details__row', 2);
                $publishersContent = $publishersElement ? $publishersElement->find('div.details__content.table__row-content', 0) : null;
                if ($values = $publishersContent->find('a')) {
                    foreach ($values as $value) {
                        $pubs[] = trim($value->plaintext);
                    }
                }

                // Description
                $content = $html->find('div.description', 0)->innertext;
                $content = preg_replace('/<a[^>]*>(.*?)<\/a>/is', '', $content);
                $content = preg_replace('/<img[^>]*>/is', '', $content);
                $content = str_replace(". More information", "", $content);

                $post_id = wp_insert_post(array(
                    'post_type' => 'rom',
                    'post_title' => $title,
                    'post_content' => $content
                ));

                // Image
                $pictures = $html->find('picture source');
                foreach ($pictures as $picture) {
                    $source = $picture->find('source', 0);
                    if ($source) {
                        $srcset = $source->srcset;
                        if (!empty($srcset) && filter_var($srcset, FILTER_VALIDATE_URL)) {
                            $images[] = $source->srcset;
                        }
                    }
                }

                $terms = array();
                $terms[] = $console;
                wp_set_post_terms($post_id, $terms, 'console');

                if (!empty($type)) {
                    wp_set_post_terms($post_id, [$type], 'type');
                }

                update_field('released', $release_date, $post_id);

                if (!empty($images)) {
                    $is_background = true;
                    $attachs_id = [];
                    $images_count = count($images);
                    $half_count = ceil($images_count / 2);

                    for ($i = 0; $i < $half_count; $i++) {
                        $image = $images[$i];
                        $attach_id = Generate_Image_Using_File_Get_Content($image, $title, $post_id);
                        if ($is_background) {
                            set_post_thumbnail($post_id, $attach_id);
                            $is_background = false;
                        } else {
                            $attachs_id[] = $attach_id;
                        }
                    }

                    if (!empty($attachs_id)) {
                        update_field('image', $attachs_id, $post_id);
                    }
                }


                if (!empty($genres)) {
                    $terms = array();
                    foreach (k_grabber_genre_names($genres) as $genre) {
                        if (term_exists($genre, 'genre')) {
                            $term = term_exists($genre, 'genre');
                        } else {
                            $term = wp_insert_term($genre, 'genre');
                        }
                        $terms[] = $term['term_id'];
                    }
                    wp_set_post_terms($post_id, $terms, 'genre');
                }

                wp_set_post_terms($post_id, k_grabber_company_names($devs), 'dev');
                wp_set_post_terms($post_id, k_grabber_company_names($pubs), 'pub');

                echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Created:', 'rom') . ' <a href="' . get_edit_post_link($post_id) . '">' . get_the_title($post_id) . '</a><br>'));
            }

            $html->clear();
            unset($html);
        } else {
            echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Grabber GOG ERROR.' . '<br>', 'rom')));
        }
    } catch (\Throwable $th) {
        echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Grabber GOG ERROR.' . '<br>', 'rom')));
    }
}

function grabber_backloggd_ac($url, $console, $type, $stt)
{
    try {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, $url);
        $str = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($httpCode == 200) {
            $html = new simple_html_dom();
            $html->load($str);

            $title = $html->find('h1', 0)->plaintext;

            $temp_post_id = 0;

            // Tìm xem có post trùng tên vs console không

            $title_variants = array(
                $title,                                 // Tiêu đề gốc
                str_replace("\u{2019}", "'", $title),   // Nháy đơn Unicode sang ASCII (cách 1)
                str_replace("'", "'", $title),          // Nháy đơn ASCII sang Unicode (cách 1)
                str_replace('&#x27;', "'", $title),     // HTML entity sang ASCII
                str_replace('&#39;', "'", $title),      // HTML entity khác sang ASCII
                html_entity_decode($title)              // Giải mã tất cả HTML entity
            );

            foreach ($title_variants as $variant) {

                $temp_post_id = rom_exists($variant, $console);

                if ($temp_post_id > 0) {
                    break;
                }
            }
            if ($temp_post_id > 0) {
                echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Existed:', 'rom') . ' <a href="' . get_edit_post_link($temp_post_id) . '">' . get_the_title($temp_post_id) . '</a><br>'));
                exit();
            } else {

                // Get background image
               		if ($temp = $html->find('.card-img.height', 0)) {
    $image = isset($temp->{'data-src'}) ? $temp->{'data-src'} : $temp->src; // Lấy data-src, nếu không có thì lấy src
}

                // Released date
                $releaseDateElement = $html->find('.sub-title a', 0);
                $release_date_text = $releaseDateElement->plaintext;
                $date = DateTime::createFromFormat('M d, Y', $release_date_text);
                $release_date = $date->format('F j, Y');

                // Genres
                $genresElement = $html->find('p.genre-tag a');
                $genres = [];

                foreach ($genresElement as $value) {
                    $genres[] = trim($value->plaintext);
                }

                // Publishers
                $pubs = [];
                $publishersElement = $html->find('div.col-auto.sub-title a');
                if ($publishersElement) {
                    foreach ($publishersElement as $value) {
                        $text = trim($value->plaintext);
                        if ($text && !in_array($text, $pubs)) {
                            $pubs[] = $text;
                        }
                    }
                }

                // Description
                $content = $html->find('#collapseSummary', 0)->plaintext;
                $content = preg_replace('/<a[^>]*>(.*?)<\/a>/is', '', $content);
                $content = preg_replace('/<img[^>]*>/is', '', $content);
                $content = str_replace(". More information", "", $content);

                $post_id = wp_insert_post(array(
                    'post_type' => 'rom',
                    'post_title' => $title,
                    'post_content' => $content
                ));

                if (!empty($image)) {
                    $attach_id = Generate_Featured_Image($image, $title, $post_id);
                    set_post_thumbnail($post_id, $attach_id);
                }

                $terms = array();
                $terms[] = $console;
                wp_set_post_terms($post_id, $terms, 'console');

                if (!empty($type)) {
                    wp_set_post_terms($post_id, [$type], 'type');
                }

                update_field('released', $release_date, $post_id);

                if (!empty($images)) {
                    $is_background = true;
                    $attachs_id = [];
                    $images_count = count($images);
                    $half_count = ceil($images_count / 2);

                    for ($i = 0; $i < $half_count; $i++) {
                        $image = $images[$i];
                        $attach_id = Generate_Image_Using_File_Get_Content($image, $title, $post_id);
                        if ($is_background) {
                            set_post_thumbnail($post_id, $attach_id);
                            $is_background = false;
                        } else {
                            $attachs_id[] = $attach_id;
                        }
                    }

                    if (!empty($attachs_id)) {
                        update_field('image', $attachs_id, $post_id);
                    }
                }


                if (!empty($genres)) {
                    $terms = array();
                    foreach (k_grabber_genre_names($genres) as $genre) {
                        if (term_exists($genre, 'genre')) {
                            $term = term_exists($genre, 'genre');
                        } else {
                            $term = wp_insert_term($genre, 'genre');
                        }
                        $terms[] = $term['term_id'];
                    }
                    wp_set_post_terms($post_id, $terms, 'genre');
                }


				wp_set_post_terms($post_id, k_grabber_company_names($devs), 'dev');
               wp_set_post_terms($post_id, k_grabber_company_names($pubs), 'pub');
				
				
                echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Created:', 'rom') . ' <a href="' . get_edit_post_link($post_id) . '">' . get_the_title($post_id) . '</a><br>'));
            }

            $html->clear();
            unset($html);
        } else {
            echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Grabber Backloggd ERROR.' . '<br>', 'rom')));
        }
    } catch (\Throwable $th) {
        echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Grabber Backloggd ERROR.' . '<br>', 'rom')));
    }
}

function grabber_romhackplaza_ac($url, $console, $type, $stt)
{
    try {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, $url);
        $str = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);


        if ($httpCode == 200) {
            $html = new simple_html_dom();
            $html->load($str);


			            $title = null;
            $rows = $html->find('div.row');
            foreach ($rows as $row) {
                $strong = $row->find('strong', 0);
                if ($strong && strpos(strtolower($strong->plaintext), 'game name') !== false) {
                    $aElement = $row->find('a', 0);
                    if ($aElement) {
                        $title = trim($aElement->plaintext);
                    }
                    break;
                }
            }

            if (!$title) {
                echo json_encode(['status' => false, 'message' => $stt . '. ' . __('Không tìm thấy tiêu đề.', 'rom')]);
                $html->clear();
                unset($html);
                return;
            }

			
			

            $title_variants = array(
                $title,                                 // Tiêu đề gốc
                str_replace("\u{2019}", "'", $title),   // Nháy đơn Unicode sang ASCII (cách 1)
                str_replace("'", "'", $title),          // Nháy đơn ASCII sang Unicode (cách 1)
                str_replace('&#x27;', "'", $title),     // HTML entity sang ASCII
                str_replace('&#39;', "'", $title),      // HTML entity khác sang ASCII
                html_entity_decode($title)              // Giải mã tất cả HTML entity
            );

            foreach ($title_variants as $variant) {

                $temp_post_id = rom_exists($variant, $console);

                if ($temp_post_id > 0) {
                    break;
                }
            }
            if ($temp_post_id > 0) {
                echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Existed:', 'rom') . ' <a href="' . get_edit_post_link($temp_post_id) . '">' . get_the_title($temp_post_id) . '</a><br>'));
                exit();
            } else {

                // Get background image
                if ($temp = $html->find('.attachment-post-thumbnail.size-post-thumbnail.wp-post-image', 0)) {
                    $image = $temp->src;
                }

                // Released date
                $rows = $html->find('.post-details-content .row');

                foreach ($rows as $row) {
                    $strongElement = $row->find('strong', 0);
                    if ($strongElement && strpos($strongElement->plaintext, 'Last updated') !== false) {
                        $spanElement = $row->find('span', 0);
                        if ($spanElement) {
                            $release_date = $spanElement->plaintext;
                        }
                        break;
                    }
                }

                // Genres
                $genres = [];
                $rows = $html->find('.post-details-content .row');
                foreach ($rows as $row) {
                    $strongElement = $row->find('strong', 0);
                    if ($strongElement && strpos($strongElement->plaintext, 'Genre') !== false) {
                        $aElements = $row->find('a');
                        foreach ($aElements as $a) {
                            $genres[] = trim($a->plaintext);
                        }
                        break;
                    }
                }

                // Devs
                $devs = [];
                $rows = $html->find('.post-details-content .row');

                foreach ($rows as $row) {
                    $strongElement = $row->find('strong', 0);

                    if ($strongElement && strpos($strongElement->plaintext, 'Released by') !== false) {
                        $aElements = $row->find('a');

                        foreach ($aElements as $a) {
                            $devs[] = trim($a->plaintext);
                        }
                        break;
                    }
                }

                $regions = [];
                $rows = $html->find('.post-details-content .row');

                foreach ($rows as $row) {
                    $strongElement = $row->find('strong', 0);

                    if ($strongElement && strpos($strongElement->plaintext, 'Language') !== false) {
                        $aElements = $row->find('a');

                        foreach ($aElements as $a) {
                            $regions[] = trim($a->plaintext);
                        }
                        break;
                    }
                }

                $content = $html->find('.entry-content.clearfix', 0)->find('p', 0)->plaintext;
                $content = preg_replace('/<a[^>]*>(.*?)<\/a>/is', '', $content);
                $content = preg_replace('/<img[^>]*>/is', '', $content);

                $post_id = wp_insert_post(array(
                    'post_type' => 'rom',
                    'post_title' => $title,
                    'post_content' => $content
                ));

                if (!empty($image)) {
                    $attach_id = Generate_Featured_Image($image, $title, $post_id);
                    set_post_thumbnail($post_id, $attach_id);
                }

                $terms = array();
                $terms[] = $console;
                wp_set_post_terms($post_id, $terms, 'console');

                if (!empty($type)) {
                    wp_set_post_terms($post_id, [$type], 'type');
                }

                update_field('released', $release_date, $post_id);

                if (!empty($images)) {
                    $is_background = true;
                    $attachs_id = [];
                    $images_count = count($images);
                    $half_count = ceil($images_count / 2);

                    for ($i = 0; $i < $half_count; $i++) {
                        $image = $images[$i];
                        $attach_id = Generate_Image_Using_File_Get_Content($image, $title, $post_id);
                        if ($is_background) {
                            set_post_thumbnail($post_id, $attach_id);
                            $is_background = false;
                        } else {
                            $attachs_id[] = $attach_id;
                        }
                    }

                    if (!empty($attachs_id)) {
                        update_field('image', $attachs_id, $post_id);
                    }
                }


                if (!empty($genres)) {
                    $terms = array();
                    foreach (k_grabber_genre_names($genres) as $genre) {
                        if (term_exists($genre, 'genre')) {
                            $term = term_exists($genre, 'genre');
                        } else {
                            $term = wp_insert_term($genre, 'genre');
                        }
                        $terms[] = $term['term_id'];
                    }
                    wp_set_post_terms($post_id, $terms, 'genre');
                }

                wp_set_post_terms($post_id, k_grabber_company_names($devs), 'dev');
                wp_set_post_terms($post_id, k_grabber_company_names($pubs), 'pub'); 

                if (!empty($regions)) {
                    $terms = array();
                    foreach (k_grabber_region_names($regions) as $region) {
                        if (term_exists($region, 'region')) {
                            $term = term_exists($region, 'region');
                        } else {
                            $term = wp_insert_term($region, 'region');
                        }
                        $terms[] = $term['term_id'];
                    }
                    wp_set_post_terms($post_id, $terms, 'region');
                }

                echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Created:', 'rom') . ' <a href="' . get_edit_post_link($post_id) . '">' . get_the_title($post_id) . '</a><br>'));
            }

            $html->clear();
            unset($html);
        } else {
            echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Grabber Romhackplaza ERROR.' . '<br>', 'rom')));
        }
    } catch (\Throwable $th) {
        echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Grabber Romhackplaza ERROR.' . '<br>', 'rom')));
    }
}

// addition functions
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
        throw new Exception("Grabber Igdb ERROR.", $http_status);
    }

    return $response;
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
        throw new Exception("Grabber Igdb ERROR.", $http_status);
    }

    return json_decode($response);
}


function save_file_from_url($url)
{
    $upload_dir = wp_upload_dir();
    $upload_path = $upload_dir['basedir'];
    $upload_url = $upload_dir['baseurl'];

    $retro_path = $upload_path . '/Retrostic';
    if (!file_exists($retro_path)) {
        wp_mkdir_p($retro_path);
    }

    $file_name = basename(parse_url($url, PHP_URL_PATH));
    $save_path = $retro_path . '/' . $file_name;

    $file_content = @file_get_contents($url);
    $result = @file_put_contents($save_path, $file_content);

    $file_size_kb = round(filesize($save_path) / 1024, 2) . 'K';
    $file_info = array(
        'name' => pathinfo($file_name, PATHINFO_FILENAME),
        'url' => $upload_url . '/Retrostic/' . $file_name,
        'file_size' => $file_size_kb,
        'file_type' => pathinfo($file_name, PATHINFO_EXTENSION)
    );

    return $file_info;
}

/**
 * @return int|WP_Error Attachment id; k_grabber_store_image() refuses anything that is not an image.
 */
function Generate_Image_Using_File_Get_Content($image_url, $image_title = '', $post_id = 0)
{
    $image_data = @file_get_contents($image_url);
    if ($image_data === false) {
        return new WP_Error('image_download_error', 'Could not download image.');
    }

    return k_grabber_store_image($image_data, (string) $image_title, (int) $post_id);
}

function k_grabber_gallery_multi()
{
    try {
        $params = $_REQUEST['acf'];
        $url = $params['url'];
        $postId = $params['postId'];
        $title = $params['title'];

        switch (true) {
            case strpos($url, 'mobygames.com') !== false:

                $curl = curl_init();
                curl_setopt($curl, CURLOPT_HEADER, 0);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($curl, CURLOPT_URL, $url);
                $str = curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                curl_close($curl);

                if ($httpCode == 200 && $str !== false) {
                    include_once('simple_html_dom.php');
                    $html = new simple_html_dom();
                    $html->load($str);
                    $images = $html->find('#gameShots img');
                    $attach_id = [];
                    $count = 0;
                    foreach ($images as $image) {
                        if ($count >= 5) {
                            break;
                        }
                        $attach_id[] = Generate_Featured_Image($image->src, $title, $postId);
                        $count++;
                    }
                    update_field('image', $attach_id, $postId);

                    $html->clear();
                    unset($html);
                }
                exit;
                break;
            case strpos($url, 'retrostic.com') !== false:
                break;
            case strpos($url, 'igdb.com') !== false:
                $grant_type = 'client_credentials';
                $client_id = 'w38dtppzl9ms96wfq40dm8zuwqtue3';
                $client_secret = '8bf0jeapi6o5gufogwmkux4mgn4dmb';

                $requestURL = "https://id.twitch.tv/oauth2/token?client_id=$client_id&client_secret=$client_secret&grant_type=$grant_type&redirect_uri=https://romsvault.com/";

                $options = array(
                    'http' => array(
                        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                        'method' => 'POST',
                        'content' => '',
                    ),
                );

                $context = stream_context_create($options);
                $loginResponse = json_decode(file_get_contents($requestURL, false, $context));
                $authorization = $loginResponse->token_type . ' ' . $loginResponse->access_token;
                $curl = curl_init();
                $gameData = getGameDataAPI($url, $authorization, $client_id);
                $dataArray = json_decode($gameData)[0]->screenshots;
                if (is_array($dataArray)) {
                    $attach_id = [];
                    $count = 0;

                    foreach ($dataArray as $data) {
                        if ($count >= 5) {
                            break;
                        }
                        $image = str_replace("t_thumb", "t_720p", 'https:' . $data->url);
                        $attach_id[] = Generate_Featured_Image($image, $title, $postId);
                        $count++;
                    }

                    update_field('image', $attach_id, $postId);
                }

                exit;
                break;
            case strpos($url, 'romstation.fr') !== false:
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_HEADER, 0);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($curl, CURLOPT_URL, $url);

                $str = curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                curl_close($curl);

                if ($httpCode == 200 && $str !== false) {
                    include_once('simple_html_dom.php');
                    $html = new simple_html_dom();
                    $html->load($str);
                    $as = $html->find('.cGalleryAlbums_recent a');

                    $attach_id = [];
                    $count = 0;

                    foreach ($as as $a) {
                        if ($count >= 5) {
                            break;
                        }
                        if (!empty($a->getAttribute('href'))) {
                            $attach_id[] = Generate_Featured_Image($a->getAttribute('href'), 'gallery', $postId);
                            $count++;
                        }
                    }

                    update_field('image', $attach_id, $postId);


                    $html->clear();
                    unset($html);
                }
                exit;
                break;

				
				
				
case strpos($url, 'gamesdb.launchbox-app.com') !== false:

    $images_url = str_replace('/details/', '/images/', $url);

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_URL, $images_url);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36');
    $str = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($httpCode == 200 && $str !== false) {

        include_once('simple_html_dom.php');

        $dom = new simple_html_dom();
        $dom->load($str);

        // selector ngắn + chắc
        $container = $dom->find("div.imageContainerGrid.image-list", 0);

        if ($container) {

            $attach_id = [];
            $count = 0;

            foreach ($container->find("a") as $a) {
                if ($count >= 5) break;

                $p = $a->find(".imgCardText > p", 0);
                if (!$p) continue;

                $caption = trim(strip_tags($p->innertext));
                if (stripos($caption, 'Screenshot') === false) continue;

                // HTML thật: href là direct image link images.launchbox-app.com
                $img_src = $a->getAttribute('href');
                if (!$img_src) continue;

                // normalize href (phòng trường hợp //...)
                if (strpos($img_src, '//') === 0) $img_src = 'https:' . $img_src;

                $image_title = $title . '_' . $count;

                $new_attachment = Generate_Featured_Image($img_src, $image_title, $postId);
                if ($new_attachment && !is_wp_error($new_attachment)) {
                    $attach_id[] = $new_attachment;
                    $count++;
                }
            }

            if (!empty($attach_id)) {
                update_field('image', $attach_id, $postId);
            }
        }

        $dom->clear();
        unset($dom);
    }

    exit;
    break;


				
				
				
				
            case strpos($url, 'gog.com') !== false:

                $grant_type = 'client_credentials';
                $client_id = 'w38dtppzl9ms96wfq40dm8zuwqtue3';
                $client_secret = '8bf0jeapi6o5gufogwmkux4mgn4dmb';

                $requestURL = "https://id.twitch.tv/oauth2/token?client_id=$client_id&client_secret=$client_secret&grant_type=$grant_type&redirect_uri=https://romsvault.com/";

                $options = array(
                    'http' => array(
                        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                        'method' => 'POST',
                        'content' => '',
                    ),
                );

                $context = stream_context_create($options);
                $loginResponse = json_decode(file_get_contents($requestURL, false, $context));
                $authorization = $loginResponse->token_type . ' ' . $loginResponse->access_token;
                $curl = curl_init();
                $gameData = getGameDataAPI($url, $authorization, $client_id);
                $dataArray = json_decode($gameData)[0]->screenshots;

                if (is_array($dataArray)) {
                    $attach_id = [];
                    $count = 0;
                    foreach ($dataArray as $data) {
                        if ($count >= 5) {
                            break;
                        }
                        $image = str_replace("t_thumb", "t_720p", 'https:' . $data->url);
                        $attach_id[] = Generate_Featured_Image($image, $title, $postId);
                        $count++;
                    }
                    update_field('image', $attach_id, $postId);
                }

                exit;

                break;
            default:
                _e('The URL must belong to one of the following domains: mobygames.com, retrostic.com, igdb.com, romstation.fr, gamesdb.launchbox-app.com, gog.com');
                break;
        }
        exit;
    } catch (\Throwable $th) {
        //throw $th;
        exit;
    }
}

add_action('wp_ajax_k_grabber_gallery_multi', 'k_grabber_gallery_multi');


function grabber_nintendolife_ac($url, $console, $type, $stt)
{
    try {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, $url);
        $str = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($httpCode == 200) {

            $html = new simple_html_dom();
            $html->load($str);


            $title = trim($html->find('#cover div.info h1 a', 0)->plaintext);
            $lastPos = strrpos($title, "Switch");

            if ($lastPos !== false) {
                $title = substr_replace($title, "", $lastPos, strlen("Switch"));
            }



            $temp_post_id = 0;

            // Tìm xem có post trùng tên vs console không

            $title_variants = array(
                $title,                                 // Tiêu đề gốc
                str_replace("\u{2019}", "'", $title),   // Nháy đơn Unicode sang ASCII (cách 1)
                str_replace("'", "'", $title),          // Nháy đơn ASCII sang Unicode (cách 1)
                str_replace('&#x27;', "'", $title),     // HTML entity sang ASCII
                str_replace('&#39;', "'", $title),      // HTML entity khác sang ASCII
                html_entity_decode($title)              // Giải mã tất cả HTML entity
            );

            foreach ($title_variants as $variant) {

                $temp_post_id = rom_exists($variant, $console);

                if ($temp_post_id > 0) {
                    break;
                }
            }
            if ($temp_post_id > 0) {
                echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Existed:', 'rom') . ' <a href="' . get_edit_post_link($temp_post_id) . '">' . get_the_title($temp_post_id) . '</a><br>'));
                exit();
            } else {



                $content = $html->find('#about div.body', 0)->plaintext;

                $post_id = wp_insert_post(array(
                    'post_type' => 'rom',
                    'post_title' => $title,
                    'post_content' => $content
                ));


                if ($temp = $html->find('#cover div.info p.image.accent-bg a', 0)) {
                    $image = $temp->href;
                }

                if (!empty($image)) {
                    $attach_id = Generate_Featured_Image($image, $title, $post_id);
                    set_post_thumbnail($post_id, $attach_id);
                }



                $dt_dd_pairs = $html->find('#cover div.info dl')[0]->children();
				$publisher = '';
				$dev = '';

				for ($i = 0; $i < count($dt_dd_pairs); $i++) {
					if (trim($dt_dd_pairs[$i]->plaintext) == 'Publisher') {
						$publisher = $dt_dd_pairs[$i + 1]->find('a', 0)->innertext;
					}
					if (trim($dt_dd_pairs[$i]->plaintext) == 'Developer') {
						$dev = $dt_dd_pairs[$i + 1]->find('a', 0)->innertext;
					}
				}


                // Genres
                $genres = [];
                // Locate the "Genre" section using its dt text
                $dtGenre = null;
                foreach ($html->find('dt') as $dt) {
                    if (trim($dt->plaintext) === 'Genre') {
                        $dtGenre = $dt;
                        break;
                    }
                }

                // If the "Genre" dt is found, find its sibling dd and extract genres
                if ($dtGenre) {
                    $dd = $dtGenre->next_sibling();
                    if ($dd) {
                        $genreList = $dd->find('ul.tag-list li a.tag');
                        foreach ($genreList as $genre) {
                            $genres[] = trim($genre->plaintext);
                        }
                    }
                }


                // Extract release dates without prices
                $dtRelease = null;
                foreach ($html->find('dt') as $dt) {
                    if (trim($dt->plaintext) === 'Release Date') {
                        $dtRelease = $dt;
                        break;
                    }
                }

                if ($dtRelease) {
                    $dd = $dtRelease->next_sibling();
                    if ($dd) {
                        $releaseList = $dd->find('ul li');
                        foreach ($releaseList as $item) {
                            // Extract text, split at the comma, and keep only the date
                            $fullText = trim($item->plaintext);
                            $date = explode(',', $fullText)[0]; // Keep only the part before the comma
                            $release[] = $date;
                        }
                    }
                }


                $images = $html->find('#screenshots div.body div.swiper-wrap div.swiper-container div.swiper-wrapper div.swiper-slide a.scanlines');
                $attach_id = [];
                $count = 0;
                foreach ($images as $image) {
                    if ($count >= 5) {
                        break;
                    }
                    $attach_id[] = Generate_Featured_Image($image->href, $title, $post_id);
                    $count++;
                }

                update_field('image', $attach_id, $post_id);


                // wp_set_post_terms($post_id, array($platform), 'game_platforms');
                // if (!empty($type)) {
                //     wp_set_post_terms($post_id, [$type], 'game_type');
                // }

                $terms = array();
                $terms[] = $console;
                wp_set_post_terms($post_id, $terms, 'console');
                if (!empty($type)) {
                    wp_set_post_terms($post_id, [$type], 'type');
                }


         wp_set_post_terms($post_id, k_grabber_company_names($devs), 'dev');
         wp_set_post_terms($post_id, k_grabber_company_names($pubs), 'pub');

                if (!empty($genres)) {
                    $terms = array();
                    foreach (k_grabber_genre_names($genres) as $genre) {
                        if (term_exists($genre, 'genre')) {
                            $term = term_exists($genre, 'genre');
                        } else {
                            $term = wp_insert_term($genre, 'genre');
                        }
                        $terms[] = $term['term_id'];
                    }
                    wp_set_post_terms($post_id, $terms, 'genre');
                }

                if (!empty($release)) {
                    $first_release = reset($release); // get the first value in the array
                    update_field('released', $first_release, $post_id);
                }


                echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Created:', 'rom') . ' <a href="' . get_edit_post_link($post_id) . '">' . get_the_title($post_id) . '</a><br>'));

                return;
            }
        } else {
            echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Grabber Nintendo Life ERROR.' . '<br>', 'rom')));
        }
    } catch (\Throwable $th) {
        echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Grabber Nintendo Life ERROR.' . '<br>', 'rom')));
    }
}

function grabber_pushsquare_ac($url, $console, $type, $stt)
{
    try {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, $url);
        $str = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($httpCode == 200) {

            $html = new simple_html_dom();
            $html->load($str);


            $title = trim($html->find('#cover div.info h1 a', 0)->plaintext);
            $lastPosPS4 = strrpos($title, "PS4");

            if ($lastPosPS4 !== false) {
                $title = substr_replace($title, "", $lastPosPS4, strlen("PS4"));
            }

            $lastPosPS5 = strrpos($title, "PS5");

            if ($lastPosPS5 !== false) {
                $title = substr_replace($title, "", $lastPosPS5, strlen("PS5"));
            }



            $temp_post_id = 0;

            // Tìm xem có post trùng tên vs console không

            $title_variants = array(
                $title,                                 // Tiêu đề gốc
                str_replace("\u{2019}", "'", $title),   // Nháy đơn Unicode sang ASCII (cách 1)
                str_replace("'", "'", $title),          // Nháy đơn ASCII sang Unicode (cách 1)
                str_replace('&#x27;', "'", $title),     // HTML entity sang ASCII
                str_replace('&#39;', "'", $title),      // HTML entity khác sang ASCII
                html_entity_decode($title)              // Giải mã tất cả HTML entity
            );

            foreach ($title_variants as $variant) {

                $temp_post_id = rom_exists($variant, $console);

                if ($temp_post_id > 0) {
                    break;
                }
            }
            if ($temp_post_id > 0) {
                echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Existed:', 'rom') . ' <a href="' . get_edit_post_link($temp_post_id) . '">' . get_the_title($temp_post_id) . '</a><br>'));
                exit();
            } else {



                $content = $html->find('#about div.body', 0)->plaintext;

                $post_id = wp_insert_post(array(
                    'post_type' => 'rom',
                    'post_title' => $title,
                    'post_content' => $content
                ));


                if ($temp = $html->find('#cover div.info p.image.accent-bg a', 0)) {
                    $image = $temp->href;
                }

                if (!empty($image)) {
                    $attach_id = Generate_Featured_Image($image, $title, $post_id);
                    set_post_thumbnail($post_id, $attach_id);
                }



                $dt_dd_pairs = $html->find('#cover div.info dl')[0]->children();
				$publisher = '';
				$dev = '';

				for ($i = 0; $i < count($dt_dd_pairs); $i++) {
					if (trim($dt_dd_pairs[$i]->plaintext) == 'Publisher') {
						$publisher = $dt_dd_pairs[$i + 1]->find('a', 0)->innertext;
					}
					if (trim($dt_dd_pairs[$i]->plaintext) == 'Developer') {
						$dev = $dt_dd_pairs[$i + 1]->find('a', 0)->innertext;
					}
				}


                // Genres
                $genres = [];
                // Locate the "Genre" section using its dt text
                $dtGenre = null;
                foreach ($html->find('dt') as $dt) {
                    if (trim($dt->plaintext) === 'Genre') {
                        $dtGenre = $dt;
                        break;
                    }
                }

                // If the "Genre" dt is found, find its sibling dd and extract genres
                if ($dtGenre) {
                    $dd = $dtGenre->next_sibling();
                    if ($dd) {
                        $genreList = $dd->find('ul.tag-list li a.tag');
                        foreach ($genreList as $genre) {
                            $genres[] = trim($genre->plaintext);
                        }
                    }
                }


                // Extract release dates without prices
                $dtRelease = null;
                foreach ($html->find('dt') as $dt) {
                    if (trim($dt->plaintext) === 'Release Date') {
                        $dtRelease = $dt;
                        break;
                    }
                }

                if ($dtRelease) {
                    $dd = $dtRelease->next_sibling();
                    if ($dd) {
                        $releaseList = $dd->find('ul li');
                        foreach ($releaseList as $item) {
                            // Extract text, split at the comma, and keep only the date
                            $fullText = trim($item->plaintext);
                            $date = explode(',', $fullText)[0]; // Keep only the part before the comma
                            $release[] = $date;
                        }
                    }
                }


                $images = $html->find('#screenshots div.body div.swiper-wrap div.swiper-container div.swiper-wrapper div.swiper-slide a.scanlines');
                $attach_id = [];
                $count = 0;
                foreach ($images as $image) {
                    if ($count >= 5) {
                        break;
                    }
                    $attach_id[] = Generate_Featured_Image($image->href, $title, $post_id);
                    $count++;
                }

                update_field('image', $attach_id, $post_id);


                // wp_set_post_terms($post_id, array($platform), 'game_platforms');
                // if (!empty($type)) {
                //     wp_set_post_terms($post_id, [$type], 'game_type');
                // }

                $terms = array();
                $terms[] = $console;
                wp_set_post_terms($post_id, $terms, 'console');
                if (!empty($type)) {
                    wp_set_post_terms($post_id, [$type], 'type');
                }


                wp_set_post_terms($post_id, k_grabber_company_names($devs), 'dev');
                wp_set_post_terms($post_id, k_grabber_company_names($pubs), 'pub');


                if (!empty($genres)) {
                    $terms = array();
                    foreach (k_grabber_genre_names($genres) as $genre) {
                        if (term_exists($genre, 'genre')) {
                            $term = term_exists($genre, 'genre');
                        } else {
                            $term = wp_insert_term($genre, 'genre');
                        }
                        $terms[] = $term['term_id'];
                    }
                    wp_set_post_terms($post_id, $terms, 'genre');
                }

                if (!empty($release)) {
                    $first_release = reset($release); // get the first value in the array
                    update_field('released', $first_release, $post_id);
                }


                echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Created:', 'rom') . ' <a href="' . get_edit_post_link($post_id) . '">' . get_the_title($post_id) . '</a><br>'));

                return;
            }
        } else {
            echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Grabber Pushsquare ERROR.' . '<br>', 'rom')));
        }
    } catch (\Throwable $th) {
        echo json_encode(array('status' => true, 'message' => $stt . '. ' . __('Grabber Pushsquare ERROR.' . '<br>', 'rom')));
    }
}
