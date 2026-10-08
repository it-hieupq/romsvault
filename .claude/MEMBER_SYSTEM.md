# MEMBER_SYSTEM.md — romsvault Member Subsystem

**Traced:** 2026-10-08 từ `auth/` (19 PHP + `auth/js/auth.js`, ~6.4k dòng).
**Nguồn chuẩn:** `functions.php` include map → `auth/*`. Toàn bộ subsystem nằm trong
`auth/`, không có phần nào ở `inc/`.

Doc liên quan: [ARCHITECTURE.md](./ARCHITECTURE.md) · [DATA_DICTIONARY.md](./DATA_DICTIONARY.md)

---

## 1. Nguyên tắc nền: cache-safe

Front-end được Cloudflare/APO cache. Hệ quả thiết kế:

- **Không dùng `wp_users`.** Member là bảng riêng `{prefix}member`, khóa theo `google_sub`.
- **Không bao giờ set WP auth cookie.** Session cookie `rf_member` scope `/wp-json/rf/v1`
  nên không bao giờ chạm HTML đã cache.
- **Không `is_user_logged_in()` trong template.** PHP render state khách; JS gắn
  `html.rf-authed` rồi hoán đổi UI. Các class kiểm soát: `rf-authed`, `rf-guest-only`,
  `rf-inactive-only`.
- Mọi trang member (`/wishlist`, `/profile`, `/request`) đều `noindex`
  (`wp_robots_no_robots`) vì nội dung per-user.

---

## 2. Thứ tự load (bắt buộc)

`functions.php` require theo đúng thứ tự này — `member-core.php` phải trước mọi thứ:

```
auth/setting-member.php     → schema + bảng + enum + cache key convention
auth/member-core.php        → session, origin guard, current member, throttle, restricted
auth/auth-google.php        → POST /login, POST /logout
auth/actions/*.php          → 8 module endpoint (18 route)
auth/member-ui.php          → member menu + restricted notice
auth/member-pages.php       → rewrite /wishlist /profile /request
is_admin() → auth/admin-*.php (6 trang)
```

---

## 3. Luồng đăng nhập (Google ID token, dependency-free)

`auth/auth-google.php`

1. Client dùng Google Identity Services, lấy **ID token** (`credential`).
   Client id lấy từ `GOOGLE_CLIENT_ID` (wp-config), localize qua `RF_AUTH.clientId`.
   `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URI` **không dùng** trong luồng này.
2. `POST rf/v1/login` với `{ credential, nonce }`.
3. `rf_verify_google_id_token()` xác thực **không phụ thuộc thư viện ngoài**:
   - `alg === RS256`, có `kid`
   - chữ ký kiểm với **Google JWKS** `RF_GOOGLE_JWKS_URL`
     = `https://www.googleapis.com/oauth2/v3/certs`, cache trong Redis
     (`{WP_REDIS_PREFIX}rf_google_jwks`); `kid` lạ → force refresh đúng **1 lần** (key rotation)
   - `aud === GOOGLE_CLIENT_ID`
   - `iss ∈ {accounts.google.com, https://accounts.google.com}`
   - `exp` còn hạn, `iat` tươi trong `RF_IAT_LEEWAY` = **300s** (chống replay)
   - `email_verified`, có `sub`
   - JWKS không lấy được → `503 jwks_unavailable` (không fail-open)
4. Upsert `{prefix}member` theo `google_sub`, cập nhật `last_login_at`.
5. Tạo session Redis, set cookie, trả về member payload.

JWK → PEM được tự dựng bằng DER encoding thủ công (`rf_der_len`, `rf_der_uint`,
`rf_jwk_rsa_to_pem`) — không cần `firebase/php-jwt` hay composer.

---

## 4. Session (Redis-backed, không phải WP)

`auth/member-core.php`

| Hằng | Giá trị | Ghi chú |
|---|---|---|
| `RF_SESSION_TTL` | `7 * DAY_IN_SECONDS` | override được ở wp-config |
| `RF_SESSION_COOKIE` | `rf_member` | |
| `RF_SESSION_PATH` | `/wp-json/rf/v1` | **cố ý** hẹp — không chạm HTML cached |

