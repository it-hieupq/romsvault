<?php
function willgroup_get_file_init() {
	/**
	 * Create Get File page
	 */
	if( function_exists('acf_add_options_page') ) {
		acf_add_options_page(array(
			'page_title' 	  => 'Get File',
			'menu_title'	  => 'Get File',
			'menu_slug' 	  => 'get_file',
			'icon_url'		  => 'dashicons-search',
			'capability'	  => 'manage_options',
			'id'			  => 'get_file',
			'post_id' 		  => 'get_file',
			'parent'		  => 'edit.php'
		));
	}

	/**
	 * Add get_file field group
	 */
	if ( function_exists('acf_add_local_field_group') ) {
		acf_add_local_field_group(array(
			'location' => array (
				array (
					array (
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => 'get_file',
					),
				),
			),
		));	
	}
}
add_action( 'init', 'willgroup_get_file_init' );

/**
 * Get file
 */
function willgroup_get_file() {
	$url = $_POST['url'];
	$username = $_POST['username'];
	$password = $_POST['password'];
	$ip = $_POST['ip'];
	
	if ( $url == '' ) {
		_e('Please input link file.', 'willgroup');
		exit;
	}
	if ( $username == '' ) {
		_e('Please input username.', 'willgroup');
		exit;
	}
	if ( $password == '' ) {
		_e('Please input password.', 'willgroup');
		exit;
	}
	if ( $ip == '' ) {
		_e('Please input ip.', 'willgroup');
		exit;
	}

	require get_template_directory() . '/inc/simple_html_dom.php';
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_HEADER, 0);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
	curl_setopt($curl, CURLOPT_URL, $url);
	$str = curl_exec($curl);
	$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	curl_close($curl); 
	if ( $httpCode == 200 ) {
		$html = new simple_html_dom();
		$html->load($str);
		$menu = $html->find('#vaultMenu a');
		$html->clear();
		unset($html);
		if ( $menu ) {
			unset($menu[0]);
			foreach ( $menu as $menu_item ) {
				$url = 'https://vimm.net' . $menu_item->href;
				$curl = curl_init();
				curl_setopt($curl, CURLOPT_HEADER, 0);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
				curl_setopt($curl, CURLOPT_URL, $url);
				$str = curl_exec($curl);
				$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
				curl_close($curl); 
				if ( $httpCode == 200 ) {
					$html1 = new simple_html_dom();
					$html1->load($str);
					$games = $html1->find('.hovertable tr');
					if ( $games ) {
						foreach ( $games as $key_game => $game ) {
							$referer = 'https://vimm.net' . $game->find('a', 0)->href;
							$curl = curl_init();
							curl_setopt($curl, CURLOPT_HEADER, 0);
							curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
							curl_setopt($curl, CURLOPT_URL, $referer);
							$str = curl_exec($curl);
							$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
							curl_close($curl); 
							if ( $httpCode == 200 ) {
								$html = new simple_html_dom();
								$html->load($str);
								$url = 'https:' . $html->find('#download_form', 0)->action;
								$url .= '?mediaId=' . $html->find('#download_form [name="mediaId"]', 0)->value;
								$html->clear();
								unset($html);

								$ch = curl_init();
								curl_setopt( $ch, CURLOPT_URL, $url );
								curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
								curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
								curl_setopt( $ch, CURLOPT_HEADER, true );
								curl_setopt( $ch, CURLOPT_NOBODY, true );
								curl_setopt( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:84.0) Gecko/20100101 Firefox/84.0');
								curl_setopt( $ch, CURLOPT_REFERER, $referer );
								curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 20 );
								$response = curl_exec( $ch );
								if ( $response !== false ) {
							   		if ( curl_getinfo($ch, CURLINFO_HTTP_CODE) == '200' ) {
							      		if ( preg_match('~filename=(?|"([^"]*)"|\'([^\']*)\'|([^;]*))~', $response, $match) ) {
							    			$filename = $match[1];
							      		}
							    	}
							  	}
							  	curl_close( $ch );

							  	if ( empty($filename) ) continue;

							  	// Check if a file exists on a remote server
						       	$ftp_conn = ftp_connect($ip, 21) or die('Cannot connect to host');
								ftp_login($ftp_conn, $username, $password) or die('Cannot login');
								ftp_pasv($ftp_conn, true);
						        $contents_on_server = ftp_nlist($ftp_conn, '.'); 
						        ftp_close($ftp_conn);
						        if ( in_array( './' . $filename, $contents_on_server ) ) {
						            continue;
						        }

    							// Upload a file to a remote server
							    $fp = fopen ('ftp://' . $username . ':' . $password . '@' . $ip . '/' . $filename, 'wb');
								$ch = curl_init();
								curl_setopt( $ch, CURLOPT_URL, $url );
								curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
								curl_setopt( $ch, CURLOPT_BINARYTRANSFER, true );
								curl_setopt( $ch, CURLOPT_RETURNTRANSFER, false );
								curl_setopt( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:84.0) Gecko/20100101 Firefox/84.0');
								curl_setopt( $ch, CURLOPT_REFERER, $referer );
								curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 20 );
								curl_setopt( $ch, CURLOPT_TIMEOUT, 30000 );
								curl_setopt( $ch, CURLOPT_FILE, $fp );
								curl_exec( $ch );
								curl_close( $ch );
								fclose( $fp );
							}
						}
					}
					$html1->clear();
					unset($html1);
				}
			}
		}
	}
}
add_action( 'wp_ajax_willgroup_get_file', 'willgroup_get_file' );

