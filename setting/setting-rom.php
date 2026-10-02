<?php
function k_init_rom() {

	/**
	 * Add rom post type
	 */
	register_post_type( 'rom',
		array (
			'labels' => array(
				'name' 				=> __( 'Roms', 'romsfun' ),
				'singular_name' 	=> __( 'Rom', 'romsfun' ),
				'menu_name' 		=> __( 'Roms', 'romsfun' ),
				'name_admin_bar'    => __( 'Roms', 'romsfun' ),
				'all_items'			=> __( 'All Roms', 'romsfun' ),
				'add_new' 			=> __( 'Add Rom', 'romsfun' ),
				'add_new_item' 		=> __( 'Add Rom', 'romsfun' ),
				'edit_item' 		=> __( 'Edit Rom', 'romsfun' ),
			),
			'description' 		=> __( 'Roms', 'romsfun' ),
			'menu_position' 	=> 5,
			'menu_icon' 		=> 'dashicons-media-interactive',
			'capability_type' 	=> 'post',
			'public' 			=> true,
			'has_archive' 		=> false,
			'show_in_rest'		=> true,
			'supports' 			=> array('title', 'editor', 'thumbnail'),
			'rewrite' => false,
		)
	);

	/**
	 * Add console taxonomy
	 */
	register_taxonomy( 'console', array( 'rom' ),
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
			'hierarchical'      => true,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite' => [
				'slug'         => 'roms',   // 👈 PREFIX MÀY MUỐN
				'with_front'   => false,
				'hierarchical' => false,
			],
		)
	);

	/**
	 * Add collection taxonomy
	 */
	register_taxonomy('collection', array('rom'), 
		array(
			'labels'            => array(
				'name'              => __( 'Collection', 'romsfun' ),
				'singular_name'     => __( 'Collection', 'romsfun' ),
				'search_items'      => __( 'Search Collection', 'romsfun' ),
				'all_items'         => __( 'All Collection', 'romsfun' ),
				'parent_item'       => __( 'Parent Collection', 'romsfun' ),
				'parent_item_colon' => __( 'Parent Collection:', 'romsfun' ),
				'edit_item'         => __( 'Edit Collection', 'romsfun' ),
				'update_item'       => __( 'Update Collection', 'romsfun' ),
				'add_new_item'      => __( 'Add Collection', 'romsfun' ),
				'new_item_name'     => __( 'Collection Name', 'romsfun' ),
				'menu_name'         => __( 'Collection', 'romsfun' ),
			),
			'hierarchical'      	=> true,
			'public'            	=> true,
			'rewrite' 				=> array('slug' => 'collection', 'hierarchical' => false),
		) 
	);

	/**
	 * Add region taxonomy
	 */
	register_taxonomy('region', array('rom'),
		array(
			'labels'            => array(
				'name'              => __( 'Region', 'romsfun' ),
				'singular_name'     => __( 'Region', 'romsfun' ),
				'search_items'      => __( 'Search Region', 'romsfun' ),
				'all_items'         => __( 'All Region', 'romsfun' ),
				'parent_item'       => __( 'Parent Region', 'romsfun' ),
				'parent_item_colon' => __( 'Parent Region:', 'romsfun' ),
				'edit_item'         => __( 'Edit Region', 'romsfun' ),
				'update_item'       => __( 'Update Region', 'romsfun' ),
				'add_new_item'      => __( 'Add Region', 'romsfun' ),
				'new_item_name'     => __( 'Region Name', 'romsfun' ),
				'menu_name'         => __( 'Region', 'romsfun' ),
			),
			'hierarchical'      	=> true,
			'public'            	=> true,
            'rewrite' 				=> array('hierarchical' => false),
		) 
	);

	/**
	 * Add genre taxonomy
	 */
	register_taxonomy('genre', array('rom'), 
		array(
			'labels'            => array(
				'name'              => __( 'Genre', 'romsfun' ),
				'singular_name'     => __( 'Genre', 'romsfun' ),
				'search_items'      => __( 'Search Genre', 'romsfun' ),
				'all_items'         => __( 'All Genre', 'romsfun' ),
				'parent_item'       => __( 'Parent Genre', 'romsfun' ),
				'parent_item_colon' => __( 'Parent Genre:', 'romsfun' ),
				'edit_item'         => __( 'Edit Genre', 'romsfun' ),
				'update_item'       => __( 'Update Genre', 'romsfun' ),
				'add_new_item'      => __( 'Add Genre', 'romsfun' ),
				'new_item_name'     => __( 'Genre Name', 'romsfun' ),
				'menu_name'         => __( 'Genre', 'romsfun' ),
			),
			'hierarchical'      	=> true,
			'public'            	=> true,
            'rewrite' 				=> array('hierarchical' => false),
		) 
	);

	/**
	 * Add developer taxonomy
	 */
	register_taxonomy('dev', array('rom'), 
		array(
			'labels'            => array(
				'name'              => __( 'Developer', 'romsfun' ),
				'singular_name'     => __( 'Developer', 'romsfun' ),
				'search_items'      => __( 'Search Developer', 'romsfun' ),
				'all_items'         => __( 'All Developer', 'romsfun' ),
				'parent_item'       => __( 'Parent Developer', 'romsfun' ),
				'parent_item_colon' => __( 'Parent Developer:', 'romsfun' ),
				'edit_item'         => __( 'Edit Developer', 'romsfun' ),
				'update_item'       => __( 'Update Developer', 'romsfun' ),
				'add_new_item'      => __( 'Add Developer', 'romsfun' ),
				'new_item_name'     => __( 'Developer Name', 'romsfun' ),
				'menu_name'         => __( 'Developer', 'romsfun' ),
			),
			'hierarchical'      	=> false,
			'public'            	=> true,
            'rewrite' 				=> array('hierarchical' => false),
		) 
	);

	/**
	 * Add publisher taxonomy
	 */
	register_taxonomy('pub', array('rom'), 
		array(
			'labels'            => array(
				'name'              => __( 'Publisher', 'romsfun' ),
				'singular_name'     => __( 'Publisher', 'romsfun' ),
				'search_items'      => __( 'Search Publisher', 'romsfun' ),
				'all_items'         => __( 'All Publisher', 'romsfun' ),
				'parent_item'       => __( 'Parent Publisher', 'romsfun' ),
				'parent_item_colon' => __( 'Parent Publisher:', 'romsfun' ),
				'edit_item'         => __( 'Edit Publisher', 'romsfun' ),
				'update_item'       => __( 'Update Publisher', 'romsfun' ),
				'add_new_item'      => __( 'Add Publisher', 'romsfun' ),
				'new_item_name'     => __( 'Publisher Name', 'romsfun' ),
				'menu_name'         => __( 'Publisher', 'romsfun' ),
			),
			'hierarchical'      	=> false,
			'public'            	=> true,
            'rewrite' 				=> array('hierarchical' => false),
		) 
	);

	/**
	 * Add type taxonomy
	 */
	register_taxonomy('type', array('rom'), 
		array(
			'labels'            => array(
				'name'              => __( 'Type', 'romsfun' ),
				'singular_name'     => __( 'Type', 'romsfun' ),
				'search_items'      => __( 'Search Type', 'romsfun' ),
				'all_items'         => __( 'All Type', 'romsfun' ),
				'parent_item'       => __( 'Parent Type', 'romsfun' ),
				'parent_item_colon' => __( 'Parent Type:', 'romsfun' ),
				'edit_item'         => __( 'Edit Type', 'romsfun' ),
				'update_item'       => __( 'Update Type', 'romsfun' ),
				'add_new_item'      => __( 'Add Type', 'romsfun' ),
				'new_item_name'     => __( 'Type Name', 'romsfun' ),
				'menu_name'         => __( 'Type', 'romsfun' ),
			),
			'show_in_rest'          => true,
			'hierarchical'      	=> true,
			'public'            	=> true,
			'query_var'         	=> 'type',
			'rewrite' => array('hierarchical' => false),
		) 
	);

	/**
	 * Add rom field group
	 */
	acf_add_local_field_group( array(
		'key'		=> 'rom_settings',
		'title'     => 'Settings',
		'fields' 	=> array (
            array (
                'key' 	=> 'hide',
                'label' => 'Hide',
                'name' 	=> 'hide',
                'type' 	=> 'select',
                'wrapper' 	=> array(
                    'width' => '25',
                ),
                'choices' => array(
                    'public' => 'Public',
                    'restricted' => 'Restricted',
                ),
                'default_value' => 'public',
                'return_format' => 'value',
                'multiple' => 0,
            ),

			array (
				'key' 	=> 'file_size',
				'label' => 'File Size',
				'name' 	=> 'file_size',
				'type' 	=> 'text',
				'required' => 0,
				'wrapper' 	=> array(
					'width' => '25',
				),
			),

			array (
				'key' 	=> 'views',
				'label' => __( 'Views', 'romsfun' ),
				'name' 	=> 'views',
				'type' 	=> 'number',
				'default_value' => 0,
				'wrapper' 		=> array(
					'width' 	=> '25',
				),
				'show_column'   	   => 1,
				'show_column_weight'   => 700,
				'show_column_sortable' => 1,
			),

			array (
				'key' 	=> 'downloads',
				'label' => __( 'Downloads', 'romsfun' ),
				'name' 	=> 'downloads',
				'type' 	=> 'number',
				'default_value' => 0,
				'wrapper' 		=> array(
					'width' 	=> '25',
				),
			),

			array (
				'label' 		=> __( 'Rating Count', 'romsfun' ),
				'key'   		=> 'rating_count',
				'name'  		=> 'rating_count',
				'type' 		    => 'number',
				'translations'  => 'ignore',
				'default_value' => 0,
				'wrapper'    	=> array(
					'width'  	=> '25',
				),
			),

			array (
				'label' 		=> __( 'Rating Avg', 'romsfun' ),
				'key'   		=> 'rating_avg',
				'name'  		=> 'rating_avg',
				'type' 		    => 'number',
				'translations'  => 'ignore',
				'default_value' => 0,
				'wrapper'    	=> array(
					'width'  	=> '25',
				),
			),

			array (
				'label'		    => __( 'Hide ADS', 'romsfun' ),
				'key' 		    => 'hide_ads',
				'name' 		    => 'hide_ads',
				'type' 		    => 'true_false',
				'wrapper'    	=> array(
					'width'  	=> '25',
				),
			),
			
			array (
				'key' 	=> 'released',
				'label' => __( 'Release Date', 'romsfun' ),
				'name' 	=> 'released',
				'type' 	=> 'textarea',
				'rows'  => 2,
				'wrapper' 	=> array(
					'width' => '25',
				),
			),
			array (
				'label' 		=> __( 'Related Posts', 'romsfun' ),
				'key'   		=> 'related_posts',
				'name'  		=> 'related_posts',
				'type' 		    => 'post_object',
				'post_type'		=> 'post',
				'multiple'		=> 1,
				'return_format' => 'id',
				'wrapper'    	=> array(
					'width'  	=> '25',
				),
				
			),

            array(
                'label' => __('Internal Notes', 'romsfun'),
                'key' => 'internal_note',
                'name' => 'internal_note',
                'type' => 'textarea',
                'allow_quickedit' => 1,
                'rows' => 3,
            ),

			array(
				'key' 		=> 'image',
				'label' 	=> 'Screenshots',
				'name' 		=> 'image',
				'type' 		=> 'gallery',
				'preview_size' => 'thumbnail',
			),
			// grabber gallery
			array(
				'key'       => 'grabber_gallery',
				'name'      => 'grabber_gallery',
				'label'     => __('Grabber Gallery', 'romsfun'),
				'type'      => 'group',
				'sub_fields' => array(
					array(
						'key'       => 'grabber_input',
						'name'      => 'grabber_input',
						'type'      => 'text',
						// 'label'     => __('Image URL', 'romsfun'),
						'wrapper'   => array(
							'width' => '90',
						),
					),
					array(
						'key'       => 'grabber_button',
						'name'      => 'grabber_button',
						'type'      => 'text',
						'wrapper'   => array(
							'width' => '10',
						),
						'readonly' => 1,
						'default_value' => __('Grab Images', 'romsfun'),
					),
				),
				'wrapper' => array(
					'width' => '100',
				),
			),
			
						// grabber file
			array(
				'key'       => 'grabber_file',
				'name'      => 'grabber_file',
				'label'     => __('Grabber File', 'romspure'),
				'type'      => 'group',
				'sub_fields' => array(
					array(
						'key'       => 'grb_file_name',
						'name'      => 'grb_file_name',
						'label'     => __('File Name', 'romspure'),
						'type'      => 'text',
						'wrapper'   => array(
							'width' => '45',
						),
					),
					array(
						'key'       => 'grb_file_url',
						'name'      => 'grb_file_url',
						'label'     => __('File Url', 'romspure'),
						'type'      => 'text',
						'wrapper'   => array(
							'width' => '45',
						),
					),
					array(
						'key'       => 'grabber_file_button',
						'name'      => 'grabber_file_button',
						'type'      => 'text',
						'wrapper'   => array(
							'width' => '10',
						),
						'readonly' => 1,
						'default_value' => __('Grab Files', 'romspure'),
					),
				),
				'wrapper' => array(
					'width' => '100',
				),
			),

            array (
                'key'   		=> 'download_note',
                'label' 		=> __( 'Download note', 'romsfun' ),
                'name'  		=> 'download_note',
                'type'  		=> 'textarea',
                'rows'          => 5,
            ),

            array (
				'label'		    => __( 'Download list', 'romsfun' ),
				'key' 		    => 'download_list',
				'name' 		    => 'download_list',
				'type' 		    => 'repeater',
				'layout'	    => 'table',
				'button_label'  => __( 'Add File', 'romsfun' ),
				'sub_fields'    => array (
					array (
						'label' => __( 'Name', 'romsfun' ),
						'key' 	=> 'name',
						'name' 	=> 'name',
						'required' => 1,
						'type' 	=> 'text',
                        'wrapper'    => array(
        					'width'  => '45',
        				),
					),
					array (
						'label' => __( 'Url', 'romsfun' ),
						'key' 	=> 'url',
						'name' 	=> 'url',
						'required' => 1,
						'type' 	=> 'url',
                        'wrapper'    => array(
        					'width'  => '35',
        				),
					),
					array (
						'label' => __( 'File Size', 'romsfun' ),
						'key' 	=> 'file_size',
						'name' 	=> 'file_size',
						'required' => 0,
						'type' 	=> 'text',
                        'wrapper'    => array(
        					'width'  => '10',
        				),
					),
					array (
						'label' => __( 'File Type', 'romsfun' ),
						'key' 	=> 'file_type',
						'name' 	=> 'file_type',
						'required' => 0,
						'type' 	=> 'text',
                        'wrapper'    => array(
        					'width'  => '15',
        				),
					),
				),
			),	

			array (
				'label'		    => __( 'Download List (AFF)', 'romsfun' ),
				'key' 		    => 'download_list_aff',
				'name' 		    => 'download_list_aff',
				'type' 		    => 'repeater',
				'layout'	    => 'table',
				'button_label'  => __( 'Add', 'romsfun' ),
				'sub_fields'    => array (
					array (
						'label' => __( 'Name', 'romsfun' ),
						'key' 	=> 'name',
						'name' 	=> 'name',
						'type' 	=> 'text',
						'required' => 1,
                        'wrapper'    => array(
        					'width'  => '45',
        				),
					),
					array (
						'label' => __( 'Url', 'romsfun' ),
						'key' 	=> 'url',
						'name' 	=> 'url',
						'type' 	=> 'url',
						'required' => 1,
                        'wrapper'    => array(
        					'width'  => '35',
        				),
					),
					array (
						'label' => __( 'File Size', 'romsfun' ),
						'key' 	=> 'file_size',
						'name' 	=> 'file_size',
						'type' 	=> 'text',
						'required' => 0,
                        'wrapper'    => array(
        					'width'  => '10',
        				),
					),
					array (
						'label' => __( 'File Type', 'romsfun' ),
						'key' 	=> 'file_type',
						'name' 	=> 'file_type',
						'type' 	=> 'text',
						'required' => 0,
                        'wrapper'    => array(
        					'width'  => '15',
        				),
					),
				),
			),
		),
		'location' => array (
			array (
				array (
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'rom',
				),
			),
			array (
				array (
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'emulator',
				),
			),
		),
		'position' => 'acf_after_title',
		'label_placement' => 'top',
	) );

	/**
	 * Add console field group
	 */
	acf_add_local_field_group( array(
		'key'		=> 'console_settings',
		'fields' 	=> array (
			array (
				'key' 	=> 'cat_image',
				'label' => __( 'Image', 'romsfun' ),
				'name' 	=> 'cat_image',
				'type' 	=> 'image',
				'preview_size'		 => 'thumbnail',
				'show_column' 		 => 1,
				'show_column_weight' => 10,
				'return_format' => 'url',
			),
			array (
				'label' => __( 'Original Slug', 'romsfun' ),
				'key' 	=> 'console_original_slug',
				'name' 	=> 'console_original_slug',
				'type' 	=> 'text',
				'show_column' 		 => 1,
				'show_column_weight' => 510,
			),
			array (
				'key' 	=> 'console_rom_downloads',
				'label' => __( 'Downloads', 'romsfun' ),
				'name' 	=> 'console_rom_downloads',
				'type' 	=> 'number',
				'default_value'		 => 1,
				'show_column' 		 => 1,
				'show_column_weight' => 520,
			),
			array (
				'key' 	=> 'console_guide',
				'label' => __( 'Guide', 'romsfun' ),
				'name' 	=> 'console_guide',
				'type' 	=> 'wysiwyg',
			),
		),
		'location' => array (
			array (
				array (
					'param'    => 'taxonomy',
					'operator' => '==',
					'value'    => 'console',
				),
			),
		),
		'position' => 'normal',
		'label_placement' => 'top',
	) );

	/**
	 * Add region field group
	 */
	acf_add_local_field_group( array(
		'key'		=> 'region_settings',
		'fields' 	=> array (
			array (
				'key' 	=> 'region_image',
				'label' => __( 'Image', 'romsfun' ),
				'name' 	=> 'region_image',
				'type' 	=> 'image',
				'show_column' 		 => 1,
				'show_column_weight' => 10,
			),
		),
		'location' => array (
			array (
				array (
					'param'    => 'taxonomy',
					'operator' => '==',
					'value'    => 'region',
				),
			),
		),
		'position' => 'normal',
		'label_placement' => 'top',
	) );

    /**
     * Add collection field group
     */
    acf_add_local_field_group( array(
        'key'		=> 'collection_settings',
        'fields' 	=> array (
            array (
                'key' 	=> 'collection_banner',
                'label' => __( 'Banner', 'romsfun' ),
                'name' 	=> 'collection_banner',
                'type' 	=> 'image',
                'return_format' => 'array',
                'show_column' 		 => 1,
                'show_column_weight' => 10,
				'preview_size' => 'thumbnail',
            ),
        ),
        'location' => array (
            array (
                array (
                    'param'    => 'taxonomy',
                    'operator' => '==',
                    'value'    => 'collection',
                ),
            ),
        ),
        'position' => 'normal',
        'label_placement' => 'top',
    ) );
}
add_action( 'init', 'k_init_rom' );

