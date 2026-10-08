# Member Request Ticket — Design Spec

- **Ngày:** 2026-08-06
- **Trạng thái:** Đã chốt nghiệp vụ, chờ lập implementation plan. **Chưa code.**
- **Phạm vi:** Tính năng member tạo "Request" (yêu cầu thêm game / thêm-cập nhật file), theo dõi trạng thái xử lý, admin quản lý & phản hồi.

---

## 0. Quy ước đặt tên (Naming)

**Quyết định: tách UI ↔ code.**

| Lớp | Dùng từ | Lý do |
|-----|---------|-------|
| Hiển thị cho member (menu, heading, label) | **"Request"** | Khớp CTA "Create Request", thân thiện với người dùng. |
| Code / DB / route / function / file | **`ticket`** | Trong `auth/` từ "request" đã overload nặng (`WP_REST_Request $request`, `rf_verify_request_origin`, `$_REQUEST`, `wp_remote_request`). Đặt `rf_request_*` sẽ gây nhầm lẫn với HTTP request. `ticket` không đụng gì, nghĩa domain rõ ràng. |

Cụ thể: bảng `{prefix}member_ticket`, hàm `rf_ticket_*`, route `/ticket`, file `auth/actions/ticket.php` + `auth/admin-ticket.php` + `auth/templates/ticket.php`. Nhưng string i18n hiển thị dùng "Request" (vd `__( 'Create Request', 'rom' )`).

> Có thể veto: nếu muốn đồng nhất hoàn toàn 1 từ, đổi cả code sang `request` — nhưng chấp nhận rủi ro nhầm lẫn nêu trên.

---

## 1. Tổng quan

Member đã đăng nhập có thể gửi 1 "Request" tới admin để:
- **Add new game** — đề nghị thêm game chưa có trên site.
- **Add/Update file for existing game** — đề nghị thêm hoặc cập nhật file cho game đã có.

Sau khi gửi, member theo dõi được trạng thái xử lý ngay trên trang `/ticket` (form + danh sách ticket của mình). Admin xử lý trong wp-admin: đổi trạng thái và nhập nội dung phản hồi (member đọc được).

Bám kiến trúc member sẵn có: REST `rf/v1/*` (không admin-ajax cho front-end), template cache-safe + data client-side, bảng custom trong `auth/setting-member.php`, admin page kiểu `auth/admin-report.php`.

---

## 2. Data model — bảng `{prefix}member_ticket`

Thêm vào `rf_member_install_schema()` trong `auth/setting-member.php`; bump `RF_MEMBER_DB_VERSION` **5 → 6**.

| Cột | Kiểu | Ghi chú |
|-----|------|---------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | PK |
| `member_id` | BIGINT UNSIGNED NOT NULL | Chủ ticket (FK logic tới `{prefix}member.id`) |
| `request_type` | TINYINT UNSIGNED NOT NULL | 0 = add_new_game, 1 = update_existing |
| `game_title` | VARCHAR(255) NOT NULL | Free-text (không link post_id) |
| `reference_url` | VARCHAR(512) NULL | Link tham khảo, optional (cho phép external) |
| `description` | TEXT NULL | Mô tả chi tiết yêu cầu |
| `response_content` | TEXT NULL | **Admin nhập** khi xử lý; member đọc được |
| `status` | TINYINT UNSIGNED NOT NULL DEFAULT 0 | 0 Init, 1 Processing, 2 Solved, 3 Closed, 4 Cancelled |
| `closed_by` | TINYINT UNSIGNED NULL | 1 = member, 2 = admin (ai kết thúc ticket) |
| `created_at` | DATETIME NOT NULL | Site-local (nhất quán report/rating) |
| `updated_at` | DATETIME NOT NULL | Cập nhật mỗi lần đổi status / response |

**Index:**
- `PRIMARY KEY (id)`
- `KEY idx_member_created (member_id, created_at)` — phục vụ "my tickets" (newest-first), check open-ticket, đếm-tạo-hôm-nay (đều lọc theo `member_id`).
- `KEY idx_status_created (status, created_at)` — admin list + filter theo status, newest-first.

DDL string-built an toàn vì tên bảng chỉ từ `$wpdb->prefix` (không có input người dùng), giống các bảng member khác.

---

## 3. Mapping arrays (code ⇄ label)

Đặt trong `auth/setting-member.php` (luôn load, dùng chung front REST + admin). Label bọc `__()` textdomain `rom`.

