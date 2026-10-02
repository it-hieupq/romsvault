/**
 * main.js — merged theme JavaScript.
 *   Section 1: jQuery interactions (comments, report, rating) — was site.js
 *   Section 2: Vanilla UI behaviors (menu, filters, search, modal, download) — was function.js
 * Toast notifications live in toast.js (window.showToast); no toast code here.
 */

// ==========================================================================
// Section 1 — jQuery interactions
// ==========================================================================

jQuery(function ($) {

	// Toast notifications live in toast.js (window.showToast / window.removeToast).

	/**
	 * Comment Form Submission
	 */
	var commentFormSubmitting = false;

	function rfEscapeHtml(s) {
		return String(s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	// Optimistically insert the just-submitted comment so the member sees it even
	// though the cached HTML has not changed (plan §13.M — avoids duplicate posts).
	function rfInsertPendingComment(comment) {
		var $list = $('#comments .space-y-6').first();
		if (!$list.length) { return; }
		var display = (window.rfAuth && window.rfAuth.getDisplay()) || {};
		var author = rfEscapeHtml(display.name || 'You');
		var body = rfEscapeHtml(comment.content); // newlines shown via CSS white-space:pre-wrap
		// Show the member's own avatar (from localStorage rf_display); fall back to
		// the gradient placeholder when they have none, matching the server default.
		var avatar = display.avatar
			? '<img src="' + rfEscapeHtml(display.avatar) + '" class="w-12 h-12 rounded-full object-cover flex-shrink-0" alt="" />'
			: '<div class="w-12 h-12 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex-shrink-0"></div>';
		var html = '<div class="border-b border-gray-200 pb-6 last:border-b-0 last:pb-0">' +
			'<div class="flex items-start gap-4 mb-3">' +
			avatar +
			'<div class="flex-1"><div class="flex items-center gap-2 mb-1">' +
			'<h4 class="font-bold text-gray-900">' + author + '</h4>' +
			'<span class="text-xs text-gray-500 italic">awaiting moderation</span></div>' +
			'<p class="text-gray-700 mb-2" style="white-space:pre-wrap">' + body + '</p></div></div></div>';
		$list.prepend(html);
	}

	$('#commentForm').on('submit', function (e) {
		e.preventDefault();

		if (commentFormSubmitting) {
			return false;
		}

		var $form = $(this);
		var $submitBtn = $form.find('button[type="submit"]');
		var $messageField = $('#commentMessage');

		$messageField.removeClass('border-red-500');

		var comment = $messageField.val().trim();
		if (!comment) {
			showToast('Please input your comment.', 'error');
			$messageField.addClass('border-red-500');
			return false;
		}
		if (comment.length > 240) {
			showToast('Your comment is too long.', 'error');
			$messageField.addClass('border-red-500');
			return false;
		}

		// Member-only (plan §4.7). If localStorage says not logged in, open the modal.
		if (!window.rfAuth || !window.rfAuth.getDisplay()) {
			if (window.rfAuth) { window.rfAuth.openLogin(); }
			return false;
		}

		var postId = parseInt($form.find('input[name="post_id"]').val(), 10) || 0;
		var parent = parseInt($form.find('input[name="parent"]').val(), 10) || 0;

		commentFormSubmitting = true;
		var originalBtnText = $submitBtn.html();
		var loadingIcon = '<svg class="inline animate-spin mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="18px" height="18px" fill="currentColor"><path d="M480-192q-120 0-204-84t-84-204q0-120 84-204t204-84q65 0 120.5 27t95.5 72v-99h72v240H528v-72h131q-29-44-76-70t-103-26q-90 0-153 63t-63 153q0 90 63 153t153 63q84 0 144-55.5T693-456h74q-9 112-91 188t-196 76Z"/></svg>';
		$submitBtn.prop('disabled', true).html(loadingIcon + originalBtnText);

		function reset() {
			commentFormSubmitting = false;
			$submitBtn.prop('disabled', false).html(originalBtnText);
		}

		window.rfAuth.fetch('comment', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ post: postId, content: comment, parent: parent })
		})
			.then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
			.then(function (res) {
				reset();
				if (res.status === 200 && res.body && res.body.ok) {
					$('#commentFormMsg').addClass('hidden').text('');
					showToast('Thank you for your comment. It is awaiting moderation.', 'success');
					rfInsertPendingComment(res.body.comment || { content: comment });
					$form[0].reset();
					$form.find('input[name="parent"]').remove();
					$('#replyIndicator').addClass('hidden');
					$('#replyAuthorName').text('');
					var $formTitle = $form.closest('.bg-white').find('h2');
					if ($formTitle.length && $formTitle.data('original-title')) {
						$formTitle.text($formTitle.data('original-title'));
					}
					return;
				}
				// One message only, taken from the server response.
				var message = (res.body && res.body.message) ? res.body.message : 'An error occurred while submitting your comment. Please try again.';
				if (res.status === 401) { if (window.rfAuth) { window.rfAuth.openLogin(); } return; }
				// Blocked account: show the message at the form, not as a toast.
				if (res.status === 403 && res.body && res.body.error === 'blocked') { $('#commentFormMsg').text(message).removeClass('hidden'); return; }
				showToast(message, 'error');
			})
			.catch(function () {
				reset();
				showToast('Network error. Please try again later.', 'error');
			});

		return false;
	});

	/**
	 * Comment Reply Handler
	 */
	$(document).on('click', '.comment-reply', function (e) {
		e.preventDefault();

		var parentId = $(this).data('parent');
		var authorName = $(this).data('author') || '';
		var $form = $('#commentForm');

		if (!$form.length) {
			return;
		}

		// Remove existing parent input
		$form.find('input[name="parent"]').remove();

		// Add parent comment ID
		if (parentId) {
			$form.prepend('<input type="hidden" name="parent" value="' + parseInt(parentId) + '"/>');
		}

		// Show reply indicator
		var $replyIndicator = $('#replyIndicator');
		var $replyAuthorName = $('#replyAuthorName');
		if ($replyIndicator.length && authorName) {
			$replyAuthorName.text(authorName);
			$replyIndicator.removeClass('hidden');
		}

		// Update form title to show we're replying
		var $formTitle = $form.closest('.bg-white').find('h2');
		if (parentId && $formTitle.length) {
			var originalTitle = $formTitle.data('original-title');
			if (!originalTitle) {
				originalTitle = $formTitle.text();
				$formTitle.data('original-title', originalTitle);
			}
			$formTitle.text('Reply to Comment');
		}

		// Focus on comment field
		$('#commentMessage').focus();

		// Scroll to form
		$('html, body').animate({
			scrollTop: $form.offset().top - adminbarHeight - siteheaderHeight - 20
		}, 600);
	});

	/**
	 * Cancel Reply Handler
	 */
	$(document).on('click', '#cancelReply', function (e) {
		e.preventDefault();

		var $form = $('#commentForm');

		// Remove parent input
		$form.find('input[name="parent"]').remove();

		// Hide reply indicator
		$('#replyIndicator').addClass('hidden');
		$('#replyAuthorName').text('');

		// Reset form title
		var $formTitle = $form.closest('.bg-white').find('h2');
		if ($formTitle.length && $formTitle.data('original-title')) {
			$formTitle.text($formTitle.data('original-title'));
		}
	});

	/**
	 * Report — member-only. Submits to REST rf/v1/report via the auth fetch
	 * wrapper (auth.js); guests are sent to the login modal. One toast per action;
	 * a blocked account shows its message inline at the form.
	 */
	$(document).on('submit', '#report-form', function (e) {
		e.preventDefault();
		var $form = $(this);
		if ($form.data('submitting')) { return false; }

		var $msg = $('#reportFormMsg');
		$msg.addClass('hidden').text('');

		var type = $form.find('#report-type').val();
		if (!type) { showToast('Please select an issue type.', 'error'); return false; }

		// Member-only. Guests never see this form (rf-authed-only), so just bail
		// defensively without popping a modal — the guest sees an inline sign-in line.
		if (!window.rfAuth || !window.rfAuth.getDisplay()) {
			return false;
		}

		var postId = parseInt($form.find('input[name="post_id"]').val(), 10) || 0;
		var content = $form.find('#report-content').val();

		var $submitBtn = $form.find('button[type="submit"]');
		var originalBtnText = $submitBtn.html();
		$form.data('submitting', true);
		$submitBtn.prop('disabled', true).html('Sending...');

		function reset() {
			$form.data('submitting', false);
			$submitBtn.prop('disabled', false).html(originalBtnText);
		}

		window.rfAuth.fetch('report', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ post: postId, type: type, content: content, url: window.location.href })
		})
			.then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
			.then(function (res) {
				reset();
				if (res.status === 200 && res.body && res.body.ok) {
					$form[0].reset();
					// Close the help modal (mirror #close-help-modal).
					var modal = document.getElementById('help-modal');
					if (modal) {
						var c = modal.querySelector('.relative');
						if (c) { c.classList.remove('scale-100', 'opacity-100'); c.classList.add('scale-95', 'opacity-0'); }
						setTimeout(function () { modal.classList.add('hidden'); modal.classList.remove('flex'); document.body.style.overflow = ''; }, 150);
					}
					showToast((res.body && res.body.message) ? res.body.message : 'Your report has been sent. Thank you!', 'success');
					return;
				}
				var message = (res.body && res.body.message) ? res.body.message : 'An error occurred. Please try again.';
				if (res.status === 401) { if (window.rfAuth) { window.rfAuth.openLogin(); } return; }
				if (res.status === 403 && res.body && res.body.error === 'blocked') { $msg.text(message).removeClass('hidden'); return; }
				showToast(message, 'error');
			})
			.catch(function () {
				reset();
				showToast('Network error. Please try again later.', 'error');
			});

		return false;
	});

});