// Add rom columns
function k_rom_columns_head($cols) {		
	$cols = array_merge(
		array_slice( $cols, 0, 1, false ),
		array( 'thumbnail' => __( 'Image' , 'romsfun' ) ),
		array_slice( $cols, 1, 2, false ),
		array( 'download_list' => __( 'Download list' , 'romsfun' ) ),
		array( 'download_list_aff' => __( 'Download List (AFF)' , 'romsfun' ) ),
		array( 'status' => __( 'Status' , 'romsfun' ) ),
		array( 'hide' => __( 'Hide' , 'romsfun' ) ),
		array( 'type' => __( 'Type' , 'romsfun' ) ),
		array( 'views' => __( 'Views' , 'romsfun' ) ),
		array_slice( $cols, 3, null, false )
	);
	return $cols;
}
function k_rom_columns_content($col_name, $post_ID) {
	if ($col_name == 'thumbnail') {
		$image = wp_get_attachment_image_src( get_post_thumbnail_id( $post_ID ), 'thumbnail' );
		if ( $image ) {
			echo '<a href="' . get_edit_post_link( $post_ID ) . '"><img width="60px" src="' . $image['0'] . '"/></a>';
		} else {
			echo '—';
		}
		echo '<style type="text/css">';
		echo '.column-thumbnail, .column-status { width:60px; }';
		echo '.column-title, .column-download_list, .column-download_list_aff, .column-wpseo-title { width:15%; position:relative; }';
		echo '</style>';
	}

	if ( $col_name == 'download_list' ) {
		echo '<div class="k-download-links">';
		if ( $links = get_field('download_list', $post_ID) ) {
			foreach ( $links as $value ) {
				echo $value['name'] . '<br>';
			}
		}
		echo '</div>';
		echo '<a class="k-edit-download-links" href="javascript:void(0);" data-field="download_list" data-post_id=' . $post_ID . '><i class="dashicons dashicons-edit"></i></a>';
	}

	if ( $col_name == 'download_list_aff' ) {
		echo '<div class="k-download-links">';
		if ( $links = get_field('download_list_aff', $post_ID) ) {
			foreach ( $links as $value ) {
				echo $value['name'] . '<br>';
			}
		}
		echo '</div>';
		echo '<a class="k-edit-download-links" href="javascript:void(0);" data-field="download_list_aff" data-post_id=' . $post_ID . '><i class="dashicons dashicons-edit"></i></a>';
	}

	if ( $col_name == 'status' ) {
		echo '<div class="k-post-status">' . get_post_status($post_ID) . '</div>';
		echo '<a class="k-change-post-status" href="javascript:void(0);" data-post_id=' . $post_ID . '><i class="dashicons dashicons-edit"></i></a>';
	}

	if ( $col_name == 'hide' ) {
		$hide_value = get_field('hide', $post_ID);
		if ( empty( $hide_value ) ) {
			$hide_value = 'public';
		}
		
		$hide_labels = array(
            'public' => 'Public',
            'restricted' => 'Restricted',
        );
		
		$display_label = isset( $hide_labels[ $hide_value ] ) ? $hide_labels[ $hide_value ] : $hide_value;
		echo '<div class="k-hide-status">' . esc_html( $display_label ) . '</div>';
		echo '<a class="k-change-hide-status" href="javascript:void(0);" data-post_id="' . esc_attr( $post_ID ) . '"><i class="dashicons dashicons-edit"></i></a>';
	}
	

	if ($col_name == 'type') {
        $terms = get_the_terms($post_ID, 'type'); // Replace 'type' with your actual taxonomy slug
        if ($terms && !is_wp_error($terms)) {
            $term_links = array();
            foreach ($terms as $term) {
                $term_links[] = '<a href="edit.php?post_type=rom&type=' . $term->slug . '">' . esc_html($term->name) . '</a>';
            }
            echo implode(', ', $term_links);
        } else {
            echo '—';
        }
    }

	if ( $col_name == 'views' ) {
		echo number_format_i18n( (int) get_field('views', $post_ID) );
	}

	if ( $col_name == 'wpseo-title' ) {
		echo '<br><a class="k-edit-text-field" href="javascript:void(0);" data-post_id=' . $post_ID . ' data-field="_yoast_wpseo_title"><i class="dashicons dashicons-edit"></i></a>';
	}

	if ( $col_name == 'wpseo-metadesc' ) {
		echo '<br><a class="k-edit-text-field" href="javascript:void(0);" data-post_id=' . $post_ID . ' data-field="_yoast_wpseo_metadesc"><i class="dashicons dashicons-edit"></i></a>';
	}
}
add_filter('manage_rom_posts_columns', 'k_rom_columns_head');
add_action('manage_rom_posts_custom_column', 'k_rom_columns_content', 20, 2);

