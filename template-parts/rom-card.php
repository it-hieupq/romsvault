<?php
/**
 * Rom card, the only rom listing item in the theme.
 *
 * A plain cell with no width of its own: the surrounding template owns the
 * columns and the gap, and a .horizontal-scroll-wrapper caller wraps it in a
 * fixed-width flex item.
 */
$card_id       = get_the_ID();
$card_consoles = get_the_terms( $card_id, 'console' );
$card_console  = ( ! is_wp_error( $card_consoles ) && ! empty( $card_consoles ) ) ? $card_consoles[0] : null;

// Up to two regions as "🇺🇸 USA · 🇪🇺 Europe", then "+N"; every name is in the tooltip.
$card_regions      = rom_sort_regions( get_the_terms( $card_id, 'region' ) );
$card_region_shown = array_slice( $card_regions, 0, 2 );
$card_region_more  = count( $card_regions ) - count( $card_region_shown );
$card_region_bits  = array();
foreach ( $card_region_shown as $card_region ) {
	// Each flag + name stays on one line when the badge wraps.
	$card_region_bits[] = '<span class="whitespace-nowrap">' . esc_html( trim( rom_region_flag( $card_region->slug ) . ' ' . $card_region->name ) ) . '</span>';
}

$card_genres      = get_the_terms( $card_id, 'genre' );
$card_genre_names = array();
if ( ! is_wp_error( $card_genres ) && ! empty( $card_genres ) ) {
	foreach ( $card_genres as $card_genre ) {
		$card_genre_names[] = $card_genre->name;
	}
}

// The star badge only shows once the rom has a review.
$card_rating = rom_rating( $card_id );

$card_size = get_post_meta( $card_id, 'file_size', true );
if ( empty( $card_size ) ) {
	$card_size = get_post_meta( $card_id, 'download_list_0_file_size', true );
}

$card_thumb_id = get_post_thumbnail_id( $card_id );
$card_thumb    = $card_thumb_id ? wp_get_attachment_image_src( $card_thumb_id, 'thumbnail' ) : false;
// Box art comes in every shape. Close to the 2:3 frame it fills it; anything
// else sits whole inside the frame over a blurred copy of itself.
$card_ratio = ( $card_thumb && $card_thumb[2] ) ? $card_thumb[1] / $card_thumb[2] : 2 / 3;
$card_fill  = $card_ratio >= 0.60 && $card_ratio <= 0.76;
// Both copies share sizes so the browser picks one file for both.
$card_sizes = '(min-width: 1400px) 260px, (min-width: 1280px) 20vw, (min-width: 1024px) 25vw, (min-width: 640px) 33vw, 50vw';
?>
<a href="<?php the_permalink(); ?>" class="group bg-white rounded-2xl border border-slate-200/80 overflow-hidden shadow-subtle hover:shadow-card-hover hover:border-primary/50 transition-all flex flex-col">
    <div class="relative aspect-[2/3] overflow-hidden bg-slate-100">
		<?php if ( $card_thumb ) :
			if ( ! $card_fill ) :
				echo wp_get_attachment_image( $card_thumb_id, 'thumbnail', false, array(
					'alt'         => '',
					'aria-hidden' => 'true',
					'sizes'       => $card_sizes,
					'loading'     => 'lazy',
					'class'       => 'absolute inset-0 w-full h-full object-cover scale-110 blur-xl brightness-75',
				) );
			endif;
			echo wp_get_attachment_image( $card_thumb_id, 'thumbnail', false, array(
				'alt'     => the_title_attribute( array( 'echo' => false ) ),
				'sizes'   => $card_sizes,
				'loading' => 'lazy',
				'class'   => 'relative w-full h-full ' . ( $card_fill ? 'object-cover object-top' : 'object-contain' ) . ' group-hover:scale-105 transition-transform duration-300',
			) );
		endif; ?>

		<?php if ( $card_region_bits ) : ?>
            <?php // Long lists wrap inside the cover instead of running off its left edge. ?>
            <span class="absolute bottom-2.5 right-2.5 px-2 py-0.5 rounded-md bg-slate-900/80 text-slate-200 text-[10px] font-bold uppercase backdrop-blur-sm" style="max-width:calc(100% - 1.25rem);text-align:right" title="<?php echo esc_attr( implode( ', ', wp_list_pluck( $card_regions, 'name' ) ) ); ?>"><?php echo implode( ' · ', $card_region_bits ); // Each bit is escaped above. ?><?php if ( $card_region_more ) : ?> <span class="text-slate-400">+<?php echo (int) $card_region_more; ?></span><?php endif; ?></span>
		<?php endif; ?>
    </div>

    <div class="p-3.5 space-y-1.5">
        <?php // Wraps on narrow cards so a long console name never pushes the rating off the edge. ?>
        <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-1">
			<?php if ( $card_console ) : ?>
                <span class="px-2 py-0.5 rounded-md bg-primary text-white text-[10px] font-bold uppercase tracking-wider shadow-sm" style="<?php echo esc_attr( rom_console_badge_style( $card_console ) ); ?>"><?php echo esc_html( $card_console->name ); ?></span>
			<?php else : ?>
                <span></span>
			<?php endif; ?>
            <span class="flex items-center gap-1.5 text-[11px] font-semibold text-slate-500 flex-shrink-0">
				<?php if ( $card_rating['count'] ) : ?>
                <span class="inline-flex items-center gap-0.5 text-slate-700">
                    <?php // SVG, not a star glyph: the body webfont is a latin-only subset, so a
                          // literal U+2605 falls through to whatever the OS happens to provide. ?>
                    <svg class="w-3 h-3 text-amber-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path d="M10 15.27 16.18 19l-1.64-7.03L20 7.24l-7.19-.62L10 0 7.19 6.62 0 7.24l5.46 4.73L3.82 19z"/>
                    </svg>
                    <?php echo esc_html( number_format_i18n( $card_rating['score'], 1 ) ); ?>
                </span>
				<?php endif; ?>
				<?php if ( ! empty( $card_size ) ) : ?>
					<?php if ( $card_rating['count'] ) : ?><span class="text-slate-300" aria-hidden="true">&middot;</span><?php endif; ?>
                    <span><?php echo esc_html( $card_size ); ?></span>
				<?php endif; ?>
            </span>
        </div>

        <h3 class="font-bold text-meta text-slate-900 group-hover:text-primary transition-colors line-clamp-2 leading-snug"><?php the_title(); ?></h3>

		<?php if ( $card_genre_names ) : ?>
            <p class="text-[12px] text-slate-500 font-medium truncate"><?php echo esc_html( implode( ', ', $card_genre_names ) ); ?></p>
		<?php endif; ?>
    </div>
</a>
