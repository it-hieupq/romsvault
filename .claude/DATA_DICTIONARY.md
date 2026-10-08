# DATA_DICTIONARY.md — romsvault Data Architecture

**Version:** 3.0
**Traced:** 2026-10-08 từ `setting/*.php`, `inc/options.php`, `auth/setting-member.php`
(thay bản cũ ghi nguồn `/inc/setting-*.php` — **đường dẫn đó không còn tồn tại**)

> Kiến trúc & URL: [ARCHITECTURE.md](./ARCHITECTURE.md) ·
> Member subsystem: [MEMBER_SYSTEM.md](./MEMBER_SYSTEM.md)

---

## 1. Custom Post Types

### `rom` — `setting/setting-rom.php:7`
| Property | Value |
|---|---|
| `public` | `true` |
| `has_archive` | `false` |
| `rewrite` | `false` (URL dựng bằng filter) |
| `supports` | `title`, `editor`, `thumbnail` |
| `show_in_rest` | `true` |

**Taxonomies:** `console`, `collection`, `region`, `genre`, `dev`, `pub`, `type`

### `emulator` — `setting/setting-emulator.php:7`
| Property | Value |
|---|---|
| `public` | `true` |
| `has_archive` | `false` |
| `rewrite` | `slug: emulators/%console%` (tag `%console%` chưa register — dead) |
| `supports` | `title`, `editor`, `thumbnail`, `comments` |
| `show_in_rest` | `true` |

**Taxonomies:** `emulator_console`, `os`

### ❌ Đã bị xoá khỏi code
`pack`, `library`, `ac` (Activation Code) — bản doc cũ liệt kê 5 CPT + `ac`.
Không còn `register_post_type` cho 3 cái này. Tàn dư tham chiếu: xem
[ARCHITECTURE.md § 10.1](./ARCHITECTURE.md).

Hệ quả cho field: các group `lib_settings`, `group_pack_settings`, `ac_settings` và mọi
field của chúng (`lib_size`, `lib_screenshot`, `pack_size`, `pack_number_of_files`,
`pack_request`, `pack_guide`, `pack_note`, `ac_code`, `ac_max`, `ac_users`) **không còn
được register**. Dữ liệu cũ nếu còn trong `postmeta` thì là orphan.

---

## 2. Taxonomies

### Của `rom`
| Taxonomy | `hierarchical` | Rewrite slug | REST |
|---|---|---|---|
| `console` | `true` | `roms` (`with_front: false`) | ✅ |
| `collection` | `true` | `collection` | — |
| `region` | `true` | default | — |
| `genre` | `true` | default | — |
| `dev` | **`false`** | default | — |
| `pub` | **`false`** | default | — |
| `type` | `true` | default → **override thành `/{slug}/` không prefix** | ✅ |

> Doc cũ ghi `dev` và `pub` là hierarchical `true` — **sai**, cả hai là `false`.

### Của `emulator`
| Taxonomy | `hierarchical` | Rewrite slug |
|---|---|---|
| `emulator_console` | `true` | `emulator-console` → **override thành `/emulators/{slug}`** |
| `os` | `true` | default |

---

## 3. ACF Field Groups

Tất cả đăng bằng `acf_add_local_field_group()`. Field key ở theme này **không** dùng
tiền tố `field_` kiểu ACF UI — key thường trùng luôn với name.

### 3.1 `rom_settings` — `setting/setting-rom.php:210`
**Location:** post_type `rom` **OR** post_type `emulator` (dùng chung cho cả 2 CPT)

