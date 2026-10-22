<?php
/**
 * Truncate text.
 */
function truncate( $text, $chars = 250 ) {
	$text = strip_tags( $text );
	if ( strlen( $text ) > $chars ) {
		$text = $text." ";
		$text = substr($text,0,$chars);
		$text = substr($text,0,strrpos($text,' '));
		$text = $text."...";
	}
    return $text;
}

/**
 * Remove body tag class
 */
add_action( 'body_class', 'k_body_class');
function k_body_class( $classes ) {
    if( in_array('tag', $classes) ) {
        unset( $classes[array_search('tag', $classes)] );
    }
    return $classes;
}

/**
 * Change excerpt more string
 */
function k_excerpt_more( $more ) {
	return '...';
}
add_filter( 'excerpt_more', 'k_excerpt_more' );

/**
 * Change excerpt more string
 */
function k_excerpt_length( $length ) {
    return 44;
}
add_filter( 'excerpt_length', 'k_excerpt_length' );

/**
 * Replace Taxomony Description Field with Visual/WYSIWYG Editor
 */

/**
 * Make links
 */
function k_make_links($text) {  
    return preg_replace('!(((f|ht)tp(s)?://)[-a-zA-Zа-яА-Я()0-9@:%_+.~#?&;//=]+)!i', '<a href="$1" rel="nofollow">$1</a>', $text);         
}

/** 
 * Force noindex download yoast 
 */
add_filter( 'wpseo_robots', 'k_noindex_download', 10, 1 );
function k_noindex_download($str) {
    if ( (isset($_GET['download']) && empty($_GET['download'])) || !empty($_GET['download']) ) {
        return 'noindex,follow';
    }
    return $str;
}

/**
 * Block non-administrators from accessing the WordPress back-end.
 */
function k_block_users_backend() {
    if ( is_user_logged_in() && is_admin() && current_user_can( 'subscriber' ) && ! wp_doing_ajax() ) {
        wp_redirect( home_url() );
        exit;
    } 

    if ( is_user_logged_in() && current_user_can( 'subscriber' ) ) {
        show_admin_bar(false);
    }
}
add_action( 'init', 'k_block_users_backend' );
/** 
 * Add noindex to pagination
 */
function k_add_noindex_to_pagination($str) {
    if ( is_tax('console') ) {
        $paged = ( get_query_var('paged') ) ? get_query_var('paged') : 1;
        if ( $paged > 1 ) {
            return 'noindex,follow';
        }
    }
    return $str;
}
add_filter( 'wpseo_robots', 'k_add_noindex_to_pagination', 10, 1 );

/** 
 * Add noindex to download
 */
function k_add_noindex_to_download($str) {
    global $wp_query;
    if ( ! empty( $wp_query->query_vars['download'] ) ) {
        return 'noindex,follow';
    }
    return $str;
}
add_filter( 'wpseo_robots', 'k_add_noindex_to_download', 10, 1 );

/**
 * Remove canonical from download 
 */
function k_remove_canonical_from_download( $canonical ) {
    if ( get_query_var( 'download' ) ) {
        $canonical = get_permalink( get_queried_object_id() );
    }
    return $canonical;
}
add_filter( 'wpseo_canonical', 'k_remove_canonical_from_download', 99, 1 );

/**
 * Add download title
 */
function k_change_download_title($title) {
    if ( get_query_var( 'download' ) ) {
        $post_id = get_queried_object_id();
        if ( ! $post_id ) {
            return $title;
        }

        if ( get_field('download_title', $post_id) ) {
            $title = get_field('download_title', $post_id);
        } else {
            $title = get_field('_yoast_wpseo_title', $post_id);
            $post_title = get_the_title($post_id);
            if ($title) {
	            $title = str_replace('%%title%%', $post_title, $title);
	            $title = str_replace('%%ct_console%%', '', $title);
	            $title = __('Download', 'rom') . ' ' . $title;
            } else {
                $title = __('Download', 'rom') . ' ' . $post_title;
            }
        }
    }
    return $title;
}
add_filter('wpseo_title', 'k_change_download_title');

/**
 * Add download description
 */
function k_change_download_desc($desc) {
    if ( get_query_var( 'download' ) ) {
        $custom_desc = get_field('_yoast_wpseo_metadesc', get_queried_object_id());
        if ( ! empty( $custom_desc ) ) {
            $desc = $custom_desc;
        }
    }
    return $desc;
}
add_filter('wpseo_metadesc', 'k_change_download_desc');

/**
 * Query vars of the download endpoint. The rules that set them live next to the single
 * rule of each post type (setting/setting-rom.php, setting/setting-emulator.php):
 * /roms/{console}/{post_name}/download/[{file_index}]/ resolves the post itself and
 * adds download=1, plus download_file for a single file (1-based).
 */
add_filter( 'query_vars', function ( $vars ) {
    $vars[] = 'download';
    $vars[] = 'download_file';
    return $vars;
} );

/**
 * The download endpoint resolves to its own rom/emulator, so canonical redirection
 * would send /.../download/ back to the post permalink; the member pages are query-var
 * routes with no post behind them at all. Cancel it for those two only: everywhere else
 * WP keeps normalising URLs (trailing slash, a rom reached through the wrong console
 * segment, ...).
 */
add_filter( 'redirect_canonical', function ( $redirect_url ) {
    if ( get_query_var( 'download' ) || get_query_var( 'rf_page' ) ) {
        return false;
    }
    return $redirect_url;
} );

function k_download_template_redirect() {
    // A slug that matches no post keeps WP's own 404 instead of the download page.
    if ( ! get_query_var( 'download' ) || is_404() ) {
        return;
    }

    require get_template_directory() . '/download.php';
    exit;
}
add_action( 'template_redirect', 'k_download_template_redirect' );

add_filter( 'wp_unique_post_slug_is_bad_attachment_slug', '__return_true' );

/**
 * Get social icons
 */
