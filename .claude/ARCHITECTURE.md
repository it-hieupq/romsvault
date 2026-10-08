# ARCHITECTURE.md — romsvault Theme

**Version:** 3.0
**Platform:** WordPress / PHP 8.3
**Traced:** 2026-10-08 (thay thế bản 2.0 generated 2026-01-29, đã sai lệch nặng)

> **Nguồn chuẩn duy nhất: `functions.php`.** Toàn bộ include map nằm ở đó. Khi
> tài liệu này và code lệch nhau, **tin code** và sửa tài liệu.
>
> Member subsystem (phần lớn nhất của codebase) có doc riêng:
> [MEMBER_SYSTEM.md](./MEMBER_SYSTEM.md).

---

## 1. Tên & định danh

| | |
|---|---|
| Theme directory | `romsvault` |
| `@package` | `romsvault` |
| Textdomain i18n | `romsfun` (**không** đổi — đã dùng khắp code) |
| Function prefix | `k_` (legacy), `rf_` (member/tracking), `romsfun_` (enqueue) |
| Domain production | ⚠️ không nhất quán — xem § 10 Known debt |

---

## 2. Bản đồ thư mục

```
romsvault/
├── functions.php              # include map — ĐỌC FILE NÀY TRƯỚC
├── setting/                   # CPT + taxonomy + ACF field group  (5 file, 1.651 dòng)
│   ├── setting-rom.php        # CPT rom + 7 taxonomy + rewrite + admin column/filter/bulk
│   ├── setting-emulator.php   # CPT emulator + 2 taxonomy + rewrite
│   ├── setting-post.php       # ACF cho post mặc định
│   ├── setting-page-home.php  # flexible content trang home
│   └── setting-console-group.php
├── auth/                      # MEMBER SUBSYSTEM → xem MEMBER_SYSTEM.md
│   ├── setting-member.php     # schema 7 bảng + enum
│   ├── member-core.php        # session Redis, origin guard, throttle, restricted
│   ├── auth-google.php        # login/logout (Google ID token, RS256/JWKS)
│   ├── actions/*.php          # 8 module REST (rf/v1, 18 route)
│   ├── templates/*.php        # /wishlist /profile /request
│   ├── admin-*.php            # 6 trang admin
│   └── js/auth.js
├── tracking/                  # view/download beacon (KHÔNG bootstrap WP)
│   ├── config.php             # chỉ define(), không logic
│   ├── track.php              # endpoint POST /t
│   └── flush.php              # CLI, drain Redis → DB
├── caching/
│   ├── AppSecurity.php        # sanitize/validate/headers/IP
│   └── caching-rom.php        # filter counts + term cache
├── grabber/                   # 7 file: multi, igdb, mobygames, retrostic, rom, simple_html_dom
├── inc/                       # utility, ajax, options, seo, insert-link*, breadcrumb, nav-walker
│   ├── acf/                   # ACF PRO bundled (vendor)
│   └── acf-quick-edit-fields/ # vendor
├── template-parts/            # 15 partial
├── js/                        # main, admin, rating, toast, tracking
├── assets/                    # css (input/output tailwind), js (lightgallery), fonts, images
└── tailwindcss/               # build toolchain (node_modules — KHÔNG commit)
```

**Thư mục `class/` không tồn tại** (bản doc cũ ghi sai).
**`inc/setting-*.php` không tồn tại** — đã chuyển sang `setting/`.

---

## 3. Post Types

Chỉ còn **2 CPT**. (Bản doc cũ ghi 5 CPT + `ac` — `pack`, `library`, `ac` đã bị xoá
khỏi code; xem § 10.)

### 3.1 `rom` — `setting/setting-rom.php:7`

| Property | Value |
|---|---|
| `public` | `true` |
| `has_archive` | `false` |
| `rewrite` | **`false`** — URL dựng hoàn toàn bằng filter |
| `supports` | `title`, `editor`, `thumbnail` |
| `show_in_rest` | `true` |
| `menu_position` / `menu_icon` | 5 / `dashicons-media-interactive` |
| `capability_type` | `post` |