// ==========================================================================
// Section 2 — Vanilla UI behaviors
// ==========================================================================

// Mobile Menu Toggle
const mobileMenuBtn = document.getElementById('mobile-menu-btn');
const mobileMenuPanel = document.getElementById('mobile-menu-panel');
const mobileMenuOverlay = document.getElementById('mobile-menu-overlay');
const mobileMenuClose = document.getElementById('mobile-menu-close');

function openMobileMenu() {
    if (mobileMenuPanel && mobileMenuOverlay) {
        mobileMenuPanel.classList.add('active');
        mobileMenuOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeMobileMenu() {
    if (mobileMenuPanel && mobileMenuOverlay) {
        mobileMenuPanel.classList.remove('active');
        mobileMenuOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }
}

if (mobileMenuBtn) {
    mobileMenuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        openMobileMenu();
    });
}

if (mobileMenuClose) {
    mobileMenuClose.addEventListener('click', (e) => {
        e.stopPropagation();
        closeMobileMenu();
    });
}

if (mobileMenuOverlay) {
    mobileMenuOverlay.addEventListener('click', () => {
        closeMobileMenu();
    });
}

// Close mobile menu when clicking on menu items
const mobileMenuItems = document.querySelectorAll('.mobile-menu-item');
mobileMenuItems.forEach(item => {
    item.addEventListener('click', () => {
        closeMobileMenu();
    });
});

