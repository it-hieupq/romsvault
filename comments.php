
   <section class="comment-section" id="comments">
		<?php
		// Get current page from query var or default to 1
		$current_page = isset($_GET['cpage']) ? absint($_GET['cpage']) : 1;
		if ($current_page < 1) {
			$current_page = 1;
		}

		$comments_per_page = 10;

		// Get cached parent comments with pagination
		$comments_data = k_get_parent_comments($post->ID, $current_page, $comments_per_page);
		$comments = $comments_data['comments'];
		$total_comments = $comments_data['total'];
		?>

        <div class="bg-white rounded-2xl shadow-md p-6 mb-8">
            <h2 class="text-lg font-bold mb-4 text-gray-900"><?php _e( 'Discussions', 'rom' ) ?></h2>

            <?php // Cache-safe: rendered the same for everyone. JS toggles html.rf-authed
                  // to show the member form vs the sign-in prompt (no is_user_logged_in). ?>

            <!-- Guest: prompt to sign in (member-only comments, plan §4.7). Display classes sit on
                 the inner div so they never fight rf-guest-only's own display rule. -->
            <div class="rf-guest-only mb-6">
                <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 p-4 rounded-2xl bg-slate-50 border border-slate-200/70">
                    <span class="shrink-0 inline-flex items-center justify-center w-10 h-10 rounded-full bg-primary-light text-primary">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </span>
                    <p class="flex-1 m-0 text-meta text-slate-600">
                        <span class="block font-semibold text-slate-800"><?php _e( 'Join the discussion', 'rom' ); ?></span>
                        <?php _e( 'Sign in to comment, report broken links and more.', 'rom' ); ?>
                    </p>
                    <button type="button" data-rf-login class="self-start sm:self-auto inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-primary text-white font-bold text-xs hover:bg-primary-hover shadow-sm shadow-primary/25 active:scale-95 transition-all">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>
                        <?php _e( 'Sign in', 'rom' ); ?>
                    </button>
                </div>
            </div>

            <!-- Member: comment form (raw text, no name/email — identity from session) -->
            <form id="commentForm" class="rf-authed-only form-comment space-y-4 mb-6" method="POST" action="" novalidate>
                <input type="hidden" name="post_id" value="<?php echo esc_attr( $post->ID ); ?>">

                <!-- Inline message (e.g. blocked account) -->
                <div id="commentFormMsg" class="hidden p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"></div>

                <!-- Reply Indicator (shown when replying) -->
                <div id="replyIndicator" class="hidden p-3 bg-rom-pink/10 border border-rom-pink/20 rounded-lg mb-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-700">
                            <span class="font-semibold text-rom-pink"><?php _e( 'Replying to:', 'rom' ); ?></span>
                            <span id="replyAuthorName" class="ml-1"></span>
                        </span>
                        <button type="button" id="cancelReply" class="text-sm text-rom-pink hover:underline"><?php _e( 'Cancel', 'rom' ); ?></button>
                    </div>
                </div>

                <div>
                    <textarea
                            id="commentMessage"
                            name="message"
                            placeholder="<?php _e( 'Your message', 'rom' ); ?>"
                            rows="5"
                            maxlength="240"
                            required
                            class="w-full bg-gray-100 px-4 py-3 border border-gray-300 rounded-2xl focus:outline-none focus:border-rom-pink focus:bg-white text-gray-900"></textarea>
                </div>

                <button type="submit" class="px-6 py-3 bg-rom-pink text-white rounded-full hover:bg-rom-pink-dark transition font-semibold">
				    <?php _e( 'Send comment', 'rom' ); ?>
                </button>
            </form>

			<?php
			if ( $comments ) :
				// Get all parent comment IDs for optimized child comment query
				$parent_ids = array();
				foreach ($comments as $comment) {
					$parent_ids[] = $comment->comment_ID;
				}

				// Recursively get ALL descendant comments (all nested levels) for all parent comments
				$all_descendants = k_get_all_descendant_comments($post->ID, $parent_ids);

				// Build a map of all descendants by their top-level parent (level 0 comment ID)
				// This helps us quickly filter descendants for each parent comment
				$descendants_by_parent = array();

				// Build a lookup map for efficient parent traversal
				$descendant_map = array();
				foreach ($all_descendants as $desc) {
					$descendant_map[$desc->comment_ID] = $desc;
				}

				// Function to find the top-level parent (level 0) for any descendant comment
				$find_top_parent = function($comment) use (&$find_top_parent, $parent_ids, $descendant_map) {
					$current_parent_id = $comment->comment_parent;

					// If parent is one of our level 0 parents, we found it
					if (in_array($current_parent_id, $parent_ids)) {
						return $current_parent_id;
					}

					// Otherwise, traverse up the tree
					if (isset($descendant_map[$current_parent_id])) {
						return $find_top_parent($descendant_map[$current_parent_id]);
					}

					// Fallback: return the direct parent (shouldn't happen normally)
					return $current_parent_id;
				};

				// Build descendants map for each parent
				foreach ($all_descendants as $descendant) {
					$top_parent_id = $find_top_parent($descendant);

					if (in_array($top_parent_id, $parent_ids)) {
						if (!isset($descendants_by_parent[$top_parent_id])) {
							$descendants_by_parent[$top_parent_id] = array();
						}
						$descendants_by_parent[$top_parent_id][] = $descendant;
					}
				}
				?>
                <div class="space-y-6">

					<?php foreach ( $comments as $key => $comment ) :
						// Get ALL descendant comments (all nested levels) for this parent
						$all_level1_comments = isset($descendants_by_parent[$comment->comment_ID])
							? $descendants_by_parent[$comment->comment_ID]
							: array();

						// Sort all descendant comments by date (oldest first)
						usort($all_level1_comments, function($a, $b) {
							return strtotime($a->comment_date) - strtotime($b->comment_date);
						});
						?>
                        <div class="border-b border-gray-200 pb-6 last:border-b-0 last:pb-0">
                            <!-- Parent Comment (Level 0) -->
                            <div class="flex items-start gap-4 mb-3">
                                <div class="w-12 h-12 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center flex-shrink-0">
	                                <?php echo get_avatar( $comment, 40, '', '', array( 'class' => 'rounded-full' ) ); ?>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <h4 class="font-bold text-gray-900"><?php echo esc_html(get_comment_author( $comment )); ?></h4>
                                        <?php if ( rf_comment_is_member( $comment ) ) : ?>
                                            <span class="verified-badge inline-flex items-center justify-center rounded-full bg-rom-pink text-white" title="<?php esc_attr_e( 'Verified User', 'rom' ); ?>">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                </svg>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-gray-700 mb-2" style="white-space:pre-wrap"><?php echo rf_render_comment_content( $comment ); ?></p>
                                    <div class="flex items-center gap-2">
                                        <button type="button" class="comment-reply text-sm text-rom-pink hover:underline font-medium" data-parent="<?php echo esc_attr( $comment->comment_ID ); ?>" data-author="<?php echo esc_attr( get_comment_author( $comment ) ); ?>">
                                            <?php _e( 'Reply', 'rom' ); ?>
                                        </button>
                                        <span class="text-xs text-gray-500">
                                            <?php
                                            $comment_timestamp = mysql2date('U', $comment->comment_date);
                                            echo esc_html(human_time_diff($comment_timestamp, current_time('timestamp')) . ' ago');
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

	                        <?php if ( !empty($all_level1_comments) ) : ?>
                                <!-- All Level 1 Comments (including replies to level 1) -->
                                <div class="pl-16 pt-4">

			                        <?php foreach ( $all_level1_comments as $level1_comment ) :
			                            // Get parent comment to show "Replying to" indicator
			                            $level1_parent = get_comment($level1_comment->comment_parent);
			                            $level1_parent_author = $level1_parent ? get_comment_author($level1_parent) : '';
			                            ?>
                                        <div class="flex items-start gap-4 mb-4">
                                            <div class="w-12 h-12 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center flex-shrink-0">
		                                        <?php echo get_avatar( $level1_comment, 40, '', '', array( 'class' => 'rounded-full' ) ); ?>
                                            </div>

                                            <div class="flex-1">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <div class="flex items-center gap-1">
                                                        <h4 class="font-bold text-gray-900"><?php echo esc_html($level1_comment->comment_author); ?></h4>
                                                        <?php if ( rf_comment_is_member( $level1_comment ) ) : ?>
                                                            <span class="verified-badge inline-flex items-center justify-center rounded-full bg-rom-pink text-white" title="<?php esc_attr_e( 'Verified User', 'rom' ); ?>">
                                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                                </svg>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($level1_parent_author) : ?>
                                                        <span class="text-xs text-gray-500"><?php echo esc_html(sprintf(__('Replying to %s', 'rom'), $level1_parent_author)); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-gray-700 mb-2" style="white-space:pre-wrap"><?php echo rf_render_comment_content( $level1_comment ); ?></p>
                                                <div class="flex items-center gap-2">
                                                    <button type="button" class="comment-reply text-sm text-rom-pink hover:underline font-medium" data-parent="<?php echo esc_attr( $level1_comment->comment_ID ); ?>" data-author="<?php echo esc_attr( get_comment_author( $level1_comment ) ); ?>">
                                                        <?php _e( 'Reply', 'rom' ); ?>
                                                    </button>
                                                    <span class="text-xs text-gray-500">
                                                        <?php
                                                        // Convert comment date (MySQL format) to Unix timestamp
                                                        // comment_date is in local timezone, mysql2date handles conversion
                                                        $level1_comment_timestamp = mysql2date('U', $level1_comment->comment_date);
                                                        echo esc_html(human_time_diff($level1_comment_timestamp, current_time('timestamp')) . ' ago');
                                                        ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
			                        <?php endforeach; ?>

                                </div>
	                        <?php endif; ?>
                        </div>

					<?php endforeach; ?>

                </div>

                <?php
                // Display pagination if there are more than one page
                if ($total_comments > $comments_per_page) {
                    k_comment_pagination($total_comments, $comments_per_page, $current_page, get_permalink($post->ID));
                }
                ?>
			<?php endif; ?>
        </div>

    </section>
