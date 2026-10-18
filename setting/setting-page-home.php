<?php
function k_init_page_home() {
	/**
	 * Add home field group
	 */
	if( function_exists('acf_add_local_field_group') ) {
		
		// Page home
		acf_add_local_field_group( array(
			'key'		=> 'home_settings',
			'title' 	=> __( 'Settings', 'rom' ),
			'fields' 	=> array (
				array(
					'label' => __( 'Content', 'rom' ),
					'name'  => 'home_content',
					'key'   => 'home_content',
					'type'  => 'flexible_content',
					'layouts' => array(

						array(
							'key'           => 'home_console_groups',
							'name'          => 'home_console_groups',
							'label'         => __( 'Console Group', 'rom' ),
							'display'       => 'block',
							'sub_fields'    => array(
								array(
									'key'       => 'home_console_groups_title',
									'label'     => __( 'Console groups title', 'rom' ),
									'name'      => 'home_console_groups_title',
									'type'      => 'text',
									'required'      => 1,
								),
							),
						),

						array(
							'label'   	 => __( 'Introduction', 'rom' ),
							'name'    	 => 'home_intro',
							'key'    	 => 'home_intro',
							'display'    => 'block',
							'sub_fields' => array(
								array (
									'label' 		=> __( 'Title', 'rom' ),
									'name' 			=> 'home_intro_title',
									'key' 			=> 'home_intro_title',
									'type' 			=> 'text',
									'required'      => 1,
								),
								array (
									'label' 		=> __( 'Description', 'rom' ),
									'name' 			=> 'home_intro_desc',
									'key' 			=> 'home_intro_desc',
									'type' 			=> 'wysiwyg',
									'required'      => 1,
								),
							),
						),

                        array(
                            'label'   	 => __( 'Roms Collection', 'rom' ),
                            'name'    	 => 'home_roms_collection',
                            'key'    	 => 'home_roms_collection',
                            'display'    => 'block',
                            'sub_fields' => array(
                                array (
                                    'label'		 	=> __( 'Title', 'rom' ),
                                    'name' 		 	=> 'home_roms_collection_title',
                                    'key'   	 	=> 'home_roms_collection_title',
                                    'type' 		 	=> 'text',
                                    'wrapper'		=> array(
                                        'width'		=> '50',
                                    ),
                                    'required'      => 1,
                                ),
								array (
									'label'		 	=> __( 'Collection', 'rom' ),
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
									'label'		 	=> __( 'Link', 'rom' ),
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
							'label'   	 => __( 'Roms', 'rom' ),
							'name'    	 => 'home_roms',
							'key'    	 => 'home_roms',
							'display'    => 'block',
							'sub_fields' => array(
								array (
								 	'label'		 	=> __( 'Title', 'rom' ),
								 	'name' 		 	=> 'home_roms_title',
								 	'key'   	 	=> 'home_roms_title',
									'type' 		 	=> 'text',
									'wrapper'		=> array(
										'width'		=> '66.666',
									),
								    'required'      => 1,
								),
								array (
									'label'		 	=> __( 'Number', 'rom' ),
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
								 	'label'		 	=> __( 'Sort', 'rom' ),
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
							),
						),
						array(
							'label'   	 => __( 'Posts', 'rom' ),
							'name'    	 => 'home_new_posts',
							'key'    	 => 'home_new_posts',
							'display'    => 'block',
							'sub_fields' => array(
								array (
								 	'label'		 	=> __( 'Title', 'rom' ),
								 	'name' 		 	=> 'home_new_posts_title',
								 	'key'   	 	=> 'home_new_posts_title',
									'type' 		 	=> 'text',
									'wrapper'		=> array(
										'width'		=> '25',
									),
								    'required'      => 1,
								),
								array (
								 	'label'		 	=> __( 'Number', 'rom' ),
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
								 	'label'		 	=> __( 'Link', 'rom' ),
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
					'button_label' => __( 'Add layout', 'rom' ),
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

