# Google Login & Member System — Design / Implementation Plan

**Status:** Approved plan, chưa implement
**Ngày:** 2026-07-19 · **Cập nhật:** 2026-07-21 (quy mô — §1b) · **2026-07-21b: chốt Plan B** — đã verify Cloudflare **APO không thể cache cho user mang cookie login WP**; member dùng **session riêng (Redis + cookie `rf_member`)**, KHÔNG dùng session WP native. §4.2/4.3/4.5/4.7/§8 viết lại theo hướng này. · **2026-07-21c: review findings — §12 (bắt buộc xử lý trước/khi impl).** · **2026-07-22: review round 2 — §13** (localStorage sync đa-tab + 401/403 wrapper, ZSET cho session index thay Set, GSI **không chạy trong webview** FB/Zalo, DB index target `google_id`, comment pending không hiện + cache purge, ID token replay, PII grep gate pre-deploy). §4.2b/§4.3/§5 cập nhật inline theo §13. · **2026-07-22b: §1/§1b** — feature là **greenfield** (không kế thừa hệ nào). `wp_users` hiện **chỉ admin/editor**, con số 10K–100K là **member dự kiến sau golive** (không phải hiện trạng). · **2026-07-22c: ĐẢO quyết định #1** — member sang **bảng riêng `member`** (không `wp_users`); comment attribute bằng `rf_member_id` + bảng index `member_comments` (member xem được "comment của tôi"). Spec: **§14**. §3/§4.4/§4.7/§5 sửa theo; §13.3.4 phần lớn moot. · **2026-07-22d:** +§14.6 (invariant phân biệt staff vs member — 2 namespace id độc lập); +§15 (reports **member-only, có auth**, staff không gửi, member xem report của mình, +cột `rf_member_id`); "my-comments" chỉ **pending+approved** (spam/trash DELETE khỏi index); **BỎ role member** (§3#7, §4.1). · **2026-07-22e:** comment = **RAW TEXT tuyệt đối** (không HTML/file, **không auto-link** — URL là text); +**§16 throttle hành động member** (counter `INCR rf_action_rl:{member_id}` + TTL/phút, `> limit` → `429`). **Rating → member-only** (bỏ guest rate; §4.8/§3#4/§2). Comment/report/rating đều vào bucket throttle §16. · **2026-07-22f: CHỐT gate download = phương án C** — tách "trang `/download/`" khỏi "hành động tải": hành động tải đi qua **`rf/v1/download-url`** (cookie `rf_member` sẵn có) trả **URL file cuối** (host ngoài + `get_download_token()`); trang `/download/` giữ **công khai, cache-safe**, KHÔNG nhồi URL/token vào HTML nữa. **BỎ token HMAC, BỎ cookie thứ 2** → chỉ **1 cookie `rf_member`** (Path=`/wp-json/rf/v1`). Block tức thì (endpoint re-check `blocked` live). `/download/` đang **noindex** (không SEO). §4.5 viết lại. · **Rating HOÃN sang future work** (§4.8) — chốt storage = bảng riêng `rf_member_ratings` khi làm; phase này không đụng rating. · **BỎ HẲN ZSET session index** (§13.T) — block đã tức thì nhờ re-check `blocked` mọi endpoint, session hết theo TTL, không leak để fix.
**Phạm vi:** Thêm đăng nhập bằng Google (member ở bảng riêng, KHÔNG role WP), gate download link cho member, quản lý + block member trong admin.

> Doc này là nguồn tham chiếu để maintain. Cập nhật khi implement thực tế lệch khỏi plan.
> ⚠️ Ở quy mô ~100K member + 26M traffic/tháng + **Cloudflare APO**, phần **caching (§8)** là rủi ro sống-còn — đọc kỹ trước khi code UI/login-state. Nguyên tắc: **member không bao giờ gửi cookie `wp-*`/`wordpress*` lên front-end**, nếu không APO bypass cache → 100K người ăn origin.

---

## 1. Bối cảnh (hiện trạng code)

Xác minh trực tiếp trong code, KHÔNG suy đoán:

- **Feature member là GREENFIELD — chưa tồn tại trong code.** Không có member đăng ký sẵn, không có luồng đăng nhập member để kế thừa hay tích hợp. → Toàn bộ (Google auth, session Redis, gate download/comment, trang quản lý) **build mới hoàn toàn từ đầu**.
- **Download link hiện KHÔNG gate theo login.** `single-rom.php:238-258` chỉ ẩn theo field `hide` + `wp_is_mobile()`. Link trỏ `/download/{slug}-{ID}`; endpoint xử lý ở `download.php` + token ở `inc/download-token.php`.
- **Field `hide`** định nghĩa tại `inc/setting-rom.php:222-226`: `public` / `download` (ẩn download trên PC, hiện trên mobile) / `all` (404 PC). Có bulk action + cột admin + AJAX cycle `k_change_hide_status` đã build quanh field này.
- Comments = WP-native (`wp_comments`, gắn `user_id` khi login). Ratings = ACF meta (cho guest). Reports = bảng custom `wphh_user_report`. Tất cả key theo `wp_users`.

---

## 1b. Ngữ cảnh vận hành & quy mô (2026-07-21)

**Techstack:** nginx + PHP 8.3 + Redis 7.4 (object cache, phpredis) + TailwindCSS, đứng sau **Cloudflare full-page cache**.

**Quy mô:**
- ~90K bài publish.
- ~26M lượt truy cập/tháng (~10 req/s trung bình, peak cao hơn nhiều).
- `wp_users` hiện **chỉ có admin/editor** (user hệ thống) — **chưa có member nào**. **Dự kiến 10K–100K member**, đạt trong thời gian ngắn sau golive (mọi user login Google = 1 member). → Ở launch, mọi email trùng account sẵn có = staff → auto-link luôn bị từ chối (§4.2/§12.G); member chỉ sinh mới. Post-golive: ~toàn bộ `wp_users` là member, vài staff lẻ → `is_member` selectivity ≈ 0 (§13.3.4).
- Cache = **Cloudflare APO** (xác nhận qua header `cf-apo-via: origin,cookie` + `cf-edge-cache`). APO cache full HTML tại edge.
- **APO bypass cache theo bộ cookie CỐ ĐỊNH** (`wp-*`, `wordpress*`, `comment_author_*`, WooCommerce) và **KHÔNG cho bỏ danh sách này** ("Bypass on Cookie" chỉ THÊM, không xoá). → **Không thể ép APO cache cho user mang cookie login WP.** Đã verify (docs + community Cloudflare).

**⚠️ Hệ quả kiến trúc bắt buộc (chi tiết §8):**

1. **Không check-login bằng AJAX mỗi pageview.** Nút login ở header → có mặt trên MỌI trang. 26M pageview × 1 AJAX = tái hiện chính xác sự cố overload của tracking cũ (admin-ajax boot WP + ghi DB per-view). → Trạng thái login phải lộ qua **`localStorage` đọc client-side, 0 request server, 0 cookie front-end** (§4.2b).
2. **Member phải TIẾP TỤC ăn APO cache.** Vì APO không cache được user có cookie WP (điểm trên), **member KHÔNG được dùng session WP native** (`wp_set_auth_cookie` set `wordpress_logged_in_*` → APO bypass → 100K người ăn origin). → **Plan B: member dùng session riêng** (Redis + cookie `rf_member` HttpOnly **scope hẹp `/wp-json/rf/v1`**, KHÔNG gửi lên front-end) + **display state trong `localStorage`** (không cookie nào lên front-end → APO luôn cache). Phục vụ **1 bản HTML chung cho tất cả**, personalize 100% client. (Plan gốc từng định "chỉnh CF ngừng bypass" — BẤT KHẢ THI với APO; đã bỏ.)
3. **Trang quản lý 100K member** không được `COUNT(*)`/quét `wp_capabilities LIKE` mỗi lần load (§4.4).
4. **Signup mở (auto-tạo qua Google)** ở quy mô này cần chống abuse/bot (§7).

---

## 2. Mục tiêu & Non-goals

**Mục tiêu**
- Đăng nhập bằng Google (không signup thủ công).
- Member lưu ở **bảng riêng `member`** (không role `wp_users` — §14).
- Gate download link của post `restricted` chỉ cho member đã login.
- Bắt buộc login (member) để **comment, report** (§4.7/§15). *(Rating member-only: HOÃN — future work, §4.8.)*
- Admin block/unblock member (chỉ admin).
- Trang admin quản lý members, sort theo ngày đăng ký.

**Non-goals**
- Không mở signup public (chỉ tạo member tự động qua Google).
- ~~Không đụng rating (guest vẫn rate)~~ → **đảo: rating chuyển member-only** (2026-07-22e, §4.8).
- Không thêm social provider khác ngoài Google.

---

## 3. Quyết định đã chốt

| # | Quyết định | Chốt |
|---|---|---|
| 1 | Lưu member ở đâu | **Bảng riêng `member`** (KHÔNG dùng `wp_users`) — vì session tự quản đã bỏ hết auth WP, `wp_users` chỉ còn gây EAV bloat + rủi ro escalation + đống vấn đề perf §4.4/§13.3.4. Chi tiết §14. *(Đảo từ quyết định gốc "wp_users" — 2026-07-22c.)* |
| 2 | Signup thủ công | **Bỏ** — chỉ login Google, auto-tạo member |
| 3 | Member login Google | Auto xem download + comment |
| 4 | Rating | **Member-only** *(thiết kế xong nhưng **HOÃN sang future work** — 2026-07-22f; đợt này giữ rating guest hiện tại. §4.8)* |
| 5 | Handler Google OAuth | **Tự viết**, không dùng plugin |
| 6 | Đánh dấu "restricted" post | **Tái dùng field `hide`**, thêm value `members` |
| 7 | Role member | **BỎ** — member không nằm trong `wp_users` nên không cần WP role. Danh tính = row `member` (§14). *(Đảo từ "tạo role member" — 2026-07-22c.)* |
| 8 | Hệ cache | **Giữ Cloudflare APO** (Plan B). Không gỡ APO để làm auth |
| 9 | Session member | **Session riêng, Redis-backed** — cookie `rf_member` (opaque id, HttpOnly, scope `/wp-json/rf/v1`). KHÔNG `wp_set_auth_cookie`, KHÔNG cookie WP trên front-end |
| 10 | Display login-state | **`localStorage`** (JS ghi lúc login, đọc mỗi trang) — không dùng cookie để tránh APO bypass |
| 11 | Enforce download/comment | Qua **endpoint REST động** (`/wp-json/rf/v1/*`, APO bypass sẵn) verify `rf_member`; KHÔNG dùng `is_user_logged_in()` |

---

## 4. Kiến trúc

