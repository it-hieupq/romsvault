<div class="bg-white rounded-xl p-4 border border-border">
    <div class="flex items-center gap-4">
        <div class="w-28 h-28 rounded-lg overflow-hidden flex-shrink-0">
            <a href="<?php the_permalink(); ?>">
                <img src="<?php the_post_thumbnail_url('thumbnail'); ?>" class="w-full h-full object-cover" alt="<?php the_title() ?>">
            </a>
        </div>
        <div class="space-y-1">

            <div class="flex items-center justify-start mb-2">
                <p class="text-xs uppercase tracking-wide text-muted">
                    <span class="w-2 h-2 rounded-full bg-romfun-pink inline-block mr-1"></span>
			        <?php the_modified_date() ?>
                </p>
                <span class="mx-2 text-muted">•</span>
                <span class="text-xs text-muted"> <?php echo sprintf( '%d min read', max( 1, ceil( str_word_count(get_the_content()) / 200 ) ) ); ?></span>
            </div>

            <h3 class="font-bold"><a class="hover:text-romfun-pink" href="<?php the_permalink(); ?>"><?php the_title() ?></a></h3>
            <p class="text-sm text-muted leading-relaxed">
                <?php echo truncate(get_the_excerpt(), 150); ?>
            </p>
        </div>
    </div>
</div>