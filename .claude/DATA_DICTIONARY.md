# DATA_DICTIONARY.md — romsvault Data Architecture

**Traced:** 2026-10-08 từ `setting/*.php`, `inc/options.php`, `auth/setting-member.php`

> Tham chiếu dùng dạng `file.php:symbol()` thay vì số dòng — số dòng rot sau mỗi lần sửa.

Doc liên quan: [ARCHITECTURE.md](./ARCHITECTURE.md) · [MEMBER_SYSTEM.md](./MEMBER_SYSTEM.md)

---

## 1. Custom Post Types

Theme có đúng **2 CPT**.

### `rom` — `setting/setting-rom.php:k_init_rom()`
| Property | Value |
|---|---|
| `public` | `true` |
| `has_archive` | `false` |
| `rewrite` | `false` — URL dựng bằng filter |
| `supports` | `title`, `editor`, `thumbnail` |
| `show_in_rest` | `true` |

**Taxonomies:** `console`, `collection`, `region`, `genre`, `dev`, `pub`, `type`

### `emulator` — `setting/setting-emulator.php:k_init_emulator()`
| Property | Value |
|---|---|
| `public` | `true` |
| `has_archive` | `false` |
| `rewrite` | `slug: emulators/%console%` (tag `%console%` chưa register — dead) |
| `supports` | `title`, `editor`, `thumbnail`, `comments` |
| `show_in_rest` | `true` |

**Taxonomies:** `emulator_console`, `os`

---

## 2. Taxonomies

### Của `rom`
| Taxonomy | `hierarchical` | Rewrite slug | REST |
|---|---|---|---|
| `console` | `true` | `roms` (`with_front: false`) | ✅ |
| `collection` | `true` | `collection` | — |
| `region` | `true` | default | — |
| `genre` | `true` | default | — |
| `dev` | `false` | default | — |
| `pub` | `false` | default | — |
| `type` | `true` | default → **override thành `/{slug}/` không prefix** | ✅ |

### Của `emulator`
| Taxonomy | `hierarchical` | Rewrite slug |
|---|---|---|
| `emulator_console` | `true` | `emulator-console` → **override thành `/emulators/{slug}`** |
| `os` | `true` | default |

`console` có `'default_term' => ['name' => 'Unknown', 'slug' => 'unknown']` ⇒ **mọi `rom`
luôn mang ít nhất một console term**, trên mọi đường ghi (editor, quick/bulk edit, REST,
grabber). Lý do và caveat: [ARCHITECTURE.md § 4.3](./ARCHITECTURE.md).

---

## 3. ACF Field Groups

Tất cả đăng bằng `acf_add_local_field_group()`. Field key ở theme này **không** dùng
tiền tố `field_` kiểu ACF UI — key thường trùng luôn với name.

### 3.1 `rom_settings` — `setting/setting-rom.php`
**Location:** post_type `rom` **OR** post_type `emulator` — dùng chung cho cả 2 CPT.

| Key / Name | Type | Default | Required | Ghi chú |
|---|---|---|---|---|
| `hide` | select | `public` | — | 2 choice: `public`, `restricted` (§ 3.1.1) |
| `file_size` | text | — | — | |
| `views` | number | `0` | — | ghi bởi `tracking/flush.php` |
| `downloads` | number | `0` | — | ghi bởi `tracking/flush.php` |
| `rating_count` | number | `0` | — | cộng dồn bởi `POST rf/v1/rating` |
| `rating_avg` | number | `0` | — | cộng dồn bởi `POST rf/v1/rating` |
| `hide_ads` | true_false | — | — | |
| `released` | textarea | — | — | |
| `related_posts` | post_object | — | — | `multiple: 1`, `return_format: id` |
| `internal_note` | textarea | — | — | |
| `image` | gallery | — | — | screenshots |
| `grabber_gallery` | group | — | — | sub: `grabber_input` (text), `grabber_button` (text, default `Grab Images`) |
| `grabber_file` | group | — | — | sub: `grb_file_name`, `grb_file_url`, `grabber_file_button` |
| `download_note` | textarea | — | — | |
| `download_list` | repeater | — | — | § 3.8 |
| `download_list_aff` | repeater | — | — | link affiliate, § 3.8 |