### 4.1 ~~Role `member`~~ → BỎ (2026-07-22c)
Member **không nằm trong `wp_users`** (tách bảng — §14) → **không cần WP role, không `add_role`, không `wp_insert_user`**. Toàn bộ nội dung cũ (ẩn admin bar, chặn `/wp-admin`, tắt mail new-user) **không còn áp dụng** vì member không phải WP user.
- Danh tính member = row `member` (§14.1). Tạo/đọc/block đều thao tác bảng riêng.
- Việc tạo bảng `member`/`member_comments` + migration cột report (§15) đặt trong file `auth/setting-member.php`, chạy ở activation hook.
- Vì không có WP user cho member: **không có** rủi ro member vào được `/wp-admin`/adminbar → cả lớp phòng thủ đó biến mất (khỏi maintain).

### 4.2 Google OAuth handler (`auth/auth-google.php`)
Luồng Google Identity Services (GSI), dùng ID token — **không cần Client Secret**.

```
Nút GSI (client) → ID token (JWT) → POST /wp-json/rf/v1/login { credential }
  (CSRF: KHÔNG WP nonce — dùng Origin check + verify ID token; nonce claim client-gen — xem §13.O/§12.F)
  → VERIFY token: chữ ký RS256 theo JWKS Google (cache Redis),
    check aud=client_id, iss=accounts.google.com, exp còn hạn, iat còn tươi (≤ ~2–5') [§13.O]
  → Match member (bảng member — §14.2):
      SELECT id, blocked FROM member WHERE google_sub = :sub
      1. có row → dùng member đó (cập nhật last_login_at)
      2. else → INSERT member(google_sub, email, name, avatar_url, created_at=now) → member mới
         (KHÔNG còn luật chống-escalation: member & staff KHÁC BẢNG — §14.2/§14.6.
          email_verified=true chỉ để tin attribute email; match theo google_sub, KHÔNG theo email)
  → Nếu member.blocked = 1 → từ chối (§4.3)
  → TẠO SESSION MEMBER (§4.2b): sinh session_id ngẫu nhiên (≥128-bit)
      → Redis SET rf_sess:{id} = {member_id, exp}, TTL = RF_SESSION_TTL (fixed ~30 ngày — §9)
      → (KHÔNG index session — §13.T; block hiệu lực nhờ re-check blocked mọi endpoint)
      → Set-Cookie rf_member = session_id (HttpOnly, Secure, SameSite=Lax, path=/wp-json/rf/v1)
  → JSON {success, display:{name, avatar, exp}}   (KHÔNG role — member không có WP role)
      → JS ghi localStorage + đóng modal (KHÔNG reload cần thiết)
  ⚠️ KHÔNG gọi wp_set_auth_cookie → tránh cookie WP → APO bypass cache
```

Lưu ở **`member`** (§14.1): `google_sub` (identity), `email`, `name`, `avatar_url`, `blocked`, `created_at`, `last_login_at`.

> **`member` là nguồn sự thật của member** (KHÔNG `wp_users` — §14). Member KHÔNG có `wp_users` row, KHÔNG role, KHÔNG `wp_set_auth_cookie`/`WP_Session_Tokens`. Danh tính = `member.id`; phiên đăng nhập = session Redis riêng. Member không "logged-in" theo nghĩa WP.

**Vì sao verify token:** browser có thể giả token với email bất kỳ. Bắt buộc verify chữ ký Google server-side. `email_verified` chỉ là chốt an toàn khi link vào user đã tồn tại (tránh chiếm tài khoản admin trùng email).

**⚠️ Nonce trên trang cache (quan trọng ở scale):** trang bị CF cache → mọi WP nonce nhồi vào HTML sẽ bị **share cho mọi guest và stale sau khi nonce hết hạn** (12–24h), làm login hỏng hàng loạt. → Endpoint login **KHÔNG dùng WP nonce**. Ranh giới bảo mật thật = **verify chữ ký ID token** + **Origin/Referer check** + **rate-limit** (+ optional **nonce claim client-gen** — §13.O). ⚠️ `g_csrf_token` **chỉ tồn tại ở GSI redirect/`login_uri` mode**; luồng modal callback + `fetch()` KHÔNG có nó (§12.F/§13.O) — đừng dựa vào. WP nonce chỉ dùng cho action động ở admin (block/unblock — trang admin không bị cache).

**JWKS cache:** khoá công khai Google (verify RS256) lưu transient qua Redis object cache (theo `kid`/`Cache-Control` của Google), không fetch mỗi lần login.

### 4.2b Session model member (Redis-backed) — cốt lõi Plan B

Vì APO không cache được user mang cookie WP (§1b), member **không dùng session WP**. Thay bằng:

- **Cookie `rf_member`** = session_id **opaque** (ngẫu nhiên ≥128-bit, KHÔNG chứa dữ liệu). `HttpOnly` + `Secure` + `SameSite=Lax`, **`path=/wp-json/rf/v1`** → **chỉ gửi tới endpoint REST động, KHÔNG bao giờ gửi lên trang HTML front-end** → APO không thấy cookie → **vẫn cache cho member**.
- **Redis `rf_sess:{id}`** = `{member_id, exp}`, TTL **cố định** = `RF_SESSION_TTL` (~30 ngày, hằng config §9; fixed để khỏi ghi lại TTL mỗi request). Đây là nơi tra ngược session → member.
- **KHÔNG có index session** (bỏ ZSET — §13.T, 2026-07-22f). Không lưu danh sách session của member ở đâu → session tự hết theo TTL. Đánh đổi: không "logout mọi thiết bị" chủ động (chấp nhận). Block vẫn tức thì nhờ re-check `blocked`.
- **Xác thực mỗi request động:** endpoint đọc cookie `rf_member` → `GET rf_sess:{id}` → có `member_id` → check `member.blocked` (point lookup PK) → cho qua. Chi phí = 1 Redis GET + 1 lookup, **chỉ trên action thật** (download/comment), KHÔNG phải mỗi pageview.
- **Revoke:** logout = `DEL rf_sess:{id}` (session hiện tại). **Block KHÔNG enumerate/xoá session** (không có index) → dựa hoàn toàn vào **re-check `member.blocked` ở MỌI endpoint** (§4.3) + session hết theo TTL. Không cần vô hiệu hoá token stateless.
- **Login state hiển thị:** KHÔNG suy từ `rf_member` (cookie HttpOnly, không scope front-end). Dùng **`localStorage`** (§4.6): JS ghi `{name, avatar, exp}` lúc login, đọc mỗi trang để personalize. 0 cookie front-end, 0 request server.
- **Đồng bộ khi hết hạn:** nếu localStorage nói "đã login" nhưng session Redis đã hết/bị revoke → gọi action động trả `401`/`403` → JS xoá localStorage + trả UI về "Đăng nhập" (chi tiết §13 phần localStorage sync).

> Vì sao opaque-id + Redis (không phải JWT stateless): **revoke được ngay** (block/logout xoá 1 key), fit sẵn Redis 7.4 đang có, tra cứu chỉ xảy ra ở endpoint động tần suất thấp.

### 4.3 Block user (chỉ admin)
- Cờ block: cột **`member.blocked = 1`** (§14, thay usermeta `member_blocked`).
- Chặn **mọi đường**:
    - Google login handler: nếu `member.blocked` → trả lỗi, KHÔNG tạo session.
    - **Mọi endpoint REST verify `rf_member`** re-check `member.blocked` theo `member_id` → dù còn session hợp lệ vẫn bị từ chối (`403`). *(Đây là điểm khiến block có hiệu lực tức thì độc lập với việc xoá session — §13.T.)*
    - *(Không còn `authenticate` filter: member KHÔNG phải `wp_users` nên không đi qua login WP — §14.4.)*
- **Khi block:** chỉ cần `UPDATE member SET blocked=1`. **KHÔNG enumerate/xoá session** (không có index — §13.T). Member bị đá **ngay lần gọi action kế tiếp** vì mọi endpoint re-check `blocked`; session còn sót tự hết theo TTL (vô hại vì luôn bị `blocked` chặn). *(Muốn "logout mọi thiết bị" sau này → thêm lại index; hiện không cần.)*
- Quyền: mọi action block/unblock guard `current_user_can('edit_users')` (admin). **Nonce dùng bình thường** ở đây — trang admin KHÔNG bị cache.
- UI: nút Block/Unblock **chỉ ở trang quản lý members** (§4.4).

### 4.4 Trang quản lý Members (`auth/admin-members.php`)
> ⚠️ **Đọc theo §14.** Member ở `member` → list/sort/count query **thẳng `member`** (cột indexed), KHÔNG `get_users()`/`wp_capabilities LIKE`. Phần lớn cảnh báo perf bên dưới (§13.3.4) **moot** khi tách bảng — giữ lại để đối chiếu lịch sử. `WP_List_Table` vẫn dùng được nhưng data source = custom query trên `member`.

- Admin menu page (`add_menu_page`, cap `edit_users`, icon dashicons-groups).
- Liệt kê member bằng `WP_List_Table` (data source = query `member`, phân trang + sort chuẩn).
- Cột: Avatar/Name · Email · **Ngày đăng ký (`created_at`)** · Google sub · Trạng thái (Active/Blocked) · Actions (Block/Unblock, View).
- **Sort mặc định theo `created_at DESC`** (cột có index — §14).
- Search theo email/name.
- Query: `SELECT ... FROM member ORDER BY created_at DESC LIMIT ... ` (keyset cho page sâu); `COUNT(*) FROM member` rẻ (bảng đơn mục đích, 100K row indexed).

**⚠️ Ở ~100K member (xem thêm §13.3.4 — index target đúng):**
- `role => member` dịch thành `wp_capabilities LIKE '%member%'` → **quét usermeta**, chậm. Cân nhắc set thêm usermeta cờ `is_member = 1` và lọc theo cờ. ⚠️ Lưu ý §13.3.4: **post-golive, ~mọi user là member → `is_member` selectivity ≈ 0** (100K member vs vài staff lẻ), index này KHÔNG thu hẹp list; nó chỉ đổi `LIKE` scan → equality. Có thể **bỏ filter is_member** và chỉ exclude vài staff role. *(Lúc mới launch list còn nhỏ/rỗng → chưa thành vấn đề perf; luận điểm này áp cho khi đã scale.)*
- **KHÔNG `COUNT(*)` toàn bộ member mỗi lần load** (WP_List_Table cần total để phân trang) → cache tổng số member trong **Redis/option** (`rf_member_count`), cập nhật (+1/−1) khi tạo/xoá/đổi role member; refresh định kỳ để tự sửa lệch.
- Sort `user_registered DESC` + `LIMIT`: `wp_users.user_registered` **mặc định không có index** → thêm `KEY (user_registered)`. Với page sâu, dùng **keyset/seek pagination** (`WHERE user_registered < :last ... LIMIT`) thay `OFFSET` lớn (§13.3.4).
- Search email/name: giới hạn theo `LIKE` có phân trang; không auto-search khi rỗng.

### 4.5 Gate download — phương án C (chốt 2026-07-22f)
Semantics `members`: **member login mới lấy được URL file — áp dụng cả PC lẫn mobile**.

