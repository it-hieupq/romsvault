<?php
/**
 * Rom card for a CSS grid.
 *
 * rom-item-grid.php is the fixed-width item of a .horizontal-scroll-wrapper and
 * carries its own flex-shrink/width/padding; this one is a plain grid cell, so the
 * surrounding template owns the columns and the gap.
 */
$card_id       = get_the_ID();
$card_consoles = get_the_terms( $card_id, 'console' );
$card_console  = ( ! is_wp_error( $card_consoles ) && ! empty( $card_consoles ) ) ? $card_consoles[0] : null;

$card_regions = get_the_terms( $card_id, 'region' );
$card_region  = ( ! is_wp_error( $card_regions ) && ! empty( $card_regions ) ) ? $card_regions[0] : null;

$card_genres      = get_the_terms( $card_id, 'genre' );
$card_genre_names = array();
if ( ! is_wp_error( $card_genres ) && ! empty( $card_genres ) ) {
	foreach ( $card_genres as $card_genre ) {
		$card_genre_names[] = $card_genre->name;
	}
}

$card_count = (int) get_post_meta( $card_id, 'rating_count', true );
$card_score = (float) get_post_meta( $card_id, 'rating_avg', true );

$card_size = get_post_meta( $card_id, 'file_size', true );
if ( empty( $card_size ) ) {
	$card_size = get_post_meta( $card_id, 'download_list_0_file_size', true );
}

$card_thumb = get_the_post_thumbnail_url( $card_id, 'thumbnail' );
?>
<a href="<?php the_permalink(); ?>" class="group bg-white rounded-2xl border border-slate-200/80 overflow-hidden shadow-subtle hover:shadow-card-hover hover:border-primary/50 transition-all flex flex-col">
    <div class="relative aspect-[3/4] overflow-hidden bg-slate-100">
		<?php if ( $card_thumb ) : ?>
            <img src="<?php echo esc_url( $card_thumb ); ?>" alt="<?php the_title_attribute(); ?>"
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy">
		<?php endif; ?>

		<?php if ( $card_count > 0 ) : ?>
            <div class="absolute top-2.5 left-2.5 flex items-center gap-1 px-2 py-0.5 rounded-md bg-slate-950/80 text-white text-[11px] font-bold backdrop-blur-sm shadow-sm">
                <span class="text-amber-400 text-[10px]">&#9733;</span>
                <span><?php echo esc_html( round( $card_score, 1 ) ); ?></span>
            </div>
		<?php endif; ?>

		<?php if ( $card_region ) : ?>
            <span class="absolute top-2.5 right-2.5 px-2 py-0.5 rounded-md bg-slate-900/80 text-slate-200 text-[10px] font-bold uppercase backdrop-blur-sm"><?php echo esc_html( $card_region->name ); ?></span>
		<?php endif; ?>
    </div>

    <div class="p-3.5 space-y-1.5">
        <div class="flex items-center justify-between gap-2">
			<?php if ( $card_console ) : ?>
                <span class="px-2 py-0.5 rounded-md bg-primary text-white text-[10px] font-bold uppercase tracking-wider shadow-sm shadow-primary/20"><?php echo esc_html( $card_console->name ); ?></span>
			<?php else : ?>
                <span></span>
			<?php endif; ?>
			<?php if ( ! empty( $card_size ) ) : ?>
                <span class="text-[11px] font-semibold text-slate-500 flex-shrink-0"><?php echo esc_html( $card_size ); ?></span>
			<?php endif; ?>
        </div>

        <h3 class="font-bold text-meta text-slate-900 group-hover:text-primary transition-colors line-clamp-2 leading-snug"><?php the_title(); ?></h3>

		<?php if ( $card_genre_names ) : ?>
            <p class="text-[12px] text-slate-500 font-medium truncate"><?php echo esc_html( implode( ', ', $card_genre_names ) ); ?></p>
		<?php endif; ?>
    </div>
</a>