Taxonomy gắn kèm: `console`, `collection`, `region`, `genre`, `dev`, `pub`, `type`.

### 3.2 `emulator` — `setting/setting-emulator.php:7`

| Property | Value |
|---|---|
| `public` | `true` |
| `has_archive` | `false` |
| `rewrite` | `array('slug' => 'emulators/%console%')` |
| `supports` | `title`, `editor`, `thumbnail`, **`comments`** |
| `show_in_rest` | `true` |
| `menu_position` / `menu_icon` | 5 / `dashicons-album` |

⚠️ Tag `%console%` **chưa bao giờ được register** bằng `add_rewrite_tag()`. Nó vô hại
vì `post_type_link` (`k_change_emulator_link`) ghi đè permalink thành
`/emulators/{slug}.html`, nhưng đây là cấu hình chết.

Taxonomy gắn kèm: `emulator_console`, `os`.

---

## 4. Taxonomies

### 4.1 Của `rom`

| Taxonomy | `hierarchical` | `rewrite` | `show_in_rest` | Template |
|---|---|---|---|---|
| `console` | `true` | `slug: roms`, `with_front: false`, `hierarchical: false` | ✅ | `taxonomy-console.php` |
| `collection` | `true` | `slug: collection`, `hierarchical: false` | — | `taxonomy-collection.php` |
| `region` | `true` | default, `hierarchical: false` | — | `taxonomy.php` |
| `genre` | `true` | default, `hierarchical: false` | — | `taxonomy.php` |
| `dev` | **`false`** | default, `hierarchical: false` | — | `taxonomy.php` |
| `pub` | **`false`** | default, `hierarchical: false` | — | `taxonomy.php` |
| `type` | `true` | default **nhưng bị override thành không prefix** | ✅ | `taxonomy-type.php` |

### 4.2 Của `emulator`

| Taxonomy | `hierarchical` | `rewrite` | Template |
|---|---|---|---|
| `emulator_console` | `true` | `slug: emulator-console`, `hierarchical: true` → **bị `term_link` ghi đè thành `/emulators/{slug}`** | `taxonomy-emulator_console.php` |
| `os` | `true` | default, `hierarchical: true` | `taxonomy.php` |

---

## 5. URL & rewrite

### 5.1 Bảng URL

| Nội dung | URL |
|---|---|
| ROM single | `/roms/{console_original_slug \|\| console_slug}/{post_name}.html` |
| Console archive | `/roms/{console_slug}/` |
| Collection | `/collection/{slug}/` |
| Type archive | `/{type_slug}/` (**không prefix**) |
| Emulator single | `/emulators/{post_name}.html` |
| Emulator console archive | `/emulators/{slug}/` (+ `/page/{n}/`) |
| Download | `/download/{post_name}-{post_id}[/{file_index}]` |
| Member | `/wishlist/`, `/profile/`, `/request/` |
| Tracking beacon | `POST /t` (nginx → PHP-FPM trực tiếp) |

### 5.2 Cơ chế dựng URL ROM

Không dùng `rewrite` của WP. Ba filter phối hợp:

1. **`rewrite_rules_array`** (`setting-rom.php:823`) — lặp mọi term `console`, đọc ACF
   `console_original_slug` (fallback `$term->slug`), sinh **1 rule riêng cho từng console**:
   ```
   roms/{console_slug}/([^/]*)\.html$  →  index.php?post_type=rom&name=$matches[1]
   ```
   ⇒ Thêm/sửa console hoặc `console_original_slug` thì **phải flush rewrite rules**.
2. **`post_type_link`** (`setting-rom.php:883`) — build permalink ngược lại từ term
   `console` đầu tiên. Không có term → trả permalink gốc.
3. **`preview_post_link`** (`setting-rom.php:872`) — ROM preview đổi sang
   `?post_type=rom&p={ID}&preview=true` (vì rule `.html` không khớp bản nháp).

### 5.3 Taxonomy `type` — URL không prefix

