jQuery(function ($) {
    'use strict';

    function initRomGalleryOnce() {
        const gallery = document.getElementById('romGallery');
        if (!gallery) return;

        // Nếu đã init trước đó => skip (avoid duplicate)
        if (gallery.__lgInitialized) {
            // debug: console.warn('Gallery already initialized');
            return;
        }

        // Wait until lightGallery is available
        if (typeof lightGallery === 'undefined') {
            // try again one time after short delay, but do NOT schedule multiple retries
            setTimeout(function () {
                if (typeof lightGallery !== 'undefined') {
                    initRomGalleryOnce();
                } else {
                    console.error('lightGallery not found after retry.');
                }
            }, 200);
            return;
        }

        // Ensure plugin lgThumbnail exists (optional)
        const plugins = [];
        if (typeof lgThumbnail !== 'undefined') {
            plugins.push(lgThumbnail);
        } else {
            // it's ok if thumbnail plugin missing; we'll still init without it
            // console.warn('lgThumbnail not found — thumbnails disabled.');
        }

        // If there is an older instance attached, destroy it first (safety)
        if (gallery.__lgInstance && typeof gallery.__lgInstance.destroy === 'function') {
            try {
                gallery.__lgInstance.destroy();
            } catch (e) {
                // ignore
                console.warn('Failed to destroy previous lg instance', e);
            }
            gallery.__lgInstance = null;
        }

        // lazy attribute for <img> to improve performance
        gallery.querySelectorAll('img').forEach(img => {
            if (!img.hasAttribute('loading')) img.setAttribute('loading', 'lazy');
        });

        // Initialize once
        try {
            const instance = lightGallery(gallery, {
                selector: 'a',
                plugins: plugins,
                thumbnail: !!plugins.length, // enable thumbnail only if plugin present
                animateThumb: true,
                showThumbByDefault: !!plugins.length,
                preload: 1,
                speed: 450,
                loop: true,
                download: false,
                actualSize: false,
                thumbWidth: 80,
                thumbHeight: 80,
                thumbMargin: 10,
                mobileSettings: {
                    controls: true,
                    showCloseIcon: true,
                    download: false
                }
            });

            // store instance and flag on DOM element to prevent double init
            gallery.__lgInstance = instance;
            gallery.__lgInitialized = true;

            // OPTIONAL: expose for debug
            window._romGallery = instance;

        } catch (err) {
            console.error('Error initializing lightGallery (final):', err);
            return;
        }
    }

    // Run once on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initRomGalleryOnce);
    } else {
        initRomGalleryOnce();
    }

    // Also try a single attempt after window load (in case scripts are loaded late)
    window.addEventListener('load', function () {
        setTimeout(initRomGalleryOnce, 150);
    });

});
