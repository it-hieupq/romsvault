# ARCHITECTURE.md — romsvault Theme

**Platform:** WordPress · PHP 8.3 · Tailwind CSS 3
**Traced:** 2026-10-08

> **`functions.php` là nguồn chuẩn duy nhất** cho việc file nào thật sự được load.
> Khi tài liệu này và code lệch nhau: **tin code, sửa tài liệu, và nói cho user biết.**
>
> Tham chiếu trong doc này dùng dạng `file.php:symbol()` thay vì số dòng — số dòng rot
> sau mỗi lần sửa.

Doc liên quan: [DATA_DICTIONARY.md](./DATA_DICTIONARY.md) ·
[MEMBER_SYSTEM.md](./MEMBER_SYSTEM.md) ·
[tracking-test-plan.md](../docs/tracking-test-plan.md)

---

## 1. Định danh

| | |
|---|---|
| Theme directory | `romsvault` |
| `@package` | `romsvault` |
| Textdomain | `rom` (khai báo ở `style.css` header + `load_theme_textdomain()`) |
| Thư mục `languages/` | **chưa tồn tại** → mọi string rơi về literal English |
| Function prefix | `rom_` (theme-level: enqueue, nav) · `rf_` (member + tracking) · `k_` (legacy) |

---

## 2. Bản đồ thư mục

```
romsvault/
├── functions.php              # include map — ĐỌC FILE NÀY TRƯỚC
│
├── setting/                   # đăng ký CPT, taxonomy, ACF field group
│   ├── setting-rom.php        # CPT rom + 7 taxonomy + rewrite + admin column/filter/bulk
│   ├── setting-emulator.php   # CPT emulator + 2 taxonomy + rewrite
│   ├── setting-post.php       # ACF cho post mặc định
│   ├── setting-page-home.php  # flexible content trang Home
│   └── setting-console-group.php
│
├── auth/                      # MEMBER SUBSYSTEM → MEMBER_SYSTEM.md
│   ├── setting-member.php     # schema 7 bảng + enum dùng chung
│   ├── member-core.php        # session Redis, origin guard, throttle, restricted
│   ├── auth-google.php        # login / logout (Google ID token, RS256 qua JWKS)
│   ├── actions/               # 8 module REST → 18 route rf/v1
│   ├── templates/             # /wishlist /profile /request
│   ├── admin-*.php            # 6 trang admin
│   └── js/auth.js
│
├── tracking/                  # beacon view/download — KHÔNG bootstrap WordPress
│   ├── config.php             # chỉ define(), không logic
│   ├── track.php              # endpoint POST /t
│   └── flush.php              # CLI, drain Redis → DB
│
├── caching/
│   ├── AppSecurity.php        # sanitize / validate / security header / client IP
│   └── caching-rom.php        # filter counts + term cache
│
├── grabber/                   # scrape metadata/gallery/file cho ROM (admin-only)
│
├── inc/                       # utility, ajax, options, seo, insert-link*, breadcrumb, nav-walker, menu-icons
│   ├── acf/                   # ACF PRO bundled (vendor, include bị comment)
│   └── acf-quick-edit-fields/ # vendor
│
├── template-parts/            # 12 partial
├── js/                        # main, admin, rating, toast, tracking
├── assets/                    # css (tailwind in/out), js (lightGallery), fonts, images
└── tailwindcss/               # build toolchain (node_modules — KHÔNG commit)
```

### File có trên disk nhưng KHÔNG được load

| File | Trạng thái |
|---|---|
| `grabber/grabber-rom.php` | require bị comment |
| `grabber/grabber-mobygames.php` | require bị comment |
| `grabber/grabber-igdb.php` | require bị comment |
| `grabber/grabber-retrostic.php` | require bị comment |
| `inc/check-duplicate.php` | **không có require nào** → `wp_ajax_k_check_duplicate` không tồn tại ở runtime |
| `inc/acf/acf.php` | include bị comment — theme trông đợi ACF chạy dạng **plugin** |

Mỗi file grabber tự `add_action('wp_ajax_…')` ở cuối, nên include lại là đủ để endpoint
sống lại — không cần sửa gì thêm.

---

## 3. Post Types

Theme có đúng **2 CPT**.