- `generate_rewrite_rules` → `k_rewrite_type_taxonomy_link()` (`setting-rom.php:851`):
  sinh `{slug}/?$` và `{slug}/page/?([0-9]{1,})/?$` cho **mọi** term `type`, prepend vào
  `$wp_rewrite->rules`.
- `term_link` → `/{term->slug}/`.
- **301 redirect** `/type/{slug}/...` → `/{slug}/...` ở `template_redirect` **priority 1**
  (giữ cả query string). Bỏ qua khi `is_admin()`, `wp_doing_ajax()`, `wp_doing_cron()`.

> ⚠️ Vì term `type` chiếm URL gốc không prefix, **slug của term `type` xung đột trực tiếp
> với slug của page/post**. Kiểm tra trước khi tạo term `type` mới.

### 5.4 Emulator

- `generate_rewrite_rules` → `k_rewrite_emulator_console_link()`: `emulators/{slug}/` +
  pagination cho mỗi term.
- `rewrite_rules_array` → `k_rewrite_emulator_link()` (hook ở `setting-emulator.php:213`):
  thêm `emulators/([^/]*)\.html$`.
  ⚠️ **Hàm này không có `return` ở nhánh terms rỗng/`WP_Error`** → trả `null`, xoá sạch
  mảng rewrite rules. Chỉ không nổ vì luôn tồn tại term `emulator_console`.
- `term_link` → `/emulators/{slug}` (bỏ prefix `emulator-console`).
- `post_type_link` → `/emulators/{post_name}.html`.

### 5.5 Endpoint `/download`

- `add_rewrite_endpoint( 'download', EP_ROOT )` — `inc/utility.php:176` (hook `init`).
- `template_redirect` → `k_download_template_redirect()` (`inc/utility.php:180`) nạp
  `download.php`.
- Slug dạng `{post_name}-{post_id}`; segment thứ 2 (nếu có) là **file index 1-based**.
- SEO: `/download/*` bị `noindex` + bỏ canonical + override title/description qua các
  filter `wpseo_*` (`inc/utility.php:57-169`).

### 5.6 Flush rewrite rules

| Trigger | Nơi |
|---|---|
| `after_switch_theme` | `setting/setting-rom.php:958`, `auth/setting-member.php` |
| Guard option `rf_member_pages_rw` ở `admin_init` | `auth/member-pages.php:32` — **bump giá trị khi thêm route member** |

---

## 6. Template hierarchy (file thực tế)

### Single
| Điều kiện | File |
|---|---|
| `rom` | `single-rom.php` |
| `emulator` | `single-emulator.php` |
| `post` | `single.php` |

### Taxonomy
| Taxonomy | File |
|---|---|
| `console` | `taxonomy-console.php` |
| `collection` | `taxonomy-collection.php` |
| `type` | `taxonomy-type.php` |
| `emulator_console` | `taxonomy-emulator_console.php` |
| còn lại (`region`/`genre`/`dev`/`pub`/`os`) | `taxonomy.php` (dispatcher) |

`taxonomy.php` chọn partial + tiêu đề qua `get_taxonomy_template_slug_by_post_type()` /
`get_taxonomy_title_suffix_by_post_type()` (`inc/utility-post-type.php`).

### Page template
| Template name | File |
|---|---|
| Home | `page-home.php` |
| Collections | `page-collections.php` |
| Emulators | `page-emulators.php` |
| ROMs | `page-roms.php` |
| Contact Us | `page-contact-us.php` |
| All rom (browse) | `browse-roms.php` |
| fallback | `page.php` |

❌ **Không tồn tại** (doc cũ ghi sai): `page-packs.php`, `page-libraries.php`,
`page-login.php`, `page-signup.php`, `single-pack.php`, `single-library.php`,
`taxonomy-pack_console.php`, `taxonomy-lib_console.php`.

### Khác
`index.php`, `archive.php`, `search.php`, `404.php`, `comments.php`, `header.php`,
`footer.php`, `sidebar.php`, `download.php`.

### `template-parts/` (13)
`hero`, `rom-filter`, `rom-item-grid`, `rom-item-list`, `emu-item`, `blog-item`,
`blog-item-2`, `console-groups`, `console-item-list`, `rating-section`, `report-form`,
`sharing`, `content-none`.
(`pack-item` và `lib-item` đã xoá 2026-10-08 — xem § 10.1.)

