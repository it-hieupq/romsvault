<?php
$terms = $args['terms'] ?? [];
$title = $args['title'] ?? [];
$type  = $args['type'] ?? [];
?>

<h3 class="text-lg font-semibold mb-8 flex items-center text-gray-600">
    <span class="w-3 h-3 rounded-full bg-romfun-pink mr-2"></span>
    <?php echo $title ?>
</h3>

<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
    <?php foreach ( $terms as $term ) : 
        $image = get_field('cat_image', 'term_' . $term->term_id); 
        ?>
        <a href="<?php echo get_term_link( $term->term_id ) ?>"
           class="border border-gray-200 rounded-xl block shadow-sm hover:shadow-lg transition overflow-hidden">

            <div class="flex items-center justify-center h-[160px] overflow-hidden bg-gray-100 p-4">
                <img src="<?php echo $image; ?>" alt="<?php echo $term->name; ?>" />
            </div>
            <div class="console-info bg-white px-3 py-6">
                <div class="font-bold text-base text-center mb-6 hover:text-romfun-pink"><?php echo $term->name ?></div>
                <div class="text-xs text-gray-500 flex justify-between">
                    <span class="inline-flex items-center">
                        <?php echo get_svg_icon( 'joystick' ) . number_format( $term->count?:0, 0, '' ) . ' ' . $type ?>
                    </span>
                    <span class="inline-flex items-center">
                        <?php
                        $console_downloads = get_field('console_rom_downloads', 'term_' . $term->term_id);
                        echo get_svg_icon( 'download' ) . number_format( $console_downloads ?: 0, 0, '' ) . ' ' . __( '', 'rom' );
                        ?>
                    </span>
                </div>
            </div>
        </a>
    <?php endforeach; ?>
</div>