/**
 * Add hide filter dropdown in admin list
 */
add_action('restrict_manage_posts', function ($post_type) {
	if ($post_type !== 'rom') {
		return;
	}
	
	$selected = isset($_GET['hide_filter']) ? $_GET['hide_filter'] : '';
	
	$choices = array(
		'public' => 'Public',
		'restricted' => 'Restricted',
	);
	
	echo '<select name="hide_filter" id="hide_filter">';
	echo '<option value="">' . __('Hide Status', 'romsfun') . '</option>';
	
	foreach ($choices as $value => $label) {
		printf(
			'<option value="%s"%s>%s</option>',
			esc_attr($value),
			selected($selected, $value, false),
			esc_html($label)
		);
	}
	
	echo '</select>';
});

/**
 * Filter posts by hide field
 */
add_action('parse_query', function ($query) {
	global $pagenow;
	
	if (!is_admin() || $pagenow !== 'edit.php') {
		return;
	}
	
	if (!isset($_GET['post_type']) || $_GET['post_type'] !== 'rom') {
		return;
	}
	
	if (!isset($_GET['hide_filter']) || empty($_GET['hide_filter'])) {
		return;
	}
	
	$hide_value = sanitize_text_field($_GET['hide_filter']);
	
	$meta_query = $query->get('meta_query');
	if (!is_array($meta_query)) {
		$meta_query = array();
	}
	
	$meta_query[] = array(
		'key' => 'hide',
		'value' => $hide_value,
		'compare' => '='
	);
	
	$query->set('meta_query', $meta_query);
});