---

## 7. Subsystem

### 7.1 Tracking view / download (v3.0)

Thay thế hoàn toàn `inc/ajax-tracking.php` của bản cũ.

```
browser navigator.sendBeacon()
   → POST /t                       nginx map SCRIPT_FILENAME → tracking/track.php
   → track.php                     KHÔNG load WordPress (~2MB thay vì ~68MB / 1.700 file)
   → Redis hIncrBy                 rftrk:pending_views | rftrk:pending_downloads
   → flush.php (system cron 10')   RENAME-stash → batch UPDATE → DEL
```

**`tracking/config.php`** — chỉ `define()`, không logic/include (mỗi byte trả phí mỗi
page view):

| Hằng | Giá trị | Ghi chú |
|---|---|---|
| `RFTRK_REDIS_HOST` / `_PORT` | `127.0.0.1` / `6379` | |
| `RFTRK_REDIS_TIMEOUT` | `1`s | connect **và** read — thiếu read timeout thì Redis treo sẽ ghim worker PHP-FPM |
| `RFTRK_REDIS_TIMEOUT_CLI` | `2.0`s | flush chạy từ cron, chờ được lâu hơn |
| `RFTRK_REDIS_PERSISTENT_ID` | `rftrk` | 1 socket / worker → hot path không bắt tay TCP |
| `RFTRK_KEY_VIEWS` | `rftrk:pending_views` | **cố ý không** có prefix `WP_REDIS_PREFIX` |
| `RFTRK_KEY_DOWNLOADS` | `rftrk:pending_downloads` | (plugin Redis Object Cache flush bằng `SCAN MATCH 'romsfun_*' + DEL` → sẽ xoá mất số chưa về DB) |
| `RFTRK_STASH_SUFFIX` | `:flushing` | bản copy đang được drain |
| `RFTRK_KEY_HEARTBEAT` | `rftrk:last_flush` | timestamp — monitor để phát hiện cron chết |
| `RFTRK_SQL_CHUNK` | `2000` | row/statement |

**Thiết kế cố ý:**
- `track.php` trả `204` **trước** khi làm việc gì; `sendBeacon` bỏ qua body.
- Buffer **không có TTL** — buffer bị bỏ rơi chỉ tốn vài MB, buffer hết hạn thì **mất số
  không dấu vết**. Cron chết được phát hiện bằng heartbeat.
- Redis chết → **mất count, không fallback ghi DB trực tiếp** (fallback sẽ biến sự cố
  Redis thành write storm lên `postmeta`).
- Không kiểm post tồn tại ở `track.php` (làm ở `flush.php`, nơi đã có DB connection).
- `flush.php` chỉ chạy CLI (`PHP_SAPI !== 'cli'` → 403). nginx chặn
  `location ^~ .../tracking/ { deny all; }`.
- `flush.php` **move buffer bằng RENAME** trước khi ghi, xoá bản copy **sau khi** ghi
  thành công → run chết giữa đường để lại copy cho run sau.
- Aggregate taxonomy (`rftrk_add_term_meta`, `flush.php:184`): `rom → console.console_rom_downloads`,
  `emulator → emulator_console.emulator_console_downloads`.
- ⚠️ Nếu 1 batch tiến gần 600s thì phải bọc `flock -n` — 2 run song song sẽ đọc cùng
  buffer và nhân đôi mọi count.

Phía client: `rf_enqueue_tracking_script()` (`functions.php:376`) quyết định
`trackType` = `view` (single) hoặc `download` (trang download **có** file index),
localize `rfTracking = { endpoint: '/t', postId, trackType }`.

Test plan vận hành: [`docs/tracking-test-plan.md`](../docs/tracking-test-plan.md).

### 7.2 Download token

`inc/download-token.php`

