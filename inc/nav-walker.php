<?php
/**
 * Navigation menu filters and functions
 */

// Filter to add description to menu items
add_filter('wp_nav_menu_objects', 'k_nav_menu_item', 10, 2);
function k_nav_menu_item( $items, $args ) {
    foreach ( $items as &$item ) {
        if ( $item->description ) {
            $item->title = '<span class="title">' . $item->title . '</span>';
            $item->title .= '<span class="desc">' . $item->description . '</span>';
        }
    }
    return $items;
}

/**
 * Check if a menu item should be marked as active
 * Handles categories, taxonomy terms, post types, and their children
 */
function rom_is_menu_item_active( $item ) {
    $is_active = false;
    $current_page_id = get_queried_object_id();
    $current_url = untrailingslashit( ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
    $home_url = untrailingslashit( home_url() );
    $item_url = untrailingslashit( $item->url );
    
    // Direct object ID match
    if ( $item->object_id == $current_page_id ) {
        return true;
    }
    
    // Front page check
    if ( is_front_page() && ( $item_url === $home_url || $item_url === $home_url . '/' ) ) {
        return true;
    }
    
    // Exact URL match
    if ( ! is_front_page() && $item_url === $current_url ) {
        return true;
    }
    
    // Handle taxonomy terms (category, post_tag, custom taxonomies)
    if ( $item->type === 'taxonomy' ) {
        $taxonomy = $item->object;
        $term_id = $item->object_id;
        
        // Check if currently viewing this taxonomy term
        if ( is_tax( $taxonomy, $term_id ) ) {
            return true;
        }
        
        // Check if viewing a child/sub-term of this taxonomy term
        if ( is_tax( $taxonomy ) || ( $taxonomy === 'category' && is_category() ) || ( $taxonomy === 'post_tag' && is_tag() ) ) {
            $queried_term = get_queried_object();
            if ( $queried_term && isset( $queried_term->term_id ) ) {
                // Direct match
                if ( $queried_term->term_id == $term_id ) {
                    return true;
                }
                // Check if current term is a descendant of the menu item term
                $ancestors = get_ancestors( $queried_term->term_id, $taxonomy, 'taxonomy' );
                if ( in_array( $term_id, $ancestors ) ) {
                    return true;
                }
            }
        }
        
        // Check if viewing a post that belongs to this taxonomy term or its children
        if ( is_singular() ) {
            global $post;
            if ( $post ) {
                // Get all terms for this taxonomy that the post belongs to
                $post_terms = wp_get_post_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );
                if ( ! is_wp_error( $post_terms ) && ! empty( $post_terms ) ) {
                    foreach ( $post_terms as $post_term_id ) {
                        // Check if this term is the menu item term or a descendant
                        if ( $post_term_id == $term_id ) {
                            return true;
                        }
                        // Check if this term is a child of the menu item term
                        $ancestors = get_ancestors( $post_term_id, $taxonomy, 'taxonomy' );
                        if ( in_array( $term_id, $ancestors ) ) {
                            return true;
                        }
                    }
                }
            }
        }
        
        // Check if viewing a category archive (for category taxonomy)
        if ( $taxonomy === 'category' && is_category( $term_id ) ) {
            return true;
        }
        
        // Check if viewing a post in this category or child category
        if ( $taxonomy === 'category' && is_single() ) {
            $categories = get_the_category();
            if ( ! empty( $categories ) ) {
                foreach ( $categories as $category ) {
                    if ( $category->term_id == $term_id ) {
                        return true;
                    }
                    // Check if category is a child of the menu item category
                    $ancestors = get_ancestors( $category->term_id, 'category', 'taxonomy' );
                    if ( in_array( $term_id, $ancestors ) ) {
                        return true;
                    }
                }
            }
        }
    }
    
    // Handle post types (pages, custom post types)
    if ( $item->type === 'post_type' ) {
        $post_type = $item->object;
        
        // Check if viewing the specific post/page
        if ( is_singular( $post_type ) && $item->object_id == $current_page_id ) {
            return true;
        }
        
        // Check if viewing a child page (for pages)
        if ( $post_type === 'page' && is_page() ) {
            $ancestors = get_post_ancestors( $current_page_id );
            if ( in_array( $item->object_id, $ancestors ) ) {
                return true;
            }
        }
        
        // Check if viewing post type archive
        if ( is_post_type_archive( $post_type ) ) {
            return true;
        }
        
        // Special handling for page template menus (e.g., page-roms.php)
        if ( $post_type === 'page' ) {
            $template = get_page_template_slug( $item->object_id );
            
            // Check if menu item links to ROMs page template and we're viewing ROMs
            if ( $template === 'page-roms.php' ) {
                if ( is_singular( 'rom' ) || is_tax( 'console' ) ) {
                    return true;
                }
            }
            
            // Check if menu item links to Emulators page template and we're viewing Emulators
            if ( $template === 'page-emulators.php' ) {
                if ( is_singular( 'emulator' ) || is_tax( 'emulator_console' ) ) {
                    return true;
                }
            }
        }
    }
    
    // Handle custom URL menu items - check if URL pattern matches
    if ( $item->type === 'custom' ) {
        // Extract path from item URL
        $item_path = parse_url( $item_url, PHP_URL_PATH );
        $current_path = parse_url( $current_url, PHP_URL_PATH );
        
        // If item path is a prefix of current path, consider it active
        if ( $item_path && $current_path && strpos( $current_path, $item_path ) === 0 && strlen( $item_path ) > 1 ) {
            return true;
        }
    }
    
    return $is_active;
}