### 3.1 `rom` — `setting/setting-rom.php:k_init_rom()`

| Property | Value |
|---|---|
| `public` | `true` |
| `has_archive` | `false` |
| `rewrite` | **`false`** — URL dựng hoàn toàn bằng filter (§ 5.2) |
| `supports` | `title`, `editor`, `thumbnail` |
| `show_in_rest` | `true` |
| `capability_type` | `post` |
| `menu_position` / `menu_icon` | `5` / `dashicons-media-interactive` |

Taxonomy: `console`, `collection`, `region`, `language`, `genre`, `dev`, `pub`, `type`

### 3.2 `emulator` — `setting/setting-emulator.php:k_init_emulator()`

| Property | Value |
|---|---|
| `public` | `true` |
| `has_archive` | `false` |
| `rewrite` | **`false`** — URL dựng hoàn toàn bằng filter (§ 5.4) |
| `supports` | `title`, `editor`, `thumbnail`, **`comments`** |
| `show_in_rest` | `true` |
| `menu_position` / `menu_icon` | `5` / `dashicons-album` |

Taxonomy: `emulator_console`, `os`

---

## 4. Taxonomies

### 4.1 Của `rom`

| Taxonomy | `hierarchical` | `rewrite` | REST | Template |
|---|---|---|---|---|
| `console` | `true` | `slug: roms`, `with_front: false`, `hierarchical: false` | ✅ | `taxonomy-console.php` |
| `collection` | `true` | `slug: collection`, `hierarchical: false` | — | `taxonomy-collection.php` |
| `region` | `true` | default, `hierarchical: false` | — | `taxonomy.php` |
| `language` | `true` | default, `hierarchical: false` | — | `taxonomy.php` |
| `genre` | `true` | default, `hierarchical: false` | — | `taxonomy.php` |
| `dev` | `false` | default, `hierarchical: false` | — | `taxonomy.php` |
| `pub` | `false` | default, `hierarchical: false` | — | `taxonomy.php` |
| `type` | `true` | default, **bị override thành URL không prefix** (§ 5.3) | ✅ | `taxonomy-type.php` |

### 4.2 Của `emulator`

| Taxonomy | `hierarchical` | `rewrite` | Template |
|---|---|---|---|
| `emulator_console` | `true` | `slug: emulators`, `with_front: false`, `hierarchical: false` | `taxonomy-emulator_console.php` |
| `os` | `true` | default, `hierarchical: true` | `taxonomy.php` |

### 4.3 `console` có `default_term` — mọi `rom` luôn có console

`console` đăng kèm `'default_term' => [ 'name' => 'Unknown', 'slug' => 'unknown' ]`.

Lý do: `console` **là** thứ dựng permalink ROM (`post_type_link`), nên một `rom` không có
console thì không có URL dùng được — permalink rơi về dạng query, breadcrumb trống,
`rom_exists()` mất khả năng chống trùng.

- WP tạo term này **ngay trong `register_taxonomy()`** (không lazy) và lưu
  `wp_options.default_term_console`.
- Phủ **mọi đường ghi**: editor, quick edit, bulk edit, REST/Gutenberg, và grabber
  (`wp_insert_post()` của `k_grabber_multi`) — khác với validation ở tầng form, vốn chỉ
  chặn được editor.
- ⚠️ Nó **không chặn** sai sót, chỉ đảm bảo URL hợp lệ. ROM thiếu console vẫn publish êm
  tại `/roms/unknown/{slug}/` và search engine index được.
  Soi tồn đọng: `edit.php?post_type=rom&console=unknown`.

---

## 5. URL & rewrite

### 5.1 Bảng URL

| Nội dung | URL |
|---|---|
| ROM single | `/roms/{console_slug}/{post_name}/` |
| Console archive | `/roms/{console_slug}/` |
| Collection | `/collection/{slug}/` |
| Language archive | `/language/{slug}/` |
| Type archive | `/{type_slug}/` — **không prefix** |
| Emulator single | `/emulators/{emulator_console_slug}/{post_name}/` |
| Emulator console archive | `/emulators/{slug}/` (+ `/page/{n}/`) |
| Download | `{permalink của post}` + `download/[{file_index}/]` |
| Member | `/wishlist/` · `/profile/` · `/request/` |
| Tracking beacon | `POST /t` — nginx map thẳng vào PHP-FPM |