function k_get_social_icons() {
    return array(
        ''            => __('Select Icon', 'rom'),
        'facebook'    => '<svg class="facebook w-5 h-5" fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path d="M279.14 288l14.22-92.66h-88.91v-60.13c0-25.35 12.42-50.06 52.24-50.06h40.42V6.26S260.43 0 225.36 0c-73.22 0-121.08 44.38-121.08 124.72v70.62H22.89V288h81.39v224h100.17V288z"/></svg>',
        'telegram'    => '<svg class="telegram w-5 h-5" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path fill-rule="evenodd" clip-rule="evenodd" d="M23.1117 4.49449C23.4296 2.94472 21.9074 1.65683 20.4317 2.227L2.3425 9.21601C0.694517 9.85273 0.621087 12.1572 2.22518 12.8975L6.1645 14.7157L8.03849 21.2746C8.13583 21.6153 8.40618 21.8791 8.74917 21.968C9.09216 22.0568 9.45658 21.9576 9.70712 21.707L12.5938 18.8203L16.6375 21.8531C17.8113 22.7334 19.5019 22.0922 19.7967 20.6549L23.1117 4.49449ZM3.0633 11.0816L21.1525 4.0926L17.8375 20.2531L13.1 16.6999C12.7019 16.4013 12.1448 16.4409 11.7929 16.7928L10.5565 18.0292L10.928 15.9861L18.2071 8.70703C18.5614 8.35278 18.5988 7.79106 18.2947 7.39293C17.9906 6.99479 17.4389 6.88312 17.0039 7.13168L6.95124 12.876L3.0633 11.0816ZM8.17695 14.4791L8.78333 16.6015L9.01614 15.321C9.05253 15.1209 9.14908 14.9366 9.29291 14.7928L11.5128 12.573L8.17695 14.4791Z"></path> </g></svg>',
        'twitter'     => '<svg class="twitter w-5 h-5" fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M459.37 151.716c.325 4.548.325 9.097.325 13.645 0 138.72-105.583 298.558-298.558 298.558-59.452 0-114.68-17.219-161.137-47.106 8.447.974 16.568 1.299 25.34 1.299 49.055 0 94.213-16.568 130.274-44.832-46.132-.975-84.792-31.188-98.112-72.772 6.498.974 12.995 1.624 19.818 1.624 9.421 0 18.843-1.3 27.614-3.573-48.081-9.747-84.143-51.98-84.143-102.985v-1.299c13.969 7.797 30.214 12.67 47.431 13.319-28.264-18.843-46.781-51.005-46.781-87.391 0-19.492 5.197-37.36 14.294-52.954 51.655 63.675 129.3 105.258 216.365 109.807-1.624-7.797-2.599-15.918-2.599-24.04 0-57.828 46.782-104.934 104.934-104.934 30.213 0 57.502 12.67 76.67 33.137 23.715-4.548 46.456-13.32 66.599-25.34-7.798 24.366-24.366 44.833-46.132 57.827 21.117-2.273 41.584-8.122 60.426-16.243-14.292 20.791-32.161 39.308-52.628 54.253z"/></svg>',
        'instagram'   => '<svg class="instagram w-5 h-5" fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37-2.1-147.9-2.1-184.9 0-35.8 1.7-67.6 9.9-93.9 36.1s-34.4 58-36.2 93.9c-2.1 37-2.1 147.9 0 184.9 1.7 35.9 9.9 67.7 36.2 93.9s58 34.4 93.9 36.2c37 2.1 147.9 2.1 184.9 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37 2.1-147.8 0-184.8zM398.8 388c-7.8 19.6-22.9 34.7-42.6 42.6-29.5 11.7-99.5 9-132.1 9s-102.7 2.6-132.1-9c-19.6-7.8-34.7-22.9-42.6-42.6-11.7-29.5-9-99.5-9-132.1s-2.6-102.7 9-132.1c7.8-19.6 22.9-34.7 42.6-42.6 29.5-11.7 99.5-9 132.1-9s102.7-2.6 132.1 9c19.6 7.8 34.7 22.9 42.6 42.6 11.7 29.5 9 99.5 9 132.1s2.7 102.7-9 132.1z"/></svg>',
        'youtube'     => '<svg class="youtube w-5 h-5" fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path d="M549.655 124.083c-6.281-23.65-24.787-42.276-48.284-48.597C458.781 64 288 64 288 64S117.22 64 74.629 75.486c-23.497 6.322-42.003 24.947-48.284 48.597-11.412 42.867-11.412 132.305-11.412 132.305s0 89.438 11.412 132.305c6.281 23.65 24.787 41.5 48.284 47.821C117.22 448 288 448 288 448s170.78 0 213.371-11.486c23.497-6.321 42.003-24.171 48.284-47.821 11.412-42.867 11.412-132.305 11.412-132.305s0-89.438-11.412-132.305zm-317.51 213.508V175.185l142.739 81.205-142.739 81.201z"/></svg>',
        'linkedin'    => '<svg class="linkedin w-5 h-5" fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M100.28 448H7.4V148.9h92.88zM53.79 108.1C24.09 108.1 0 83.5 0 53.8a53.79 53.79 0 0 1 107.58 0c0 29.7-24.1 54.3-53.79 54.3zM447.9 448h-92.68V302.4c0-34.7-.7-79.2-48.29-79.2-48.29 0-55.69 37.7-55.69 76.7V448h-92.78V148.9h89.08v40.8h1.3c12.4-23.5 42.69-48.3 87.88-48.3 94 0 111.28 61.9 111.28 142.3V448z"/></svg>',
        'pinterest'   => '<svg class="pinterest w-5 h-5" fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M204 6.5C101.4 6.5 0 74.9 0 185.6 0 256 39.6 296 63.6 296c9.9 0 15.6-27.6 15.6-35.4 0-9.3-23.7-29.1-23.7-67.8 0-80.4 61.2-137.4 140.4-137.4 68.1 0 118.5 38.7 118.5 109.8 0 53.1-21.3 152.7-90.3 152.7-24.9 0-46.2-18-46.2-43.8 0-37.8 26.4-74.4 26.4-113.4 0-66.2-93.9-54.2-93.9 25.8 0 16.8 2.1 35.4 9.6 50.7-13.8 59.4-42 147.9-42 209.1 0 18.9 2.7 37.5 4.5 56.4 3.4 3.8 1.7 3.4 6.9 1.5 50.4-69 48.6-82.5 71.4-172.8 12.3 23.4 44.1 36 69.3 36 106.2 0 153.9-103.5 153.9-196.8C384 71.3 298.2 6.5 204 6.5z"/></svg>',
        'reddit'      => '<svg class="reddit w-5 h-5" fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M201.5 305.5c-13.8 0-24.9-11.1-24.9-24.6 0-13.8 11.1-24.9 24.9-24.9 13.6 0 24.6 11.1 24.6 24.9 0 13.6-11.1 24.6-24.6 24.6zM504 256c0 137-111 248-248 248S8 393 8 256 119 8 256 8s248 111 248 248zm-132.3-41.2c-9.4 0-17.7 3.9-23.8 10-22.4-15.5-52.6-25.5-86.1-26.6l17.4-78.3 55.4 12.5c0 13.6 11.1 24.6 24.6 24.6 13.8 0 24.9-11.3 24.9-24.9s-11.1-24.9-24.9-24.9c-9.7 0-18 5.8-22.1 13.8l-61.2-13.6c-3-.8-6.1 1.4-6.9 4.4l-19.1 86.4c-33.2 1.4-63.1 11.3-85.5 26.8-6.1-6.4-14.7-10.2-24.1-10.2-34.9 0-46.3 46.9-14.4 62.8-1.1 5-1.7 10.2-1.7 15.5 0 52.6 59.2 95.2 132 95.2 73.1 0 132.3-42.6 132.3-95.2 0-5.3-.6-10.8-1.9-15.8 31.3-16 19.8-62.5-14.9-62.5zM302.8 331c-18.2 18.2-76.1 17.9-93.6 0-2.2-2.2-6.1-2.2-8.3 0-2.5 2.5-2.5 6.4 0 8.6 22.8 22.8 87.3 22.8 110.2 0 2.5-2.2 2.5-6.1 0-8.6-2.2-2.2-6.1-2.2-8.3 0zm7.7-75c-13.6 0-24.6 11.1-24.6 24.9 0 13.6 11.1 24.6 24.6 24.6 13.8 0 24.9-11.1 24.9-24.6 0-13.8-11-24.9-24.9-24.9z"/></svg>',
        'flickr'      => '<svg class="flickr w-5 h-5" fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M400 32H48C21.5 32 0 53.5 0 80v352c0 26.5 21.5 48 48 48h352c26.5 0 48-21.5 48-48V80c0-26.5-21.5-48-48-48zM144.5 319c-35.1 0-63.5-28.4-63.5-63.5s28.4-63.5 63.5-63.5 63.5 28.4 63.5 63.5-28.4 63.5-63.5 63.5zm159 0c-35.1 0-63.5-28.4-63.5-63.5s28.4-63.5 63.5-63.5 63.5 28.4 63.5 63.5-28.4 63.5-63.5 63.5z"/></svg>',
    );
}

