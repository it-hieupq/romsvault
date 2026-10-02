<?php
function k_init_emulator() {

	/**
	 * Add emulator post type
	 */
	register_post_type( 'emulator',
		array ( 
			'labels' => array(
				'name' 				=> __( 'Emulator', 'romsfun' ),
				'singular_name' 	=> __( 'Emulator', 'romsfun' ),
				'menu_name' 		=> __( 'Emulator', 'romsfun' ),
				'name_admin_bar'    => __( 'Emulator', 'romsfun' ),
				'all_items'			=> __( 'All Emulator', 'romsfun' ),
				'add_new' 			=> __( 'Add Emulator', 'romsfun' ),
				'add_new_item' 		=> __( 'Add Emulator', 'romsfun' ),
				'edit_item' 		=> __( 'Edit Emulator', 'romsfun' ),
			),
			'description' 		=> __( 'Emulators', 'romsfun' ),
			'menu_position' 	=> 5,
			'menu_icon' 		=> 'dashicons-album',
			'capability_type' 	=> 'post',
			'public' 			=> true,
			'has_archive' 		=> false,
		'show_in_rest'      => true,
		'supports' 			=> array('title', 'editor', 'thumbnail', 'comments'),
		'rewrite' 			=> array(
				'slug' 			=> 'emulators/%console%',
			),
		)
	);

	/**
	 * Add emulator_console taxonomy
	 */
	register_taxonomy('emulator_console', array('emulator'), 
		array(
			'labels'            => array(
				'name'              => __( 'Console', 'romsfun' ),
				'singular_name'     => __( 'Console', 'romsfun' ),
				'search_items'      => __( 'Search Console', 'romsfun' ),
				'all_items'         => __( 'All Console', 'romsfun' ),
				'parent_item'       => __( 'Parent Console', 'romsfun' ),
				'parent_item_colon' => __( 'Parent Console:', 'romsfun' ),
				'edit_item'         => __( 'Edit Console', 'romsfun' ),
				'update_item'       => __( 'Update Console', 'romsfun' ),
				'add_new_item'      => __( 'Add Console', 'romsfun' ),
				'new_item_name'     => __( 'Console Name', 'romsfun' ),
				'menu_name'         => __( 'Console', 'romsfun' ),
			),
			'hierarchical'      	=> true,
			'public'            	=> true,
			'show_admin_column' 	=> true,
			'show_in_rest'			=> true,
			'rewrite' 				=> array('slug' => 'emulator-console', 'hierarchical' => true),
		) 
	);

	/**
	 * Add os taxonomy
	 */
	register_taxonomy('os', array('emulator'), 
		array(
			'labels'            => array(
				'name'              => __( 'OS', 'romsfun' ),
				'singular_name'     => __( 'OS', 'romsfun' ),
				'search_items'      => __( 'Search OS', 'romsfun' ),
				'all_items'         => __( 'All OS', 'romsfun' ),
				'parent_item'       => __( 'Parent OS', 'romsfun' ),
				'parent_item_colon' => __( 'Parent OS:', 'romsfun' ),
				'edit_item'         => __( 'Edit OS', 'romsfun' ),
				'update_item'       => __( 'Update OS', 'romsfun' ),
				'add_new_item'      => __( 'Add OS', 'romsfun' ),
				'new_item_name'     => __( 'OS Name', 'romsfun' ),
				'menu_name'         => __( 'OS', 'romsfun' ),
			),
			'hierarchical'      	=> true,
			'public'            	=> true,
			'show_admin_column' 	=> true,
            'rewrite' 				=> array('hierarchical' => true),
		) 
	);

	/**
	 * Add emulator_console field group
	 */
	acf_add_local_field_group( array(
		'key'		=> 'emulator_console_settings',
		'fields' 	=> array (
			array (
				'key' 	=> 'emulator_console_image',
				'label' => __( 'Image', 'romsfun' ),
				'name' 	=> 'emulator_console_image',
				'type' 	=> 'image',
				'preview_size'		 => 'thumbnail',
				'show_column' 		 => 1,
				'show_column_weight' => 10,
				'return_format' => 'url',
			),
			array (
				'key' 	=> 'emulator_console_downloads',
				'label' => __( 'Downloads', 'romsfun' ),
				'name' 	=> 'emulator_console_downloads',
				'type' 	=> 'number',
				'default_value'		 => 0,
				'show_column' 		 => 1,
				'show_column_weight' => 520,
			),
		),
		'location' => array (
			array (
				array (
					'param'    => 'taxonomy',
					'operator' => '==',
					'value'    => 'emulator_console',
				),
			),
		),
		'position' => 'normal',
		'label_placement' => 'top',
	) );
}
add_action( 'init', 'k_init_emulator' );