**Nguyên lý:** tách **trang `/download/`** (HTML landing, công khai) khỏi **hành động tải** (lấy URL file thật). Chỉ gate *hành động*, không gate *trang* → dùng đúng cookie `rf_member` (Path=`/wp-json/rf/v1`) sẵn có, **không cần token URL, không cần cookie thứ 2**.

**Vì sao `/download/` không tự thấy `rf_member`:** cookie scope `/wp-json/rf/v1` (cố tình, để front-end 0 cookie → APO HIT). `/download/` là path khác → không nhận. Nhưng KHÔNG kéo cookie/token sang `/download/` nữa — đẩy *hành động* về `/wp-json/rf/v1`.

**Chỉ post `hide=members` mới đổi; `public`/`download` GIỮ NGUYÊN.** `hide` là thuộc tính **của post** (giống nhau cho mọi người xem) → **render server-side vào HTML an toàn cache** (không phải per-user). KHÔNG cần gọi endpoint để "hỏi post có public không" — trạng thái đó đã nằm trong HTML dưới dạng `data-hide`.

- **Post `public`/`download`:** `download.php` render URL file + `get_download_token()` **y như hiện tại** (không đụng — §12.K). Đếm download giữ luồng `rf_track_download` cũ. Guest tải bình thường, **0 endpoint**.
- **Post `hide=members`:** `download.php`/`single-rom.php` render nút mang `data-hide="members"` + `data-post`/`data-file`, **KHÔNG** nhồi URL file/token vào HTML (bỏ `add_query_arg('token', get_download_token(), $link['url'])` ở `download.php:273/302` cho nhánh này). JS đọc `data-hide`:
    - ≠ `members` → link tải thẳng như cũ, **KHÔNG gọi endpoint**.
    - `= members` → bấm → JS gọi **`GET /wp-json/rf/v1/download-url?post=ID&file=IDX`** (cookie `rf_member` tự gửi) → endpoint verify session (§4.2b) + `blocked` **live** → hợp lệ: áp affiliate + sinh `get_download_token()` + **trả JSON `{url}`** → JS mở URL. Không session/blocked → `401`/`403` → mở modal (`403` không reopen — §13.b).