#### 3.1.1 Field `hide`

```php
'choices' => array(
    'public'     => 'Public',
    'restricted' => 'Restricted',
)
```

`restricted` = **chỉ member active tải được**. Gate thật ở server:
`rf_is_restricted()` → `GET rf/v1/download-url` trả `401` / `403`.
`rf_is_restricted()` chỉ `true` khi giá trị **đúng bằng** `'restricted'`; mọi giá trị
khác trong DB đều coi là public.

### 3.2 `console_settings` — `setting/setting-rom.php`
**Location:** taxonomy `console`

| Key / Name | Type | Default | Ghi chú |
|---|---|---|---|
| `cat_image` | image | — | `return_format: url` |
| `console_original_slug` | text | — | **driver của URL rewrite ROM** — sửa phải flush rewrite |
| `console_rom_downloads` | number | `1` | aggregate, cộng bởi `tracking/flush.php` |
| `console_guide` | wysiwyg | — | |

### 3.3 `region_settings` — `setting/setting-rom.php`
**Location:** taxonomy `region` · `region_image` (image)

### 3.4 `collection_settings` — `setting/setting-rom.php`
**Location:** taxonomy `collection` · `collection_banner` (image)

### 3.5 `emulator_console_settings` — `setting/setting-emulator.php`
**Location:** taxonomy `emulator_console`

| Key / Name | Type | Default | Ghi chú |
|---|---|---|---|
| `emulator_console_image` | image | — | `return_format: url`, hiện ở admin column |
| `emulator_console_downloads` | number | `0` | aggregate, cộng bởi `tracking/flush.php` |

### 3.6 `post_settings` — `setting/setting-post.php`
**Location:** post_type `post` · `views` (number, default `0`, step 1)

### 3.7 `home_settings` — `setting/setting-page-home.php`
**Location:** page template `page-home.php`
Field duy nhất: `home_content` (**flexible_content**)

| Layout | Sub-fields |
|---|---|
| `home_console_groups` | `home_console_groups_title` (text, required) |
| `home_intro` | `home_intro_title` (text, required), `home_intro_desc` (wysiwyg, required) |
| `home_roms_collection` | `home_roms_collection_title` (text), `home_roms_collection_items` (taxonomy `collection`, multi), `home_roms_collection_link` (link) |
| `home_roms` | `home_roms_title` (text), `home_roms_number` (number, default `8`), `home_roms_sort` (select: `latest` \| `popular`), `home_roms_layout` (select: `carousel` \| `list`) |
| `home_new_posts` | `home_new_posts_title` (text), `home_new_posts_number` (number, default `10`), `home_new_posts_link` (link) |

### 3.8 Repeater `download_list` / `download_list_aff`
| Sub-field | Type | Required |
|---|---|---|
| `name` | text | ✅ |
| `url` | url | ✅ |
| `file_size` | text | — |
| `file_type` | text | — |

**Index ở URL download là 1-based.** `rf_build_download_url()` và `download.php` merge
`download_list` + `download_list_aff` (đúng thứ tự đó) rồi index vào mảng **đã merge** ⇒
**đổi thứ tự hoặc số lượng item sẽ làm lệch mọi URL `/download/.../{n}` đã chia sẻ.**

---

## 4. ACF Options Pages

| Page title | `menu_slug` | `post_id` | Capability | Position | Nơi đăng |
|---|---|---|---|---|---|
| Configs | `options` | `option` | `edit_posts` | 3.3 | `inc/options.php` |
| Report | `report` | `report` | `manage_options` | 3.1 | `auth/admin-report.php` |
| Console Group | `console-group` | (default) | — | child của `edit.php?post_type=rom` | `setting/setting-console-group.php` |

### 4.1 `options_settings` — `inc/options.php` (`post_id: option`)
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
| | `rom_links_export_button` | — | trigger `wp_ajax_rom_export_rom_links` |