| Key / Name | Type | Default | Required | Ghi chú |
|---|---|---|---|---|
| `hide` | select | `public` | — | **Chỉ 2 choice: `public`, `restricted`** |
| `file_size` | text | — | 0 | |
| `views` | number | `0` | — | ghi bởi `tracking/flush.php` |
| `downloads` | number | `0` | — | ghi bởi `tracking/flush.php` |
| `rating_count` | number | **`0`** | — | doc cũ ghi `1` — sai |
| `rating_avg` | number | **`0`** | — | doc cũ ghi `5` — sai |
| `hide_ads` | true_false | — | — | |
| `released` | textarea | — | — | |
| `related_posts` | post_object | — | — | `multiple: 1`, `return_format: id` |
| `internal_note` | textarea | — | — | |
| `image` | gallery | — | — | screenshots |
| `grabber_gallery` | group | — | — | sub: `grabber_input` (text), `grabber_button` (text, default `Grab Images`) |
| `grabber_file` | group | — | — | sub: `grb_file_name`, `grb_file_url`, `grabber_file_button` (default `Grab Files` — ⚠️ textdomain sai: `romspure` thay vì `romsfun`) |
| `download_note` | textarea | — | — | |
| `download_list` | repeater | — | — | xem § 3.8 |
| `download_list_aff` | repeater | — | — | link affiliate, xem § 3.8 |

#### Field `hide` — mô hình 2 giá trị
```php
'choices' => array(
    'public'     => 'Public',
    'restricted' => 'Restricted',
)
```
❌ Doc cũ ghi 3 giá trị `public` / `download` ("Hide Download PC") / `all` ("404 PC").
Mô hình đó **đã bỏ**. `restricted` nay nghĩa là **chỉ member active tải được**, gate
bằng `rf_is_restricted()` + `GET rf/v1/download-url`. Giá trị legacy còn trong DB
được coi là public.

### 3.2 `console_settings` — `setting/setting-rom.php:547`
**Location:** taxonomy `console`

| Key / Name | Type | Default | Ghi chú |
|---|---|---|---|
| `cat_image` | image | — | `return_format: url` |
| `console_original_slug` | text | — | **driver của URL rewrite ROM** — sửa phải flush rewrite |
| `console_rom_downloads` | number | `1` | aggregate, cộng bởi `tracking/flush.php` |
| `console_guide` | wysiwyg | — | |

### 3.3 `region_settings` — `setting/setting-rom.php:601`
**Location:** taxonomy `region` · `region_image` (image)

### 3.4 `collection_settings` — `setting/setting-rom.php:628`
**Location:** taxonomy `collection` · `collection_banner` (image)

### 3.5 `emulator_console_settings` — `setting/setting-emulator.php:87`
**Location:** taxonomy `emulator_console`

| Key / Name | Type | Default | Ghi chú |
|---|---|---|---|
| `emulator_console_image` | image | — | `return_format: url`, hiện ở column |
| `emulator_console_downloads` | number | `0` | aggregate, cộng bởi `tracking/flush.php` |

### 3.6 `post_settings` — `setting/setting-post.php:6`
**Location:** post_type `post` · `views` (number, default `0`, step 1)

### 3.7 `home_settings` — `setting/setting-page-home.php:9`
**Location:** page template `page-home.php`
Field duy nhất: `home_content` (**flexible_content**)

| Layout | Sub-fields |
|---|---|
| `home_console_groups` | `home_console_groups_title` (text, required) |
| `home_intro` | `home_intro_title` (text, required), `home_intro_desc` (wysiwyg, required) |
| `home_roms_collection` | `home_roms_collection_title` (text), `home_roms_collection_items` (taxonomy `collection`, multi), `home_roms_collection_link` (link) |
| `home_roms` | `home_roms_title` (text), `home_roms_number` (number, default `8`), `home_roms_sort` (select: `latest` \| `popular`), `home_roms_layout` (select: `carousel` \| `list`) |
| `home_new_posts` | `home_new_posts_title` (text), `home_new_posts_number` (number, default `10`), `home_new_posts_link` (link) |

> Phần này **khớp** bản doc cũ — không drift.

### 3.8 Repeater `download_list` / `download_list_aff`
| Sub-field | Type | Required |
|---|---|---|
| `name` | text | ✅ |
| `url` | url | ✅ |
| `file_size` | text | — |
| `file_type` | text | — |

**Index là 1-based ở URL download.** `rf_build_download_url()` và `download.php` merge
`download_list` + `download_list_aff` (theo thứ tự đó) rồi index vào mảng đã merge ⇒
**đổi thứ tự / số lượng item sẽ làm lệch mọi URL `/download/.../{n}` đã chia sẻ**.

