# RATING_PLAN — Custom member ratings (Play Store style)

Status: DESIGN (awaiting review) · Date: 2026-08-01

Replace the rateYo, cookie-based guest rating with a **member-only** rating system:
display-only stars everywhere, plus a Play-Store-style review section (score 1–5 +
text) under single pages, backed by a new table. **Existing rating data is preserved.**

---

## 1. Decisions (locked)

| # | Decision |
|---|---|
| Average source | **Unchanged running-average.** Each member rating updates post_meta `rating_avg`/`rating_count` **incrementally with the existing formula** (`avg = (avg*count + score)/(count+1)`, `count += 1`); input = member score 1–5. The `member_rating` table does NOT compute the average — it only stores the review. No seed, no recompute-from-table; existing avg/count just keep accumulating. |
| Review section | **Full Play Store.** Member's own create/edit form + a **public list of all members' reviews** (paginated, Load more). |
| Moderation | **Auto-publish + `status` column.** Reviews show immediately; admin can hide later (1=visible, 0=hidden). Hiding removes the review from the LIST only — it does **not** recompute the average (score stays counted). |
| Editing | **None — immutable.** One rating per member per post; a second attempt is rejected (409). Enforced by UNIQUE(member_id, post_id). |
| Content | Optional, `wp_strip_all_tags` + trim, **≤ 1000 chars**. Score = whole star **1–5**. |
| Stars display | **Display-only, custom CSS** from `rating_avg` (fractional). rateYo (js+css) removed. |
| Guest rating | **Removed.** Rating is member-only now; legacy `k_rating` admin-ajax + cookie + nonce deleted. |

---

## 2. Current system (to be replaced) — verified in code

- Average stored in **post_meta** `rating_avg` (float) + `rating_count` (int); ACF fields on
  rom/lib/pack/emulator (`inc/setting-*.php`).
- **Displayed in many places** (read side we KEEP): `single-rom.php`, `single-emulator.php`,
  `single-library.php`, `single-pack.php`, `download.php`, `header.php` — each renders
  `<div class="rating" data-post_id data-rateyo-rating="{avg}">` for rateYo.
- Write path: `k_rating()` in `inc/ajax.php` (`wp_ajax[_nopriv]_k_rating`) → running average in
  post_meta; anti-repeat via **cookie `ratings`**; nonce `k_rating_action`; rateYo `onSet` in
  `js/main.js` (~L266); `rating_nonce` localized in `functions.php`; `'rating'` in the
  `get_nonce` map (`inc/ajax.php`).
- **SEO schema** (`header.php` L33–51): inline JSON-LD `aggregateRating` with
  `ratingCount: {count}?:1`, `ratingValue: {score}?:5` — **falls back to a fake 1 rating / 5★**
  when the post has none.
- **ACF defaults are fake:** `rating_count` default_value = **1**, `rating_avg` default_value = **5**
  (`inc/setting-rom.php` L273/285; also `setting-lib.php`, `setting-pack.php`) — every freshly
  published post looks pre-rated 5★ even before anyone rates it.

---

## 3. Data model

### 3.1 New table `{prefix}member_rating`
```sql
CREATE TABLE {prefix}member_rating (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id  BIGINT UNSIGNED NOT NULL,
  post_id    BIGINT UNSIGNED NOT NULL,
  score      TINYINT UNSIGNED NOT NULL,          -- 1..5 (validated)
  content    TEXT            NULL,
  status     TINYINT(1)      NOT NULL DEFAULT 1,  -- 1=visible, 0=hidden by admin
  created_at DATETIME        NOT NULL,
  updated_at DATETIME        NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_member_post (member_id, post_id),          -- one rating per member per post
  KEY idx_post_status_created (post_id, status, created_at), -- public reviews list, newest-first
  KEY idx_member_created (member_id, created_at)            -- profile "my ratings"
) ENGINE=InnoDB
```
Created in `auth/setting-member.php` (bump `RF_MEMBER_DB_VERSION` → '5'); add
`rf_member_rating_table()` helper. Idempotent `CREATE TABLE IF NOT EXISTS`.

### 3.2 Average update (unchanged running-average)
The `member_rating` table is **storage only** — it does NOT compute the average. On each new member
rating the POST handler updates post_meta with the SAME formula legacy `k_rating` used:
```
$count = (int)   get_post_meta($post_id, 'rating_count', true);
$avg   = (float) get_post_meta($post_id, 'rating_avg', true);
$new_count = $count + 1;
$new_avg   = (($avg * $count) + $score) / $new_count;   // score = member's 1..5
update_post_meta($post_id, 'rating_count', $new_count);
update_post_meta($post_id, 'rating_avg', round($new_avg, 2));
```
No seed, no SUM over the table. Existing avg/count simply keep accumulating (data preserved by not
changing the logic). Ratings are immutable (no edit) so the running average never needs to reverse a
value. Admin "hide" affects the reviews LIST only — the score stays counted in the average.
Templates keep reading `rating_avg`/`rating_count` unchanged.

