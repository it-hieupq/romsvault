# Wishlist Plan v5 — WP_Query + Transient (single cache, full payload)

> Trạng thái: DRAFT. Thay v4 (pure-PHP endpoint + denormalize url/thumb/console).
> Mô hình: **MariaDB = truth. Read đi WP REST, dựng card bằng `WP_Query` (WP full), cache NGUYÊN payload vào 1 transient/member, TTL 4h.** Toggle = ghi DB + **DEL transient** (không mutate).
> Insight lật v4: đường đọc `/wishlist` **không** phải 15K concurrent — 15K là traffic duyệt ROM của guest (CF cache). `/wishlist` chỉ member đã login chủ động mở → volume nhỏ → **nạp WP mỗi lần đọc là chấp nhận được**, và WP_Query dựng card tươi mọi field → **xoá sạch** cột denormalize + drift + hook sync + pure-PHP endpoint của v4.

## 0. Complexity budget

| Chiều | v5 | (v4 để so) |
|---|---|---|
| Cột denormalize | **0** | 3 (url, thumb, console) |
| File PHP mới | **0** (sửa 2 REST handler sẵn có) | 2 (rf-session-lite, /api/wl-get.php) |
| Endpoint đọc | 1 WP REST `GET /rf/v1/wishlist` | 1 pure-PHP `/api/wl-get.php` |
| Hook WP | **1** (`transition_post_status`+`deleted_post`, chỉ DEL rows + invalidate cache — KHÔNG dựng card) | 1 handler + lo denormalize |
| Hook sync freshness | **0** (WP_Query tươi mỗi rebuild) | phải thêm `save_post`+`edited_term` (drift) |
| Cron **mới** | **0** (member-cleanup sẵn có piggyback DEL) | 0 |
| Cơ chế tự lành | **1**: TTL 4h + miss→WP_Query | 1 |
| Freshness card | **tươi ≤4h MỌI field** | url/thumb/console đóng băng vô hạn |

> ⚠️ **Tiền đề nền CẦN ĐO trước khi chốt v5:** cả v5 dựa trên giả định *volume đọc `/wishlist` nhỏ* (member-only, chủ động mở) nên nạp WP mỗi đọc là rẻ — **chưa verify bằng số**. Đo `req/phút` tới `GET /wp-json/rf/v1/wishlist` (access log hoặc plan test tải) TRƯỚC khi coi v5 là chốt; volume lớn bất thường → cân lại (R4).

**3 invariant — thuộc lòng:**
1. **JOIN/filter publish mọi nơi** (WP_Query `post_status=publish`, cap count, read-back): draft/trash/orphan tự rớt từ nguồn → orphan rows vô hại, không cần cron dọn.
2. **Mọi write đổi wishlist của member M → `delete_transient(rf_wl:{M})`** (toggle: 1 key; hook status: fan-out các member chứa post). **DEL, KHÔNG bao giờ mutate/SET** cache từ write path (lost-update + partial-cache race).
3. **Response `/rf/v1/wishlist` là per-member → BẮT BUỘC `no-store` + không được cache ở CF/nginx.** Cache nhầm = leak wishlist chéo member (R1).

## 1. Dictionary

| Key | Kiểu | Nội dung | Ghi | Xóa | TTL |
|---|---|---|---|---|---|
| `{PREFIX}rf_sess:{id}` | string | session `{member_id,exp}` — chỉ ĐỌC qua `rf_session_get()` | auth flow | logout/expire | 7d |
| `rf_wl_v1_{member_id}` (transient) | string | **NGUYÊN payload**: `{ids:[...], items:[{id,title,url,thumb,type_key,console}]}`; sentinel rỗng = `{ids:[],items:[]}` | read khi miss | toggle DEL; hook fan-out DEL; member-cleanup | **4h** (phẳng, không jitter) |

Client: cookie **`rf_member`** (HttpOnly, `RF_SESSION_COOKIE`, auth-google.php:449). `localStorage.rf_wishlist_ids` = **mảng ids** (icon tim trang duyệt; §8).

