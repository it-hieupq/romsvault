<?php
function k_init_emulator() {

	/**
	 * Add emulator post type
	 */
	register_post_type( 'emulator',
		array ( 
			'labels' => array(
				'name' 				=> __( 'Emulator', 'rom' ),
				'singular_name' 	=> __( 'Emulator', 'rom' ),
				'menu_name' 		=> __( 'Emulator', 'rom' ),
				'name_admin_bar'    => __( 'Emulator', 'rom' ),
				'all_items'			=> __( 'All Emulator', 'rom' ),
				'add_new' 			=> __( 'Add Emulator', 'rom' ),
				'add_new_item' 		=> __( 'Add Emulator', 'rom' ),
				'edit_item' 		=> __( 'Edit Emulator', 'rom' ),
			),
			'description' 		=> __( 'Emulators', 'rom' ),
			'menu_position' 	=> 5,
			'menu_icon' 		=> 'dashicons-album',
			'capability_type' 	=> 'post',
			'public' 			=> true,
			'has_archive' 		=> false,
		'show_in_rest'      => true,
		'supports' 			=> array('title', 'editor', 'thumbnail', 'comments'),
		// The permalink (post_type_link) and the single rule are built by this file,
		// exactly as for rom, so WP must not generate any rule for this post type.
		'rewrite' 			=> false,
		)
	);

	/**
	 * Add emulator_console taxonomy
	 */
	register_taxonomy('emulator_console', array('emulator'), 
		array(
			'labels'            => array(
				'name'              => __( 'Console', 'rom' ),
				'singular_name'     => __( 'Console', 'rom' ),
				'search_items'      => __( 'Search Console', 'rom' ),
				'all_items'         => __( 'All Console', 'rom' ),
				'parent_item'       => __( 'Parent Console', 'rom' ),
				'parent_item_colon' => __( 'Parent Console:', 'rom' ),
				'edit_item'         => __( 'Edit Console', 'rom' ),
				'update_item'       => __( 'Update Console', 'rom' ),
				'add_new_item'      => __( 'Add Console', 'rom' ),
				'new_item_name'     => __( 'Console Name', 'rom' ),
				'menu_name'         => __( 'Console', 'rom' ),
			),
			'hierarchical'      	=> true,
			'public'            	=> true,
			'show_admin_column' 	=> true,
			'show_in_rest'			=> true,
			'rewrite' 				=> array(
				'slug'         => 'emulators',
				'with_front'   => false,
				'hierarchical' => false,
			),
		) 
	);

	/**
	 * Add os taxonomy
	 */
	register_taxonomy('os', array('emulator'), 
		array(
			'labels'            => array(
				'name'              => __( 'OS', 'rom' ),
				'singular_name'     => __( 'OS', 'rom' ),
				'search_items'      => __( 'Search OS', 'rom' ),
				'all_items'         => __( 'All OS', 'rom' ),
				'parent_item'       => __( 'Parent OS', 'rom' ),
				'parent_item_colon' => __( 'Parent OS:', 'rom' ),
				'edit_item'         => __( 'Edit OS', 'rom' ),
				'update_item'       => __( 'Update OS', 'rom' ),
				'add_new_item'      => __( 'Add OS', 'rom' ),
				'new_item_name'     => __( 'OS Name', 'rom' ),
				'menu_name'         => __( 'OS', 'rom' ),
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
				'label' => __( 'Image', 'rom' ),
				'name' 	=> 'emulator_console_image',
				'type' 	=> 'image',
				'preview_size'		 => 'thumbnail',
				'show_column' 		 => 1,
				'show_column_weight' => 10,
				'return_format' => 'url',
			),
			array (
				'key' 	=> 'emulator_console_downloads',
				'label' => __( 'Downloads', 'rom' ),
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
		array( 'thumbnail' => __( 'Image' , 'rom' ) ),
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
 * Emulator permalink: /emulators/{emulator_console}/{post_name}/.
 *
 * The post type has rewrite => false, so WP hands us ?emulator={slug} here and the
 * first emulator_console term supplies the console segment. An emulator with no term
 * keeps that query-var URL, which still resolves.
 */
function k_change_emulator_link($post_link, $post, $leavename, $sample) {
	if ( $post->post_type !== 'emulator' ) {
		return $post_link;
	}

	$terms = get_the_terms( $post->ID, 'emulator_console' );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return $post_link;
	}

	// %emulator% is the token get_sample_permalink() expects for this post type.
	$post_name = $leavename ? '%emulator%' : $post->post_name;
	if ( empty( $post_name ) ) {
		return $post_link;
	}

	return home_url( 'emulators/' . $terms[0]->slug . '/' . $post_name . '/' );
}
add_filter('post_type_link', 'k_change_emulator_link', 10, 4);

/**
 * One rule for every emulator, mirroring the rom rule. post_name alone identifies
 * the emulator, so the console segment is not matched and the rule never depends on
 * the term list. The archive rules WP generates for emulator_console sit one segment
 * up, and the lookahead leaves their /emulators/{console}/feed/ endpoints alone.
 */
add_filter('rewrite_rules_array', function ($rules) {
	$new_rules = array(
		'emulators/[^/]+/(?!(?:feed|embed)/?$)([^/]+)/?$' => 'index.php?post_type=emulator&name=$matches[1]',
	);

	return $new_rules + $rules;
});