- Session id = `bin2hex(random_bytes(32))` → opaque 256-bit.
- Redis key: `{WP_REDIS_PREFIX}rf_sess:{id}`.
- Cookie: `HttpOnly`, `SameSite=Lax`, `Secure` khi `is_ssl()`.
- `rf_redis()` là connection **riêng**, độc lập plugin Redis Object Cache —
  bật/tắt plugin đó **không** làm logout member. Trả `null` (không throw) khi
  phpredis thiếu hoặc Redis chết.
- Redis chết lúc login → lỗi mềm, **không** fallback sang WP cookie.
- Không có session index: block member dựa vào re-check `member.blocked` ở từng
  endpoint + TTL hết hạn.

---

## 5. Bảo vệ request

### 5.1 Origin guard (CSRF)

`rf_verify_request_origin()` — true chỉ khi:
- header `X-RF-Auth: 1` (JS cross-site không set được header custom), **và**
- host của `Origin` (fallback `Referer`) khớp host của `home_url()`.

Mọi endpoint gọi guard này **trừ** `GET /download-url` (dựa vào `SameSite=Lax`).

### 5.2 Vá xung đột với WP REST nonce

`rest_authentication_errors` priority **101**: nếu lỗi là `rest_cookie_invalid_nonce`
và URI chứa `rf/v1/` → bỏ qua. Lý do: staff đang đăng nhập wp-admin gửi kèm WP auth
cookie tới `/wp-json`, core reject trước khi handler chạy → member-cũng-là-staff bị
401 giả. Vá này an toàn vì handler vẫn tự kiểm same-origin + `X-RF-Auth` + session.

### 5.3 Throttle

`rf_check_action_rate( $member_id )` — **một bucket dùng chung** cho mọi action:

| Hằng | Giá trị |
|---|---|
| `RF_ACTION_LIMIT` | 30 action |
| `RF_ACTION_WINDOW` | 180s (≈10/phút bền) |

- Redis key `{WP_REDIS_PREFIX}rf_action_rl:{member_id}`; TTL set **một lần** ở hit đầu
  (fixed window, không trượt).
- Khóa theo `member_id` chứ **không** theo IP (CGNAT).
- **Fail-open** khi Redis chết.

### 5.4 Caps theo feature

| Feature | Hằng | Giá trị | Cơ chế |
|---|---|---|---|
| Wishlist | `RF_WISHLIST_MAX` | 30 item/member | check ở endpoint |
| Report | `RF_REPORT_DAILY_MAX` | 30/ngày | cap + insert **atomic trong 1 câu SQL** |
| Ticket | `RF_TICKET_DAILY_MAX` | 10/ngày | + **1 ticket mở/member** là invariant tầng DB |
| Contact | `RF_CONTACT_DAILY_MAX` | 5/ngày | soft cap bằng COUNT |
| Rating | `RF_RATING_MAXLEN` | 164 ký tự | + UNIQUE `(member_id, post_id)` = 1 review/post |

---

## 6. Schema (7 bảng custom)

`auth/setting-member.php` — `RF_MEMBER_DB_VERSION = '7'`.
Self-healing: chạy ở `after_switch_theme` + guard ở `admin_init`
(`rf_member_maybe_upgrade`). **Front-end không bao giờ trigger** (không ghi DB trên
page load đã cache). Idempotent: `CREATE TABLE IF NOT EXISTS` + check tồn tại index.

Accessor: `rf_member_table()`, `rf_member_comments_table()`, `rf_member_wishlist_table()`,
`rf_member_report_table()`, `rf_member_rating_table()`, `rf_ticket_table()`,
`rf_member_contact_table()`.

### `{prefix}member`
Nguồn sự thật về member (**không** phải `wp_users`).

| Cột | Kiểu | Ghi chú |
|---|---|---|
| `id` | BIGINT UNSIGNED AI | PK |
| `google_sub` | VARCHAR(32) | `UNIQUE uq_google_sub` |
| `email` | VARCHAR(191) | `idx_email` |
| `name`, `avatar_url` | VARCHAR(255/512) NULL | |
| `blocked` | TINYINT(1) = 0 | re-check ở mọi endpoint |
| `created_at` | DATETIME | `idx_created`, `idx_blocked_created` |
| `last_login_at` | DATETIME NULL | |

### `{prefix}member_comments`
Index "my comments" cho nhanh (comment thật vẫn ở `wp_comments`).

`comment_id` PK · `member_id` · `post_id` · `status` VARCHAR(20) · `created_at`
· `idx_member_created`

