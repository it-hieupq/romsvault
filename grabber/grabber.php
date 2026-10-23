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
 * Clean scraped developer/publisher names before they become dev/pub terms.
 *
 * Trailing legal suffixes are dropped ("Konami Co., Ltd." -> "Konami"), so a grab
 * lands on the brand term instead of creating a variant of it. No comma may stay
 * in a name: the classic tag box joins a rom's terms with commas and splits them
 * again on save, which turned "Atari, Inc." into the terms "Atari" and "Inc.".
 * The cleaned name is then mapped to its brand by k_grabber_brand_aliases().
 *
 * @param mixed $names Scraped names; a missing list counts as none.
 * @return string[]
 */
function k_grabber_company_names($names) {
	$clean = array();
	foreach ((array) $names as $name) {
		$name = trim((string) $name);
		do {
			$prev = $name;
			$name = trim(preg_replace('/(^|[\s,]+)(inc|incorporated|ltd|limited|llc|corp|corporation|co|gmbh|s\.?a|plc|k\.?k)\.?$/i', '', $name));
			// The suffix is part of the name when only a bare word or a dangling "&" would be
			// left: "Sun Corporation", "New Corporation", "Mitsui & Co.". Keep the last good form.
			if ($name !== '' && (substr($name, -1) === '&' || in_array(strtolower($name), array('new', 'sun', 'ray', 'net', 'log', 'hi'), true))) {
				$name = $prev;
			}
		} while ($name !== $prev);
		$name = trim(preg_replace('/\s*,\s*/', ' ', $name));
		if ($name === '') {
			continue;
		}
		foreach (k_grabber_brand_aliases() as $pattern => $brand) {
			if (preg_match($pattern, $name)) {
				$name = $brand;
				break;
			}
		}
		$clean[] = $name;
	}
	return array_values(array_unique($clean));
}

/**
 * Brand alias table for dev/pub names: pattern => brand term name.
 *
 * Dev and pub terms were folded into one term per brand (studios, regional arms,
 * labels and pre-merger names included), so a new grab has to land on that same
 * term: "Ubisoft Montreal" -> "Ubisoft", "SCEA" -> "Sony", "Squaresoft" -> "Square
 * Enix". Matching is case-insensitive, so "SEGA" also finds the dev term "Sega".
 *
 * The first matching pattern wins. Names that share a prefix with a different
 * company are excluded: Spike Chunsoft, Human Head, Climax Entertainment. Zoo is
 * left out because ZOO Corporation (Japan) is not Zoo Digital (UK).
 *
 * @return array<string, string>
 */