```
get_download_token() = base64( XOR( "{IP}|{timestamp}", <hardcoded key> ) )
```
- `$duration = 60` → timestamp **+60 phút** (doc cũ ghi 300s là sai).
- IP lấy từ `HTTP_CF_CONNECTING_IP` (Cloudflare), fallback `0.0.0.0`.
- Dùng ở: `download.php:276`, `inc/ajax.php:132`, `auth/actions/download.php:52`,
  và endpoint AJAX `get_download_token`.
- ⚠️ Key đối xứng hardcode ngay trong source (`inc/download-token.php:38`).

**Cloudflare purge:** filter `cloudflare_purge_by_url` → `purge_cloudflare_url()` thêm
`/download/{post_name}-{ID}` và `/download/.../{index}` cho mọi file vào danh sách purge.
⚠️ URL hardcode `https://romsfun.com`.

### 7.3 Hide / restricted (thay cho "hide status" cũ)

Mô hình **2 giá trị**, không còn 3:

| Giá trị `hide` | Nghĩa |
|---|---|
| `public` | hiện bình thường |
| `restricted` | **chỉ member active tải được** |

- `rf_is_restricted( $post_id )` (`auth/member-core.php:211`) — chỉ `true` khi `hide`
  **đúng bằng** `'restricted'`. Mọi giá trị khác (rỗng, legacy `download`/`all` còn
  trong DB) đều coi là public → dữ liệu cũ không thể vô tình gate bài viết.
- Gate thật ở server: `GET rf/v1/download-url` → 401 `auth_required` / 403 `blocked`.
- UI: `rf_render_restricted_notice()` (cache-safe, class `.rf-inactive-only`).
- Admin: toggle 1 click (`wp_ajax_k_change_hide_status`) + bulk action
  `set_hide_public` / `set_hide_restricted` + cột sortable + filter
  (`setting-rom.php:965-1054`).

Chi tiết: [MEMBER_SYSTEM.md § 8](./MEMBER_SYSTEM.md).

### 7.4 Caching

| File | Vai trò |
|---|---|
| `caching/caching-rom.php` | `get_filtered_counts()` (đếm theo filter), `get_cached_terms()`, `get_cached_all_terms()`, `get_cached_terms_by_ids()`, `get_top_type_terms_home()`, `get_filter_base_url()`, `get_brows_page_url()` |
| `template-parts/rom-filter.php` | transient `post_ids_of_{term_id}`, TTL `DAY_IN_SECONDS` |
| `auth/actions/*` | cache per-member — xem MEMBER_SYSTEM.md § 9 |

### 7.5 Security — `caching/AppSecurity.php`

Class static, 474 dòng:
- `validate_input()` (đệ quy, có giới hạn depth), `clean_text_input()`, `validate_number()`
- `check_input_length()`, `scan_payload()`, `normalize_input()` → phát hiện payload tấn công
- `log_threat()`, `flag_threat()`, `block_request()`, `send_error()` (một responder JSON duy nhất)
- `get_client_ip()` — validate IP đến theo **dải IP Cloudflare** (`cloudflare_ranges()`,
  `ip_in_ranges()`) trước khi tin `HTTP_CF_CONNECTING_IP`
- `set_security_headers()`

### 7.6 Grabber (admin-only)

`grabber/` — scrape metadata/gallery/file cho ROM. Nguồn: Retrostic, IGDB, MobyGames,
`grabber-multi.php` (1.900+ dòng, nhiều nguồn). Dùng `simple_html_dom.php`.

Trong `functions.php` chỉ **2 file được require**: `grabber/grabber.php` và
`grabber/grabber-multi.php`. Các file còn lại bị comment nhưng **vẫn tự đăng
`wp_ajax_*`** nếu được include — hiện không include nên endpoint đó không tồn tại.

---

## 8. AJAX & REST — toàn bộ bề mặt

### 8.1 REST `rf/v1` (18 route)
→ [MEMBER_SYSTEM.md § 8](./MEMBER_SYSTEM.md).

### 8.2 `admin-ajax.php` — public (`nopriv`)

