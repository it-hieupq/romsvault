<?php
function k_grabber_retrostic_init()
{
	/**
	 * Create grabber_retrostic page
	 */
	if (function_exists('acf_add_options_page')) {
		acf_add_options_page(array(
			'page_title'    => 'Grabber retrostic',
			'menu_title'    => 'Grabber retrostic',
			'menu_slug'     => 'grabber_retrostic',
			'icon_url'      => 'dashicons-download',
			'capability'    => 'manage_options',
			'id'            => 'grabber_retrostic',
			'post_id'       => 'grabber_retrostic',
			'parent'        => 'edit.php?post_type=rom',
		));
	}

	/**
	 * Add grabber_retrostic field group
	 */
	if (function_exists('acf_add_local_field_group')) {
		acf_add_local_field_group(array(
			'key'   => 'grabber_retrostic_settings',
			'title' => __('Settings', 'romsfun'),
			'fields' => array(
				array(
					'label'      => __('Console', 'romsfun'),
					'name'       => 'grabber_retrostic_console',
					'key'        => 'grabber_retrostic_console',
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
					'label'       => __('URLs', 'romsfun'),
					'name'        => 'grabber_retrostic_urls',
					'key'         => 'grabber_retrostic_urls',
					'type'        => 'textarea',
					'placeholder' => 'Enter URLs, one per line',
					'rows'        => 10,
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => 'grabber_retrostic',
					),
				),
			),
		));
	}
}

k_grabber_retrostic_init('init', 'k_grabber_retrostic_init');

function k_grabber_retrostic()
{
	$params = $_REQUEST['acf'];

	if (empty($params['grabber_retrostic_console'])) {
		_e('Please select console.', 'romsfun');
		exit;
	}

	if (empty($params['grabber_type'])) {
		_e('Please select type.', 'romsfun');
		exit;
	}

	if (empty($params['grabber_retrostic_urls'])) {
		_e('Please input url.', 'romsfun');
		exit;
	}

	$index = 1;
	$message = "";
	$type = $params['grabber_type'];
	$console = $params['grabber_retrostic_console'];
	require_once get_template_directory() . '/grabber/simple_html_dom.php';

	$urls_array = preg_split('/\r\n|\r|\n/', trim($params['grabber_retrostic_urls']));
	if (empty($urls_array)) {
		_e('Please input valid URLs.', 'romsfun');
		exit;
	}

	foreach ($urls_array as $url) {
		_e('Url ' . $index . ': ', 'romsfun');
		$index++;

		switch (true) {
			case strpos($url, 'mobygames.com') !== false:
				grabber_mobygames_ac($url, $console, $type);
				break;
			case strpos($url, 'retrostic.com') !== false:
				grabber_retrostic_ac($url, $console, $type);
				break;
			case strpos($url, 'igdb.com') !== false:
				grabber_igdb_ac($url, $console, $type);
				break;
			case strpos($url, 'romstation.fr') !== false:
				grabber_romstation_ac($url, $console, $type);
				break;
			default:
				_e('The URL must belong to one of the following domains: <strong>mobygames.com, retrostic.com, igdb.com, romstation.fr</strong>' . '<br>', 'romsfun');
				break;
		}
	}
	exit;
}

add_action('wp_ajax_k_grabber_retrostic', 'k_grabber_retrostic');