### `{prefix}member_wishlist`
PK `(member_id, post_id)` chống trùng · `idx_member_created` (list newest-first)
· `idx_post` (fan-out khi post bị xoá/trash — PK không serve được vì `post_id`
không phải cột trái nhất). Cap 30 ở endpoint, **không** ở schema.

### `{prefix}member_report`
`id` PK · `member_id` · `post_id` · `report_type` VARCHAR(191) ·
`report_content` TEXT · `url_report` VARCHAR(128) · `created_at`
· `idx_member_created` (my-reports + dedup window) · `idx_created` (admin list)

`url_report` lưu permalink denormalized → member vẫn thấy report sau khi post bị xoá.
**Không có index theo `post_id`** (report là audit log, không cleanup theo post).

### `{prefix}member_rating`
`id` PK · `member_id` · `post_id` · `score` TINYINT · `content` VARCHAR(191) ·
`status` TINYINT(1)=1 (1 hiện / 0 admin ẩn) · `created_at` · `updated_at`
· `UNIQUE uq_member_post` · `idx_post_status_created` · `idx_member_created`

**Chỉ lưu trữ review.** Sao trung bình vẫn là post_meta `rating_avg`/`rating_count`
tính tăng dần — bảng này không tính average.

### `{prefix}member_ticket`
`id` PK · `member_id` · `request_type` TINYINT · `game_title` VARCHAR(255) ·
`reference_url` VARCHAR(255) · `description` VARCHAR(500) · `response_content` TEXT ·
`status` TINYINT=0 · `closed_by` TINYINT NULL · `created_at` · `updated_at`

```sql
open_slot BIGINT UNSIGNED AS (CASE WHEN status IN (0,1,2) THEN member_id END) STORED
UNIQUE KEY uq_open_slot (open_slot)
```

→ "tối đa 1 ticket mở / member" là **invariant tầng DB**: 2 request create đồng thời
thì cái thứ 2 trúng duplicate key. Vì không tạo mới được khi chưa đóng cái cũ, nó cũng
chặn luôn tốc độ tạo. Index: `idx_member_created`, `idx_status_created`.

### `{prefix}member_contact`
`id` PK · `member_id` · `title` VARCHAR(64) · `content` VARCHAR(512) ·
`response` TEXT NULL · `created_at` · `updated_at` · `idx_member_created` · `idx_created`

**Không có cột status** — `response` khác NULL *chính là* tín hiệu "admin đã trả lời".

---

## 7. Enum dùng chung

`auth/setting-member.php` là single source of truth, chia sẻ giữa REST layer,
trang `/request` và trang admin.

### `rf_ticket_statuses()`
| Code | Label | |
|---|---|---|
| 0 | Init | mở |
| 1 | Processing | mở |
| 2 | Solved | mở |
| 3 | Closed | **terminal** |
| 4 | Cancelled | **terminal** (chỉ member set được) |

Admin chỉ set 0..3; Cancel là hành động của member.

### `rf_ticket_types()`
| Code | Label |
|---|---|
| 0 | Add new game |
| 1 | Add / Update file for existing game |
| 2 | Other |

### `rf_ticket_status_colors()`
Map màu theo status (`bg` / `text` cho pill, `main` cho stepper fill). Mirror sang
client dạng JSON ở trang `/request`, dùng lại ở bảng admin.

---

## 8. REST API — namespace `rf/v1`

`RF_REST_NS = 'rf/v1'` (`auth/member-core.php`). Base: `/wp-json/rf/v1/`.

**Mọi route dùng `permission_callback => '__return_true'`** — auth được kiểm *trong*
handler (origin guard → session → blocked → throttle → cap). Đây là thiết kế có ý,
không phải lỗ hổng.