- **Đếm download KHÔNG đổi (#9):** member vẫn tải qua trang `/download/` như public → JS `rf_track_download` (admin-ajax, `#download-container[data-ajax-url]` sẵn trong `download.php`) đã bắn khi vào trang file. Endpoint `download-url` **chỉ cấp URL cho nút cuối, KHÔNG tự đếm** — tránh đếm 2 lần.
- Endpoint **chỉ bị gọi cho post `members`** (thiểu số, gate chọn lọc §13.V) → KHÔNG phải hot-path 26M.

**UI cache-safe (`single-rom.php:238-258`):** trang single BỊ APO cache → KHÔNG `is_user_logged_in()` trong PHP. Nút `hide==='members'` render mặc định **"Đăng nhập để tải"**. JS đọc **`localStorage`** (§4.6): đã login → cho bấm (dẫn tới `/download/` như thường); chưa login → mở modal Google. **0 request server lúc render.**

> ⚠️ Block **tức thì**: endpoint `download-url` đọc `member.blocked` mỗi lần bấm → không cửa sổ rò, không token TTL. Chặn thật = endpoint verify `rf_member`, KHÔNG phải ẩn nút.

### 4.6 Header login button + Modal (mới)
- Header (`header.php`) hiện **chưa có** khu account: nav desktop ở L97-105, mobile menu L119+.
- Thêm **nút "Đăng nhập"** cạnh nav desktop (sau L105) và trong mobile menu → bấm mở **modal popup** chứa nút Google GSI.
- Trạng thái cache-safe: header bị cache như trang → render mặc định nút "Đăng nhập"; JS đọc **`localStorage`** (§4.2b/§4.6), nếu đã login đổi thành avatar + menu (Logout). Không dùng `is_user_logged_in()` trong PHP header, **không gọi AJAX check-login**, **không cookie front-end**.
- **Lazy-load GSI:** chỉ nạp `https://accounts.google.com/gsi/client` khi user mở modal (bấm "Đăng nhập"), KHÔNG nhồi script Google vào 26M pageview.
- **Dùng GSI button (user gesture), KHÔNG dựa One Tap auto** — và **detect in-app webview (FB/Zalo) → banner "mở trình duyệt ngoài"** vì Google KHÔNG hỗ trợ đăng nhập trong webview (§13.P).
- Markup modal đặt 1 lần (footer.php hoặc cuối header.php), tái dùng cho cả nút header lẫn nút "Đăng nhập để tải" ở single.

### 4.7 Comment
> ⚠️ **§14 là spec chuẩn cho comment attribution.** Member giờ ở `member` (không `wp_users`) → comment attribute bằng `rf_member_id` (comment_meta) + bảng index `member_comments`, KHÔNG set `wp_users.user_id`. Các dòng dưới đọc theo §14 (chỗ nào ghi `user_id` → hiểu là `rf_member_id`).

Member **không phải WP-logged-in** → luồng comment WP mặc định (`wp-comments-post.php`) không gắn được author. → Comment của member đi qua **endpoint REST riêng**.
- **Submit:** `POST /wp-json/rf/v1/comment` (kèm `rf_member`) → verify session + block → **`wp_new_comment()` / `wp_handle_comment_submission()`** (KHÔNG `wp_insert_comment` thô — thô bỏ qua moderation/chống-trùng/chống-flood/Akismet, hở spam ở scale này — xem §12.B). Tạo comment dạng **guest** (`user_id=0`), set `comment_author`/`comment_author_email` từ `member` + ghi `comment_meta['rf_member_id']` + đồng bộ `member_comments` (§14). Áp lại rate-limit/sanitize như `inc/ajax.php` (AppSecurity). **Rate-limit theo `rf_member_id` (không chỉ IP — CGNAT, §13.R).**
- **"Comment của tôi":** `GET /wp-json/rf/v1/my-comments` → verify `rf_member` → query `member_comments` theo `member_id` (indexed, kể cả pending) → trả về kèm status (§14).
- **UI cache-safe:** khu comment render mặc định "Đăng nhập để bình luận"; JS đọc `localStorage` → đã login thì hiện form. KHÔNG `is_user_logged_in()` trong template.
- **Comment "chờ duyệt" của member KHÔNG hiển thị lại** (không có cookie `comment_author_*`, HTML lại bị cache) → JS tự chèn comment vào DOM client-side + trạng thái "đang chờ duyệt"; server dedup theo `(rf_member_id, post_id, hash nội dung, cửa sổ thời gian)` (§13.M). *(View "my-comments" cũng trả pending → giảm nhẹ §13.M.)*
- **Comment = RAW TEXT tuyệt đối — không HTML, không file, KHÔNG auto-link (§14.7).** Form = `<textarea maxlength=...>` thường (không TinyMCE, không toolbar, không upload). Input: **strip sạch mọi HTML** (`wp_strip_all_tags`, giữ `\n`) + hard-limit độ dài server-side. Output: **`esc_html` toàn bộ** + `white-space: pre-wrap`; **URL hiển thị dạng text, không thành link**. Mỗi submit tính vào **throttle hành động chung (§16)**.
- **Cache purge:** comment qua REST vẫn phải trigger purge HTML của post (verify plugin CF hook `wp_insert_comment`/`comment_post` — §13.N).
- **Chặn guest server-side:** vô hiệu/deny `pre_comment_on_post` cho request không qua endpoint REST đã verify (không để lọt đường `wp-comments-post.php` ẩn danh).
- Hiển thị danh sách comment vẫn render bình thường trong HTML cache (nội dung công khai, không phải per-user).

### 4.8 Rating — MEMBER-ONLY · **HOÃN sang FUTURE WORK (2026-07-22f)**
> ⏸️ **KHÔNG làm trong đợt này.** Rating guest hiện tại (ACF meta) **giữ nguyên** cho tới khi làm. Ghi lại thiết kế đã chốt để làm sau:
- **Chỉ member login mới rate.** Submit qua **`POST /wp-json/rf/v1/rating`** (kèm `rf_member`) → verify session + block → **throttle §16** → ghi rating.
- Guest bấm rate → JS mở modal login. UI cache-safe: render mặc định, JS đọc `localStorage`.
- **Storage đã chốt = bảng riêng `rf_member_ratings(member_id, post_id, value, created_at)` + `UNIQUE(member_id, post_id)`** (dedup double-rate + cho "rating của tôi"). Không dùng ACF meta cho member.

---

## 5. Data model

| Nơi lưu | Key | Ý nghĩa |
|---|---|---|
| **DB `member`** | (row) | **Nguồn sự thật của member** — bảng riêng (§14). Cột chính: `id, google_sub, email, name, avatar_url, blocked, created_at, last_login_at` |
| DB `member_comments` | (row) | Index "comment của member" → truy xuất nhanh theo `member_id` (§14). `PK(comment_id)`, `KEY(member_id, created_at)` |
| `wp_comments` | (row) | Nội dung + moderation của comment (Akismet/dedup). Member comment = guest row (`user_id=0`), author lấy từ `member` |
| comment_meta | `rf_member_id` | Link comment → member (bền, rebuild được `member_comments` từ đây) |
| Cookie | `rf_member` | **HttpOnly**, Secure, SameSite=Lax, **path=`/wp-json/rf/v1`**. Giá trị = session_id opaque. KHÔNG gửi lên front-end (§4.2b) |
| Redis | `rf_sess:{id}` | `{member_id, exp}`, TTL ~30d. Tra ngược session → member (`member.id`) |
| ~~Redis `rf_user_sessions`~~ | — | **BỎ (2026-07-22f).** Không dùng index session; block tức thì nhờ re-check `blocked` mọi endpoint; session hết theo TTL (§13.T) |
| Redis | `rf_action_rl:{member_id}` | **Counter** throttle hành động member — `INCR` + TTL `RF_ACTION_WINDOW`; `> RF_ACTION_LIMIT` → `429` (§16). Key hết hạn → reset |
| localStorage (client) | `rf_display` | `{name, avatar, exp}` — JS personalize trang cache. **Chỉ lớp hiển thị (cosmetic), KHÔNG phải credential** (§4.2b/§4.6/§13 localStorage sync) |
| ACF field `hide` (rom) | thêm value `members` | Post restricted → login mới tải |

> **Đã bỏ khỏi model** (do tách bảng — §14): các usermeta `google_id`/`google_avatar`/`member_blocked`/`is_member` (→ cột trong `member`); role `member` trong `wp_users` (không còn tạo `wp_users` cho member); `rf_member_count` Redis (COUNT trên `member` đã rẻ, index sẵn). Block = cột `member.blocked`.

---

## 6. Files

### Tạo mới
| File | Nội dung |
|---|---|
| `auth/setting-member.php` | Tạo bảng **`member` + `member_comments`** + migration cột `rf_member_id` cho `wphh_user_report` (§15) — chạy ở activation (idempotent). **KHÔNG còn role/admin-bar/chặn-wp-admin/tắt-mail** vì member không phải `wp_users` (§4.1/§14.4) |
| `auth/auth-google.php` | **REST controller `/wp-json/rf/v1`**: `login` (verify JWT RS256/JWKS-cache-Redis + Origin check + iat freshness + optional nonce claim + **match/tạo member trên `member`** + tạo session Redis + set cookie `rf_member`; **KHÔNG index session** — §13.T), `logout`, `download-url` (§4.5), `comment` + `my-comments` (§4.7/§14.3), `report` + `my-reports` (§15). *(`rating` — HOÃN, §4.8.)* Kèm helper session Redis (create/get/destroy) + throttle §16 (`member_id`) + per-IP `CF-Connecting-IP` + block check; hook `transition_comment_status` sync `member_comments` |
| `auth/admin-members.php` | Trang quản lý members (`WP_List_Table`, data source = query `member`) + block/unblock (set `member.blocked`; **KHÔNG xoá session — không index**, block tức thì nhờ re-check §4.3) + `COUNT(*) member` (rẻ, indexed — **bỏ `rf_member_count`**) |
| `auth/js/auth.js` | Đọc `localStorage` → swap header/nút download/comment form; lazy-load GSI khi mở modal; **detect webview → banner mở-browser-ngoài**; ghi/xoá `localStorage` lúc login/logout; **wrapper fetch xử lý 401/403 tập trung**; **`storage` event sync đa-tab**; gọi endpoint `rf/v1`; xử lý `401`/`403`→ xoá localStorage + revert UI |

→ `require` 3 file PHP trong `functions.php`; register REST route `rf/v1`; enqueue `auth.js` cache-safe (không phụ thuộc login state).

### Sửa
| File | Thay đổi |
|---|---|
| `inc/setting-rom.php` | +choice `members` (L222-226); +bulk action `set_hide_members`; +`members` vào AJAX cycle `k_change_hide_status` |
| `download.php` | **KHÔNG gate ở đây** (phương án C — §4.5). Chỉ **bỏ nhồi URL file/token vào HTML** (L273/302) → nút mang `data-post`/`data-file`; JS gọi `rf/v1/download-url` lấy `{url}`. `public`/`download` guest vẫn tải (§12.K) |
| `inc/download-token.php` | `get_download_token()` (token host ngoài) **giữ nguyên**, chỉ **chuyển nơi gọi** từ `download.php` sang endpoint `rf/v1/download-url` (sau verify `rf_member` cho post `members`). **KHÔNG** thêm token HMAC/WP-side (bỏ — §4.5) |
| `single-rom.php` | +nhánh `hide==='members'` (L238-258), nút `data-restricted`; JS token-flow khi bấm tải. **Render giống hệt PC/mobile** cho nhánh members (§12.E) |
| `header.php` | +nút "Đăng nhập" (desktop sau L105 + mobile menu) mở modal; JS đổi sang avatar theo `localStorage` |
| `footer.php` | +markup modal login (Google GSI) dùng chung |
| `comments.php` / comment area | Render cache-safe; submit qua `rf/v1/comment`; chặn `wp-comments-post.php` ẩn danh (§4.7) |
| `functions.php` | +3 require; đọc hằng `GOOGLE_CLIENT_ID`; register REST `rf/v1`; enqueue `auth.js` |

---

## 7. Bảo mật (checklist)
- [ ] Verify chữ ký ID token (RS256, JWKS Google), check `aud`/`iss`/`exp`, **và `iat` còn tươi** (§13.O).
- [ ] `email_verified=true` mới link theo email.
- [ ] ~~Từ chối auto-link vào user role admin/editor/author~~ → **MOOT do tách bảng** (member ở `member`, staff ở `wp_users`, không chung namespace — §14.2/§14.6).
- [ ] **Comment = raw text (không HTML/file/auto-link):** strip HTML lúc nhập (`wp_strip_all_tags`) + `esc_html` lúc xuất + hard-limit độ dài; URL là text, không `<a>` → không stored-XSS, không link spam (§14.7).
- [ ] **Throttle hành động member (§16):** counter `INCR rf_action_rl:{member_id}` + TTL (EXPIRE **chỉ lần đầu**, tránh trượt-TTL), `> RF_ACTION_LIMIT` (vd 3/60s) → `429`. Key theo `member_id` (không CGNAT-nhầm). Per-IP `CF-Connecting-IP` giữ riêng cho abuse tiền-auth.
- [ ] ~~**Rating = member-only**~~ → **HOÃN (future work, §4.8)** — đợt này không đụng rating.
- [ ] Nonce cho action block/unblock (admin, trang không cache). **Login KHÔNG dùng WP nonce** (§4.2).
- [ ] **CSRF login/action động:** Origin/Referer check + verify ID token; endpoint đổi-trạng-thái thêm custom header `X-RF-Auth` (§12.F). KHÔNG dựa `g_csrf_token` (modal mode không có — §13.O).
- [ ] Block check ở: Google login + **mọi endpoint `rf/v1` verify `rf_member`** (re-check `member.blocked` — chốt chính; KHÔNG có `authenticate` filter vì member ≠ WP user — §4.3/§14.4).
- [ ] Logout **xoá session Redis** (`rf_sess:{id}`). Block = `set blocked=1` (KHÔNG enumerate session — không index §13.T); đá ngay nhờ re-check `blocked`.
- [ ] Guard `edit_users` cho trang quản lý + action block.
- [ ] Gate download (phương án C §4.5) = **endpoint `rf/v1/download-url` (verify `rf_member` + `blocked`) trả URL file cho post `members`**; `download.php` KHÔNG render URL cho post `members`. Public/download KHÔNG đổi.
- [ ] `rf_member`: HttpOnly + Secure + SameSite=Lax, scope `/wp-json/rf/v1`, giá trị **opaque ≥128-bit** (không nhét dữ liệu vào cookie); session có TTL.
- [ ] **⚠️ TRƯỚC PRODUCTION (review 2026-07-23):**
    - **(B)** rate-limit `rf/v1/login` + tạo member theo `CF-Connecting-IP` + Cloudflare Turnstile trong modal — **CHƯA impl** (endpoint login hiện không giới hạn → bot spam tạo member). Bắt buộc làm trước golive.
    - **(C)** Cloudflare **Full (Strict) SSL** để `is_ssl()`=true ở origin → cookie `rf_member` mới có cờ **Secure** ở prod. Flexible SSL (http tới origin) sẽ khiến cookie KHÔNG Secure.
    - **(A)** ✅ đã vá: nonce enforce theo claim trong token (chống bypass replay).
- [ ] **Chống abuse ở scale (signup mở qua Google):** rate-limit tạo member theo **`CF-Connecting-IP`** (§12.A — **origin phải chỉ nhận traffic từ dải IP Cloudflare**, nếu không header spoof được — #8) + Cloudflare Turnstile trong modal; rate-limit comment theo **`member_id`** (§13.R).
- [ ] `localStorage rf_display` KHÔNG chứa credential/quyền, chỉ dữ liệu hiển thị (name/avatar/role/exp).
- [ ] **Member KHÔNG bao giờ nhận cookie `wp-*`/`wordpress*` — và lý tưởng là ZERO `Set-Cookie` — trên front-end** (§13.S).
- [ ] **PII grep gate pre-deploy** (§13.Q) + test 2 trình duyệt: cùng HTML thô, member vẫn `cf-cache-status: HIT`.
- [ ] PII/GDPR: có đường xoá member (email/name/avatar) khi cần; block ≠ xoá dữ liệu.

## 8. Caching với Cloudflare APO + 100K member — PHẦN QUYẾT ĐỊNH

Nguyên tắc gốc: **1 bản HTML ẩn danh chung cho tất cả (guest = member), personalize 100% phía client.** Origin KHÔNG bao giờ render nội dung theo user vào HTML; **member không mang bất kỳ cookie nào lên front-end** → APO luôn cache.

### 8.1 Ràng buộc thật của APO (đã verify)
- APO **bypass cache theo bộ cookie CỐ ĐỊNH** (`wp-*`, `wordpress*`, `comment_author_*`, WooCommerce). Header quan sát được: `cf-apo-via: origin,cookie` + `cf-cache-status: DYNAMIC`.
- **KHÔNG gỡ được danh sách bypass** ("Bypass on Cookie" của Business/Enterprise chỉ THÊM cookie, không xoá) → **không có cách ép APO cache cho user mang cookie login WP**.
- APO **bỏ qua cookie không thuộc bộ trên** (không đưa vào cache key). Nhưng Plan B **không phụ thuộc** điều này: ta đơn giản **không gửi cookie nào lên front-end**.

### 8.2 Vì sao các cách khác bị loại
- **Để member dùng session WP** → có `wordpress_logged_in_*` → APO bypass → 100K người ăn origin. ❌
- **Check-login bằng 1 AJAX/pageview** → 26M hit `admin-ajax.php`/tháng, mỗi hit boot WP. ❌ (đúng pattern đã gây overload ở tracking cũ).
- **Gỡ APO** → mất edge-cache HTML, origin gánh 26M req → sập, trừ khi thay bằng hệ cache khác (dự án infra riêng, không làm ở đây).

### 8.3 Kiến trúc chốt (Plan B)
1. **Cache-safe rendering (origin):** templates front-end (header, single, archive, comment area, nút download) **tuyệt đối không gọi** `is_user_logged_in()` / `current_user_can()` / `wp_get_current_user()` trong output. Luôn render trạng thái guest mặc định.
2. **Không cookie front-end:**
    - Display state → **`localStorage rf_display`** (JS đọc/ghi client-side). Không cookie.
    - Credential → **`rf_member` HttpOnly, path=`/wp-json/rf/v1`** → chỉ gửi tới REST động, **không lên trang HTML** → APO thấy request front-end **không có cookie** → cache bình thường cho member.
3. **Endpoint động** (`/wp-json/rf/v1/*`, `/download/`, `admin-ajax`, `/wp-admin`) APO **bypass sẵn** → nơi verify session, không cache. Chi phí Redis chỉ phát sinh trên **action thật**, không phải mỗi pageview.
4. **KHÔNG cần đổi cấu hình APO.** Đây là ưu điểm lớn nhất của Plan B so với ý tưởng cũ: giữ nguyên hạ tầng cache, chỉ đảm bảo member không mang cookie WP.

### 8.4 Rủi ro phải test kỹ
- **Leak PII:** nếu lỡ 1 chỗ render tên/avatar/`is_user_logged_in()` vào HTML → bản cache dính data 1 member phát cho mọi người. → Review templates + **grep gate pre-deploy** (§13.Q) + test 2 trình duyệt (login + guest) so **HTML thô từ CDN phải giống hệt**.
- **Cache HIT cho member:** verify member đang login vẫn nhận `cf-cache-status: HIT` (KHÔNG `DYNAMIC`) trên trang front-end — nghĩa là không lỡ set cookie WP nào. **Zero `Set-Cookie` trên front-end** (§13.S).
- **Nonce stale trên trang cache** → login không dùng WP nonce (§4.2/§13.O).
- **Đồng bộ localStorage ↔ session Redis:** session hết hạn/bị revoke nhưng localStorage còn → action động trả `401`/`403` → JS xoá localStorage + revert UI (§13 localStorage sync).
- Logout: JS xoá `localStorage` (+ fire `storage` event đa-tab), server `DEL` session Redis + hết hạn cookie `rf_member` **đúng path** (§13.U).

## 9. Config cần chuẩn bị
- **Google OAuth Client ID** (Google Cloud Console → OAuth 2.0, Authorized JS origins = domain). Client Secret không cần.
- **Lưu ở `wp-config.php`** dạng hằng: `define('GOOGLE_CLIENT_ID', '...');`. Code đọc qua hằng này (không hardcode, không commit ID vào theme).
- **KHÔNG cần đổi cấu hình Cloudflare/APO** (Plan B) — chỉ đảm bảo member không set cookie WP.
- **Redis 7.4** (sẵn có) làm session store; dùng kết nối phpredis theo pattern `inc/ajax-tracking.php` (reuse `rf_redis()`). Hằng config `define()` (để chỉnh dễ — #10): `RF_SESSION_TTL = 30 * DAY_IN_SECONDS` (**fixed window, KHÔNG sliding**), `RF_ACTION_LIMIT = 3`, `RF_ACTION_WINDOW = 60`.
- Session id = opaque ngẫu nhiên (`random_bytes`), không cần secret ký riêng (state ở Redis).

## 10. Thứ tự implement (phases)
0. **(KHÔNG đổi APO)** Xác lập baseline cache trên staging → verify trang front-end trả `cf-cache-status: HIT` cho cả guest. Đây là mốc để phase sau đối chiếu: sau khi member login vẫn phải `HIT` (không `DYNAMIC`).
1. Tạo bảng `member` + `member_comments` (`auth/setting-member.php`) ở activation → verify: schema + index đúng (§14.1). (KHÔNG còn role member/wp-admin/admin-bar — §4.1.)
2. REST login + session (`auth-google.php`): verify JWT + **match/tạo member trên `member`** + tạo session Redis (`{member_id}`) + set cookie `rf_member` + trả display → JS ghi `localStorage`. → verify: login Google tạo được row `member`; **member browse front-end vẫn `HIT` cache** (không set cookie WP); reload thấy avatar (từ localStorage). **Test luôn webview FB/Zalo → banner mở-browser** (§13.P).
3. Gate download **phương án C** (`hide=members` + `rf/v1/download-url` trả `{url}` + `single-rom.php` + bỏ nhồi URL/token trong `download.php`) → verify: guest/không session bấm tải post `members` → `401` (mở modal); member lấy được `{url}` và tải; **trang `/download/` vẫn cache-safe** (0 per-user secret trong HTML); block → `403` ngay. *(Rating KHÔNG làm — hoãn §4.8.)*
4. Comment qua `rf/v1/comment` → verify: guest không post được; member comment = **`user_id=0` + `comment_meta[rf_member_id]` + row `member_comments`** (§14.3/§14.6); `wp-comments-post.php` ẩn danh bị chặn; **comment pending hiện client-side + purge cache** (§13.M/N); `my-comments` trả pending+approved.
5. Block user → verify: set `member.blocked` (KHÔNG xoá session — không index); member bị đá ở action kế nhờ re-check `blocked`; login lại bị từ chối.
6. Trang quản lý members → verify: list + sort `created_at` DESC + block/unblock; **`COUNT(*) member` rẻ (indexed)**; §13.3.4 moot sau tách bảng.
7. **Trang "Tài khoản" (member)** → render `rf/v1/my-comments` + `rf/v1/my-reports` (pending+approved) client-side; verify: member xem đúng comment/report của mình, endpoint per-user không đụng cache. *(my-ratings để dành khi làm rating — §4.8.)*

### 10.P2 — Chi tiết P2 (auth + session, step-by-step) — UI trước

> Xuyên suốt: **KHÔNG** gọi `is_user_logged_in()`/`wp_get_current_user()` trong template; trạng thái login do JS + `localStorage` quyết. Điểm chèn UI (theo `header.php` thực tế): desktop **sau `</nav>` (L105)**, mobile **trong `.mobile-menu-content` (sau L139)**, modal đặt 1 lần ở `footer.php`.

**P2.0 · UI scaffold (làm trước, chưa cần backend)** — `header.php`, `footer.php`, `auth/js/auth.js` (khung)
- Desktop: sau L105 thêm `<div id="rf-account" data-state="guest">` render mặc định **nút "Đăng nhập"**; JS thay bằng avatar+tên+dropdown (Logout, Tài khoản) khi login.
- Mobile: sau L139 thêm `<div id="rf-account-mobile">` tương tự.
- Modal (`footer.php`): overlay + slot nút Google GSI + banner webview (ẩn sẵn). Dùng chung header + nút "Đăng nhập để tải" ở single.
- JS khung: đọc `localStorage.rf_display` (còn hạn) → swap UI; bấm "Đăng nhập" → mở modal. Chưa nối backend.
- *verify:* front-end vẫn `HIT`; set tay `localStorage.rf_display` → reload thấy avatar; xoá → về nút login; modal mở/đóng. **Chốt look & feel trước khi đụng auth.**

**P2.1 · Config + nạp GSI** — `wp-config.php`, `functions.php`, `auth.js`
- `define('GOOGLE_CLIENT_ID', ...)`; Google Cloud Console OAuth, Authorized JS origins = domain.
- **Lazy-load** `accounts.google.com/gsi/client` chỉ khi mở modal; `renderButton` (user gesture), KHÔNG One Tap auto (§13.P).
- *verify:* chỉ mở modal mới nạp gsi/client; nút Google hiện.

**P2.2 · REST route + login skeleton** — `auth/auth-google.php`, `functions.php`
- Namespace `rf/v1`, `POST /login` nhận `{credential, nonce}`; tạm trả stub (chưa verify) để nối luồng.
- JS: GSI callback → `fetch('/wp-json/rf/v1/login', {credentials:'include', headers:{'X-RF-Auth':'1'}})`. **Pretty URL**, không `?rest_route=` (§12.C).
- *verify:* login → `/wp-json/rf/v1/login` trả 200; chưa đụng DB.

**P2.3 · Verify ID token (ranh giới bảo mật thật)** — `auth/auth-google.php` (+JWKS helper)
- Fetch JWKS Google, cache Redis theo `kid` (reuse `rf_redis()`); verify **RS256** + `aud`/`iss`/`exp`/**`iat`≤~5'** (§13.O) + khớp `nonce` client-gen. Fail → `401`.
- Degraded (Redis chết): báo lỗi mềm, **KHÔNG** fallback `wp_set_auth_cookie` (§12.D).
- *verify:* token thật pass; sửa payload/hết hạn/`aud` sai → `401`; ngắt Redis → lỗi mềm, không sập.

**P2.4 · Match/tạo member (cần P1)** — `auth/auth-google.php`
- `SELECT id, blocked FROM wphh_member WHERE google_sub=?` → có: dùng + update `last_login_at`; không: `INSERT`. `blocked=1` → `403` (không tạo session).
- *verify:* lần đầu tạo 1 row; login lại không trùng; `blocked=1` → `403`.

**P2.5 · Tạo session + set cookie** — `auth/auth-google.php`
- `session_id = bin2hex(random_bytes(32))`; `SET rf_sess:{id}={member_id,exp}` TTL `RF_SESSION_TTL`. **KHÔNG ZSET.**
- `Set-Cookie rf_member` — HttpOnly, Secure, SameSite=Lax, **Path=`/wp-json/rf/v1`** (không Path=`/`). KHÔNG `wp_set_auth_cookie`. Trả `{success, display:{name,avatar,exp}}`.
- *verify:* có `rf_sess:*` trong Redis; `Set-Cookie` đúng path+flags; KHÔNG có `Set-Cookie: wordpress*/wp-*`.

**P2.6 · JS success + logout** — `auth.js`, `auth/auth-google.php` (`POST /logout`)
- Success: ghi `localStorage.rf_display`, đóng modal, swap UI không reload.
- `logout`: `DEL rf_sess:{id}` + expire cookie đúng Path (§13.U); JS xoá localStorage + fire `storage`.
- *verify:* reload vẫn login; logout → về guest, `rf_sess` xoá, cookie expire.

**P2.7 · Đồng bộ trạng thái client** — `auth.js`
- Wrapper `fetch`: `401`→xoá localStorage + về "Đăng nhập"; `403`→"đã khoá", không reopen modal (§13.b).
- `storage` event sync đa-tab; detect webview (`FBAN/FBAV/FB_IAB/Zalo`) → banner mở-browser, ẩn nút Google (§13.P).
- *verify:* 2 tab logout đồng bộ; `rf_sess` hết hạn → action kế `401` tự revert; UA webview → banner.

**P2.8 · Cache-safety gate (nghiệm thu P2)**
- Grep gate template front-end không chứa `is_user_logged_in|current_user_can|wp_get_current_user|wp_set_auth_cookie` (§13.Q).
- Assert: member login browse `/`/single/archive → **`cf-cache-status: HIT`** + **zero `Set-Cookie`** front-end (§13.S); HTML thô guest vs member giống hệt.
- *verify:* pass cả 2 → P2 xong (điều kiện sống-còn trước P3).

> Thứ tự: **P2.0 → P2.8**. P2.0–P2.2 dựng khung nhìn/bấm được ngay; P2.3–P2.5 lõi bảo mật; P2.6–P2.8 hoàn thiện + nghiệm thu.

## 11. Quyết định đã chốt (trước đây là open questions)
- [x] Client ID lưu ở **`wp-config.php`** (hằng `GOOGLE_CLIENT_ID`).
- [x] Login qua **modal popup** mở từ **nút "Đăng nhập" trên header** (cần thêm nút — header hiện chưa có). Tái dùng cho single restricted.
- [x] Nút Block **chỉ ở trang quản lý members**, không thêm vào Users list mặc định.
- [x] **Giữ Cloudflare APO** (không gỡ); member dùng **session riêng Redis + cookie `rf_member`** (scope `/wp-json/rf/v1`), KHÔNG `wp_set_auth_cookie` (đã verify APO không cache được user có cookie WP).
- [x] Display login-state qua **`localStorage`**, không cookie front-end.
- [x] Enforce download (phương án C §4.5) = **endpoint `rf/v1/download-url` trả URL file cho post `members`** (verify `rf_member`); comment = **`rf/v1/comment`** (member không phải WP-logged-in nên không dùng luồng WP mặc định).

---

## 12. Review findings — BẮT BUỘC xử lý (2026-07-21c)

Kết quả review kiến trúc + bảo mật. 🔴 = phải vá trong plan/impl; 🟡 = nên; 🟢 = đồng bộ doc.

### 🔴 Cao
- **A. IP thật sau Cloudflare.** Rate-limit/abuse (§7) phải đọc **`CF-Connecting-IP`** (chỉ tin khi request từ dải IP Cloudflare), KHÔNG dùng `REMOTE_ADDR` (= IP Cloudflare → rate-limit vô dụng hoặc block nhầm toàn bộ). **Kiểm luôn `get_user_IP()` trong `inc/ajax-tracking.php`** đã đúng chưa.
- **B. Comment dùng `wp_new_comment()`, KHÔNG `wp_insert_comment` thô** (§4.7). Thô bỏ qua moderation/chống-trùng/chống-flood/Akismet → hở spam ở signup mở + 26M traffic. Chỉ tự set `user_id` sau verify `rf_member`.
- **C. REST luôn gọi pretty URL `/wp-json/rf/v1/...`.** Cookie `rf_member` scope `/wp-json/rf/v1` **chỉ được gửi** ở dạng pretty; nếu JS lỡ dùng `?rest_route=/rf/v1/...` (path=`/`) → cookie không gửi → auth hỏng. Pin dạng URL trong JS, không dùng `rest_route`.
- **D. Degraded-mode khi Redis chết** (bài học tracking). Redis down → login/verify/token fail: trả lỗi "thử lại sau", **guest vẫn duyệt bình thường, site KHÔNG sập**; **tuyệt đối không fallback sang `wp_set_auth_cookie`** (sẽ set cookie WP → phá APO cache).

### 🟡 Trung bình
- **E. `wp_is_mobile()` × APO cache-by-device.** `hide=download` cũ render khác nhau PC/mobile → chỉ đúng nếu APO bật "cache by device type". Nút của `hide=members` **phải render giống hệt PC/mobile** (KHÔNG nhánh `wp_is_mobile()`). Verify cấu hình device-cache hiện tại.
- **F. CSRF REST + làm rõ `g_csrf_token`.** `SameSite=Lax` chặn phần lớn; endpoint đổi-trạng-thái (`comment`) thêm **check `Origin` + custom header `X-RF-Auth`** (cross-site không set được custom header). `g_csrf_token` **chỉ áp dụng GSI redirect/login_uri mode**; nếu dùng callback+fetch (modal) thì không có — bảo mật dựa trên verify ID token + Origin check. Sửa câu chữ ở §4.2 cho khớp mode sẽ chọn.
- **G. Auto-link email thu hẹp** (§4.2): chỉ auto-link vào account role `member`/`subscriber`/không-cap (đã chặn admin/editor/author) — tránh dính account cũ role lạ do luồng signup chết để lại.
- **H. Rác `rf_user_sessions:{user_id}`** (§4.2b): id session hết-hạn-TTL vẫn nằm trong set → phình dần. → **Thay bằng ZSET + prune (§13.T).**

### 🟢 Đồng bộ doc / lưu ý
- **I.** ✅ Đã sửa §1b điểm 1: "cookie JS-readable" → **localStorage**.
- **J. Redirect login khi chưa auth:** login là **modal-only**. Phương án C: JS xử lý client-side — post `members` chưa login bấm tải → **mở modal ngay** (không điều hướng qua `download.php`). *(Câu cũ "download.php không token → ?login=1" là kiến trúc token đã bỏ — §4.5.)*
- **K. Download `public`/`download` KHÔNG đổi** — chỉ `hide=members` đi qua endpoint (§4.5). Public giữ render URL server-side + `get_download_token()` như cũ.
- **L. CSP:** nếu có Content-Security-Policy, thêm `img-src ... googleusercontent.com` cho avatar. Googlebot = guest → thấy bản cache công khai, **không cloaking** ✓.

### Token download — MOOT dưới phương án C (§4.5)
- ~~Xác nhận `download-token.php` bind post_id + TTL~~ → **không còn dùng token làm WP-side gate.** `get_download_token()` chỉ là token cho **host tải ngoài** (giữ nguyên); gate member = verify `rf_member` ở endpoint `download-url`.

---

## 13. Review findings — round 2 (2026-07-22)

Vòng review thứ 2. 🔴 = phải vá; 🟡 = nên; 🟢 = lưu ý. Lettering tiếp §12 (M→).

### 🔴 Cao

- **M. Comment "chờ duyệt" của member không hiển thị lại → duplicate.** WP bình thường cho tác giả thấy comment pending của mình nhờ cookie `comment_author_*` — mà Plan B cố tình KHÔNG set (đúng, vì đó chính là cookie APO bypass). Hệ quả: member submit → HTML cache không đổi → không thấy gì → submit lại → trùng.
    - **Fix:** sau khi `rf/v1/comment` trả `200`, **JS tự chèn comment vào DOM client-side** + badge "đang chờ duyệt". Server **dedup** theo `(user_id, post_id, hash(nội dung), cửa sổ thời gian ngắn)`. (Comment vào moderation queue thì không hiện trong HTML công khai — đúng, không đụng.)

- **N. Cache purge khi comment qua REST.** Comment hiển thị nằm trong HTML cache. `wp_new_comment()` có fire `wp_insert_comment`/`comment_post`, nhưng **phải verify plugin CF/APO thực sự hook mấy cái đó để purge** khi đi qua REST (không phải qua `wp-comments-post.php`). Nếu không → comment mới không hiện tới hết TTL.
    - **Test:** member comment → `curl` lại URL post → phải thấy comment (hoặc thấy cache đã purge/`MISS` rồi `HIT` lại). Comment vào queue thì không hiện — ok.

- **O. Replay window của ID token — KHÔNG dùng server nonce trên trang cache.** GSI cho set `nonce` chống replay, nhưng nonce server-rendered sẽ **stale trên cache** (đúng lý do đã bỏ WP nonce). Chỉ check `exp` là chưa đủ.
    - **Tối thiểu:** reject token có `iat` cũ hơn ~2–5 phút (ngoài `aud`/`iss`/`exp`).
    - **Chặt hơn (khuyến nghị):** **client tự sinh nonce random** → `sessionStorage` → truyền vào `google.accounts.id.initialize({ nonce })` → Google nhét vào claim `nonce` của JWT → server so khớp. Nonce **client-gen nên KHÔNG đụng cache** — đây là cách sạch, hơn iat-only.
    - Chốt lại §4.2: bỏ hẳn chữ "g_csrf_token" ở luồng modal — **không tồn tại** ở callback+fetch mode. Ranh giới CSRF = **Origin/Referer check server-side + custom header `X-RF-Auth`** cho endpoint đổi-trạng-thái; login dựa verify ID token (+ Origin để chặn login-CSRF).

- **P. GSI KHÔNG chạy trong in-app webview (Facebook/Zalo) — blocker, không phải "test kỹ".** Google **chính thức không hỗ trợ** đăng nhập user trong Android/iOS webview (docs GSI supported-browsers). FedCM đã **bắt buộc** cho mọi One Tap + Sign-In button từ 8/2025 → ta ở FedCM dù muốn hay không; Safari **không có kế hoạch** FedCM (Apple đẩy passkey), iOS Chrome = WebKit nên chịu ràng buộc Safari. Với tỷ trọng traffic social-share lớn (26M/tháng), đây là vấn đề kiến trúc.
    - **Fix:**
        1. **Dùng GSI button (user gesture) trong modal, KHÔNG dựa One Tap auto** (One Tap auto/iframe im lặng vỡ khi 3p-cookie bị chặn; fallback duy nhất tin được là top-level redirect → tụt conversion).
        2. **Detect in-app webview** (UA `FBAN/FBAV/FB_IAB/Zalo/...`) → banner "Mở trong Chrome/Safari để đăng nhập".
        3. **Graceful degrade — gate của ta đã lo sẵn:** webview user KHÔNG login được nhưng **vẫn browse như guest** (chỉ download/comment gated). Không force-login để đọc → không mất traffic, chỉ mất conversion login. Chấp nhận được.

- **Q. PII grep gate pre-deploy (đừng test bằng mắt).** Rủi ro chết người nhất: 1 chỗ lỡ render per-user vào HTML → cache dính data 1 member phát cho cả 26M.
    - **Gate:** CI/pre-deploy **fail** nếu template front-end còn `is_user_logged_in|current_user_can|wp_get_current_user|wp_set_auth_cookie`.
    - **Assert tự động:** response front-end của member **KHÔNG** có `Set-Cookie: wp*/wordpress*` **và** trả `cf-cache-status: HIT`.

### 🟡 Trung bình

- **R. Rate-limit comment theo `user_id`, không chỉ IP.** VN mobile phần lớn CGNAT → limit theo IP chặn nhầm cả cụm hoặc vô dụng. Signup mở + 26M traffic → **per-user + Akismet + moderation** (bổ sung trục user_id cho §7/§12.B).

- **S. Audit MỌI cookie front-end cho member — không chỉ `wp-*`.** Plugin nào start PHP session (`PHPSESSID`) hay set cookie path `/` → dù APO không bypass theo cookie đó, response vẫn có `Set-Cookie` → hành vi cache khó lường.
    - **Rule cứng:** front-end request của member = **ZERO `Set-Cookie`**.
    - **Ca đặc biệt:** user cũ còn dính `wordpress_logged_in_*` từ luồng login chết / lúc test → bị APO bypass **vĩnh viễn** tới khi cookie hết hạn. Cân nhắc chủ động clear.

- **T. `rf_user_sessions`: ✅ CHỐT (2026-07-22f) — BỎ HẲN index.** Không tạo ZSET/Set nào cho session của member. Lý do: §4.3 đã re-check `member.blocked` ở MỌI endpoint động → block hiệu lực tức thì, không cần enumerate+DEL session; session tự hết theo TTL → không có leak để fix. Đánh đổi: mất "logout mọi thiết bị" (chấp nhận). Nội dung Set/ZSET dưới đây giữ làm lịch sử. ~~`rf_user_sessions:{user_id}`: Set → ZSET (hoặc bỏ hẳn). Thay §12.H.~~
    - **ZSET:** member = `session_id`, **score = `exp` timestamp**. Prune: `ZREMRANGEBYSCORE key -inf {now}` (chạy khi block + định kỳ). **Set TTL cho chính key ZSET** = max session TTL, refresh mỗi lần `ZADD` → user ngừng login vài tháng thì cả key tự bay. (Đúng pattern `RScoredSortedSet` delayed-queue đã dùng ở AutoPayment.)
    - **Hoặc bỏ hẳn index:** vì §4.3 đã re-check `member_blocked` ở MỌI endpoint → block đã hiệu lực tức thì **không cần** enumerate+DEL session. Index chỉ phục vụ (a) reclaim memory sớm, (b) "logout mọi thiết bị". Không cần 2 thứ đó → bỏ index, để session hết theo TTL → không có leak để fix.

- **U. Logout — `Set-Cookie` expire đúng `path=/wp-json/rf/v1`.** Xoá cookie sai path → browser không xoá → cookie rác còn (vô hại nhưng bẩn). Kèm: clear `localStorage` + **fire `storage` event** để sync đa-tab.

### 🟢 Lưu ý

- **V. SEO của gate download.** Post `members` → Googlebot (=guest) thấy "Đăng nhập để tải" → **không cloaking** (ok về luật), nhưng gate rộng thì **mất giá trị landing + tăng bounce**. → **Gate chọn lọc** post cao giá trị, đừng gate đại trà.

---

### localStorage — Race Condition & Đồng bộ trạng thái Client (mở rộng)

**Vấn đề:** `localStorage rf_display` là trạng thái hiển thị phía client, **không phải nguồn sự thật**. Lệch backend trong 3 tình huống: (a) mở nhiều tab, logout ở 1 tab; (b) session Redis hết TTL / bị Admin block trong khi `rf_display` còn; (c) session bị `DEL` ở thiết bị khác.

**Nguyên tắc:** `localStorage` chỉ là lớp hiển thị (cosmetic). **Ranh giới thật luôn server-side** — mọi endpoint `rf/v1/*` re-check `member_blocked` + tồn tại `rf_sess:{id}`. Dù `rf_display` stale, mọi action thật vẫn bị chặn ở REST. → KHÔNG "tin" localStorage như credential; nó chỉ để render UI đỡ gọi server mỗi pageview.

**a) Đồng bộ đa tab — `storage` event**
- `window.addEventListener('storage', ...)` trong `auth.js`: 1 tab ghi/xoá `rf_display` → các tab **khác** nhận event → revert UI về Guest (hoặc cập nhật avatar), **không reload**.
- ⚠️ `storage` event **KHÔNG fire ở chính tab gây thay đổi** → tab đang logout/login phải **tự update UI trực tiếp**, không trông chờ event lo cho chính nó.

**b) Wrapper `fetch()` tập trung xử lý `401`/`403`**
Mọi call REST (`/download-url`, `/comment`, `/logout`) đi qua 1 wrapper chung:

| Mã | Ý nghĩa | Hành động client |
|---|---|---|
| `401 Unauthorized` | Session hết hạn / không có / bị revoke | Xoá `rf_display` → revert UI về "Đăng nhập" → cho mở lại modal Google |
| `403 Forbidden` | Tài khoản bị **block** | Xoá `rf_display` → revert UI → hiện "Tài khoản của bạn đã bị khoá". **KHÔNG mở lại modal Google** |

- ⚠️ `403` (blocked): **không reopen modal login** để tránh loop (login lại → handler §4.3 cũng reject → lại `403`). Hiện thẳng "đã khoá".

**c) Self-heal khi hết hạn (localStorage ↔ Redis)**
- `rf_display` nói "đã login" nhưng session Redis đã hết/revoke → action động trả `401`/`403` → wrapper (b) tự xoá localStorage + revert UI. Không cần poll server.

**d) Logout**
- JS: `localStorage.removeItem('rf_display')` (→ trigger `storage` event sang tab khác) + tự update UI tab hiện tại.
- Server (`rf/v1/logout`): `DEL rf_sess:{id}` + `ZREM` khỏi ZSET index + set `rf_member` hết hạn **đúng `path=/wp-json/rf/v1`** (§13.U).

---

### DB index (§13.3.4 — hiệu chỉnh lý do)

- **`wp_users(user_registered)`** — đúng, giữ. Tránh filesort cho `ORDER BY user_registered DESC LIMIT`.
  ```sql
  ALTER TABLE wp_users ADD INDEX idx_user_registered (user_registered);
  ```
- **`wp_usermeta(meta_key(191), meta_value(191))`** — đúng và **quan trọng, nhưng beneficiary thật là lookup `google_id` mỗi lần login** (selectivity cao, unique/user, hot path 100K login) — **KHÔNG phải list `is_member`**.
  ```sql
  ALTER TABLE wp_usermeta ADD INDEX idx_meta_key_value (meta_key(191), meta_value(191));
  ```
    - ⚠️ **`is_member=1` selectivity ≈ 0** vì **post-golive ~mọi user đều là member** (100K member vs vài staff lẻ) → filter này KHÔNG thu hẹp list, query vẫn xử ~100K rows. Index chỉ đổi `wp_capabilities LIKE '%member%'` scan → equality (lợi vừa phải), không phải phép màu.
    - ⚠️ WP core **đã có sẵn index `meta_key(191)`** trên `wp_usermeta`. Composite này thêm `meta_value` nên có giá trị cho equality key+value (`google_id`), không redundant.
- **Mục tiêu "<10ms":** đạt cho **point-lookup `google_id`**; **KHÔNG đảm bảo cho list sorted ở OFFSET sâu**. Vì `is_member` không lọc gì, thứ quyết định perf list là **keyset/seek pagination** (`WHERE user_registered < :last ORDER BY ... DESC LIMIT 50`) thay `LIMIT/OFFSET` mặc định của `WP_List_Table` — `OFFSET 100000` chết bất kể index. Thực tế admin xem trang đầu (mới nhất) → OFFSET nông, có thể sống với default; page sâu thì phải keyset.
- **Đơn giản hoá:** nếu **mọi** user là member, "list members" ≈ "list all users order by registered" → index `user_registered` gánh chính; có thể **bỏ filter `is_member`, chỉ exclude vài staff role**.
- **DDL:** MariaDB add secondary index = `ALGORITHM=INPLACE, LOCK=NONE` (online, không block), 100K rows vài giây — vẫn chạy giờ thấp tải cho chắc.

> ⚠️ **§13.3.4 phần lớn MOOT sau khi tách bảng (§14, 2026-07-22c):** member không còn ở `wp_users`/`wp_usermeta` → không cần index `user_registered`/`usermeta(google_id)`; lookup login = `UNIQUE(google_sub)` trên `member`; list/sort/count = cột indexed trên `member`. Giữ mục này để đối chiếu lịch sử. Keyset pagination vẫn áp dụng cho `member` khi page sâu.

---

## 14. Data store — bảng riêng `member` (chốt 2026-07-22c)

Đảo **quyết định #1**: member sang **bảng riêng**, KHÔNG `wp_users`. Lý do: khi session tự quản (Plan B), `wp_users` không còn cho auth (`is_user_logged_in`/cookie WP đều bị bỏ) → thứ nó "tặng" chỉ còn comment-native + admin UI, mà cả hai đều thay được. Ngược lại `wp_users` bắt trả giá: EAV bloat (~15 usermeta mặc định/member → ~1.5M row ở 100K), rủi ro escalation (member & staff chung bảng → phải viết luật chống auto-link vào admin), và toàn bộ khổ perf §4.4/§13.3.4. Bảng riêng xoá sạch mấy thứ đó.

### 14.1 Schema

```sql
CREATE TABLE wphh_member (              -- $wpdb->prefix = wphh_
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  google_sub    VARCHAR(32)     NOT NULL,          -- 'sub' claim = danh tính thật (ổn định)
  email         VARCHAR(191)    NOT NULL,          -- attribute (có thể đổi), 191 cho index utf8mb4
  name          VARCHAR(255)    NULL,
  avatar_url    VARCHAR(512)    NULL,
  blocked       TINYINT(1)      NOT NULL DEFAULT 0,
  created_at    DATETIME        NOT NULL,          -- ngày đăng ký (sort admin)
  last_login_at DATETIME        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_google_sub (google_sub),           -- match login = point lookup
  KEY idx_email (email),                           -- auto-link/kiểm trùng email
  KEY idx_blocked_created (blocked, created_at)     -- list admin (active, mới nhất)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE wphh_member_comments (            -- index "comment của tôi" (§14.3)
  comment_id  BIGINT UNSIGNED NOT NULL,            -- = wp_comments.comment_ID
  member_id   BIGINT UNSIGNED NOT NULL,
  post_id     BIGINT UNSIGNED NOT NULL,
  status      VARCHAR(20)     NOT NULL,            -- mirror: approved/hold/spam/trash
  created_at  DATETIME        NOT NULL,
  PRIMARY KEY (comment_id),
  KEY idx_member_created (member_id, created_at)   -- list comment của 1 member, mới nhất
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- **`google_sub` là identity** (ổn định), `email` chỉ là attribute (Google cho đổi email) → match/tra theo `sub`, không theo email.
- Tạo bảng ở activation hook (`dbDelta` hoặc raw `CREATE TABLE IF NOT EXISTS`) — tên thật có prefix `$wpdb->prefix` (= **`wphh_`**): **`wphh_member`**, **`wphh_member_comments`**. Code luôn tham chiếu qua `$wpdb->prefix . 'member'`, không hardcode.

### 14.2 Login / session (thay §4.2/§4.2b)
- Match: `SELECT id, blocked FROM member WHERE google_sub = ?`. Có → dùng. Không → `INSERT` member mới (`created_at = now`).
- **KHÔNG còn luật chống-escalation §4.2/§12.G** — member & staff khác bảng, không thể nhầm. (Nếu vẫn muốn: cấm `email` trùng email của 1 `wp_users` staff, nhưng không bắt buộc.)
- Session Redis `rf_sess:{id}` lưu **`member_id` (`member.id`)**, không phải `wp_user_id`.
- Block check mỗi request động = đọc `member.blocked` theo PK (`member_id`) — 1 point lookup, rẻ.

### 14.3 Comment (thay §4.7) — "biết ai comment + member xem comment của mình"
Giữ `wp_comments` làm **nguồn sự thật nội dung + moderation** (Akismet/dedup/threading/admin UI đều chạy trên đó — không rebuild lại). Attribution nằm ngoài `wp_users`:

1. **Submit** (`rf/v1/comment`, đã verify `rf_member`): `wp_new_comment()` tạo row **guest** (`user_id = 0`), set `comment_author` = `member.name`, `comment_author_email` = `member.email`. → admin thấy tên, Akismet/moderation vẫn hoạt động.
2. **Link owner:** ghi `comment_meta[comment_id]['rf_member_id'] = member_id`. Đây là **link bền** — nguồn để rebuild bảng index nếu cần.
3. **Index truy xuất:** `INSERT member_comments (comment_id, member_id, post_id, status, created_at)`. Đây là thứ khiến **"comment của tôi" nhanh**: `SELECT ... WHERE member_id = ? ORDER BY created_at DESC` — indexed, không đụng `commentmeta` (tránh reverse-lookup theo `meta_value`).
4. **Đồng bộ status (chỉ giữ pending + approved):** hook `transition_comment_status` → `UPDATE member_comments.status` khi sang `hold`/`approved`; **`DELETE` khỏi `member_comments` khi sang `spam`/`trash`** → member KHÔNG thấy comment bị gỡ. (`comment_meta.rf_member_id` + `wp_comments` vẫn giữ để admin/audit.)

**Endpoint "comment của tôi":** `GET /wp-json/rf/v1/my-comments` (APO bypass sẵn) → verify `rf_member` → query `member_comments` theo `member_id` → chỉ có **pending + approved** (spam/trash đã bị xoá khỏi bảng index) → JOIN `wp_comments` lấy nội dung → trả JSON (JS render ở trang "Tài khoản"). Endpoint động per-user → **không đụng HTML cache**. Trả cả pending → giảm nhẹ §13.M.

> Vì sao có cả `comment_meta` lẫn `member_comments`: meta = link bền/nguồn sự thật của quan hệ; bảng index = đường đọc nhanh + đếm rẻ per-member. Mất bảng index → rebuild từ meta. Đừng chỉ dùng `commentmeta` join vì reverse-lookup theo `meta_value` ở scale là đúng cái EAV mình đang tránh.

### 14.4 Tác động lan sang phần khác (phải rà khi impl)
- **§4.1 role `member`:** BỎ. Không tạo role/`wp_users` cho member nữa → không còn lo chặn wp-admin/adminbar (member không phải WP user). Việc tạo bảng đặt ở `auth/setting-member.php`.
- **§4.3 block:** cột `member.blocked` thay usermeta `member_blocked`. Block = set cột (KHÔNG xoá/enumerate session — không index §13.T); hiệu lực nhờ re-check mọi endpoint.
- **§4.4 admin page:** query `member` (đã sửa inline).
- **§5 data model:** đã sửa.
- **§6 files:** +tạo 2 bảng ở activation; `auth/auth-google.php` thao tác `member` thay `wp_insert_user`; +endpoint `my-comments`; +hook `transition_comment_status`. `admin-members.php` query bảng riêng.
- **§13.3.4 index:** moot (đã note).
- **⚠️ Reports `wphh_user_report` (§1):** hiện key theo `wp_users`. Nếu member report thì phải key theo **`rf_member_id`** — RÀ và đổi FK/cột, hoặc quyết định report vẫn là guest. **Cần xác nhận trước khi impl.**
- **GDPR:** tự viết export/erase cho `member` + `member_comments` + `comment_meta.rf_member_id` (mất tooling native của WP user — chấp nhận, bảng phẳng nên đơn giản).

### 14.5 Cái mất khi rời `wp_users` (chấp nhận)
- Không có badge "registered user" / link user-profile native trong admin comment list (thay bằng tên + `rf_member_id` trong meta).
- Không auto tương thích plugin nào đó sau này cần WP user cho member (speculative).
- Phải tự làm GDPR tooling (nhỏ).
  → Đổi lại: hết EAV bloat, hết escalation surface, hết khổ perf §4.4/§13.3.4, blast radius tách khỏi account staff.

### 14.6 Phân biệt STAFF vs MEMBER (author namespace) — CỐT LÕI

Comment có **2 tác nhân**: staff (`wp_users`) và member (`member`). `member.id` và `wp_users.ID` là **2 chuỗi AUTO_INCREMENT độc lập** → `id=5` tồn tại ở cả hai = **2 thực thể khác nhau**. Trùng giá trị là **bình thường & vô hại** — miễn id luôn được diễn giải trong đúng bảng của nó. Nguy hiểm **chỉ** xảy ra khi code chéo namespace (đem `rf_member_id` gọi `get_userdata()`).

**Cái phân biệt nằm ở chính comment, KHÔNG ở con số:**

| | Staff comment | Member comment |
|---|---|---|
| `wp_comments.user_id` | `> 0` (= `wp_users.ID`) | **`= 0`** (guest row) |
| `comment_meta[rf_member_id]` | không có | **có** (= `member.id`) |
| Đường tạo | WP native (đang login wp-admin) | REST `rf/v1/comment` |

→ **Discriminator:** có `rf_member_id` meta ⇒ member; else `user_id>0` ⇒ staff; else guest (đã chặn guest nên chỉ còn 2). Vì member comment **luôn `user_id=0`**, hai nhánh không bao giờ chồng nhau **kể cả khi `rf_member_id == user_id` về số**.

**4 INVARIANT bắt buộc** (an toàn nằm ở đây, không ở việc tránh trùng số):
1. Member comment **luôn** `user_id=0`; **KHÔNG BAO GIỜ** ghi `member_id` vào `user_id`.
2. **KHÔNG BAO GIỜ** đem `rf_member_id` gọi `get_userdata()` / coi như `wp_users.ID`; và ngược lại.
3. `member_comments.member_id` (và mọi `rf_member_id`) join **CHỈ** tới `member`.
4. Tên khoá tự mang namespace: `rf_member_id` (member) vs `user_id` (WP staff) — **CẤM** biến chung chung `author_id`.

**Mọi chỗ đọc author đi qua 1 helper duy nhất** (không ai tự chế logic):
```php
function rf_resolve_comment_author($c){
  $mid=(int)get_comment_meta($c->comment_ID,'rf_member_id',true);
  if($mid>0) return ['type'=>'member','id'=>$mid];             // → member
  if((int)$c->user_id>0) return ['type'=>'staff','id'=>(int)$c->user_id]; // → wp_users
  return ['type'=>'guest','id'=>0];                            // (không nên xảy ra)
}
```

> Cùng 1 người vừa là staff (có `wp_users`) vừa Google-login thành member (có `member`) = **2 danh tính ở 2 namespace**, comment mỗi bên tách biệt đúng — không cần cấm, không xung đột.

### 14.7 Comment = RAW TEXT tuyệt đối (không HTML, không file, không auto-link)

Quyết định: comment là **raw text** — user KHÔNG viết HTML, KHÔNG đính file, và **URL cũng chỉ là text (KHÔNG auto-link)**. Lợi: không stored-XSS, không link clickable trong comment → triệt tiêu hoàn toàn giá trị spam link.

- **Frontend:** `<textarea maxlength=N>` thuần — **không TinyMCE/toolbar, không ô upload**. Hiện bộ đếm ký tự.
- **Input (server, REST `rf/v1/comment`):** `$content = wp_strip_all_tags( wp_unslash($raw) )` (giữ `\n`) → bóc mọi tag. Belt-and-suspenders: filter global `pre_comment_content` strip tags cho **mọi** comment (kể cả staff) để đảm bảo "always text". **Hard-limit độ dài server-side** (vd 1–2K ký tự) — không chỉ tin `maxlength` client. Vẫn qua `wp_new_comment()` (giữ Akismet/moderation/dedup — §12.B).
- **Output (render list):** `esc_html($content)` toàn bộ + xuống dòng bằng CSS `white-space: pre-wrap`. **KHÔNG `make_clickable`, KHÔNG linkify** → URL hiển thị dạng text thô, không thành `<a>`. `comment_author` (tên Google) cũng `esc_html`.
- **Không file:** comment không bao giờ nhận upload/attachment.
- **Đồng bộ §13.M:** client insert pending chỉ chèn text đã `esc_html` — không markup nên trivial, không lo XSS.
- **Rate-limit:** mọi lần submit comment tính vào **throttle hành động member chung (§16)**.

---

## 15. Reports — member-only, có auth (chốt 2026-07-22c)

Đổi report từ ẩn danh/`wp_users` sang **bắt buộc member auth**; staff (user hệ thống) **KHÔNG gửi report**; member **xem được report của mình**.

### 15.1 Bảng (`wphh_user_report` — custom, sửa)
- **Thêm cột `rf_member_id BIGINT UNSIGNED NOT NULL`** + `KEY idx_member_created (rf_member_id, created_at)` để list "report của tôi" nhanh.
- Report giờ **luôn thuộc 1 member** (không guest, không staff) → `rf_member_id` là owner key, join **chỉ** tới `member` (invariant §14.6.3).
- ⚠️ **Row cũ** (nếu có, key theo `wp_users` từ hệ chết): vì member là greenfield → thực tế chưa có member report nào. Quyết định: giữ row cũ làm **legacy** (cột mới NULL/0) hoặc dọn. Rà trước khi migrate cột.

### 15.2 Luồng
- **Submit:** `POST /wp-json/rf/v1/report` (kèm `rf_member`) → verify session + block → **throttle hành động chung (§16)** → ghi row với `rf_member_id`. Chưa login/không session → `401` → JS mở modal.
- **Staff không gửi được:** endpoint yêu cầu `rf_member` session; staff-qua-staff (chỉ có login wp-admin) không có session này → không gửi. *(Nếu 1 staff cũng Google-login thì họ đang hành động với tư cách member — §14.6; chấp nhận.)*
- **"Report của tôi":** `GET /wp-json/rf/v1/my-reports` → verify `rf_member` → `WHERE rf_member_id = ?` (indexed) → JSON render trang "Tài khoản". Endpoint động per-user, không đụng cache.
- **UI cache-safe:** nút "Report" render mặc định; JS đọc `localStorage` → chưa login thì mở modal, đã login mới cho gửi. KHÔNG `is_user_logged_in()` trong template.

### 15.3 Files (bổ sung §6)
- `auth/auth-google.php` (REST `rf/v1`): +route `report`, `my-reports`.
- Migration: `ALTER TABLE {$wpdb->prefix}user_report ADD COLUMN rf_member_id ... , ADD KEY ...` (= `wphh_user_report`) ở activation (idempotent: check cột tồn tại).
- Template report (single/nút report): submit qua `rf/v1/report`, chặn đường ẩn danh cũ.

---

## 16. Throttle hành động member (chốt 2026-07-22e)

Giới hạn: **1 member không làm quá nhiều action trong 1 phút** (vd **≤3 action/phút**). **1 bucket CHUNG** mọi loại action, đếm gộp theo **`member_id`**. *(Đợt này: comment + report. Rating gia nhập bucket khi làm — §4.8.)*

- **Cơ chế — counter đơn giản (Redis `INCR` + TTL fixed-window):**
  ```
  n = INCR rf_action_rl:{member_id}
  if (n == 1) EXPIRE rf_action_rl:{member_id} RF_ACTION_WINDOW   // CHỈ set TTL lần đầu
  if (n > RF_ACTION_LIMIT) → 429 "Thao tác quá nhanh, thử lại sau"   // KHÔNG thực hiện action
  // else → cho chạy
  ```
    - **Key hết hạn → count reset → member action bình thường lại.**
    - ⚠️ **EXPIRE chỉ set khi `n == 1`.** Nếu EXPIRE mỗi lần INCR → TTL bị "trượt" liên tục → dưới tải đều member **kẹt vĩnh viễn** (bug kinh điển của fixed-window). Phải né.
- **Key = `member_id`** (đã auth ở điểm action nên sẵn có) → giới hạn **per-member chính xác**, KHÔNG bị CGNAT chặn nhầm cả cụm IP. `member_id` lấy từ session đã verify (§4.2b).
- **Action tính vào bucket:** comment, report (đợt này); **rating** khi làm sau (§4.8). GET/`my-*` KHÔNG tính.
- **Hằng:** `RF_ACTION_LIMIT = 3`, `RF_ACTION_WINDOW = 60` (giây) — chỉnh được.
- **Vị trí gọi:** helper `rf_check_action_rate($member_id)` ở đầu **mọi endpoint action**, **sau** verify session + block, **trước** khi thực hiện.
- **Lớp per-IP thô** (`CF-Connecting-IP`, §12.A): vẫn giữ **riêng** để chặn abuse **trước khi có member session** (vd spam login/tạo member) — khác mục đích với throttle per-member ở đây.
- **Race nhỏ:** INCR xong mà process chết trước EXPIRE → key mất TTL, kẹt tới khi bị dọn. Lo thì gói `INCR`+`EXPIRE` trong **1 Lua script** (atomic). Scale này thường bỏ qua được.