```php
function rf_ticket_statuses() {
    return array(
        0 => __( 'Init', 'rom' ),
        1 => __( 'Processing', 'rom' ),
        2 => __( 'Solved', 'rom' ),
        3 => __( 'Closed', 'rom' ),
        4 => __( 'Cancelled', 'rom' ),
    );
}

function rf_ticket_types() {
    return array(
        0 => __( 'Add new game', 'rom' ),
        1 => __( 'Add / Update file for existing game', 'rom' ),
    );
}
```

Hằng số tập status để tái dùng logic:
- **Terminal** (đóng slot, không reopen): `{3 Closed, 4 Cancelled}`.
- **Open** (chặn tạo mới + cho phép thao tác): `{0 Init, 1 Processing, 2 Solved}`.

---

## 4. Business rules

### 4.1 Giới hạn khi tạo (POST `/ticket`) — 3 lớp

1. **Burst throttle (dùng chung):** gọi `rf_check_action_rate( member_id )` như comment/report/wishlist (30 action / 180s, Redis, fail-open).
2. **1 ticket mở / thời điểm:** không được tạo nếu member đang có ticket status ∉ terminal (`status NOT IN (3,4)`).
3. **Cap 10 tạo / ngày:** `COUNT(*)` ticket của member có `created_at ≥ 00:00 hôm nay (site-local)` phải `< 10` (đếm mọi ticket đã tạo, kể cả đã cancel/close — chống spam open/close).

Rule 2 + 3 thực thi **atomic trong 1 INSERT** để concurrent submit không lách:

```sql
INSERT INTO {member_ticket}
    (member_id, request_type, game_title, reference_url, description, status, created_at, updated_at)
SELECT %d, %d, %s, %s, %s, 0, %s, %s FROM (SELECT 1) d
WHERE NOT EXISTS (
        SELECT 1 FROM (SELECT id FROM {member_ticket}
                       WHERE member_id = %d AND status NOT IN (3,4) LIMIT 1) o
      )
  AND ( SELECT c FROM (SELECT COUNT(*) c FROM {member_ticket}
                       WHERE member_id = %d AND created_at >= %s) t ) < 10
```

rows-affected: `1` = tạo được, `0` = bị chặn (đang có ticket mở **hoặc** đủ 10/ngày → phân biệt lý do bằng 1 SELECT phụ trước đó để trả message đúng), `false` = lỗi SQL. (Sub-select bọc derived table để MySQL cho đọc cùng bảng đang insert — tránh error 1093, giống report.)

### 4.2 Vòng đời (state machine)

```
Init(0) ──admin──> Processing(1) ──admin──> Solved(2)
   │                    │                      │
   └──────── admin: Closed(3) [terminal] ◄─────┘
   member (bất kỳ khi đang open): Close ─> Closed(3) | Cancel ─> Cancelled(4)   [terminal]
```

- Admin di chuyển tự do trong `{Init, Processing, Solved}` và có thể đưa về `Closed(3)`.
- Member trên ticket đang open: **Close → 3** hoặc **Cancel → 4** (`closed_by = 1`).
- Admin kết thúc: **Closed → 3** (`closed_by = 2`). (Cancel là ý định member; admin dùng Closed.)
- `Closed` & `Cancelled` **terminal** — mọi endpoint từ chối thao tác tiếp (không reopen).
- Mỗi lần đổi status hoặc nhập response → cập nhật `updated_at`.

### 4.3 Quyền

| Hành động | Ai | Điều kiện |
|-----------|-----|-----------|
| Tạo ticket | member (chưa blocked) | pass 3 lớp §4.1 |
| Xem ticket của mình | member (kể cả blocked — read-only) | sở hữu |
| Close / Cancel | member | sở hữu + ticket đang open |
| Đổi status + nhập response | admin (`manage_options`) | ticket chưa terminal |
| Xóa ticket (row/bulk) | admin | — |

Member blocked: xem được, không tạo/không close (giống report — chỉ chặn hành động ghi).

---

## 5. REST endpoints — `auth/actions/ticket.php`

Namespace `RF_REST_NS` (rf/v1). `permission_callback => __return_true`, tự verify origin + session trong handler (pattern report). Message trả về sẵn (client hiển thị nguyên văn qua toast).

### POST `/ticket`
Body: `{ type:int(0|1), game_title:string, reference_url?:string, description?:string }`
- Verify origin → member → chưa blocked → `rf_check_action_rate`.
- Validate `type` ∈ {0,1}; `game_title` bắt buộc, trim, cap 255; `reference_url` `esc_url_raw`, cap 512, cho phép external; `description` `wp_strip_all_tags` + cap (đề xuất 2000 chars).
- Atomic INSERT §4.1. Trả: `open_exists` / `daily_limit` / `ok`.

