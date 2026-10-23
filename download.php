<?php
/**
 * Download Page Template
 * Handles ROM download pages with validation, security, and optimization
 *
 * @package romsvault
 */

// The rewrite rule resolves the post itself, so the queried object IS the download
// target. $file_number is 1-based; 0 means "list every file".
$post = get_queried_object();
if ( ! $post instanceof WP_Post || ! valid_downloadable_post_type( $post->post_type ) ) {
    wp_redirect( home_url() );
    exit;
}

$post_id       = $post->ID;
$file_number   = absint( get_query_var( 'download_file' ) );
$download_base = trailingslashit( get_permalink( $post_id ) ) . 'download/';

get_header();

// Get download lists
$normal_downloads = get_field( 'download_list', $post_id );
$aff_downloads    = get_field( 'download_list_aff', $post_id );

// Merge and validate downloads array
$downloads = array();
if ( is_array( $normal_downloads ) && is_array( $aff_downloads ) ) {
    $downloads = array_merge( $normal_downloads, $aff_downloads );
} elseif ( is_array( $normal_downloads ) ) {
    $downloads = $normal_downloads;
} elseif ( is_array( $aff_downloads ) ) {
    $downloads = $aff_downloads;
}

// Validate downloads array is not empty
if ( empty( $downloads ) ) {
    get_template_part( 'template-parts/content-none' );
    get_footer();
    exit;
}

$console_name = get_custom_console_name( $post->post_type );

$console = null;

if ( $console_name ) :
    $terms = wp_get_post_terms( $post_id, $console_name, array( 'fields' => 'all' ) );
    if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) :
        $console = $terms[0];
    else :
        rf_force_404();
    endif;  
else :
    rf_force_404();
endif;

// Check if user has already rated
$has_rated         = false;
if ( isset( $_COOKIE['ratings'] ) ) {
    $rated_posts = json_decode( stripslashes( $_COOKIE['ratings'] ), true );
    if ( is_array( $rated_posts ) && in_array( $post_id, $rated_posts, true ) ) {
        $has_rated = true;
    }
}
$count = (int)get_post_meta($post->ID, 'rating_count', true);
$count = max(0, $count);
$score = (float)get_post_meta($post->ID, 'rating_avg', true);
$score = round($score, 1, PHP_ROUND_HALF_DOWN);
?>


    <section class="py-6 text-gray-900">
        <div class="content-container px-4 sm:px-6 lg:px-8 w-full">
            <div class="prose max-w-none bg-white p-6 rounded-2xl shadow-xl">
                <?php k_breadcrumb(); ?>
                <h1 class="text-rom-pink text-2xl font-bold">
                    <?php echo esc_html( __( 'Download', 'rom' ) . ' ' . get_the_title( $post_id ) . ' ' . $post->post_type ); ?>
                </h1>
                <div class="flex items-center pt-2 pb-6">
                    <div class="d-flex align-items-center min-w-[5rem]">
                        <div class="rating"
                             data-post_id="<?php echo esc_attr( $post_id ); ?>"
                             data-rateyo-rating="<?php echo esc_attr( $score ); ?>"
                             <?php if ( $has_rated ) : ?>data-rateyo-read-only="true"<?php endif; ?>>
                        </div>
                    </div>
                    <span class="ml-2 text-sm text-gray-600"><?php echo esc_html( $score ); ?></span>
                    <span class="ml-2 text-sm text-gray-500">
					(<?php echo esc_html( $count . ' ' . _n( 'review', 'reviews', $count, 'rom' ) ); ?>)
				</span>
                </div>

                <?php if ( $console && $guide = get_field( 'console_guide', 'term_' . $console->term_id ) ) : ?>
                    <div class="bg-rom-pink/10 p-4 rounded-xl shadow-lg mb-6">
                        <div class="font-bold text-rom-pink mb-3 flex items-center">
                            <?php echo get_svg_icon( 'noti' ) . ' ' . esc_html( __( 'Note', 'rom' ) ); ?>
                        </div>
                        <div class="revert page-content"><?php echo wp_kses_post( $guide ); ?></div>
                    </div>
                <?php endif; ?>


                <?php if ( $guide = get_field( 'download_note', $post_id ) ) : ?>
                    <div class="bg-green-100 p-4 rounded-xl shadow-lg mb-6">
                        <div class="font-bold text-green-500 mb-3 flex items-center">
                            <?php echo get_svg_icon( 'info' ) . ' ' . esc_html( __( 'Download Guides', 'rom' ) ); ?>
                        </div>
                        <?php echo wpautop( $guide ); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

