<?php
get_header();

while ( have_posts() ) : the_post();
	global $post;
	$terms = wp_get_post_terms( $post->ID, 'emulator_console', array( 'fields' => 'all' ) );
	if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
		$console      = $terms[0];
		$console_link = get_term_link( $console->term_id );
	} else {
		rf_force_404();
	}

	$views = (int) get_field( 'views', $post->ID );


	$downloads = (int) get_field( 'downloads', $post->ID );
	$file_size = get_field( 'file_size', $post->ID );

	$download_url  = rom_download_url( $post );
	$rf_restricted = rf_is_restricted( $post->ID );

	/**
	 * Same contract as the rom page: the download endpoint indexes both repeaters
	 * as one merged list, 1-based (inc/ajax.php, inc/download-token.php), so they
	 * have to be merged in that order here too.
	 */
	$all_downloads = array_merge(
		(array) ( get_field( 'download_list', $post->ID ) ?: array() ),
		(array) ( get_field( 'download_list_aff', $post->ID ) ?: array() )
	);

	// Spec cells. The console is already the lead tag and sits in the h1, so it is
	// not repeated here; 'region' is not registered for this post type at all.
	$specs    = array();
	$os_terms = wp_get_post_terms( $post->ID, 'os', array( 'fields' => 'all' ) );
	if ( ! is_wp_error( $os_terms ) && ! empty( $os_terms ) ) {
		$os_links = array();
		foreach ( $os_terms as $os_term ) {
			$os_links[] = sprintf(
				'<a class="hover:text-primary transition-colors" href="%s">%s</a>',
				esc_url( get_term_link( $os_term->term_id ) ),
				esc_html( $os_term->name )
			);
		}
		$specs[] = array(
			'icon'  => 'platform',
			'label' => __( 'Platform', 'rom' ),
			'value' => implode( ', ', $os_links ),
		);
	}
	if ( ! empty( $file_size ) ) {
		$specs[] = array(
			'icon'  => 'size',
			'label' => __( 'File Size', 'rom' ),
			'value' => esc_html( $file_size ),
		);
	}
	if ( $released = get_field( 'released', $post->ID ) ) {
		$specs[] = array(
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
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 lg:gap-8 items-start">

                    <!-- Logo -->
                    <div class="md:col-span-5 lg:col-span-4 flex flex-col items-center">
						<?php if ( has_post_thumbnail( $post ) ) : ?>
                            <div class="relative w-full rounded-2xl overflow-hidden shadow-md border border-slate-200/80 bg-slate-100 group">
                                <?php // The page's LCP image: width/height, a srcset sized to this column, high fetch priority. ?>
                                <?php echo wp_get_attachment_image( get_post_thumbnail_id( $post ), 'full', false, array(
									'alt'           => the_title_attribute( array( 'echo' => false ) ),
									'class'         => 'w-full h-auto block group-hover:scale-105 transition-transform duration-500',
									'loading'       => 'eager',
									'fetchpriority' => 'high',
									'decoding'      => 'async',
									'sizes'         => '(min-width: 1024px) 360px, (min-width: 768px) 40vw, 100vw',
								) ); ?>
                                <div class="absolute top-3 right-3">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-slate-950/85 backdrop-blur-md text-white text-[10px] font-bold uppercase tracking-wider shadow-sm">
                                        <span class="w-1.5 h-1.5 rounded-full bg-primary"></span>
										<?php echo esc_html( $console->name ); ?>
                                    </span>
                                </div>
                            </div>
						<?php endif; ?>
                        <?php // Stars are rendered here, not by JS; js/rating.js swaps this after a vote. ?>
                        <div class="w-full mt-4 flex flex-wrap items-center justify-center gap-2" data-rf-rating-summary="hero">
							<?php echo rom_rating_summary_html( $post->ID, 'hero' ); ?>
                        </div>
                    </div>

                    <!-- Title, meta, specs, actions -->
                    <div class="md:col-span-7 lg:col-span-8 flex flex-col justify-between space-y-5 sm:space-y-6">

                        <!-- Console + OS tags -->
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="<?php echo esc_url( $console_link ); ?>"
                               class="inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-rose-50 text-primary text-caption font-bold border border-rose-200/60 hover:bg-rose-100 transition-colors [&_svg]:w-4 [&_svg]:h-4 [&_svg]:mr-0"><?php echo get_svg_icon( 'joystick' ) . esc_html( $console->name ); ?></a>
							<?php
							if ( ! is_wp_error( $os_terms ) && ! empty( $os_terms ) ) :
								foreach ( $os_terms as $os_term ) :
									?>
                                    <a href="<?php echo esc_url( get_term_link( $os_term->term_id ) ); ?>"
                                       class="px-3 py-1 rounded-lg bg-slate-100 text-slate-700 text-caption font-semibold hover:bg-slate-200 transition-colors"><?php echo esc_html( $os_term->name ); ?></a>
								<?php
								endforeach;
							endif;
							?>
                        </div>

                        <!-- Title + meta -->
                        <div>
                            <div class="flex items-start justify-between gap-3">
                                <h1 class="min-w-0 text-xl sm:text-2xl font-bold text-slate-900 tracking-tight leading-snug">
									<?php
									/* translators: 1: emulator name, 2: console name. */
									echo esc_html( sprintf( __( '%1$s %2$s Emulator', 'rom' ), get_the_title(), $console->name ) );
									?>
                                </h1>
                                <div class="flex-shrink-0 [&_.rf-wish-btn]:m-0"><?php rf_render_wishlist_button( $post->ID ); ?></div>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 sm:gap-4 text-meta text-slate-500 mt-2">
                                <span class="flex items-center gap-1 text-slate-600">
									<?php echo esc_html( number_format( $downloads, 0, '', ',' ) . ' ' . __( 'downloads', 'rom' ) ); ?>
                                </span>
								<?php if ( ! empty( $file_size ) ) : ?>
                                    <span class="text-slate-300">&bull;</span>
                                    <span class="text-slate-600"><?php echo esc_html( $file_size ); ?></span>
								<?php endif; ?>
                                <span class="text-slate-300">&bull;</span>
                                <span class="text-slate-500"><?php echo esc_html( sprintf( __( 'Updated: %s', 'rom' ), get_the_modified_date( 'M Y' ) ) ); ?></span>
                            </div>
                        </div>

                        <!-- Specs grid -->
						<?php if ( $specs ) : ?>
                            <?php // Same cards as the rom facts: two per row, an odd last one takes the whole row. ?>
                            <div class="grid grid-cols-2 gap-3 md:gap-4 my-1 [&>:last-child:nth-child(odd)]:col-span-2">
								<?php foreach ( $specs as $spec ) : ?>
                                    <div class="p-3 sm:p-3.5 rounded-xl bg-slate-50/80 border border-slate-200/70 hover:bg-slate-50 hover:border-slate-300 transition-colors flex items-start gap-2.5">
                                        <div class="w-8 h-8 rounded-lg bg-rose-50 border border-rose-100/80 text-primary flex items-center justify-center flex-shrink-0 mt-0.5 [&_svg]:w-4 [&_svg]:h-4 [&_svg]:mr-0">
											<?php echo get_svg_icon( $spec['icon'] ); ?>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400"><?php echo esc_html( $spec['label'] ); ?></span>
                                            <span class="font-bold text-[13px] text-slate-800 mt-0.5 block leading-snug break-words"><?php echo $spec['value']; ?></span>
                                        </div>
                                    </div>
								<?php endforeach; ?>
                            </div>
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
											<?php echo get_svg_icon( 'save' ) . esc_html__( 'Download Emulator', 'rom' ); ?>
                                        </a>
                                        <a href="<?php echo esc_url( $console_link ); ?>"
                                           class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-slate-100 text-slate-700 font-semibold text-xs hover:bg-slate-200 hover:text-slate-900 transition-colors [&_svg]:w-5 [&_svg]:h-5 [&_svg]:mr-0">
											<?php echo get_svg_icon( 'emulator' ) . esc_html( sprintf( __( 'All %s Emulators', 'rom' ), $console->name ) ); ?>
                                        </a>
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
                        <span class="text-caption font-bold uppercase tracking-widest text-amber-600"><?php esc_html_e( 'Emulator Information', 'rom' ); ?></span>
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
	 * Same gallery contract as the rom page: data-src/data-poster put the youtube
	 * tile on lgVideo, and the row is one fixed-height scrolling strip because the
	 * shots keep their native ratio.
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
        <section id="screenshots" class="py-6 sm:py-8 scroll-mt-24">
            <div class="max-w-content mx-auto px-4 sm:px-6">
                <div class="flex items-end justify-between gap-3 mb-3">
                    <div>
                        <span class="text-caption font-bold uppercase tracking-widest text-primary"><?php esc_html_e( 'Gallery', 'rom' ); ?></span>
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
                               data-sub-html="<?php echo esc_attr( sprintf( __( '%s video', 'rom' ), get_the_title() ) ); ?>">
                                <img src="https://img.youtube.com/vi/<?php echo esc_attr( $yt_id ); ?>/hqdefault.jpg"
                                     alt="<?php echo esc_attr( sprintf( __( '%s video', 'rom' ), get_the_title() ) ); ?>"
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
                        <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight mt-0.5"><?php esc_html_e( 'Available Builds', 'rom' ); ?></h2>
                    </div>
                </div>

				<?php if ( $rf_restricted ) {
					rf_render_restricted_notice();
				} ?>

                <div class="<?php echo $rf_restricted ? 'rf-authed-only rf-hide-blocked' : ''; ?>">
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-card divide-y divide-slate-100 overflow-hidden">
						<?php foreach ( $all_downloads as $index => $file ) :
							// 1-based, matching the /download/{n}/ endpoint.
							$file_url  = rom_download_url( $post, $index + 1 );
							$file_type = ! empty( $file['file_type'] ) ? $file['file_type'] : '';
							// Same as the rom page: the badge shows the archive the link really
							// serves, read off the URL; the Format line keeps the editor's file_type.
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
	$related_query = rom_nearby_query( $post, $console, 6 );

	if ( $related_query->have_posts() ) : ?>
        <section class="py-8 sm:py-12 border-t border-slate-200/80">
            <div class="max-w-content mx-auto px-4 sm:px-6">
                <div class="flex items-center justify-between mb-6 gap-3">
                    <div>
                        <span class="text-caption font-bold uppercase tracking-widest text-amber-600">
							<?php echo esc_html( sprintf( __( 'More %s', 'rom' ), $console->name ) ); ?>
                        </span>
                        <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight mt-0.5"><?php esc_html_e( 'Related Emulators', 'rom' ); ?></h2>
                    </div>
                    <a href="<?php echo esc_url( $console_link ); ?>"
                       class="text-caption sm:text-meta font-bold text-primary underline underline-offset-4 hover:text-primary-hover flex-shrink-0"><?php esc_html_e( 'View all', 'rom' ); ?> &rarr;</a>
                </div>
                <div class="horizontal-scroll-container">
                    <div class="horizontal-scroll-wrapper">
						<?php while ( $related_query->have_posts() ) : $related_query->the_post(); ?>
                            <div class="flex-shrink-0 w-1/2 md:w-1/3 lg:w-1/4 min-w-0 px-1.5 md:px-2 box-border grid">
								<?php get_template_part( 'template-parts/emu-item' ); ?>
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