// Scroll to Top Button
const scrollToTopBtn = document.getElementById('scrollToTop');
if (scrollToTopBtn) {
    // Show/hide button on scroll
    window.addEventListener('scroll', () => {
        if (window.pageYOffset > 300) {
            scrollToTopBtn.classList.remove('hidden');
        } else {
            scrollToTopBtn.classList.add('hidden');
        }
    });
    
    // Smooth scroll to top
    scrollToTopBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();

        // Use requestAnimationFrame for smooth animation
        const startPosition = window.pageYOffset || document.documentElement.scrollTop;
        const startTime = performance.now();
        const duration = 600; // 600ms animation
        
        const easeInOutCubic = (t) => {
            return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
        };
        
        const animateScroll = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const ease = easeInOutCubic(progress);
            
            window.scrollTo(0, startPosition * (1 - ease));
            
            if (progress < 1) {
                requestAnimationFrame(animateScroll);
            }
        };
        
        requestAnimationFrame(animateScroll);
    });
}

// Custom Multi-Select Dropdown Functionality
function initCustomSelect(wrapperId, triggerId, dropdownId, selectedTextId, options) {
    const wrapper = document.getElementById(wrapperId);
    const trigger = document.getElementById(triggerId);
    const dropdown = document.getElementById(dropdownId);
    const selectedText = document.getElementById(selectedTextId);

    if (!wrapper || !trigger || !dropdown || !selectedText) return;

    const searchInput = dropdown.querySelector('.custom-select-search-input');

    // Toggle dropdown on trigger click
    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        const isActive = dropdown.classList.contains('active');

        // Close all other dropdowns
        document.querySelectorAll('.custom-select-dropdown.active').forEach(dd => {
            if (dd !== dropdown) {
                dd.classList.remove('active');
                dd.parentElement.querySelector('.custom-select-trigger').classList.remove('active');
            }
        });

        // Toggle current dropdown
        dropdown.classList.toggle('active');
        trigger.classList.toggle('active');

        // Auto-focus search input when opening
        if (dropdown.classList.contains('active') && searchInput) {
            setTimeout(() => searchInput.focus(), 50);
        }
    });

    // Stop propagation on dropdown click
    dropdown.addEventListener('click', (e) => {
        e.stopPropagation();
    });

    // Filter options as the user types
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim().toLowerCase();
            dropdown.querySelectorAll('.custom-select-option').forEach(option => {
                const label = option.querySelector('label');
                if (!label) return;
                const text = label.textContent.toLowerCase();
                option.style.display = text.includes(query) ? '' : 'none';
            });
        });

        // Prevent stray key handling from closing the dropdown
        searchInput.addEventListener('keydown', (e) => {
            e.stopPropagation();
        });
    }

    // Clear-all button: uncheck every input in this dropdown
    const clearBtn = dropdown.querySelector('.custom-select-clear-all');
    if (clearBtn) {
        clearBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropdown.querySelectorAll('input:checked').forEach(input => {
                input.checked = false;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    }

    // Update selected text based on checked options
    function updateSelectedText() {
        const checkedOptions = Array.from(dropdown.querySelectorAll('input:checked'));
        if (checkedOptions.length === 0) {
            selectedText.textContent = options.placeholder || 'Select...';
            selectedText.classList.remove('has-selection');
        } else if (checkedOptions.length === 1) {
            const label = checkedOptions[0].closest('.custom-select-option').querySelector('label').textContent;
            selectedText.textContent = label;
            selectedText.classList.add('has-selection');
        } else {
            selectedText.textContent = `${checkedOptions.length} selected`;
            selectedText.classList.add('has-selection');
        }
    }

    // Handle checkbox/radio changes
    dropdown.querySelectorAll('input').forEach(input => {
        input.addEventListener('change', () => {
            updateSelectedText();
        });
    });

    // Initial update
    updateSelectedText();
}

// Initialize all custom selects
initCustomSelect('genre-select-wrapper', 'genre-select-trigger', 'genre-dropdown', 'genre-selected-text', {placeholder: 'Select Genre'});
initCustomSelect('collection-select-wrapper', 'collection-select-trigger', 'collection-dropdown', 'collection-selected-text', {placeholder: 'Select Collection'});
initCustomSelect('type-select-wrapper', 'type-select-trigger', 'type-dropdown', 'type-selected-text', {placeholder: 'Select Type'});
const consoleSelectWrapper = document.getElementById('console-select-wrapper');
if (consoleSelectWrapper) {
    initCustomSelect('console-select-wrapper', 'console-select-trigger', 'console-dropdown', 'console-selected-text', {placeholder: 'Select Console'});
}
initCustomSelect('sort-select-wrapper', 'sort-select-trigger', 'sort-dropdown', 'sort-selected-text', {placeholder: 'Select Sort'});

// Close dropdowns when clicking outside
document.addEventListener('click', (e) => {
    if (!e.target.closest('.custom-select-wrapper')) {
        document.querySelectorAll('.custom-select-dropdown.active').forEach(dropdown => {
            dropdown.classList.remove('active');
            dropdown.parentElement.querySelector('.custom-select-trigger').classList.remove('active');
        });
    }
});

// Selected Filters Badge Management
const selectedFiltersContainer = document.getElementById('selected-filters-container');
const selectedFiltersDiv = document.getElementById('selected-filters');

// SVG Icons - converted from PHP svg_icons() function
const svgIcons = {
    '': '',
    'sort': '<svg class="inline" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="20px" height="20px" fill="#000000"><path d="M144-264v-72h240v72H144Zm0-180v-72h432v72H144Zm0-180v-72h672v72H144Z"/></svg>',
    'collection': '<svg class="inline" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="18px" height="18px" fill="#000000"><path d="m276-528 204-336 204 336H276ZM696-96q-70 0-119-49t-49-119q0-70 49-119t119-49q70 0 119 49t49 119q0 70-49 119T696-96Zm-552-24v-288h288v288H144Zm551.77-48Q736-168 764-195.77q28-27.78 28-68Q792-304 764.23-332q-27.78-28-68-28Q656-360 628-332.23q-28 27.78-28 68Q600-224 627.77-196q27.78 28 68 28ZM216-192h144v-144H216v144Zm188-408h152l-76-125-76 125Zm76 0ZM360-336Zm331 67Z"/></svg>',
    'type': '<svg class="inline" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="20px" height="20px" fill="#000000"><path d="M425-144v-241L217-265l-55-95 208-120-208-120 55-95 208 120v-241h110v241l208-120 55 95-208 120 208 120-55 95-208-120v241H425Z"/></svg>',
    'region': '<svg class="inline" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="20px" height="20px" fill="#000000"><path d="M480.21-480Q510-480 531-501.21t21-51Q552-582 530.79-603t-51-21Q450-624 429-602.79t-21 51Q408-522 429.21-501t51 21ZM480-96Q323.03-227.11 245.51-339.55 168-452 168-549q0-134 89-224.5T479.5-864q133.5 0 223 90.5T792-549q0 97-77 209T480-96Z"/></svg>',
    'console': '<svg class="inline" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="20px" height="20px" fill="#000000"><path d="m251-444 229 132 230-132-194-111v147h-72v-147L251-444Zm193-195v-14q-42.24-12.32-69.12-46.92-26.88-34.6-26.88-80.1 0-54.98 38.72-93.48 38.72-38.5 93.5-38.5t93.28 38.6q38.5 38.6 38.5 93.21 0 45.19-26.88 79.99T516-653v14l264 153q17.1 9.62 26.55 25.81Q816-444 816-424v104q0 20-9.45 36.19Q797.1-267.63 780-258L516-105q-17.13 10-36.07 10Q461-95 444-105L180-258q-17.1-9.63-26.55-25.81Q144-300 144-320v-104q0-20 9.45-36.19Q162.9-476.38 180-486l264-153Zm0 389L216-382v62l264 152 264-152v-62L516-250q-17.13 10-36.07 10Q461-240 444-250Zm36-470q25 0 42.5-17.5T540-780q0-25-17.5-42.5T480-840q-25 0-42.5 17.5T420-780q0 25 17.5 42.5T480-720Zm0 552Z"/></svg>',
    'genre': '<svg class="inline" width="22px" height="22px" fill="#000000" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M16.25 11.25c.364 0 .704.145.984.391.549.332.766-.034.766-.391v-1.75c0-.825-.675-1.5-1.5-1.5h-2.75c-.356 0-.724-.216-.391-.766.246-.28.391-.619.391-.984 0-.967-1.007-1.75-2.25-1.75s-2.25.783-2.25 1.75c0 .3.095.576.255.823.507.673.136.927-.255.927h-2.75c-.825 0-1.5.675-1.5 1.5v1.75c0 .391.254.762.928.244.246-.149.522-.244.822-.244.966 0 1.75 1.008 1.75 2.25s-.784 2.25-1.75 2.25c-.364 0-.704-.145-.984-.391-.549-.332-.766.034-.766.391v2.75c0 .825.675 1.5 1.5 1.5h2.75c.391 0 .762-.254.243-.927-.148-.247-.243-.523-.243-.823 0-.967 1.007-1.75 2.25-1.75s2.25.783 2.25 1.75c0 .365-.145.704-.391.984-.333.55.035.766.391.766h2.75c.825 0 1.5-.675 1.5-1.5v-2.75c0-.391-.254-.762-.928-.244-.246.149-.522.244-.822.244-.966 0-1.75-1.008-1.75-2.25s.784-2.25 1.75-2.25z"/></svg>',
    'home': '<svg class="inline" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="20px" height="20px" fill="#000000"><path d="M192-144v-337l-64 49-44-58 396-302 396 302-44 58-64-49v337H192Zm144-216q-15 0-25.5-10.5T300-396q0-14 10.5-25t25.5-11q14 0 25 11t11 25q0 15-11 25.5T336-360Zm144 0q-15 0-25.5-10.5T444-396q0-14 10.5-25t25.5-11q14 0 25 11t11 25q0 15-11 25.5T480-360Zm144 0q-15 0-25.5-10.5T588-396q0-14 10.5-25t25.5-11q14 0 25 11t11 25q0 15-11 25.5T624-360Z"/></svg>',
    'size': '<svg class="inline" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="20px" height="20px" fill="#000000"><path d="m243-240-51-51 405-405H240v-72h480v480h-72v-357L243-240Z"/></svg>',
    'send': '<svg class="inline mr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="20px" height="20px" fill="currentColor"><path d="M144-192v-576l720 288-720 288Zm72-107 454-181-454-181v109l216 72-216 72v109Zm0 0v-362 362Z"/></svg>',
    'reload': '<svg class="inline" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="20px" height="20px" fill="#000000"><path d="M480-192q-120 0-204-84t-84-204q0-120 84-204t204-84q65 0 120.5 27t95.5 72v-99h72v240H528v-72h131q-29-44-76-70t-103-26q-90 0-153 63t-63 153q0 90 63 153t153 63q84 0 144-55.5T693-456h74q-9 112-91 188t-196 76Z"/></svg>'
};

function getSvgIcon(key = '') {
    if (key && svgIcons.hasOwnProperty(key)) {
        return svgIcons[key];
    }
    return '';
}

// Filter configuration mapping
const filterConfig = {
    'genre': {label: 'Genre', name: 'genres[]', iconKey: 'genre'},
    'collection': {label: 'Collection', name: 'collections[]', iconKey: 'collection'},
    'type': {label: 'Type', name: 'types[]', iconKey: 'type'},
    'console': {label: 'Console', name: 'consoles[]', iconKey: 'console'},
    'sort': {label: 'Sort', name: 'sort', iconKey: 'sort'}
};

// Function to update selected filters badges
function updateSelectedFilters() {
    if (!selectedFiltersDiv) return;

    // Clear existing badges
    selectedFiltersDiv.innerHTML = '';

    // Collect all selected filters
    const selectedFilters = [];

    // Get all checked checkboxes within filter dropdowns only (genre, collection, type, console)
    document.querySelectorAll('.custom-select-dropdown input[type="checkbox"]:checked').forEach(checkbox => {
        const name = checkbox.name;
        const value = checkbox.value;
        const optionElement = checkbox.closest('.custom-select-option');
        
        // Skip if not in a valid option element
        if (!optionElement) return;
        
        const labelElement = optionElement.querySelector('label');
        if (!labelElement) return;
        
        const label = labelElement.textContent.trim();
        
        // Match by checking if the name starts with the filterConfig name (handles array names like 'genres[]')
        const filterType = Object.keys(filterConfig).find(key => {
            const configName = filterConfig[key].name;
            return name === configName || (configName.endsWith('[]') && name.startsWith(configName.replace('[]', '')));
        });

        if (filterType) {
            selectedFilters.push({
                type: filterType,
                filterLabel: filterConfig[filterType].label,
                value: value,
                label: label,
                input: checkbox
            });
        }
    });

    // Get selected radio button (sort) within filter dropdowns only
    const selectedRadio = document.querySelector('.custom-select-dropdown input[type="radio"]:checked');
    if (selectedRadio) {
        const name = selectedRadio.name;
        const value = selectedRadio.value;
        const optionElement = selectedRadio.closest('.custom-select-option');
        
        if (optionElement) {
            const labelElement = optionElement.querySelector('label');
            if (labelElement) {
                const label = labelElement.textContent.trim();
                const filterType = Object.keys(filterConfig).find(key => filterConfig[key].name === name);

                if (filterType) {
                    selectedFilters.push({
                        type: filterType,
                        filterLabel: filterConfig[filterType].label,
                        value: value,
                        label: label,
                        input: selectedRadio
                    });
                }
            }
        }
    }

    // Create badges for each selected filter
    selectedFilters.forEach(filter => {
        const badge = document.createElement('div');
        badge.className = 'inline-flex items-center gap-2 px-3 py-1 bg-romfun-pink text-white rounded-full text-sm font-medium';
        badge.setAttribute('data-filter-type', filter.type);
        badge.setAttribute('data-filter-value', filter.value);

        // Get icon for this filter type
        const iconKey = filterConfig[filter.type]?.iconKey || '';
        const iconHtml = getSvgIcon(iconKey);
        // Replace fill color to match badge text (white)
        const iconHtmlWhite = iconHtml.replace('fill="#000000"', 'fill="currentColor"');

        badge.innerHTML = `
                <div class="inline-flex items-center">${iconHtmlWhite ? `<span class="mr-1">${iconHtmlWhite}</span>` : ''} <span>${filter.label}</span></div>
                <button type="button" class="remove-filter-btn ml-1 hover:bg-romfun-pink-dark rounded-full p-0.5 transition-colors" aria-label="Remove filter">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            `;

        // Add remove functionality
        const removeBtn = badge.querySelector('.remove-filter-btn');
        removeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            // Uncheck the corresponding input
            filter.input.checked = false;
            // Trigger change event to update dropdown text
            filter.input.dispatchEvent(new Event('change', {bubbles: true}));
            // Update badges
            updateSelectedFilters();
        });

        selectedFiltersDiv.appendChild(badge);
    });

    // Show/hide container based on whether there are any filters
    if (selectedFiltersContainer) {
        if (selectedFilters.length > 0) {
            selectedFiltersContainer.classList.remove('hidden');
        } else {
            selectedFiltersContainer.classList.add('hidden');
        }
    }
}

