<?php

use YoastSEO_Vendor\GuzzleHttp\Client;

function k_grabber_init()
{
    /**
     * Create grabber page
     */
    if (function_exists('acf_add_options_page')) {
        acf_add_options_page(array(
            'page_title' => 'Grabber',
            'menu_title' => 'Grabber',
            'menu_slug' => 'grabber',
            'icon_url' => 'dashicons-download',
            'capability' => 'manage_options',
            'id' => 'grabber',
            'post_id' => 'grabber',
        ));
    }

    /**
     * Add grabber field group
     */
    if (function_exists('acf_add_local_field_group')) {
        acf_add_local_field_group(array(
            'key' => 'grabber_settings',
            'title' => __('Settings', 'rom'),
            'fields' => array(
                array(
                    'label' => __('Console', 'rom'),
                    'name' => 'grabber_console',
                    'key' => 'grabber_console',
                    'type' => 'taxonomy',
                    'taxonomy' => 'console',
                    'field_type' => 'select',
                    'allow_null' => 1,
                ),
                array(
                    'label' => __('Url', 'rom'),
                    'name' => 'grabber_url',
                    'key' => 'grabber_url',
                    'type' => 'url',
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'options_page',
                        'operator' => '==',
                        'value' => 'grabber',
                    ),
                ),
            ),
        ));
    }
}

add_action('init', 'k_grabber_init');

/**
 * Grabber
 */
