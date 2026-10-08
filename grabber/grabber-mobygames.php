<?php
function k_grabber_mobygames_init() {
	/**
	 * Create grabber_mobygames page
	 */
	if( function_exists('acf_add_options_page') ) {
		acf_add_options_page(array(
			'page_title' 	  => 'Grabber Mobygames',
			'menu_title'	  => 'Grabber Mobygames',
			'menu_slug' 	  => 'grabber_mobygames',
			'icon_url'		  => 'dashicons-download',
			'capability'	  => 'manage_options',
			'id'			  => 'grabber_mobygames',
			'post_id' 		  => 'grabber_mobygames',
			'parent'	      => 'edit.php?post_type=rom',
		));
	}

	/**
	 * Add grabber_mobygames field group
	 */
	if ( function_exists('acf_add_local_field_group') ) {
		acf_add_local_field_group(array(
			'key'		=> 'grabber_mobygames_settings',
			'title' 	=> __( 'Settings', 'rom' ),
			'fields' 	=> array (
				array(
					'label' 	 => __( 'Console', 'rom' ),
					'name'  	 => 'grabber_mobygames_console',
					'key'   	 => 'grabber_mobygames_console',
					'type'  	 => 'taxonomy',
					'taxonomy'   => 'console',
					'field_type' => 'select',
					'allow_null' => 1,
				),
				array(
					'label' => __( 'Url', 'rom' ),
					'name'  => 'grabber_mobygames_url',
					'key'   => 'grabber_mobygames_url',
					'type'  => 'url',
				),
			),
			'location' => array (
				array (
					array (
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => 'grabber_mobygames',
					),
				),
			),
		));	
	}
}
add_action( 'init', 'k_grabber_mobygames_init' );

/**
 * grabber_mobygames
 */
function k_grabber_mobygames() {
	$params = $_REQUEST['acf'];
	if ( empty($params['grabber_mobygames_console']) ) {
		_e('Please select console.', 'rom');
		exit;
	}
	if ( empty($params['grabber_mobygames_url']) ) {
		_e('Please input url.', 'rom');
		exit;
	}

	require_once get_template_directory() . '/grabber/simple_html_dom.php';

	$console = $params['grabber_mobygames_console'];
	$url = $params['grabber_mobygames_url'];
	update_field('grabber_mobygames_console', $console, 'grabber_mobygames');
	update_field('grabber_mobygames_url', $url, 'grabber_mobygames');

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_HEADER, 0);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
	curl_setopt($curl, CURLOPT_URL, $url);
	$str = curl_exec($curl);
	$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	if ( $httpCode == 200 ) {

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
            echo __('Post exists:', 'rom') . ' ' . '<a href="' . get_edit_post_link($post_id) . '" target="_blank">' . get_edit_post_link($post_id) . '</a>';
            exit;
        }
        elseif ($post_id_1 > 0) {
            echo __('Post exists:', 'rom') . ' ' . '<a href="' . get_edit_post_link($post_id_1) . '" target="_blank">' . get_edit_post_link($post_id_1) . '</a>';
            exit;
        }
        elseif ($post_id_2 > 0) {
            echo __('Post exists:', 'rom') . ' ' . '<a href="' . get_edit_post_link($post_id_2) . '" target="_blank">' . get_edit_post_link($post_id_2) . '</a>';
            exit;
        }
        elseif ($post_id_3 > 0) {
            echo __('Post exists:', 'rom') . ' ' . '<a href="' . get_edit_post_link($post_id_3) . '" target="_blank">' . get_edit_post_link($post_id_3) . '</a>';
            exit;
        }
        else {

			if ( $temp = $html->find('#cover img', 0) ) {
				$image = $temp->src;
				$image = str_replace('.webp', '.jpg', $image);
			}

			$options = $html->find('#infoBlock dt');
			if ( $options ) {
				foreach ( $options as $option ) {
					if ( trim($option->plaintext) == 'Released' ) {
						if ( $value = $option->next_sibling()->find('a', 0) ) {
							$release_date = trim($value->plaintext);
						}
					}

					if ( trim($option->plaintext) == 'Genre' ) {
						if ( $values = $option->next_sibling()->find('a') ) {
							foreach ( $values as $value ) {
								$genres[] = trim($value->plaintext);
							}
						}
					}

					if ( trim($option->plaintext) == 'Developers' ) {
						if ( $values = $option->next_sibling()->find('a') ) {
							foreach ( $values as $value ) {
								$devs[] = trim($value->plaintext);
							}
						}
					}

					if ( trim($option->plaintext) == 'Publishers' ) {
						if ( $values = $option->next_sibling()->find('a') ) {
							foreach ( $values as $value ) {
								$pubs[] = trim($value->plaintext);
							}
						}
					}
				}
			}

			$html->find('#description-text a', 0)->outertext = '';
			$content = $html->find('#description-text', 0)->innertext;

			$post_id = wp_insert_post(array(
				'post_type'    => 'rom',
				'post_title'   => $title,
				'post_content' => $content
			));

			$terms = array();
			$terms[] = $console;
			wp_set_post_terms( $post_id, $terms, 'console' );

			update_field('released', $release_date, $post_id);

			if ( ! empty($image) ) {
				$attach_id = Generate_Featured_Image($image, $title, $post_id);
				set_post_thumbnail( $post_id, $attach_id );
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

			if ( ! empty($pubs) ) {
				$terms = array();
				foreach ( $pubs as $pub ) {
					if ( term_exists($pub, 'pub') ) {
						$term = term_exists($pub, 'pub');
					} else {
						$term = wp_insert_term($pub, 'pub' );
					}
					$terms[] = $term['term_id'];
				}
				wp_set_post_terms( $post_id, $terms, 'pub' );
			}

			echo __('Post created:', 'rom') . ' ' . '<a href="' . get_edit_post_link($post_id) . '" target="_blank">' . get_edit_post_link($post_id) .'</a>';
			exit;
		}

		$html->clear(); 
		unset($html);
	}
	
	_e('Grabber Mobygames ERROR.', 'rom');
	exit;
}
add_action( 'wp_ajax_k_grabber_mobygames', 'k_grabber_mobygames' );