// Suggested Filter Badge Click Handler
document.querySelectorAll('.suggested-filter-badge').forEach(badge => {
    badge.addEventListener('click', function () {
        const termId = this.getAttribute('data-term-id');
        const taxonomy = this.getAttribute('data-taxonomy');
        const isSelected = this.getAttribute('data-selected') === 'true';

        // Find the corresponding checkbox in the dropdown
        const checkbox = document.querySelector(`input[type="checkbox"][name="${taxonomy}s[]"][value="${termId}"]`);

        if (checkbox) {
            // Toggle checkbox
            checkbox.checked = !isSelected;

            // Trigger change event to update dropdown text
            checkbox.dispatchEvent(new Event('change', {bubbles: true}));

            // Update badge style
            if (!isSelected) {
                this.classList.remove('bg-gray-100', 'text-gray-800', 'hover:bg-gray-200');
                this.classList.add('bg-romfun-pink', 'text-white');
                this.setAttribute('data-selected', 'true');
            } else {
                this.classList.remove('bg-romfun-pink', 'text-white');
                this.classList.add('bg-gray-100', 'text-gray-800', 'hover:bg-gray-200');
                this.setAttribute('data-selected', 'false');
            }

            // Update selected filters badges
            setTimeout(() => {
                updateSelectedFilters();
            }, 10);
        }
    });
});