| Method | Route | File | Origin guard | Mô tả |
|---|---|---|---|---|
| POST | `/login` | `auth-google.php` | ✅ | Google ID token → session |
| POST | `/logout` | `auth-google.php` | ✅ | hủy session + clear cookie |
| POST | `/comment` | `actions/comment.php` | ✅ | gửi comment (qua `wp_new_comment`) |
| GET | `/my-comments` | `actions/comment.php` | ✅ | comment của mình (default limit 10) |
| GET | `/wishlist` | `actions/wishlist.php` | ✅ | wishlist của mình |
| POST | `/wishlist` | `actions/wishlist.php` | ✅ | idempotent set `{post, on:0\|1}` |
| GET | `/download-url` | `actions/download.php` | ❌ (SameSite=Lax) | **gate** download restricted |
| POST | `/report` | `actions/report.php` | ✅ | report post |
| GET | `/my-reports` | `actions/report.php` | ✅ | report của mình |
| GET | `/my-stats` | `actions/profile.php` | ✅ | số liệu dashboard profile |
| POST | `/rating` | `actions/rating.php` | ✅ | chấm điểm + review |
| GET | `/ratings` | `actions/rating.php` | ✅ | review công khai của 1 post |
| GET | `/my-ratings` | `actions/rating.php` | ✅ | review của mình |
| POST | `/ticket` | `actions/ticket.php` | ✅ | tạo request |
| GET | `/my-tickets` | `actions/ticket.php` | ✅ | request của mình (limit ≤ 50) |
| POST | `/ticket/close` | `actions/ticket.php` | ✅ | member tự Cancel/Close |
| POST | `/contact` | `actions/contact.php` | ✅ | gửi tin nhắn cho admin |
| GET | `/my-contacts` | `actions/contact.php` | ✅ | lịch sử tin nhắn (limit ≤ 50) |

**Error contract:** trả `{ ok: false, error: '<code>', message?: '...' }` + HTTP status
thật (400 / 401 / 403 / 404 / 409 / 429 / 503). Mã lỗi hay gặp: `bad_request`,
`not_found`, `auth_required`, `blocked`, `rate_limited`, `daily_limit`,
`limit_reached`, `open_exists`, `no_file`.

> Text lỗi hiển thị cho user **phải** lấy từ field `message` của response, không
> hardcode phía client (xem CLAUDE.md § Messaging).

### Gate download restricted

```
GET rf/v1/download-url?post=ID&file=IDX
```

`auth/actions/download.php:rf_rest_download_url()`:
1. validate `post` + `file`, post phải `publish`
2. `rf_is_restricted($post_id)` → nếu restricted:
   - không có session → **401 `auth_required`**
   - `blocked === 1` → **403 `blocked`**
3. `rf_build_download_url()` — resolve `download_list` + `download_list_aff`
   (merge, index 1-based), chèn aff code theo host từ option `affs`, rồi
   `add_query_arg('token', get_download_token())`
4. trả `{ ok: true, url }`

Post không restricted thì cho qua (khách vẫn tải được). **Server là gate thật**;
notice ở UI chỉ là trình bày.

---

## 9. Cache per-member

`rf_member_cache_key( string $entity, $member_id )` → `member:{id}:{entity}`

Một hình dạng colon-namespaced duy nhất cho mọi cache theo member. **Không** thêm
prefix theme vào key — Redis key đã được namespace bởi `WP_REDIS_PREFIX` ở wp-config.
Đổi shape payload thì bump slug entity.

| Entity | TTL | Invalidate |
|---|---|---|
| `wishlist` | 4 × `HOUR_IN_SECONDS` | khi toggle; fan-out khi post trash/delete |
| `tickets` (history) | `DAY_IN_SECONDS` | khi tạo / đổi status |
| `contacts` (history) | `DAY_IN_SECONDS` | khi gửi mới / admin reply |
| `stats` | — | dùng cho `/my-stats` |

Lưu ý: list chỉ cache ở **trang đầu mặc định** (`offset === 0 && limit === default`),
các trang sau query trực tiếp.

---

## 10. Trang front-end member

`auth/member-pages.php` — routing giống endpoint `/download`: rewrite set query var,
`template_redirect` nạp template từ `auth/templates/`.

| URL | `rf_page` | Template | Ghi chú |
|---|---|---|---|
| `/wishlist/` | `wishlist` | `auth/templates/wishlist.php` | |
| `/profile/` | `profile` | `auth/templates/profile.php` | |
| `/request/` | `ticket` | `auth/templates/ticket.php` | **slug công khai là `/request`**, page key nội bộ vẫn `ticket` |

- Query var `rf_page` thêm qua filter `query_vars`.
- Flush rewrite một lần, guard bằng option `rf_member_pages_rw` (giá trị hiện tại `'3'`
  — **bump giá trị này khi thêm/đổi route**, nếu không rule mới sẽ không được flush).