add_filter('rewrite_rules_array', function ($rules) {
	$terms = get_terms(array(
		'taxonomy' => 'console',
		'hide_empty' => false,
	));
	
	if (is_wp_error($terms) || empty($terms)) {
		return $rules;
	}
	
	$new_rules = array();
	
	foreach ($terms as $term) {
		$term_slug = get_field('console_original_slug', 'term_' . $term->term_id);
		
		// Sử dụng console_original_slug nếu có, nếu không thì fallback về slug mặc định
		$console_slug = !empty($term_slug) ? sanitize_title($term_slug) : $term->slug;
		
		// Tạo rewrite rule riêng cho từng console
		$new_rules['roms/' . $console_slug . '/([^/]*)\.html$'] = 'index.php?post_type=rom&name=$matches[1]';
	}
	
	return $new_rules + $rules;
});

/**
 * Generate rewrite rules for type taxonomy without prefix
 */
function k_rewrite_type_taxonomy_link($wp_rewrite) {
	$terms = get_terms(array(
		'taxonomy' => 'type',
		'hide_empty' => false,
	));
	
	if (!empty($terms) && !is_wp_error($terms)) {
		$rules = array();
		foreach ($terms as $term) {
			// Main term archive
			$rules[$term->slug . '/?$'] = 'index.php?type=' . $term->slug;
			// Pagination
			$rules[$term->slug . '/page/?([0-9]{1,})/?$'] = 'index.php?type=' . $term->slug . '&paged=' . $wp_rewrite->preg_index(1);
		}
		
		// Merge with global rules (add at the end for low priority)
		$wp_rewrite->rules = $rules + $wp_rewrite->rules;
	}
}
add_filter('generate_rewrite_rules', 'k_rewrite_type_taxonomy_link');