**Cache backend = WP Transient API** (chạy trên Redis object-cache, prefix `romsfun_`, DB 0). `wp cache flush` (selective `MATCH 'romsfun_*'`) xoá transient → **vô hại** (rebuild từ DB) — nhất quán với quyết định "logout-on-flush chấp nhận".

## 2. Nguyên tắc cốt lõi

1. **MariaDB = truth.** Redis/transient là cache thuần: mất/wipe/evict → vô hại, WP_Query dựng lại đủ.
2. **Read = WP REST + WP_Query.** Nạp WP nhưng chỉ chịu tải nhỏ của `/wishlist`; WP_Query cho phép dựng card **tươi mọi field** (permalink qua `post_type_link` filter, thumbnail, taxonomy) → **không denormalize gì**.
3. **Cache-aside + TTL 4h:** hit → serve; miss → WP_Query → set_transient; write → DEL. TTL bound mọi drift.
4. **Write đi WP REST** (toggle tần suất thấp, rate-limit 6/phút).

## 3. Schema — KHÔNG cột mới

```sql
-- verify/thêm (cho cap đúng + chống dup), KHÔNG thêm cột denormalize:
ALTER TABLE member_wishlist
  ADD UNIQUE KEY uniq_member_post (member_id, post_id),   -- INSERT IGNORE vô nghĩa nếu thiếu
  ADD KEY idx_post (post_id);                              -- fan-out DELETE/SELECT theo post_id
```

- **Bỏ hoàn toàn** ý tưởng cột `url`/`thumb`/`console` của v4: WP_Query lấy tươi. Không drift, không sync.
- Cột hiện có (`member_id, post_id, created_at`) đủ dùng.

## 4. Read path — `GET /rf/v1/wishlist` (WP REST)

```
auth: rf_verify_request_origin → rf_current_member; guest → 401; blocked → 403
      (client 401 → "Đăng nhập để xem"; cookie session = rf_member / RF_SESSION_COOKIE)
$key = "rf_wl_v1_{member_id}"                      # transient; WP tự thêm prefix romsfun_
$payload = get_transient($key)
  hit ($payload !== false) → rf_wishlist_serve($payload)     # KHÔNG chạm DB (kể cả sentinel rỗng)
  miss → rf_wishlist_build_payload($member_id):
     SELECT w.post_id, p.post_title, p.post_type
       FROM member_wishlist w JOIN wp_posts p ON p.ID=w.post_id AND p.post_status='publish'
      WHERE w.member_id=? ORDER BY w.created_at DESC LIMIT RF_WISHLIST_MAX
     mỗi row → { id, title,
                 url   = get_permalink($pid),                # absolute — giữ contract response cũ
                 thumb = get_the_post_thumbnail_url($pid,'medium') ?: '',
                 type_key = post_type,
                 console  = get_the_terms[0]->name  (qua rf_post_type_meta) }
     → payload = { ids:[...], items:[...] }
     set_transient($key, payload, 4h)                        # phẳng; sentinel {ids:[],items:[]} nếu rỗng
     → rf_wishlist_serve(payload)
```

- **Blocked-check giữ nguyên** (như `rf_rest_wishlist_list` hiện tại): member `blocked===1` → 403, KHÔNG serve cache.
- **`rf_wishlist_serve()` — HIT KHÔNG phải raw-echo:** cả hit lẫn miss đều qua serve — decode payload `{ids, items(type_key)}` → mỗi item chèn `type = rf_post_type_meta(type_key)['label']` (ROM/Hack/Emu/Lib), rồi bọc `{ok, ids, count=len(ids), max=RF_WISHLIST_MAX}`. Rẻ (≤30 item, WP đã nạp); đổi label/cap → **không cần rebuild cache** (không lưu trong payload).
- **`url` = absolute** (`get_permalink`) — giữ nguyên contract response trước-cache nên **JS không phải đổi gì** (response mới là superset: item keys y cũ + thêm `ids` top-level). Build chạy trong WP nên `post_type_link` filter resolve đúng URL nhúng taxonomy của emulator (`emulators/%console%`) / pack (`packs/%pack_console%`). (Relative để CDN/đổi-domain là tùy chọn tương lai, kèm cập nhật JS.)
- **`ids`** = ids đã publish (khớp `items`) → **field MỚI** thêm vào response (`list` cũ chỉ có `items/count/max`); client sync localStorage.
- **Headers:** `Cache-Control: private, no-store`. Không lock stampede (key per-member).
- **Redis wipe = miss = dựng lại đầy đủ:** **không có trạng thái trống/sai** (0 blank-display), chỉ thêm **latency rebuild** ở lần đọc kế — khác hẳn card-blank của v4.