---

## 4. ACF Options Pages

| Page title | `menu_slug` | `post_id` | Capability | Position | Nơi đăng |
|---|---|---|---|---|---|
| Configs | `options` | `option` | `edit_posts` | 3.3 | `inc/options.php:20` |
| Report | `report` | `report` | `manage_options` | 3.1 | `auth/admin-report.php:14` |
| Console Group | `console-group` | (default) | — | child của `edit.php?post_type=rom` | `setting/setting-console-group.php` |

### 4.1 `options_settings` — `inc/options.php:37` (`post_id: option`)
Chia theo tab:

| Tab | Key / Name | Type | Ghi chú |
|---|---|---|---|
| General (`tab_general`) | `logo` | image | `return_format: url` |
| | `favicon` | image | |
| | `social_networks` | repeater | sub: `social_network_icon`, `social_network_url` |
| | `copyright` | — | |
| Ads (`tab_ads`) | `ads_banner` | — | |
| JS (`tab_js`) | `header_js` | — | inject `<head>` |
| | `footer_js` | — | inject footer |
| Affs (`tab_affs`) | `affs` | repeater | sub: `aff_host`, `aff_code` |
| Report (`tab_page_report`) | `page_report_issues` | repeater | sub: `page_report_issue` — **danh sách loại report cho `POST rf/v1/report`** |
| Homepage (`tab_homepage`) | `home_collections` | — | |
| ROM links (`tab_rom_links`) | `rom_links_console` | — | chọn console để export |
| | `rom_links_export_button` | — | trigger `wp_ajax_romsfun_export_rom_links` |

**`affs` được dùng ở 3 nơi** để chèn aff code theo host: `download.php`,
`inc/ajax.php` (`k_get_download`), `auth/actions/download.php:43`
(`rf_build_download_url`). Match bằng `strpos($link_url, $a['aff_host']) !== false`,
lấy **aff đầu tiên khớp** rồi `break`.

### 4.2 `console_group_fields` — `setting/setting-console-group.php:7`
**Location:** options_page `console-group` · hook `acf/include_fields`

| Key | Name | Type | Required | Ghi chú |
|---|---|---|---|---|
| `console_groups` | `title` | repeater | — | ⚠️ key và name **lệch nhau** (`console_groups` vs `title`) |
| `console_groups_title` | `title` | text | ✅ | sub |
| `console_groups_consoles` | `consoles` | taxonomy | ✅ | sub · taxonomy `console`, `multi_select`, `return_format: id`, `hide_empty: true` |

### 4.3 Report field group — `auth/admin-report.php:31`
⚠️ Group này được đăng **rỗng**: chỉ có `location`, **không có `key` và không có
`fields`**. Nội dung trang Report thực tế được render bằng hook
`toplevel_page_report` (priority 5 và 20) đọc bảng `{prefix}member_report`.
Các loại report (`page_report_issues`) nằm ở options page `options`, không ở đây.

---

## 5. Post meta chính (ngoài ACF)

| Meta key | Post type | Ai ghi |
|---|---|---|
| `views` | `rom`, `emulator`, `post` | `tracking/flush.php`, legacy `k_update_post_views` |
| `downloads` | `rom`, `emulator` | `tracking/flush.php` |
| `rating_count`, `rating_avg` | `rom`, `emulator` | `POST rf/v1/rating` (cộng dồn) |
| `hide` | `rom`, `emulator` | admin toggle / bulk / `inc/utility.php:655` |
| `_wp_page_template` | `page` | WP core — breadcrumb & nav-walker query theo meta này |

## 6. Term meta chính

| Meta key | Taxonomy | Ai ghi |
|---|---|---|
| `console_original_slug` | `console` | admin (ACF) — **ảnh hưởng rewrite** |
| `console_rom_downloads` | `console` | `tracking/flush.php:184` |
| `emulator_console_downloads` | `emulator_console` | `tracking/flush.php:184` |
| `cat_image`, `console_guide` | `console` | admin |
| `region_image` | `region` | admin |
| `collection_banner` | `collection` | admin |