| Action | File | Nonce | Ghi chú |
|---|---|---|---|
| `k_update_post_views` | `inc/ajax.php:6` | ❌ | legacy; tracking thật đi qua `/t` |
| `k_get_download` | `inc/ajax.php:21` | ❌ | trả HTML nút download + link có token; **có** check `rf_is_restricted` |
| `get_download_token` | `inc/ajax.php:165` | ❌ | trả token string |

❌ **Đã bị xoá khỏi code** (doc cũ vẫn liệt kê): `k_comment`, `k_rating`,
`handle_report_form`, `get_nonce`. Comment / rating / report nay là REST `rf/v1`.

### 8.3 `admin-ajax.php` — admin

| Nhóm | Action |
|---|---|
| Insert link | `k_insert_link`, `k_insert_link_aff`, `k_insert_link_s3`, `k_insert_external_link_aff` |
| Quick edit | `k_edit_download_links`, `k_add_download_link`, `k_save_download_links`, `k_change_post_status`, `k_edit_text_field`, `k_save_text_field` |
| ROM | `k_change_hide_status`, `k_check_duplicate`, `willgroup_get_file`, `romsfun_export_rom_links` |
| SEO | `k_insert_seo` |
| Grabber | `k_grabber`, `k_grabber_multi`, `k_grabber_gallery_multi`, `k_grab_file` (+ `k_grabber_rom/_igdb/_mobygames/_retrostic` khi file được include) |
| Member admin | `app_report_delete_row(s)`, `app_ticket_update_status`, `app_ticket_delete_row(s)`, `app_contact_save_reply`, `app_contact_delete_row(s)` |

---

## 9. Asset pipeline

| Handle | File | Deps | Điều kiện |
|---|---|---|---|
| `main-style` | `style.css` (`get_stylesheet_uri`) | — | luôn |
| `tailwind` | `assets/css/output.css` | — | luôn |
| `rf-toast` | `js/toast.js` | — (không jQuery) | luôn |
| `rf-auth` | `auth/js/auth.js` | `rf-toast` | luôn |
| `main-js` | `js/main.js` | `jquery`, `rf-toast` | luôn |
| `rf-rating` | `js/rating.js` | `rf-auth` | single valid post type / trang download |
| `rf-tracking` | `js/tracking.js` | — | chỉ khi có gì để track |
| `lightgallery` + `lg-thumbnail` + `lightgallery-init` | `assets/` | `jquery` | single valid post type |
| `admin-js` | `js/admin.js` | `jquery` | admin |

- Cache busting bằng `filemtime()` cho mọi asset tự viết.
- `assets/css/output.css` là **build artifact** của `tailwindcss/` — **không stage**.
- Font Plus Jakarta Sans self-host (`assets/fonts/`).
- Image size: chỉ giữ `thumbnail` (300×9999, không crop); unset `medium`,
  `medium_large`, `large`, `1536x1536`, `2048x2048`.
- Rating stars là **display-only** renderer tự viết trong `js/main.js` (RateYo đã bỏ).
- ACF PRO bundled nhưng **mọi dòng include bị comment** (`functions.php:261-262`) —
  theme trông đợi ACF chạy như **plugin**. Chỉ `add_filter('acf/settings/show_admin', '__return_false')`
  là còn hiệu lực (ẩn menu Field Groups).

---

## 10. Known debt (đã xác nhận bằng trace, chưa sửa)

### 10.1 ✅ Tàn dư CPT `pack` / `library` — ĐÃ DỌN (2026-10-08)
Hai CPT này không còn `register_post_type` từ trước; toàn bộ nhánh code không-thể-chạy-tới
đã được xoá ở 6 file + 3 file bị xoá hẳn:

| File | Đã xoá |
|---|---|
| `functions.php` | `pack`/`library` khỏi `is_singular()` (tracking + filter `the_content`), `page-packs.php`/`page-libraries.php` khỏi `get_romsfun_page_templates()` |
| `inc/utility-post-type.php` | `AVAILABLE_POST_TYPES` → `['rom','emulator']`, 3 map (`get_custom_console_name`, template slug, title suffix), `should_load_dynamic_nonce()` |
| `inc/utility.php` | 2 block select filter admin + 2 block `parse_query` tax_query |
| `inc/breadcrumb.php` | 6 nhánh `pack_console`/`lib_console`/`is_singular('pack'\|'library')` |
| `inc/nav-walker.php` | nhánh `page-packs.php` |
| `auth/actions/wishlist.php` | `rf_post_type_meta()` còn `rom` + `emulator` |
| *(xoá file)* | `template-parts/pack-item.php`, `template-parts/lib-item.php` |