## 5. Write path — toggle (`POST /rf/v1/wishlist`)

Giữ nguyên chữ ký thật của `rf_rest_wishlist_set` (idempotent set + bulk remove), chỉ **thêm DEL cache** sau mỗi write thật:

```
set {post, on:0|1}:  origin-check → auth → blocked → rate-limit 6/phút → publish+type check
   on=1 & chưa có:   cap rf_wishlist_count >= RF_WISHLIST_MAX → 409; else INSERT IGNORE → delete_transient
   on=0 & đang có:   DELETE row → delete_transient
   (no-op nếu đã đúng trạng thái → KHÔNG DEL, khỏi thrash cache)
   → return { ok, active, count }
bulk {remove:[ids]}: DELETE ... IN (ids) → delete_transient → return { ok, count }
```

- **DEL chứ không mutate/SET cache:** 2 tab toggle sát nhau → sửa cache tại chỗ sẽ lost-update (mất item) hoặc partial-cache (add vào cache trống → 1 item sai). DEL idempotent; lần **đọc** kế rebuild từ DB = đúng. Giá: 1 rebuild ≤30 post ở lần đọc kế (hiếm + rẻ), toggle không tốn gì.
- **Residual reader-race** (reader miss SELECT trước commit, set_transient stale sau DEL): cửa sổ ~ms, TTL 4h bound. Chấp nhận (R2).
- **KHÔNG cần read-back ids trong response toggle:** client đã biết `(post_id, on)` nó vừa bấm → tự cập nhật `localStorage.rf_wishlist_ids` cho đúng 1 id đó; `count` trong response nuôi bộ đếm "X/30". `ids` đầy đủ chỉ lấy khi mở `/wishlist`. → bớt 1 SELECT mỗi toggle.

## 6. Hook vòng đời post — 1 handler, CHỈ invalidate

`transition_post_status` (mọi chiều) + `deleted_post`, chung 1 hàm — **không dựng card** (WP_Query lo lúc đọc):

```
$members = SELECT member_id FROM member_wishlist WHERE post_id=?   # LẤY TRƯỚC khi xóa rows (ordering!)
if (trash | hard-delete):  DELETE FROM member_wishlist WHERE post_id=?   # row-deletion, chống cap-overflow
batch flush: wp_cache_delete_multiple(keys($members), 'transient')  # 1 pipeline Redis thay N DEL; ẩn/hiện tức thì
             (fallback delete_transient per-key nếu không có persistent object cache)
```

- Fan-out DEL cả 2 chiều (rời publish → ẩn; vào publish → hiện) — nhẹ vì chỉ DEL, không build.
- **Draft/pending: GIỮ rows** (chốt v3-Q5); trash/hard-delete: **DELETE rows** (chặn cap-overflow 50-item).
- Post không ai wishlist: `$members` rỗng → fan-out rỗng, DELETE trúng 0 row (index) → ~free. 99% edit là ca này.
- **KHÔNG hook `save_post` để sync card:** không cần — WP_Query rebuild tươi title/url/thumb/console mỗi ≤4h. (Đây là thứ v4 phải thêm; v5 khỏi.)

## 7. Cache payload — sample

**Key** `rf_wl_v1_{member_id}` · TTL 4h (phẳng — read per-member rải rác, không có mass-expiry để cần jitter) · lưu đúng phần không suy ra được:

```json
{
  "ids": [40231, 51884, 22107],
  "items": [
    { "id": 40231, "title": "God of War II",       "url": "/roms/god-of-war-ii/",                        "thumb": "/wp-content/uploads/2025/03/gow2-300x300.jpg",     "type_key": "rom",      "console": "PlayStation 2" },
    { "id": 51884, "title": "DeSmuME",              "url": "/emulators/nintendo-ds/desmume/",              "thumb": "/wp-content/uploads/2024/11/desmume-300x169.png", "type_key": "emulator", "console": "Nintendo DS" },
    { "id": 22107, "title": "Pokémon Emerald 809",  "url": "/packs/game-boy-advance/pokemon-emerald-809/", "thumb": "",                                                "type_key": "pack",     "console": "Game Boy Advance" }
  ]
}
```

Rỗng (sentinel): `{ "ids": [], "items": [] }`
Serve trả về: `{ ok, ids, count, max, items:[{...,type}] }` (label/max/type thêm ở serve từ constant, không lưu).
Kích thước: ~250–300B/item → 8 item ≈ ~2KB, đầy 30 ≈ ~8KB.

## 8. Client (`js/auth.js`) — localStorage icon-only

- **`localStorage.rf_wishlist_ids`** = **chỉ mảng ids**. Nhiệm vụ **DUY NHẤT**: tô tim (filled/empty) trên trang duyệt **CF-cached** (listing/single) với **0 request**. Không lưu card. **KHÔNG** dùng quyết định nội dung `/wishlist`.
- Nuôi bằng `ids` của response (miễn phí): **seed lúc login** (1 lần gọi `/rf/v1/wishlist` lấy `.ids`, cũng warm cache), **mỗi toggle**, **mỗi lần mở `/wishlist`**. Clear khi logout; `storage` event đồng bộ multi-tab.
- **Trang `/wishlist`:** đã login → luôn gọi `GET /rf/v1/wishlist`, skeleton → replace. Lỗi (5xx) → toast + nút thử lại. 401 → UI login.
- Toggle: optimistic → debounce 800ms → POST → sync `rf_wishlist_ids` theo `ids` response. 409/429 → rollback + toast.
- **Cross-device stale (R6, chấp nhận):** thêm ở máy A → tim máy B stale tới khi máy B tương tác/ghé `/wishlist`. Chỉ lệch **icon**, không lệch nội dung (trang `/wishlist` luôn fetch). Tự lành.
- **Tùy chọn nếu lag phiền:** SWR — vẽ tim từ localStorage tức thì (0 flicker) + 1 async refresh ids/phiên (sessionStorage). **KHÔNG** fetch ids mỗi trang duyệt (per-member request trên đường nóng + flicker first-paint). Mặc định: localStorage-only, chấp nhận R6.

## 9. Failure modes

| Sự cố | Hậu quả | Bound |
|---|---|---|
| Redis wipe / `wp cache flush` / evict transient | miss → dựng lại nguyên payload | **không trống/sai** (0 blank-display), chỉ +latency rebuild |
| Redis down lúc toggle DEL | payload stale | ≤ TTL 4h |
| Admin sửa title/thumb/slug/console | member thấy giá trị cũ | **≤ 4h** (rebuild lấy tươi) — KHÔNG stale vô hạn như v4 |
| Trash/draft post | item ẩn: tức thì (hook fan-out DEL) hoặc ≤4h (nếu bỏ hook) | ≤ 4h worst |
| Race add-vs-trash → orphan row | không hiển thị, không ăn cap (JOIN publish) | vô hại vĩnh viễn; sweep optional |
| Cross-device icon | tim trang duyệt máy khác stale | tới khi tương tác/ghé wishlist (R6) |

## 10. Hạ tầng