---

## 7. Bảng custom (member subsystem)

`auth/setting-member.php` — `RF_MEMBER_DB_VERSION = '7'`.
Mọi `DATETIME` theo format MySQL `YYYY-MM-DD HH:MM:SS`.

| Bảng | Accessor | PK | Vai trò |
|---|---|---|---|
| `{prefix}member` | `rf_member_table()` | `id` | nguồn sự thật về member (**không** dùng `wp_users`) |
| `{prefix}member_comments` | `rf_member_comments_table()` | `comment_id` | index "my comments" |
| `{prefix}member_wishlist` | `rf_member_wishlist_table()` | `(member_id, post_id)` | wishlist, cap 30 ở endpoint |
| `{prefix}member_report` | `rf_member_report_table()` | `id` | report, audit log (không cleanup theo post) |
| `{prefix}member_rating` | `rf_member_rating_table()` | `id` | review; `UNIQUE(member_id, post_id)` |
| `{prefix}member_ticket` | `rf_ticket_table()` | `id` | request; `UNIQUE(open_slot)` = 1 ticket mở/member |
| `{prefix}member_contact` | `rf_member_contact_table()` | `id` | hộp thư gửi admin |

Cột, index và lý do thiết kế từng bảng: [MEMBER_SYSTEM.md § 6](./MEMBER_SYSTEM.md).

### ❌ Bảng cũ không còn dùng
`{prefix}user_report` — doc cũ ghi `handle_report_form` insert vào đây. Endpoint đó đã
bị xoá; report nay vào `{prefix}member_report` qua `POST rf/v1/report`.

### Enum lưu dạng số

`member_ticket.status` (`rf_ticket_statuses()`):
| 0 | 1 | 2 | 3 | 4 |
|---|---|---|---|---|
| Init | Processing | Solved | Closed *(terminal)* | Cancelled *(terminal, member-only)* |

`member_ticket.request_type` (`rf_ticket_types()`):
| 0 | 1 | 2 |
|---|---|---|
| Add new game | Add / Update file for existing game | Other |

`member_rating.status`: `1` = hiện, `0` = admin ẩn.
`member.blocked`: `1` = bị chặn (re-check ở mọi endpoint).

---

## 8. Redis keys

| Key | Chủ sở hữu | TTL | Ghi chú |
|---|---|---|---|
| `rftrk:pending_views` | `tracking/` | **không TTL** | hash `{post_id => delta}` |
| `rftrk:pending_downloads` | `tracking/` | **không TTL** | hash `{post_id => delta}` |
| `rftrk:*:flushing` | `tracking/flush.php` | — | bản copy đang drain (RENAME) |
| `rftrk:last_flush` | `tracking/flush.php` | — | heartbeat timestamp |
| `{WP_REDIS_PREFIX}rf_sess:{id}` | `auth/member-core.php` | `RF_SESSION_TTL` = 7 ngày | session member |
| `{WP_REDIS_PREFIX}rf_action_rl:{member_id}` | `auth/member-core.php` | `RF_ACTION_WINDOW` = 180s | throttle 30 action |
| `{WP_REDIS_PREFIX}rf_google_jwks` | `auth/auth-google.php` | — | cache JWKS Google |

⚠️ **Prefix `rftrk:` cố ý KHÔNG mang `WP_REDIS_PREFIX`** (mặc định `romsfun_`): plugin
Redis Object Cache flush bằng `SCAN MATCH 'romsfun_*' + DEL`, sẽ xoá sạch count chưa về DB.

## 9. Transient

| Transient | TTL | Nơi |
|---|---|---|
| `post_ids_of_{term_id}` | `DAY_IN_SECONDS` | `template-parts/rom-filter.php` |
| `member:{id}:wishlist` | `4 * HOUR_IN_SECONDS` | `auth/actions/wishlist.php` |
| `member:{id}:tickets` | `DAY_IN_SECONDS` | `auth/actions/ticket.php` |
| `member:{id}:contacts` | `DAY_IN_SECONDS` | `auth/actions/contact.php` |
| ~~`romsfun_site_options`~~ | ~~`3 * DAY_IN_SECONDS`~~ | `caching/ConfigCacheManager.php` **đã xoá 2026-10-08** (không được require ở đâu) → transient này không còn tồn tại; option đọc trực tiếp bằng `get_field('<key>', 'option')` |