Sweep xác nhận không còn `pack_console` / `lib_console` / `lib_type` / `page-packs` /
`page-libraries` trong code của theme. `php -l` pass toàn bộ.

### 10.2 ✅ `caching/ConfigCacheManager.php` — ĐÃ XOÁ (2026-10-08)
Class không được require ở đâu (3 dòng require trong `functions.php` đã bị comment, và
2 trong 3 file đó — `TaxonomyCacheManager.php`, `RomCacheManager.php` — không tồn tại trên
disk). Đã xoá cả file lẫn 3 dòng comment.

Cách đọc option hiện tại: gọi `get_field('<key>', 'option')` trực tiếp
(vd `auth/actions/download.php:43` đọc `affs`). Nếu sau này cần lại tầng cache option,
dựng mới — bản cũ lấy từ git (`git show 2a86d9b:caching/ConfigCacheManager.php`), dùng
typed constant PHP 8.3 và transient `romsfun_site_options` TTL 3 ngày.

### 10.3 ✅ `genDownloadToken()` — ĐÃ XOÁ (2026-10-08)
Hàm trong `functions.php`, không ai gọi, và có bug `setcookie(..., '1.0' + 600)` → `601`
(≈ epoch 1970) khiến cookie hết hạn ngay. Nó gọi ra `https://cloud.romspure.com/auth`.
Token thật dùng `get_download_token()` ở `inc/download-token.php` (xem § 7.2).

### 10.4 Domain / tên không nhất quán
| Nơi | Giá trị |
|---|---|
| Theme directory | `romsvault` |
| Textdomain | `romsfun` |
| `inc/download-token.php:57` | `https://romsfun.com/download/...` hardcode |
| `functions.php:450` | `https://cloud.romspure.com/auth` |
| `.claude/nginx-config.txt` | `server_name romsfun.com`, path `themes/romsfun` |

⇒ `nginx-config.txt` trỏ sai thư mục theme so với repo (`romsfun` vs `romsvault`).
**Phải kiểm lại trước khi deploy** — sai path thì endpoint `/t` chết.

### 10.5 `k_rewrite_emulator_link()` thiếu return
`setting/setting-emulator.php:197-212` — nhánh terms rỗng/`WP_Error` không `return $rules`
⇒ trả `null` ⇒ **xoá sạch rewrite rules**. Chỉ an toàn nhờ luôn có term.

### 10.6 Trùng `menu_position` admin
`rf-ratings` = 3.2 và `ticket` = 3.2; `options` = 3.3 và `contact` = 3.3.

### 10.7 Git history
Repo chỉ có **1 commit** `2a86d9b init`. Mọi commit trước (vd `c07b936`) **không tồn tại**
— repo đã bị re-init, history mất.

---

## 11. Guardrail khi sửa code

1. **Cache-aware** — không `is_user_logged_in()` / cookie WP trong template.
   Member state là client-side + REST `rf/v1`.
2. **i18n** — mọi string hướng người dùng qua `__()` / `esc_html__()` textdomain
   `romsfun`. UI mặc định English, **không** tiếng Việt.
3. **Escape khi output** — `esc_html` / `esc_attr` / `esc_url`.
4. **Mọi `$wpdb` query dùng `prepare()`** (ngoại lệ duy nhất: DDL trong
   `auth/setting-member.php` — table name chỉ từ `$wpdb->prefix`, không có input user).
5. **Verify** — sau sửa PHP: `php -l <file>`; sau sửa JS: `node --check <file>`.
6. **Flush rewrite** sau khi đổi console slug / `console_original_slug` / term `type` /
   route member.
7. **Không stage** `tailwindcss/node_modules/`, `assets/css/output.css`, lockfile.