- **R1 — cache-leak (🔴, rủi ro số 1):** response `/rf/v1/wishlist` per-member. Chốt chặn: **`Cache-Control: private, no-store`** + **CF bypass rule cho `/wp-json/rf/`** (per-member, không cache) + **nginx: không `fastcgi_cache` route này** (hoặc `fastcgi_no_cache`/`fastcgi_cache_bypass` khi có cookie `rf_member`). **KHÔNG dựa `Vary`** (CF ignore; fastcgi bỏ qua). Verify bắt buộc trước launch (§12).
- **WP boot per read:** chấp nhận vì volume `/wishlist` nhỏ (member đã login, chủ động mở). Không cần pool riêng/pure-PHP. Nếu đo được volume lớn bất thường → khi đó mới tách endpoint ids-only nhẹ.
- **Cache = Transient API** trên Redis object-cache hiện có (TCP `WP_REDIS_HOST=redis`, DB 0, prefix `romsfun_`). Không tách DB.
- **⚠️ Dependency CỨNG — object-cache dropin phải active:** `set/get_transient` chỉ nằm ở Redis khi `wp-content/object-cache.php` (redis-cache) hoạt động. Nếu dropin tắt/hỏng → transient rơi xuống bảng `wp_options` = **ghi DB mỗi miss + autoload bloat** → phá thiết kế. Verify dropin active (đang có, 105KB); nếu lo, dùng thẳng `wp_cache_set/get` (object cache) thay vì transient để fail loud khi không có persistent cache.
- **MariaDB:** miss = 1 SELECT ids + WP_Query ≤30 post (point lookups theo PK). Nhẹ.

## 11. Sizing (100K member, ~40K non-empty, ~8 item/member)

| Hạng mục | Realistic | Worst |
|---|---|---|
| Bảng member_wishlist (~320K rows, không cột thừa) | ~15–25 MB | ~240 MB (buffer pool) |
| Miss: SELECT ids + WP_Query 30 post | ~5–20 ms | ~30 ms |
| RAM transient wishlist (active × ~2KB) | ~30 MB | ~90 MB (đầy 30 item) |
| Đọc `/wishlist` (member mở) | nhỏ (không phải 15K) | — |

## 12. Verify khi implement

- `php -l` mọi PHP sửa; `node --check js/auth.js`.
- (a) guest/401 → UI login; (b) member rỗng: miss 1 SELECT → sentinel → lần sau hit 0 DB; (c) có item: cards đúng, url relative đúng permalink (kể cả emulator/pack nhúng taxonomy), console tên term đúng; (d) toggle: DB đổi + `delete_transient` + localStorage theo response `ids`; (e) trash → item ẩn (tức thì nếu giữ hook / ≤4h nếu bỏ) + rows DELETE + count đúng; (f) restore sau trash → KHÔNG quay lại (rows đã xóa); (g) cap-overflow 50-item: 30→trash 20→add 20→restore 20 → ≤30 publish; (h) draft→ẩn, republish→hiện; (i) **`wp cache flush` / evict transient → lần đọc kế trả ĐẦY ĐỦ từ WP_Query, 0-gap**; (j) double-click add → không dup (UNIQUE); (k) sửa title/thumb 1 post đang wishlist → sau ≤4h (hoặc DEL transient tay) thấy tươi; (l) admin đổi slug emulator/pack → url mới đúng sau rebuild.
- **R1: curl `/rf/v1/wishlist` 2 session khác nhau, qua CF và thẳng origin → 2 response khác nhau, `cf-cache-status: BYPASS/DYNAMIC`, header `no-store`, không dính `fastcgi_cache` HIT chéo.**
- Purge CF/APO sau khi đổi JS.

## 13. Risks

