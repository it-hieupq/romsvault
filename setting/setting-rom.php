<?php
function k_init_rom() {

	/**
	 * Add rom post type
	 */
	register_post_type( 'rom',
		array (
			'labels' => array(
				'name' 				=> __( 'Roms', 'rom' ),
				'singular_name' 	=> __( 'Rom', 'rom' ),
				'menu_name' 		=> __( 'Roms', 'rom' ),
				'name_admin_bar'    => __( 'Roms', 'rom' ),
				'all_items'			=> __( 'All Roms', 'rom' ),
				'add_new' 			=> __( 'Add Rom', 'rom' ),
				'add_new_item' 		=> __( 'Add Rom', 'rom' ),
				'edit_item' 		=> __( 'Edit Rom', 'rom' ),
			),
			'description' 		=> __( 'Roms', 'rom' ),
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
			'hierarchical'      => true,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite' => [
				'slug'         => 'roms',   // 👈 PREFIX MÀY MUỐN
				'with_front'   => false,
				'hierarchical' => false,
			],
			// console drives the ROM permalink (post_type_link) and the per-console
			// rewrite rules, so a rom without one has no usable URL. Guarantee every
			// rom always carries a console term — this covers every write path
			// (editor, quick/bulk edit, REST, grabber), unlike form-level validation.
			// Roms that land here: edit.php?post_type=rom&console=unknown
			'default_term' => [
				'name' => 'Unknown',
				'slug' => 'unknown',
			],
		)
	);

	/**
	 * Add collection taxonomy
	 */
	register_taxonomy('collection', array('rom'), 
		array(
			'labels'            => array(
				'name'              => __( 'Collection', 'rom' ),
				'singular_name'     => __( 'Collection', 'rom' ),
				'search_items'      => __( 'Search Collection', 'rom' ),
				'all_items'         => __( 'All Collection', 'rom' ),
				'parent_item'       => __( 'Parent Collection', 'rom' ),
				'parent_item_colon' => __( 'Parent Collection:', 'rom' ),
				'edit_item'         => __( 'Edit Collection', 'rom' ),
				'update_item'       => __( 'Update Collection', 'rom' ),
				'add_new_item'      => __( 'Add Collection', 'rom' ),
				'new_item_name'     => __( 'Collection Name', 'rom' ),
				'menu_name'         => __( 'Collection', 'rom' ),
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
				'name'              => __( 'Region', 'rom' ),
				'singular_name'     => __( 'Region', 'rom' ),
				'search_items'      => __( 'Search Region', 'rom' ),
				'all_items'         => __( 'All Region', 'rom' ),
				'parent_item'       => __( 'Parent Region', 'rom' ),
				'parent_item_colon' => __( 'Parent Region:', 'rom' ),
				'edit_item'         => __( 'Edit Region', 'rom' ),
				'update_item'       => __( 'Update Region', 'rom' ),
				'add_new_item'      => __( 'Add Region', 'rom' ),
				'new_item_name'     => __( 'Region Name', 'rom' ),
				'menu_name'         => __( 'Region', 'rom' ),
			),
			'hierarchical'      	=> true,
			'public'            	=> true,
            'rewrite' 				=> array('slug' => 'roms/region', 'with_front' => false, 'hierarchical' => false),
		) 
	);

	/**
	 * Add language taxonomy
	 */
	register_taxonomy('language', array('rom'),
		array(
			'labels'            => array(
				'name'              => __( 'Language', 'rom' ),
				'singular_name'     => __( 'Language', 'rom' ),
				'search_items'      => __( 'Search Language', 'rom' ),
				'all_items'         => __( 'All Language', 'rom' ),
				'parent_item'       => __( 'Parent Language', 'rom' ),
				'parent_item_colon' => __( 'Parent Language:', 'rom' ),
				'edit_item'         => __( 'Edit Language', 'rom' ),
				'update_item'       => __( 'Update Language', 'rom' ),
				'add_new_item'      => __( 'Add Language', 'rom' ),
				'new_item_name'     => __( 'Language Name', 'rom' ),
				'menu_name'         => __( 'Language', 'rom' ),
			),
			'hierarchical'      	=> true,
			'public'            	=> true,
            'rewrite' 				=> array('slug' => 'roms/language', 'with_front' => false, 'hierarchical' => false),
		) 
	);

	/**
	 * Add genre taxonomy
	 */
	register_taxonomy('genre', array('rom'), 
		array(
			'labels'            => array(
				'name'              => __( 'Genre', 'rom' ),
				'singular_name'     => __( 'Genre', 'rom' ),
				'search_items'      => __( 'Search Genre', 'rom' ),
				'all_items'         => __( 'All Genre', 'rom' ),
				'parent_item'       => __( 'Parent Genre', 'rom' ),
				'parent_item_colon' => __( 'Parent Genre:', 'rom' ),
				'edit_item'         => __( 'Edit Genre', 'rom' ),
				'update_item'       => __( 'Update Genre', 'rom' ),
				'add_new_item'      => __( 'Add Genre', 'rom' ),
				'new_item_name'     => __( 'Genre Name', 'rom' ),
				'menu_name'         => __( 'Genre', 'rom' ),
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
				'name'              => __( 'Developer', 'rom' ),
				'singular_name'     => __( 'Developer', 'rom' ),
				'search_items'      => __( 'Search Developer', 'rom' ),
				'all_items'         => __( 'All Developer', 'rom' ),
				'parent_item'       => __( 'Parent Developer', 'rom' ),
				'parent_item_colon' => __( 'Parent Developer:', 'rom' ),
				'edit_item'         => __( 'Edit Developer', 'rom' ),
				'update_item'       => __( 'Update Developer', 'rom' ),
				'add_new_item'      => __( 'Add Developer', 'rom' ),
				'new_item_name'     => __( 'Developer Name', 'rom' ),
				'menu_name'         => __( 'Developer', 'rom' ),
			),
			'hierarchical'      	=> false,
			'public'            	=> true,
            'rewrite' 				=> array('slug' => 'roms/developer', 'with_front' => false, 'hierarchical' => false),
		) 
	);

	/**
	 * Add publisher taxonomy
	 */
	register_taxonomy('pub', array('rom'), 
		array(
			'labels'            => array(
				'name'              => __( 'Publisher', 'rom' ),
				'singular_name'     => __( 'Publisher', 'rom' ),
				'search_items'      => __( 'Search Publisher', 'rom' ),
				'all_items'         => __( 'All Publisher', 'rom' ),
				'parent_item'       => __( 'Parent Publisher', 'rom' ),
				'parent_item_colon' => __( 'Parent Publisher:', 'rom' ),
				'edit_item'         => __( 'Edit Publisher', 'rom' ),
				'update_item'       => __( 'Update Publisher', 'rom' ),
				'add_new_item'      => __( 'Add Publisher', 'rom' ),
				'new_item_name'     => __( 'Publisher Name', 'rom' ),
				'menu_name'         => __( 'Publisher', 'rom' ),
			),
			'hierarchical'      	=> false,
			'public'            	=> true,
            'rewrite' 				=> array('slug' => 'roms/publisher', 'with_front' => false, 'hierarchical' => false),
		) 
	);

	/**
	 * Add type taxonomy
	 */
	register_taxonomy('type', array('rom'), 
		array(
			'labels'            => array(
				'name'              => __( 'Type', 'rom' ),
				'singular_name'     => __( 'Type', 'rom' ),
				'search_items'      => __( 'Search Type', 'rom' ),
				'all_items'         => __( 'All Type', 'rom' ),
				'parent_item'       => __( 'Parent Type', 'rom' ),
				'parent_item_colon' => __( 'Parent Type:', 'rom' ),
				'edit_item'         => __( 'Edit Type', 'rom' ),
				'update_item'       => __( 'Update Type', 'rom' ),
				'add_new_item'      => __( 'Add Type', 'rom' ),
				'new_item_name'     => __( 'Type Name', 'rom' ),
				'menu_name'         => __( 'Type', 'rom' ),
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
				'label' => __( 'Views', 'rom' ),
				'name' 	=> 'views',
				'type' 	=> 'number',
				'default_value' => 0,
				'wrapper' 		=> array(
					'width' 	=> '25',
				),
			),

			array (
				'key' 	=> 'downloads',
				'label' => __( 'Downloads', 'rom' ),
				'name' 	=> 'downloads',
				'type' 	=> 'number',
				'default_value' => 0,
				'wrapper' 		=> array(
					'width' 	=> '25',
				),
			),

			array (
				'label' 		=> __( 'Rating Count', 'rom' ),
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
				'label' 		=> __( 'Rating Avg', 'rom' ),
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
				'label'		    => __( 'Youtube URL', 'rom' ),
				'key' 		    => 'youtube_url',
				'name' 		    => 'youtube_url',
				'type' 		    => 'url',
				'wrapper'    	=> array(
					'width'  	=> '25',
				),
			),
			
			array (
				'key' 	=> 'released',
				'label' => __( 'Release Date', 'rom' ),
				'name' 	=> 'released',
				'type' 	=> 'textarea',
				'rows'  => 2,
				'wrapper' 	=> array(
					'width' => '25',
				),
			),

            array(
                'label' => __('Internal Notes', 'rom'),
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
				'label'     => __('Grabber Gallery', 'rom'),
				'type'      => 'group',
				'sub_fields' => array(
					array(
						'key'       => 'grabber_input',
						'name'      => 'grabber_input',
						'type'      => 'text',
						// 'label'     => __('Image URL', 'rom'),
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
						'default_value' => __('Grab Images', 'rom'),
					),
				),
				'wrapper' => array(
					'width' => '100',
				),
			),

            array (
                'key'   		=> 'download_note',
                'label' 		=> __( 'Download note', 'rom' ),
                'name'  		=> 'download_note',
                'type'  		=> 'textarea',
                'rows'          => 5,
            ),

            array (
				'label'		    => __( 'Download list', 'rom' ),
				'key' 		    => 'download_list',
				'name' 		    => 'download_list',
				'type' 		    => 'repeater',
				'layout'	    => 'table',
				'button_label'  => __( 'Add File', 'rom' ),
				'sub_fields'    => array (
					array (
						'label' => __( 'Name', 'rom' ),
						'key' 	=> 'name',
						'name' 	=> 'name',
						'required' => 1,
						'type' 	=> 'text',
                        'wrapper'    => array(
        					'width'  => '45',
        				),
					),
					array (
						'label' => __( 'Url', 'rom' ),
						'key' 	=> 'url',
						'name' 	=> 'url',
						'required' => 1,
						'type' 	=> 'url',
                        'wrapper'    => array(
        					'width'  => '35',
        				),
					),
					array (
						'label' => __( 'File Size', 'rom' ),
						'key' 	=> 'file_size',
						'name' 	=> 'file_size',
						'required' => 0,
						'type' 	=> 'text',
                        'wrapper'    => array(
        					'width'  => '10',
        				),
					),
					array (
						'label' => __( 'File Type', 'rom' ),
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
				'label'		    => __( 'Download List (AFF)', 'rom' ),
				'key' 		    => 'download_list_aff',
				'name' 		    => 'download_list_aff',
				'type' 		    => 'repeater',
				'layout'	    => 'table',
				'button_label'  => __( 'Add', 'rom' ),
				'sub_fields'    => array (
					array (
						'label' => __( 'Name', 'rom' ),
						'key' 	=> 'name',
						'name' 	=> 'name',
						'type' 	=> 'text',
						'required' => 1,
                        'wrapper'    => array(
        					'width'  => '45',
        				),
					),
					array (
						'label' => __( 'Url', 'rom' ),
						'key' 	=> 'url',
						'name' 	=> 'url',
						'type' 	=> 'url',
						'required' => 1,
                        'wrapper'    => array(
        					'width'  => '35',
        				),
					),
					array (
						'label' => __( 'File Size', 'rom' ),
						'key' 	=> 'file_size',
						'name' 	=> 'file_size',
						'type' 	=> 'text',
						'required' => 0,
                        'wrapper'    => array(
        					'width'  => '10',
        				),
					),
					array (
						'label' => __( 'File Type', 'rom' ),
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
				'label' => __( 'Image', 'rom' ),
				'name' 	=> 'cat_image',
				'type' 	=> 'image',
				'preview_size'		 => 'thumbnail',
				'show_column' 		 => 1,
				'show_column_weight' => 10,
				'return_format' => 'url',
			),
			array (
				'key' 	=> 'console_rom_downloads',
				'label' => __( 'Downloads', 'rom' ),
				'name' 	=> 'console_rom_downloads',
				'type' 	=> 'number',
				'default_value'		 => 1,
				'show_column' 		 => 1,
				'show_column_weight' => 520,
			),
			array (
				'key' 	=> 'console_color',
				'label' => __( 'Badge color', 'rom' ),
				'name' 	=> 'console_color',
				'type' 	=> 'color_picker',
				'instructions' => __( 'Background of the console badge on rom cards. Text turns white or dark to match.', 'rom' ),
			),
			array (
				'key' 	=> 'console_guide',
				'label' => __( 'Guide', 'rom' ),
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
				'label' => __( 'Image', 'rom' ),
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
                'label' => __( 'Banner', 'rom' ),
                'name' 	=> 'collection_banner',
                'type' 	=> 'image',
                'instructions' => __( '16:9 ratio, 1280×720 recommended. Other ratios are center-cropped on the card.', 'rom' ),
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
		array( 'thumbnail' => __( 'Image' , 'rom' ) ),
		array_slice( $cols, 1, 2, false ),
		array( 'download_list' => __( 'Download list' , 'rom' ) ),
		array( 'download_list_aff' => __( 'Download List (AFF)' , 'rom' ) ),
		array( 'status' => __( 'Status' , 'rom' ) ),
		array( 'hide' => __( 'Hide' , 'rom' ) ),
		array( 'type' => __( 'Type' , 'rom' ) ),
		array( 'views' => __( 'Views' , 'rom' ) ),
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
	echo '<option value="">' . __('Hide Status', 'rom') . '</option>';
	
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

/**
 * Rom URLs: /roms/{console}/{post_name}/ and the download endpoint on top of it,
 * /roms/{console}/{post_name}/download/[{file_index}]/.
 *
 * post_name alone identifies the rom, so the console segment is never matched: these
 * rules do not depend on the console term list and survive any term change without a
 * flush. A rom reached through the wrong console segment still resolves and
 * redirect_canonical 301s it to its permalink. The lookahead on the single rule keeps
 * WP's own taxonomy endpoints (/roms/{console}/feed/) reachable, and download_file is
 * 1-based - absent means the page lists every file.
 *
 * The dev, pub, language and region archives live under the same prefix (/roms/developer/{term}/,
 * /roms/publisher/{term}/, /roms/language/{term}/, /roms/region/{term}/), so their WP-generated rules are moved ahead of the rom
 * single rule, which would otherwise read the term slug as a rom post_name.
 */
add_filter('rewrite_rules_array', function ($rules) {
	$new_rules = array(
		'roms/[^/]+/([^/]+)/download/([1-9][0-9]*)/?$'    => 'index.php?post_type=rom&name=$matches[1]&download=1&download_file=$matches[2]',
		'roms/[^/]+/([^/]+)/download/?$'             => 'index.php?post_type=rom&name=$matches[1]&download=1',
		'roms/[^/]+/(?!(?:feed|embed)/?$)([^/]+)/?$' => 'index.php?post_type=rom&name=$matches[1]',
	);

	$tax_rules = array();
	foreach ($rules as $regex => $query) {
		if (strpos($regex, 'roms/developer/') === 0 || strpos($regex, 'roms/publisher/') === 0 || strpos($regex, 'roms/language/') === 0 || strpos($regex, 'roms/region/') === 0) {
			$tax_rules[$regex] = $query;
		}
	}

	return $tax_rules + $new_rules + $rules;
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

// Those rules are generated per type term, so a new, renamed or deleted term has no
// root-level rule until the rewrite rules are rebuilt.
add_action( 'created_type', 'flush_rewrite_rules', 10, 0 );
add_action( 'edited_type', 'flush_rewrite_rules', 10, 0 );
add_action( 'delete_type', 'flush_rewrite_rules', 10, 0 );

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

	$console_slug = $terms[0]->slug;

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

	return home_url( 'roms/' . $console_slug . '/' . $post_name . '/' );

}, 10, 4);

// Rewrite term link for type taxonomy to remove 'type' prefix
add_filter('term_link', function ($termlink, $term, $taxonomy) {
	if ($taxonomy === 'type') {
		return home_url('/' . $term->slug . '/');
	}
	return $termlink;
}, 10, 3);

add_action('after_switch_theme', function () {
	flush_rewrite_rules();
});

/**
 * Change hide status
 */
function k_change_hide_status() {
	$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
	
	if (!$post_id) {
		wp_send_json_error(array('message' => __('Invalid post ID', 'rom')));
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
	$bulk_actions['set_hide_public']        = __('Set hide: Public', 'rom');
	$bulk_actions['set_hide_restricted']    = __('Set hide: Restricted', 'rom');
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
		esc_html(_n('%d rom updated.', '%d roms updated.', $count, 'rom')) .
		'</p></div>',
		$count
	);
});