// Add emulator columns
function k_emulator_columns_head($cols) {		
	$cols = array_merge(
		array_slice( $cols, 0, 1, true ),
		array( 'thumbnail' => __( 'Image' , 'romsfun' ) ),
		array_slice( $cols, 1, null, true )
	);
	return $cols;
}
function k_emulator_columns_content($col_name, $post_ID) {
	if ($col_name == 'thumbnail') {
		$image = wp_get_attachment_image_src( get_post_thumbnail_id( $post_ID ), 'thumbnail' );
		if ( $image ) {
			echo '<a href="' . get_edit_post_link( $post_ID ) . '"><img width="70px" src="' . $image['0'] . '"/></a>';
		} else {
			echo '—';
		}
		echo '<style type="text/css">';
		echo '.column-thumbnail { width:70px; }';
		echo '</style>';
	}
}
add_filter('manage_emulator_posts_columns', 'k_emulator_columns_head');
add_action('manage_emulator_posts_custom_column', 'k_emulator_columns_content', 10, 2);

/**
 * Rewrite emulator_console link
 */
function k_rewrite_emulator_console_link($wp_rewrite) {
    $terms = get_terms( array(
        'taxonomy' 	 => 'emulator_console',
        'hide_empty' => false,
    ) );
    if ( ! empty($terms) ) {
	    foreach ( $terms as $term ) {
	        $rules['emulators/' . $term->slug . '/?$'] = 'index.php?' . $term->taxonomy . '=' . $term->slug;
	        $rules['emulators/' . $term->slug . '/page/?([0-9]{1,})/?$'] = 'index.php?' . $term->taxonomy . '=' . $term->slug . '&paged=' . $wp_rewrite->preg_index( 1 );
		}

	    // merge with global rules
	    $wp_rewrite->rules = $rules + $wp_rewrite->rules;	
	}
}
add_filter('generate_rewrite_rules', 'k_rewrite_emulator_console_link');

add_filter('term_link', 'k_change_emulator_console_link', 10, 3);
function k_change_emulator_console_link( $url, $term, $taxonomy ) {
	if ( $taxonomy == 'emulator_console' )
		return home_url('emulators/' . $term->slug);
	else 
		return $url;
}

/**
 * Change emulator link
 */
function k_change_emulator_link($post_link, $post, $leavename, $sample) {
	$terms = get_the_terms( $post->ID, 'emulator_console' );
    if ( ! is_wp_error($terms) && ! empty($terms) ) {
    	$term_slug = $terms[0]->slug;
    	if ( ! empty($terms[0]->parent) )
    		$term_slug = get_term($terms[0]->parent)->slug . '/' . $term_slug;
		
		if ( ! empty($post->post_name) ) {
			$post_link = home_url( 'emulators/' . ( $leavename ? '%postname%' : $post->post_name ) . '.html' );
		}
	}

	return $post_link;
}
add_filter('post_type_link', 'k_change_emulator_link', 10, 4);

function k_rewrite_emulator_link($rules) {
	$terms = get_terms( array('taxonomy' => 'emulator_console') );
	if ( ! is_wp_error($terms) && ! empty($terms) ) {
		$new_rules = array();

		foreach ( $terms as $term ) {
			$term_slug = $term->slug;
			if ( ! empty($term->parent) ) 
				$term_slug = get_term($term->parent)->slug . '/' . $term_slug;
			
			$new_rules[ 'emulators/([^/]*)\.html$'] = 'index.php?post_type=emulator&name=$matches[1]';
		}

		return $new_rules + $rules;
	}
}
add_filter('rewrite_rules_array', 'k_rewrite_emulator_link');