// Function to update suggested filter badge states when filters change
function updateSuggestedFilterBadges() {
    document.querySelectorAll('.suggested-filter-badge').forEach(badge => {
        const termId = badge.getAttribute('data-term-id');
        const taxonomy = badge.getAttribute('data-taxonomy');
        const checkbox = document.querySelector(`input[type="checkbox"][name="${taxonomy}s[]"][value="${termId}"]`);

        if (checkbox) {
            const isSelected = checkbox.checked;
            badge.setAttribute('data-selected', isSelected ? 'true' : 'false');

            if (isSelected) {
                badge.classList.remove('bg-gray-100', 'text-gray-800', 'hover:bg-gray-200');
                badge.classList.add('bg-romfun-pink', 'text-white');
            } else {
                badge.classList.remove('bg-romfun-pink', 'text-white');
                badge.classList.add('bg-gray-100', 'text-gray-800', 'hover:bg-gray-200');
            }
        }
    });
}

// Hook into all checkbox and radio changes to update badges using event delegation
// This ensures we catch all changes even if elements are added dynamically
document.addEventListener('change', (e) => {
    // Check if the changed element is a checkbox or radio within a filter dropdown
    if ((e.target.type === 'checkbox' || e.target.type === 'radio') && 
        e.target.closest('.custom-select-dropdown')) {
        // Small delay to let existing handlers update the dropdown text first
        setTimeout(() => {
            updateSelectedFilters();
            updateSuggestedFilterBadges();
        }, 10);
    }
});