### 5.2 ROM — URL dựng bằng filter, không dùng `rewrite` của WP

Ba filter phối hợp, tất cả trong `setting/setting-rom.php`:

1. **`rewrite_rules_array`** — **một rule duy nhất** cho mọi ROM:
   ```
   roms/[^/]+/(?!(?:feed|embed)/?$)([^/]+)/?$   →   index.php?post_type=rom&name=$matches[1]
   ```
   Segment console **không được match** — `post_name` một mình đã xác định ROM. Rule vì thế
   không phụ thuộc danh sách term: thêm, đổi tên hay xoá console **không cần flush**.
   Lookahead giữ nguyên endpoint taxonomy `/roms/{console}/feed/` và `/embed/`.
2. **`post_type_link`** — dựng permalink từ slug của term `console` đầu tiên của post.
   Post không có term `console` → trả permalink gốc.
3. **`preview_post_link`** — ROM preview chuyển sang `?post_type=rom&p={ID}&preview=true`,
   vì rule trên không khớp bản nháp.

> ROM truy cập qua segment console **sai** vẫn resolve, rồi `redirect_canonical` 301 về
> permalink đúng (§ 5.5).

### 5.3 Taxonomy `type` — chiếm URL gốc

- **`generate_rewrite_rules`** → `k_rewrite_type_taxonomy_link()`: sinh `{slug}/?$` và
  `{slug}/page/?([0-9]{1,})/?$` cho mọi term `type`, prepend vào `$wp_rewrite->rules`.
- **`term_link`** → `/{term->slug}/`.
- **301 redirect** `/type/{slug}/…` → `/{slug}/…` ở `template_redirect` **priority 1**
  (giữ query string). Bỏ qua khi `is_admin()`, `wp_doing_ajax()`, `wp_doing_cron()`.

> ⚠️ Term `type` chiếm namespace URL gốc ⇒ **slug term `type` xung đột trực tiếp với slug
> của page và post**. Kiểm tra trùng trước khi tạo term `type` mới.

### 5.4 Emulator

Đối xứng với ROM. Trong `setting/setting-emulator.php`:

- **CPT `rewrite: false`** ⇒ WP không sinh rule nào cho post type này.
- **Taxonomy `emulator_console` mang thẳng prefix `emulators`** ⇒ WP tự sinh toàn bộ rule
  archive (`/emulators/{slug}/`, `/page/{n}/`, feed, embed). Không có `term_link` override,
  không có rule viết tay per-term.
- **`rewrite_rules_array`** — một rule duy nhất cho single, cùng dạng ROM:
  ```
  emulators/[^/]+/(?!(?:feed|embed)/?$)([^/]+)/?$   →   index.php?post_type=emulator&name=$matches[1]
  ```
  Archive nông hơn single đúng một segment nên hai bên không tranh nhau.
- **`post_type_link`** → `/emulators/{emulator_console_slug}/{post_name}/` theo term đầu
  tiên. Emulator không có term `emulator_console` giữ URL dạng query `?emulator={slug}`,
  vẫn resolve được.

### 5.5 Endpoint `download` — nằm trên permalink của post

`/roms/{console}/{post_name}/download/` và `/roms/{console}/{post_name}/download/{n}/`
(tương tự dưới `/emulators/`).

- Rule nằm **cạnh rule single của từng post type** (§ 5.2, § 5.4): nó resolve chính post
  đó rồi set `download=1`, cộng `download_file` khi có file index. ⇒ `$wp_query` là một
  query singular thật, `is_singular()` **true**, `get_queried_object()` trả post.
- Query var `download` + `download_file` đăng ký bằng filter `query_vars` trong
  `inc/utility.php`. `download_file` **1-based**; rule chỉ nhận `[1-9][0-9]*` nên
  `/download/0/` và `/download/abc/` rơi về 404.
- `template_redirect` → `k_download_template_redirect()` nạp `download.php`. Slug không
  khớp post nào ⇒ giữ nguyên 404 của WP.
- `download.php` lấy post bằng `get_queried_object()`, dựng mọi link con từ
  `get_permalink()`. Không có `download_file` ⇒ liệt kê mọi file; có ⇒ trang tải 1 file.
