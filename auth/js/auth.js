/**
 * Member auth UI — P2.0 scaffold. NO backend yet.
 *
 * State lives in localStorage.rf_display = { name, email, avatar, exp } and is
 * COSMETIC only (never a credential). Real enforcement happens server-side at
 * the /wp-json/rf/v1/* endpoints (wired in later phases).
 *
 * Templates always render the guest state; this file swaps to the logged-in
 * state client-side, so the cached HTML stays identical for everyone.
 */
(function () {
  'use strict';

  var LS_KEY = 'rf_display';

  function readDisplay() {
    try {
      var raw = localStorage.getItem(LS_KEY);
      if (!raw) return null;
      var d = JSON.parse(raw);
      if (d && d.exp && d.exp * 1000 < Date.now()) {
        localStorage.removeItem(LS_KEY);
        return null;
      }
      return d;
    } catch (e) {
      return null;
    }
  }

  // Route all notifications through the theme's shared toast system (site.js).
  function toast(msg, type) {
    if (window.showToast) window.showToast(msg, type || 'error');
  }

  function fill(sel, value, isImg) {
    var els = document.querySelectorAll(sel);
    for (var i = 0; i < els.length; i++) {
      if (isImg) { if (value) els[i].src = value; }
      else { els[i].textContent = value || ''; }
    }
  }

  // "Member since" comes straight from the cached display object (created_at is
  // stored at login), so the profile page never needs a server round-trip for it.
  function fmtMonthYear(s) {
    if (!s) return '';
    var d = new Date(String(s).replace(' ', 'T') + 'Z'); // created_at is GMT
    if (isNaN(d.getTime())) return '';
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'long' });
  }

  // Reflect the member's blocked state in the profile status badge (Active/Blocked).
  function setStatusBadge(blocked) {
    var a = document.querySelector('[data-rf-status="active"]');
    var b = document.querySelector('[data-rf-status="blocked"]');
    if (!a || !b) return;
    a.classList.toggle('hidden', blocked);
    b.classList.toggle('hidden', !blocked);
  }

  function applyState(d) {
    var blocked = !!(d && d.blocked === 1);
    document.documentElement.classList.toggle('rf-authed', !!d);
    document.documentElement.classList.toggle('rf-blocked', blocked);
    if (d) {
      fill('[data-rf-name]', d.name);
      fill('[data-rf-firstname]', (d.name || '').trim().split(/\s+/)[0] || d.name); // desktop: first name only
      fill('[data-rf-email]', d.email);
      fill('[data-rf-avatar]', d.avatar, true);
      if (d.created_at) fill('[data-rf-member-since]', fmtMonthYear(d.created_at));
      setStatusBadge(blocked);
      var notice = document.querySelector('[data-rf-blocked-notice]');
      if (notice) notice.classList.toggle('hidden', !blocked);
    }
  }

  // Persist the server's blocked verdict into the cosmetic display state and
  // re-render. Called when any endpoint replies 403 'blocked' (mark blocked) and
  // when my-stats reports the flag (also clears it on unblock). This is NOT a
  // logout: a blocked member keeps their session and can still read the site and
  // download non-restricted posts — only member actions (comment/rating/wishlist/
  // restricted download) are refused, which the server enforces. Messaging stays
  // the caller's job (the server 'message'), so no toast is raised here.
  function setBlocked(blocked) {
    var d = readDisplay();
    if (!d) return;
    var b = blocked ? 1 : 0;
    if ((d.blocked ? 1 : 0) !== b) {
      d.blocked = b;
      try { localStorage.setItem(LS_KEY, JSON.stringify(d)); } catch (e) {} // syncs other tabs
    }
    applyState(d);
  }

  // Session gone (a dynamic action returned 401) -> drop cosmetic state, revert to
  // guest. Only 401 calls this; 403 is handled by callers (see rfFetch). Revert
  // state ONLY — messaging is the caller's job (a toast for wishlist/download, an
  // inline form message for comments) so we never stack two.
  function handleAuthLost(blocked) {
    localStorage.removeItem(LS_KEY); // also fires `storage` in other tabs
    applyState(null);
    if (blocked) { try { sessionStorage.setItem('rf_blocked', '1'); } catch (e) {} }
  }

  // Central wrapper for authenticated rf/v1 action calls (download-url, comment,
  // report... in P3+). Adds credentials + CSRF header and centralises 401/403.
  // `path` is relative to RF_AUTH.rest (pretty URL, so the cookie path matches).
  function rfFetch(path, options) {
    options = options || {};
    options.credentials = 'include';
    options.headers = Object.assign({ 'X-RF-Auth': '1' }, options.headers || {});
    return fetch(cfg().rest + path, options).then(function (r) {
      // Only 401 (session truly gone) reverts to guest. 403 (blocked, comments
      // closed, bad origin) is NOT a logout — the caller shows a message instead.
      // A 403 'blocked' additionally flips the cached blocked flag so the UI (badge,
      // html.rf-blocked) reflects it at once. Clone the body so the caller can still
      // read its own copy for the toast.
      if (r.status === 401) { handleAuthLost(false); }
      else if (r.status === 403) {
        r.clone().json().then(function (j) { if (j && j.error === 'blocked') setBlocked(1); }).catch(function () {});
      } else if (r.status === 200 && String(options.method || 'GET').toUpperCase() === 'POST') {
        // A write succeeded, and every write 403s while blocked -> the member is not
        // blocked. Clear any stale flag so an admin unblock reflects without a reload.
        var cur = readDisplay();
        if (cur && cur.blocked === 1) setBlocked(0);
      }
      return r;
    });
  }

  // --- config passed from PHP (wp_localize_script; static, cache-safe) ---
  function cfg() { return window.RF_AUTH || {}; }

  // --- Google Identity Services (GSI): lazy-loaded on first modal open ---
  var GSI_SRC = 'https://accounts.google.com/gsi/client';
  var gsiLoading = false, gsiReady = false, gsiNonce = null;

  function gsiNote(msg) {
    var slot = document.getElementById('rf-gsi-slot');
    if (slot) slot.innerHTML = '<span class="rf-gsi-note">' + msg + '</span>';
  }

  function makeNonce() {
    try {
      var a = new Uint8Array(16);
      window.crypto.getRandomValues(a);
      return Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
    } catch (e) {
      return String(Date.now()) + Math.random().toString(16).slice(2);
    }
  }

  function loadGsi(cb) {
    if (gsiReady) { cb(); return; }
    if (gsiLoading) { document.addEventListener('rf-gsi-ready', cb, { once: true }); return; }
    gsiLoading = true;
    var s = document.createElement('script');
    s.src = GSI_SRC; s.async = true; s.defer = true;
    s.onload = function () { gsiReady = true; document.dispatchEvent(new Event('rf-gsi-ready')); cb(); };
    s.onerror = function () { gsiLoading = false; gsiNote('Could not load Google sign-in. Please try again.'); };
    document.head.appendChild(s);
  }

  function renderGsi() {
    var slot = document.getElementById('rf-gsi-slot');
    if (!slot || !window.google || !google.accounts || !google.accounts.id) return;
    if (!cfg().clientId) { gsiNote('Google sign-in is not configured yet.'); return; }
    gsiNonce = makeNonce();
    try { sessionStorage.setItem('rf_nonce', gsiNonce); } catch (e) {}
    google.accounts.id.initialize({
      client_id: cfg().clientId,
      callback: onCredential,
      nonce: gsiNonce
    });
    slot.innerHTML = '';
    google.accounts.id.renderButton(slot, { theme: 'outline', size: 'large', shape: 'pill', text: 'signin_with', logo_alignment: 'left' });
  }

  // Receives the Google ID token, POSTs { credential, nonce } to rf/v1/login.
  // On success: persist display state to localStorage, swap the UI, close modal.
  function onCredential(resp) {
    if (!resp || !resp.credential) return;
    var nonce = null;
    try { nonce = sessionStorage.getItem('rf_nonce'); } catch (e) {}
    gsiNote('Signing in…');
    fetch(cfg().rest + 'login', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'X-RF-Auth': '1' },
      body: JSON.stringify({ credential: resp.credential, nonce: nonce })
    })
      .then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
      .then(function (res) {
        if (res.status === 200 && res.body && res.body.ok && res.body.display) {
          try { localStorage.setItem(LS_KEY, JSON.stringify(res.body.display)); } catch (e) {}
          applyState(res.body.display); // storage event syncs other tabs
          closeModal();
          // Member-shell pages (/profile, /wishlist, /request) run their per-user init on
          // page load only — applyState alone can't populate them (stuck skeleton / unwired
          // form). Reload just these so they render correctly; content pages keep the smooth
          // in-place swap (scroll + context preserved).
          if ( /^\/(profile|wishlist|request)\/?$/i.test( location.pathname ) ) {
            location.reload();
          }
          return;
        }
        gsiNote('');
        toast(res.body && res.body.message ? res.body.message : 'Sign-in failed. Please try again.');
      })
      .catch(function () { gsiNote(''); toast('Network error. Please try again.'); });
  }

  // --- modal ---
  function openModal() {
    var m = document.getElementById('rf-modal');
    if (m) { m.classList.add('open'); m.setAttribute('aria-hidden', 'false'); }
    if (document.body.classList.contains('rf-webview')) return; // webview: banner shown via CSS, skip GSI
    loadGsi(renderGsi);
  }
  function closeModal() {
    var m = document.getElementById('rf-modal');
    if (m) { m.classList.remove('open'); m.setAttribute('aria-hidden', 'true'); }
  }

  // --- in-app webview detection (P2.7 refines) ---
  function isInAppWebview() {
    var ua = navigator.userAgent || '';
    return /FBAN|FBAV|FB_IAB|Instagram|Zalo|Line\//i.test(ua);
  }

  function bind() {
    document.querySelectorAll('[data-rf-login]').forEach(function (btn) {
      btn.addEventListener('click', function (e) { e.preventDefault(); openModal(); });
    });
    document.querySelectorAll('[data-rf-close]').forEach(function (el) {
      el.addEventListener('click', function () { closeModal(); });
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });

    document.querySelectorAll('[data-rf-menu-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var menu = btn.closest('.rf-menu');
        if (menu) menu.classList.toggle('open');
      });
    });
    document.addEventListener('click', function () {
      var open = document.querySelectorAll('.rf-menu.open');
      for (var i = 0; i < open.length; i++) open[i].classList.remove('open');
    });

    // Mobile member card: toggle the inline submenu (Profile/Wishlist/Logout).
    document.querySelectorAll('[data-rf-mtoggle]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var box = btn.closest('.rf-m-account');
        if (box) {
          var open = box.classList.toggle('open');
          btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
      });
    });

    // Logout: clear local display immediately, then kill the server session.
    document.querySelectorAll('[data-rf-logout]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        localStorage.removeItem(LS_KEY); // fires `storage` in other tabs -> they revert too
        applyState(null);
        rfFetch('logout', { method: 'POST' }).catch(function () {});
      });
    });

    // Download gate (P3) — single-rom "Download ROM": guests get the login modal.
    document.querySelectorAll('[data-rf-gate]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        if (!readDisplay()) { e.preventDefault(); openModal(); }
      });
    });
    // Download page (members-only): fetch the real URL from the verified endpoint.
    document.querySelectorAll('[data-rf-download]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        if (!readDisplay()) { openModal(); return; }
        var post = btn.getAttribute('data-post'), file = btn.getAttribute('data-file');
        rfFetch('download-url?post=' + encodeURIComponent(post) + '&file=' + encodeURIComponent(file))
          .then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
          .then(function (res) {
            if (res.status === 200 && res.body && res.body.url) { window.location.href = res.body.url; return; }
            if (res.status === 401) { openModal(); return; }
            if (res.status === 403) { toast(res.body && res.body.message ? res.body.message : 'Your account is locked.'); }
          })
          .catch(function () {});
      });
    });

    // Wishlist (P7) — heart toggle on game pages.
    var wishBtns = document.querySelectorAll('[data-rf-wish]');
    if (wishBtns.length) {
      wishBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (!readDisplay()) { openModal(); return; }
          // Optimistic: flip the heart now; only SEND the final state after a
          // quiet period (coalesce rapid clicks -> even = no request, odd = one).
          setWishState(btn, !btn.classList.contains('is-active'));
          if (btn._rfTimer) clearTimeout(btn._rfTimer);
          btn._rfTimer = setTimeout(function () { syncWish(btn); }, 1500);
        });
      });
      // Mark initial (committed) state from the member's wishlist (one call).
      if (readDisplay()) {
        rfFetch('wishlist').then(function (r) { return r.ok ? r.json() : null; }).then(function (j) {
          if (!j || !j.items) return;
          var ids = {};
          j.items.forEach(function (it) { ids[it.id] = true; });
          wishBtns.forEach(function (btn) {
            if (ids[parseInt(btn.getAttribute('data-post'), 10)]) setWishState(btn, true, true);
          });
        }).catch(function () {});
      }
    }

    // Wishlist page: render the member's saved games client-side.
    var wishPage = document.querySelector('[data-rf-wishlist-page]');
    if (wishPage && readDisplay()) { renderWishlistPage(wishPage); }

    var ticketPage = document.querySelector('[data-rf-ticket-page]');
    if (ticketPage) {
      if (readDisplay()) {
        initTicketPage(ticketPage);
      } else {
        // Guest: the form stays visible but any submit (Enter, or the swapped button) prompts sign-in.
        var gForm = ticketPage.querySelector('[data-rf-ticket-form]');
        if (gForm) gForm.addEventListener('submit', function (e) { e.preventDefault(); openModal(); });
      }
    }

    // Contact-us page: member sends a message; a guest's submit prompts sign-in.
    var contactForm = document.querySelector('[data-rf-contact-form]');
    if (contactForm) {
      if (readDisplay()) { initContactForm(contactForm); }
      else { contactForm.addEventListener('submit', function (e) { e.preventDefault(); openModal(); }); }
    }

    // Profile dashboard (P7): counts + lazy accordion lists (comments/reports).
    initProfileDashboard();

    // Multi-tab sync: another tab logged in/out -> update this tab's UI.
    window.addEventListener('storage', function (e) {
      if (e.key === LS_KEY) applyState(readDisplay());
    });

    function setWishState(btn, active, commit) {
      btn.classList.toggle('is-active', !!active);
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
      if (commit) btn._rfCommitted = !!active; // server-confirmed baseline
    }
    // Send the button's final desired state to the server (idempotent set). Skips
    // when the displayed state already matches the committed one (net no change).
    function syncWish(btn) {
      if (btn._rfInflight) return;
      var committed = !!btn._rfCommitted;
      var desired = btn.classList.contains('is-active');
      if (desired === committed) return;
      btn._rfInflight = true;
      rfFetch('wishlist', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post: parseInt(btn.getAttribute('data-post'), 10) || 0, on: desired ? 1 : 0 })
      })
        .then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
        .then(function (res) {
          btn._rfInflight = false;
          if (res.status === 200 && res.body && res.body.ok) {
            setWishState(btn, res.body.active, true); // commit server truth
          } else {
            setWishState(btn, !!btn._rfCommitted); // revert display to baseline
            if (res.status === 409) { toast('Your wishlist is full (max ' + ((res.body && res.body.max) || 30) + ').'); }
            else if (res.status === 429) { toast('You are doing that too fast. Please wait a moment.'); }
            else if (res.status === 401) { openModal(); }
            else if (res.status === 403) { toast(res.body && res.body.message ? res.body.message : 'Your account is locked.'); }
          }
          // If the user toggled again while the request was in flight, resync.
          if (btn.classList.contains('is-active') !== !!btn._rfCommitted) syncWish(btn);
        })
        .catch(function () { btn._rfInflight = false; setWishState(btn, !!btn._rfCommitted); });
    }
    function escapeHtml(s) {
      return String(s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
      });
    }

    // ---- /ticket page: create form + own ticket list (status) + close/cancel ----
    // Contact-us page: a member sends a message (POST /contact). One-shot form: toast +
    // reset on success; the member reads any admin reply back on their /profile page.
    function initContactForm(form) {
      var submit = form.querySelector('[data-rf-contact-submit]');
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(form);
        var title = String(fd.get('title') || '').trim();
        var content = String(fd.get('content') || '').trim();
        if (!title) { toast('Please enter a title.'); return; }
        if (!content) { toast('Please enter your message.'); return; }
        if (submit) submit.disabled = true;
        rfFetch('contact', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ title: title, content: content })
        })
          .then(function (r) { return r.json().then(function (b) { return { status: r.status, body: b }; }); })
          .then(function (res) {
            if (submit) submit.disabled = false;
            if (res.status === 200 && res.body && res.body.ok) {
              toast(res.body.message, 'success');
              form.reset();
            } else {
              toast(res.body && res.body.message ? res.body.message : 'Could not send your message.');
              if (res.status === 401) openModal();
            }
          })
          .catch(function () { if (submit) submit.disabled = false; toast('Network error. Please try again.'); });
      });
    }

    function initTicketPage(root) {
      var form   = root.querySelector('[data-rf-ticket-form]');
      var submit = root.querySelector('[data-rf-ticket-submit]');
      var lock   = root.querySelector('[data-rf-ticket-lock]');

      // Current (open) request
      var curLoading = root.querySelector('[data-rf-current-loading]');
      var curCard    = root.querySelector('[data-rf-current-card]');
      var curNone    = root.querySelector('[data-rf-current-none]');

      // History (lazy: fetched only when the member opens the panel)
      var histToggle  = root.querySelector('[data-rf-history-toggle]');
      var histPanel   = root.querySelector('[data-rf-history-panel]');
      var histLoading = root.querySelector('[data-rf-history-loading]');
      var list        = root.querySelector('[data-rf-ticket-list]');
      var empty       = root.querySelector('[data-rf-ticket-empty]');

      var hasOpen = false;
      var histLoaded = false;

      // Status labels (i18n) for the lifecycle stepper — PHP passes them on the page
      // root as a JSON array [Init, Processing, Solved, Closed, Cancelled].
      var STATUS_LABELS = [];
      try { STATUS_LABELS = JSON.parse(root.getAttribute('data-rf-status-labels') || '[]'); } catch (e) { STATUS_LABELS = []; }
      // Per-status colors (same map as PHP rf_ticket_status_colors), keyed by status code.
      var STATUS_COLORS = {};
      try { STATUS_COLORS = JSON.parse(root.getAttribute('data-rf-status-colors') || '{}'); } catch (e) { STATUS_COLORS = {}; }

      function setFormLocked(locked) {
        hasOpen = locked;
        if (submit) submit.disabled = locked;
        if (lock) lock.style.display = locked ? '' : 'none'; // inline wins over the flex/hidden class clash
      }

      // Lifecycle stepper: Init(0) -> Processing(1) -> Solved(2) -> Closed(3). Steps up to
      // the current status are filled, the current one glows. Cancelled(4) leaves the flow,
      // so it shows a distinct terminal pill instead of the stepper.
      function hexToRgba(hex, a) {
        var m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(String(hex || ''));
        return m ? 'rgba(' + parseInt(m[1], 16) + ',' + parseInt(m[2], 16) + ',' + parseInt(m[3], 16) + ',' + a + ')' : 'rgba(236,72,153,' + a + ')';
      }

      function stepperHtml(status) {
        if (status === 4) {
          var c4 = STATUS_COLORS[4] || { bg: '#fee2e2', text: '#991b1b' };
          return '<div class="rf-tk-cancel" style="background:' + c4.bg + ';color:' + c4.text + '">' + escapeHtml(STATUS_LABELS[4] || 'Cancelled') + '</div>';
        }
        // Everything follows the CURRENT status color (CSS var --rf-tk-c): reached dots and
        // the connector bar are tinted with it; not-yet-reached steps stay the default gray.
        var col = ( STATUS_COLORS[status] && STATUS_COLORS[status].main ) || '#ec4899';
        var h = '<div class="rf-tk-steps" style="--rf-tk-c:' + col + ';--rf-tk-glow:' + hexToRgba(col, 0.25) + '">';
        for (var i = 0; i < 4; i++) {
          var cls = i < status ? 'is-done' : (i === status ? 'is-done is-active' : '');
          h += '<div class="rf-tk-step ' + cls + '"><span class="rf-tk-dot">' + (i + 1) + '</span><span class="rf-tk-lbl">' + escapeHtml(STATUS_LABELS[i] || '') + '</span></div>';
        }
        return h + '</div>';
      }

      // Terminal (history) rows show a muted status pill instead of the lifecycle stepper —
      // the stepper is only meaningful for the current, in-flight request.
      function statusBadge(t) {
        var c = STATUS_COLORS[t.status] || { bg: '#f3f4f6', text: '#374151' };
        return '<span style="background:' + c.bg + ';color:' + c.text + ';font-size:11px;font-weight:600;padding:2px 8px;border-radius:9999px">' + escapeHtml(t.status_label) + '</span>';
      }

      // Small inline line-icons for the current-request card sections.
      function svg(p) {
        return '<svg fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">' + p + '</svg>';
      }
      var TK_ICON = {
        tag:   '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8z"/><circle cx="7" cy="7" r="1.2"/>',
        doc:   '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        link:  '<path d="M9.5 14.5 14.5 9.5"/><path d="M11 6.8 12.4 5.4a4 4 0 0 1 5.7 5.7L16.6 12.5"/><path d="M12.9 17.4 11.5 18.8a4 4 0 0 1-5.7-5.7L7.2 11.7"/>',
        reply: '<path d="M21 11.5a8.5 8.5 0 0 1-12.9 7.3L3 20.5l1.7-5.1A8.5 8.5 0 1 1 21 11.5z"/>',
        check: '<circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/>',
        x:     '<circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/>'
      };

      function actionsHtml(t) {
        return t.is_open
          ? '<div class="mt-4 flex gap-2">'
            + '<button type="button" class="rf-ticket-act rf-tk-btn rf-tk-btn-close" data-id="' + t.id + '" data-mode="close">' + svg(TK_ICON.check) + escapeHtml('Close') + '</button>'
            + '<button type="button" class="rf-ticket-act rf-tk-btn rf-tk-btn-cancel" data-id="' + t.id + '" data-mode="cancel">' + svg(TK_ICON.x) + escapeHtml('Cancel') + '</button>'
            + '</div>'
          : '';
      }

      function rowHtml(t, withStepper) {
        // History (terminal): mirror the current card's look (title + type pill + status +
        // reference) but drop the stepper, the description and the admin response.
        if ( ! withStepper ) {
          var hType = '<span class="rf-tk-type">' + svg(TK_ICON.tag) + escapeHtml(t.type_label) + '</span>';
          var hRef = t.reference_url
            ? '<div class="rf-tk-sec"><div class="rf-tk-sec-h">' + svg(TK_ICON.link) + escapeHtml('Reference') + '</div><a href="' + encodeURI(t.reference_url) + '" target="_blank" rel="nofollow noopener" class="text-rom-pink underline break-all" style="font-size:14px">' + escapeHtml(t.reference_url) + '</a></div>'
            : '';
          return '<div class="p-5">'
            + '<div class="flex items-start justify-between gap-3 mb-2">'
            + '<h3 class="font-bold text-base text-gray-900 break-words min-w-0">' + escapeHtml(t.game_title) + '</h3>'
            + hType
            + '</div>'
            + '<div>' + statusBadge(t) + '</div>'
            + hRef
            + '</div>';
        }

        // Current (in-flight): richer, spaced, icon-labelled sections + the stepper.
        var typePill = '<span class="rf-tk-type">' + svg(TK_ICON.tag) + escapeHtml(t.type_label) + '</span>';
        var descBlk = t.description
          ? '<div class="rf-tk-sec"><div class="rf-tk-sec-h">' + svg(TK_ICON.doc) + escapeHtml('Details') + '</div><div class="rf-tk-sec-b">' + escapeHtml(t.description) + '</div></div>'
          : '';
        var refBlk = t.reference_url
          ? '<div class="rf-tk-sec"><div class="rf-tk-sec-h">' + svg(TK_ICON.link) + escapeHtml('Reference') + '</div><a href="' + encodeURI(t.reference_url) + '" target="_blank" rel="nofollow noopener" class="text-rom-pink underline break-all" style="font-size:14px">' + escapeHtml(t.reference_url) + '</a></div>'
          : '';
        var respBlk = t.response
          ? '<div class="rf-tk-reply"><div class="rf-tk-sec-h">' + svg(TK_ICON.reply) + escapeHtml('Admin response') + '</div><div class="rf-tk-sec-b">' + escapeHtml(t.response) + '</div></div>'
          : '';
        return '<div class="bg-white rounded-2xl shadow-md p-6">'
          + '<div class="flex items-start justify-between gap-3 mb-1">'
          + '<h3 class="font-bold text-lg text-gray-900 break-words min-w-0">' + escapeHtml(t.game_title) + '</h3>'
          + typePill
          + '</div>'
          + stepperHtml(t.status)
          + descBlk + refBlk + respBlk
          + actionsHtml(t)
          + '</div>';
      }

      // Current request = the member's newest ticket, shown only while still open (the
      // one-open-ticket rule means an open ticket is always the newest). Also drives the
      // form lock. Cheap: fetches just the top row.
      function loadCurrent() {
        if (curLoading) curLoading.classList.remove('hidden');
        if (curCard) curCard.classList.add('hidden');
        if (curNone) curNone.classList.add('hidden');
        rfFetch('my-tickets?offset=0&limit=1')
          .then(function (r) { return r.ok ? r.json() : null; })
          .then(function (j) {
            if (curLoading) curLoading.classList.add('hidden');
            var t = ( j && j.ok && j.tickets && j.tickets.length ) ? j.tickets[0] : null;
            if (t && t.is_open) {
              if (curCard) { curCard.innerHTML = rowHtml(t, true); curCard.classList.remove('hidden'); }
              setFormLocked(true);
            } else {
              if (curNone) curNone.classList.remove('hidden');
              setFormLocked(false);
            }
          })
          .catch(function () {
            if (curLoading) curLoading.classList.add('hidden');
            if (curNone) curNone.classList.remove('hidden');
            setFormLocked(false);
          });
      }

      // History shows only the latest 10 requests (terminal ones — the open request lives
      // in the Current section). Loaded lazily on first open; no pagination / load-more.
      function loadHistory() {
        if (histLoading) histLoading.classList.remove('hidden');
        // Fetch 11 so that, after excluding the (at most one) open request, up to 10
        // finished requests remain; hard-cap the display at 10 regardless of the response.
        rfFetch('my-tickets?offset=0&limit=11&scope=history')
          .then(function (r) { return r.ok ? r.json() : null; })
          .then(function (j) {
            if (histLoading) histLoading.classList.add('hidden');
            if (!j || !j.ok) { if (empty) empty.classList.remove('hidden'); return; }
            list.innerHTML = '';
            var shown = 0;
            ( j.tickets || [] ).forEach(function (t) {
              if (t.is_open || shown >= 10) { return; } // the open request lives in Current; cap at 10
              list.insertAdjacentHTML('beforeend', rowHtml(t, false));
              shown++;
            });
            var any = shown > 0;
            list.classList.toggle('hidden', !any);
            if (empty) empty.classList.toggle('hidden', any);
          })
          .catch(function () { if (histLoading) histLoading.classList.add('hidden'); if (empty) empty.classList.remove('hidden'); });
      }

      if (histToggle) histToggle.addEventListener('click', function () {
        var willOpen = histPanel.classList.contains('hidden');
        histPanel.classList.toggle('hidden', !willOpen);
        histToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        if (willOpen && !histLoaded) { histLoaded = true; loadHistory(); } // first open only
      });

      // Create
      if (form) form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (hasOpen) { toast('You already have an open request.'); return; }
        var fd = new FormData(form);
        var typeVal = fd.get('type');
        if (typeVal === null || typeVal === '') { toast('Please select a request type.'); return; }
        var title = String(fd.get('game_title') || '').trim();
        if (!title) { toast('Please enter the game title.'); return; }
        if (submit) submit.disabled = true;
        rfFetch('ticket', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            type: typeVal,
            game_title: title,
            reference_url: String(fd.get('reference_url') || '').trim(),
            description: String(fd.get('description') || '').trim()
          })
        })
          .then(function (r) { return r.json().then(function (b) { return { status: r.status, body: b }; }); })
          .then(function (res) {
            if (res.status === 200 && res.body && res.body.ok) {
              toast(res.body.message, 'success');
              form.reset();
              loadCurrent();                     // show the new open request + lock the form
              if (histLoaded) loadHistory(); // refresh history if it was already open
            } else {
              if (submit) submit.disabled = false;
              toast(res.body && res.body.message ? res.body.message : 'Could not submit your request.');
              if (res.status === 401) openModal();
            }
          })
          .catch(function () { if (submit) submit.disabled = false; toast('Network error. Please try again.'); });
      });

      // Close / Cancel (delegated on the whole page — covers the current card and history)
      root.addEventListener('click', function (e) {
        var btn = e.target.closest('.rf-ticket-act');
        if (!btn) return;
        var id = parseInt(btn.getAttribute('data-id'), 10) || 0;
        var mode = btn.getAttribute('data-mode');
        if (!id) return;
        btn.disabled = true;
        rfFetch('ticket/close', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: id, mode: mode })
        })
          .then(function (r) { return r.json().then(function (b) { return { status: r.status, body: b }; }); })
          .then(function (res) {
            if (res.status === 200 && res.body && res.body.ok) {
              toast(res.body.message, 'success');
              loadCurrent();                     // now no open request -> unlocks the form
              if (histLoaded) loadHistory();
            } else {
              btn.disabled = false;
              toast(res.body && res.body.message ? res.body.message : 'Could not update your request.');
            }
          })
          .catch(function () { btn.disabled = false; toast('Network error. Please try again.'); });
      });

      loadCurrent();
    }

    function renderWishlistPage(box) {
      var loading = box.querySelector('.rf-wish-loading');
      var list = box.querySelector('.rf-wish-list');
      var empty = box.querySelector('.rf-wish-empty');
      rfFetch('wishlist').then(function (r) { return r.ok ? r.json() : null; }).then(function (j) {
        if (loading) loading.classList.add('hidden');
        if (!j || !j.ok || !j.items.length) { if (empty) empty.classList.remove('hidden'); return; }
        var html = '';
        j.items.forEach(function (it) {
          var thumb = it.thumb
            ? '<img src="' + encodeURI(it.thumb) + '" alt="" class="rf-wl-thumb">'
            : '<div class="rf-wl-thumb"></div>';
          var typeKey = (it.type_key || '').replace(/[^a-z0-9_-]/gi, '');
          var badges = '<span class="rf-badge rf-badge-' + typeKey + '">' + escapeHtml(it.type || '') + '</span>';
          if (it.console) badges += '<span class="rf-badge rf-badge-console">' + escapeHtml(it.console) + '</span>';
          html += '<div class="rf-wl-row">'
            + '<a href="' + encodeURI(it.url) + '" class="rf-wl-link">'
            + thumb
            + '<div class="rf-wl-info"><div class="rf-wl-title">' + escapeHtml(it.title) + '</div>'
            + '<div class="rf-wl-badges">' + badges + '</div></div>'
            + '</a>'
            + '<button type="button" class="rf-wl-remove" data-post="' + it.id + '" aria-label="Remove from wishlist" title="Remove from wishlist">'
            + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'
            + '</button>'
            + '</div>';
        });
        list.innerHTML = html;
        list.classList.remove('hidden');

        // Remove (delegated): drop the row instantly and coalesce the actual
        // deletes into ONE bulk request so rapid removals don't hit the throttle.
        var pendingRemove = [];
        var removeTimer = null;
        var MSG_REMOVED = 'Removed from your wishlist.';
        var MSG_REMOVE_FAIL = 'Could not remove the item from your wishlist.';
        function flushRemove() {
          if (!pendingRemove.length) return;
          var ids = pendingRemove.slice();
          pendingRemove = [];
          rfFetch('wishlist', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ remove: ids })
          }).then(function (r) {
            if (r.status === 429) { // throttled: re-queue and retry once the window frees (no toast yet)
              pendingRemove = ids.concat(pendingRemove);
              if (removeTimer) clearTimeout(removeTimer);
              removeTimer = setTimeout(flushRemove, 3000);
              return;
            }
            toast(r.status === 200 ? MSG_REMOVED : MSG_REMOVE_FAIL, r.status === 200 ? 'success' : 'error');
          }).catch(function () { toast(MSG_REMOVE_FAIL, 'error'); });
        }
        list.addEventListener('click', function (e) {
          var btn = e.target.closest('.rf-wl-remove');
          if (!btn) return;
          e.preventDefault();
          // Blocked members can't modify their wishlist: report it straight from the
          // cached state — no optimistic removal and no request to the server.
          var disp = readDisplay();
          if (disp && disp.blocked === 1) { toast(MSG_REMOVE_FAIL, 'error'); return; }
          var row = btn.closest('.rf-wl-row');
          var post = parseInt(btn.getAttribute('data-post'), 10) || 0;
          if (post) pendingRemove.push(post);
          if (row) row.parentNode.removeChild(row); // optimistic
          if (!list.querySelector('.rf-wl-row')) {
            list.classList.add('hidden');
            if (empty) empty.classList.remove('hidden');
          }
          if (removeTimer) clearTimeout(removeTimer);
          removeTimer = setTimeout(flushRemove, 900);
        });
      }).catch(function () {
        if (loading) loading.classList.add('hidden');
        if (empty) empty.classList.remove('hidden');
      });
    }

    // ---- Profile dashboard (P7): stat counts + lazy accordion lists ----
    function initProfileDashboard() {
      var detail = document.querySelector('[data-rf-detail]');
      if (!detail || !readDisplay()) return;

      // Preserve the server-rendered (i18n) "Load more" label to restore after a load.
      var lmInit = document.querySelector('[data-rf-loadmore]');
      var LOAD_MORE = lmInit ? lmInit.textContent : 'Load more';

      // Counts + join date + status badge from one lightweight call.
      rfFetch('my-stats')
        .then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
        .then(function (res) {
          var j = res.body;
          if (res.status !== 200 || !j || !j.ok) return;
          setCount('wishlist', j.wishlist);
          setCount('comments', j.comments.total);
          setCount('reports', j.reports.total);
          setCount('ratings', j.ratings);
          setCount('contacts', j.contacts);
          setPending(j.comments.pending);
          setBlocked(j.blocked); // persist to localStorage + render Active/Blocked badge (also clears on unblock)
        })
        .catch(function () {});

      var CATS = {
        reports:  { endpoint: 'my-reports',  key: 'reports',  render: renderReportRow,  paginated: true },
        comments: { endpoint: 'my-comments', key: 'comments', render: renderCommentRow, paginated: true },
        ratings:  { endpoint: 'my-ratings',  key: 'ratings',  render: renderRatingRow,  paginated: true },
        contacts: { endpoint: 'my-contacts', key: 'contacts', render: renderContactRow, paginated: true }
      };

      // Accordion: one panel open at a time; each loads on first open, then caches.
      document.querySelectorAll('[data-rf-stat-toggle]').forEach(function (btn) {
        var cat = btn.getAttribute('data-rf-stat-toggle');
        var conf = CATS[cat];
        if (!conf) return;
        btn.addEventListener('click', function () { togglePanel(cat, btn, conf); });
        var panel = document.getElementById('rf-panel-' + cat);
        var lm = panel && panel.querySelector('[data-rf-loadmore]');
        if (lm) lm.addEventListener('click', function () { loadPage(panel, conf, false); });
      });

      function togglePanel(cat, btn, conf) {
        var panel = document.getElementById('rf-panel-' + cat);
        if (!panel) return;
        var willOpen = panel.classList.contains('hidden');
        closeAll();
        if (!willOpen) return; // it was open -> collapse (toggle off)
        panel.classList.remove('hidden');
        btn.setAttribute('aria-expanded', 'true');
        var chev = btn.querySelector('.rf-chevron'); if (chev) chev.classList.add('rotate-180');
        if (!panel._rfLoaded) loadPage(panel, conf, true);
      }
      function closeAll() {
        document.querySelectorAll('[data-rf-panel]').forEach(function (p) { p.classList.add('hidden'); });
        document.querySelectorAll('[data-rf-stat-toggle]').forEach(function (b) {
          b.setAttribute('aria-expanded', 'false');
          var c = b.querySelector('.rf-chevron'); if (c) c.classList.remove('rotate-180');
        });
      }

      // Fetch one page. first=true shows the skeleton and resets the panel; otherwise
      // it appends (Load more). Paginated categories advance _rfOffset; non-paginated
      // ones (comments) return everything in one shot.
      function loadPage(panel, conf, first) {
        var skel = panel.querySelector('[data-rf-skeleton]');
        var list = panel.querySelector('[data-rf-list]');
        var empty = panel.querySelector('[data-rf-empty]');
        var lmWrap = panel.querySelector('[data-rf-loadmore-wrap]');
        var lmBtn = panel.querySelector('[data-rf-loadmore]');
        var offset = panel._rfOffset || 0;
        if (first) {
          skel.classList.remove('hidden'); list.classList.add('hidden');
          empty.classList.add('hidden'); lmWrap.classList.add('hidden');
        } else if (lmBtn) { lmBtn.disabled = true; lmBtn.textContent = '…'; }

        var q = conf.endpoint + (conf.paginated ? ('?offset=' + offset + '&limit=10') : '');
        rfFetch(q)
          .then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
          .then(function (res) {
            panel._rfLoaded = true;
            if (first) skel.classList.add('hidden');
            var j = res.body;
            if (res.status !== 200 || !j || !j.ok) {
              if (first) { empty.textContent = (j && j.message) || 'Could not load. Please try again.'; empty.classList.remove('hidden'); }
              if (lmBtn) { lmBtn.disabled = false; lmBtn.textContent = LOAD_MORE; }
              return;
            }
            var items = j[conf.key] || [];
            if (first && !items.length) { empty.classList.remove('hidden'); return; }
            var html = '';
            for (var i = 0; i < items.length; i++) html += conf.render(items[i]);
            list.insertAdjacentHTML('beforeend', html);
            list.classList.remove('hidden');
            panel._rfOffset = offset + items.length;
            if (conf.paginated && j.has_more) {
              lmWrap.classList.remove('hidden');
              if (lmBtn) { lmBtn.disabled = false; lmBtn.textContent = LOAD_MORE; }
            } else {
              lmWrap.classList.add('hidden');
            }
          })
          .catch(function () {
            if (first) { skel.classList.add('hidden'); empty.classList.remove('hidden'); }
            if (lmBtn) { lmBtn.disabled = false; lmBtn.textContent = LOAD_MORE; }
          });
      }

      function setCount(cat, n) { fill('[data-rf-count="' + cat + '"]', String(n)); }
      function setPending(n) {
        var el = document.querySelector('[data-rf-count-pending]');
        if (!el) return;
        if (n > 0) { el.textContent = n + ' pending'; el.classList.remove('hidden'); }
        else { el.classList.add('hidden'); }
      }
    }

    function fmtDate(s) {
      if (!s) return '';
      var d = new Date(String(s).replace(' ', 'T'));
      if (isNaN(d.getTime())) return s;
      return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }
    function renderContactRow(it) {
      var replyIcon = '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/></svg>';
      var clockIcon = '<svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>';
      var replied = it.is_replied
        ? '<div class="mt-3 rounded-xl bg-amber-50 border border-amber-200 p-3 shadow-sm">'
          + '<div class="flex items-center gap-1 mb-2 text-amber-700">' + replyIcon
          + '<span class="text-[11px] font-bold uppercase tracking-wide">Admin reply</span></div>'
          + '<div class="text-sm text-amber-900 whitespace-pre-line break-words">' + escapeHtml(it.response) + '</div></div>'
        : '<span class="inline-flex items-center gap-1 mt-1 text-[11px] font-semibold text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">' + clockIcon + 'Awaiting reply</span>';
      return '<li class="py-3">'
        + '<div class="flex items-start justify-between gap-3">'
        + '<div class="min-w-0">'
        + '<div class="font-semibold text-gray-800 break-words">' + escapeHtml(it.title || '') + '</div>'
        + '<p class="text-sm text-gray-600 mt-1 whitespace-pre-line break-words">' + escapeHtml(it.content || '') + '</p>'
        + '</div>'
        + '<time class="shrink-0 text-xs text-gray-400">' + escapeHtml(fmtDate(it.created_at)) + '</time>'
        + '</div>'
        + replied
        + '</li>';
    }
    function renderReportRow(it) {
      var title = (it.post && it.post.title) ? escapeHtml(it.post.title) : '(removed post)';
      var link = it.url ? encodeURI(it.url) : '#';
      var content = it.content ? '<p class="text-sm text-gray-600 mt-1">' + escapeHtml(it.content) + '</p>' : '';
      return '<li class="py-3">'
        + '<div class="flex items-start justify-between gap-3">'
        + '<div class="min-w-0">'
        + '<a href="' + link + '" class="block font-semibold text-gray-800 hover:text-rom-pink truncate">' + title + '</a>'
        + '<span class="inline-block mt-1 text-[11px] font-semibold text-rom-pink bg-rom-pink/10 px-2 py-0.5 rounded-full">' + escapeHtml(it.type || '') + '</span>'
        + content
        + '</div>'
        + '<time class="shrink-0 text-xs text-gray-400">' + escapeHtml(fmtDate(it.date)) + '</time>'
        + '</div>'
        + '</li>';
    }
    function renderCommentRow(it) {
      var title = (it.post && it.post.title) ? escapeHtml(it.post.title) : '';
      var url = (it.post && it.post.url) ? encodeURI(it.post.url) : '#';
      var badge = it.status === 'approved'
        ? '<span class="text-[11px] font-semibold text-green-700 bg-green-50 px-2 py-0.5 rounded-full">Approved</span>'
        : '<span class="text-[11px] font-semibold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full">Pending</span>';
      return '<li class="py-3">'
        + '<div class="flex items-start justify-between gap-3">'
        + '<div class="min-w-0">'
        + '<a href="' + url + '" class="block font-semibold text-gray-800 hover:text-rom-pink truncate">' + title + '</a>'
        + '<p class="text-sm text-gray-600 mt-1">' + escapeHtml(it.content || '') + '</p>'
        + '</div>'
        + '<div class="shrink-0 flex flex-col items-end gap-1">' + badge + '<time class="text-xs text-gray-400">' + escapeHtml(fmtDate(it.date)) + '</time></div>'
        + '</div>'
        + '</li>';
    }
    function renderRatingRow(it) {
      var title = (it.post && it.post.title) ? escapeHtml(it.post.title) : '(removed post)';
      var stars = '<span class="rf-stars" style="font-size:.85rem"><span class="rf-stars-fill" style="width:' + ((it.score / 5) * 100) + '%"></span></span>';
      var content = it.content ? '<p class="text-sm text-gray-600 mt-1">' + escapeHtml(it.content) + '</p>' : '';
      return '<li class="py-3">'
        + '<div class="flex items-start justify-between gap-3">'
        + '<div class="min-w-0">'
        + '<span class="block font-semibold text-gray-800 truncate">' + title + '</span>'
        + '<span class="block mt-1">' + stars + '</span>'
        + content
        + '</div>'
        + '<time class="shrink-0 text-xs text-gray-400">' + escapeHtml(fmtDate(it.date)) + '</time>'
        + '</div>'
        + '</li>';
    }
  }

  if (isInAppWebview()) document.body.classList.add('rf-webview');
  applyState(readDisplay());

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind);
  } else {
    bind();
  }

  // Public API for later phases (P3+ download/comment/report buttons):
  //   window.rfAuth.getDisplay()      -> current display object or null
  //   window.rfAuth.fetch(path, opts) -> authenticated call with 401/403 handling
  //   window.rfAuth.openLogin()       -> open the login modal
  window.rfAuth = { fetch: rfFetch, getDisplay: readDisplay, openLogin: openModal };
})();
