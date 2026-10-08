<?php
function k_insert_external_link_aff_init() {
	/**
	 * Create insert_external_link_aff page
	 */
	if( function_exists('acf_add_options_page') ) {
		acf_add_options_page(array(
			'page_title' 	  => 'Insert External Link Aff',
			'menu_title'	  => 'Insert External Link Aff',
			'menu_slug' 	  => 'insert_external_link_aff',
			'icon_url'		  => 'dashicons-money-alt',
			'capability'	  => 'manage_options',
			'id'			  => 'insert_external_link_aff',
			'post_id' 		  => 'insert_external_link_aff',
		));
	}

	/**
	 * Add insert_external_link_aff field group
	 */
	if ( function_exists('acf_add_local_field_group') ) {
		acf_add_local_field_group(array(
			'key'		=> 'insert_external_link_aff_settings',
			'title' 	=> __( 'Settings', 'rom' ),
			'fields' 	=> array (
				array(
					'label' => __( 'Console', 'rom' ),
					'name'  => 'insert_external_link_aff_console',
					'key'   => 'insert_external_link_aff_console',
					'type'  => 'taxonomy',
					'taxonomy'   => 'console',
					'field_type' => 'select',
					'allow_null' => 1,
				),
				array(
					'label' => __( 'Url', 'rom' ),
					'name'  => 'insert_external_link_aff_url',
					'key'   => 'insert_external_link_aff_url',
					'type'  => 'text',
				),
				array(
					'label' => __( 'Type', 'rom' ),
					'name'  => 'insert_external_link_aff_type',
					'key'   => 'insert_external_link_aff_type',
					'type'  => 'text',
				),
				array(
					'label' => __( 'Post Status', 'rom' ),
					'name'  => 'insert_external_link_aff_post_status',
					'key'   => 'insert_external_link_aff_post_status',
					'type'  => 'radio',
					'choices'     => array(
						'any' 	  => 'All',
						'draft'	  => 'Draft',
						'publish' => 'Publish',
					),
					'layout' => 'horizontal',
				),
				array(
					'label' => __( 'Remove', 'rom' ),
					'name'  => 'insert_external_link_aff_remove',
					'key'   => 'insert_external_link_aff_remove',
					'type'  => 'true_false',
				),
			),
			'location' => array (
				array (
					array (
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => 'insert_external_link_aff',
					),
				),
			),
		));	
	}
}
add_action( 'init', 'k_insert_external_link_aff_init' );

/**
 * Insert External Link Aff
 */