function k_grabber_brand_aliases() {
	static $aliases = null;
	if ($aliases !== null) {
		return $aliases;
	}
	$aliases = array(
		'/^nintendo 64 wizard$/i'                              => 'Nintendo 64 Wizard',
		'/^(square electronic arts|electronic arts square|square ea)\b/i' => 'Square Enix',
		'/^(bandai|namco|namcot)\b/i'                          => 'Bandai Namco',
		'/^(square|squaresoft|enix)\b/i'                       => 'Square Enix',
		'/^(koei|tecmo)\b/i'                                   => 'Koei Tecmo',
		'/^(sega|sammy|segasoft)\b/i'                          => 'SEGA',
		'/^(sony|sce|scea|scee|scei|scej|sie|siea|siee)\b/i'   => 'Sony',
		'/^(ea|electronic arts)\b/i'                           => 'Electronic Arts',
		'/^ubi ?soft\b/i'                                      => 'Ubisoft',
		'/^(warner|wb)\b/i'                                    => 'Warner Bros.',
		'/^(takara|tomy)\b/i'                                  => 'Takara Tomy',
		'/^kce\b/i'                                            => 'Konami',
		'/^d3(\s?publisher)?\b/i'                              => 'D3Publisher',
		'/^focus entertainment\b/i'                            => 'Focus Home Interactive',
		'/^buena vista\b/i'                                    => 'Buena Vista Games',
		'/^circle ent(ertainment|\.)?$/i'                      => 'CIRCLE Entertainment',
		'/^spike\b(?! chunsoft)/i'                             => 'Spike',
		'/^human\b(?! head)/i'                                 => 'Human',
		'/^climax\b(?! entertainment)/i'                       => 'Climax',
	);
	$prefix_brands = array(
		'Activision', 'Aksys', 'Arc System Works', 'Asmik', 'Atari', 'Atlus', 'Banpresto', 'Bethesda',
		'Capcom', 'Codemasters', 'Conspiracy', 'Crave', 'Cryo Interactive', 'Culture Brain', 'Data East',
		'Digital Eclipse', 'Disney', 'Eidos', 'Eurocom', 'Global A', 'GungHo', 'Hasbro', 'Hudson',
		'Idea Factory', 'Imagineer', 'Infogrames', 'Interplay', 'Irem', 'Jaleco', 'JVC', 'Kadokawa', 'Kemco',
		'Konami', 'Majesco', 'Marvelous', 'Microsoft', 'Midas', 'Midway', 'Mindscape', 'Natsume',
		'Nintendo', 'Nippon Ichi', 'NIS', 'Pandemic', 'Psygnosis', 'Rare', 'Red Storm', 'Rockstar',
		'Sierra', 'SNK', 'Southpeak', 'Sting', 'Success', 'Taito', 'Team17', 'Telltale', 'THQ',
		'Titus', 'Toho', 'Tokuma Shoten', 'Victor', 'Virgin', 'Visual Concepts', 'Vivendi', 'Xseed',
	);
	foreach ($prefix_brands as $brand) {
		$aliases['/^' . preg_quote($brand, '/') . '\b/i'] = $brand;
	}
	return $aliases;
}

/**
 * Map scraped genre names onto the genre terms the site keeps.
 *
 * Sources name the same genre differently ("Sport" / "Sports", "RPG" /
 * "Role-Playing", "Platform" / "Platformer"), and those were folded into one term
 * each. Names are entity-decoded first ("Shoot&#039;em up"); one name may map to
 * several genres ("Adventure/ Visual Novel"). Unknown names pass through as they are.
 *
 * @param mixed $genres Scraped genre names; a missing list counts as none.
 * @return string[]
 */
function k_grabber_genre_names($genres) {
	static $aliases = array(
		'/^(role-?playing( \(rpg\))?|rpg)$/i'                           => 'Role-Playing (RPG)',
		'/^(sports?|soccer)$/i'                                         => 'Sports',
		'/^platform(er)?$/i'                                            => 'Platformer',
		'/^simulat(or|ion)$/i'                                          => 'Simulation',
		"/^(hack and slash\\/ ?)?beat ?'?em ?up$/i"                     => "Beat 'em up",
		"/^shoot ?'?em ?up$/i"                                          => "Shoot 'em up",
		'/^(education(al)?|edutainment)$/i'                             => 'Education',
		'/^party( game)?$/i'                                            => 'Party',
		'/^quiz(\/ ?trivia)?$/i'                                        => 'Quiz',
		'/^(racing( \/ driving)?|rally|off-road)$/i'                    => 'Racing',
		'/^strategy( \/ tactics)?$/i'                                   => 'Strategy',
		'/^(casino|gambling|miscellaneous\/ ?gambling)$/i'              => 'Casino',
		'/^manage(ment|rial)$/i'                                        => 'Management',
		'/^point[- ]?(and|&)[- ]?click$/i'                              => 'Point-and-click',
		'/^(life simulation|virtual life)$/i'                           => 'Life Simulation',
		'/^card( game)?$/i'                                             => 'Card Game',
		'/^(board game|chess)$/i'                                       => 'Board Game',
		'/^(puzzle|reflexion)$/i'                                       => 'Puzzle',
		'/^(music|rhythm)$/i'                                           => 'Music',
		'/^(compilation|bundle)$/i'                                     => 'Compilation',
		'/^(other|misc)$/i'                                             => 'Other',
		'/^adventure\/ ?visual novel$/i'                                => array('Adventure', 'Visual Novel'),
	);
	$clean = array();
	foreach ((array) $genres as $genre) {
		$genre = trim(html_entity_decode((string) $genre, ENT_QUOTES, 'UTF-8'));
		if ($genre === '') {
			continue;
		}
		foreach ($aliases as $pattern => $to) {
			if (preg_match($pattern, $genre)) {
				$genre = $to;
				break;
			}
		}
		foreach ((array) $genre as $name) {
			$clean[] = $name;
		}
	}
	return array_values(array_unique($clean));
}

