<?php
/**
 * Collection card: the banner with the short description laid over it, then the
 * name and the rom count on one row.
 *
 * Expects $args['term'], a 'collection' WP_Term. A plain grid cell with no width
 * of its own: the surrounding template owns the columns and the gap.
 */
$card_term = $args['term'] ?? null;
if ( ! $card_term instanceof WP_Term ) {
	return;
}

$card_banner = get_field( 'collection_banner', 'term_' . $card_term->term_id );
$card_desc   = wp_strip_all_tags( $card_term->description );
$card_count  = (int) $card_term->count;
?>
<a href="<?php echo esc_url( get_term_link( $card_term ) ); ?>" class="group bg-white rounded-2xl border border-slate-200/80 overflow-hidden shadow-subtle hover:shadow-card-hover hover:border-primary/50 transition-all flex flex-col">
    <div class="relative aspect-video overflow-hidden bg-gradient-to-br from-slate-200 to-slate-100">
		<?php if ( ! empty( $card_banner['ID'] ) ) :
			// The banner only has a 300px 'thumbnail' size; srcset lets wide/retina cells pull the original.
			echo wp_get_attachment_image( $card_banner['ID'], 'thumbnail', false, array(
				'class'   => 'w-full h-full object-cover group-hover:scale-105 transition-transform duration-300',
				'alt'     => $card_term->name,
				'loading' => 'lazy',
				'sizes'   => '(min-width: 1400px) 440px, (min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw',
			) );
		endif; ?>

		<?php if ( $card_desc ) : ?>
            <div class="absolute inset-x-0 bottom-0 h-3/5 flex items-end bg-gradient-to-t from-black/90 via-black/60 to-transparent px-3.5 pb-3">
                <p class="text-[12px] leading-snug text-white line-clamp-2 [text-shadow:0_1px_2px_rgb(0_0_0/0.8)]"><?php echo esc_html( $card_desc ); ?></p>
            </div>
		<?php endif; ?>
    </div>

    <div class="p-3.5 flex items-center justify-between gap-3">
        <h3 class="font-bold text-meta text-slate-900 group-hover:text-primary transition-colors truncate"><?php echo esc_html( $card_term->name ); ?></h3>
        <span class="flex-shrink-0 px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 text-[11px] font-semibold">
			<?php
			/* translators: %s: number of roms in the collection */
			echo esc_html( sprintf( _n( '%s ROM', '%s ROMs', $card_count, 'rom' ), number_format_i18n( $card_count ) ) );
			?>
        </span>
    </div>
</a>