---

## 4. Backend — REST (namespace rf/v1, member-core pattern)

New module `auth/actions/rating.php` (require after report.php in functions.php).

### 4.1 `POST /rating`  { post, score, content? }  — submit own rating (once)
- Guards: `rf_verify_request_origin`, `rf_current_member`, **blocked → 403**, `rf_check_action_rate`.
- Validate: `post` absint + published + `valid_post_type`; `score` int 1..5; `content`
  `wp_strip_all_tags`+trim, ≤ 1000 (`RF_RATING_MAXLEN`).
- **Insert-only (immutable):** `INSERT` the row; if it already exists (UNIQUE member_id+post_id) →
  **409 `already_rated`**, no update. (Guard by checking an existing row first and/or `INSERT IGNORE`
  + affected-rows.)
- On a genuine insert, update the post average **incrementally** per §3.2. Do NOT touch avg/count on a
  duplicate/409.
- Returns `{ ok, mine:{score,content,created_at}, post:{avg,count} }`.

### 4.2 `GET /ratings?post=&offset=&limit=`  — PUBLIC reviews list
- Public (no session required); origin check only. Visible rows (`status=1`) for the post,
  newest-first, paginated (limit default 10, max 50), served by `idx_post_status_created`.
- Join `member` for display name (+ avatar). Returns `{ ok, reviews:[{id,score,content,
  member_name,member_avatar,date}], has_more, total, post:{avg,count,dist?} }`.
- If a member session is present, also return `mine` (their own rating) to prefill the form.
- `dist` (5→1 star histogram) optional (GROUP BY score) — Play-Store bars; can be phase 2.

### 4.3 `GET /my-ratings?offset=&limit=`  — profile dashboard panel
- Member session; **allowed while blocked** (read-only, like my-reports). Own rows +
  post title (LEFT JOIN posts), newest-first, paginated. `{ ok, ratings:[...], has_more, total }`.

All list queries add a `, id DESC` tiebreaker for stable offset pagination (as with reports/comments).

---

## 5. Frontend

### 5.1 Display-only stars (replace rateYo)
- Add `rf_render_stars($avg)` helper (e.g. in `inc/utility.php`) → CSS overlay stars:
  `<span class="rf-stars" style="--rf-fill:{avg/5*100}%">★★★★★</span>` (empty + filled layer).
- CSS in `style.css` (`.rf-stars`). Replace the 6 `.rating` render blocks
  (single-rom/emulator/library/pack, download.php, header.php) with `rf_render_stars($score)`.
- Remove rateYo enqueue (js+css) in functions.php; remove `$('.rating').rateYo()` init in main.js.

### 5.2 Review section — `template-parts/rating-section.php` (single pages)
Cache-safe shell (static HTML, JS fills). Included in the single templates below the content.
- **Aggregate header:** big `rating_avg` + display stars + total `rating_count` (+ optional dist bars).
- **Member form** (`rf-authed-only`): custom 5-star input (buttons/radios, no lib) + textarea
  (≤1000) + submit → `POST /rating`. If already rated (`mine` present), show their rating **read-only**
  instead of the form (no edit). One toast on result.
- **Guest** (`rf-guest-only`): "Sign in to rate" line (no modal auto-open, matches comment pattern).
- **Blocked:** form hidden via `html.rf-blocked` (server still 403s) — reuse existing blocked hook.
- **Reviews list:** client-rendered from `GET /ratings`, newest-first, **Load more** (10/page),
  skeleton → rows → branded button. Reuse the accordion/list loader style from the profile dashboard.
- JS lives in a new `js/rating.js` (or auth.js) using `window.rfAuth.fetch`.

### 5.3 SEO schema + ACF defaults (no fake ratings)
Goal: a post has NO rating until it genuinely earns one — no phantom 5★ in search results.
- **ACF defaults → 0.** Change `rating_count` default_value `1 → 0` and `rating_avg` `5 → 0` in
  `inc/setting-rom.php`, `setting-lib.php`, `setting-pack.php` (and `setting-emulator.php` if it
  defines these fields). Only affects NEW posts; existing stored values are untouched.
- **SEO schema gated on real ratings.** In `header.php`, render the `aggregateRating` block ONLY
  when `rating_count > 0`; drop the `?:1` / `?:5` fallbacks. A just-published post (count 0) emits
  NO `aggregateRating` (Product/Thing schema without the rating node). Posts with ≥1 rating render
  normally from `rating_avg` / `rating_count` (which, post-launch, already blend seed + members).
- **Existing rated posts:** keep their current `rating_avg` / `rating_count` as the immutable seed
  (§3.2); later member ratings recompute the blend on top of that seed. Never overwritten.
