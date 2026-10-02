/**
 * Toast notifications — standalone, zero dependencies (no jQuery).
 *
 * Loaded before auth.js and main.js so every script can call it:
 *   window.showToast(message, type)   // type: 'success' (default) | 'error'
 *   window.removeToast(toastElement)
 *
 * Behavior mirrors the previous jQuery implementation: container is created on
 * first use, toasts auto-dismiss after 3s, hover pauses the timer, and closing
 * animates via the `hiding` class before removal. `message` is injected as HTML
 * (same as before) — callers pass trusted server messages or literal strings.
 */
(function () {
	'use strict';

	var AUTO_DISMISS = 3000;
	var HIDE_ANIM = 300;

	var ICONS = {
		success: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
		error: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
	};

	var CLOSE_ICON = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';

	function getContainer() {
		var container = document.getElementById('toastContainer');
		if (!container) {
			container = document.createElement('div');
			container.className = 'toast-container';
			container.id = 'toastContainer';
			document.body.appendChild(container);
		}
		return container;
	}

	function scheduleDismiss(toast) {
		clearTimeout(toast._dismissTimer);
		toast._dismissTimer = setTimeout(function () {
			removeToast(toast);
		}, AUTO_DISMISS);
	}

	function removeToast(toast) {
		if (!toast) return;
		clearTimeout(toast._dismissTimer);
		toast.classList.add('hiding');
		setTimeout(function () {
			if (toast.parentNode) {
				toast.parentNode.removeChild(toast);
			}
		}, HIDE_ANIM);
	}

	function showToast(message, type) {
		type = type || 'success';

		var toast = document.createElement('div');
		toast.className = 'toast ' + type;
		toast.innerHTML =
			'<div class="toast-icon">' + (ICONS[type] || ICONS.success) + '</div>' +
			'<div class="toast-content"></div>' +
			'<button class="toast-close" type="button" aria-label="Close">' + CLOSE_ICON + '</button>';

		toast.querySelector('.toast-content').innerHTML = message;

		toast.querySelector('.toast-close').addEventListener('click', function () {
			removeToast(toast);
		});
		toast.addEventListener('mouseenter', function () {
			clearTimeout(toast._dismissTimer);
		});
		toast.addEventListener('mouseleave', function () {
			scheduleDismiss(toast);
		});

		getContainer().appendChild(toast);
		scheduleDismiss(toast);
		return toast;
	}

	window.showToast = showToast;
	window.removeToast = removeToast;
})();