### GET `/my-tickets?offset&limit`
- Ticket của member, `ORDER BY created_at DESC, id DESC`, paginate offset/limit (limit 1..50, default 10).
- Trả mỗi row: `id, type(int + label), status(int + label), game_title, reference_url, description, response_content, created_at, updated_at, closed_by`. Kèm `has_more`, `total`.
- Blocked vẫn xem được.

### POST `/ticket/close`
Body: `{ id:int, mode:string('close'|'cancel') }`
- Verify origin → member → chưa blocked.
- Ticket phải thuộc member + đang open (`status NOT IN (3,4)`).
- `mode=close` → status 3; `mode=cancel` → status 4. Set `closed_by=1`, `updated_at`.
- Trả message tương ứng.

---

## 6. Trang `/ticket` (form + tracking, 1 trang)

- **Routing:** `auth/member-pages.php` — thêm slug `ticket` vào `rf_member_pages()` + `add_rewrite_rule('^ticket/?$', 'index.php?rf_page=ticket', 'top')`. Bump lại guard flush (`rf_member_pages_rw`) để rewrite mới có hiệu lực.
- **Template:** `auth/templates/ticket.php` — cache-safe shell, HTML giống nhau cho mọi người, `wp_robots_no_robots`.
  - **Guest:** prompt đăng nhập (`data-rf-login`).
  - **Member (rf-authed):**
    - **Form tạo:** select `request_type`, input `game_title`, input `reference_url` (optional), textarea `description`. Submit → POST `/ticket`.
    - **Khóa form** khi đang có ticket open: hiển thị notice "Bạn đang có 1 request chưa kết thúc" (JS phát hiện từ danh sách).
    - **Danh sách ticket của mình:** mỗi item hiện type, game_title, badge status (màu theo status), `response_content` của admin (nếu có), thời gian, nút **Close** / **Cancel** khi đang open. Load qua GET `/my-tickets` (skeleton → rows → "Load more").
- **JS:** `auth/js/auth.js` — thêm module ticket (submit form, render list, close/cancel, lock form theo trạng thái open). Message lỗi lấy từ server → `window.showToast`.

---

## 7. Menu profile

`auth/templates/profile.php` — thêm 1 card **"Create Request"** (kiểu card Wishlist: icon + tiêu đề + mô tả ngắn, `<a href="/ticket/">`). Không cần accordion lazy-load (tracking nằm ở `/ticket`).

---

## 8. Admin — `auth/admin-ticket.php`

Mirror `auth/admin-report.php`:
- ACF options page **"Requests"** (`menu_slug` `ticket`, icon `dashicons-tickets`, `capability manage_options`), đặt cạnh Report/Members (position ~3.1–3.4).
- Bảng phân trang (reuse `rf_admin_per_page`, `rf_admin_per_page_field`, `rf_admin_today_badge`, `rf_admin_today_count`, `rf_admin_time_ago`): cột type, member (email/name, LEFT JOIN `{prefix}member`), game_title, reference_url, description, **status (`<select>` inline)**, **response_content (textarea)**, created/updated, action xóa.
- **Đổi status + response:** AJAX `wp_ajax_app_ticket_update_status` — check `manage_options` + nonce + validate status ∈ {0..3} (admin không set Cancel); nếu status hiện tại đã terminal thì từ chối; →Closed set `closed_by=2`; luôn set `updated_at`; lưu `response_content` (sanitize, cap). Trả message.
- **Xóa:** `wp_ajax_app_ticket_delete_row` / `_rows` (copy nguyên pattern report, nonce `ticket_delete_nonce`).
- Filter theo status (dropdown GET), sort mặc định `created_at DESC` (idx_status_created).

---

## 9. Guardrails (bắt buộc)

- **Security:** mọi `$wpdb` query dùng `prepare()`; escape output (`esc_html/esc_attr/esc_url`); sanitize input; nonce cho admin-ajax; REST verify origin + session.
- **i18n:** mọi string UI qua `__()/esc_html__()` textdomain `rom`; không tiếng Việt trong UI theme.
- **Cache-aware:** `/ticket` là shell cache-safe, không dùng `is_user_logged_in()`/cookie WP trong template; state member client-side + REST.
- **Messaging:** 1 notification / action; text lỗi từ `message` server → toast dùng chung.
- **Verify:** sau sửa PHP chạy `php -l`; sau sửa JS chạy `node --check`.

---

