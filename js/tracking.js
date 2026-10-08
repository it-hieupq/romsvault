/**
 * Romsvault sendBeacon Tracking
 * 
 * Tracks views and downloads via sendBeacon to bypass Cloudflare caching.
 * Uses sessionStorage to prevent duplicate tracking.
 * 
 * @package romsvault
 * @since 2.0
 */

(function () {
    'use strict';

    /**
     * Check if already tracked in this session
     */
    function isAlreadyTracked(postId, trackType) {
        try {
            var key = 'rf_tracked_' + trackType;
            var tracked = sessionStorage.getItem(key);
            if (tracked) {
                var trackedIds = JSON.parse(tracked);
                return Array.isArray(trackedIds) && trackedIds.includes(postId);
            }
        } catch (e) {
            sessionStorage.removeItem('rf_tracked_' + trackType);
        }
        return false;
    }

    /**
     * Mark as tracked in sessionStorage
     */
    function markAsTracked(postId, trackType) {
        try {
            var key = 'rf_tracked_' + trackType;
            var tracked = sessionStorage.getItem(key);
            tracked = tracked ? JSON.parse(tracked) : [];

            if (!Array.isArray(tracked)) {
                tracked = [];
            }

            if (!tracked.includes(postId)) {
                tracked.push(postId);
                sessionStorage.setItem(key, JSON.stringify(tracked));
            }
        } catch (e) {
            // Ignore silently
        }
    }

    /**
     * Send tracking request to server
     */
    function sendTrackRequest(postId, trackType) {
        if (isAlreadyTracked(postId, trackType)) {
            return;
        }

        var payload = new URLSearchParams({
            id: postId,
            a: trackType === 'download' ? 'd' : 'v'
        });

        // sendBeacon never blocks unload and survives the page being closed.
        // It reports queueing rather than delivery, so mark optimistically —
        // the endpoint answers 204 with no body for us to inspect anyway.
        if (navigator.sendBeacon(rfTracking.endpoint, payload)) {
            markAsTracked(postId, trackType);
        }
    }

    /**
     * Initialize tracking on page load
     */
    function init() {
        if (typeof rfTracking === 'undefined') {
            return;
        }

        var postId = parseInt(rfTracking.postId, 10);
        var trackType = rfTracking.trackType || 'view';

        setTimeout(function () {
            sendTrackRequest(postId, trackType);
        }, 1000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