/**
 * Create the filter dropdown 
 */
function k_admin_posts_filter_restrict_manage_posts() {
    global $typenow;
    
    // Get post type from current screen or GET parameter
    $type = isset($_GET['post_type']) ? $_GET['post_type'] : (isset($typenow) ? $typenow : 'rom');
    
    // Only show filters for 'rom' post type
    if ( 'rom' == $type ) {
    	$terms = get_terms(array('taxonomy' => 'console', 'hide_empty' => false));
        ?>
        <select name="console" style="width: 90px;">
	        <option value=""><?php _e('Console', 'rom'); ?></option>
	        <?php
	            $current_v = isset($_GET['console'])? $_GET['console']:'';
	            foreach ($terms as $term) {
	                printf
	                    (
	                        '<option value="%s"%s>%s</option>',
	                        $term->slug,
	                        $term->slug == $current_v? ' selected="selected"':'',
	                        $term->name
	                    );
	                }
	        ?>

        </select>


<?php
     $terms = get_terms(array('taxonomy' => 'type', 'hide_empty' => false));
        ?>
        <select name="type" style="width: 90px;">
	        <option value=""><?php _e('Type', 'rom'); ?></option>
	        <?php
	            $current_v = isset($_GET['type'])? $_GET['type']:'';
	            foreach ($terms as $term) {
	                printf
	                    (
	                        '<option value="%s"%s>%s</option>',
	                        $term->slug,
	                        $term->slug == $current_v? ' selected="selected"':'',
	                        $term->name
	                    );
	                }
	        ?>

        </select>



        <?php
		
		
	// Trường nhập liệu tìm kiếm từ khóa trong Download Links & Note
?>
<input type="text" name="download_keyword" placeholder="<?php _e('Search keyword in links', 'rom'); ?>" value="<?php echo isset($_GET['download_keyword']) ? esc_attr(sanitize_text_field($_GET['download_keyword'])) : ''; ?>" />

<input type="text" name="note_download" placeholder="<?php _e('Search keyword in File Type', 'rom'); ?>" value="<?php echo isset($_GET['note_download']) ? esc_attr(sanitize_text_field($_GET['note_download'])) : ''; ?>" />



<?php
		
		// Lọc download list có hay không 

        $values = array(
            'Exists'     => 'EXISTS', 
            'Not Exists' => 'NOT EXISTS',
        );
        ?>
        <select name="download_links">
	        <option value=""><?php _e('Download Links', 'rom'); ?></option>
	        <?php
	            $current_v = isset($_GET['download_links'])? $_GET['download_links']:'';
	            foreach ($values as $label => $value) {
	                printf
	                    (
	                        '<option value="%s"%s>%s</option>',
	                        $value,
	                        $value == $current_v? ' selected="selected"':'',
	                        $label
	                    );
	                }
	        ?>
        </select>
        <?php
		
		// Lọc status

        $values = array(
            'Draft'   => 'draft', 
            'Publish' => 'publish',
        );
        ?>
        <select name="status">
	        <option value=""><?php _e('Status', 'rom'); ?></option>
	        <?php
	            $current_v = isset($_GET['status'])? $_GET['status']:'';
	            foreach ($values as $label => $value) {
	                printf
	                    (
	                        '<option value="%s"%s>%s</option>',
	                        $value,
	                        $value == $current_v? ' selected="selected"':'',
	                        $label
	                    );
	                }
	        ?>
        </select>
        <?php
    }
    
    // Filters for 'emulator' post type
    if ( 'emulator' == $type ) {
    	// Emulator Console filter
    	$terms = get_terms(array('taxonomy' => 'emulator_console', 'hide_empty' => false));
        ?>
        <select name="emulator_console" style="width: 90px;">
	        <option value=""><?php _e('Console', 'rom'); ?></option>
	        <?php
	            $current_v = isset($_GET['emulator_console'])? $_GET['emulator_console']:'';
	            foreach ($terms as $term) {
	                printf
	                    (
	                        '<option value="%s"%s>%s</option>',
	                        $term->slug,
	                        $term->slug == $current_v? ' selected="selected"':'',
	                        $term->name
	                    );
	                }
	        ?>
        </select>

<?php
    	// OS filter
    	$terms = get_terms(array('taxonomy' => 'os', 'hide_empty' => false));
        ?>
        <select name="os" style="width: 90px;">
	        <option value=""><?php _e('OS', 'rom'); ?></option>
	        <?php
	            $current_v = isset($_GET['os'])? $_GET['os']:'';
	            foreach ($terms as $term) {
	                printf
	                    (
	                        '<option value="%s"%s>%s</option>',
	                        $term->slug,
	                        $term->slug == $current_v? ' selected="selected"':'',
	                        $term->name
	                    );
	                }
	        ?>
        </select>
        <?php
    }
}
add_action( 'restrict_manage_posts', 'k_admin_posts_filter_restrict_manage_posts' );

/**
 * Filter
 */
