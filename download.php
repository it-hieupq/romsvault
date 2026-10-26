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

// A file number the post doesn't have answers 404, but still renders the page
// chrome so the reader can pick one of the files that do exist.
$file_missing = $file_number && ( empty( $downloads[ $file_number - 1 ]['name'] ) || empty( $downloads[ $file_number - 1 ]['url'] ) );
if ( $file_missing ) {
    status_header( 404 );
    nocache_headers();
}

get_header();

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

$is_emulator  = ( 'emulator' === $post->post_type );
$normal_count = is_array( $normal_downloads ) ? count( $normal_downloads ) : 0;

/**
 * Files grouped as the endpoint numbers them: direct links first, then the
 * affiliate mirrors, 1-based across both (inc/ajax.php, inc/download-token.php).
 */
$file_groups = array(
    array( 'label' => __( 'Direct downloads', 'rom' ), 'items' => array() ),
    array( 'label' => __( 'Mirror links', 'rom' ), 'items' => array() ),
);
foreach ( array( 0 => $normal_downloads, 1 => $aff_downloads ) as $group_key => $group_files ) {
    if ( ! is_array( $group_files ) ) {
        continue;
    }
    foreach ( $group_files as $key => $value ) {
        if ( empty( $value['name'] ) || empty( $value['url'] ) ) {
            continue;
        }
        $file_groups[ $group_key ]['items'][] = array(
            'n'    => $key + 1 + ( $group_key ? $normal_count : 0 ),
            'file' => $value,
        );
    }
}

