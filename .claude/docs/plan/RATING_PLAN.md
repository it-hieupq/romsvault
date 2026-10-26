# RATING_PLAN — Custom member ratings (Play Store style)

Status: **IMPLEMENTED** · Designed 2026-08-01 · Last updated 2026-10-26 (recount model, commit `74808dd`)

Member-only ratings: a member rates a post once (whole star 1–5 + optional short review),
reviews are listed Play-Store style under single pages, and the post's average/count are a
cache recounted from the visible reviews. The legacy rateYo / cookie / `k_rating` guest
flow is gone. Post types: `rom` and `emulator` (the theme has no others).

---

## 1. Decisions (current)

| # | Decision |
|---|---|
| Source of truth | The `member_rating` table. Post_meta `rating_avg` / `rating_count` are only a **cache** of the visible reviews (`status = 1`), so listings never aggregate the table. |
| Cache update | **Recount, not running average.** `rf_rating_recount()` recomputes count / avg / per-star dist from the table after every new rating and every admin hide / unhide / delete, under a per-post `GET_LOCK`. No visible review → both meta rows are deleted (missing reads as 0). |
| Review section | Play Store: aggregate (score, total, 5→1 bars), member form, public paginated list (Load more), avatars. |
| Moderation | Auto-publish + `status` column (1 visible, 0 hidden). Hide / unhide / delete all recount the cache, so the shown average always matches the visible reviews. |
| Editing | **Immutable.** One rating per member per post, enforced by `UNIQUE(member_id, post_id)`; a second attempt is 409. |
| Content | Optional, `wp_strip_all_tags` + trim, **≤ 164 chars** (`RF_RATING_MAXLEN`). Score: int 1–5. |
| Display | Server-rendered stars everywhere (no JS renderer, no rateYo). One display rule: `rom_rating()` rounds half-up to one decimal. Unrated posts show **empty stars + "No reviews yet"**; cards hide the star badge. |
| Guest rating | Removed. Guests see the sign-in modal when they try to rate. |
| SEO | `SoftwareApplication` node in Yoast's graph; `aggregateRating` only when `count > 0`. |

---

## 2. Data model

### 2.1 Table `{prefix}member_rating` (`auth/setting-member.php`, `RF_MEMBER_DB_VERSION`)
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
  UNIQUE KEY uq_member_post (member_id, post_id),
  KEY idx_post_status_created (post_id, status, created_at),
  KEY idx_member_created (member_id, created_at)
) ENGINE=InnoDB
```
Helper: `rf_member_rating_table()`. Idempotent `CREATE TABLE IF NOT EXISTS`, version-guarded.

### 2.2 Post_meta cache
- `rating_count` (int) and `rating_avg` (float, 2 decimals) on `rom` / `emulator`.
- Written **only** by `rf_rating_recount()` (`auth/actions/rating.php`):
  `SELECT score, COUNT(*) … WHERE post_id = %d AND status = 1 GROUP BY score` → count, avg, dist.
- **Not ACF fields any more** (removed from `setting/setting-rom.php`): their `default_value 0`
  wrote a pair of zero rows on every post save and let admins hand-edit the cache. All zero-value
  rows and the `_rating_*` ACF reference rows were deleted (~140k rows, 2026-10-26).
- Why keep the cache: cards in a 20-item listing read it from the primed meta cache with **0 extra
  queries**; aggregating the table per card would cost one query each.

---

## 3. Backend — REST (`rf/v1`, `auth/actions/rating.php`)

### 3.1 `POST /rating` { post, score, content? } — submit own rating (once)
- Guards: `rf_verify_request_origin` (header `X-RF-Auth: 1` + same-origin `Origin`/`Referer`),
  `rf_current_member`, blocked → 403, `rf_check_action_rate`.
- Validate: post published + `valid_post_type`; score int 1..5; content sanitized + capped.
- `INSERT IGNORE` → 0 rows = 409 `already_rated`; on insert, `rf_rating_recount()`.
- Returns `{ ok, mine, post:{avg,count,score}, dist, summary:{hero,compact}, message }`.
  `score` is the rounded display value; `summary` is server-rendered HTML
  (`rom_rating_summary_html()`) that `js/rating.js` swaps into the page after a vote.

### 3.2 `GET /ratings?post=&offset=&limit=` — public reviews list
- Origin check only (no session needed). Visible rows, newest-first, `, id DESC` tiebreaker,
  limit default 10 / max 50. Joins `member` for name + avatar.
- Returns `{ ok, reviews, has_more, total, dist, post:{avg,count,score} }`, plus `mine` when a
  member session is present.
- **Cache:** the first page (`offset=0, limit=10`, what every single page view asks for) keeps its
  public part (`reviews`, `total`, `dist`) in transient `rf_ratings_p1_<post>` — Redis-backed —
  for `RF_RATINGS_CACHE_TTL` (1 h). `rf_rating_recount()` deletes it on every write, so the TTL only
  bounds stale reviewer names / avatars. `post` is read from meta (object cache) and `mine` is never
  cached. Measured: miss 4 queries, hit 0.
- **Skipped for guests on unreviewed posts:** `rating-section.php` renders `data-rf-count`;
  `js/rating.js` shows the empty state without a request when it is `0` and there is no member
  session (no reviews to list, no `mine` to find). Members always fetch.

### 3.3 `GET /my-ratings?offset=&limit=` — profile dashboard panel
- Member session; allowed while blocked (read-only). Own rows + post title (LEFT JOIN posts).

---

## 4. Frontend

### 4.1 Display helpers (`inc/utility.php`)
- `rom_rating( $post_id )` → `{count, score}`; score rounded half-up to 1 decimal, 0 when unrated.
  Used by the hero, download page, cards and schema, so every place shows the same number.
- `rom_star_fill( $score )` → fill width %. A star tile is 1.1em wide but the star 1em, so whole
  stars count the full tile and the fraction only the star (4.3 → 85.45%, not 86%). Mirrored by
  `starFill()` in `js/rating.js`.
- `rom_rating_summary_html( $post_id, 'hero'|'compact' )` → stars + score + "(n reviews)", or empty
  stars + "No reviews yet". The `hero` variant (single pages, under the cover) has one invisible
  button per star tile.

### 4.2 Where ratings show
- **Single rom / emulator hero:** `rom_rating_summary_html( …, 'hero' )` in
  `[data-rf-rating-summary="hero"]`. Hover previews a score; click scrolls to `#rating` and preselects
  that score in the form (guests → sign-in modal; already rated → just scrolls).