| # | Rủi ro | Mức | Vá |
|---|---|---|---|
| R1 | CF/origin cache `/rf/v1/wishlist` → leak chéo member | 🔴 | `no-store` + CF bypass `/wp-json/rf/` + nginx no fastcgi_cache + verify — **bắt buộc trước launch** |
| R2 | Stale sau toggle nếu Redis flaky lúc DEL; residual reader-race | 🟢 | TTL 4h bound; client đúng nhờ `ids` response |
| R3 | Fan-out DEL post hot (nhiều nghìn member) | 🟢 | **Batch** `wp_cache_delete_multiple($keys,'transient')` = 1 pipeline Redis (N round-trip → ~1); không build; chạy trong request admin, không đụng user path. Fallback per-key khi không có persistent object cache |
| R4 | WP boot mỗi đọc `/wishlist` gây tải | 🟡 **cần đo** | Tiền đề v5 = volume `/wishlist` nhỏ, **CHƯA verify** (§0). Đo `req/phút` bằng access log / plan test tải trước khi chốt; lớn bất thường → tách ids-endpoint nhẹ hoặc pure-PHP hit-server |
| R8 | object-cache dropin tắt → transient ghi `wp_options` (DB) | 🟡 | Verify dropin active (§10); cân nhắc `wp_cache_*` để fail loud thay vì âm thầm ghi DB |
| R5 | Cap race 2 tab → 31 | 🟡 | Chấp nhận (rate-limit + debounce); cứng: `INSERT...SELECT WHERE COUNT<30` |
| R6 | Cross-device icon stale | 🟢 | Chấp nhận: cosmetic, tự lành; hoặc SWR 1 lần/phiên nếu phiền |
| R7 | Card stale ≤4h sau admin sửa | 🟢 | TTL 4h bound; muốn tức thì → `delete_transient` trong `save_post` (không bắt buộc) |

**Rollout:** (1) verify schema UNIQUE + index `post_id`; (2) sửa `rf_rest_wishlist_list` thêm transient (hit/miss/sentinel) + `rf_rest_wishlist_set` thêm `delete_transient`; (3) hook `transition_post_status`/`deleted_post` (DELETE rows + fan-out DEL transient); (4) CF/nginx bypass + verify R1; (5) JS `/wishlist` fetch + localStorage. **Không backfill, không cột mới, không cron, không pure-PHP.**

## 14. Danh sách thay đổi (files)

- `auth/auth-google.php`:
  - `rf_rest_wishlist_list`: bọc `get_transient`/`set_transient` (TTL 4h phẳng, sentinel rỗng); miss dựng bằng SQL JOIN publish + WP helper (get_permalink/thumbnail/get_the_terms); serve chèn `type` label + `count`/`max`; header `no-store`. `rf_wishlist_count` JOIN publish (cap đếm published).
  - `rf_rest_wishlist_set` (add/remove): sau ghi DB → `delete_transient` + read-back `ids`.
  - Hook `transition_post_status` + `deleted_post`: SELECT members TRƯỚC → DELETE rows khi trash/delete → fan-out `delete_transient`.
  - `rf_wishlist_count` + read-back: JOIN publish (giữ).
  - Member-cleanup cron (sẵn có): thêm `delete_transient` khi xóa member.
- Schema: `UNIQUE(member_id, post_id)` + `KEY(post_id)`.
- `js/auth.js`: `/wishlist` fetch + render; localStorage ids-only (seed/toggle/wishlist/logout/storage-event).
- Hạ tầng: CF bypass `/wp-json/rf/` + nginx no `fastcgi_cache` route đó.
- **KHÔNG:** cột denormalize, `rf-session-lite.php`, `/api/wl-get.php`, `post_card_cache`, hook `save_post`/`edited_term` sync, cron/watcher, backfill.

---

### Diff v4 → v5 (vì sao lật)

| | v4 (pure-PHP + denormalize) | v5 (WP_Query + transient) |
|---|---|---|
| Read | pure-PHP `/api/wl-get.php`, 0 WP | WP REST + `WP_Query` (nạp WP) |
| Dựng card | JOIN + 3 cột đóng băng | WP_Query tươi mọi field |
| Cột schema | +url/thumb/console | **0 cột mới** |
| Sync freshness | phải thêm `save_post`+`edited_term` (drift vô hạn) | **không cần** (tươi ≤4h) |
| File mới | 2 | **0** |
| TTL | 6h | **4h** |
| Giả định tải sai của v4 | tối ưu 15K concurrent cho đường đọc | đường đọc `/wishlist` vốn nhỏ (member-only) |
| Rủi ro sống sót | R1 cache-leak | R1 cache-leak (vẫn phải chặn) |
| Đánh đổi | phức tạp cao, url/thumb/console stale vô hạn | nạp WP mỗi đọc (rẻ ở volume thật) |