add_filter('preview_post_link', function($preview_link, $post) {
	if ($post->post_type === 'rom') {
		return add_query_arg([
			'post_type' => 'rom',
			'p' => $post->ID,
			'preview' => 'true'
		], home_url('/'));
	}
	return $preview_link;
}, 10, 2);

add_filter('post_type_link', function ($permalink, $post, $leavename = false, $sample = false) {

	if ($post->post_type !== 'rom') {
		return $permalink;
	}

	$terms = get_the_terms($post->ID, 'console');
	if (!$terms || is_wp_error($terms)) {
		return $permalink;
	}

	$term = $terms[0];
	$term_id = $term->term_id;
	
	// Get console_original_slug from ACF field (using 'term_' format)
	$console_original_slug = get_field('console_original_slug', 'term_' . $term_id);
	
	// Use console_original_slug if exists, otherwise fallback to default slug
	$console_slug = !empty($console_original_slug) ? sanitize_title($console_original_slug) : $term->slug;

	if ($leavename) {
		$post_name = '%postname%';
	} elseif (!empty($post->post_name)) {
		$post_name = $post->post_name;
	} else {
		$post_name = sanitize_title($post->post_title);
		if (empty($post_name)) {
			$post_name = 'rom-preview-' . $post->ID;
		}
	}

	return home_url( 'roms/' . $console_slug . '/' . $post_name . '.html' );

}, 10, 4);

