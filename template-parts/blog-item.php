<div class="flex-shrink-0 w-full md:w-1/2 lg:w-1/3 min-w-0 px-2 box-border">
    <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition h-full">
        <div class="aspect-video relative overflow-hidden">
            <a href="<?php the_permalink(); ?>" class="block w-full">
                <img src="<?php the_post_thumbnail_url('thumbnail'); ?>"
                        alt="<?php the_title_attribute(); ?>"
                        class="w-full h-full object-cover transform hover:scale-105 transition duration-300 ease-in-out">
            </a>
        </div>

        <div class="p-6 space-y-3">
            <div class="flex items-center gap-3 text-xs text-ink-soft">
                    <span class="inline-flex items-center gap-2">
                      <span class="w-2 h-2 rounded-full bg-brand"></span>
                      <span><?php the_modified_date() ?></span>
                    </span>
                <span>•</span>
                <span>
                    <?php echo sprintf( '%d min read', max( 1, ceil( str_word_count(get_the_content()) / 200 ) ) ); ?>
                </span>
            </div>
            <h3 class="text-base font-bold line-clamp-2">
                <a class="hover:text-romfun-pink" href="<?php the_permalink(); ?>">
				<?php the_title() ?>
                </a>
            </h3>

            <div class="text-muted">
                <?php echo truncate(get_the_excerpt(), 150) ?>
            </div>

            <div class="flex flex-wrap gap-2">
                <span class="px-3 py-1 rounded-full bg-shell text-gray-600 border text-xs flex">
                    <svg class="inline w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960"><path d="M480-312q70 0 119-49t49-119q0-70-49-119t-119-49q-70 0-119 49t-49 119q0 70 49 119t119 49Zm0-72q-40 0-68-28t-28-68q0-40 28-68t68-28q40 0 68 28t28 68q0 40-28 68t-68 28Zm0 192q-142.6 0-259.8-78.5Q103-349 48-480q55-131 172.2-209.5Q337.4-768 480-768q142.6 0 259.8 78.5Q857-611 912-480q-55 131-172.2 209.5Q622.6-192 480-192Zm0-288Zm0 216q112 0 207-58t146-158q-51-100-146-158t-207-58q-112 0-207 58T127-480q51 100 146 158t207 58Z"/></svg>
                    <?php echo get_field( 'views' ) . ' ' . __( 'views', 'roms' ) ?>
                </span>
                <span class="px-3 py-1 rounded-full bg-shell text-gray-600 border text-xs flex">
                      <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                      </svg>
                    <?php echo get_comments_number() . ' ' . __( 'comments', 'roms' ); ?>
                </span>
            </div>
        </div>

    </div>
</div>