function k_admin_posts_filter( $query ){
    global $pagenow, $typenow;
    
    // Get post type from current screen or GET parameter
    $type = isset($_GET['post_type']) ? $_GET['post_type'] : (isset($typenow) ? $typenow : 'rom');

    if ( 'rom' == $type && is_admin() && $pagenow=='edit.php' ) {
    	if ( isset($_GET['download_links']) && $_GET['download_links'] != '' ) {
	        $query->query_vars['meta_query'][] = array(
	        	'key'     => 'download_list_0_url',
	        	'compare' => $_GET['download_links']
	        );
    	}

		
		// Lọc theo từ khóa tùy chỉnh trong Download Links
if (isset($_GET['download_keyword']) && $_GET['download_keyword'] != '') {
    $keyword = sanitize_text_field($_GET['download_keyword']); // Từ khóa người dùng nhập
    $compare = 'LIKE'; // Mặc định là LIKE

    $meta_query = array('relation' => 'OR');
    for ($i = 0; $i < 99; $i++) {
        $meta_query[] = array(
            'key'     => 'download_list_' . $i . '_url',
            'value'   => $keyword,
            'compare' => $compare,
        );
    }
    $query->query_vars['meta_query'][] = $meta_query;
}
		
		
			// Lọc theo từ khóa tùy chỉnh trong Note Download
if (isset($_GET['note_download']) && $_GET['note_download'] != '') {
    $keyword = sanitize_text_field($_GET['note_download']); // Từ khóa người dùng nhập
    $compare = 'LIKE'; // Mặc định là LIKE

    $meta_query = array('relation' => 'OR');
    for ($i = 0; $i < 99; $i++) {
        $meta_query[] = array(
            'key'     => 'download_list_' . $i . '_file_type',
            'value'   => $keyword,
            'compare' => $compare,
        );
    }
    $query->query_vars['meta_query'][] = $meta_query;
}
		
		
		// Lọc theo statsus
    	if ( isset($_GET['status']) && $_GET['status'] != '' ) {
	        $query->query_vars['post_status'] = $_GET['status'];
    	}
    }
    
    // Filters for 'emulator' post type
    if ( 'emulator' == $type && is_admin() && $pagenow=='edit.php' ) {
    	// Initialize tax_query if not exists
    	if ( !isset($query->query_vars['tax_query']) || !is_array($query->query_vars['tax_query']) ) {
    		$query->query_vars['tax_query'] = array();
    	}
    	
    	// Filter by emulator_console taxonomy
    	if ( isset($_GET['emulator_console']) && $_GET['emulator_console'] != '' ) {
	        $query->query_vars['tax_query'][] = array(
	        	'taxonomy' => 'emulator_console',
	        	'field'    => 'slug',
	        	'terms'    => sanitize_text_field($_GET['emulator_console'])
	        );
    	}
    	
    	// Filter by os taxonomy
    	if ( isset($_GET['os']) && $_GET['os'] != '' ) {
	        $query->query_vars['tax_query'][] = array(
	        	'taxonomy' => 'os',
	        	'field'    => 'slug',
	        	'terms'    => sanitize_text_field($_GET['os'])
	        );
    	}
    }
}
add_filter( 'parse_query', 'k_admin_posts_filter' );

/**
 * Query posts where
 */
function willgroup_posts_where( $where, $query ) {
    global $wpdb;

    $post_title = esc_sql( $query->get( 'post_title' ) );

    if ( $post_title ) {
        $where .= " AND $wpdb->posts.post_title LIKE '%$post_title%'";
    }

    return $where;
}
add_filter( 'posts_where', 'willgroup_posts_where', 10, 2 );

function rom_exists($title, $console_id)
{

    global $wpdb;

    $post_title = wp_unslash(sanitize_post_field('post_title', $title, 0, 'db'));
    $post_type = wp_unslash(sanitize_post_field('post_type', 'rom', 0, 'db'));

    $query = "SELECT id,post_title FROM $wpdb->posts WHERE 1=1";
    $args = array();

    if (!empty($title)) {
        $query .= ' AND post_title = %s';
        $args[] = $post_title;
    }

    if (!empty($type)) {
        $query .= ' AND post_type = %s';
        $args[] = $post_type;
    }

    if (!empty($args)) {

        $result = $wpdb->get_results($wpdb->prepare($query, $args));

        foreach ($result as $item) {

            $post_id = $item->id;

            $terms = wp_get_post_terms($post_id, 'console');
            foreach ($terms as $term) {
                if ($term->term_id == $console_id) {
                    return $post_id;
                }
            }

        }
    }

    return 0;

}

add_action( 'template_redirect', 'handle_hide_urls' );
function handle_hide_urls() {

    if ( ! is_single() ) {
        return;
    }

    // Editor shortcut: ?hide=1 on a single flips it to restricted.
    if ( isset( $_GET['hide'] ) && $_GET['hide'] == 1 && current_user_can( 'edit_post', get_the_ID() ) ) {
        update_field( 'hide', 'restricted', get_the_ID() );
    }
}

// Find "Hack" - /wp-admin/edit.php?post_type=rom&find_hack=1
// 
function custom_admin_posts_query($query) {

    if (is_admin() && $query->is_main_query() && $query->get('post_type') === 'rom') {

        $meta_query = array('relation' => 'OR');

        for ($i = 0; $i < 99; $i++) {
            $meta_query[] = array(
                'key' => 'download_list_' . $i . '_url',
                'value' => 'Aftermarket',
                'compare' => 'LIKE',
            );
        }

        if (isset($_GET['c']) && $_GET['c'] == 1) {
            $query->set('meta_query', $meta_query);
        }
    }
}
add_action('parse_query', 'custom_admin_posts_query');

// Replace link

function acf_update_value_for_dynamic_download_urls($value, $post_id, $field, $original) {
    if (is_string($value) && strpos($field['name'], 'download_list_') === 0 && substr($field['name'], -4) === '_url') {
        $pattern = '/https:\/\/drive-admin\.kennychipheo\.workers\.dev/';
        $replacement = 'https://sto1.romsforever.co';
        $value = preg_replace($pattern, $replacement, $value);
    }
    return $value;
}
add_filter('acf/update_value', 'acf_update_value_for_dynamic_download_urls', 10, 4);

/**
 * Build ROMs query (no cache, optimized queries)
 * 
 * @param array $query_args Query arguments
 * @param string $context Context: 'browse' or 'console'
 * @return WP_Query Query object
 */
