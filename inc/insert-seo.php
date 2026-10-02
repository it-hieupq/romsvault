<?php
function k_insert_seo_init() {
	/**
	 * Create insert_seo page
	 */
	if( function_exists('acf_add_options_page') ) {
		acf_add_options_page(array(
			'page_title' 	  => 'Insert SEO',
			'menu_title'	  => 'Insert SEO',
			'menu_slug' 	  => 'insert_seo',
			'icon_url'		  => 'dashicons-insert',
			'capability'	  => 'manage_options',
			'id'			  => 'insert_seo',
			'post_id' 		  => 'insert_seo',
		));
	}

	/**
	 * Add insert_seo field group
	 */
	if ( function_exists('acf_add_local_field_group') ) {
		acf_add_local_field_group(array(
			'key'		=> 'insert_seo_settings',
			'title' 	=> __( 'Settings', 'romsfun' ),
			'fields' 	=> array (
				array(
					'label' => __( 'Console', 'romsfun' ),
					'name'  => 'insert_seo_console',
					'key'   => 'insert_seo_console',
					'type'  => 'taxonomy',
					'taxonomy'   => 'console',
					'field_type' => 'select',
					'allow_null' => 1,
				),
				array(
					'label' => __( 'Title', 'romsfun' ),
					'name'  => 'insert_seo_title',
					'key'   => 'insert_seo_title',
					'type'  => 'text',
				),
				array(
					'label' => __( 'Description', 'romsfun' ),
					'name'  => 'insert_seo_desc',
					'key'   => 'insert_seo_desc',
					'type'  => 'text',
				),
				array(
					'label' => __( 'Post Status', 'romsfun' ),
					'name'  => 'insert_seo_post_status',
					'key'   => 'insert_seo_post_status',
					'type'  => 'radio',
					'choices'     => array(
						'draft'	  => 'Draft',
						'publish' => 'Publish',
						'any' 	  => 'All',
					),
					'layout' => 'horizontal',
				),
			),
			'location' => array (
				array (
					array (
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => 'insert_seo',
					),
				),
			),
		));	
	}
}
add_action( 'init', 'k_insert_seo_init' );

/**
 * Insert SEO
 */
function k_insert_seo() {
	$params = $_REQUEST['acf'];
	
	if ( empty($params['insert_seo_console']) ) {
		_e('Please select console.', 'romsfun');
		exit;
	}

	if ( empty($params['insert_seo_title']) ) {
		_e('Please input title.', 'romsfun');
		exit;
	}

	$console = $params['insert_seo_console'];
	$title = $params['insert_seo_title'];
	$desc = $params['insert_seo_desc'];
	$post_status = $params['insert_seo_post_status'];

	update_field('insert_seo_console', $console, 'insert_seo');
	update_field('insert_seo_title', $title, 'insert_seo');
	update_field('insert_seo_desc', $desc, 'insert_seo');

	$args = array(
		'post_type' 	 => 'rom',
		'post_status'	 => $post_status,
		'posts_per_page' => -1,
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
			update_post_meta(get_the_ID(), '_yoast_wpseo_title', str_replace('[title]', get_the_title(), $title));
        	update_post_meta(get_the_ID(), '_yoast_wpseo_metadesc', str_replace('[title]', get_the_title(), $desc));
		} wp_reset_postdata();
	}
	
	_e('Insert SEO is completed.', 'romsfun');
	exit;
}
add_action( 'wp_ajax_k_insert_seo', 'k_insert_seo' );