**`affs` được đọc ở 3 nơi** để chèn aff code theo host: `download.php`,
`inc/ajax.php:k_get_download()`, `auth/actions/download.php:rf_build_download_url()`.
Match bằng `strpos($link_url, $a['aff_host']) !== false`, lấy **aff đầu tiên khớp** rồi
`break`.

### 4.2 `console_group_fields` — `setting/setting-console-group.php`
**Location:** options_page `console-group` · hook `acf/include_fields`

| Key | Name | Type | Required | Ghi chú |
|---|---|---|---|---|
| `console_groups` | `title` | repeater | — | ⚠️ key và name **lệch nhau** |
| `console_groups_title` | `title` | text | ✅ | sub |
| `console_groups_consoles` | `consoles` | taxonomy | ✅ | sub · taxonomy `console`, `multi_select`, `return_format: id`, `hide_empty: true` |

### 4.3 Report field group — `auth/admin-report.php`
⚠️ Group này đăng **rỗng**: chỉ có `location`, **không có `key` và không có `fields`**.
Nội dung trang Report thực tế render bằng hook `toplevel_page_report` (priority 5 và 20),
đọc bảng `{prefix}member_report`. Danh sách loại report (`page_report_issues`) nằm ở
options page `options`, không ở đây.

---

## 5. Post meta chính

| Meta key | Post type | Ai ghi |
|---|---|---|
| `views` | `rom`, `emulator`, `post` | `tracking/flush.php` (+ legacy `k_update_post_views`) |
| `downloads` | `rom`, `emulator` | `tracking/flush.php` |
| `rating_count`, `rating_avg` | `rom`, `emulator` | `POST rf/v1/rating` (cộng dồn) |
| `hide` | `rom`, `emulator` | admin toggle / bulk action / `inc/utility.php` |
| `_wp_page_template` | `page` | WP core — breadcrumb & nav-walker query theo meta này |

## 6. Term meta chính

| Meta key | Taxonomy | Ai ghi |
|---|---|---|
| `console_original_slug` | `console` | admin (ACF) — **ảnh hưởng rewrite rules** |
| `console_rom_downloads` | `console` | `tracking/flush.php:rftrk_add_term_meta()` |
| `emulator_console_downloads` | `emulator_console` | `tracking/flush.php:rftrk_add_term_meta()` |
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
| `{prefix}member_report` | `rf_member_report_table()` | `id` | report — audit log, không cleanup theo post |
| `{prefix}member_rating` | `rf_member_rating_table()` | `id` | review; `UNIQUE(member_id, post_id)` |
| `{prefix}member_ticket` | `rf_ticket_table()` | `id` | request; `UNIQUE(open_slot)` = 1 ticket mở/member |
| `{prefix}member_contact` | `rf_member_contact_table()` | `id` | hộp thư gửi admin |

Cột, index và lý do thiết kế từng bảng: [MEMBER_SYSTEM.md § 6](./MEMBER_SYSTEM.md).

### Enum lưu dạng số

`member_ticket.status` — `rf_ticket_statuses()`:
| 0 | 1 | 2 | 3 | 4 |
|---|---|---|---|---|
| Init | Processing | Solved | Closed *(terminal)* | Cancelled *(terminal, member-only)* |

`member_ticket.request_type` — `rf_ticket_types()`:
| 0 | 1 | 2 |
|---|---|---|
| Add new game | Add / Update file for existing game | Other |

`member_rating.status`: `1` = hiện, `0` = admin ẩn.
`member.blocked`: `1` = bị chặn — re-check ở **mọi** endpoint.

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

`WP_REDIS_PREFIX` hiện là `romsvault_` (`wp-config.php`).

> ⚠️ Prefix `rftrk:` **cố ý KHÔNG** mang `WP_REDIS_PREFIX`. Plugin Redis Object Cache
> flush bằng `SCAN MATCH 'romsvault_*' + DEL` → sẽ xoá sạch count chưa về DB.