- No blend: a never-rated post starts at `0 / 0`; the first member rating makes it `1 / score`.
  Already-rated posts keep their stored avg/count and keep accumulating (unchanged logic).

### 5.5 Profile dashboard — enable Ratings
- Flip the reserved "Ratings (Soon)" card to active; add `CATS.ratings` +
  `renderRatingRow` in the generic accordion loader; count from `my-stats`
  (add `ratings` count) ; panel = `GET /my-ratings`. Minimal, reuses the pattern.

---

## 6. Admin moderation
- Add a lightweight admin list (mirror `auth/admin-report.php`) `auth/admin-rating.php`
  (is_admin-gated): list reviews, **Hide/Unhide** action (flip `status`) — **list visibility only**,
  the average is not recomputed (§3.2). Optional delete.

---

## 7. Remove legacy guest flow (after member path verified)
- `inc/ajax.php`: delete `k_rating()` + its two `add_action` hooks; remove `'rating'` from the
  `get_nonce` allowed map.
- `functions.php`: remove `rating_nonce` localize (L197) + rateYo enqueue (js+css, deps).
- `js/main.js`: remove the rateYo init/submit block (~L266–350).
- Cookie `ratings`: stops being written; harmless if left in old browsers.

---

## 8. Data-preservation guarantees
1. The avg/count **calculation logic is unchanged** (same running average as legacy `k_rating`) —
   existing values are never wiped, just accumulated onto. No seed meta, no table aggregation.
2. New `member_rating` table is **purely additive** (storage only); migration idempotent
   (`IF NOT EXISTS`, version-guarded on `admin_init` + `after_switch_theme`).
3. ACF `rating_avg`/`rating_count` fields keep their stored values; only the field DEFAULT changes
   (1→0, 5→0), affecting **new posts only** — existing posts' data is not rewritten.
4. Rollback: drop `member_rating`; the post_meta averages are independent of it and remain valid.

---

## 9. Validation / security / throttle
- Score strictly int 1..5; content sanitized + length-capped (1000). Post must be published +
  `valid_post_type`. All `$wpdb->prepare`. Output escaped (member name/content) on render.
- Writes: origin + session + blocked + shared action throttle. Reads (`/ratings` public,
  `/my-ratings`) allowed while blocked.
- One rating/member/post enforced at DB (UNIQUE) — not just UI.

---

## 10. Build order (each step ends with php -l / node --check + a functional check)
1. **Schema** — `member_rating` table + `rf_member_rating_table()` + version bump. table & indexes exist.
2. **`POST /rating`** — insert row (409 on duplicate, immutable) + incremental avg/count update (§3.2).
   inserted once; 2nd attempt 409; blocked→403; avg/count move by the classic formula.
3. **`GET /ratings`** (public list + `mine`) & **`GET /my-ratings`**. shape + pagination + `,id DESC` tiebreaker.
4. **Display stars** — `rf_render_stars()` + CSS; swap 6 templates; drop rateYo enqueue+init. stars from avg, no rateYo.
5. **Review section** template + `js/rating.js` (submit-once form, list + Load more, guest/blocked). end-to-end.
6. **Profile Ratings** card + `my-ratings` panel + `my-stats` ratings count. count + list.
7. **ACF defaults → 0 + SEO schema gate** — rating_count/avg default 1→0 & 5→0 (rom/lib/pack,
   emulator if present); `header.php` emits `aggregateRating` only when `rating_count>0` (drop
   `?:1`/`?:5`). Independent of the rest (may run first). unrated posts show no fake 5★.
8. **Remove legacy `k_rating`** (ajax, nonce, localize, main.js block). no refs, checks pass.
9. *(optional)* Admin moderation page (hide/unhide = list visibility only); distribution histogram.

---

## 11. Suggestions / open items
- **SEO (now specified in §5.3):** the existing `header.php` JSON-LD keeps rich snippets, but is
  gated on `rating_count > 0` so a just-published post emits no `aggregateRating` (no fake 5★).
  Reviews themselves stay client-rendered.
- **Reviews cache:** `GET /ratings` for hot posts could use a per-post transient (like wishlist)
  invalidated on new review. Start simple (indexed query); add cache if load shows need.
- **Distribution bars** (5★…1★ histogram) — nice Play-Store touch, one GROUP BY; phase 2.
- **Delete own rating** — Play Store allows; add a DELETE (or score=0 semantics) if wanted. v1: edit only.
- **Avatar in reviews** — member.avatar_url is available; include for a richer list.

## 12. Risks
- Touching 6 display templates (star swap) — mechanical but broad; do in one focused step.
- Concurrent-rating race on the read-modify-write of avg/count post_meta (same as legacy `k_rating`) —
  acceptable (ratings are rare); note only.
- Removing guest rating changes product behavior (only members rate now) — intended per decisions.