function k_build_roms_query($query_args, string $context = 'browse') {
	$context = sanitize_key($context);
	
	// Extract parameters
	$console_id = isset($query_args['console_id']) ? absint($query_args['console_id']) : 0;
	$type_id = isset($query_args['type_id']) ? absint($query_args['type_id']) : 0;
	$collection_id = isset($query_args['collection_id']) ? absint($query_args['collection_id']) : 0;
	$filters = isset($query_args['filters']) ? $query_args['filters'] : array();
	$sort = isset($query_args['sort']) ? sanitize_text_field($query_args['sort']) : '';
	$paged = isset($query_args['paged']) ? absint($query_args['paged']) : 1;

	// Build query from args
	$args = array(
		'post_type' => 'rom',
		'post_status'    => 'publish',
		'posts_per_page' => ROM_ARCHIVE_PER_PAGE,
		'paged' => $paged,
        'update_post_meta_cache' => true,
        'update_post_term_cache' => true ,
        'no_found_rows' => false,
        'cache_results' => true,
	);
	
	// Add console filter if context is console
	if ($context === 'console' && $console_id > 0) {
		$args['tax_query'] = array(
			'relation' => 'AND',
			array(
				'taxonomy' => 'console',
				'field' => 'term_id',
				'terms' => $console_id,
			),
		);
	}
	
	// Add type filter if context is type
	if ($context === 'type' && $type_id > 0) {
		$args['tax_query'] = array(
			'relation' => 'AND',
			array(
				'taxonomy' => 'type',
				'field' => 'term_id',
				'terms' => $type_id,
			),
		);
	}
	
	// Add collection filter if context is collection
	if ($context === 'collection' && $collection_id > 0) {
		$args['tax_query'] = array(
			'relation' => 'AND',
			array(
				'taxonomy' => 'collection',
				'field' => 'term_id',
				'terms' => $collection_id,
			),
		);
	}
	
	if (!empty($filters['search'])) {
		$args['s'] = $filters['search'];
	}
	
	// Add tax queries
	$tax_query = isset($args['tax_query']) ? $args['tax_query'] : array('relation' => 'AND');
	
	if (!empty($filters['genres'])) {
		$tax_query[] = array(
			'taxonomy' => 'genre',
			'field' => 'term_id',
			'terms' => $filters['genres'],
			'operator' => 'IN',
		);
	}
	if (!empty($filters['collections']) && $context !== 'collection') {
		$tax_query[] = array(
			'taxonomy' => 'collection',
			'field' => 'term_id',
			'terms' => $filters['collections'],
			'operator' => 'IN',
		);
	}
	if (!empty($filters['types']) && $context !== 'type') {
		$tax_query[] = array(
			'taxonomy' => 'type',
			'field' => 'term_id',
			'terms' => $filters['types'],
			'operator' => 'IN',
		);
	}
	if (!empty($filters['consoles']) && $context !== 'console') {
		$tax_query[] = array(
			'taxonomy' => 'console',
			'field' => 'term_id',
			'terms' => $filters['consoles'],
			'operator' => 'IN',
		);
	}
	
	if (count($tax_query) > 1) {
		$args['tax_query'] = $tax_query;
	}
	
	// Add sorting
	if (!empty($sort)) {
		switch ($sort) {
			case 'newest':
				$args['orderby'] = 'date';
				$args['order'] = 'DESC';
				break;
			case 'oldest':
				$args['orderby'] = 'date';
				$args['order'] = 'ASC';
				break;
			case 'popular':
				$args['orderby'] = 'meta_value_num';
				$args['meta_key'] = 'views';
				$args['order'] = 'DESC';
				break;
			case 'downloads':
				$args['orderby'] = 'meta_value_num';
				$args['meta_key'] = 'downloads';
				$args['order'] = 'DESC';
				break;
			case 'alphabetical':
				$args['orderby'] = 'title';
				$args['order'] = 'ASC';
				break;
			case 'latest':
				$args['orderby'] = 'modified';
				$args['order'] = 'DESC';
				break;
		}
	} elseif($context === 'console' || $context === 'type' || $context === 'collection') {
        // default, tax console, type or collection => sort by download
        $args['orderby'] = 'meta_value_num';
        $args['meta_key'] = 'downloads';
        $args['order'] = 'DESC';
    }

    return new WP_Query($args);
}

/**
 * Get parent comments with pagination (no cache)
 * 
 * @param int $post_id Post ID
 * @param int $page Current page number
 * @param int $per_page Comments per page
 * @return array Array with 'comments' and 'total' count
 */
function k_get_parent_comments($post_id, $page = 1, $per_page = 10) {
	$post_id = absint($post_id);
	$page = absint($page);
	$per_page = absint($per_page);
	
	// Calculate offset
	$offset = ($page - 1) * $per_page;
	
	// Query parent comments
	$args = array(
		'status'  => 'approve',
		'post_id' => $post_id,
		'parent'  => 0,
		'number'  => $per_page,
		'offset'  => $offset,
		'orderby' => 'comment_date',
		'order'   => 'DESC',
		'type'    => 'comment',
	);
	
	$comments_query = new WP_Comment_Query;
	$comments = $comments_query->query($args);
	
	// Get total count of parent comments for pagination
	$count_args = array(
		'status'  => 'approve',
		'post_id' => $post_id,
		'parent'  => 0,
		'count'   => true,
		'type'    => 'comment',
	);
	$total_comments = get_comments($count_args);
	
	// Ensure it's an integer
	$total_comments = absint($total_comments);
	
	return array(
		'comments' => $comments ? $comments : array(),
		'total'    => $total_comments,
	);
}

/**
 * Recursively get all descendant comments of given parent comment IDs
 * Fetches all nested replies at any depth (no cache)
 * 
 * @param int $post_id Post ID
 * @param array $parent_ids Array of parent comment IDs
 * @return array Flat array of all descendant comments (all levels)
 */
function k_get_all_descendant_comments($post_id, $parent_ids) {
	$post_id = absint($post_id);
	if (empty($parent_ids) || !is_array($parent_ids)) {
		return array();
	}
	
	// Sanitize parent IDs
	$parent_ids = array_map('absint', $parent_ids);
	$parent_ids = array_filter($parent_ids);
	
	if (empty($parent_ids)) {
		return array();
	}
	
	$all_descendants = array();
	$ids_to_fetch = $parent_ids;
	
	// Recursively fetch all descendants
	while (!empty($ids_to_fetch)) {
		// Fetch direct children of current level
		$args = array(
			'status'    => 'approve',
			'post_id'   => $post_id,
			'parent__in' => $ids_to_fetch,
			'orderby'   => 'comment_date',
			'order'     => 'ASC',
			'type'      => 'comment',
		);
		
		$comments_query = new WP_Comment_Query;
		$children = $comments_query->query($args);
		
		if (empty($children)) {
			break; // No more descendants
		}
		
		// Add to all descendants array
		$all_descendants = array_merge($all_descendants, $children);
		
		// Get IDs of children to fetch their descendants in next iteration
		$ids_to_fetch = array();
		foreach ($children as $child) {
			$ids_to_fetch[] = $child->comment_ID;
		}
	}
	
	return $all_descendants;
}

/**
 * Comment pagination function
 * 
 * @param int $total Total number of comments
 * @param int $per_page Comments per page
 * @param int $current_page Current page number
 * @param string $base_url Base URL for pagination links
 */