// Initial update of selected filters (run after DOM is ready)
function initSelectedFilters() {
    updateSelectedFilters();
    updateSuggestedFilterBadges();

    // Update selected text for all dropdowns based on checked inputs
    document.querySelectorAll('.custom-select-wrapper').forEach(wrapper => {
        const selectedText = wrapper.querySelector('.selected-text');
        const dropdown = wrapper.querySelector('.custom-select-dropdown');
        if (!selectedText || !dropdown) return;
        
        const checkedInputs = dropdown.querySelectorAll('input:checked');

        if (checkedInputs.length === 0) {
            const placeholder = selectedText.id.replace('-selected-text', '');
            let placeholderText = 'Select ';
            if (placeholder.includes('genre')) placeholderText = 'Select Genre';
            else if (placeholder.includes('collection')) placeholderText = 'Select Collection';
            else if (placeholder.includes('type')) placeholderText = 'Select Type';
            else if (placeholder.includes('console')) placeholderText = 'Select Console';
            else if (placeholder.includes('sort')) placeholderText = 'Select Sort';
            selectedText.textContent = placeholderText;
            selectedText.classList.remove('has-selection');
        } else if (checkedInputs.length === 1) {
            const optionElement = checkedInputs[0].closest('.custom-select-option');
            if (optionElement) {
                const labelElement = optionElement.querySelector('label');
                if (labelElement) {
                    selectedText.textContent = labelElement.textContent.trim();
                    selectedText.classList.add('has-selection');
                }
            }
        } else {
            selectedText.textContent = `${checkedInputs.length} selected`;
            selectedText.classList.add('has-selection');
        }
    });
}

// Run initialization when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(initSelectedFilters, 100);
    });
} else {
    // DOM is already ready
    setTimeout(initSelectedFilters, 100);
}

const searchInput = document.getElementById('searchInput');
const clearFiltersBtn = document.getElementById('clearFilters');

if (clearFiltersBtn) {
    clearFiltersBtn.addEventListener('click', (e) => {
        e.preventDefault();

        // Clear search input
        if (searchInput) {
            searchInput.value = '';
        }

        // Uncheck all checkboxes (genres, collections, types, consoles)
        document.querySelectorAll('input[type="checkbox"]:checked').forEach(checkbox => {
            checkbox.checked = false;
            // Trigger change event to update dropdown text
            checkbox.dispatchEvent(new Event('change', {bubbles: true}));
        });

        // Uncheck selected radio button (sort)
        const selectedRadio = document.querySelector('input[type="radio"]:checked');
        if (selectedRadio) {
            selectedRadio.checked = false;
            // Trigger change event to update dropdown text
            selectedRadio.dispatchEvent(new Event('change', {bubbles: true}));
        }

        updateSelectedFilters();
        updateSuggestedFilterBadges();
    });
}