function k_insert_external_link_aff() {
	$params = $_REQUEST['acf'];
	if ( empty($params['insert_external_link_aff_console']) ) {
		header('Content-Type: application/json');
		echo json_encode( array( 'status' => false, 'message' => __('Please select console.', 'rom') ) );
		exit;
	}
	if ( empty($params['insert_external_link_aff_url']) ) {
		header('Content-Type: application/json');
		echo json_encode( array( 'status' => false, 'message' => __('Please input url.', 'rom') ) );
		exit;
	}

	require get_template_directory() . '/inc/simple_html_dom.php';

	$console = $params['insert_external_link_aff_console'];
	$url = $params['insert_external_link_aff_url'];
	$type = $params['insert_external_link_aff_type'];
	$post_status = $params['insert_external_link_aff_post_status'];
	$remove = $params['insert_external_link_aff_remove'];
	update_field('insert_external_link_aff_console', $console, 'insert_external_link_aff');
	update_field('insert_external_link_aff_url', $url, 'insert_external_link_aff');
	update_field('insert_external_link_aff_type', $type, 'insert_external_link_aff');
	update_field('insert_external_link_aff_post_status', $post_status, 'insert_external_link_aff');


	if ( $remove ) {
		$args = array(
			'post_type' 	 => 'rom',
			'post_status'	 => $post_status,
			'posts_per_page' => -1,
			'fields' 		 => 'ids',
			'orderby'		 => 'title',
			'order'			 => 'ASC',			 
			'tax_query'		 => array(
				array(
					'taxonomy' => 'console',
					'field'	   => 'term_id',
					'terms'    => $console,
				),
			), 
		);
		$query = new WP_Query($args);

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) { $query->the_post();
				delete_field('download_list_aff');
			}
		} 

		header('Content-Type: application/json');
		echo json_encode( array( 'status' => false, 'message' => __('Remove External Link Aff is completed.', 'rom') ) );
		
	} else {
		$offset = $_REQUEST['offset'];
		$upload_dir = wp_upload_dir();
		$path = $upload_dir['basedir'] . '/' . 'aff_' . $console;

		if ( $offset == 0 ) {
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_HEADER, 0);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
			curl_setopt($curl, CURLOPT_URL, $url);
			$str = curl_exec($curl);
			curl_close($curl);
			$html = new simple_html_dom();
			$html->load($str);
			$elements = $html->find('.premium tr');
			unset($elements[0]);

			$jsonData = array();
			foreach ( $elements as $key => $element ) {
				$name = trim($element->find('td a', 0)->plaintext);
				$name = html_entity_decode($name);
				$name = trim(pathinfo($name, PATHINFO_FILENAME)); 
				$link = trim($element->find('td a', 0)->href);
				$size = trim($element->find('td', 1)->plaintext);

				$jsonData[$key] = array();
				$jsonData[$key]['name'] = $name;
				$jsonData[$key]['link'] = $link;
				$jsonData[$key]['size'] = $size;
			}
			$jsonString = json_encode($jsonData, JSON_PRETTY_PRINT);
			$fp = fopen($path, 'w');
			fwrite($fp, $jsonString);
			fclose($fp);

			$html->clear(); 
 			unset($html);
		}

		$jsonString = file_get_contents($path);
		$elements = json_decode($jsonString, true);

		if ( ! empty($elements) ) {
			$args = array(
				'post_type' 	 => 'rom',
				'post_status'	 => $post_status,
				'posts_per_page' => 5,
				'offset'		 => $offset,
				'fields' 		 => 'ids',
				'orderby'		 => 'title',
				'order'			 => 'ASC',			 
				'tax_query'		 => array(
					array(
						'taxonomy' => 'console',
						'field'	   => 'term_id',
						'terms'    => $console,
					),
				), 
			);
			$query = new WP_Query($args);

			if ( $query->have_posts() ) {
				$message = '';

				while ( $query->have_posts() ) { $query->the_post();
					$title = get_the_title();
					$title = html_entity_decode($title);
					$temp_title = cleanString($title);
					$temp_title = preg_replace('/\([^)]+\)/', '', $temp_title);
					$temp_title = strtolower($temp_title);
					$words = preg_split('/\s+/', $temp_title);
					$temp_title = preg_grep('/^[A-Za-z\d]+$/', $words);
					$temp_title = array_unique($temp_title);
					sort($temp_title);

					$links = '';
					foreach ( $elements as $key => $element ) {
						$name = $element['name'];
						$link = $element['link'];
						$size = $element['size'];

						if ( have_rows('download_list_aff') ) {
							while ( have_rows('download_list_aff') ) { the_row();
								if ( get_sub_field('url') == $link ) {
									unset($elements[$key]);
									$links .= '<br>Existed: ' . $name;
									continue 2;
								}
							}
						}

						if ( count(get_field('download_list_aff')) > 100 ) break;

						$temp_name = cleanString($name);
						$temp_name = preg_replace('/\([^)]+\)/', '', $temp_name);
						$temp_name = strtolower($temp_name);
						$words = preg_split('/\s+/', $temp_name);
						$temp_name = preg_grep('/^[A-Za-z\d]+$/', $words);
						$temp_name = array_unique($temp_name);
						sort($temp_name);

						if ( ( $temp_title == $temp_name) || 
							 ( count($temp_title) >= 2 && count($temp_title) <= 4 && strpos($name, $title) !== FALSE && strpos($name, $title) == 0 ) ||
							 ( ( count(array_intersect($temp_title, $temp_name)) >= count($temp_title) - 1 ) && count($temp_title) >= 5 ) ) { 

							$temp_title_numbers = array_filter($temp_title, 'is_numeric');
							$temp_name_numbers = array_filter($temp_name, 'is_numeric');
							if ( empty($temp_title_numbers) && ! empty($temp_name_numbers) ) continue;
							if ( ! empty($temp_title_numbers) && empty($temp_name_numbers) ) continue;
							if ( ! empty($temp_title_numbers) && ! empty($temp_name_numbers) ) {
								foreach ( $temp_title_numbers as $key2 => $value ) {
									if ( $temp_name_numbers[$key2] != $temp_title_numbers[$key2] ) continue 2;
								}
							}

					        $row = array(
					        	'name' 		=> $name,
					        	'url' 		=> $link,
					        	'file_size' => $size,
					        	'file_type' => $type
					        );
					        add_row('download_list_aff', $row);
					        
					        unset($elements[$key]);
					        $links .= '<br>Added: ' . $name; //urldecode($link);
						}
					}

					if ( $links ) {
						$message .= '<li><a href="' . urldecode(get_edit_post_link()) . '" target="_blank">' . $title . '</a>' . $links . '</li>';
					}
				}

				$jsonString = json_encode($elements, JSON_PRETTY_PRINT);
				$fp = fopen($path, 'w');
				fwrite($fp, $jsonString);
				fclose($fp);
				
				header('Content-Type: application/json');
				echo json_encode( array( 'status' => true, 'offset' => $offset + 5, 'message' => $message ) );
			} else {
				header('Content-Type: application/json');
				echo json_encode( array( 'status' => false, 'message' => __('Insert External Link Aff is completed.', 'rom') ) );
			}
		} else {
			header('Content-Type: application/json');
			echo json_encode( array( 'status' => false, 'message' => __('Error. Please contact coder.', 'rom') ) );
		}
    }
    
	exit;
}
add_action( 'wp_ajax_k_insert_external_link_aff', 'k_insert_external_link_aff' );