function grabber_mobygames_ac($url, $console, $type)
{
	try {
		update_field('grabber_mobygames_console', $console, 'grabber_mobygames');
		update_field('grabber_mobygames_url', $url, 'grabber_mobygames');

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

			$title_1 = str_replace('’', '\'', $title);
			$title_2 = str_replace('\'', '’', $title);
			$title_3 = str_replace('&#x27;', '\'', $title);

			$post_id = rom_exists($title, $console);
			$post_id_1 = rom_exists($title_1, $console);
			$post_id_2 = rom_exists($title_2, $console);
			$post_id_3 = rom_exists($title_3, $console);

			if ($post_id > 0) {
				_e('Post exists: ' . '<a href="' . get_edit_post_link($post_id) . '" target="_blank">' . get_edit_post_link($post_id) . '</a>' . '<br>', 'romsfun');
				return;
			} elseif ($post_id_1 > 0) {
				_e('Post exists: ' . '<a href="' . get_edit_post_link($post_id_1) . '" target="_blank">' . get_edit_post_link($post_id_1) . '</a>' . '<br>', 'romsfun');
				return;
			} elseif ($post_id_2 > 0) {
				_e('Post exists: ' . '<a href="' . get_edit_post_link($post_id_2) . '" target="_blank">' . get_edit_post_link($post_id_2) . '</a>' . '<br>', 'romsfun');
				return;
			} elseif ($post_id_3 > 0) {
				_e('Post exists: ' . '<a href="' . get_edit_post_link($post_id_3) . '" target="_blank">' . get_edit_post_link($post_id_3) . '</a>' . '<br>', 'romsfun');
				return;
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
				wp_set_post_terms($post_id, array($type), 'type');

				update_field('released', $release_date, $post_id);

				if (! empty($image)) {
					$attach_id = Generate_Featured_Image($image, $title, $post_id);
					set_post_thumbnail($post_id, $attach_id);
				}

				if (! empty($genres)) {
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

				if (! empty($devs)) {
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

				if (! empty($pubs)) {
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

				_e('Post created:' . '<a href="' . get_edit_post_link($post_id) . '" target="_blank">' . get_edit_post_link($post_id) . '</a>' . '<br>', 'romsfun');
				return;
			}

			$html->clear();
			unset($html);
		} else {
			_e('Grabber Mobygames ERROR.' . '<br>', 'romsfun');
		}
	} catch (\Throwable $th) {
		_e('Grabber Mobygames ERROR.' . '<br>', 'romsfun');
	}
}

function grabber_retrostic_ac($url, $console, $type)
{
	try {
		update_field('grabber_retrostic_console', $console, 'grabber_retrostic');
		update_field('grabber_retrostic_url', $url, 'grabber_retrostic');

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_HEADER, 0);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_URL, $url);
		$str = curl_exec($curl);
		$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if ($httpCode == 200) {

			$html = new simple_html_dom();
			$html->load($str);
			$title = trim($html->find('span', 4)->plaintext);

			$title_1 = str_replace('’', '\'', $title);
			$title_2 = str_replace('\'', '’', $title);
			$title_3 = str_replace('&#x27;', '\'', $title);

			$post_id = rom_exists($title, $console);
			$post_id_1 = rom_exists($title_1, $console);
			$post_id_2 = rom_exists($title_2, $console);
			$post_id_3 = rom_exists($title_3, $console);

			// Check exist post
			if ($post_id > 0) {
				_e('Post exists: ' . '<a href="' . get_edit_post_link($post_id) . '" target="_blank">' . get_edit_post_link($post_id) . '</a>' . '<br>', 'romsfun');
				return;
			} elseif ($post_id_1 > 0) {
				_e('Post exists: ' . '<a href="' . get_edit_post_link($post_id_1) . '" target="_blank">' . get_edit_post_link($post_id_1) . '</a>' . '<br>', 'romsfun');
				return;
			} elseif ($post_id_2 > 0) {
				_e('Post exists: ' . '<a href="' . get_edit_post_link($post_id_2) . '" target="_blank">' . get_edit_post_link($post_id_2) . '</a>' . '<br>', 'romsfun');
				return;
			} elseif ($post_id_3 > 0) {
				_e('Post exists: ' . '<a href="' . get_edit_post_link($post_id_3) . '" target="_blank">' . get_edit_post_link($post_id_3) . '</a>' . '<br>', 'romsfun');
				return;
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
						if ($td[0]->plaintext == 'Genre:') {
							$genreString = trim($td[1]->plaintext);

							$genres = explode('>', $genreString);
							foreach ($genres as $key => $genre) {
								$genres[$key] = trim($genre);
							}
						}
					}
				}

				// download file
				$romHackData = $html->find('#tableRomhackData tbody tr');
				foreach ($romHackData as $romHack) {
					$t = $romHack->find('td');
					if (trim($t[0]->plaintext) == 'File Name:') {
						$file_url = "https://downloads.retrostic.com/romhacks/" . $t[1]->plaintext;
						break;
					}
				}

				$content = $html->find('.card p', 0);
				$post_id = wp_insert_post(array(
					'post_type'    => 'rom',
					'post_title'   => $title,
					'post_content' => $content
				));


				$terms = array();
				$terms[] = $console;
				wp_set_post_terms($post_id, $terms, 'console');
				wp_set_post_terms($post_id, array($type), 'type');

				update_field('released', $release_date, $post_id);

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

				if (!empty($file_url)) {
					$download_data = save_file_from_url($file_url);
					add_row('download_list', $download_data, $post_id);
				}

				_e('Post created: ' . '<a href="' . get_edit_post_link($post_id) . '" target="_blank">' . get_edit_post_link($post_id) . '</a><br>', 'romsfun');
				return;
			}

			$html->clear();
			unset($html);
		}
		_e('Grabber Retrostic ERROR.<br>', "romsfun");
	} catch (\Throwable $th) {
		_e('Grabber Retrostic ERROR. ' . $th . '<br>', 'romsfun');
	}
}