function k_grabber()
{

    header('Content-Type: application/json');

    // ini_set('display_errors', 1);
    // ini_set('display_startup_errors', 1);
    // error_reporting(E_ALL);

    $params = $_REQUEST['acf'];
    if (empty($params['grabber_console'])) {
        _e('Please select console.', 'rom');
        exit;
    }
    if (empty($params['grabber_url'])) {
        _e('Please input url.', 'rom');
        exit;
    }

    $console = $params['grabber_console'];
    $url = $params['grabber_url'];
    update_field('grabber_console', $console, 'grabber');
    update_field('grabber_url', $url, 'grabber');

    $page = $_REQUEST['page'];
    $game = $_REQUEST['game'];
    $stt = $_REQUEST['stt'];

    $response = wp_remote_get($url . '/page/' . $page);

    if (!is_wp_error($response)) {

        $html = wp_remote_retrieve_body($response);

        //Suppress warnings and load the HTML document
        libxml_use_internal_errors(true);

        $dom = new DOMDocument;

        $dom->loadHTML($html);

        $xpath = new DOMXPath($dom);

        $nodes = $xpath->query("//a[contains(@class, 'list-item')]");

        $i = 0;
        foreach ($nodes as $node) {
            if ($i == $game) {

                $app_link = $node->getAttribute('href');

                if (!str_contains($app_link, "https://gamesdb.launchbox-app.com")) {
                    $app_link = "https://gamesdb.launchbox-app.com" . $app_link;
                }

                //var_dump($app_link);

                //$app_link = 'https://gamesdb.launchbox-app.com/games/details/9048-a-bugs-life';

                $response = wp_remote_get($app_link);

                if (!is_wp_error($response)) {

                    $html = wp_remote_retrieve_body($response);

                    //Suppress warnings and load the HTML document
                    libxml_use_internal_errors(true);
                    $dom = new DOMDocument;
                    $dom->loadHTML($html);
                    $xpath = new DOMXPath($dom);

                    // title
                    $title = $xpath->query('//h1')->item(0)->nodeValue;

                    $devs = array();
                    $pubs = array();
                    $genres = array();
                    $overview = '';

                    $temp_post_id = 0;

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
    $game++;
    $stt++;
    echo json_encode(array('status' => true, 'page' => $page, 'game' => $game, 'stt' => $stt, 'message' => $stt . '. ' . __('Existed:', 'rom') . ' <a href="' . get_edit_post_link($temp_post_id) . '">' . get_the_title($temp_post_id) . '</a><br>'));
    exit();
} else {
    try {
        
        
                            // 				$overview_card = $xpath->query('//div[@class="detailCard"][h5[text()="Overview"]]')->item(0);
                            // 				$overview = $xpath->query('.//p', $overview_card)->item(0)->nodeValue;
                            // 				
                            $overview_card = $xpath->query('//div[@class="detailCard"][h5[text()="Overview"]]')->item(0);
                            $overview = $xpath->query('.//p', $overview_card)->item(0)->nodeValue;

                            // 				$genres_card = $xpath->query('//div[@class="detailCard"][h5[text()="Genres"]]')->item(0);
                            // 				$genres_a_nodes = $xpath->query('.//a', $genres_card);
                            // 				foreach ($genres_a_nodes as $aNode) {
                            // 					$genres[] = trim($aNode->nodeValue);
                            // 				}
                            // 				
                            $genres_card = $xpath->query('//dt[text()="Genre"]/following-sibling::dd')->item(0);
                            if ($genres_card) {
                                $genres_a_nodes = $xpath->query('.//a', $genres_card);
                                $genres = [];
                                foreach ($genres_a_nodes as $aNode) {
                                    $genres[] = trim($aNode->nodeValue);
                                }
                            }

                            // 				$devs_card = $xpath->query('//div[@class="detailCard"][h5[text()="Developers"]]')->item(0);
                            // 				$devs_a_nodes = $xpath->query('.//a', $devs_card);
                            // 				foreach ($devs_a_nodes as $aNode) {
                            // 					$devs[] = trim($aNode->nodeValue);
                            // 				}


                            $devs_card = $xpath->query('//dt[text()="Developers"]/following-sibling::dd')->item(0);
                            if ($devs_card) {
                                $devs_a_nodes = $xpath->query('.//a', $devs_card);
                                foreach ($devs_a_nodes as $aNode) {
                                    $devs[] = trim($aNode->nodeValue);
                                }
                            }

                            // 				$pubs_card = $xpath->query('//div[@class="detailCard"][h5[text()="Publishers"]]')->item(0);
                            // 				$pubs_a_nodes = $xpath->query('.//a', $pubs_card);
                            // 				foreach ($pubs_a_nodes as $aNode) {
                            // 					$pubs[] = trim($aNode->nodeValue);
                            // 				}
                            // 				
                            $pubs_card = $xpath->query('//dt[text()="Publishers"]/following-sibling::dd')->item(0);
                            if ($pubs_card) {
                                $pubs_a_nodes = $xpath->query('.//a', $pubs_card);
                                $pubs = [];
                                foreach ($pubs_a_nodes as $aNode) {
                                    $pubs[] = trim($aNode->nodeValue);
                                }
                            }
                        } catch (Exception $e) {
                            echo 'Grabber Launch Box ERROR.' . '<br>' . 'rom';
                        }

                        $post_id = wp_insert_post(array(
                            'post_type' => 'rom',
                            'post_title' => $title,
                            'post_content' => $overview
                        ));

                        $terms = array();
                        $terms[] = $console;
                        wp_set_post_terms($post_id, $terms, 'console');

                        if (!empty($type)) {
                            wp_set_post_terms($post_id, [$type], 'type');
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

wp_set_post_terms($post_id, $devs, 'dev');
wp_set_post_terms($post_id, $pubs, 'pub');
						
						//new curl to get image
                        $count = 0;
                        $images_url = str_replace('/details/', '/images/', $app_link);
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
                                $img_title = $html->find(".imgCardText > p")[0]->innertext;
                                $image_title = $title . '_' . $count;
                                if (str_contains($img_title, 'Front')) {
                                    $featured_attach_id = Generate_Featured_Image($img_src, $image_title, $post_id);
                                    set_post_thumbnail($post_id, $featured_attach_id);
                                    $count++;
                                    continue;
                                }
                                if (!str_contains($img_title, 'Screenshot')) {
                                    continue;
                                }
                                $attach_id[] = Generate_Featured_Image($img_src, $image_title, $post_id);
                                $count++;
                            }
                            update_field('image', $attach_id, $post_id);
                            $html->clear();
                            unset($html);
                        }

                    }
                    $game++;
                    $stt++;
                    echo json_encode(array('status' => true, 'page' => $page, 'game' => $game, 'stt' => $stt, 'message' => $stt . '. ' . __('Created:', 'rom') . ' <a href="' . get_edit_post_link($post_id) . '">' . get_the_title($post_id) . '</a><br>'));
                    exit;
                }
            }
            $i++;
        }
        $page++;
        $game = 0;
        echo json_encode(array('status' => true, 'page' => $page, 'game' => $game, 'stt' => $stt));
        exit;
    }
    echo json_encode(array('status' => false, 'message' => __('Grabber is completed.', 'rom')));
    exit;
}

