/**
 * Ratings & reviews section (template-parts/rating-section.php). Vanilla, no library.
 * Reads the public reviews list + the caller's own rating from GET rf/v1/ratings and
 * submits a new rating to POST rf/v1/rating through window.rfAuth.fetch (adds the
 * same-origin header + credentials and centralises 401/403-blocked). Ratings are
 * immutable: once the member has rated, the form is replaced by a read-only card.
 */
(function () {
  'use strict';

  var sec = document.querySelector('[data-rf-rating]');
  if (!sec || !window.rfAuth) return;

  var auth   = window.rfAuth;
  var postId = parseInt(sec.getAttribute('data-rf-rating'), 10) || 0;

  var form      = sec.querySelector('.rf-rating-form');
  var mineBox   = sec.querySelector('.rf-rating-mine');
  var starWrap  = sec.querySelector('.rf-star-input');
  var stars     = starWrap ? starWrap.querySelectorAll('.rf-star-btn') : [];
  var contentEl = sec.querySelector('.rf-rating-content');
  var submitBtn = sec.querySelector('.rf-rating-submit');
  var msg       = sec.querySelector('.rf-rating-msg');

  var list    = sec.querySelector('.rf-reviews-list');
  var loading = sec.querySelector('.rf-reviews-loading');
  var empty   = sec.querySelector('.rf-reviews-empty');
  var moreWrap = sec.querySelector('.rf-reviews-more');
  var moreBtn  = sec.querySelector('.rf-reviews-loadmore');

  var selected = 0;
  var offset   = 0;

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function toast(m, t) { if (window.showToast) window.showToast(m, t || 'error'); }
  function starsHtml(score) {
    return '<span class="rf-stars" style="font-size:.9rem"><span class="rf-stars-fill" style="width:' + ((score / 5) * 100) + '%"></span></span>';
  }
  function fmtDate(s) {
    if (!s) return '';
    var d = new Date(String(s).replace(' ', 'T'));
    return isNaN(d.getTime()) ? s : d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
  }
  function setText(sel, v) { var e = sec.querySelector(sel); if (e) e.textContent = v; }

  // Play-Store summary: left = avg + total (blended headline from post_meta, includes the
  // legacy baseline — display-only), right = per-star bars built SOLELY from real member
  // reviews (dist). Bar width is each star's share of the member-review total, so the bars
  // always sum to 100% regardless of the legacy count.
  function renderSummary(post, dist) {
    post = post || {}; dist = dist || {};
    var avg = +post.avg || 0, total = +post.count || 0;
    setText('[data-rf-avg]', avg.toFixed(1));
    var fill = sec.querySelector('[data-rf-avg-fill]');
    if (fill) fill.style.width = ((avg / 5) * 100) + '%';
    setText('[data-rf-total]', total);
    var distTotal = 0;
    for (var s = 1; s <= 5; s++) { distTotal += +(dist[s] || dist[String(s)] || 0); }
    for (var st = 1; st <= 5; st++) {
      var c = +(dist[st] || dist[String(st)] || 0);
      var bar = sec.querySelector('[data-rf-bar="' + st + '"]');
      if (bar) bar.style.width = (distTotal ? (c / distTotal * 100) : 0) + '%';
      setText('[data-rf-barcount="' + st + '"]', c);
    }
  }

  // --- star input ---
  function paint(n) { for (var i = 0; i < stars.length; i++) stars[i].classList.toggle('is-on', i < n); }
  for (var s = 0; s < stars.length; s++) {
    (function (btn) {
      var v = parseInt(btn.getAttribute('data-score'), 10);
      btn.addEventListener('mouseenter', function () { paint(v); });
      btn.addEventListener('click', function () { selected = v; paint(v); });
    })(stars[s]);
  }
  if (starWrap) starWrap.addEventListener('mouseleave', function () { paint(selected); });

  function reviewRow(r) {
    var avatar = r.member_avatar
      ? '<img class="shrink-0" style="width:2.25rem;height:2.25rem;border-radius:9999px" alt="" src="' + encodeURI(r.member_avatar) + '">'
      : '<span class="shrink-0" style="width:2.25rem;height:2.25rem;border-radius:9999px;background:#e5e7eb;display:inline-block"></span>';
    return '<li class="py-3">'
      + '<div class="flex items-start gap-3">'
      + avatar
      + '<div class="min-w-0 flex-1">'
      + '<div class="flex items-center justify-between gap-2">'
      + '<span class="font-semibold text-gray-800 truncate">' + esc(r.member_name || 'Member') + '</span>'
      + '<time class="shrink-0 text-xs text-gray-400">' + esc(fmtDate(r.date)) + '</time>'
      + '</div>'
      + '<div class="mt-0.5">' + starsHtml(r.score) + '</div>'
      + (r.content ? '<p class="text-sm text-gray-600 mt-1">' + esc(r.content) + '</p>' : '')
      + '</div></div></li>';
  }

  function showMine(mine) {
    if (!mine) return;
    if (form) form.classList.add('hidden');
    if (mineBox) {
      mineBox.classList.remove('hidden');
      mineBox.innerHTML = '<p class="text-sm font-semibold text-gray-700 mb-1">Your rating</p>'
        + '<div>' + starsHtml(mine.score) + '</div>'
        + (mine.content ? '<p class="text-sm text-gray-600 mt-1">' + esc(mine.content) + '</p>' : '');
    }
  }

  function loadPage(first) {
    if (moreBtn) moreBtn.disabled = true;
    auth.fetch('ratings?post=' + postId + '&offset=' + offset + '&limit=10')
      .then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
      .then(function (res) {
        if (first && loading) loading.classList.add('hidden');
        var j = res.body;
        if (res.status !== 200 || !j || !j.ok) { if (first && empty) empty.classList.remove('hidden'); return; }
        if (first) renderSummary(j.post, j.dist);
        if (first && j.mine) showMine(j.mine);
        var items = j.reviews || [];
        if (first && !items.length) { if (empty) empty.classList.remove('hidden'); }
        var html = '';
        for (var k = 0; k < items.length; k++) html += reviewRow(items[k]);
        if (list) { list.insertAdjacentHTML('beforeend', html); if (items.length) list.classList.remove('hidden'); }
        offset += items.length;
        if (j.has_more) { if (moreWrap) moreWrap.classList.remove('hidden'); if (moreBtn) moreBtn.disabled = false; }
        else if (moreWrap) { moreWrap.classList.add('hidden'); }
      })
      .catch(function () {
        if (first && loading) loading.classList.add('hidden');
        if (first && empty) empty.classList.remove('hidden');
        if (moreBtn) moreBtn.disabled = false;
      });
  }
  if (moreBtn) moreBtn.addEventListener('click', function () { loadPage(false); });

  // --- submit ---
  if (form) form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!auth.getDisplay()) { auth.openLogin(); return; }
    if (msg) msg.classList.add('hidden');
    if (selected < 1) {
      if (msg) { msg.textContent = 'Please choose a star rating.'; msg.classList.remove('hidden'); }
      return;
    }
    if (submitBtn) submitBtn.disabled = true;
    auth.fetch('rating', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ post: postId, score: selected, content: contentEl ? contentEl.value : '' })
    })
      .then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
      .then(function (res) {
        if (submitBtn) submitBtn.disabled = false;
        var j = res.body;
        if (res.status === 200 && j && j.ok) {
          toast(j.message || 'Thank you for your rating!', 'success');
          showMine(j.mine);
          var disp = auth.getDisplay() || {};
          var mineReview = { member_name: disp.name, member_avatar: disp.avatar, score: j.mine.score, content: j.mine.content, date: j.mine.created_at };
          if (list) { list.insertAdjacentHTML('afterbegin', reviewRow(mineReview)); list.classList.remove('hidden'); }
          if (empty) empty.classList.add('hidden');
        } else {
          toast((j && j.message) || 'Could not save your rating. Please try again.', 'error');
        }
      })
      .catch(function () {
        if (submitBtn) submitBtn.disabled = false;
        toast('Network error. Please try again.', 'error');
      });
  });

  // --- initial load ---
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { loadPage(true); });
  } else {
    loadPage(true);
  }
})();