/**
 * create an action for your options page that will run before the ACF callback function
 * see above for information on the hook you need to use
 */
add_action('posts_page_get_file', 'willgroup_before_get_file_page', 5);
function willgroup_before_get_file_page() {
	// Before ACF outputs the options page content start an object buffer so that we can capture the output
	ob_start();
}
	
/**
 * create an action for your options page that will run after the ACF callback function
 * see above for information on the hook you need to use
 */
add_action('posts_page_get_file', 'willgroup_after_get_file_page', 20);
function willgroup_after_get_file_page() {
	// After ACF finishes get the output and modify it
	$content = ob_get_clean();
	
	// the number of times we should replace any string
	$count = 1; 
	
	// insert something after tag <div>
	ob_start();
	?>
	<div style="max-width: 500px; margin-left: auto; margin-right: auto;">
		<div style="margin-bottom: 20px;">
			<input style="width: 100%;" type="text" name="url" placeholder="<?php _e('Link file', 'willgroup'); ?>">
		</div>
		<div style="margin-bottom: 20px;">
			<a class="button get-file" style="text-align: center; width: 100%;" data-post_id="<?php echo $row->post_id; ?>" href="javascript:void(0);">
				<?php _e('Get', 'willgroup'); ?>		
			</a>
		</div>
		<div style="margin-bottom: 20px;">
			<input style="width: 100%;" type="text" name="username" placeholder="<?php _e('Username', 'willgroup'); ?>">
		</div>
		<div style="margin-bottom: 20px;">
			<input style="width: 100%;" type="text" name="password" placeholder="<?php _e('Password', 'willgroup'); ?>">
		</div>
		<div style="margin-bottom: 20px;">
			<input style="width: 100%;" type="text" name="ip" placeholder="<?php _e('IP', 'willgroup'); ?>">
		</div>
	</div>
	<style>
		.acf-admin-notice, #postbox-container-1 {
			display: none;
		}
	</style>
	<?php

	$my_content = ob_get_clean();

	$content = str_replace('<div id="postbox-container-2" class="postbox-container">', '<div id="postbox-container-2" class="postbox-container">'.$my_content, $content, $count);
	
	// output the new content
	echo $content;
}