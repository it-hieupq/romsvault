<?php
/*
 * Pagination
 */
/**
 * One pill bar: Prev, the first page, $range pages either side of the current
 * one, the last page, Next. Skipped runs collapse into "…". Prev/Next stay in
 * place (disabled) at either end so the bar doesn't jump while paging.
 *
 * @param int|string $pages Total pages; defaults to the main query's.
 * @param int        $range Pages shown on each side of the current one.
 */
function rom_pagination( $pages = '', $range = 1 ) {
	global $paged;
	$current = max( 1, (int) $paged );
	if ( '' === $pages ) {
		global $wp_query;
		$pages = $wp_query->max_num_pages;
	}
	$pages = max( 1, (int) $pages );
	if ( 1 === $pages ) {
		return;
	}

	// Page numbers to show, in order; 0 marks a gap.
	$numbers = array();
	foreach ( range( 1, $pages ) as $i ) {
		if ( 1 === $i || $pages === $i || abs( $i - $current ) <= $range ) {
			if ( $numbers && $i - end( $numbers ) > 1 ) {
				$numbers[] = 0;
			}
			$numbers[] = $i;
		}
	}

	$cell  = 'flex items-center justify-center h-9 min-w-[2.25rem] px-2 rounded-xl text-meta font-semibold transition-colors';
	$link  = $cell . ' text-slate-600 hover:bg-rose-50 hover:text-primary';
	$arrow = 'flex items-center justify-center gap-1 h-9 px-2.5 sm:px-3 rounded-xl text-meta font-semibold transition-colors';
	$prev  = '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>';
	$next  = '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>';
	?>
    <nav class="flex justify-center mt-8 mb-4" aria-label="<?php esc_attr_e( 'Pagination', 'rom' ); ?>">
        <ul class="inline-flex items-center gap-0.5 p-1 bg-white rounded-2xl border border-slate-200/80 shadow-card max-w-full overflow-x-auto">
            <li>
				<?php if ( $current > 1 ) : ?>
                    <a class="<?php echo esc_attr( $arrow ); ?> text-slate-600 hover:bg-rose-50 hover:text-primary" href="<?php echo esc_url( get_pagenum_link( $current - 1 ) ); ?>" rel="prev">
						<?php echo $prev; ?><span class="hidden sm:inline"><?php esc_html_e( 'Prev', 'rom' ); ?></span>
                    </a>
				<?php else : ?>
                    <span class="<?php echo esc_attr( $arrow ); ?> text-slate-300 cursor-default" aria-disabled="true">
						<?php echo $prev; ?><span class="hidden sm:inline"><?php esc_html_e( 'Prev', 'rom' ); ?></span>
                    </span>
				<?php endif; ?>
            </li>
			<?php foreach ( $numbers as $n ) : ?>
                <li>
					<?php if ( 0 === $n ) : ?>
                        <span class="<?php echo esc_attr( $cell ); ?> text-slate-400 cursor-default" aria-hidden="true">&hellip;</span>
					<?php elseif ( $n === $current ) : ?>
                        <span class="<?php echo esc_attr( $cell ); ?> bg-primary text-white shadow-sm shadow-primary/25" aria-current="page"><?php echo esc_html( number_format_i18n( $n ) ); ?></span>
					<?php else : ?>
                        <a class="<?php echo esc_attr( $link ); ?>" href="<?php echo esc_url( get_pagenum_link( $n ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Page %s', 'rom' ), number_format_i18n( $n ) ) ); ?>"><?php echo esc_html( number_format_i18n( $n ) ); ?></a>
					<?php endif; ?>
                </li>
			<?php endforeach; ?>
            <li>
				<?php if ( $current < $pages ) : ?>
                    <a class="<?php echo esc_attr( $arrow ); ?> text-slate-600 hover:bg-rose-50 hover:text-primary" href="<?php echo esc_url( get_pagenum_link( $current + 1 ) ); ?>" rel="next">
                        <span class="hidden sm:inline"><?php esc_html_e( 'Next', 'rom' ); ?></span><?php echo $next; ?>
                    </a>
				<?php else : ?>
                    <span class="<?php echo esc_attr( $arrow ); ?> text-slate-300 cursor-default" aria-disabled="true">
                        <span class="hidden sm:inline"><?php esc_html_e( 'Next', 'rom' ); ?></span><?php echo $next; ?>
                    </span>
				<?php endif; ?>
            </li>
        </ul>
    </nav>
	<?php
}
