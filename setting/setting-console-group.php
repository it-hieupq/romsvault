<?php
add_action( 'acf/include_fields', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group( array(
		'qef_simple_location_rules' => 0,
		'key'                       => 'console_group_fields',
		'title'                     => 'Console Group fields',
		'fields'                    => array(
			array(
				'allow_backendsearch'  => false,
				'show_column_filter'   => false,
				'allow_bulkedit'       => false,
				'allow_quickedit'      => false,
				'show_column'          => false,
				'show_column_weight'   => 1000,
				'show_column_sortable' => false,
				'key'                  => 'console_groups',
				'label'                => 'Console Groups',
				'name'                 => 'title',
				'type'                 => 'repeater',
				'layout'               => 'row',
				'button_label'         => 'Add Group',
				'rows_per_page'        => 50,
				'sub_fields'           => array(
					array(
						'show_column_filter'   => false,
						'show_column_weight'   => 1000,
						'show_column_sortable' => false,
						'key'                  => 'console_groups_title',
						'label'                => 'Title',
						'name'                 => 'title',
						'type'                 => 'text',
						'required'             => 1,
						'parent_repeater'      => 'console_groups',
					),
					array(
						'allow_backendsearch'  => false,
						'show_column_filter'   => 0,
						'allow_bulkedit'       => 0,
						'allow_quickedit'      => 0,
						'show_column'          => 0,
						'show_column_weight'   => 1000,
						'show_column_sortable' => false,
						'key'                  => 'console_groups_consoles',
						'label'                => 'Consoles',
						'name'                 => 'consoles',
						'type'                 => 'taxonomy',
						'required'             => 1,
						'taxonomy'             => 'console',
						'return_format'        => 'id',
						'field_type'           => 'multi_select',
						'parent_repeater'      => 'console_groups',
						'hide_empty'           => true,
						'multiple'             => true,
						'ui'                   => 1,
					),
				),
			),
		),
		'location'                  => array(
			array(
				array(
					'param'    => 'options_page',
					'operator' => '==',
					'value'    => 'console-group',
				),
			),
		),
		'position'                  => 'normal',
		'style'                     => 'default',
		'label_placement'           => 'top',
		'instruction_placement'     => 'label',
		'active'                    => true,
	) );
} );

add_action( 'acf/init', function () {
	acf_add_options_page( array(
		'page_title'  => 'Console Group',
		'menu_slug'   => 'console-group',
		'parent_slug' => 'edit.php?post_type=rom',
		'position'    => '',
		'redirect'    => false,
	) );
} );