/** Autocomplete Search **/

function createSearchBox({input, resultsContainer, filterTitle = null}) {

    function normalize(str) {
        return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
    }

    function highlightText(title, query) {
        
        const normTitle = normalize(title);
        const normQuery = normalize(query);
        
        const index = normTitle.indexOf(normQuery);
        
        if (index === -1) return title;

        const realStart = index;
        const realEnd = index + query.length; 

        return (title.slice(0, realStart) + "<strong>" + title.slice(realStart, realEnd) + "</strong>" + title.slice(realEnd));
        
    }

    input.addEventListener('input', async function () {
        
        const query = this.value.trim().toLowerCase();

        if (!query) {
            
            resultsContainer.innerHTML = '';
            
            resultsContainer.hidden = true;
            
            return;
        }
        else {
            resultsContainer.hidden = false;
        }

        let api_url = 'https://romsfun.com/api/search?s=' + encodeURIComponent(query);

        if (filterTitle && filterTitle.textContent != 'Browse all ROMS') {
            api_url += '&c=' + encodeURIComponent(filterTitle.textContent);
        }

        try {
            
            const response = await fetch(api_url);
            const data = await response.json();

            resultsContainer.innerHTML = '';
            
            const fragment = document.createDocumentFragment();

            data.forEach(item => {
                
                const div = document.createElement('div');
                div.className = 'magic-search-item truncate';

                const a = document.createElement('a');
                a.href = item.url;

                const img = document.createElement('img');
                img.src = item.icon;
                img.alt = item.title;
                img.height = 45;
                img.width = 45;
                img.loading = 'lazy';
                
                const imgContainer = document.createElement('div');
                imgContainer.appendChild(img);

                let titleHighlighted = highlightText(item.title, query);
                let displayTitle = titleHighlighted;

                const consoleTitle = `<span class="inline-block bg-white text-romfun-pink text-xs border border-romfun-pink px-3 py-1 mr-1 mb-1 rounded-full">${item.console}</span> `;

                const titleSpan = document.createElement('span');
                titleSpan.innerHTML = consoleTitle + displayTitle;

                const sizeSpan = document.createElement('span');
                sizeSpan.innerHTML = item.size;

                a.appendChild(imgContainer);
                a.appendChild(titleSpan);
                a.appendChild(sizeSpan);

                div.appendChild(a);
                
                fragment.appendChild(div);
                
            });

            resultsContainer.appendChild(fragment);

        } catch (err) {
            console.error(err);
        }
        
    });
}

const filterTitle = document.getElementById('filter-title');

const search_input_1 = document.getElementById('searchInput');
const search_result_1 = document.getElementById('magic-search-results');

if (search_input_1) {
    
    createSearchBox({
        input: search_input_1,
        resultsContainer: search_result_1,
        filterTitle: filterTitle
    });

}


const search_input_2 = document.getElementById('float-search-input');
const search_result_2 = document.querySelector('.float-search-result');

if (search_input_2) {
    
    createSearchBox({
        input: search_input_2,
        resultsContainer: search_result_2,
        filterTitle: null
    });

}

const search_button_2 = document.getElementById('float-search-button');
const search_container_2 = document.querySelector('.float-search');
const search_bg = document.querySelector('.float-search-bg');

if (search_button_2 && search_container_2) {
    
    const iconSearch = `
    <svg height="24px" viewBox="0 -960 960 960" width="24px" fill="#fff">
        <path d="M784-120 532-372q-30 24-69 38t-83 14q-109 0-184.5-75.5T120-580q0-109 75.5-184.5T380-840q109 0 184.5 75.5T640-580q0 44-14 83t-38 69l252 252-56 56ZM380-400q75 0 127.5-52.5T560-580q0-75-52.5-127.5T380-760q-75 0-127.5 52.5T200-580q0 75 52.5 127.5T380-400Z" />
    </svg>
    `;
    
    const iconClose = `
    <svg height="24px" viewBox="0 -960 960 960" width="24px" fill="#fff">
        <path d="M480-424 284-228q-11 11-28 11t-28-11q-11-11-11-28t11-28l196-196-196-196q-11-11-11-28t11-28q11-11 28-11t28 11l196 196 196-196q11-11 28-11t28 11q11 11 11 28t-11 28L536-480l196 196q11 11 11 28t-11 28q-11 11-28 11t-28-11L480-424Z"/>
    </svg>
    `;

    
    search_button_2.addEventListener('click', () => {
        
        search_container_2.classList.toggle('float-search__hide');
        search_bg.classList.toggle('float-search-bg__hide');
        
        if (search_container_2.classList.contains('float-search__hide')) {
            
            search_button_2.innerHTML = iconSearch;
            
            search_result_2.innerHTML = '';
            
            search_input_2.value = '';
            
        } else {
            
            search_button_2.innerHTML = iconClose;
            
            search_input_2.focus();
            
        }

    });

}

