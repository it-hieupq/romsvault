<?php
/*
 * Pagination
 */
if( !function_exists('k_pagination') ){
function k_pagination($pages = '', $range = 1) {
    global $paged;
    $showitems = ( $range * 2 )+1;
    if( empty( $paged) ) $paged = 1;
    if($pages == ''){
        global $wp_query;
        $pages = $wp_query->max_num_pages;
        if(!$pages){
            $pages = 1;
        }
    }
    if(1 != $pages) {
        echo "<nav class='nav-pagination flex justify-center mt-8 mb-4'><ul class='flex items-center justify-center gap-2 flex-wrap'>";
        if($paged > 2 && $paged > $range+1 && $showitems < $pages) echo '<li><a class="flex items-center justify-center w-8 h-8 rounded-full border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 hover:border-gray-400 transition-colors duration-200" href="' . get_pagenum_link(1) . '" aria-label="First page"><svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor"><path d="M390.3 473.9L180.9 264.5c-4.7-4.7-4.7-12.3 0-17L390.3 38.1c4.7-4.7 12.3-4.7 17 0l19.8 19.8c4.7 4.7 4.7 12.3 0 17L246.4 256l180.7 181.1c4.7 4.7 4.7 12.3 0 17l-19.8 19.8c-4.7 4.7-12.3 4.7-17 0zm-143 0l19.8-19.8c4.7-4.7 4.7-12.3 0-17L86.4 256 267.1 74.9c4.7-4.7 4.7-12.3 0-17l-19.8-19.8c-4.7-4.7-12.3-4.7-17 0L20.9 247.5c-4.7 4.7-4.7 12.3 0 17l209.4 209.4c4.7 4.7 12.3 4.7 17 0z"/></svg></a></li>';
        if($paged > 1 && $showitems < $pages) echo '<li><a class="flex items-center justify-center w-8 h-8 rounded-full border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 hover:border-gray-400 transition-colors duration-200" aria-label="Previous" href="' . get_pagenum_link($paged - 1) . '"><svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 512" fill="currentColor"><path d="M231.293 473.899l19.799-19.799c4.686-4.686 4.686-12.284 0-16.971L70.393 256 251.092 74.87c4.686-4.686 4.686-12.284 0-16.971L231.293 38.1c-4.686-4.686-12.284-4.686-16.971 0L4.908 247.515c-4.686 4.686-4.686 12.284 0 16.971L214.322 473.9c4.687 4.686 12.285 4.686 16.971-.001z"/></svg></a></li>';
        for ($i=1; $i <= $pages; $i++) {
            if (1 != $pages &&( !($i >= $paged+$range+1 || $i <= $paged-$range-1) || $pages <= $showitems )){
                if($paged == $i) {
                    echo "<li><span class='flex items-center justify-center w-8 h-8 rounded-full bg-rom-pink text-white font-semibold cursor-not-allowed' aria-current='page'>" . $i . "</span></li>";
                } else {
                    echo "<li><a class='flex items-center justify-center w-8 h-8 rounded-full border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 hover:border-gray-400 transition-colors duration-200' href='".get_pagenum_link($i)."'>".$i."</a></li>";
                }
            }
        }
        if ($paged < $pages && $showitems < $pages) echo '<li><a class="flex items-center justify-center w-8 h-8 rounded-full border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 hover:border-gray-400 transition-colors duration-200" aria-label="Next" href="' . get_pagenum_link($paged + 1) . '"><svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 512" fill="currentColor"><path d="M24.707 38.101L4.908 57.899c-4.686 4.686-4.686 12.284 0 16.971L185.607 256 4.908 437.13c-4.686 4.686-4.686 12.284 0 16.971L24.707 473.9c4.686 4.686 12.284 4.686 16.971 0l209.414-209.414c4.686-4.686 4.686-12.284 0-16.971L41.678 38.101c-4.687-4.687-12.285-4.687-16.971 0z"/></svg></a></li>';
        
		if ( $paged < $pages - 1 ) {
			if ( $paged + $range - 1 < $pages ) {
				if ( $showitems < $pages ) {
					echo '<li><a class="flex items-center justify-center w-8 h-8 rounded-full border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 hover:border-gray-400 transition-colors duration-200" href="' . get_pagenum_link($pages) . '" aria-label="Last page"><svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor"><path d="M57.7 38.1l209.4 209.4c4.7 4.7 4.7 12.3 0 17L57.7 473.9c-4.7 4.7-12.3 4.7-17 0l-19.8-19.8c-4.7-4.7-4.7-12.3 0-17L201.6 256 20.9 74.9c-4.7-4.7-4.7-12.3 0-17l19.8-19.8c4.7-4.7 12.3-4.7 17 0zm143 0l-19.8 19.8c-4.7 4.7-4.7 12.3 0 17L361.6 256 180.9 437.1c-4.7 4.7-4.7 12.3 0 17l19.8 19.8c4.7 4.7 12.3 4.7 17 0l209.4-209.4c4.7-4.7 4.7-12.3 0-17L217.7 38.1c-4.7-4.7-12.3-4.7-17 0z"/></svg></a></li>';
				}
			}
		}
       
	   echo "</ul></nav>";
    }
}
}