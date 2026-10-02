<?php
function k_init_post() {
	/**
	 * Add post field group
	 */
	acf_add_local_field_group( array(
		'key'		=> 'post_settings',
		'title'     => __( 'Settings', 'romsfun' ),
		'fields' 	=> array (
			array (
				'key'   		=> 'views',
				'label' 		=> __( 'Views', 'romsfun' ),
				'name'  		=> 'views',
				'type'  		=> 'number',
				'default_value' => 0,
				'step' 			=> 1,
			),
		),
		'location' => array (
			array (
				array (
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'post',
				),
			),
		),
		'position' => 'normal',
		'label_placement' => 'top',
	) );
}
add_action( 'init', 'k_init_post' );

// Add post columns
function k_post_columns_head($cols): array {
	$cols = array_merge(
		array_slice( $cols, 0, 1, true ),
		array( 'thumbnail' => __( 'Image' , 'romsfun' ) ),
		array_slice( $cols, 1, null, true )
	);
	return $cols;
}
function k_post_columns_content($col_name, $post_ID): void {
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
add_filter('manage_post_posts_columns', 'k_post_columns_head');
add_action('manage_post_posts_custom_column', 'k_post_columns_content', 10, 2);