function k_comment_pagination($total, $per_page, $current_page, $base_url = '') {
	$total = absint($total);
	$per_page = absint($per_page);
	$current_page = absint($current_page);
	
	if ($per_page <= 0) {
		$per_page = 10;
	}
	
	$total_pages = ceil($total / $per_page);
	
	if ($total_pages <= 1) {
		return;
	}
	
	// Get current URL if base_url not provided
	if (empty($base_url)) {
		global $wp;
		$base_url = home_url(add_query_arg(array(), $wp->request));
	}
	
	// Ensure base_url ends without query string for clean pagination
	$base_url = remove_query_arg('cpage', $base_url);
	
	$range = 2;
	$showitems = ($range * 2) + 1;
	
	echo "<nav class='nav-pagination flex justify-center mt-8 mb-4'><ul class='flex items-center justify-center gap-2 flex-wrap'>";
	
	// First page link
	if ($current_page > 2 && $current_page > $range + 1 && $showitems < $total_pages) {
		$first_url = add_query_arg('cpage', 1, $base_url) . '#comments';
		echo '<li><a class="flex items-center justify-center w-8 h-8 rounded-full border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 hover:border-gray-400 transition-colors duration-200" href="' . esc_url($first_url) . '" aria-label="First page"><svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor"><path d="M390.3 473.9L180.9 264.5c-4.7-4.7-4.7-12.3 0-17L390.3 38.1c4.7-4.7 12.3-4.7 17 0l19.8 19.8c4.7 4.7 4.7 12.3 0 17L246.4 256l180.7 181.1c4.7 4.7 4.7 12.3 0 17l-19.8 19.8c-4.7 4.7-12.3 4.7-17 0zm-143 0l19.8-19.8c4.7-4.7 4.7-12.3 0-17L86.4 256 267.1 74.9c4.7-4.7 4.7-12.3 0-17l-19.8-19.8c-4.7-4.7-12.3-4.7-17 0L20.9 247.5c-4.7 4.7-4.7 12.3 0 17l209.4 209.4c4.7 4.7 12.3 4.7 17 0z"/></svg></a></li>';
	}
	
	// Previous page link
	if ($current_page > 1 && $showitems < $total_pages) {
		$prev_url = add_query_arg('cpage', $current_page - 1, $base_url) . '#comments';
		echo '<li><a class="flex items-center justify-center w-8 h-8 rounded-full border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 hover:border-gray-400 transition-colors duration-200" aria-label="Previous" href="' . esc_url($prev_url) . '"><svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 512" fill="currentColor"><path d="M231.293 473.899l19.799-19.799c4.686-4.686 4.686-12.284 0-16.971L70.393 256 251.092 74.87c4.686-4.686 4.686-12.284 0-16.971L231.293 38.1c-4.686-4.686-12.284-4.686-16.971 0L4.908 247.515c-4.686 4.686-4.686 12.284 0 16.971L214.322 473.9c4.687 4.686 12.285 4.686 16.971-.001z"/></svg></a></li>';
	}
	
	// Page number links
	for ($i = 1; $i <= $total_pages; $i++) {
		if (1 != $total_pages && (!($i >= $current_page + $range + 1 || $i <= $current_page - $range - 1) || $total_pages <= $showitems)) {
			if ($current_page == $i) {
				echo "<li><span class='flex items-center justify-center w-8 h-8 rounded-full bg-rom-pink text-white font-semibold cursor-not-allowed' aria-current='page'>" . $i . "</span></li>";
			} else {
				$page_url = add_query_arg('cpage', $i, $base_url) . '#comments';
				echo "<li><a class='flex items-center justify-center w-8 h-8 rounded-full border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 hover:border-gray-400 transition-colors duration-200' href='" . esc_url($page_url) . "'>" . $i . "</a></li>";
			}
		}
	}
	
	// Next page link
	if ($current_page < $total_pages && $showitems < $total_pages) {
		$next_url = add_query_arg('cpage', $current_page + 1, $base_url) . '#comments';
		echo '<li><a class="flex items-center justify-center w-8 h-8 rounded-full border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 hover:border-gray-400 transition-colors duration-200" aria-label="Next" href="' . esc_url($next_url) . '"><svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 512" fill="currentColor"><path d="M24.707 38.101L4.908 57.899c-4.686 4.686-4.686 12.284 0 16.971L185.607 256 4.908 437.13c-4.686 4.686-4.686 12.284 0 16.971L24.707 473.9c4.686 4.686 12.284 4.686 16.971 0l209.414-209.414c4.686-4.686 4.686-12.284 0-16.971L41.678 38.101c-4.687-4.687-12.285-4.687-16.971 0z"/></svg></a></li>';
	}
	
	// Last page link
	if ($current_page < $total_pages - 1) {
		if ($current_page + $range - 1 < $total_pages) {
			if ($showitems < $total_pages) {
				$last_url = add_query_arg('cpage', $total_pages, $base_url) . '#comments';
				echo '<li><a class="flex items-center justify-center w-8 h-8 rounded-full border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 hover:border-gray-400 transition-colors duration-200" href="' . esc_url($last_url) . '" aria-label="Last page"><svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor"><path d="M57.7 38.1l209.4 209.4c4.7 4.7 4.7 12.3 0 17L57.7 473.9c-4.7 4.7-12.3 4.7-17 0l-19.8-19.8c-4.7-4.7-4.7-12.3 0-17L201.6 256 20.9 74.9c-4.7-4.7-4.7-12.3 0-17l19.8-19.8c4.7-4.7 12.3-4.7 17 0zm143 0l-19.8 19.8c-4.7 4.7-4.7 12.3 0 17L361.6 256 180.9 437.1c-4.7 4.7-4.7 12.3 0 17l19.8 19.8c4.7 4.7 12.3 4.7 17 0l209.4-209.4c4.7-4.7 4.7-12.3 0-17L217.7 38.1c-4.7-4.7-12.3-4.7-17 0z"/></svg></a></li>';
			}
		}
	}
	
	echo "</ul></nav>";
}