Convention key per-member: `rf_member_cache_key( $entity, $member_id )` → `member:{id}:{entity}`.

## 10. Option (wp_options)

| Option | Nơi | Ghi chú |
|---|---|---|
| `rf_member_db_version` | `auth/setting-member.php` | hiện `'7'` |
| `rf_member_pages_rw` | `auth/member-pages.php` | hiện `'3'` — **bump khi thêm route member** |

---

## 11. Bề mặt endpoint

### 11.1 REST `rf/v1` — 18 route
Đầy đủ (method, file, origin guard, caps, error code):
[MEMBER_SYSTEM.md § 8](./MEMBER_SYSTEM.md).

Tóm tắt: `/login`, `/logout`, `/comment`, `/my-comments`, `/wishlist` (GET+POST),
`/download-url`, `/report`, `/my-reports`, `/my-stats`, `/rating`, `/ratings`,
`/my-ratings`, `/ticket`, `/my-tickets`, `/ticket/close`, `/contact`, `/my-contacts`.

### 11.2 `admin-ajax.php` public — chỉ còn 3

| Action | Function | Auth | Nonce | Rate limit |
|---|---|---|---|---|
| `k_update_post_views` | `k_update_post_views` | — | ❌ | ❌ |
| `k_get_download` | `k_get_download` | — | ❌ | ❌ (có check `rf_is_restricted`) |
| `get_download_token` | `handle_get_download_token` | — | ❌ | ❌ |

### 11.3 ❌ Endpoint public đã bị xoá
Doc cũ liệt kê và **không còn tồn tại trong code**:

| Action cũ | Thay bằng |
|---|---|
| `k_comment` (nonce `k_comment_action`, IP rate limit 5/180s) | `POST rf/v1/comment` |
| `k_rating` (nonce `k_rating_action`, cookie 1 năm) | `POST rf/v1/rating` |
| `handle_report_form` (nonce `report_form_action`, cookie 5 phút, insert `{prefix}user_report`) | `POST rf/v1/report` |
| `get_nonce` (trả nonce theo action type) | không còn cần — auth bằng session + `X-RF-Auth` |

Hàm `should_load_dynamic_nonce()` (`inc/utility-post-type.php:65`) vẫn tồn tại và vẫn
localize `enable_dynamic_nonce` cho `main-js`, nhưng **không còn endpoint `get_nonce`**
để phục vụ.

### 11.4 Chuẩn validate / sanitize
`caching/AppSecurity.php` (static):
`validate_input()` (đệ quy, giới hạn depth), `clean_text_input()`, `validate_number()`,
`get_client_ip()` (verify dải IP Cloudflare trước khi tin `HTTP_CF_CONNECTING_IP`),
`set_security_headers()`. Mọi lối thoát lỗi đi qua một responder JSON duy nhất
(`send_error()`).

---

## 12. Lưu ý khi đọc tài liệu này

1. `inc/acf/` và `inc/acf-quick-edit-fields/` là **vendor** — mọi `acf_add_local_field_group`
   trong đó không phải field của theme.
2. ACF được bundle nhưng dòng include bị comment (`functions.php:261-262`) → theme chạy
   với ACF dạng **plugin**. `acf/settings/show_admin` bị `__return_false` nên không thấy
   menu Field Groups trong admin.
3. Field key ở theme này không theo convention `field_xxx` của ACF UI ⇒ **không export
   JSON từ admin rồi import đè**, sẽ sinh key trùng.
4. Mọi field aggregate (`views`, `downloads`, `console_rom_downloads`,
   `emulator_console_downloads`) bị **ghi bởi cron** (`tracking/flush.php`). Sửa tay trong
   admin sẽ bị cộng dồn tiếp, không bị ghi đè.