- **Download page:** `'compact'` variant, display only.
- **Cards** (`template-parts/rom-card.php`, `emu-item.php`): star badge only when `count > 0`.
- **Review section** `template-parts/rating-section.php` (`#rating`) + `js/rating.js`: aggregate
  (score / total / 5→1 bars from `dist`), member form (`rf-authed-only`), read-only "Your rating"
  once rated, public list with Load more. After a vote the summary, bars and hero line update in place.
- **Profile:** "Ratings" card + `my-ratings` panel (`auth/js/auth.js`).
- Cache-safe: all HTML is identical for every visitor; member state is client-side only. No cookie is read.

### 4.3 SEO (`inc/utility.php`)
- `rom_software_schema( $post )` builds a `SoftwareApplication` node: plain-text `name`,
  `applicationCategory` (`GameApplication` for roms, `UtilitiesApplication` for emulators),
  `operatingSystem` (rom: console term; emulator: `os` terms; omitted when none), free `offers`, and
  `aggregateRating` (`ratingValue`, `ratingCount`, best 5 / worst 1) only when `count > 0`.
  No empty values.
- A `wpseo_schema_graph` filter adds it to Yoast's graph on single rom / emulator pages (not the
  noindexed download pages) with `@id` `…#software`, `mainEntityOfPage` → Yoast's `WebPage`, and
  `image` → Yoast's `#primaryimage` (the featured image / og:image source). Those `@id`s are **looked
  up in the graph**, not rebuilt, because Yoast's stored permalink can differ from the live URL; a
  missing node means no link. Without Yoast, `header.php` prints the node standalone instead.

---

## 5. Admin moderation (`auth/admin-ratings.php`)
- WP_List_Table of reviews; Hide / Unhide / Delete with `manage_options` + nonce.
- Each action reads the review's `post_id` first, then calls `rf_rating_recount()`.
- Delete also frees `uq_member_post`, so that member can rate the post again.

---

## 6. Validation / security
- Score strictly int 1..5; content sanitized + capped (164). Post must be published + valid type.
- Every query uses `$wpdb->prepare`; output escaped (member name / content / summary HTML).
- Writes: origin + session + blocked + action throttle. Reads allowed while blocked.
- One rating per member per post enforced in the DB (UNIQUE), not just the UI. The hero's
  client-side `getDisplay()` check only routes the UI; the server is the gate.
- Recount races: read + write run under `GET_LOCK('rf_rating_<post>')`, so a recount that read
  before another vote committed can't write its stale total last (verified with 6 concurrent writers).

---

## 7. Changes from the original design (2026-08-01)
- **Running average → recount from table.** The original kept the legacy `k_rating` running average
  and let "hide" leave the score counted. That made the shown count disagree with the visible list
  after moderation and accumulated rounding drift. There turned out to be no legacy baseline to
  preserve (every stored `rating_count` was 0), so the table became the source of truth.
- **ACF rating fields removed** (they were the source of the zero rows and allowed hand edits).
- **`rf_render_stars()` + JS star renderer → server-rendered `rom_rating_summary_html()`**;
  `data-rateyo-*`, `data-post_id` and the cookie `ratings` reads were removed from the templates.
- **Unrated display:** cards briefly showed a fake ★5 for unrated posts; now the badge is hidden and
  the hero shows empty stars.
- **Schema:** the hand-written JSON-LD in `header.php` (string values, broken by quotes in titles,
  empty `operatingSystem` / `softwareVersion`) became the Yoast-graph node in §4.3.
- Content cap is 164 chars, not 1000. `single-library.php` / `single-pack.php` and their settings
  never applied to this theme.

## 8. Open items
- **Cloudflare cache:** a vote updates the meta but doesn't purge the post's cached HTML, so cached
  pages show the old score until the next purge. Could purge the permalink from `rf_rating_recount()`.
  This also gates the guest skip above: a page cached with `data-rf-count="0"` keeps skipping the
  reviews request for guests until it is purged.
- **Delete own rating:** not offered (ratings are immutable); admin delete is the only path.