## 9. Transient

| Transient | TTL | Nơi |
|---|---|---|
| `post_ids_of_{term_id}` | `DAY_IN_SECONDS` | `template-parts/rom-filter.php` |
| `member:{id}:wishlist` | `4 * HOUR_IN_SECONDS` | `auth/actions/wishlist.php` |
| `member:{id}:tickets` | `DAY_IN_SECONDS` | `auth/actions/ticket.php` |
| `member:{id}:contacts` | `DAY_IN_SECONDS` | `auth/actions/contact.php` |

Convention key per-member: `rf_member_cache_key( $entity, $member_id )` →
`member:{id}:{entity}`. Theme option **không** có tầng cache riêng — đọc trực tiếp bằng
`get_field('<key>', 'option')`.

## 10. Option (wp_options)

| Option | Nơi | Ghi chú |
|---|---|---|
| `rf_member_db_version` | `auth/setting-member.php` | hiện `'7'` |
| `rf_member_pages_rw` | `auth/member-pages.php` | hiện `'3'` — **bump khi thêm route member** |
| `default_term_console` | WP core (từ `register_taxonomy()`) | `term_id` của console `unknown` |

---

## 11. Bề mặt endpoint

### 11.1 REST `rf/v1` — 18 route
Đầy đủ (method, file, origin guard, caps, error code):
[MEMBER_SYSTEM.md § 8](./MEMBER_SYSTEM.md).

Tóm tắt: `/login`, `/logout`, `/comment`, `/my-comments`, `/wishlist` (GET+POST),
`/download-url`, `/report`, `/my-reports`, `/my-stats`, `/rating`, `/ratings`,
`/my-ratings`, `/ticket`, `/my-tickets`, `/ticket/close`, `/contact`, `/my-contacts`.

### 11.2 `admin-ajax.php` — public (`nopriv`), 3 action

| Action | Function | Nonce | Rate limit |
|---|---|---|---|
| `k_update_post_views` | `k_update_post_views()` | ❌ | ❌ |
| `k_get_download` | `k_get_download()` | ❌ | ❌ — nhưng **có** check `rf_is_restricted()` |
| `get_download_token` | `handle_get_download_token()` | ❌ | ❌ |

Comment, rating và report **không** đi qua `admin-ajax.php` — tất cả là REST `rf/v1`,
auth bằng session + header `X-RF-Auth`.

> ⚠️ `should_load_dynamic_nonce()` (`inc/utility-post-type.php`) vẫn chạy và vẫn localize
> `enable_dynamic_nonce` cho `main-js`, nhưng **không có endpoint nào cấp nonce động** —
> cờ này hiện không phục vụ gì.

### 11.3 Chuẩn validate / sanitize
`caching/AppSecurity.php` (static): `validate_input()` (đệ quy, giới hạn depth),
`clean_text_input()`, `validate_number()`, `get_client_ip()` (verify dải IP Cloudflare
trước khi tin `HTTP_CF_CONNECTING_IP`), `set_security_headers()`.
Mọi lối thoát lỗi đi qua **một** responder JSON duy nhất (`send_error()`).

---

## 12. Lưu ý khi làm việc với ACF ở theme này

1. `inc/acf/` và `inc/acf-quick-edit-fields/` là **vendor** — mọi
   `acf_add_local_field_group()` trong đó không phải field của theme.
2. ACF được bundle nhưng dòng include bị comment trong `functions.php` → theme chạy với
   ACF dạng **plugin**. `acf/settings/show_admin` bị `__return_false` nên không thấy menu
   Field Groups trong admin.
3. Field key ở theme này không theo convention `field_xxx` của ACF UI ⇒ **không export
   JSON từ admin rồi import đè**, sẽ sinh key trùng.
4. Mọi field aggregate (`views`, `downloads`, `console_rom_downloads`,
   `emulator_console_downloads`) bị **ghi bởi cron** (`tracking/flush.php`). Sửa tay
   trong admin sẽ bị cộng dồn tiếp, không bị ghi đè.
