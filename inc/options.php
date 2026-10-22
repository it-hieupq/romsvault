<?php
/**
 * k Theme Options.
 *
 * @package romsvault
 */

/**
 * Register the ACF options page + settings field group on acf/init.
 * Registering at require-time (before the init hook) triggered translated
 * labels to load too early — WP 6.7 _load_textdomain_just_in_time notice
 * for the "acf" and "rom" domains.
 */
add_action( 'acf/init', function () {

/**
 * Create options page
 */
if( function_exists('acf_add_options_page') ) {
	acf_add_options_page(array(
		'page_title' 	=> 'Configs',
		'menu_title'	=> 'Configs',
		'menu_slug' 	=> 'options',
		'capability'	=> 'edit_posts',
		'icon_url' 		=> 'dashicons-wordpress-alt',
		'id'			=> 'option',
		'post_id' 		=> 'option',
		// Report and the member admin pages hang under this menu. Without this,
		// ACF swaps the menu over to its first child page and Configs itself
		// becomes unreachable.
		'redirect'		=> false,
		'position'		=> 3.3, // above Posts (5); grouped under Members (3)
	));
}

/**
 * Add options field group
 */
if( function_exists('acf_add_local_field_group') ) {
	
	acf_add_local_field_group(	array(
		'key'		=> 'options_settings',
		'title' 	=> __( 'Settings', 'rom' ),
		'fields' 	=> array (
			array (
				'key'   		=> 'tab_general',
				'label' 		=> __( 'General', 'rom' ),
				'name'  		=> 'tab_general',
				'type'  		=> 'tab',
				'placement' 	=> 'left',
			),
			array (
				'key'   		=> 'logo',
				'label' 		=> __( 'Logo', 'rom' ),
				'name'  		=> 'logo',
				'type'  		=> 'image',
				'preview_size'  => 'thumbnail',
				'wrapper'		=> array(
					'width'		=> '50%',
				),
				'return_format' => 'url',
			),
			array (
				'key'   		=> 'favicon',
				'label' 		=> __( 'Favicon', 'rom' ),
				'name'  		=> 'favicon',
				'type'  		=> 'image',
				'preview_size'  => 'thumbnail',
				'wrapper'		=> array(
					'width'		=> '50%',
				),
				'return_format' => 'url',
			),
			array (
				'key' 		    => 'social_networks',
				'label'		    => __( 'Mạng xã hội', 'rom' ),
				'name' 		    => 'social_networks',
				'type' 		    => 'repeater',
				'layout'	    => 'table',
				'button_label'  => __( 'Add', 'rom' ),
				'sub_fields'    => array (
					array (
						'key' 	  	   => 'social_network_icon',
						'label'   	   => __( 'Icon', 'rom' ),
						'name' 	  	   => 'social_network_icon',
						'type' 	  	   => 'select',
						'ui' 		   => 1,
						'allow_null'   => 1,
						'choices'	   => k_get_social_icons(),
						'wrapper'      => array(
							'width'    => '25',
						),
					),
                    array (
						'key' 		   => 'social_network_url',
						'label' 	   => __( 'Url', 'rom' ),
						'name' 		   => 'social_network_url',
						'type' 		   => 'url',
						'wrapper'      => array(
							'width'    => '75',
						),
					),
				),
			),
			array (
				'key'   		=> 'copyright',
				'label' 		=> __( 'Copyright', 'rom' ),
				'name'  		=> 'copyright',
				'type'  		=> 'wysiwyg',
			),

            // Live search, read by inc/search-index.php; an empty number falls back to its constant.
            array(
                'key'           => 'search_live_header',
                'label'         => __( 'Header search suggestions', 'rom' ),
                'name'          => 'search_live_header',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 1,
                'wrapper'       => array( 'width' => '50' ),
            ),
            array(
                'key'           => 'search_live_filter',
                'label'         => __( 'Filter bar search suggestions', 'rom' ),
                'name'          => 'search_live_filter',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 1,
                'wrapper'       => array( 'width' => '50' ),
            ),
            array(
                'key'          => 'search_cron_minutes',
                'label'        => __( 'Search index rebuild every (minutes)', 'rom' ),
                'name'         => 'search_cron_minutes',
                'type'         => 'number',
                'min'          => 1,
                'max'          => 1440,
                'placeholder'  => (string) ( ROM_SEARCH_CRON_INTERVAL / MINUTE_IN_SECONDS ),
                'wrapper'      => array( 'width' => '50' ),
            ),
            array(
                'key'          => 'search_build_batch',
                'label'        => __( 'Roms per query during a rebuild', 'rom' ),
                'name'         => 'search_build_batch',
                'type'         => 'number',
                'min'          => 50,
                'max'          => 5000,
                'step'         => 50,
                'placeholder'  => (string) ROM_SEARCH_BUILD_BATCH,
                'wrapper'      => array( 'width' => '50' ),
            ),
            array(
                'key'      => 'search_index_status',
                'label'    => __( 'Search index', 'rom' ),
                'name'     => 'search_index_status',
                'type'     => 'message',
                'message'  => '',
            ),

            // Rendered by tracking/status.php.
            array(
                'key'      => 'tracking_status',
                'label'    => __( 'View & download tracking', 'rom' ),
                'name'     => 'tracking_status',
                'type'     => 'message',
                'message'  => '',
            ),

			array (
				'key'   		=> 'tab_ads',
				'label' 		=> __( 'Ads', 'rom' ),
				'name'  		=> 'tab_ads',
				'type'  		=> 'tab',
				'placement' 	=> 'left',
			),
			array (
				'key' 		    => 'ads_banner',
				'label'		    => __( 'Banner', 'rom' ),
				'name' 		    => 'ads_banner',
				'type' 		    => 'textarea',
				'rows'			=> 10,
			),

            array (
                'key'   		=> 'tab_js',
                'label' 		=> __( 'Header/footer js', 'rom' ),
                'name'  		=> 'tab_js',
                'type'  		=> 'tab',
                'placement' 	=> 'left',
            ),
            array (
                'key' 		    => 'header_js',
                'label'		    => __( 'Header js', 'rom' ),
                'name' 		    => 'header_js',
                'type' 		    => 'textarea',
                'rows'			=> 10,
            ),
            array (
                'key' 		    => 'footer_js',
                'label'		    => __( 'Footer js', 'rom' ),
                'name' 		    => 'footer_js',
                'type' 		    => 'textarea',
                'rows'			=> 10,
            ),

			array (
				'label' 		=> __( 'Aff', 'rom' ),
				'key'   		=> 'tab_affs',
				'name'  		=> 'tab_affs',
				'type'  		=> 'tab',
				'placement' 	=> 'left',
			),
			array (
				'label'		    => __( 'Aff', 'rom' ),
				'key' 		    => 'affs',
				'name' 		    => 'affs',
				'type' 		    => 'repeater',
				'layout'	    => 'table',
				'button_label'  => __( 'Add', 'rom' ),
				'sub_fields'    => array (
					array (
						'label' => __( 'Host', 'rom' ),
						'key' 	=> 'aff_host',
						'name' 	=> 'aff_host',
						'type' 	=> 'text',
					),
                    array (
                    	'label' => __( 'Mã', 'rom' ),
						'key' 	=> 'aff_code',
						'name' 	=> 'aff_code',
						'type' 	=> 'text',
					),
				),
			),

            array(
                'key' => 'tab_page_report',
                'label' => 'Report Issues',
                'name' => 'tab_page_report',
                'type' => 'tab',
                'placement' => 'left',
            ),

            array(
                'key' => 'page_report_issues',
                'label' => 'Issues option',
                'name' => 'page_report_issues',
                'type' => 'repeater',
                'layout' => 'block',
                'button_label' => 'Add',
                'sub_fields' =>
                    array(
                        array(
                            'key' => 'page_report_issue',
                            'label' => 'Issues',
                            'name' => 'page_report_issue',
                            'type' => 'text',
                        ),
                    ),
            ),

            array(
                'key'       => 'tab_homepage',
                'label'     => __( 'Homepage', 'rom' ),
                'name'      => 'tab_homepage',
                'type'      => 'tab',
                'placement' => 'left',
            ),
            array(
                'key'           => 'home_collections',
                'label'         => __( 'Collections', 'rom' ),
                'name'          => 'home_collections',
                'type'          => 'taxonomy',
                'taxonomy'      => 'collection',
                'field_type'    => 'multi_select',
                'allow_null'    => 1,
                'add_term'      => 0,
                'load_terms'    => 0,
                'save_terms'    => 0,
                'return_format' => 'id',
            ),

            array(
                'key'       => 'tab_rom_links',
                'label'     => __( 'ROM Links', 'rom' ),
                'name'      => 'tab_rom_links',
                'type'      => 'tab',
                'placement' => 'left',
            ),
            array(
                'key'           => 'rom_links_console',
                'label'         => __( 'Console', 'rom' ),
                'name'          => 'rom_links_console',
                'type'          => 'taxonomy',
                'taxonomy'      => 'console',
                'field_type'    => 'select',
                'allow_null'    => 1,
                'add_term'      => 0,
                'load_terms'    => 0,
                'save_terms'    => 0,
                'return_format' => 'id',
                'instructions'  => __( 'Chọn console để export tất cả permalinks của ROM gắn với console đó.', 'rom' ),
            ),
            array(
                'key'      => 'rom_links_export_button',
                'label'    => '',
                'name'     => 'rom_links_export_button',
                'type'     => 'message',
                'esc_html' => 0,
                'message'  => '<button type="button" class="button button-primary" id="rom-links-export-btn">Export Links</button> <span id="rom-links-export-status" style="margin-left:10px;"></span>',
            ),
		),
		'location' => array (
			array (
				array (
					'param'    => 'options_page',
					'operator' => '==',
					'value'    => 'options',
				),
			),
		),
	));
}

} );

/**
 * ROM Links export — AJAX handler
 *
 * Query all published ROM posts attached to the selected console term
 * and return the permalinks as plain text (one per line) for the JS
 * client to download as a .md file.
 */
add_action( 'wp_ajax_rom_export_rom_links', function () {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
	}

	$console_id = isset( $_POST['console_id'] ) ? absint( $_POST['console_id'] ) : 0;
	if ( ! $console_id ) {
		wp_send_json_error( array( 'message' => 'Missing or invalid console_id' ) );
	}

	$term = get_term( $console_id, 'console' );
	if ( ! $term || is_wp_error( $term ) ) {
		wp_send_json_error( array( 'message' => 'Console term not found' ) );
	}

	$post_ids = get_posts( array(
		'post_type'      => 'rom',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'tax_query'      => array(
			array(
				'taxonomy' => 'console',
				'field'    => 'term_id',
				'terms'    => $console_id,
			),
		),
	) );

	$lines = array();
	foreach ( $post_ids as $id ) {
		$lines[] = get_permalink( $id );
	}

	wp_send_json_success( array(
		'content'  => implode( "\n", $lines ) . "\n",
		'filename' => 'rom-links-' . $term->slug . '.md',
		'count'    => count( $lines ),
	) );
} );