// Rewrite term link for type taxonomy to remove 'type' prefix
add_filter('term_link', function ($termlink, $term, $taxonomy) {
	if ($taxonomy === 'type') {
		return home_url('/' . $term->slug . '/');
	}
	return $termlink;
}, 10, 3);

// 301 redirect from old URL /type/term-name/ to new URL /term-name/
add_action('template_redirect', function() {
	// Only process on frontend, not admin
	if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
		return;
	}
	
	$request_uri = $_SERVER['REQUEST_URI'];
	$parsed_url = parse_url($request_uri);
	$path = isset($parsed_url['path']) ? trim($parsed_url['path'], '/') : '';
	
	// Check if URL starts with 'type/'
	if (preg_match('#^type/([^/]+)(/.*)?$#', $path, $matches)) {
		$term_slug = $matches[1];
		$remaining_path = isset($matches[2]) ? $matches[2] : '';
		
		// Check if term exists
		$term = get_term_by('slug', $term_slug, 'type');
		if ($term && !is_wp_error($term)) {
			$new_url = home_url('/' . $term_slug . $remaining_path);
			
			// add query string if exists
			if (!empty($parsed_url['query'])) {
				$new_url .= '?' . $parsed_url['query'];
			}
			
			wp_redirect($new_url, 301);
			exit;
		}
	}
}, 1); // Priority 1 to run early

