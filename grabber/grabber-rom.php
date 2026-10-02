<?php
function k_grabber_rom_init() {
	/**
	 * Create grabber_rom page
	 */
	if( function_exists('acf_add_options_page') ) {
		acf_add_options_page(array(
			'page_title' 	  => 'Grabber Rom',
			'menu_title'	  => 'Grabber Rom',
			'menu_slug' 	  => 'grabber_rom',
			'icon_url'		  => 'dashicons-download',
			'capability'	  => 'manage_options',
			'id'			  => 'grabber_rom',
			'post_id' 		  => 'grabber_rom',
			'parent'	      => 'edit.php?post_type=rom',
		));
	}

	/**
	 * Add grabber_rom field group
	 */
	if ( function_exists('acf_add_local_field_group') ) {

		acf_add_local_field_group(array(
			'key'		=> 'grabber_rom_settings',
			'title' 	=> __( 'Settings', 'romsfun' ),
			'fields' 	=> array (
                array(
                    'label' => __('Console', 'romsfun'),
                    'name' => 'grabber_console',
                    'key' => 'grabber_console',
                    'type' => 'taxonomy',
                    'taxonomy' => 'console',
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
					'label' => __( 'Url', 'romsfun' ),
					'name'  => 'grabber_rom_url',
					'key'   => 'grabber_rom_url',
					'type'  => 'url',
				),
			),
			'location' => array (
				array (
					array (
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => 'grabber_rom',
					),
				),
			),
		));	
	}
}
add_action( 'init', 'k_grabber_rom_init' );

/**
 * grabber_rom
 */
function k_grabber_rom() {

	$params = $_REQUEST['acf'];

    if (empty($params['grabber_console'])) {
        _e('Please select console.', 'romsfun');
        exit;
    }

    if (empty($params['grabber_type'])) {
        _e('Please select type.', 'romsfun');
        exit;
    }

	if ( empty($params['grabber_rom_url']) ) {
		_e('Please input url.', 'romsfun');
		exit;
	}

	require_once get_template_directory() . '/grabber/simple_html_dom.php';

	$url = $params['grabber_rom_url'];
    $console = $params['grabber_console'];
    $type = $params['grabber_type'];
	update_field('grabber_rom_url', $url, 'grabber_rom');
    update_field('grabber_console', $console, 'grabber_rom');
    update_field('grabber_type', $type, 'grabber_rom');

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_HEADER, 0);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
	curl_setopt($curl, CURLOPT_URL, $url);
	$str = curl_exec($curl);
	$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	if ( $httpCode == 200 ) {

		$html = new simple_html_dom();
		$html->load($str);

		if ( $temp = $html->find('#game_page_jaquette img', 0) ) {
			$image = 'https://www.romstation.fr' . $temp->src;
		}

		$title = trim($html->find('.ipsType_pageTitle', 0)->plaintext);
		
		$options = $html->find('#option_game_all p');
		if ( $options ) {
			foreach ( $options as $option ) {
				if ( trim($option->find('b', 0)->plaintext) == 'Console :' ) {
					if ( $values = $option->find('a') ) {
						foreach ( $values as $value ) {
							$consoles[] = trim($value->plaintext);
						}
					}
				}

				if ( trim($option->find('b', 0)->plaintext) == 'Language :' ) {
					if ( $values = $option->find('a img') ) {
						foreach ( $values as $value ) {
							$regions[] = trim($value->title);
						}
					}
				}

				if ( trim($option->find('b', 0)->plaintext) == 'Genre :' ) {
					if ( $values = $option->find('a') ) {
						foreach ( $values as $value ) {
							$genres[] = trim($value->plaintext);
						}
					}
				}

				if ( trim($option->find('b', 0)->plaintext) == 'Developer :' ) {
					if ( $values = $option->find('a') ) {
						foreach ( $values as $value ) {
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
            echo __('Post exists:', 'romsfun') . ' ' . '<a href="' . get_edit_post_link($post_id) . '" target="_blank">' . get_edit_post_link($post_id) . '</a>';
            exit;
        }
        elseif ($post_id_1 > 0) {
            echo __('Post exists:', 'romsfun') . ' ' . '<a href="' . get_edit_post_link($post_id_1) . '" target="_blank">' . get_edit_post_link($post_id_1) . '</a>';
            exit;
        }
        elseif ($post_id_2 > 0) {
            echo __('Post exists:', 'romsfun') . ' ' . '<a href="' . get_edit_post_link($post_id_2) . '" target="_blank">' . get_edit_post_link($post_id_2) . '</a>';
            exit;
        }
        elseif ($post_id_3 > 0) {
            echo __('Post exists:', 'romsfun') . ' ' . '<a href="' . get_edit_post_link($post_id_3) . '" target="_blank">' . get_edit_post_link($post_id_3) . '</a>';
            exit;
        }
        else {

			$post_id = wp_insert_post(array(
				'post_type'    => 'rom',
				'post_title'   => $title,
				'post_content' => $content
			));

			if ( ! empty($image) ) {
				$attach_id = Generate_Featured_Image($image, $title, $post_id);
				set_post_thumbnail( $post_id, $attach_id );
			}

            wp_set_post_terms($post_id, array($console), 'console' );
            wp_set_post_terms($post_id, array($type), 'type' );

			if ( ! empty($regions) ) {
				$terms = array();
				foreach ( $regions as $region ) {
					if ( term_exists($region, 'region') ) {
						$term = term_exists($region, 'region');
					} else {
						$term = wp_insert_term($region, 'region' );
					}
					$terms[] = $term['term_id'];
				}
				wp_set_post_terms( $post_id, $terms, 'region' );
			}

			if ( ! empty($genres) ) {
				$terms = array();
				foreach ( $genres as $genre ) {
					if ( term_exists($genre, 'genre') ) {
						$term = term_exists($genre, 'genre');
					} else {
						$term = wp_insert_term($genre, 'genre' );
					}
					$terms[] = $term['term_id'];
				}
				wp_set_post_terms( $post_id, $terms, 'genre' );
			}

			if ( ! empty($devs) ) {
				$terms = array();
				foreach ( $devs as $dev ) {
					if ( term_exists($dev, 'dev') ) {
						$term = term_exists($dev, 'dev');
					} else {
						$term = wp_insert_term($dev, 'dev' );
					}
					$terms[] = $term['term_id'];
				}
				wp_set_post_terms( $post_id, $terms, 'dev' );
			}

			echo __('Post created:', 'romsfun') . ' ' . '<a href="' . get_edit_post_link($post_id) . '" target="_blank">' . get_edit_post_link($post_id) .'</a>';
			exit;
		}
	}
	
	_e('Grabber Rom ERROR.', 'romsfun');
	exit;
}
add_action( 'wp_ajax_k_grabber_rom', 'k_grabber_rom' );