- Mỗi trang: `is_404 = false`, `status_header(200)`, `wp_robots_no_robots`.
- Shell HTML giống nhau cho mọi người; nội dung per-user do `auth.js` fetch từ `rf/v1`.

### UI helper — `auth/member-ui.php`

- `rf_member_menu_items()` — single source cho dropdown desktop + submenu mobile:
  Profile `/profile/`, Wishlist `/wishlist/`, Create Request `/request/`, Logout (button
  `data-rf-logout`).
- `rf_member_menu_icon( $key )` — inline SVG `currentColor`, keys: `user`, `heart`,
  `ticket`, `logout`.
- `rf_render_member_menu( 'desktop' | 'mobile' )` — class item `.rf-di` / `.rf-m-sub-item`.
- `rf_render_restricted_notice()` — notice "Members only", wrapper class
  `.rf-inactive-only` → hiện cho **mọi ai không phải member active** (khách **và**
  member bị block). Render cho tất cả nên cache-safe.
- Wishlist button: `rf_render_wishlist_button( $post_id )` (ở `actions/wishlist.php`) —
  `<button data-rf-wish data-post="ID">`, `auth.js` tự đánh dấu active.
- `rf_post_type_meta( $post_type )` — label + console taxonomy cho badge wishlist.

### JS

`auth/js/auth.js` (963 dòng), enqueue handle `rf-auth`, depends `rf-toast`.
Localize `RF_AUTH = { clientId, rest: rest_url('rf/v1/') }`.
`js/rating.js` (handle `rf-rating`, depends `rf-auth`) chỉ load ở single valid post type
hoặc trang download.

---

## 11. Trang admin (6)

Chỉ require khi `is_admin()`.

| File | Menu slug | Capability | Icon | Position |
|---|---|---|---|---|
| `admin-members.php` | `rf-members` | `edit_users` | `dashicons-groups` | 3 |
| `admin-report.php` | `report` | `manage_options` | `dashicons-warning` | 3.1 (ACF options page) |
| `admin-ratings.php` | `rf-ratings` | `manage_options` | `dashicons-star-filled` | 3.2 |
| `admin-wishlists.php` | `rf-wishlists` | `manage_options` | `dashicons-heart` | 3.25 |
| `admin-ticket.php` | `ticket` | `manage_options` | `dashicons-tickets` | 3.2 |
| `admin-contact.php` | `contact` | `manage_options` | `dashicons-email` | 3.3 |

> ⚠️ `rf-ratings` và `ticket` cùng position `3.2`; `inc/options.php` (`options`) cũng ở
> `3.3` trùng với `contact`. Thứ tự menu giữa các cặp này không xác định.

`admin-report.php` không dùng `add_menu_page` — nó đăng **ACF options page** slug
`report`, rồi hook `toplevel_page_report` (priority 5 / 20) để chèn bảng trước & sau
field ACF.

### AJAX admin (`admin-ajax.php`)

| Action | File |
|---|---|
| `app_report_delete_row` / `app_report_delete_rows` | `admin-report.php` |
| `app_ticket_update_status` / `app_ticket_delete_row` / `app_ticket_delete_rows` | `admin-ticket.php` |
| `app_contact_save_reply` / `app_contact_delete_row` / `app_contact_delete_rows` | `admin-contact.php` |

---

## 12. Tích hợp với phần còn lại của theme

| Điểm nối | Nơi |
|---|---|
| `rf_is_restricted()` + notice | `single-rom.php:240`, `single-emulator.php:103`, `download.php:275` |
| Gate ở AJAX cũ | `inc/ajax.php:73` (`k_get_download` cũng check restricted) |
| Set `hide = 'restricted'` theo logic | `inc/utility.php:655` |
| Field `hide` (ACF) | `setting/setting-rom.php:224` — chỉ `public` \| `restricted` |
| Toggle + bulk action admin | `setting/setting-rom.php:965-1013` |
| Fan-out wishlist khi post đổi status | `actions/wishlist.php` hook `transition_post_status`, `deleted_post` |

`valid_post_type()` / `valid_downloadable_post_type()` (`inc/utility-post-type.php`)
chặn post type lạ ở `/rating`, `/report`, `/wishlist`. Cả hai đọc hằng
`AVAILABLE_POST_TYPES` = `['rom', 'emulator']`.
`rf_post_type_meta()` (`actions/wishlist.php`) map đúng 2 post type đó sang
label + console taxonomy cho badge wishlist.