- `inc/ajax.php:k_get_download()` nhận `post_id` + `file` qua `$_POST`
  (`#download-container[data-post][data-file]` → `js/main.js`).
- SEO: trang download `noindex,follow`, canonical trỏ **permalink của post**, title +
  meta description override qua các filter `wpseo_*` trong `inc/utility.php`.
- **`redirect_canonical`** bị tắt **chỉ** khi query var `download` hoặc `rf_page` có giá
  trị. Với download đây là **điều kiện sống còn**: query resolve về post, nên nếu không
  tắt thì WP 301 `/.../download/` về permalink của post. Mọi URL khác vẫn được WP chuẩn
  hoá (trailing slash, segment console sai).

> ⚠️ File index đánh trên mảng **đã merge** `download_list` + `download_list_aff`.
> Đổi thứ tự hoặc số lượng item sẽ làm lệch mọi URL `.../download/{n}/` đã chia sẻ.

### 5.6 Khi nào rewrite rules được flush

| Trigger | Nơi |
|---|---|
| `after_switch_theme` | `setting/setting-rom.php`, `auth/setting-member.php` |
| `admin_init` + guard option `rf_member_pages_rw` | `auth/member-pages.php` — **bump giá trị option khi thêm route member mới** |
| `created_type` / `edited_type` / `delete_type` | `setting/setting-rom.php` — rule root-level của `type` là thứ duy nhất còn sinh per-term |

---

## 6. Template hierarchy

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
| `region` · `language` · `genre` · `dev` · `pub` · `os` | `taxonomy.php` (dispatcher) |

`taxonomy.php` chọn partial và heading qua `get_taxonomy_template_slug_by_post_type()` /
`get_taxonomy_title_suffix_by_post_type()` (`inc/utility-post-type.php`).

### Page template (có header `Template Name`)
| Template Name | File |
|---|---|
| Home | `page-home.php` |
| Collections | `page-collections.php` |
| Emulators | `page-emulators.php` |
| Roms | `page-roms.php` |
| Contact Us | `page-contact-us.php` |
| All rom | `browse-roms.php` |
| *(fallback)* | `page.php` |

`get_rom_page_templates()` (`functions.php`) liệt kê các template được coi là "trang
landing" — dùng ở `template-parts/hero.php` để quyết định có render hero hay không.
**Thêm page template mới thì cân nhắc cập nhật hàm này.**

### Khác
`index.php` · `archive.php` · `search.php` · `404.php` · `comments.php` · `header.php` ·
`footer.php` · `sidebar.php` · `download.php`

### `template-parts/` (12)
`hero` · `rom-filter` · `rom-card` · `emu-item` ·
`post-card` · `collection-card` · `console-groups` · `console-item-list` · `rating-section` ·
`report-form` · `sharing` · `content-none`

---

## 7. Subsystem

### 7.1 Tracking view / download

```
browser navigator.sendBeacon()
   → POST /t                      nginx map SCRIPT_FILENAME → tracking/track.php
   → track.php                    KHÔNG load WordPress (~2 MB thay vì ~68 MB / ~1.700 file)
   → Redis hIncrBy                rftrk:pending_views | rftrk:pending_downloads
   → flush.php (system cron 10')  RENAME-stash → batch UPDATE → DEL
```

`tracking/config.php` chỉ chứa `define()`, không logic và không include — mỗi byte ở đây
trả phí trên **mỗi** page view:

| Hằng | Giá trị | Ghi chú |
|---|---|---|
| `RFTRK_REDIS_HOST` / `_PORT` | `127.0.0.1` / `6379` | |
| `RFTRK_REDIS_TIMEOUT` | `1`s | connect **và** read — thiếu read timeout thì Redis treo sẽ ghim worker PHP-FPM tới `max_execution_time` |
| `RFTRK_REDIS_TIMEOUT_CLI` | `2.0`s | flush chạy từ cron, chờ được lâu hơn |
| `RFTRK_REDIS_PERSISTENT_ID` | `rftrk` | 1 socket / worker → hot path không bắt tay TCP |
| `RFTRK_KEY_VIEWS` | `rftrk:pending_views` | hash `{post_id => delta}` |
| `RFTRK_KEY_DOWNLOADS` | `rftrk:pending_downloads` | hash `{post_id => delta}` |
| `RFTRK_STASH_SUFFIX` | `:flushing` | bản copy đang được drain |
| `RFTRK_KEY_HEARTBEAT` | `rftrk:last_flush` | timestamp — monitor để phát hiện cron chết |
| `RFTRK_SQL_CHUNK` | `2000` | row / statement |