// Archive extension read off the link (zip, 7z, iso...), as on the single page.
$file_ext = function ( $url ) {
    $ext = strtolower( pathinfo( (string) wp_parse_url( (string) $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
    return preg_match( '/^[a-z0-9]{1,5}$/', $ext ) ? $ext : '';
};

// One card per group of files; $skip is the file number already open on the page.
$render_file_groups = function ( $skip = 0 ) use ( $file_groups, $file_ext, $post_id ) {
    foreach ( $file_groups as $group ) {
        $items = array_filter( $group['items'], function ( $item ) use ( $skip ) {
            return $item['n'] !== $skip;
        } );
        if ( ! $items ) {
            continue;
        }
        ?>
        <div>
            <h3 class="flex items-center gap-2 text-caption font-bold uppercase tracking-widest text-slate-400 mb-2.5">
                <?php echo esc_html( $group['label'] ); ?>
                <span class="px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-500 tracking-normal"><?php echo esc_html( count( $items ) ); ?></span>
            </h3>
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-card divide-y divide-slate-100 overflow-hidden">
                <?php foreach ( $items as $item ) :
                    $f   = $item['file'];
                    $ext = $file_ext( $f['url'] );
                    ?>
                    <a href="<?php echo esc_url( rom_download_url( $post_id, $item['n'] ) ); ?>" target="_blank" rel="nofollow"
                       class="group flex items-center gap-3 sm:gap-4 p-4 sm:px-5 hover:bg-rose-50/40 transition-colors">
                        <span class="w-10 h-10 rounded-xl bg-rose-50 text-primary flex items-center justify-center font-bold text-caption uppercase flex-shrink-0">
                            <?php echo esc_html( $ext ? $ext : '#' . $item['n'] ); ?>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-bold text-meta sm:text-body text-slate-900 group-hover:text-primary transition-colors break-words"><?php echo esc_html( $f['name'] ); ?></span>
                            <span class="flex flex-wrap items-center gap-x-2.5 gap-y-0.5 text-caption text-slate-500 mt-0.5">
                                <?php if ( ! empty( $f['file_type'] ) ) : ?>
                                    <span><?php esc_html_e( 'Format:', 'rom' ); ?> <strong class="text-slate-700"><?php echo esc_html( $f['file_type'] ); ?></strong></span>
                                <?php endif; ?>
                                <?php if ( ! empty( $f['file_size'] ) ) : ?>
                                    <span><?php esc_html_e( 'Size:', 'rom' ); ?> <strong class="text-slate-700"><?php echo esc_html( $f['file_size'] ); ?></strong></span>
                                <?php endif; ?>
                            </span>
                        </span>
                        <span class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center flex-shrink-0 shadow-sm shadow-primary/25 group-hover:bg-primary-hover transition-colors" aria-hidden="true">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v11m0 0-4.5-4.5M12 15l4.5-4.5M5 19h14"/></svg>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
};

$file_total = count( $file_groups[0]['items'] ) + count( $file_groups[1]['items'] );
$thumb_url  = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
$guide      = $console ? get_field( 'console_guide', 'term_' . $console->term_id ) : '';
$note       = get_field( 'download_note', $post_id );
?>

    <!-- Breadcrumbs bar -->
    <div class="w-full bg-white/50 border-b border-slate-200/60 py-2.5">
        <div class="max-w-content mx-auto px-4 sm:px-6">
            <?php k_breadcrumb(); ?>
        </div>
    </div>

    <!-- Header: what is being downloaded -->
    <section class="pt-6 sm:pt-8">
        <div class="max-w-content mx-auto px-4 sm:px-6">
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-card p-5 sm:p-7">
                <div class="flex items-center gap-4 sm:gap-5">
                    <?php if ( $thumb_url ) : ?>
                        <img src="<?php echo esc_url( $thumb_url ); ?>" alt=""
                             class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl border border-slate-200/80 bg-slate-50 flex-shrink-0 <?php echo $is_emulator ? 'object-contain p-2' : 'object-cover'; ?>">
                    <?php endif; ?>
                    <div class="min-w-0 flex-1">
                        <span class="text-caption font-bold uppercase tracking-widest text-amber-600"><?php esc_html_e( 'Download', 'rom' ); ?></span>
                        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight leading-snug mt-0.5"><?php echo esc_html( get_the_title( $post_id ) ); ?></h1>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 mt-2 text-meta text-slate-500">
                            <?php if ( $console ) : ?>
                                <a href="<?php echo esc_url( get_term_link( $console ) ); ?>"
                                   class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-rose-50 text-primary text-caption font-bold border border-rose-200/60 hover:bg-rose-100 transition-colors [&_svg]:w-3.5 [&_svg]:h-3.5 [&_svg]:mr-0"><?php echo get_svg_icon( 'joystick' ) . esc_html( $console->name ); ?></a>
                            <?php endif; ?>
                            <span class="inline-flex items-center gap-1.5">
                                <span class="rating"
                                      data-post_id="<?php echo esc_attr( $post_id ); ?>"
                                      data-rateyo-rating="<?php echo esc_attr( $score ); ?>"
                                      <?php if ( $has_rated ) : ?>data-rateyo-read-only="true"<?php endif; ?>></span>
                                <span class="font-bold text-slate-700"><?php echo esc_html( $score ); ?></span>
                                <span class="text-caption text-slate-400">(<?php echo esc_html( $count . ' ' . _n( 'review', 'reviews', $count, 'rom' ) ); ?>)</span>
                            </span>
                            <span class="text-slate-300">&bull;</span>
                            <span><?php echo esc_html( sprintf( _n( '%s file', '%s files', $file_total, 'rom' ), number_format_i18n( $file_total ) ) ); ?></span>
                        </div>
                    </div>
                    <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"
                       class="hidden sm:inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full bg-slate-100 text-slate-700 font-semibold text-xs hover:bg-slate-200 transition-colors flex-shrink-0">
                        &larr; <?php esc_html_e( 'Back to details', 'rom' ); ?>
                    </a>
                </div>

                <?php if ( $note || $guide ) : ?>
                    <div class="mt-5 pt-5 border-t border-slate-100 space-y-3">
                        <?php if ( $note ) : ?>
                            <details class="group rounded-2xl bg-amber-50/80 border border-amber-200/80" open>
                                <summary class="flex items-center justify-between gap-3 px-4 py-3 cursor-pointer list-none text-meta font-bold text-amber-950">
                                    <span class="inline-flex items-center gap-2 [&_svg]:w-4 [&_svg]:h-4 [&_svg]:mr-0 [&_svg]:text-amber-600"><?php echo get_svg_icon( 'info' ) . esc_html__( 'Download Notice', 'rom' ); ?></span>
                                    <svg class="w-4 h-4 text-amber-500 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                </summary>
                                <div class="px-4 pb-4 text-caption text-amber-900/90 leading-relaxed"><?php echo wpautop( wp_kses_post( $note ) ); ?></div>
                            </details>
                        <?php endif; ?>
                        <?php if ( $guide ) : ?>
                            <?php // Open by default; the toggle lets readers fold the guide away. ?>
                            <details class="group rounded-2xl bg-slate-50 border border-slate-200/70" open>
                                <summary class="flex items-center justify-between gap-3 px-4 py-3 cursor-pointer list-none text-meta font-bold text-slate-800">
                                    <span class="inline-flex items-center gap-2 [&_svg]:w-4 [&_svg]:h-4 [&_svg]:mr-0 [&_svg]:text-primary"><?php echo get_svg_icon( 'noti' ) . esc_html( sprintf( __( '%s setup guide', 'rom' ), $console->name ) ); ?></span>
                                    <svg class="w-4 h-4 text-slate-400 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                </summary>
                                <div class="revert page-content px-4 pb-4 text-meta text-slate-700"><?php echo wp_kses_post( $guide ); ?></div>
                            </details>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

<?php
// File list: no specific file picked yet.
if ( ! $file_number ) :
    ?>
    <section class="py-6 sm:py-8">
        <div class="max-w-content mx-auto px-4 sm:px-6 space-y-6">
            <?php $render_file_groups(); ?>
        </div>
    </section>
<?php
// A file number this post doesn't have.
elseif ( $file_missing ) :
    ?>
    <section class="py-6 sm:py-8">
        <div class="max-w-content mx-auto px-4 sm:px-6 space-y-6">
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-card px-5 py-10 sm:py-12 text-center">
                <span class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-rose-50 text-primary mb-4">
                    <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2z"/><path d="m10 12 4 4m0-4-4 4"/></svg>
                </span>
                <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight"><?php esc_html_e( 'This file doesn\'t exist', 'rom' ); ?></h2>
                <p class="text-meta text-slate-500 mt-1.5 max-w-md mx-auto">
                    <?php echo esc_html( sprintf( __( 'File #%1$s isn\'t available for %2$s. It may have been removed, or the link is wrong.', 'rom' ), number_format_i18n( $file_number ), get_the_title( $post_id ) ) ); ?>
                </p>
                <div class="flex flex-wrap items-center justify-center gap-3 mt-5">
                    <a href="<?php echo esc_url( rom_download_url( $post_id ) ); ?>" rel="nofollow"
                       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-primary text-white font-bold text-xs hover:bg-primary-hover active:scale-95 transition-all shadow-sm shadow-primary/25">
                        <?php esc_html_e( 'See all files', 'rom' ); ?>
                    </a>
                    <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"
                       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-slate-100 text-slate-700 font-semibold text-xs hover:bg-slate-200 transition-colors">
                        &larr; <?php esc_html_e( 'Back to details', 'rom' ); ?>
                    </a>
                </div>
            </div>
            <?php if ( $file_total ) : ?>
                <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight"><?php esc_html_e( 'Available files', 'rom' ); ?></h2>
                <?php $render_file_groups(); ?>
            <?php endif; ?>
        </div>
    </section>
<?php
// Download page for one file.
else :
    $file_index = $file_number - 1;
    $link       = $downloads[ $file_index ];

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
    $rf_members   = rf_is_restricted( $post_id );
    $download_url = $rf_members ? '' : add_query_arg( 'token', urlencode( get_download_token() ), $link['url'] );
    $file_name    = $link['name'];
    $file_type    = isset( $link['file_type'] ) ? $link['file_type'] : '';
    $file_size    = isset( $link['file_size'] ) ? $link['file_size'] : '';
    $current_ext  = $file_ext( $link['url'] );
    ?>
    <section class="py-6 sm:py-8">
        <div class="max-w-content mx-auto px-4 sm:px-6">
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-card overflow-hidden">
                <!-- File summary -->
                <div class="flex items-center gap-4 p-5 sm:p-6 border-b border-slate-100 bg-slate-50/60">
                    <span class="w-12 h-12 rounded-2xl bg-rose-50 text-primary flex items-center justify-center font-bold text-meta uppercase flex-shrink-0">
                        <?php echo esc_html( $current_ext ? $current_ext : '#' . $file_number ); ?>
                    </span>
                    <div class="min-w-0">
                        <span class="text-caption font-bold uppercase tracking-widest text-slate-400"><?php esc_html_e( 'You are downloading', 'rom' ); ?></span>
                        <h2 class="font-bold text-meta sm:text-body text-slate-900 leading-snug break-words"><?php echo esc_html( $file_name ); ?></h2>
                        <div class="flex flex-wrap items-center gap-x-2.5 text-caption text-slate-500 mt-0.5">
                            <?php if ( $file_type ) : ?>
                                <span><?php esc_html_e( 'Format:', 'rom' ); ?> <strong class="text-slate-700"><?php echo esc_html( $file_type ); ?></strong></span>
                            <?php endif; ?>
                            <?php if ( $file_size ) : ?>
                                <span><?php esc_html_e( 'Size:', 'rom' ); ?> <strong class="text-slate-700"><?php echo esc_html( $file_size ); ?></strong></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div id="download-container" class="text-center px-5 py-8 sm:py-10" data-ajax-url="<?php echo esc_attr( admin_url('admin-ajax.php') ); ?>" data-post="<?php echo esc_attr( $post_id ); ?>" data-file="<?php echo esc_attr( $file_number ); ?>">
                    <?php if ( $rf_members ) : ?>
                    <!-- Restricted: countdown + button are for active members only (guests/blocked
                         see the notice below). Wrap them here rather than putting rf-authed-only on
                         the elements themselves — that class outranks the JS `.hidden` toggle
                         (html.rf-authed .rf-authed-only beats .hidden), which would leave the
                         countdown stuck and the button showing early. -->
                    <div class="rf-authed-only rf-hide-blocked">
                    <?php endif; ?>
                    <!-- Loading State (countdown) -->
                    <div id="download-loading" class="max-w-md mx-auto">
                        <div class="flex items-end justify-between gap-3 mb-2.5">
                            <span id="countdown-text" class="text-meta font-semibold text-slate-600"><?php esc_html_e( 'Preparing a secure download link…', 'rom' ); ?></span>
                            <span class="text-base font-extrabold text-primary tabular-nums leading-none"><span id="countdown">0</span>%</span>
                        </div>
                        <?php // main.js scales the fill from 0 to 100% per animation frame (transform, not width, so it never re-lays out). ?>
                        <div class="h-3 rounded-full bg-rose-100 overflow-hidden" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-label="<?php esc_attr_e( 'Download preparation', 'rom' ); ?>">
                            <div id="download-progress" class="h-full w-full rounded-full bg-gradient-to-r from-rose-400 to-primary origin-left will-change-transform" style="transform:scaleX(0)"></div>
                        </div>
                    </div>

                    <!-- Download Button (Hidden initially) -->
                    <div id="download-button" class="hidden">
                        <a href="<?php echo $rf_members ? '#' : esc_url( $download_url ); ?>"
                           id="download-link"
                           <?php if ( $rf_members ) : ?>data-rf-download data-post="<?php echo esc_attr( $post_id ); ?>" data-file="<?php echo esc_attr( $file_number ); ?>"<?php endif; ?>
                           class="inline-flex items-center justify-center gap-1.5 px-5 py-2.5 rounded-full bg-primary text-white font-bold text-xs hover:bg-primary-hover active:scale-95 transition-all shadow-md shadow-primary/25 [&_svg]:w-3.5 [&_svg]:h-3.5 [&_svg]:mr-0"
                           rel="nofollow">
                            <?php echo get_svg_icon( 'save' ) . esc_html( __( 'Download Now', 'rom' ) . ( $file_size ? ' (' . $file_size . ')' : '' ) ); ?>
                        </a>
                        <p class="text-caption text-slate-500 mt-3">
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

    <?php if ( $file_total > 1 ) : ?>
        <!-- The other files of this post -->
        <section class="pb-6 sm:pb-8">
            <div class="max-w-content mx-auto px-4 sm:px-6 space-y-6">
                <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight"><?php esc_html_e( 'Other files', 'rom' ); ?></h2>
                <?php $render_file_groups( $file_number ); ?>
            </div>
        </section>
    <?php endif;
endif;
?>

    <section class="pb-6">
        <div class="max-w-content mx-auto px-4 sm:px-6">
            <?php get_template_part( 'template-parts/report-form', null, array('post_id' => $post_id) ); ?>
        </div>
    </section>

<?php
// Neighbours by publish date in the same console, laid out like the single page's
// "You Might Also Like" row. download.php serves roms and emulators alike, so the
// card and column widths follow the post type.
if ( $console && isset( $console->term_id ) ) :
    $is_emulator   = ( 'emulator' === $post->post_type );
    $related_query = rom_nearby_query( $post, $console, $is_emulator ? 6 : 10 );

    if ( $related_query->have_posts() ) :
        ?>
        <section class="py-8 sm:py-12 border-t border-slate-200/80">
            <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between mb-6 gap-3">
                    <div>
                        <span class="text-caption font-bold uppercase tracking-widest text-amber-600">
                            <?php echo esc_html( sprintf( __( 'More %s', 'rom' ), $console->name ) ); ?>
                        </span>
                        <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight mt-0.5"><?php esc_html_e( 'Recommended for you', 'rom' ); ?></h2>
                    </div>
                    <a href="<?php echo esc_url( get_term_link( $console ) ); ?>"
                       class="text-caption sm:text-meta font-bold text-primary hover:text-primary-hover flex-shrink-0"><?php esc_html_e( 'View all', 'rom' ); ?> &rarr;</a>
                </div>
                <div class="horizontal-scroll-container">
                    <div class="horizontal-scroll-wrapper">
                        <?php while ( $related_query->have_posts() ) : $related_query->the_post(); ?>
                            <div class="flex-shrink-0 <?php echo $is_emulator ? 'w-1/2 md:w-1/3 lg:w-1/4' : 'w-1/2 md:w-1/3 lg:w-1/4 xl:w-1/5'; ?> min-w-0 px-1.5 md:px-2 box-border grid">
                                <?php get_template_part( $is_emulator ? 'template-parts/emu-item' : 'template-parts/rom-card' ); ?>
                            </div>
                        <?php endwhile; wp_reset_postdata(); ?>
                    </div>
                </div>
            </div>
        </section>
    <?php
    endif;
endif;
?>

<?php
get_footer();