add_action('after_switch_theme', function () {
	flush_rewrite_rules();
});

/**
 * Change hide status
 */
function k_change_hide_status() {
	$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
	
	if (!$post_id) {
		wp_send_json_error(array('message' => __('Invalid post ID', 'romsfun')));
		return;
	}
	
	$current_value = get_field('hide', $post_id);
	if (empty($current_value)) {
		$current_value = 'public';
	}
	
	// Toggle: public <-> restricted (2-value model).
	$next_value = ( $current_value === 'public' ) ? 'restricted' : 'public';
	
	// Update the field
	update_field('hide', $next_value, $post_id);
	
	// Prepare response with label
	$hide_labels = array(
		'public'     => 'Public',
		'restricted' => 'Restricted',
	);

	$display_label = isset($hide_labels[$next_value]) ? $hide_labels[$next_value] : $next_value;
	
	// Return HTML similar to what's displayed in the column
	$response = '<div class="k-hide-status">' . esc_html($display_label) . '</div>';
	$response .= '<a class="k-change-hide-status" href="javascript:void(0);" data-post_id="' . esc_attr($post_id) . '"><i class="dashicons dashicons-edit"></i></a>';
	
	echo $response;
	exit;
}
add_action('wp_ajax_k_change_hide_status', 'k_change_hide_status');