<?php
// Display download lists if no specific file is selected
if ( ! $file_number ) :
    // Normal downloads section
    if ( is_array( $normal_downloads ) && ! empty( $normal_downloads ) ) :
        ?>
        <section class="pb-6 text-gray-900">
            <div class="content-container px-4 sm:px-6 lg:px-8 w-full">
                <div class="prose max-w-none bg-white p-6 lg:p-8 rounded-2xl shadow-xl">
                    <h2 class="text-lg font-semibold pb-6">
                        <?php esc_html_e( 'Normal download links', 'rom' ); ?>
                    </h2>
                    <div class="overflow-x-auto">
                        <table class="table-auto w-full">
                            <thead>
                            <tr>
                                <th class="text-left pb-6">Filename</th>
                                <th class="text-left pb-6">Type</th>
                                <th class="text-left pb-6">Size</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ( $normal_downloads as $key => $value ) :
                                if ( empty( $value['name'] ) || empty( $value['url'] ) ) {
                                    continue;
                                }
                                $file_index = $key + 1;
                                ?>
                                <tr class="odd:bg-gray-100">
                                    <td class="pl-4 py-2 rounded-l-full font-semibold text-rom-pink">
                                        <a target="_blank"
                                           href="<?php echo esc_url( $download_base . $file_index . '/' ); ?>"
                                           class="hover:underline">
                                            <?php echo $value['name'] . ' ' . get_svg_icon( 'open' ); ?>
                                        </a>
                                    </td>
                                    <td class="py-2 font-semibold text-gray-500">
                                        <?php echo esc_html( isset( $value['file_type'] ) ? $value['file_type'] : '' ); ?>
                                    </td>
                                    <td class="py-2 rounded-r-full font-semibold text-gray-500">
                                        <?php echo esc_html( isset( $value['file_size'] ) ? $value['file_size'] : '' ); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    <?php
    endif;

    // Affiliate downloads section
    if ( is_array( $aff_downloads ) && ! empty( $aff_downloads ) ) :
        $normal_count = is_array( $normal_downloads ) ? count( $normal_downloads ) : 0;
        ?>
        <section class="pb-6 text-gray-900">
            <div class="content-container px-4 sm:px-6 lg:px-8 w-full">
                <div class="prose max-w-none bg-white p-6 lg:p-8 rounded-2xl shadow-xl">
                    <h2 class="text-lg font-semibold pb-6">
                        <?php esc_html_e( 'Aff download links', 'rom' ); ?>
                    </h2>
                    <div class="overflow-x-auto">
                        <table class="table-auto w-full">
                            <thead>
                            <tr>
                                <th class="text-left pb-6">Filename</th>
                                <th class="text-left pb-6">Type</th>
                                <th class="text-left pb-6">Size</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ( $aff_downloads as $key => $value ) :
                                if ( empty( $value['name'] ) || empty( $value['url'] ) ) {
                                    continue;
                                }
                                $file_index = $key + 1 + $normal_count;
                                ?>
                                <tr class="odd:bg-gray-100">
                                    <td class="pl-4 py-2 rounded-l-full font-semibold text-rom-pink">
                                        <a target="_blank"
                                           href="<?php echo esc_url( $download_base . $file_index . '/' ); ?>"
                                           class="hover:underline">
                                            <?php echo $value['name'] . ' ' . get_svg_icon( 'open' ); ?>
                                        </a>
                                    </td>
                                    <td class="py-2 font-semibold text-gray-500">
                                        <?php echo esc_html( isset( $value['file_type'] ) ? $value['file_type'] : '' ); ?>
                                    </td>
                                    <td class="py-2 font-semibold text-gray-500">
                                        <?php echo esc_html( isset( $value['file_size'] ) ? $value['file_size'] : '' ); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    <?php
    endif;