**Quyết định thiết kế — đừng "tối ưu" lại:**

- Prefix `rftrk:` **cố ý không** mang `WP_REDIS_PREFIX` (`romsvault_`). Plugin Redis Object
  Cache flush bằng `SCAN MATCH 'romsvault_*' + DEL` → sẽ xoá sạch count chưa về DB.
- Buffer **không có TTL**. Buffer bị bỏ rơi chỉ tốn vài MB (bounded bởi số post); buffer
  hết hạn thì **mất số không để lại dấu vết**. Cron chết phát hiện bằng heartbeat, không
  phải bằng cách để Redis tự discard.
- `track.php` trả `204` **trước** khi làm bất cứ việc gì — `sendBeacon` bỏ qua body, và
  một count rơi không được phép nổi lên thành lỗi cho khách.
- Redis chết → **mất count, không fallback ghi DB trực tiếp**. Fallback sẽ biến một sự cố
  Redis thành write storm lên `postmeta`.
- Không kiểm post tồn tại ở `track.php` — việc đó làm ở `flush.php`, nơi đã có sẵn DB
  connection. ID lạ là vô hại cho tới lúc ghi.
- `flush.php` chỉ chạy CLI (`PHP_SAPI !== 'cli'` → 403), và nginx chặn thêm
  `location ^~ /wp-content/themes/romsvault/tracking/ { deny all; }`.
- `flush.php` **move buffer bằng RENAME** trước khi ghi, xoá bản copy **sau khi** ghi
  thành công → run chết giữa đường để lại copy cho run sau nhặt.
- Aggregate taxonomy (`rftrk_add_term_meta()`): `rom → console.console_rom_downloads`,
  `emulator → emulator_console.emulator_console_downloads`.
- ⚠️ Nếu một batch tiến gần 600s thì **phải** bọc `flock -n`: hai run song song sẽ đọc
  cùng buffer và nhân đôi mọi count.

Phía client: `rf_enqueue_tracking_script()` (`functions.php`) chọn `trackType` = `view`
(trang single) hoặc `download` (trang download **có** file index), rồi localize
`rfTracking = { endpoint: '/t', postId, trackType }`.

### 7.2 Download token

`inc/download-token.php`

```
get_download_token() = base64( XOR( "{client_ip}|{timestamp}", <key hardcode> ) )
```

- `$duration = 60` → timestamp là **thời điểm +60 phút**.
- Client IP lấy từ `HTTP_CF_CONNECTING_IP` (Cloudflare), fallback `0.0.0.0`.
- Caller: `download.php`, `inc/ajax.php:k_get_download()`,
  `auth/actions/download.php:rf_build_download_url()`, và AJAX action `get_download_token`.

**Cloudflare purge:** filter `cloudflare_purge_by_url` → `purge_cloudflare_url()` thêm
`.../download/` cùng `.../download/{index}/` cho mọi file vào danh sách purge. URL dựng
bằng `get_permalink()`.

### 7.3 Restricted content (field `hide`)

Mô hình **2 giá trị**:

| `hide` | Nghĩa |
|---|---|
| `public` | hiện bình thường |
| `restricted` | **chỉ member active tải được** |

- `rf_is_restricted( $post_id )` (`auth/member-core.php`) chỉ trả `true` khi `hide` **đúng
  bằng** `'restricted'`. Mọi giá trị khác — rỗng, `public`, hay giá trị lạ còn sót trong
  DB — đều coi là public, nên dữ liệu cũ không thể vô tình gate một bài viết.
- **Gate thật nằm ở server:** `GET rf/v1/download-url` trả `401 auth_required` /
  `403 blocked`. UI chỉ là trình bày.
- UI: `rf_render_restricted_notice()` — render cho **mọi** khách nên cache-safe, hiện qua
  class `.rf-inactive-only`.
