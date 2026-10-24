<?php
/**
 * Breadcrumbs.
 *
 * k_breadcrumb() collects the trail as label/url pairs, then renders it once,
 * so every branch is escaped the same way. The last crumb is the current page
 * and never links.
 */

/**
 * ID of the published page using $template ('page-roms.php'...), or 0.
 *
 * _wp_page_template is matched by meta_value, which has no index, so the answer
 * is kept in a transient and dropped whenever a page is saved or deleted.
 */
function rf_template_page( $template ) {
	static $pages = array();

	if ( ! isset( $pages[ $template ] ) ) {
		$key     = 'rf_tpl_page_' . md5( $template );
		$page_id = get_transient( $key );
		if ( false === $page_id ) {
			$ids     = get_posts( array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => '_wp_page_template',
				'meta_value'     => $template,
			) );
			$page_id = $ids ? (int) $ids[0] : 0;
			set_transient( $key, $page_id, DAY_IN_SECONDS );
		}
		$pages[ $template ] = (int) $page_id;
	}

	return $pages[ $template ];
}

function rf_template_page_flush( $post_id ) {
	if ( 'page' !== get_post_type( $post_id ) ) {
		return;
	}
	foreach ( get_rom_page_templates() as $template ) {
		delete_transient( 'rf_tpl_page_' . md5( $template ) );
	}
}
add_action( 'save_post_page', 'rf_template_page_flush' );
add_action( 'before_delete_post', 'rf_template_page_flush' );

/**
 * The listing page a crumb trail hangs off: Roms for rom content, Emulators
 * for emulator content, Collections for collection terms. Empty when the page
 * doesn't exist.
 */
function rf_breadcrumb_section( $post_type, $taxonomy = '' ) {
	if ( 'collection' === $taxonomy ) {
		$template = 'page-collections.php';
	} else {
		$template = array(
			'rom'      => 'page-roms.php',
			'emulator' => 'page-emulators.php',
		)[ $post_type ] ?? '';
	}

	$page_id = $template ? rf_template_page( $template ) : 0;
	if ( ! $page_id ) {
		return array();
	}

	return array( array( 'label' => get_the_title( $page_id ), 'url' => get_permalink( $page_id ) ) );
}

/**
 * Linked crumbs for a term and its ancestors, root first.
 */
function rf_breadcrumb_terms( $term, $include_self = true ) {
	$ids = array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) );
	if ( $include_self ) {
		$ids[] = $term->term_id;
	}

	$crumbs = array();
	foreach ( $ids as $id ) {
		$item = get_term( $id, $term->taxonomy );
		$link = $item instanceof WP_Term ? get_term_link( $item ) : '';
		if ( $link && ! is_wp_error( $link ) ) {
			$crumbs[] = array( 'label' => $item->name, 'url' => $link );
		}
	}

	return $crumbs;
}

/**
 * Crumbs between Home and a post: its category chain for blog posts, or the
 * listing page plus the console term for roms and emulators.
 */
function rf_breadcrumb_post_trail( $post_id ) {
	$post_type = get_post_type( $post_id );

	if ( 'post' === $post_type ) {
		$categories = get_the_category( $post_id );
		return $categories ? rf_breadcrumb_terms( end( $categories ) ) : array();
	}

	$crumbs   = rf_breadcrumb_section( $post_type );
	$taxonomy = get_custom_console_name( (string) $post_type );
	$terms    = $taxonomy ? get_the_terms( $post_id, $taxonomy ) : false;
	if ( $terms && ! is_wp_error( $terms ) ) {
		$crumbs = array_merge( $crumbs, rf_breadcrumb_terms( $terms[0] ) );
	}

	return $crumbs;
}

function k_breadcrumb() {
	$crumbs = array();

	if ( get_query_var( 'download' ) ) {
		// The download page renders through template_redirect on top of the single.
		$post_id  = get_queried_object_id();
		$crumbs   = rf_breadcrumb_post_trail( $post_id );
		$crumbs[] = array( 'label' => get_the_title( $post_id ), 'url' => get_permalink( $post_id ) );
		$crumbs[] = array( 'label' => __( 'Download', 'rom' ) );
	} elseif ( is_single() ) {
		$post_id  = get_queried_object_id();
		$crumbs   = rf_breadcrumb_post_trail( $post_id );
		$crumbs[] = array( 'label' => get_the_title( $post_id ) );
	} elseif ( is_tax() ) {
		$term     = get_queried_object();
		$tax      = get_taxonomy( $term->taxonomy );
		$crumbs   = rf_breadcrumb_section( $tax ? (string) reset( $tax->object_type ) : '', $term->taxonomy );
		$crumbs   = array_merge( $crumbs, rf_breadcrumb_terms( $term, false ) );
		$crumbs[] = array( 'label' => $term->name );
	} elseif ( is_post_type_archive() ) {
		$crumbs[] = array( 'label' => post_type_archive_title( '', false ) );
	} elseif ( is_category() ) {
		$crumbs   = rf_breadcrumb_terms( get_queried_object(), false );
		$crumbs[] = array( 'label' => single_cat_title( '', false ) );
	} elseif ( is_tag() ) {
		$crumbs[] = array( 'label' => __( 'Tag', 'rom' ) . ': ' . single_tag_title( '', false ) );
	} elseif ( is_page() ) {
		$page_id = get_queried_object_id();
		foreach ( array_reverse( get_post_ancestors( $page_id ) ) as $ancestor ) {
			$crumbs[] = array( 'label' => get_the_title( $ancestor ), 'url' => get_permalink( $ancestor ) );
		}
		$crumbs[] = array( 'label' => get_the_title( $page_id ) );
	} elseif ( is_search() ) {
		$crumbs[] = array( 'label' => __( 'Search results for', 'rom' ) . ': ' . get_search_query( false ) );
	} elseif ( is_404() ) {
		$crumbs[] = array( 'label' => __( 'Error 404', 'rom' ) );
	} elseif ( get_query_var( 'paged' ) ) {
		$crumbs[] = array( 'label' => __( 'Page', 'rom' ) . ' ' . (int) get_query_var( 'paged' ) );
	}
	// Author and date archives are not handled: both answer 404 on this site.

	$home_title = __( 'Home', 'rom' );
	$separator  = '<span class="mx-2 text-slate-300" aria-hidden="true">/</span>';
	?>
    <nav aria-label="<?php esc_attr_e( 'Breadcrumb', 'rom' ); ?>">
        <ul id="breadcrumb" class="pb-3 flex flex-wrap items-center text-meta font-medium [&_a:hover]:underline">
            <li class="flex items-center">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="text-rom-pink hover:underline inline-flex items-center">
                    <?php echo get_svg_icon( 'home' ) . esc_html( $home_title ); ?>
                </a>
                <?php if ( $crumbs ) { echo $separator; } ?>
            </li>
			<?php foreach ( $crumbs as $i => $crumb ) :
				$is_last = ( count( $crumbs ) - 1 === $i );
				?>
                <li class="flex items-center">
					<?php if ( ! $is_last && ! empty( $crumb['url'] ) ) : ?>
                        <a href="<?php echo esc_url( $crumb['url'] ); ?>" class="text-slate-500 hover:underline"><?php echo esc_html( $crumb['label'] ); ?></a>
					<?php else : ?>
                        <span class="<?php echo $is_last ? 'text-slate-700 font-semibold' : 'text-slate-500'; ?>"<?php echo $is_last ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $crumb['label'] ); ?></span>
					<?php endif; ?>
					<?php if ( ! $is_last ) { echo $separator; } ?>
                </li>
			<?php endforeach; ?>
        </ul>
    </nav>
	<?php
}