// Display download page for specific file
elseif ( $file_number ) :
    $file_index = $file_number - 1;

    // Validate file index
    if ( ! isset( $downloads[ $file_index ] ) || empty( $downloads[ $file_index ]['url'] ) ) {
        get_template_part( 'template-parts/content', 'none' );
        get_footer();
        exit;
    }

    $link = $downloads[ $file_index ];

    // Validate link data
    if ( empty( $link['name'] ) || empty( $link['url'] ) ) {
        get_template_part( 'template-parts/content', 'none' );
        get_footer();
        exit;
    }

    // Apply affiliate codes if applicable
    $affs = get_field('affs', 'option');
    if ( is_array( $affs ) && ! empty( $affs ) ) {
        $link_url = esc_url_raw( $link['url'] );
        foreach ( $affs as $aff ) {
            if ( ! empty( $aff['aff_host'] ) && ! empty( $aff['aff_code'] ) ) {
                if ( strpos( $link_url, $aff['aff_host'] ) !== false ) {
                    $separator = parse_url( $link_url, PHP_URL_QUERY ) ? '&' : '?';
                    $link_url  .= $separator . sanitize_text_field( $aff['aff_code'] );
                    break; // Only apply first matching affiliate
                }
            }
        }
        $link['url'] = $link_url;
    }

    // Generate download URL with token. Members-only posts (approach C) never bake
    // the URL into this cacheable HTML — the button fetches it from rf/v1/download-url
    // after the session is verified. This <a> is only the fallback k_get_download reveals.
    $rf_members           = rf_is_restricted( $post_id );
    $download_url         = $rf_members ? '' : add_query_arg( 'token', urlencode( get_download_token() ), $link['url'] );
    $file_name            = esc_html( $link['name'] );
    $file_type            = isset( $link['file_type'] ) ? esc_html( $link['file_type'] ) : '';
    $file_size            = isset( $link['file_size'] ) ? esc_html( $link['file_size'] ) : '';
    ?>
    <section class="pb-6 text-gray-900">
        <div class="content-container px-4 sm:px-6 lg:px-8 w-full">
            <div class="prose max-w-none bg-white p-6 lg:p-8 mb-8 rounded-2xl shadow-xl">
                <h1 class="text-base font-bold text-gray-500 py-4 text-center">
                    <?php echo esc_html( __( 'You are downloading', 'rom' ) . ' ' . $file_name . ' (' . $file_type . ')'); ?>
                </h1>
                <div id="download-container" class="text-center py-6" data-ajax-url="<?php echo esc_attr( admin_url('admin-ajax.php') ); ?>" data-post="<?php echo esc_attr( $post_id ); ?>" data-file="<?php echo esc_attr( $file_number ); ?>">
                    <?php if ( $rf_members ) : ?>
                    <!-- Restricted: countdown + button are for active members only (guests/blocked
                         see the notice below). Wrap them here rather than putting rf-authed-only on
                         the elements themselves — that class outranks the JS `.hidden` toggle
                         (html.rf-authed .rf-authed-only beats .hidden), which would leave the
                         countdown stuck and the button showing early. -->
                    <div class="rf-authed-only rf-hide-blocked">
                    <?php endif; ?>
                    <!-- Loading State (countdown) -->
                    <div id="download-loading" class="space-y-4">
                        <div class="flex justify-center">
                            <div class="relative w-12 h-12">
                                <div class="absolute inset-0 border-4 border-rom-pink/20 rounded-full"></div>
                                <div class="absolute inset-0 border-4 border-transparent border-t-rom-pink rounded-full animate-spin"></div>
                            </div>
                        </div>
                        <p class="text-sm text-gray-500" id="countdown-text">
                            Preparing your download. Please wait
                            <span id="countdown">7</span>
                            seconds
                        </p>
                    </div>

                    <!-- Download Button (Hidden initially) -->
                    <div id="download-button" class="hidden">
                        <a href="<?php echo $rf_members ? '#' : esc_url( $download_url ); ?>"
                           id="download-link"
                           <?php if ( $rf_members ) : ?>data-rf-download data-post="<?php echo esc_attr( $post_id ); ?>" data-file="<?php echo esc_attr( $file_number ); ?>"<?php endif; ?>
                           class="inline-flex items-center justify-center px-6 py-4 bg-rom-pink text-white rounded-full hover:bg-rom-pink-dark transition font-semibold shadow-lg hover:shadow-xl transform hover:scale-105"
                           rel="nofollow">
                            <?php echo get_svg_icon( 'save' ) . ' ' . __( 'Download Now', 'rom' ). ' ' . ' (' . $file_size . ')'; ?>
                        </a>
                        <p class="text-sm text-gray-500 mt-4">
                            <?php esc_html_e( 'If the download doesn\'t start automatically, click the button above', 'rom' ); ?>
                        </p>
                    </div>

                    <?php if ( $rf_members ) : ?>
                    </div><!-- /active-only wrapper (countdown + button) -->

                    <!-- Guests and blocked members: no button/countdown, just the notice. Active
                         members get the button above; the server (download-url) is the real gate. -->
                    <div class="max-w-md mx-auto text-left">
                        <?php rf_render_restricted_notice(); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>


    <?php
    // Display other available download links if there are multiple files
    if ( count( $downloads ) > 1 ) :
        $normal_count = is_array( $normal_downloads ) ? count( $normal_downloads ) : 0;
        $has_other_normal = false;
        $has_other_aff    = false;

        // Check if there are other normal downloads
        // $file_index is in merged array, so for normal files it matches $key directly
        if ( is_array( $normal_downloads ) ) {
            foreach ( $normal_downloads as $key => $value ) {
                // Skip if this is the current file being downloaded (only if it's a normal file)
                if ( $file_index < $normal_count && $key === $file_index ) {
                    continue;
                }
                if ( ! empty( $value['name'] ) && ! empty( $value['url'] ) ) {
                    $has_other_normal = true;
                    break;
                }
            }
        }

        // Check if there are other affiliate downloads
        if ( is_array( $aff_downloads ) ) {
            foreach ( $aff_downloads as $key => $value ) {
                $aff_index = $key + $normal_count;
                // Skip if this is the current file being downloaded
                if ( $aff_index === $file_index ) {
                    continue;
                }
                if ( ! empty( $value['name'] ) && ! empty( $value['url'] ) ) {
                    $has_other_aff = true;
                    break;
                }
            }
        }

        if ( $has_other_normal || $has_other_aff ) :
            ?>
            <section class="pb-6 text-gray-900">
                <div class="content-container px-4 sm:px-6 lg:px-8 w-full">
                    <h2 class="font-bold text-black text-lg mb-4">
                        <?php esc_html_e( 'Other available download links', 'rom' ); ?>
                    </h2>

                    <?php if ( $has_other_normal ) : ?>
                        <div class="bg-white p-6 lg:p-8 mb-6 rounded-2xl shadow-xl">
                            <h3 class="text-base font-semibold mb-4 text-gray-700">
                                <?php esc_html_e( 'Normal Download Links', 'rom' ); ?>
                            </h3>
                            <div class="overflow-x-auto">
                                <table class="table-auto w-full">
                                    <thead>
                                    <tr>
                                        <th class="text-left"><?php esc_html_e( 'Filename', 'rom' ); ?></th>
                                        <th class="text-left"><?php esc_html_e( 'Type', 'rom' ); ?></th>
                                        <th class="text-left"><?php esc_html_e( 'Size', 'rom' ); ?></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    foreach ( $normal_downloads as $key => $value ) :
                                        // Skip if this is the current file being downloaded (only if it's a normal file)
                                        if ( ( $file_index < $normal_count && $key === $file_index ) || empty( $value['name'] ) || empty( $value['url'] ) ) {
                                            continue;
                                        }
                                        $other_file_index = $key + 1;
                                        ?>
                                        <tr>
                                            <td class="text-rom-pink font-semibold py-2">
                                                <a href="<?php echo esc_url( $download_base . $other_file_index . '/' ); ?>"
                                                   class="hover:underline">
                                                    <?php echo esc_html( $value['name'] ); ?>
                                                </a>
                                            </td>
                                            <td class="py-1 font-semibold text-gray-500">
                                                <?php echo esc_html( isset( $value['file_type'] ) ? $value['file_type'] : '' ); ?>
                                            </td>
                                            <td class="py-1 font-semibold text-gray-500">
                                                <?php echo esc_html( isset( $value['file_size'] ) ? $value['file_size'] : '' ); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ( $has_other_aff ) : ?>
                        <div class="bg-white p-6 lg:p-8 mb-6 rounded-2xl shadow-xl">
                            <h3 class="text-base font-semibold mb-4 text-gray-700">
                                <?php esc_html_e( 'Affiliate Download Links', 'rom' ); ?>
                            </h3>
                            <div class="overflow-x-auto">
                                <table class="table-auto w-full">
                                    <thead>
                                    <tr>
                                        <th class="text-left"><?php esc_html_e( 'Filename', 'rom' ); ?></th>
                                        <th class="text-left"><?php esc_html_e( 'Type', 'rom' ); ?></th>
                                        <th class="text-left"><?php esc_html_e( 'Size', 'rom' ); ?></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    foreach ( $aff_downloads as $key => $value ) :
                                        $aff_index = $key + $normal_count;
                                        if ( $aff_index === $file_index || empty( $value['name'] ) || empty( $value['url'] ) ) {
                                            continue;
                                        }
                                        $other_file_index = $aff_index + 1;
                                        ?>
                                        <tr>
                                            <td class="text-rom-pink font-semibold py-2">
                                                <a href="<?php echo esc_url( $download_base . $other_file_index . '/' ); ?>"
                                                   class="hover:underline">
                                                    <?php echo esc_html( $value['name'] ); ?>
                                                </a>
                                            </td>
                                            <td class="py-1 font-semibold text-gray-500">
                                                <?php echo esc_html( isset( $value['file_type'] ) ? $value['file_type'] : '' ); ?>
                                            </td>
                                            <td class="py-1 font-semibold text-gray-500">
                                                <?php echo esc_html( isset( $value['file_size'] ) ? $value['file_size'] : '' ); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php
        endif;
    endif;