/**
 * Display header navigation menu using WordPress functions
 * Outputs menu items directly without ul/li wrappers
 */
function rom_display_header_menu( $theme_location = 'primary' ) {
    if ( ! has_nav_menu( $theme_location ) ) {
        return;
    }
    
    $locations = get_nav_menu_locations();
    $menu_id = isset( $locations[ $theme_location ] ) ? $locations[ $theme_location ] : 0;
    
    if ( ! $menu_id ) {
        return;
    }
    
    $menu_items = wp_get_nav_menu_items( $menu_id );
    if ( ! $menu_items ) {
        return;
    }
    
    foreach ( $menu_items as $item ) {
        // Check if menu item should be active
        $is_active = rom_is_menu_item_active( $item );
        
        // Build classes. The <nav> wrapper in header.php sets the shared size and
        // weight; the active item is a filled pill, the rest tint on hover.
        $link_classes = $is_active
                ? 'px-3.5 py-1.5 rounded-xl bg-primary text-white font-bold shadow-sm shadow-primary/25'
                : 'px-3.5 py-1.5 rounded-xl hover:text-primary hover:bg-rose-50/50 transition-colors';

        echo '<a class="inline-flex items-center gap-1.5 ' . esc_attr( $link_classes ) . '" href="' . esc_url( $item->url ) . '">';
        echo rom_menu_icon_svg( get_post_meta( $item->ID, '_menu_item_icon', true ), 'w-4 h-4 flex-shrink-0' );
        echo esc_html( $item->title );
        echo '</a>';
    }
}

/**
 * Display mobile navigation menu using WordPress functions
 */
function rom_display_mobile_menu( $theme_location = 'primary' ) {
    if ( ! has_nav_menu( $theme_location ) ) {
        return;
    }
    
    $locations = get_nav_menu_locations();
    $menu_id = isset( $locations[ $theme_location ] ) ? $locations[ $theme_location ] : 0;
    
    if ( ! $menu_id ) {
        return;
    }
    
    $menu_items = wp_get_nav_menu_items( $menu_id );
    if ( ! $menu_items ) {
        return;
    }
    
    foreach ( $menu_items as $item ) {
        // Check if menu item should be active
        $is_active = rom_is_menu_item_active( $item );
        
        // Build classes
        $link_classes = 'mobile-menu-item';
        if ( $is_active ) {
            $link_classes .= ' active';
        }

        // Row: icon tile, title, chevron (styles in style.css .mobile-menu-*).
        $icon = rom_menu_icon_svg( get_post_meta( $item->ID, '_menu_item_icon', true ), 'w-5 h-5' );
        echo '<a class="' . esc_attr( $link_classes ) . '" href="' . esc_url( $item->url ) . '"' . ( $is_active ? ' aria-current="page"' : '' ) . '>';
        if ( $icon ) {
            echo '<span class="mobile-menu-icon">' . $icon . '</span>';
        }
        echo '<span class="flex-1 min-w-0 truncate">' . esc_html( $item->title ) . '</span>';
        echo '<svg class="mobile-menu-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>';
        echo '</a>';
    }
}