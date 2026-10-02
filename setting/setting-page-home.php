<?php
function k_init_page_home() {
	/**
	 * Add home field group
	 */
	if( function_exists('acf_add_local_field_group') ) {
		
		// Page home
		acf_add_local_field_group( array(
			'key'		=> 'home_settings',
			'title' 	=> __( 'Settings', 'romsfun' ),
			'fields' 	=> array (
				array(
					'label' => __( 'Content', 'romsfun' ),
					'name'  => 'home_content',
					'key'   => 'home_content',
					'type'  => 'flexible_content',
					'layouts' => array(

						array(
							'key'           => 'home_console_groups',
							'name'          => 'home_console_groups',
							'label'         => __( 'Console Group', 'romsfun' ),
							'display'       => 'block',
							'sub_fields'    => array(
								array(
									'key'       => 'home_console_groups_title',
									'label'     => __( 'Console groups title', 'romsfun' ),
									'name'      => 'home_console_groups_title',
									'type'      => 'text',
									'required'      => 1,
								),
							),
						),

						array(
							'label'   	 => __( 'Introduction', 'romsfun' ),
							'name'    	 => 'home_intro',
							'key'    	 => 'home_intro',
							'display'    => 'block',
							'sub_fields' => array(
								array (
									'label' 		=> __( 'Title', 'romsfun' ),
									'name' 			=> 'home_intro_title',
									'key' 			=> 'home_intro_title',
									'type' 			=> 'text',
									'required'      => 1,
								),
								array (
									'label' 		=> __( 'Description', 'romsfun' ),
									'name' 			=> 'home_intro_desc',
									'key' 			=> 'home_intro_desc',
									'type' 			=> 'wysiwyg',
									'required'      => 1,
								),
							),
						),

                        array(
                            'label'   	 => __( 'Roms Collection', 'romsfun' ),
                            'name'    	 => 'home_roms_collection',
                            'key'    	 => 'home_roms_collection',
                            'display'    => 'block',
                            'sub_fields' => array(
                                array (
                                    'label'		 	=> __( 'Title', 'romsfun' ),
                                    'name' 		 	=> 'home_roms_collection_title',
                                    'key'   	 	=> 'home_roms_collection_title',
                                    'type' 		 	=> 'text',
                                    'wrapper'		=> array(
                                        'width'		=> '50',
                                    ),
                                    'required'      => 1,
                                ),
								array (
									'label'		 	=> __( 'Collection', 'romsfun' ),
									'name' 		 	=> 'home_roms_collection_items',
									'key'   	 	=> 'home_roms_collection_items',
									'type' 		 	=> 'taxonomy',
									'taxonomy'	 	=> 'collection',
									'wrapper'		=> array(
										'width'		=> '30',
									),
									'required'      => 1,
									'return_format' => 'id',
									'field_type' => 'multi_select',
								),
								array (
									'label'		 	=> __( 'Link', 'romsfun' ),
									'name' 		 	=> 'home_roms_collection_link',
									'key'   	 	=> 'home_roms_collection_link',
									'type' 		 	=> 'link',
									'required'      => 1,
									'wrapper'		=> array(
										'width'		=> '20',
									),
								),
                            ),
							
                        ),

						array(
							'label'   	 => __( 'Roms', 'romsfun' ),
							'name'    	 => 'home_roms',
							'key'    	 => 'home_roms',
							'display'    => 'block',
							'sub_fields' => array(
								array (
								 	'label'		 	=> __( 'Title', 'romsfun' ),
								 	'name' 		 	=> 'home_roms_title',
								 	'key'   	 	=> 'home_roms_title',
									'type' 		 	=> 'text',
									'wrapper'		=> array(
										'width'		=> '50',
									),
								    'required'      => 1,
								),
								array (
									'label'		 	=> __( 'Number', 'romsfun' ),
									'name' 		 	=> 'home_roms_number',
									'key'   	 	=> 'home_roms_number',
									'type' 		 	=> 'number',
									'default_value' => 8,
									'required'      => 1,
									'wrapper'		=> array(
										'width'		=> '16.666',
									),
								),
								array (
								 	'label'		 	=> __( 'Sort', 'romsfun' ),
								 	'name' 		 	=> 'home_roms_sort',
								 	'key'   	 	=> 'home_roms_sort',
									'type' 		 	=> 'select',
								    'required'      => 1,
									'choices'		=> array(
										'latest'	=> 'Latest',
										'popular'	=> 'Popular',
									),
									'wrapper'		=> array(
										'width'		=> '16.666',
									),
								),
								array (
									'label'		 	=> __( 'Layout', 'romsfun' ),
									'name' 		 	=> 'home_roms_layout',
									'key'   	 	=> 'home_roms_layout',
									'type' 		 	=> 'select',
									'choices'		=> array(
										'carousel'	=> 'Carousel',
										'list'	    => 'List',
									),
									'required'      => 1,
									'wrapper'		=> array(
										'width'		=> '16.666',
									),
								),
							),
						),
						array(
							'label'   	 => __( 'Posts', 'romsfun' ),
							'name'    	 => 'home_new_posts',
							'key'    	 => 'home_new_posts',
							'display'    => 'block',
							'sub_fields' => array(
								array (
								 	'label'		 	=> __( 'Title', 'romsfun' ),
								 	'name' 		 	=> 'home_new_posts_title',
								 	'key'   	 	=> 'home_new_posts_title',
									'type' 		 	=> 'text',
									'wrapper'		=> array(
										'width'		=> '25',
									),
								    'required'      => 1,
								),
								array (
								 	'label'		 	=> __( 'Number', 'romsfun' ),
								 	'name' 		 	=> 'home_new_posts_number',
								 	'key'   	 	=> 'home_new_posts_number',
									'type' 		 	=> 'number',
									'default_value' => 10,
									'wrapper'		=> array(
										'width'		=> '25',
									),
								    'required'      => 1,
								),
								array (
								 	'label'		 	=> __( 'Link', 'romsfun' ),
								 	'name' 		 	=> 'home_new_posts_link',
								 	'key'   	 	=> 'home_new_posts_link',
									'type' 		 	=> 'link',
									'wrapper'		=> array(
										'width'		=> '50',
									),
								    'required'      => 1,
								),
							),
						),
					),
					'button_label' => __( 'Add layout', 'romsfun' ),
				),
			),
			'location' => array (
				array (
					array (
						'param'    => 'page_template',
						'operator' => '==',
						'value'    => 'page-home.php',
					),
				),
				array (
					array (
						'param'    => 'page_template',
						'operator' => '==',
						'value'    => 'page-home-2.php',
					),
				),
			),
			'position' => 'acf_after_title',
			'label_placement' => 'top',
			'hide_on_screen' => array (
				
				1 => 'the_content',
			),
		));
	}
}
add_action( 'init', 'k_init_page_home' );

