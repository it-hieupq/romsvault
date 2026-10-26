<?php
/**
 * Emulator card, laid out like the rom card.
 *
 * A plain cell with no width of its own: the surrounding template owns the
 * columns and the gap.
 */
$card_id       = get_the_ID();
$card_consoles = get_the_terms( $card_id, 'emulator_console' );
$card_console  = ( ! is_wp_error( $card_consoles ) && ! empty( $card_consoles ) ) ? $card_consoles[0] : null;
// Badge colours live on the rom console term that shares the slug.
$card_color_term = $card_console ? get_term_by( 'slug', $card_console->slug, 'console' ) : null;

$card_os       = get_the_terms( $card_id, 'os' );
$card_os_names = ( ! is_wp_error( $card_os ) && ! empty( $card_os ) ) ? wp_list_pluck( $card_os, 'name' ) : array();

// Same rule as the rom card: the star badge only shows once there is a review.
$card_rating = rom_rating( $card_id );

$card_size     = get_post_meta( $card_id, 'file_size', true );
$card_thumb_id = get_post_thumbnail_id( $card_id );
?>
<a href="<?php the_permalink(); ?>" class="group bg-white rounded-2xl border border-slate-200/80 overflow-hidden shadow-subtle hover:shadow-card-hover hover:border-primary/50 transition-all flex flex-col">
    <?php // Emulator art is a logo, so it sits small in the middle instead of filling the frame. ?>
    <div class="relative aspect-[3/4] overflow-hidden bg-gradient-to-br from-slate-50 to-slate-100 flex items-center justify-center" style="padding:20%">
		<?php if ( $card_thumb_id ) :
			echo wp_get_attachment_image( $card_thumb_id, 'thumbnail', false, array(
				'alt'     => the_title_attribute( array( 'echo' => false ) ),
				'sizes'   => '160px',
				'loading' => 'lazy',
				'class'   => 'w-full h-full object-contain group-hover:scale-105 transition-transform duration-300',
			) );
		endif; ?>

        <span class="absolute right-2.5 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider shadow-sm" style="top:0.625rem;background-color:#facc15;color:#422006"><?php esc_html_e( 'Emulator', 'rom' ); ?></span>
    </div>

    <div class="p-3.5 space-y-1.5">
        <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-1">
			<?php if ( $card_console ) : ?>
                <span class="px-2 py-0.5 rounded-md bg-primary text-white text-[10px] font-bold uppercase tracking-wider shadow-sm" style="<?php echo esc_attr( rom_console_badge_style( $card_color_term instanceof WP_Term ? $card_color_term : null ) ); ?>"><?php echo esc_html( $card_console->name ); ?></span>
			<?php else : ?>
                <span></span>
			<?php endif; ?>
            <span class="flex items-center gap-1.5 text-[11px] font-semibold text-slate-500 flex-shrink-0">
				<?php if ( $card_rating['count'] ) : ?>
                <span class="inline-flex items-center gap-0.5 text-slate-700">
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

		<?php if ( $card_os_names ) : ?>
            <p class="text-[12px] text-slate-500 font-medium truncate"><?php echo esc_html( implode( ', ', $card_os_names ) ); ?></p>
		<?php endif; ?>
    </div>
</a>
