<?php
/**
 * The sidebar containing the main widget area.
 *
 * @package romsvault
 */
?>
<aside id="secondary" class="col-12 col-lg-4 col-xl-3 widget-area">


	<?php
	$args = array(
		'post_type' 	 => 'rom',
		'posts_per_page' => 5,
		'no_found_rows'  => true,
		'meta_key'   	 => 'downloads',
		'orderby' 	 	 => 'meta_value_num',  //meta_value_num
		'order' 	 	 => 'DESC',
	);
	$query = new WP_Query($args);
	if ( $query->have_posts() ) :
	?>
		<section class="mb-4">
			<h4 class="h5 font-weight-bold text-uppercase mb-3"><?php _e('Popular Roms', 'romsfun'); ?></h4>

			<?php 
			while ( $query->have_posts() ) : $query->the_post();
				get_template_part('template-parts/content', 'aside-rom');
			endwhile; wp_reset_postdata(); 
			?>
		</section>
	<?php endif; ?>

	<?php
	$args = array(
		'post_type' 	 => 'emulator',
		'posts_per_page' => 10,
		'no_found_rows'  => true,
		'meta_key'   	 => 'downloads',
		'orderby' 	 	 => 'meta_value_num',
		'order' 	 	 => 'DESC',
	);
	$query = new WP_Query($args);
	if ( $query->have_posts() ) :
	?>
		<section class="mb-4">
			<h4 class="h5 font-weight-bold text-uppercase mb-3"><?php _e('Popular Emulators', 'romsfun'); ?></h4>

			<?php 
			while ( $query->have_posts() ) : $query->the_post();
				get_template_part('template-parts/content', 'aside-emulator');
			endwhile; wp_reset_postdata(); 
			?>
		</section>
	<?php endif; ?>
</aside>