add_action('wp_ajax_k_grabber', 'k_grabber');

/**
 * Insert image
 */
function Generate_Featured_Image($image_url, $image_title = '', $post_id = 0)
{
    $upload_dir = wp_upload_dir();
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $image_url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
    $image_data = curl_exec($curl);
    curl_close($curl);

    $filename = sanitize_file_name($image_title . '.jpg');
    $upload = wp_upload_bits($filename, null, $image_data);

    if (!$upload['error']) {
        $attachment = array(
            'post_mime_type' => $upload['type'],
            'post_title' => $image_title,
            'post_content' => $image_title,
            'post_status' => 'inherit',
        );
        $attach_id = wp_insert_attachment($attachment, $upload['file'], $post_id);

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);
        update_post_meta($attach_id, '_wp_attachment_image_alt', get_the_title($attach_id));

        return $attach_id;
    } else {
        return false;
    }
}

function Curl($url, $ifpost = 0, $datafields = '', $cookiefile = '', $v = false)
{
    $ip_long = array(
        array('607649792', '608174079'), // 36.56.0.0-36.63.255.255
        array('1038614528', '1039007743'), // 61.232.0.0-61.237.255.255
        array('1783627776', '1784676351'), // 106.80.0.0-106.95.255.255
        array('2035023872', '2035154943'), // 121.76.0.0-121.77.255.255
        array('2078801920', '2079064063'), // 123.232.0.0-123.235.255.255
        array('-1950089216', '-1948778497'), // 139.196.0.0-139.215.255.255
        array('-1425539072', '-1425014785'), // 171.8.0.0-171.15.255.255
        array('-1236271104', '-1235419137'), // 182.80.0.0-182.92.255.255
        array('-770113536', '-768606209'), // 210.25.0.0-210.47.255.255
        array('-569376768', '-564133889'), // 222.16.0.0-222.95.255.255
    );
    $rand_key = mt_rand(0, 9);
    $ip = long2ip(mt_rand($ip_long[$rand_key][0], $ip_long[$rand_key][1]));

    $header = array(
        "Connection: keep-alive",
        "accept: text/html, application/xhtml+xml, */*",
        "Cache-Control: no-cache",
        "accept-language: zh-hans-cn,zh-hans;q=0.8,en-us;q=0.5,en;q=0.3",
        "user-agent: Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.2; WOW64; Trident/6.0)",
        "Client-ip: $ip",
        "x-forwarded-for: $ip"
    );

    $max_retries = 3;
    $retry_delay = 2000000; // 2 giây (microseconds)

    for ($i = 0; $i < $max_retries; $i++) {
        usleep(2000000); // Đợi 2 giây trước mỗi yêu cầu
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, $v);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        $ifpost && curl_setopt($ch, CURLOPT_POST, $ifpost);
        $ifpost && curl_setopt($ch, CURLOPT_POSTFIELDS, $datafields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_REFERER, 'https://' . getRandomString() . '.com');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $cookiefile && curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiefile);
        $cookiefile && curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiefile);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $ok = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200 && $ok !== false) {
            return $ok; // Thành công
        } elseif (in_array($http_code, [429, 503])) {
            error_log("Curl retry $i for $url: HTTP $http_code");
            usleep($retry_delay * ($i + 1)); // Tăng delay mỗi lần thử lại
            continue;
        } else {
            error_log("Curl failed for $url: HTTP $http_code");
            break;
        }
    }

    return false; // Trả về false nếu thất bại sau các lần thử
}

function getRandomString($n = 3)
{
    $characters = 'abcdefghijklmnopqrstuvwxyz';
    $randomString = '';

    for ($i = 0; $i < $n; $i++) {
        $index = rand(0, strlen($characters) - 1);
        $randomString .= $characters[$index];
    }

    return $randomString;
}