else :
    // No valid download scenario
    get_template_part( 'template-parts/content', 'none' );
endif;
?>

    <section class="pb-6 text-gray-900">
        <div class="content-container px-4 sm:px-6 lg:px-8 w-full">
            <?php get_template_part( 'template-parts/report-form', null, array('post_id' => $post_id) ); ?>
        </div>
    </section>

<?php
// Display related ROMs
if ( $console && isset( $console->term_id ) ) :
    $related_args = array(
            'post_type'      => $post->post_type,
            'post_status'    => 'publish',
            'posts_per_page' => 6,
            'post__not_in'   => array( $post_id ),
            'no_found_rows'  => true,
            'tax_query'      => array(
                    array(
                            'taxonomy' => 'console',
                            'field'    => 'term_id',
                            'terms'    => $console->term_id
                    )
            )
    );
    $related_query = new WP_Query( $related_args );

    if ( $related_query->have_posts() ) :
        ?>
        <section class="pb-6 text-gray-900">
            <div class="content-container px-4 sm:px-6 lg:px-8 w-full">
                <h2 class="font-bold text-black text-lg mb-4">
                    <?php esc_html_e( 'Recommended for you', 'rom' ); ?>
                </h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 md:gap-4">
                    <?php
                    while ( $related_query->have_posts() ) :
                        $related_query->the_post();
                        get_template_part( 'template-parts/rom-card' );
                    endwhile;
                    wp_reset_postdata();
                    ?>
                </div>
            </div>
        </section>
    <?php
    endif;
endif;
?>

<?php
get_footer();