function grabber_igdb_ac($url, $console, $type)
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
			_e('Post exists: ' . '<a href="' . get_edit_post_link($post_id) . '" target="_blank">' . get_edit_post_link($post_id) . '</a>' . '<br>', 'romsfun');
			return;
		} elseif ($post_id_1 > 0) {
			_e('Post exists: ' . '<a href="' . get_edit_post_link($post_id_1) . '" target="_blank">' . get_edit_post_link($post_id_1) . '</a>' . '<br>', 'romsfun');
			return;
		} elseif ($post_id_2 > 0) {
			_e('Post exists: ' . '<a href="' . get_edit_post_link($post_id_2) . '" target="_blank">' . get_edit_post_link($post_id_2) . '</a>' . '<br>', 'romsfun');
			return;
		} elseif ($post_id_3 > 0) {
			_e('Post exists: ' . '<a href="' . get_edit_post_link($post_id_3) . '" target="_blank">' . get_edit_post_link($post_id_3) . '</a>' . '<br>', 'romsfun');
			return;
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

		echo __('Post created:', 'romsfun') . ' ' . '<a href="' . get_edit_post_link($post_id) . '" target="_blank">' . get_edit_post_link($post_id) . '</a>' . '<br>';
	} catch (\Throwable $th) {
		_e('Grabber Igdb ERROR.' . '<br>', 'romsfun');
	}
}

function grabber_romstation_ac($url, $console, $type)
{
	try {
		update_field('grabber_rom_url', $url, 'grabber_rom');
		update_field('grabber_console', $console, 'grabber_rom');
		update_field('grabber_type', $type, 'grabber_rom');

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

			$title_1 = str_replace('’', '\'', $title);
			$title_2 = str_replace('\'', '’', $title);
			$title_3 = str_replace('&#x27;', '\'', $title);

			$post_id = rom_exists($title, $console);
			$post_id_1 = rom_exists($title_1, $console);
			$post_id_2 = rom_exists($title_2, $console);
			$post_id_3 = rom_exists($title_3, $console);

			if ($post_id > 0) {
				_e('Post exists: ' . '<a href="' . get_edit_post_link($post_id) . '" target="_blank">' . get_edit_post_link($post_id) . '</a>' . '<br>', 'romsfun');
				return;
			} elseif ($post_id_1 > 0) {
				_e('Post exists: ' . '<a href="' . get_edit_post_link($post_id_1) . '" target="_blank">' . get_edit_post_link($post_id_1) . '</a>' . '<br>', 'romsfun');
				return;
			} elseif ($post_id_2 > 0) {
				_e('Post exists: ' . '<a href="' . get_edit_post_link($post_id_2) . '" target="_blank">' . get_edit_post_link($post_id_2) . '</a>' . '<br>', 'romsfun');
				return;
			} elseif ($post_id_3 > 0) {
				_e('Post exists: ' . '<a href="' . get_edit_post_link($post_id_3) . '" target="_blank">' . get_edit_post_link($post_id_3) . '</a>' . '<br>', 'romsfun');
				return;
			} else {

				$post_id = wp_insert_post(array(
					'post_type'    => 'rom',
					'post_title'   => $title,
					'post_content' => $content
				));

				if (! empty($image)) {
					$attach_id = Generate_Featured_Image($image, $title, $post_id);
					set_post_thumbnail($post_id, $attach_id);
				}

				wp_set_post_terms($post_id, array($console), 'console');
				wp_set_post_terms($post_id, array($type), 'type');

				if (! empty($regions)) {
					$terms = array();
					foreach ($regions as $region) {
						if (term_exists($region, 'region')) {
							$term = term_exists($region, 'region');
						} else {
							$term = wp_insert_term($region, 'region');
						}
						$terms[] = $term['term_id'];
					}
					wp_set_post_terms($post_id, $terms, 'region');
				}

				if (! empty($genres)) {
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

				if (! empty($devs)) {
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

				_e('Post created: ' . '<a href="' . get_edit_post_link($post_id) . '" target="_blank">' . get_edit_post_link($post_id) . '</a><br>', 'romsfun');
				return;
			}
		} else {
			_e('Grabber Romstation ERROR.' . '<br>', 'romsfun');
		}
	} catch (\Throwable $th) {
		_e('Grabber Romstation ERROR.' . '<br>', 'romsfun');
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