- Admin: toggle 1 click (`wp_ajax_k_change_hide_status`), bulk action
  `set_hide_public` / `set_hide_restricted`, cột sortable + filter — tất cả trong
  `setting/setting-rom.php`.

Chi tiết luồng: [MEMBER_SYSTEM.md § 8](./MEMBER_SYSTEM.md).

### 7.4 Caching

| Nơi | Vai trò |
|---|---|
| `caching/caching-rom.php` | `get_cached_terms()`, `get_cached_all_terms()`, `get_cached_terms_by_ids()`, `get_filter_base_url()`, `get_brows_page_url()` |
| `template-parts/rom-filter.php` | transient `post_ids_of_{term_id}`, TTL `DAY_IN_SECONDS` |
| `auth/actions/*` | cache per-member — [MEMBER_SYSTEM.md § 9](./MEMBER_SYSTEM.md) |

Option của theme đọc trực tiếp bằng `get_field('<key>', 'option')` — không có tầng cache
riêng cho option.

### 7.5 Security — `caching/AppSecurity.php`

Class static:

- `validate_input()` (đệ quy, giới hạn depth), `clean_text_input()`, `validate_number()`
- `check_input_length()`, `scan_payload()`, `normalize_input()` — phát hiện payload tấn công
- `log_threat()`, `flag_threat()`, `block_request()`, `send_error()` — **một** responder
  JSON duy nhất cho mọi lối thoát lỗi
- `get_client_ip()` — verify IP nguồn thuộc **dải IP Cloudflare** (`cloudflare_ranges()`,
  `ip_in_ranges()`) *trước khi* tin `HTTP_CF_CONNECTING_IP`
- `set_security_headers()`

### 7.6 Grabber (admin-only)

Scrape metadata / gallery / file cho ROM từ Retrostic, IGDB, MobyGames. Dùng
`simple_html_dom.php`. Chỉ `grabber/grabber.php` và `grabber/grabber-multi.php` được load
(xem § 2).

---

## 8. Bề mặt AJAX & REST

### 8.1 REST `rf/v1` — 18 route
→ [MEMBER_SYSTEM.md § 8](./MEMBER_SYSTEM.md)

### 8.2 `admin-ajax.php` — public (`nopriv`)

| Action | Nơi | Nonce | Ghi chú |
|---|---|---|---|
| `k_update_post_views` | `inc/ajax.php` | ❌ | legacy — tracking thật đi qua `/t` |
| `k_get_download` | `inc/ajax.php` | ❌ | trả HTML nút download + link có token; **có** check `rf_is_restricted` |
| `get_download_token` | `inc/ajax.php` | ❌ | trả token string |

### 8.3 `admin-ajax.php` — admin

| Nhóm | Action |
|---|---|
| Insert link | `k_insert_link`, `k_insert_link_aff`, `k_insert_link_s3`, `k_insert_external_link_aff` |
| Quick edit | `k_edit_download_links`, `k_add_download_link`, `k_save_download_links`, `k_change_post_status`, `k_edit_text_field`, `k_save_text_field` |
| ROM | `k_change_hide_status`, `willgroup_get_file`, `rom_export_rom_links` |
| SEO | `k_insert_seo` |
| Grabber | `k_grabber`, `k_grabber_multi`, `k_grabber_gallery_multi`, `k_grab_file` |
| Member admin | `app_report_delete_row(s)`, `app_ticket_update_status`, `app_ticket_delete_row(s)`, `app_contact_save_reply`, `app_contact_delete_row(s)` |

---

## 9. Asset pipeline

| Handle | File | Deps | Điều kiện load |
|---|---|---|---|
| `main-style` | `style.css` | — | luôn |
| `tailwind` | `assets/css/output.css` | — | luôn |
| `rf-toast` | `js/toast.js` | — (không jQuery) | luôn |
| `rf-auth` | `auth/js/auth.js` | `rf-toast` | luôn |
| `main-js` | `js/main.js` | `jquery`, `rf-toast` | luôn |
| `rf-rating` | `js/rating.js` | `rf-auth` | single valid post type / trang download |
| `rf-tracking` | `js/tracking.js` | — | chỉ khi có gì để track |
| `lightgallery` + `lg-thumbnail` + `lightgallery-init` | `assets/` | `jquery` | single valid post type |
| `admin-js` | `js/admin.js` | `jquery` | admin |