## 10. Danh sách file thay đổi (dự kiến)

**Mới:**
- `auth/actions/ticket.php` — REST (POST /ticket, GET /my-tickets, POST /ticket/close).
- `auth/admin-ticket.php` — admin page + AJAX update-status/delete.
- `auth/templates/ticket.php` — trang form + tracking.

**Sửa:**
- `auth/setting-member.php` — bảng `member_ticket`, bump `RF_MEMBER_DB_VERSION`, `rf_ticket_table()`, `rf_ticket_statuses()`, `rf_ticket_types()`.
- `auth/member-pages.php` — slug + rewrite `/ticket`, re-flush guard.
- `auth/templates/profile.php` — card "Create Request".
- `auth/js/auth.js` — module ticket.
- `functions.php` — `require` `auth/actions/ticket.php` + `auth/admin-ticket.php`.

---

## 11. Chốt quyết định (từ phần brainstorm)

1. `game_title` free-text, **không** post_id.
2. Status: tinyint + mapping array; **Closed & Cancelled terminal**, không reopen.
3. Rate: **1 ticket mở/thời điểm** + **10 tạo/ngày** + throttle chung `rf_check_action_rate`. (Bỏ cap kiểu report cũ.)
4. `request_type`: enum cố định 2 giá trị + mapping array.
5. Tracking + form chung trang `/ticket`.
6. Thêm status **Cancel** (member Close hoặc Cancel đều được).
7. Thêm cột **`response_content`** (admin nhập, member đọc).
8. Naming: code = `ticket`, UI = "Request".

---

## 12. Ngoài phạm vi (không làm lần này)

- Không email/notify admin (chỉ hiện trong admin page — "giống report").
- Không thread hội thoại nhiều lượt (chỉ 1 ô `response_content`).
- Không đính kèm file.
- Không post_id / autocomplete game.
- Không reopen ticket terminal.

---

## Amendments — 2026-08-06 (sau implementation + security review)

1. **`description`: `TEXT` → `VARCHAR(500)`**, cap server `RF_TICKET_DESC_MAXLEN = 500` ký tự (thay 2000). Đủ 1 đoạn, bounded, lưu inline (không off-page như TEXT).
2. **DB-enforce "1 open ticket / member"** (fix race: 2 request tạo đồng thời có thể lách guard `WHERE NOT EXISTS`): thêm cột generated `open_slot BIGINT UNSIGNED AS (CASE WHEN status IN (0,1,2) THEN member_id END) STORED` + `UNIQUE KEY uq_open_slot`. Ticket terminal (3/4) → `open_slot = NULL` (nhiều NULL hợp lệ → nhiều ticket đã đóng OK). Insert mở thứ 2 đồng thời → duplicate key → handler bắt (`stripos($wpdb->last_error,'Duplicate')`) trả `open_exists` 409; INSERT bọc `$wpdb->suppress_errors()` vì duplicate là outcome mong đợi. Cũng chặn luôn burst 10/ngày (phải đóng ticket cũ mới tạo mới được).
3. **`reference_url`**: đo bằng `mb_strlen` (ký tự) thay `strlen` (byte) cho khớp `VARCHAR(512)`.

`response_content` giữ `TEXT`; cap admin reply = `RF_TICKET_RESPONSE_MAXLEN = 1000` (Task 6).

## Amendments — 2026-08-07

4. **`request_type` thêm code `2 => Other`.** Form: `<select required>` + option placeholder `disabled selected` (KHÔNG default) → member phải tự chọn. Server validate: reject nếu type rỗng / không phải số / ngoài list `{0,1,2}` → `bad_type` (bỏ silent `(int)` cast 0). JS gửi giá trị thô + guard rỗng (bỏ `|| 0`).
5. **Menu toggle member** (`auth/member-ui.php`) thêm item **"Create Request"** → `/request` (icon plus), giữa Wishlist và Logout.
6. **Public slug `/ticket` → `/request`** (`member-pages.php`: rewrite `^request/?$` → `rf_page=ticket`; internal page key + template `ticket.php` + REST + `data-rf-ticket-*` hooks GIỮ NGUYÊN `ticket`). Link profile card + menu → `/request/`. Flush guard bump `'3'`. Lưu ý: `rf/v1/my-tickets` là REST endpoint (API), KHÔNG phải page slug — không có `/my-request`.
7. **Admin filter** đổi `<select>` → WP-native `.subsubsub` (All | Init | Processing | Solved | Closed | Cancelled + count, bấm sẵn), giống trang Members; count từ 1 aggregate query.