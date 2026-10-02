<?php
function k_check_duplicate_init() {
	/**
	 * Create check_duplicate page
	 */
	if( function_exists('acf_add_options_page') ) {
		acf_add_options_page(array(
			'page_title' 	  => 'Check Duplicate',
			'menu_title'	  => 'Check Duplicate',
			'menu_slug' 	  => 'check_duplicate',
			'icon_url'		  => 'dashicons-columns',
			'capability'	  => 'manage_options',
			'id'			  => 'check_duplicate',
			'post_id' 		  => 'check_duplicate',
		));
	}

	/**
	 * Add check_duplicate field group
	 */
	if ( function_exists('acf_add_local_field_group') ) {
		acf_add_local_field_group(array(
			'key'		=> 'check_duplicate_settings',
			'title' 	=> __( 'Settings', 'romsfun' ),
			'fields' 	=> array (
				array(
					'label' => __( 'Console', 'romsfun' ),
					'name'  => 'check_duplicate_console',
					'key'   => 'check_duplicate_console',
					'type'  => 'taxonomy',
					'taxonomy'   => 'console',
					'field_type' => 'select',
					'allow_null' => 1,
				),
				array(
					'label' => __( 'Post Status', 'romsfun' ),
					'name'  => 'check_duplicate_post_status',
					'key'   => 'check_duplicate_post_status',
					'type'  => 'radio',
					'choices'     => array(
						'any' 	  => 'All',
						'draft'	  => 'Draft',
						'publish' => 'Publish',
					),
					'layout' => 'horizontal',
				),
			),
			'location' => array (
				array (
					array (
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => 'check_duplicate',
					),
				),
			),
		));	
	}
}
add_action( 'init', 'k_check_duplicate_init' );

/**
 * Check Duplicate
 */
function k_check_duplicate() {
	$params = $_REQUEST['acf'];
	if ( empty($params['check_duplicate_console']) ) {
		_e('Please select console.', 'romsfun');
		exit;
	}

	$console = $params['check_duplicate_console'];
	$post_status = $params['check_duplicate_post_status'];
	update_field('check_duplicate_console', $console, 'check_duplicate');
	update_field('check_duplicate_post_status', $post_status, 'check_duplicate');

	$offset = $_REQUEST['offset'];
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
			$links = '';
			
			if ( $links ) {
				$message .= '<li><a href="' . get_edit_post_link() . '" target="_blank">' . get_the_title() . '</a>' . $links . '</li>';
			}
		}

		header('Content-Type: application/json');
		echo json_encode( array( 'status' => true, 'offset' => $offset + 5, 'message' => $message ) );
	} else {
		header('Content-Type: application/json');
		echo json_encode( array( 'status' => false, 'message' => __('Check Duplicate is completed.', 'romsfun') ) );
	}

	exit;
}
add_action( 'wp_ajax_k_check_duplicate', 'k_check_duplicate' );