**Biến localize:**

| Biến JS | Handle | Nội dung |
|---|---|---|
| `ajax` | `main-js` | `ajax_url`, `enable_dynamic_nonce`, `site_url` (từ `WP_SITEURL`) |
| `RF_AUTH` | `rf-auth` | `clientId` (`GOOGLE_CLIENT_ID`), `rest` (`rest_url('rf/v1/')`) |
| `rfTracking` | `rf-tracking` | `endpoint: '/t'`, `postId`, `trackType` |

Ghi chú:

- Cache busting bằng `filemtime()` cho mọi asset tự viết.
- `assets/css/output.css` là **build artifact** của `tailwindcss/` — **không stage**.
- Font Plus Jakarta Sans self-host trong `assets/fonts/`.
- Image size: chỉ giữ `thumbnail` (300×9999, không crop). Unset `medium`, `medium_large`,
  `large`, `1536x1536`, `2048x2048`.
- Rating stars là renderer **display-only** tự viết trong `js/main.js`.
- ACF chạy dạng plugin; `add_filter('acf/settings/show_admin', '__return_false')` ẩn menu
  Field Groups khỏi admin.
- **JS không được hardcode domain** — lấy từ `ajax.site_url`.

---

## 10. Known debt

Những mục dưới đây đã verify bằng trace và **chưa sửa**.

### 10.1 Hardcode host ngoài `WP_SITEURL`
`inc/insert-link-aff.php` ghép link qua `https://cloud.romspure.com/`. Đây là **host
khác**, không phải site URL, nên không thay bằng `site_url()` được — cần đưa thành theme
option hoặc hằng ở `wp-config.php`.

### 10.2 Secret hardcode trong source
`grabber/grabber-multi.php`, `grabber-igdb.php`, `grabber-retrostic.php` — `client_id` +
`client_secret` của Twitch/IGDB nằm thẳng trong code và đã vào git. Nên chuyển sang hằng
ở `wp-config.php`, và rotate credential vì repo đã giữ chúng.
Key XOR của `get_download_token()` (`inc/download-token.php`) cũng hardcode.

### 10.3 Trùng `menu_position` admin
`rf-ratings` = `3.2` và `ticket` = `3.2`; `options` = `3.3` và `contact` = `3.3`.
Thứ tự menu giữa mỗi cặp không xác định.

### 10.4 ACF field group rỗng
`auth/admin-report.php` đăng một field group **không có `key` và không có `fields`** —
chỉ có `location`. Nội dung trang Report thực tế render bằng hook `toplevel_page_report`.

### 10.5 Không có `languages/`
`load_theme_textdomain( 'rom', … )` không tìm thấy catalog nào ⇒ 900+ lời gọi `__()`
hiện chỉ trả về literal English. i18n chưa thật sự hoạt động.

---

## 11. Guardrail khi sửa code

1. **Cache-aware** — front-end bị Cloudflare/APO cache. Không dùng `is_user_logged_in()`
   hay cookie WP trong template. Member state là client-side + REST `rf/v1`; template
   render trạng thái khách, JS hoán đổi qua `html.rf-authed`.
2. **i18n** — mọi string hướng người dùng qua `__()` / `esc_html__()` với textdomain
   `rom`. UI mặc định English, **không** tiếng Việt.
3. **Escape khi output** — `esc_html` / `esc_attr` / `esc_url`.
4. **Mọi query `$wpdb` dùng `prepare()`.** Ngoại lệ duy nhất: DDL trong
   `auth/setting-member.php`, nơi tên bảng chỉ đến từ `$wpdb->prefix`.
5. **Không hardcode domain** — PHP dùng `site_url()` / `home_url()` / `content_url()`;
   JS dùng `ajax.site_url`.
6. **Flush rewrite rules** sau khi thêm route member (kèm bump option
   `rf_member_pages_rw`). Rule của `rom`, `emulator` và hai taxonomy console đều generic
   nên không cần; term `type` tự flush bằng hook.
7. **Verify** — sau sửa PHP: `php -l <file>`; sau sửa JS: `node --check <file>`.
8. **Không stage** `tailwindcss/node_modules/`, `assets/css/output.css`, lockfile.
