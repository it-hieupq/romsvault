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

	$download_url  = trailingslashit( get_permalink( $post ) ) . 'download/';
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

	// Spec cells, built from whatever the rom actually has.
	// No Platform cell: the console already leads the tag row and the h1.
	$specs = array();
	if ( ! empty( $file_size ) ) {
		$specs[] = array(
			'icon'  => 'size',
			'label' => __( 'File Size', 'rom' ),
			'value' => esc_html( $file_size ),
		);
	}
	foreach ( array(
		'region'     => array( 'region', __( 'Region', 'rom' ) ),
		'dev'        => array( 'dev', __( 'Developer', 'rom' ) ),
		'pub'        => array( 'joystick', __( 'Publisher', 'rom' ) ),
		'collection' => array( 'collection', __( 'Collection', 'rom' ) ),
		'type'       => array( 'type', __( 'Type', 'rom' ) ),
		'language'   => array( 'flag', __( 'Language', 'rom' ) ),
	) as $spec_tax => $spec_meta ) {
		$spec_terms = wp_get_post_terms( $post->ID, $spec_tax, array( 'fields' => 'all' ) );
		if ( is_wp_error( $spec_terms ) || empty( $spec_terms ) ) {
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
		$specs[] = array(
			'icon'  => $spec_meta[0],
			'label' => $spec_meta[1],
			'value' => implode( ', ', $spec_links ),
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

                    <!-- Boxart -->
                    <div class="md:col-span-5 lg:col-span-4 flex flex-col items-center">
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
                    <div class="md:col-span-7 lg:col-span-8 flex flex-col justify-between space-y-5 sm:space-y-6">

                        <!-- Console + genre tags -->
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="<?php echo esc_url( $console_link ); ?>"
                               class="px-3 py-1 rounded-lg bg-rose-50 text-primary text-caption font-bold border border-rose-200/60 hover:bg-rose-100 transition-colors"><?php echo esc_html( $console->name ); ?></a>
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
                            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight leading-snug">
								<?php
								/* translators: 1: game title, 2: console name. */
								echo esc_html( sprintf( __( '%1$s %2$s ROM', 'rom' ), get_the_title(), $console->name ) );
								?>
                            </h1>
                            <div class="flex flex-wrap items-center gap-3 sm:gap-4 text-meta text-slate-500 mt-2">
                                <span class="flex items-center gap-1 text-slate-600">
									<?php echo esc_html( number_format( $downloads, 0, '', ',' ) . ' ' . __( 'downloads', 'rom' ) ); ?>
                                </span>
                                <span class="text-slate-300">&bull;</span>
                                <span class="text-slate-500"><?php echo esc_html( sprintf( __( 'Updated: %s', 'rom' ), get_the_modified_date( 'M Y' ) ) ); ?></span>
                            </div>
                        </div>

                        <!-- Specs grid -->
						<?php if ( $specs ) : ?>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 sm:gap-3.5 my-1">
								<?php foreach ( $specs as $spec ) : ?>
                                    <div class="p-3 sm:p-3.5 rounded-xl bg-slate-50/80 border border-slate-200/70 hover:bg-slate-50 hover:border-slate-300 transition-colors flex items-start gap-2.5">
                                        <div class="w-8 h-8 rounded-lg bg-rose-50 border border-rose-100/80 text-primary flex items-center justify-center flex-shrink-0 mt-0.5 [&_svg]:w-4 [&_svg]:h-4 [&_svg]:mr-0">
											<?php echo get_svg_icon( $spec['icon'] ); ?>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400"><?php echo esc_html( $spec['label'] ); ?></span>
                                            <span class="font-bold text-[13px] text-slate-800 mt-0.5 block truncate"><?php echo $spec['value']; ?></span>
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
                                    <span class="<?php echo $rf_restricted ? 'rf-authed-only rf-hide-blocked' : ''; ?> flex flex-wrap items-center gap-3">
                                        <a href="<?php echo esc_url( $download_url ); ?>"
                                           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-primary text-white font-bold text-meta hover:bg-primary-hover active:scale-95 transition-all shadow-sm shadow-primary/25 [&_svg]:w-4 [&_svg]:h-4">
											<?php echo get_svg_icon( 'save' ) . esc_html__( 'Download ROM', 'rom' ); ?>
                                        </a>
										<?php
										$emu_term = get_term_by( 'slug', $console->slug, 'emulator_console' );
										if ( $emu_term && ! is_wp_error( $emu_term ) && $emu_term->count > 0 ) : ?>
                                            <a href="<?php echo esc_url( get_term_link( $emu_term->term_id ) ); ?>"
                                               class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-slate-100 text-slate-700 font-semibold text-meta hover:bg-slate-200 hover:text-slate-900 transition-colors [&_svg]:w-3.5 [&_svg]:h-3.5">
												<?php echo get_svg_icon( 'emulator' ) . esc_html( sprintf( __( 'Download %s Emulator', 'rom' ), $emu_term->name ) ); ?>
                                            </a>
										<?php endif; ?>
                                    </span>
								<?php endif; ?>
								<?php rf_render_wishlist_button( $post->ID ); ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </section>

	<?php if ( $screenshots = get_field( 'image' ) ) :
		$shot_total = count( $screenshots );
		?>
        <!-- Screenshots. Fixed 200px height, width left to each shot's own ratio, so the
             row flows left to right and wraps when it runs out of width. Shot ratios vary
             per console (a DS capture is portrait 256x384, a PS2 one landscape), which is
             why no column grid is used here. -->
        <section id="screenshots" class="py-6 sm:py-8 scroll-mt-24">
            <div class="max-w-content mx-auto px-4 sm:px-6">
                <div class="flex items-end justify-between gap-3 mb-3">
                    <div>
                        <span class="text-caption font-bold uppercase tracking-widest text-primary"><?php esc_html_e( 'Gameplay Gallery', 'rom' ); ?></span>
                        <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight mt-0.5"><?php esc_html_e( 'Screenshots', 'rom' ); ?></h2>
                    </div>
                    <span class="text-caption text-slate-500 flex-shrink-0">
                        <?php echo esc_html( sprintf( _n( '%s image', '%s images', $shot_total, 'rom' ), number_format_i18n( $shot_total ) ) ); ?>
                    </span>
                </div>

                <div id="romGallery" class="flex flex-wrap items-start gap-2.5">
                    <?php foreach ( $screenshots as $img ) :
                        $caption  = $img['caption'] ?: ( $img['alt'] ?: get_the_title() );
                        $alt_text = $img['alt'] ?: get_the_title() . ' - Screenshot';
                        ?>
                        <a href="<?php echo esc_url( $img['url'] ); ?>"
                           class="group relative inline-block shrink-0 max-w-full rounded-lg overflow-hidden border border-slate-200/80 bg-slate-900 shadow-subtle hover:border-primary/50 transition-all"
                           data-src="<?php echo esc_url( $img['url'] ); ?>"
                           data-sub-html="<?php echo esc_attr( $caption ); ?>">
                            <img src="<?php echo esc_url( $img['url'] ); ?>" alt="<?php echo esc_attr( $alt_text ); ?>"
                                 class="block h-[200px] w-auto max-w-full object-cover opacity-90 group-hover:opacity-100 group-hover:scale-105 transition-all duration-300"
                                 loading="lazy">
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
	<?php endif; ?>

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
							// 1-based, matching the /download/{n}/ endpoint.
							$file_url  = $download_url . ( $index + 1 ) . '/';
							$file_type = ! empty( $file['file_type'] ) ? $file['file_type'] : '';
							?>
                            <div class="p-4 sm:p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:bg-rose-50/20 transition-colors">
                                <div class="flex items-start gap-3.5 flex-1 min-w-0">
                                    <div class="w-10 h-10 rounded-xl bg-rose-50 text-primary flex items-center justify-center font-bold text-caption flex-shrink-0 mt-0.5 uppercase">
										<?php echo esc_html( $file_type ? ltrim( $file_type, '.' ) : '#' . ( $index + 1 ) ); ?>
                                    </div>
                                    <div class="min-w-0">
                                        <span class="font-bold text-slate-900 text-meta sm:text-body block"><?php echo esc_html( $file['name'] ); ?></span>
                                        <div class="flex flex-wrap items-center gap-2.5 text-caption text-slate-500 mt-1">
											<?php if ( $file_type ) : ?>
                                                <span><?php esc_html_e( 'Format:', 'rom' ); ?> <strong class="text-slate-700"><?php echo esc_html( $file_type ); ?></strong></span>
											<?php endif; ?>
											<?php if ( ! empty( $file['file_size'] ) ) : ?>
												<?php if ( $file_type ) : ?><span>&bull;</span><?php endif; ?>
                                                <span><?php esc_html_e( 'File Size:', 'rom' ); ?> <strong class="text-slate-800 font-bold"><?php echo esc_html( $file['file_size'] ); ?></strong></span>
											<?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 self-start md:self-center flex-shrink-0">
                                    <a href="<?php echo esc_url( $file_url ); ?>"
                                       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-primary text-white font-bold text-caption uppercase tracking-wider hover:bg-primary-hover shadow-sm shadow-primary/25 active:scale-95 transition-all [&_svg]:w-3.5 [&_svg]:h-3.5">
										<?php echo get_svg_icon( 'download' ) . esc_html__( 'Download', 'rom' ); ?>
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
                               class="text-caption font-bold text-primary hover:text-primary-hover w-fit"><?php esc_html_e( 'View all', 'rom' ); ?> &rarr;</a>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
							<?php while ( $emu_query->have_posts() ) : $emu_query->the_post(); ?>
                                <div class="p-4 sm:p-5 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col justify-between gap-3 hover:border-slate-200 transition-colors">
                                    <div>
                                        <h3 class="font-bold text-body text-slate-900"><?php the_title(); ?></h3>
										<?php if ( has_excerpt() ) : ?>
                                            <p class="text-caption text-slate-600 leading-relaxed mt-1.5"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 28 ) ); ?></p>
										<?php endif; ?>
                                    </div>
                                    <a href="<?php the_permalink(); ?>"
                                       class="inline-flex items-center gap-1 text-caption font-bold text-primary hover:text-primary-hover bg-rose-50 hover:bg-rose-100 px-3 py-1.5 rounded-lg transition-colors w-fit">
										<?php esc_html_e( 'Download', 'rom' ); ?> <?php the_title(); ?> &rarr;
                                    </a>
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
                    <div class="prose max-w-none text-slate-700 text-body"><?php echo wpautop( $console_guide ); ?></div>
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
	// Other roms in the same collection.
	$collections = wp_get_post_terms( $post->ID, 'collection' );
	if ( ! is_wp_error( $collections ) && ! empty( $collections ) ) :
		$primary_collection    = $collections[0];
		$collection_roms_query = new WP_Query( array(
			'post_type'      => 'rom',
			'posts_per_page' => 10,
			'post__not_in'   => array( $post->ID ),
			'post_status'    => 'publish',
			'no_found_rows'  => true,
			'tax_query'      => array(
				array(
					'taxonomy' => 'collection',
					'field'    => 'term_id',
					'terms'    => $primary_collection->term_id,
				),
			),
		) );

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
                           class="text-caption sm:text-meta font-bold text-primary hover:text-primary-hover flex-shrink-0"><?php esc_html_e( 'View all', 'rom' ); ?> &rarr;</a>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 sm:gap-6">
						<?php while ( $collection_roms_query->have_posts() ) : $collection_roms_query->the_post();
							get_template_part( 'template-parts/rom-card' );
						endwhile; wp_reset_postdata(); ?>
                    </div>
                </div>
            </section>
		<?php endif;
	endif; ?>

	<?php
	$related_query = new WP_Query( array(
		'post_type'      => 'rom',
		'posts_per_page' => 10,
		'post__not_in'   => array( $post->ID ),
		'post_status'    => 'publish',
		'no_found_rows'  => true,
		'tax_query'      => array(
			array(
				'taxonomy' => 'console',
				'field'    => 'term_id',
				'terms'    => $console->term_id,
			),
		),
	) );

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
                       class="text-caption sm:text-meta font-bold text-primary hover:text-primary-hover flex-shrink-0"><?php esc_html_e( 'View all', 'rom' ); ?> &rarr;</a>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 sm:gap-6">
					<?php while ( $related_query->have_posts() ) : $related_query->the_post();
						get_template_part( 'template-parts/rom-card' );
					endwhile; wp_reset_postdata(); ?>
                </div>
            </div>
        </section>
	<?php endif; ?>

<?php
endwhile;
get_footer();