// Report Form Modal Handler
(function() {
    'use strict';
    
    // Wait for DOM to be ready
    function initReportModal() {
        const modal = document.getElementById('help-modal');
        const openButton = document.getElementById('open-help-modal');
        const closeButton = document.getElementById('close-help-modal');
        const overlay = document.getElementById('modal-overlay');
        const modalContent = modal ? modal.querySelector('.relative') : null;
        
        if (!modal || !openButton || !closeButton || !modalContent) {
            return;
        }
        
        function openModal() {
            // Ensure flex class is present for centering
            if (modal.classList.contains('hidden')) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.style.overflow = 'hidden';
                // Reset modal content to initial animation state
                modalContent.classList.remove('scale-100', 'opacity-100');
                modalContent.classList.add('scale-95', 'opacity-0');
                // Trigger animation after a brief delay to ensure flex is applied
                setTimeout(function() {
                    modalContent.classList.remove('scale-95', 'opacity-0');
                    modalContent.classList.add('scale-100', 'opacity-100');
                }, 10);
            }
        }
        
        function closeModal() {
            // Animate out
            modalContent.classList.remove('scale-100', 'opacity-100');
            modalContent.classList.add('scale-95', 'opacity-0');
            setTimeout(function() {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.style.overflow = '';
            }, 150);
        }
        
        openButton.addEventListener('click', openModal);
        closeButton.addEventListener('click', closeModal);
        overlay.addEventListener('click', closeModal);
        
        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                closeModal();
            }
        });
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initReportModal);
    } else {
        initReportModal();
    }
})();

// Copy to Clipboard Function
function copyToClipboard(text, buttonElement) {
    if (!navigator.clipboard || !navigator.clipboard.writeText) {
        console.error('Clipboard API is not supported in this browser');
        return;
    }

    navigator.clipboard.writeText(text).then(function() {
        showCopyMessage(buttonElement);
    }).catch(function(err) {
        console.error('Failed to copy to clipboard:', err);
    });
}

function showCopyMessage(buttonElement) {
    const container = buttonElement.parentElement;
    
        // Find flash message element
    let messageEl = container.querySelector('.copy-message');
    
    if (!messageEl) {
        messageEl = document.createElement('div');
        messageEl.className = 'copy-message absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 text-gray-500 text-xs px-4 py-2 rounded whitespace-nowrap opacity-0 transition-opacity duration-300 pointer-events-none z-10 inline-block';
        messageEl.style.display = 'inline-block';
        messageEl.textContent = 'Copied to clipboard!';
        container.appendChild(messageEl);
    }
    
    messageEl.classList.remove('opacity-0');
    messageEl.classList.add('opacity-100');
    
    setTimeout(function() {
        messageEl.classList.remove('opacity-100');
        messageEl.classList.add('opacity-0');
    }, 2000);
}

// Download Page Countdown and AJAX Handler
(function() {
    'use strict';
    
    function initDownloadCountdown() {
        const countdownElement = document.getElementById('countdown');
        const loadingElement = document.getElementById('download-loading');
        const downloadContainer = document.getElementById('download-container');
        
        if (!countdownElement || !loadingElement || !downloadContainer) {
            return;
        }
        
        let countdown = 7;
        let storedDownloadHTML = null;
        
        // Get AJAX URL from data attribute or use localized ajax_url
        const ajaxUrl = downloadContainer.getAttribute('data-ajax-url') || 
                       (typeof ajax !== 'undefined' && ajax.ajax_url ? ajax.ajax_url : '');
        
        if (!ajaxUrl) {
            console.error('AJAX URL not available');
            return;
        }
        
        // Call AJAX immediately when page loads
        (function() {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success && response.data && response.data.html) {
                            storedDownloadHTML = response.data.html;
                        } else {
                            // Log generic error message (detailed errors only in development via PHP)
                            console.error('Failed to load download link');
                        }
                    } catch (e) {
                        console.error('Failed to load download link');
                    }
                } else {
                    console.error('Failed to load download link');
                }
            };
            
            xhr.onerror = function() {
                console.error('Failed to load download link');
            };
            
            xhr.send('action=k_get_download');
        })();
        
        // Update countdown every second
        const interval = setInterval(function () {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(interval);
                
                // Hide loading
                loadingElement.classList.add('hidden');
                
                // Insert stored HTML or fallback to existing button
                if (storedDownloadHTML) {
                    downloadContainer.innerHTML = storedDownloadHTML;
                } else {
                    // Fallback: show existing button if AJAX failed
                    const buttonElement = document.getElementById('download-button');
                    if (buttonElement) {
                        buttonElement.classList.remove('hidden');
                    }
                }
            }
        }, 1000);
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDownloadCountdown);
    } else {
        initDownloadCountdown();
    }
})();
// ---------------------------------------------------------------------------
// Rating stars — display only (custom, no library; replaces RateYo). Fill each
// `.rating` element from its data-rateyo-rating. Ratings are never editable here;
// submitting happens in the member review section via rf/v1/rating.
// ---------------------------------------------------------------------------
(function () {
  function renderStars(el) {
    var v = parseFloat(el.getAttribute('data-rateyo-rating')) || 0;
    if (v < 0) v = 0;
    if (v > 5) v = 5;
    el.innerHTML = '<span class="rf-stars" role="img" aria-label="' + v.toFixed(1) + ' / 5">'
      + '<span class="rf-stars-fill" style="width:' + ((v / 5) * 100) + '%"></span></span>';
  }
  function init() {
    var els = document.querySelectorAll('.rating');
    for (var i = 0; i < els.length; i++) renderStars(els[i]);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