//create function return list svg icons
function svg_icons() {
    return array(
            ''           => __( 'Select Icon', 'rom' ),
            'home'       => '<svg class="inline mr-[2px]" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="18px" height="18px" fill="currentColor"><path d="M240-144q-40 0-68-28t-28-68v-249q0-20 7.5-37.5T173-558l240-231q14-14 31.5-20.5T480-816q18 0 35.5 6.5T547-789l38 37-297 291v173h384v-173L532-598l106-104 149 144q14 14 21.5 31.5T816-489v249q0 40-28 68t-68 28H240Z"/></svg>',
            'sort'       => '<svg class="inline" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="18px" height="18px" fill="#000000"><path d="M480-432 48-648l432-216 432 216-432 216Zm0 168L72-468l80-40 328 163 328-163 80 40-408 204Zm0 168L72-300l80-40 328 163 328-163 80 40L480-96Z"/></svg>',
            'type'       => '<svg class="inline mr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="18px" height="18px" fill="currentColor"><path d="M425-144v-241L217-265l-55-95 208-120-208-120 55-95 208 120v-241h110v241l208-120 55 95-208 120 208 120-55 95-208-120v241H425Z"/></svg>',
            'region'     => '<svg class="inline mr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="18px" height="18px" fill="currentColor"><path d="M480.21-480Q510-480 531-501.21t21-51Q552-582 530.79-603t-51-21Q450-624 429-602.79t-21 51Q408-522 429.21-501t51 21ZM480-96Q323.03-227.11 245.51-339.55 168-452 168-549q0-134 89-224.5T479.5-864q133.5 0 223 90.5T792-549q0 97-77 209T480-96Z"/></svg>',
            'console'    => '<svg class="inline" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="18px" height="18px" fill="#000000"><path d="M480-528 360-648v-216h240v216L480-528Zm168 168L528-480l120-120h216v240H648Zm-552 0v-240h216l120 120-120 120H96ZM360-96v-216l120-120 120 120v216H360Z"/></svg>',
            'genre'      => '<svg class="inline" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="18px" height="18px" fill="#000000"><path d="M384-144H216q-29.7 0-50.85-21.15Q144-186.3 144-216v-168q40-2 68-29.5t28-66.5q0-39-28-66.5T144-576v-168q0-29.7 21.15-50.85Q186.3-816 216-816h168q0-40 27.77-68 27.78-28 68-28Q520-912 548-884.16q28 27.84 28 68.16h168q29.7 0 50.85 21.15Q816-773.7 816-744v168q40 0 68 27.77 28 27.78 28 68Q912-440 884.16-412q-27.84 28-68.16 28v168q0 29.7-21.15 50.85Q773.7-144 744-144H576q-2-40-29.38-68t-66.5-28q-39.12 0-66.62 28-27.5 28-29.5 68Z"/></svg>',
            'size'       => '<svg class="inline mr-[2px]" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="18px" height="18px" fill="currentColor"><path d="M240-144q-60 0-102-42T96-288q0-60 42-102t102-42q60 0 102 42t42 102q0 60-42 102t-102 42Zm480 0q-60 0-102-42t-42-102q0-60 42-102t102-42q60 0 102 42t42 102q0 60-42 102t-102 42Zm-479.79-72Q270-216 291-237.21t21-51Q312-318 290.79-339t-51-21Q210-360 189-338.79t-21 51Q168-258 189.21-237t51 21Zm480 0Q750-216 771-237.21t21-51Q792-318 770.79-339t-51-21Q690-360 669-338.79t-21 51Q648-258 669.21-237t51 21ZM480-528q-60 0-102-42t-42-102q0-60 42-102t102-42q60 0 102 42t42 102q0 60-42 102t-102 42Zm.21-72Q510-600 531-621.21t21-51Q552-702 530.79-723t-51-21Q450-744 429-722.79t-21 51Q408-642 429.21-621t51 21Zm-.21-72Zm240 384Zm-480 0Z"/></svg>',
            'download'   => '<svg class="inline text-rom-pink" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="20px" height="20px" fill="currentColor"><path d="M480-336 288-528l51-51 105 105v-342h72v342l105-105 51 51-192 192ZM263.72-192Q234-192 213-213.15T192-264v-72h72v72h432v-72h72v72q0 29.7-21.16 50.85Q725.68-192 695.96-192H263.72Z"/></svg>',
            'download-2' => '<svg class="inline mr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="20px" height="20px" fill="currentColor"><path d="M192-96v-72h576v72H192Zm288-144L219-576h141v-288h240v288h141L480-240Z"/></svg>',
            'time'       => '<svg class="inline mr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="20px" height="20px" fill="currentColor"><path d="M480-144q-70 0-131.13-26.6-61.14-26.6-106.4-71.87-45.27-45.26-71.87-106.4Q144-410 144-480t26.6-131.13q26.6-61.14 71.87-106.4 45.26-45.27 106.4-71.87Q410-816 480-816q81 0 149.5 35T744-686v-130h72v240H576v-72h107q-35.91-44.8-88.45-70.4Q542-744 480-744q-109 0-186.5 77.5T216-480q0 109 77.5 186.5T480-216q109 0 186.5-77.5T744-480h72q0 140-98 238t-238 98Zm100-200L444-480v-192h72v162l115 115-51 51Z"/></svg>',
            'joystick'   => '<svg class="inline mr-1 text-rom-pink" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="20px" height="20px" fill="currentColor"><path d="m251-444 229 132 230-132-194-111v147h-72v-147L251-444Zm193-195v-14q-42.24-12.32-69.12-46.92-26.88-34.6-26.88-80.1 0-54.98 38.72-93.48 38.72-38.5 93.5-38.5t93.28 38.6q38.5 38.6 38.5 93.21 0 45.19-26.88 79.99T516-653v14l264 153q17.1 9.62 26.55 25.81Q816-444 816-424v104q0 20-9.45 36.19Q797.1-267.63 780-258L516-105q-17.13 10-36.07 10Q461-95 444-105L180-258q-17.1-9.63-26.55-25.81Q144-300 144-320v-104q0-20 9.45-36.19Q162.9-476.38 180-486l264-153Zm0 389L216-382v62l264 152 264-152v-62L516-250q-17.13 10-36.07 10Q461-240 444-250Zm36-470q25 0 42.5-17.5T540-780q0-25-17.5-42.5T480-840q-25 0-42.5 17.5T420-780q0 25 17.5 42.5T480-720Zm0 552Z"/></svg>',
            'reload'     => '<svg class="inline" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="18px" height="18px" fill="#6b7280"><path d="M480-192q-120 0-204-84t-84-204q0-120 84-204t204-84q65 0 120.5 27t95.5 72v-99h72v240H528v-72h131q-29-44-76-70t-103-26q-90 0-153 63t-63 153q0 90 63 153t153 63q84 0 144-55.5T693-456h74q-9 112-91 188t-196 76Z"/></svg>',
            'save'       => '<svg class="inline w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path> 							</svg>',
            'file'       => '<svg class="inline" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="18px" height="18px" fill="currentColor"><path d="M696-312q0 89.86-63.07 152.93Q569.86-96 480-96q-91 0-153.5-65.5T264-319v-389q0-65 45.5-110.5T420-864q66 0 111 48t45 115v365q0 40.15-27.93 68.07Q520.15-240 480-240q-41 0-68.5-29.09T384-340v-380h72v384q0 10.4 6.8 17.2 6.8 6.8 17.2 6.8 10.4 0 17.2-6.8 6.8-6.8 6.8-17.2v-372q0-35-24.5-59.5T419.8-792q-35.19 0-59.5 25.5Q336-741 336-706v394q0 60 42 101.5T480-168q60 1 102-43t42-106v-403h72v408Z"/></svg>',
            'open'       => '<svg class="inline" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="18px" height="18px" fill="currentColor"><path d="M216-144q-29.7 0-50.85-21.15Q144-186.3 144-216v-528q0-29.7 21.15-50.85Q186.3-816 216-816h264v72H216v528h528v-264h72v264q0 29.7-21.15 50.85Q773.7-144 744-144H216Zm171-192-51-51 357-357H576v-72h240v240h-72v-117L387-336Z"/></svg>',
            'info'       => '<svg class="inline mr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="20px" height="20px" fill="currentColor"><path d="M479.79-672q15.21 0 25.71-10.29t10.5-25.5q0-15.21-10.29-25.71t-25.5-10.5q-15.21 0-25.71 10.29t-10.5 25.5q0 15.21 10.29 25.71t25.5 10.5ZM444-360h72v-264h-72v264ZM96-96v-696q0-29.7 21.15-50.85Q138.3-864 168-864h624q29.7 0 50.85 21.15Q864-821.7 864-792v480q0 29.7-21.15 50.85Q821.7-240 792-240H240L96-96Z"/></svg>',
            'released'   => '<svg class="inline mr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="20px" height="20px" fill="currentColor"><path d="M324.21-408q-15.21 0-25.71-10.29t-10.5-25.5q0-15.21 10.29-25.71t25.5-10.5q15.21 0 25.71 10.29t10.5 25.5q0 15.21-10.29 25.71t-25.5 10.5Zm156 0q-15.21 0-25.71-10.29t-10.5-25.5q0-15.21 10.29-25.71t25.5-10.5q15.21 0 25.71 10.29t10.5 25.5q0 15.21-10.29 25.71t-25.5 10.5Zm156 0q-15.21 0-25.71-10.29t-10.5-25.5q0-15.21 10.29-25.71t25.5-10.5q15.21 0 25.71 10.29t10.5 25.5q0 15.21-10.29 25.71t-25.5 10.5ZM216-96q-29.7 0-50.85-21.5Q144-139 144-168v-528q0-29 21.15-50.5T216-768h72v-96h72v96h240v-96h72v96h72q29.7 0 50.85 21.5Q816-725 816-696v528q0 29-21.15 50.5T744-96H216Zm0-72h528v-360H216v360Z"/></svg>',
            'collection' => '<svg class="inline mr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="20px" height="20px" fill="currentColor"><path d="m276-528 204-336 204 336H276ZM696-96q-70 0-119-49t-49-119q0-69 49-118.5T696-432q69 0 118.5 49.5T864-264q0 70-49.5 119T696-96Zm-552-24v-288h288v288H144Z"/></svg>',
            'dev'        => '<svg class="inline mr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="20px" height="20px" fill="currentColor"><path d="M336-240 96-480l240-240 51 51-189 189 189 189-51 51Zm288 0-51-51 189-189-189-189 51-51 240 240-240 240Z"/></svg>',
            'view'       => '<svg class="inline mr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="20px" height="20px" fill="currentColor"><path d="M480-312q70 0 119-49t49-119q0-70-49-119t-119-49q-70 0-119 49t-49 119q0 70 49 119t119 49Zm0-72q-40 0-68-28t-28-68q0-40 28-68t68-28q40 0 68 28t28 68q0 40-28 68t-68 28Zm0 192q-142.6 0-259.8-78.5Q103-349 48-480q55-131 172.2-209.5Q337.4-768 480-768q142.6 0 259.8 78.5Q857-611 912-480q-55 131-172.2 209.5Q622.6-192 480-192Zm0-288Zm0 216q112 0 207-58t146-158q-51-100-146-158t-207-58q-112 0-207 58T127-480q51 100 146 158t207 58Z"/></svg>',
            'size-2'     => '<svg class="inline mr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="20px" height="20px" fill="currentColor"><path d="M480-480ZM230-83l-50-51 106-106h-82v-72h204v204h-72v-81L230-83Zm250-13v-72h216v-456H528v-168H264v408h-72v-408q0-29.7 21.15-50.85Q234.3-864 264-864h312l192 192v504q0 29.7-21.15 50.85Q725.7-96 696-96H480Z"/></svg>',
            'flag'       => '<svg class="inline" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="20px" height="20px" fill="currentColor"><path d="M192-144v-672h336l24 96h216v384H528l-24-96H264v288h-72Z"/></svg>',
            'emulator'   => '<svg class="inline mr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="20px" height="20px" fill="currentColor"><path d="M480-528 360-648v-216h240v216L480-528Zm168 168L528-480l120-120h216v240H648Zm-552 0v-240h216l120 120-120 120H96ZM360-96v-216l120-120 120 120v216H360Z"/></svg>',
            'more'       => '<svg class="inline" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="20px" height="20px" fill="currentColor"><path d="M381-480 192-669l51-51 240 240-240 240-51-51 189-189Zm285 0L477-669l51-51 240 240-240 240-51-51 189-189Z"/></svg>',
            'noti'       => '<svg class="inline mr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="18px" height="18px" fill="currentColor"><path d="M160-200v-80h80v-280q0-83 50-147.5T420-792v-28q0-25 17.5-42.5T480-880q25 0 42.5 17.5T540-820v28q80 20 130 84.5T720-560v280h80v80H160ZM480-80q-33 0-56.5-23.5T400-160h160q0 33-23.5 56.5T480-80Z"/></svg>'
    );
}

function get_svg_icon($key = '') {
	$icons = svg_icons();
	if ( !empty($key) && isset($icons[$key])) {
		return $icons[$key];
	}
	return '';
}

/**
 * Inline style for a console badge: the term's `console_color` as background,
 * with white or near-black text picked by the colour's brightness. Returns ''
 * when the term has no valid colour, so the badge keeps its bg-primary.
 */
function rom_console_badge_style( $term ) {
	$hex = $term ? sanitize_hex_color( get_term_meta( $term->term_id, 'console_color', true ) ) : '';
	if ( ! $hex ) {
		return '';
	}

	$hex = ltrim( $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	list( $r, $g, $b ) = array_map( 'hexdec', str_split( $hex, 2 ) );

	// YIQ brightness: light colours (Wii sky, Game Boy lime...) get dark text.
	$text = ( $r * 299 + $g * 587 + $b * 114 ) / 1000 >= 150 ? '#0f172a' : '#ffffff';

	return 'background-color:#' . $hex . ';color:' . $text . ';';
}