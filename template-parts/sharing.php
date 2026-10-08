<?php
$post_url = urlencode(get_permalink());
$post_title = urlencode(get_the_title());
?>
<div class="pt-2 w-full">
	<hr class="border-0 border-t border-gray-50 mb-3">
	<p class="text-sm text-gray-600 font-semibold mb-3"><?php _e('Share this article', 'rom'); ?></p>
	<div class="flex gap-3">
		<a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $post_url; ?>" target="_blank" rel="noopener noreferrer" class="no-underline text-gray-800 w-10 h-10 rounded-full bg-shell border border-border flex items-center justify-center text-ink hover:bg-brand hover:text-white hover:border-brand transition">
			<span class="text-sm font-bold">f</span>
		</a>
		<a href="https://x.com/intent/tweet?url=<?php echo $post_url; ?>&text=<?php echo $post_title; ?>" target="_blank" rel="noopener noreferrer" class="no-underline text-gray-800 w-10 h-10 rounded-full bg-shell border border-border flex items-center justify-center text-ink hover:bg-brand hover:text-white hover:border-brand transition">
			<span class="text-sm font-bold">x</span>
		</a>
		<a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo $post_url; ?>" target="_blank" rel="noopener noreferrer" class="no-underline text-gray-800 w-10 h-10 rounded-full bg-shell border border-border flex items-center justify-center text-ink hover:bg-brand hover:text-white hover:border-brand transition">
			<span class="text-sm font-bold">in</span>
		</a>
		<div class="relative">
			<a href="#" onclick="copyToClipboard('<?php echo esc_js(get_permalink()); ?>', this); return false;" class="no-underline w-10 h-10 rounded-full bg-shell border border-border flex items-center justify-center text-ink hover:bg-romfun-pink-light hover:text-white hover:border-brand transition">
				<span class="text-sm">🔗</span>
			</a>
		</div>
	</div>
</div>