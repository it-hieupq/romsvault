<?php
/**
 * Blog post card for a CSS grid.
 *
 * The post counterpart of rom-card.php: same shell, so a news grid and a rom
 * grid read as one design. It is a plain cell with no width of its own: the
 * surrounding template owns the columns and the gap, and a
 * .horizontal-scroll-wrapper caller wraps it in a fixed-width flex item.
 */
$card_id         = get_the_ID();
$card_categories = get_the_category( $card_id );
$card_category   = ! empty( $card_categories ) ? $card_categories[0] : null;

// 150px 'thumbnail' is a hard square crop; the 16:9 frame needs a wider source.
$card_thumb    = get_the_post_thumbnail_url( $card_id, 'medium_large' );
$card_views    = (int) get_post_meta( $card_id, 'views', true );
$card_comments = (int) get_comments_number( $card_id );
$card_minutes  = max( 1, (int) ceil( str_word_count( wp_strip_all_tags( get_the_content() ) ) / 200 ) );
?>
<article class="group relative bg-white rounded-2xl border border-slate-200/80 overflow-hidden shadow-subtle hover:shadow-card-hover hover:border-primary/50 transition-all flex flex-col">
    <div class="relative aspect-video overflow-hidden bg-slate-100">
		<?php if ( $card_thumb ) : ?>
            <img src="<?php echo esc_url( $card_thumb ); ?>" alt="<?php the_title_attribute(); ?>"
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy">
		<?php endif; ?>

		<?php if ( $card_category ) : ?>
            <span class="absolute top-3 left-3 px-2 py-0.5 rounded-md bg-primary text-white text-[10px] font-bold uppercase tracking-wider shadow-sm shadow-primary/20"><?php echo esc_html( $card_category->name ); ?></span>
		<?php endif; ?>
    </div>

    <div class="p-4 flex flex-col flex-1 gap-2">
        <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            <time datetime="<?php echo esc_attr( get_the_modified_date( 'c' ) ); ?>"><?php echo esc_html( get_the_modified_date() ); ?></time>
            <span class="w-1 h-1 rounded-full bg-slate-300" aria-hidden="true"></span>
            <span><?php echo esc_html( sprintf( _n( '%d min read', '%d min read', $card_minutes, 'rom' ), $card_minutes ) ); ?></span>
        </div>

        <h3 class="font-bold text-base text-slate-900 group-hover:text-primary transition-colors line-clamp-2 leading-snug">
            <?php // The stretched ::after makes the whole card the link target without
                  // wrapping block content, and keeps one link per card for screen readers. ?>
            <a href="<?php the_permalink(); ?>" class="after:absolute after:inset-0"><?php the_title(); ?></a>
        </h3>

        <p class="text-meta text-slate-500 line-clamp-2"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></p>

        <div class="mt-auto pt-3 flex items-center gap-4 text-[12px] font-medium text-slate-500 border-t border-slate-100">
            <span class="inline-flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.04 12.32a1 1 0 0 1 0-.64C3.42 7.51 7.36 4.5 12 4.5c4.64 0 8.57 3.01 9.96 7.18a1 1 0 0 1 0 .64C20.58 16.49 16.64 19.5 12 19.5c-4.64 0-8.57-3.01-9.96-7.18Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                </svg>
                <?php echo esc_html( number_format_i18n( $card_views ) ); ?>
            </span>
            <span class="inline-flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.42-4.03 8-9 8a9.86 9.86 0 0 1-4.26-.95L3 20l1.4-3.72A7.4 7.4 0 0 1 3 12c0-4.42 4.03-8 9-8s9 3.58 9 8Z"/>
                </svg>
                <?php echo esc_html( number_format_i18n( $card_comments ) ); ?>
            </span>
            <span class="ml-auto inline-flex items-center gap-1 text-primary opacity-0 -translate-x-1 group-hover:opacity-100 group-hover:translate-x-0 transition-all">
                <?php esc_html_e( 'Read', 'rom' ); ?>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/>
                </svg>
            </span>
        </div>
    </div>
</article>