/**
 * Bulk actions to change 'hide' status for multiple roms at once
 */
add_filter('bulk_actions-edit-rom', function ($bulk_actions) {
	$bulk_actions['set_hide_public']        = __('Set hide: Public', 'romsfun');
	$bulk_actions['set_hide_restricted']    = __('Set hide: Restricted', 'romsfun');
	return $bulk_actions;
});

add_filter('handle_bulk_actions-edit-rom', function ($redirect_url, $action, $post_ids) {
	$map = array(
		'set_hide_public'   => 'public',
		'set_hide_restricted' => 'restricted'
	);

	if (!isset($map[$action])) {
		return $redirect_url;
	}

	$hide_value = $map[$action];
	$count = 0;
	foreach ($post_ids as $post_id) {
		update_field('hide', $hide_value, $post_id);
		$count++;
	}

	return add_query_arg('bulk_hide_updated', $count, $redirect_url);
}, 10, 3);

/**
 * Make the 'views' column sortable in the rom admin list
 */
add_filter('manage_edit-rom_sortable_columns', function ($columns) {
	$columns['views'] = 'views';
	return $columns;
});

add_action('pre_get_posts', function ($query) {
	if (!is_admin() || !$query->is_main_query()) {
		return;
	}

	if ($query->get('post_type') !== 'rom') {
		return;
	}

	if ($query->get('orderby') === 'views') {
		$query->set('meta_key', 'views');
		$query->set('orderby', 'meta_value_num');
	}
});

// Admin notice after a bulk hide update
add_action('admin_notices', function () {
	if (empty($_GET['bulk_hide_updated'])) {
		return;
	}

	$count = intval($_GET['bulk_hide_updated']);
	printf(
		'<div class="notice notice-success is-dismissible"><p>' .
		esc_html(_n('%d rom updated.', '%d roms updated.', $count, 'romsfun')) .
		'</p></div>',
		$count
	);
});