/**
 * Store a downloaded image as an attachment, compressed to WebP.
 *
 * Shared by every grabber download path. The bytes are checked before anything
 * is written: a source that answers with an error page used to be saved as a
 * .jpg holding HTML — three such files were found in the library, each the
 * "Cannot GET /igdb/image/upload/t_1080p/.jpg" page IGDB serves for an empty
 * image id. Anything that is not an image is now refused.
 *
 * Images are fitted into the compressor's 1280x1000 box at q80. An animated GIF
 * is kept as it is, since encoding it would keep only its first frame.
 *
 * @param string $data    Downloaded bytes.
 * @param string $title   Becomes the file name slug and the attachment title.
 * @param int    $post_id Post the attachment belongs to.
 * @param string $content Attachment post_content.
 * @return int|WP_Error Attachment id.
 */
function k_grabber_store_image(string $data, string $title, int $post_id, string $content = '')
{
    if ('' === $data) {
        return new WP_Error('image_empty', 'Downloaded image is empty.');
    }

    $mime = k_detect_image_mime($data);
    if (!in_array($mime, K_WEBP_INPUT_MIMES, true)) {
        return new WP_Error('image_not_an_image', sprintf('Download is %s, not an image.', $mime));
    }

    try {
        $out  = k_compress_image_to_webp($data);
        $blob = $out['blob'];
        $ext  = 'webp';
        $type = 'image/webp';
    } catch (RuntimeException $e) {
        if ('image/gif' !== $mime) {
            return new WP_Error('image_encode_error', $e->getMessage());
        }
        $blob = $data;
        $ext  = 'gif';
        $type = 'image/gif';
    }

    $upload = wp_upload_bits(k_webp_basename($title) . '.' . $ext, null, $blob);
    if (!empty($upload['error'])) {
        return new WP_Error('upload_error', $upload['error']);
    }

    $attach_id = wp_insert_attachment(array(
        'post_mime_type' => $type,
        'post_title'     => $title,
        'post_content'   => $content,
        'post_status'    => 'inherit',
    ), $upload['file'], $post_id);

    if (is_wp_error($attach_id) || !$attach_id) {
        return is_wp_error($attach_id) ? $attach_id : new WP_Error('attachment_error', 'Could not create the attachment.');
    }

    require_once(ABSPATH . 'wp-admin/includes/image.php');
    wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $upload['file']));
    update_post_meta($attach_id, '_wp_attachment_image_alt', get_the_title($attach_id));

    return (int) $attach_id;
}

/**
 * Insert image
 *
 * @return int|false Attachment id, false when the download or the image fails.
 */
function Generate_Featured_Image($image_url, $image_title = '', $post_id = 0)
{
    if (empty($image_url)) {
        return false;
    }

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $image_url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
    $image_data = curl_exec($curl);
    $status     = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    // An error status comes with a body too; storing it is how HTML ended up
    // saved as .jpg, so anything but 200 counts as a failed download.
    if (200 !== $status || !is_string($image_data)) {
        return false;
    }

    $attach_id = k_grabber_store_image($image_data, (string) $image_title, (int) $post_id, (string) $image_title);

    return is_wp_error($attach_id) ? false : $attach_id;
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