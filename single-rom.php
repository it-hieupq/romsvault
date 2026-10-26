<?php
get_header();

while ( have_posts() ) : the_post();
	global $post;
	$consoles = wp_get_post_terms( $post->ID, 'console', array( 'fields' => 'all' ) );
	if ( ! is_wp_error( $consoles ) && ! empty( $consoles ) ) {
		$console       = $consoles[0];
		$console_link  = get_term_link( $console->term_id );
		$console_image = get_field( 'cat_image', 'term_' . $console->term_id );
		$download_list = get_field( 'download_list', $post->ID );
	} else {
		rf_force_404();
	}

	$views = (int) get_field( 'views' );

	$rating = false;
	if ( isset( $_COOKIE['ratings'] ) ) {
		$ratings = json_decode( stripslashes( $_COOKIE['ratings'] ), true );
		if ( is_array( $ratings ) && in_array( $post->ID, $ratings ) ) {
			$rating = true;
		}
	}
	$count = max( 0, (int) get_post_meta( $post->ID, 'rating_count', true ) );
	$score = round( (float) get_post_meta( $post->ID, 'rating_avg', true ), 1, PHP_ROUND_HALF_DOWN );

	$downloads = (int) get_field( 'downloads', $post->ID );

	$file_size = get_field( 'file_size' );
	if ( empty( $file_size ) && is_array( $download_list ) && ! empty( $download_list ) ) {
		$file_size = $download_list[0]['file_size'];
	}

	$download_url  = rom_download_url( $post );
	$rf_restricted = rf_is_restricted( $post->ID );

	/**
	 * The download page indexes the two repeaters as one merged list and the file
	 * index is 1-based (see inc/ajax.php and inc/download-token.php), so the rows
	 * have to be merged in the same order here or the affiliate links would point
	 * at the wrong file.
	 */
	$download_list_aff = get_field( 'download_list_aff', $post->ID );
	$all_downloads     = array_merge(
		is_array( $download_list ) ? $download_list : array(),
		is_array( $download_list_aff ) ? $download_list_aff : array()
	);

	// Specs, built from whatever the rom actually has; empty ones never render.
	// No Platform cell: the console already leads the tag row and the h1.
	// Single-value facts sit in a grid. Region and language often list several
	// terms, so they get their own rows of chips instead of a cramped cell.
	$facts     = array();
	$chip_rows = array();
	if ( ! empty( $file_size ) ) {
		$facts[] = array(
			'icon'  => 'size',
			'label' => __( 'File Size', 'rom' ),
			'value' => esc_html( $file_size ),
		);
	}
	foreach ( array(
		'region'     => array( 'region', __( 'Region', 'rom' ) ),
		'dev'        => array( 'dev', __( 'Developer', 'rom' ) ),
		'pub'        => array( 'publisher', __( 'Publisher', 'rom' ) ),
		'collection' => array( 'collection', __( 'Collection', 'rom' ) ),
		'type'       => array( 'type', __( 'Release Type', 'rom' ) ),
		'language'   => array( 'flag', __( 'Language', 'rom' ) ),
	) as $spec_tax => $spec_meta ) {
		$spec_terms = wp_get_post_terms( $post->ID, $spec_tax, array( 'fields' => 'all' ) );
		if ( is_wp_error( $spec_terms ) || empty( $spec_terms ) ) {
			continue;
		}
		if ( in_array( $spec_tax, array( 'region', 'language' ), true ) ) {
			$chip_rows[] = array(
				'tax'   => $spec_tax,
				'icon'  => $spec_meta[0],
				'label' => $spec_meta[1],
				'terms' => $spec_terms,
			);
			continue;
		}
		$spec_links = array();
		foreach ( $spec_terms as $spec_term ) {
			$spec_links[] = sprintf(
				'<a class="hover:text-primary transition-colors" href="%s">%s</a>',
				esc_url( get_term_link( $spec_term->term_id ) ),
				esc_html( $spec_term->name )
			);
		}
		$facts[] = array(
			'icon'  => $spec_meta[0],
			'label' => $spec_meta[1],
			'value' => implode( ', ', $spec_links ),
		);
	}
	if ( $released = get_field( 'released', $post->ID ) ) {
		$facts[] = array(
			'icon'  => 'released',
			'label' => __( 'Release Date', 'rom' ),
			'value' => esc_html( wp_strip_all_tags( $released ) ),
		);
	}
	?>

    <!-- Breadcrumbs bar -->
    <div class="w-full bg-white/50 border-b border-slate-200/60 py-2.5">
        <div class="max-w-content mx-auto px-4 sm:px-6">
			<?php k_breadcrumb(); ?>
        </div>
    </div>

    <!-- Hero overview -->
    <section class="py-6 sm:py-8">
        <div class="max-w-content mx-auto px-4 sm:px-6">
            <div class="bg-white rounded-xl border border-slate-200/80 p-5 sm:p-7 lg:p-8 shadow-card">
                <?php // Boxart column has a fixed width so tablets keep most of the row for the details. ?>
                <div class="grid grid-cols-1 md:grid-cols-[200px_minmax(0,1fr)] lg:grid-cols-[280px_minmax(0,1fr)] gap-6 lg:gap-10 items-start">

                    <!-- Boxart -->
                    <div class="w-full max-w-[260px] md:max-w-none mx-auto flex flex-col items-center">
						<?php if ( $thumb_url = get_the_post_thumbnail_url( $post->ID, 'full' ) ) : ?>
                        <div class="relative w-full rounded-2xl overflow-hidden shadow-md border border-slate-200/80 bg-slate-100 group">
                                <img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php the_title_attribute(); ?>"
                                     class="w-full h-auto block group-hover:scale-105 transition-transform duration-500" loading="eager">
                            <div class="absolute top-3 right-3">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-slate-950/85 backdrop-blur-md text-white text-[10px] font-bold uppercase tracking-wider shadow-sm">
                                    <span class="w-1.5 h-1.5 rounded-full bg-primary"></span>
									<?php echo esc_html( $console->name ); ?>
                                </span>
                            </div>
                        </div>
						<?php else : ?>
							<?php // No cover yet: a quiet placeholder keeps the column from collapsing to the stars. ?>
                        <div class="w-full aspect-[3/4] rounded-2xl border border-slate-200/80 bg-gradient-to-br from-slate-50 to-slate-100 flex flex-col items-center justify-center gap-2 text-slate-300">
                            <svg class="w-12 h-12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="4"/><path d="M6 12h4m-2-2v4"/><path d="M15 11h.01M18 13h.01"/></svg>
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400"><?php echo esc_html( $console->name ); ?></span>
                        </div>
						<?php endif; ?>
                        <div class="w-full mt-4 flex flex-wrap items-center justify-center gap-2">
                            <div class="rating [&_.rf-stars]:text-[1.6rem]" data-post_id="<?php echo esc_attr( $post->ID ); ?>"
                                 data-rateyo-rating="<?php echo esc_attr( $score ); ?>"
                        			<?php if ( $rating ) : ?>data-rateyo-read-only="true"<?php endif; ?>></div>
                            <span class="text-slate-900 font-extrabold text-lg"><?php echo esc_html( $score ); ?></span>
                            <span class="text-slate-400 font-normal text-caption">(<?php echo esc_html( $count . ' ' . _n( 'review', 'reviews', $count, 'rom' ) ); ?>)</span>
                        </div>
                    </div>

                    <!-- Title, meta, specs, actions -->
                    <div class="min-w-0 flex flex-col space-y-5 sm:space-y-6">

                        <!-- Console + genre tags -->
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="<?php echo esc_url( $console_link ); ?>"
                               class="inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-rose-50 text-primary text-caption font-bold border border-rose-200/60 hover:bg-rose-100 transition-colors [&_svg]:w-4 [&_svg]:h-4 [&_svg]:mr-0"><?php echo get_svg_icon( 'joystick' ) . esc_html( $console->name ); ?></a>
							<?php
							$genre_terms = wp_get_post_terms( $post->ID, 'genre', array( 'fields' => 'id=>name' ) );
							if ( ! is_wp_error( $genre_terms ) && ! empty( $genre_terms ) ) :
								foreach ( $genre_terms as $genre_id => $genre_name ) :
									?>
                                    <a href="<?php echo esc_url( $console_link . '?genres[]=' . $genre_id ); ?>"
                                       class="px-3 py-1 rounded-lg bg-slate-100 text-slate-700 text-caption font-semibold hover:bg-slate-200 transition-colors"><?php echo esc_html( $genre_name ); ?></a>
								<?php
								endforeach;
							endif;
							?>
                        </div>

                        <!-- Title + score -->
                        <div>
                            <div class="flex items-start justify-between gap-3">
                                <h1 class="min-w-0 text-xl sm:text-2xl font-bold text-slate-900 tracking-tight leading-snug">
									<?php
									/* translators: 1: game title, 2: console name. */
									echo esc_html( sprintf( __( '%1$s %2$s ROM', 'rom' ), get_the_title(), $console->name ) );
									?>
                                </h1>
                                <div class="flex-shrink-0 [&_.rf-wish-btn]:m-0"><?php rf_render_wishlist_button( $post->ID ); ?></div>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 sm:gap-4 text-meta text-slate-500 mt-2">
                                <span class="flex items-center gap-1 text-slate-600">
									<?php echo esc_html( number_format( $downloads, 0, '', ',' ) . ' ' . __( 'downloads', 'rom' ) ); ?>
                                </span>
                                <span class="text-slate-300">&bull;</span>
                                <span class="text-slate-500"><?php echo esc_html( sprintf( __( 'Updated: %s', 'rom' ), get_the_modified_date( 'M Y' ) ) ); ?></span>
                            </div>
                        </div>

                        <!-- Facts: one value each, wrapping rather than truncating -->
						<?php if ( $facts ) : ?>
                            <?php // Two columns; an odd last fact takes the whole row instead of sitting alone. ?>
                            <dl class="grid grid-cols-2 gap-3 md:gap-4 [&>:last-child:nth-child(odd)]:col-span-2">
								<?php foreach ( $facts as $fact ) : ?>
                                    <div class="rounded-xl bg-slate-50 border border-slate-200/60 px-3.5 py-3">
                                        <dt class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 [&_svg]:w-3.5 [&_svg]:h-3.5 [&_svg]:mr-0 [&_svg]:text-primary [&_svg]:flex-shrink-0">
											<?php echo get_svg_icon( $fact['icon'] ) . esc_html( $fact['label'] ); ?>
                                        </dt>
                                        <dd class="mt-1 text-[13px] font-bold text-slate-800 leading-snug break-words"><?php echo $fact['value']; ?></dd>
                                    </div>
								<?php endforeach; ?>
                            </dl>
						<?php endif; ?>

                        <!-- Region / language: every term as a chip, so long lists wrap instead of cutting off -->
						<?php if ( $chip_rows ) : ?>
                            <dl class="divide-y divide-slate-100">
								<?php foreach ( $chip_rows as $row ) : ?>
                                    <div class="flex flex-col sm:flex-row sm:items-start gap-2 sm:gap-4 py-2.5 first:pt-0 last:pb-0">
                                        <dt class="sm:w-28 sm:pt-1.5 flex-shrink-0 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 [&_svg]:w-3.5 [&_svg]:h-3.5 [&_svg]:mr-0 [&_svg]:text-primary [&_svg]:flex-shrink-0">
											<?php echo get_svg_icon( $row['icon'] ) . esc_html( $row['label'] ); ?>
                                        </dt>
                                        <dd class="flex flex-wrap gap-1.5">
											<?php foreach ( $row['terms'] as $chip_term ) : ?>
                                                <a href="<?php echo esc_url( get_term_link( $chip_term->term_id ) ); ?>"
                                                   class="px-2.5 py-1 rounded-md bg-slate-100 text-slate-700 text-caption font-semibold hover:bg-rose-50 hover:text-primary transition-colors"><?php echo esc_html( trim( ( 'region' === $row['tax'] ? rom_region_flag( $chip_term->slug ) : '' ) . ' ' . $chip_term->name ) ); ?></a>
											<?php endforeach; ?>
                                        </dd>
                                    </div>
								<?php endforeach; ?>
                            </dl>
						<?php endif; ?>

                        <!-- Actions -->
                        <div class="pt-3 border-t border-slate-100">
							<?php if ( $rf_restricted ) {
								rf_render_restricted_notice();
							} ?>
                            <div class="flex flex-wrap items-center gap-3">
								<?php if ( ! empty( $all_downloads ) ) : ?>
                                    <?php // Display lives on the inner span: a flex utility here would override rf-authed-only's display:none. ?>
                                    <span class="<?php echo $rf_restricted ? 'rf-authed-only rf-hide-blocked' : ''; ?>">
                                    <span class="flex flex-wrap items-center gap-3">
                                        <a href="<?php echo esc_url( $download_url ); ?>" rel="nofollow"
                                           class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-primary text-white font-bold text-xs hover:bg-primary-hover active:scale-95 transition-all shadow-sm shadow-primary/25 [&_svg]:w-4 [&_svg]:h-4">
											<?php echo get_svg_icon( 'save' ) . esc_html__( 'Download ROM', 'rom' ); ?>
                                        </a>
										<?php
										$emu_term = get_term_by( 'slug', $console->slug, 'emulator_console' );
										if ( $emu_term && ! is_wp_error( $emu_term ) && $emu_term->count > 0 ) : ?>
                                            <a href="<?php echo esc_url( get_term_link( $emu_term->term_id ) ); ?>"
                                               class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-slate-100 text-slate-700 font-semibold text-xs hover:bg-slate-200 hover:text-slate-900 transition-colors [&_svg]:w-5 [&_svg]:h-5 [&_svg]:mr-0">
												<?php echo get_svg_icon( 'emulator' ) . esc_html( sprintf( __( 'Download %s Emulator', 'rom' ), $emu_term->name ) ); ?>
                                            </a>
										<?php endif; ?>
                                    </span>
                                    </span>
								<?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </section>

	<?php if ( $post->post_content !== '' ) : ?>
        <!-- About -->
        <section id="about" class="py-6 sm:py-8 scroll-mt-24">
            <div class="max-w-content mx-auto px-4 sm:px-6">
                <div class="bg-white rounded-3xl border border-slate-200/80 p-5 sm:p-7 lg:p-9 shadow-card space-y-6">
                    <div class="border-b border-slate-100 pb-4">
                        <span class="text-caption font-bold uppercase tracking-widest text-amber-600"><?php esc_html_e( 'Game Information', 'rom' ); ?></span>
                        <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight mt-0.5">
							<?php echo esc_html( sprintf( __( 'About %1$s for %2$s', 'rom' ), get_the_title(), $console->name ) ); ?>
                        </h2>
                    </div>
                    <div class="prose max-w-none text-slate-700 text-body">
						<?php the_content(); ?>
                    </div>
                </div>
            </div>
        </section>
	<?php endif; ?>
	<?php
	$screenshots = get_field( 'image' );
	$shot_total  = is_array( $screenshots ) ? count( $screenshots ) : 0;

	/**
	 * Youtube preview leads the row when the field holds a watch/short/embed URL.
	 * The tile follows lightGallery's video contract — data-src is the youtube URL,
	 * data-poster the still — so lgVideo plays it inside the lightbox; href is only
	 * the no-JS fallback.
	 */
	$yt_id  = '';
	$yt_url = get_field( 'youtube_url', $post->ID );
	if ( $yt_url && preg_match( '~(?:youtu\.be/|youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/))([A-Za-z0-9_-]{11})~', $yt_url, $yt_match ) ) {
		$yt_id = $yt_match[1];
	}

	$gallery_meta = array();
	if ( $yt_id ) {
		$gallery_meta[] = __( '1 video', 'rom' );
	}
	if ( $shot_total ) {
		$gallery_meta[] = sprintf( _n( '%s image', '%s images', $shot_total, 'rom' ), number_format_i18n( $shot_total ) );
	}

	if ( $shot_total || $yt_id ) :
		?>
        <!-- Screenshots. One scrolling row: fixed 200px height, width left to each
             shot's own ratio, since ratios vary per console (a DS capture is portrait
             256x384, a PS2 one landscape) and a column grid would crop them. -->
        <section id="screenshots" class="py-6 sm:py-8 scroll-mt-24">
            <div class="max-w-content mx-auto px-4 sm:px-6">
                <div class="flex items-end justify-between gap-3 mb-3">
                    <div>
                        <span class="text-caption font-bold uppercase tracking-widest text-primary"><?php esc_html_e( 'Gameplay Gallery', 'rom' ); ?></span>
                        <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight mt-0.5"><?php esc_html_e( 'Screenshots', 'rom' ); ?></h2>
                    </div>
                    <span class="text-caption text-slate-500 flex-shrink-0">
                        <?php echo esc_html( implode( ' · ', $gallery_meta ) ); ?>
                    </span>
                </div>

                <div class="horizontal-scroll-container">
                    <div id="romGallery" class="flex items-start gap-2.5 pb-2.5">
                    <?php if ( $yt_id ) : ?>
                        <a href="<?php echo esc_url( $yt_url ); ?>"
                           class="group relative shrink-0 block rounded-lg overflow-hidden border border-slate-200/80 bg-slate-900 shadow-subtle hover:border-primary/50 transition-all cursor-pointer"
                           data-src="<?php echo esc_url( $yt_url ); ?>"
                           data-poster="<?php echo esc_url( 'https://img.youtube.com/vi/' . $yt_id . '/hqdefault.jpg' ); ?>"
                           data-sub-html="<?php echo esc_attr( sprintf( __( '%s gameplay video', 'rom' ), get_the_title() ) ); ?>">
                            <img src="https://img.youtube.com/vi/<?php echo esc_attr( $yt_id ); ?>/hqdefault.jpg"
                                 alt="<?php echo esc_attr( sprintf( __( '%s gameplay video', 'rom' ), get_the_title() ) ); ?>"
                                 class="block h-[200px] w-auto object-cover opacity-90 group-hover:opacity-100 transition-opacity duration-300"
                                 loading="lazy">
                            <span class="absolute inset-0 flex items-center justify-center bg-slate-950/25 group-hover:bg-slate-950/5 transition-colors">
                                <span class="w-12 h-12 rounded-full bg-primary/90 flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform">
                                    <svg class="w-5 h-5 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                                </span>
                            </span>
                            <span class="absolute top-2 left-2 px-2 py-0.5 rounded-md bg-slate-950/80 text-white text-[10px] font-bold uppercase tracking-wider backdrop-blur-sm"><?php esc_html_e( 'Video', 'rom' ); ?></span>
                        </a>
                    <?php endif; ?>
                    <?php if ( $shot_total ) : foreach ( $screenshots as $img ) :
                        $caption  = $img['caption'] ?: ( $img['alt'] ?: get_the_title() );
                        $alt_text = $img['alt'] ?: get_the_title() . ' - Screenshot';
                        ?>
                        <a href="<?php echo esc_url( $img['url'] ); ?>"
                           class="group relative inline-block shrink-0 rounded-lg overflow-hidden border border-slate-200/80 bg-slate-900 shadow-subtle hover:border-primary/50 transition-all"
                           data-src="<?php echo esc_url( $img['url'] ); ?>"
                           data-sub-html="<?php echo esc_attr( $caption ); ?>">
                            <img src="<?php echo esc_url( $img['url'] ); ?>" alt="<?php echo esc_attr( $alt_text ); ?>"
                                 class="block h-[200px] w-auto object-cover opacity-90 group-hover:opacity-100 group-hover:scale-105 transition-all duration-300"
                                 loading="lazy">
                        </a>
                    <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </section>
	<?php endif; ?>

	<?php if ( ! empty( $all_downloads ) ) : ?>
        <!-- Download matrix -->
        <section id="download" class="py-6 sm:py-8 scroll-mt-24">
            <div class="max-w-content mx-auto px-4 sm:px-6">
                <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3 mb-4">
                    <div>
                        <span class="text-caption font-bold uppercase tracking-widest text-amber-600"><?php esc_html_e( 'Download', 'rom' ); ?></span>
                        <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight mt-0.5"><?php esc_html_e( 'Available ROM & ISO Versions', 'rom' ); ?></h2>
                    </div>
                </div>

				<?php if ( $rf_restricted ) {
					rf_render_restricted_notice();
				} ?>

                <div class="<?php echo $rf_restricted ? 'rf-authed-only rf-hide-blocked' : ''; ?>">
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-card divide-y divide-slate-100 overflow-hidden">
						<?php foreach ( $all_downloads as $index => $file ) :
							// 1-based, matching the /download/{n} endpoint.
							$file_url  = rom_download_url( $post, $index + 1 );
							$file_type = ! empty( $file['file_type'] ) ? $file['file_type'] : '';
							// The badge shows the archive the link really serves (zip, 7z, iso...),
							// read off the URL; the Format line keeps the editor's file_type.
							$file_ext = strtolower( pathinfo( (string) wp_parse_url( $file['url'] ?? '', PHP_URL_PATH ), PATHINFO_EXTENSION ) );
							if ( ! preg_match( '/^[a-z0-9]{1,5}$/', $file_ext ) ) {
								$file_ext = '';
							}
							?>
                            <div class="p-4 sm:p-5 flex items-center justify-between gap-3 sm:gap-4 hover:bg-rose-50/20 transition-colors">
                                <div class="flex items-start gap-3.5 flex-1 min-w-0">
                                    <div class="w-10 h-10 rounded-xl bg-rose-50 text-primary flex items-center justify-center font-bold text-caption flex-shrink-0 mt-0.5 uppercase">
										<?php echo esc_html( $file_ext ? $file_ext : '#' . ( $index + 1 ) ); ?>
                                    </div>
                                    <div class="min-w-0">
                                        <span class="font-bold text-slate-900 text-meta sm:text-body block"><?php echo esc_html( $file['name'] ); ?></span>
                                        <div class="flex flex-wrap items-center gap-2.5 text-caption text-slate-500 mt-1">
											<?php if ( $file_type ) : ?>
                                                <span><?php esc_html_e( 'Format:', 'rom' ); ?> <strong class="text-slate-700"><?php echo esc_html( $file_type ); ?></strong></span>
											<?php endif; ?>
											<?php if ( ! empty( $file['file_size'] ) ) : ?>
												<?php if ( $file_type ) : ?><span>&bull;</span><?php endif; ?>
                                                <span><?php esc_html_e( 'Size:', 'rom' ); ?> <strong class="text-slate-800 font-bold"><?php echo esc_html( $file['file_size'] ); ?></strong></span>
											<?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <a href="<?php echo esc_url( $file_url ); ?>" rel="nofollow" aria-label="<?php esc_attr_e( 'Download', 'rom' ); ?>"
                                       class="inline-flex items-center justify-center gap-1.5 w-8 h-8 sm:w-auto sm:h-auto sm:px-3 sm:py-1 rounded-full bg-primary text-white font-bold text-[10px] uppercase tracking-wider hover:bg-primary-hover shadow-sm shadow-primary/25 active:scale-95 transition-all">
                                        <?php // Own icon: the shared 'download' one is painted pink and vanishes on this button. ?>
                                        <svg class="w-3.5 h-3.5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11m0 0-4.5-4.5M12 15l4.5-4.5M5 19h14"/></svg>
										<span class="hidden sm:inline"><?php esc_html_e( 'Download', 'rom' ); ?></span>
                                    </a>
                                </div>
                            </div>
						<?php endforeach; ?>
                    </div>

					<?php if ( $download_note = get_field( 'download_note', $post->ID ) ) : ?>
                        <div class="mt-3.5 p-3.5 rounded-2xl bg-amber-50/80 border border-amber-200/80 flex items-start gap-3 text-caption text-amber-950">
                            <svg class="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                            <div>
                                <span class="font-bold"><?php esc_html_e( 'Download Notice:', 'rom' ); ?></span>
                                <div class="text-amber-900/90 mt-0.5 leading-relaxed"><?php echo wpautop( esc_html( $download_note ) ); ?></div>
                            </div>
                        </div>
					<?php endif; ?>
                </div>
            </div>
        </section>
	<?php endif; ?>

	<?php
	// Emulators that actually exist for this console.
	$emu_term = get_term_by( 'slug', $console->slug, 'emulator_console' );
	if ( $emu_term && ! is_wp_error( $emu_term ) && $emu_term->count > 0 ) :
		$emu_query = new WP_Query( array(
			'post_type'      => 'emulator',
			'posts_per_page' => 4,
			'post_status'    => 'publish',
			'no_found_rows'  => true,
			'tax_query'      => array(
				array(
					'taxonomy' => 'emulator_console',
					'field'    => 'term_id',
					'terms'    => $emu_term->term_id,
				),
			),
		) );
		if ( $emu_query->have_posts() ) : ?>
            <!-- Emulators -->
            <section id="emulators" class="py-6 sm:py-8 scroll-mt-24">
                <div class="max-w-content mx-auto px-4 sm:px-6">
                    <div class="bg-white rounded-3xl border border-slate-200/80 p-5 sm:p-7 shadow-card">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 mb-5 pb-3.5 border-b border-slate-100">
                            <div>
                                <span class="text-caption font-bold uppercase tracking-widest text-amber-600"><?php esc_html_e( 'Compatibility', 'rom' ); ?></span>
                                <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight mt-0.5">
									<?php echo esc_html( sprintf( __( 'Recommended Emulators for %s', 'rom' ), $emu_term->name ) ); ?>
                                </h2>
                            </div>
                            <a href="<?php echo esc_url( get_term_link( $emu_term->term_id ) ); ?>"
                               class="text-caption font-bold text-primary underline underline-offset-4 hover:text-primary-hover w-fit"><?php esc_html_e( 'View all', 'rom' ); ?> &rarr;</a>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-4">
							<?php while ( $emu_query->have_posts() ) : $emu_query->the_post(); ?>
                                <div class="p-4 sm:p-5 rounded-2xl bg-slate-50 border border-slate-100 flex items-start gap-4 hover:border-slate-200 transition-colors">
                                    <?php // Fixed square: emulator logos vary in ratio, so they sit contained inside it. ?>
                                    <a href="<?php the_permalink(); ?>" class="flex-shrink-0 w-20 h-20 sm:w-24 sm:h-24 rounded-xl bg-white border border-slate-200/80 flex items-center justify-center p-2.5 overflow-hidden" tabindex="-1" aria-hidden="true">
										<?php if ( has_post_thumbnail() ) :
											the_post_thumbnail( 'thumbnail', array( 'class' => 'w-full h-full object-contain', 'loading' => 'lazy', 'alt' => '' ) );
										else :
											echo get_svg_icon( 'emulator' );
										endif; ?>
                                    </a>
                                    <div class="min-w-0 flex-1 flex flex-col justify-between gap-3 self-stretch">
                                    <div>
                                        <h3 class="font-bold text-body text-slate-900"><?php the_title(); ?></h3>
										<?php if ( has_excerpt() ) : ?>
                                            <p class="text-caption text-slate-600 leading-relaxed mt-1.5"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 28 ) ); ?></p>
										<?php endif; ?>
                                    </div>
                                    <a href="<?php the_permalink(); ?>"
                                       class="inline-flex items-center gap-1 text-caption font-bold text-primary hover:text-primary-hover bg-rose-50 hover:bg-rose-100 px-3 py-1.5 rounded-full transition-colors w-fit">
										<?php esc_html_e( 'Download', 'rom' ); ?> <?php the_title(); ?> &rarr;
                                    </a>
                                    </div>
                                </div>
							<?php endwhile; wp_reset_postdata(); ?>
                        </div>
                    </div>
                </div>
            </section>
		<?php endif;
	endif; ?>

	<?php
	// Per-console setup guide. Only a handful of console terms have one.
	$console_guide = get_field( 'console_guide', 'term_' . $console->term_id );
	if ( ! empty( $console_guide ) ) : ?>
        <section class="py-6 sm:py-8">
            <div class="max-w-content mx-auto px-4 sm:px-6">
                <div class="bg-white rounded-3xl border border-slate-200/80 p-5 sm:p-7 shadow-card space-y-4">
                    <div>
                        <span class="text-caption font-bold uppercase tracking-widest text-amber-600"><?php esc_html_e( 'How to play', 'rom' ); ?></span>
                        <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight mt-0.5">
							<?php echo esc_html( sprintf( __( '%s Setup Guide', 'rom' ), $console->name ) ); ?>
                        </h2>
                    </div>
                    <div class="revert page-content max-w-none text-slate-700 text-body"><?php echo wpautop( $console_guide ); ?></div>
                </div>
            </div>
        </section>
	<?php endif; ?>

	<?php
	// Other roms in the same collection.
	$collections = wp_get_post_terms( $post->ID, 'collection' );
	if ( ! is_wp_error( $collections ) && ! empty( $collections ) ) :
		$primary_collection    = $collections[0];
		$collection_roms_query = rom_nearby_query( $post, $primary_collection );

		if ( $collection_roms_query->have_posts() ) : ?>
            <section class="py-8 border-t border-slate-200/80">
                <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between mb-6 gap-3">
                        <div>
                            <span class="text-caption font-bold uppercase tracking-widest text-amber-600"><?php esc_html_e( 'Collection', 'rom' ); ?></span>
                            <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight mt-0.5">
								<?php echo esc_html( sprintf( __( 'Others ROM in %s Collection', 'rom' ), $primary_collection->name ) ); ?>
                            </h2>
                        </div>
                        <a href="<?php echo esc_url( get_term_link( $primary_collection->term_id ) ); ?>"
                           class="text-caption sm:text-meta font-bold text-primary underline underline-offset-4 hover:text-primary-hover flex-shrink-0"><?php esc_html_e( 'View all', 'rom' ); ?> &rarr;</a>
                    </div>
                    <div class="horizontal-scroll-container">
                        <div class="horizontal-scroll-wrapper">
							<?php while ( $collection_roms_query->have_posts() ) : $collection_roms_query->the_post(); ?>
                                <div class="flex-shrink-0 w-1/2 md:w-1/3 lg:w-1/4 xl:w-1/5 min-w-0 px-1.5 md:px-2 box-border grid">
									<?php get_template_part( 'template-parts/rom-card' ); ?>
                                </div>
							<?php endwhile; wp_reset_postdata(); ?>
                        </div>
                    </div>
                </div>
            </section>
		<?php endif;
	endif; ?>

	<?php
	$related_query = rom_nearby_query( $post, $console );

	if ( $related_query->have_posts() ) : ?>
        <section class="py-8 sm:py-12 border-t border-slate-200/80">
            <div class="max-w-site mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between mb-6 gap-3">
                    <div>
                        <span class="text-caption font-bold uppercase tracking-widest text-amber-600">
							<?php echo esc_html( sprintf( __( 'More %s', 'rom' ), $console->name ) ); ?>
                        </span>
                        <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight mt-0.5"><?php esc_html_e( 'You Might Also Like', 'rom' ); ?></h2>
                    </div>
                    <a href="<?php echo esc_url( $console_link ); ?>"
                       class="text-caption sm:text-meta font-bold text-primary underline underline-offset-4 hover:text-primary-hover flex-shrink-0"><?php esc_html_e( 'View all', 'rom' ); ?> &rarr;</a>
                </div>
                <div class="horizontal-scroll-container">
                    <div class="horizontal-scroll-wrapper">
						<?php while ( $related_query->have_posts() ) : $related_query->the_post(); ?>
                            <div class="flex-shrink-0 w-1/2 md:w-1/3 lg:w-1/4 xl:w-1/5 min-w-0 px-1.5 md:px-2 box-border grid">
								<?php get_template_part( 'template-parts/rom-card' ); ?>
                            </div>
						<?php endwhile; wp_reset_postdata(); ?>
                    </div>
                </div>
            </div>
        </section>
	<?php endif; ?>

    <!-- Ratings, report and comments. These partials bring their own card wrapper. -->
    <section class="py-2">
        <div class="max-w-content mx-auto px-4 sm:px-6">
			<?php get_template_part( 'template-parts/rating-section' ); ?>
			<?php get_template_part( 'template-parts/report-form' ); ?>
			<?php comments_template(); ?>
        </div>
    </section>

<?php
endwhile;
get_footer();
