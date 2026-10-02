# Wishlist v5 — Plan test tải

> Mục tiêu chính: **chứng minh (hay bác) tiền đề nền của v5** — "WP-boot mỗi lần đọc `/wishlist` là chấp nhận được vì volume nhỏ" (R4/§0 của `wishlist_plan_v2.md`). Kèm đo hiệu năng hit/miss, chịu tải cold-cache, và **kiểm rò rỉ cache chéo member (R1)** dưới tải.

## 0. Bước 0 — ĐO VOLUME THẬT trước (rẻ nhất, quyết định nhất)

Trước khi bench, lấy số thật từ log — có thể kết luận luôn mà không cần test nặng:
```
# nginx access log: req/phút tới endpoint đọc + trang /wishlist, giờ cao điểm
grep '/wp-json/rf/v1/wishlist' access.log | awk '{print $4}' | cut -d: -f1-2 | uniq -c | sort -rn | head
grep 'GET /wishlist' access.log | ...
```
- **Ngưỡng quyết định (gắn R4):**
  - < ~500 req/phút (~8 rps): v5 dư sức, có thể bỏ qua test nặng.
  - 500–3.000 req/phút: chạy scenario A+B để chắc.
  - > ~3.000 req/phút: nghi ngờ tiền đề → phải test kỹ + chuẩn bị fallback (ids-endpoint nhẹ / pure-PHP hit-server).

## 1. Success criteria (pass/fail)

| Chỉ số | Ngưỡng đạt |
|---|---|
| p95 latency GET `/wishlist` — **hit** | < 50ms tại concurrency mục tiêu |
| p95 latency — **miss** (rebuild) | < 300ms |
| FPM: `max_children reached` | **0** trong suốt test tại peak thật ×2 |
| Cache hit ratio (steady, TTL 4h) | > 95% |
| DB QPS khi cold-burst | không đẩy MariaDB `Threads_running` vượt ngưỡng an toàn; không slow-query |
| **R1 leak** | 0 response chứa wishlist của member khác; header `no-store`; `cf-cache-status: BYPASS/DYNAMIC` |
| Toggle | invalidate đúng (đọc kế thấy thay đổi); không lost-update khi 2 client song song |

## 2. Chuẩn bị dữ liệu & phiên (điểm khó: endpoint cần session thật)

Endpoint yêu cầu cookie `rf_member` (HttpOnly) → session ở Redis `{PREFIX}rf_sess:{id}`. Không login Google được trong bench → **seed phiên trực tiếp**:
- Tạo N member test (bảng member) với wishlist đa dạng: **~50% rỗng, ~40% 1–10 item, ~10% đầy 30**; kèm vài **post "hot"** được nhiều member wishlist (test fan-out).
- Seed N key `rf_sess:{sid}` = `{"member_id":X,"exp":...}` (TTL 7d) qua `redis-cli`/script; xuất map `sid → cookie` cho VU.
- Mỗi virtual user gửi header `Cookie: rf_member={sid}` + `Origin`/`Referer` hợp lệ (qua `rf_verify_request_origin`).

## 3. Công cụ

`k6` (ưu tiên — script JS, per-VU cookie, ngưỡng tự động) hoặc `vegeta`/`wrk` + Lua. Env: **staging mirror** (cùng RAM/FPM/DB/Redis config như prod), KHÔNG bench thẳng prod. Bật quan sát: `php-fpm status`, MariaDB `SHOW PROCESSLIST`+slow log, `redis-cli INFO stats/keyspace`, nginx/CF `cf-cache-status`.

## 4. Scenarios

| # | Kịch bản | Cách | Đo |
|---|---|---|---|
| A | **Hit baseline** | Warm cache trước; bắn GET `/wishlist` ở peak-thật ×1, ×2, ×4 | p95 hit, FPM active workers, có chạm `max_children`? |
| B | **Cold-cache stampede** | `FLUSHDB` (hoặc xoá prefix `romsfun_rf_wl_v1_*`) → đổ tải đột ngột | DB QPS/`Threads_running`, p95 miss, thời gian cache đầy lại, latency có phục hồi? |
| C | **Mix thực tế** | 95% read + 5% toggle (tôn trọng cap 6/phút/member) | invalidate đúng sau toggle; chi phí rebuild; không lost-update (2 VU cùng member 2 "tab") |
| D | **R1 leak dưới tải** | 2+ session song song bắn đồng thời; qua CF và thẳng origin | assert body[A] ≠ body[B]; header `no-store`; không `fastcgi_cache` HIT chéo |
| E | **Sentinel rỗng** | VU toàn member rỗng | lần 1 = 1 query + set sentinel; lần sau 0 DB (kiểm general log / processlist) |
| F | **Fan-out hot post** | Trash 1 post được ~N nghìn member wishlist | thời gian hook (`transition_post_status`) + số DEL; latency request admin; DB `DELETE ... WHERE post_id` |

## 5. Cần quan sát (dấu hiệu vỡ)

- **FPM:** `listen queue` tăng, `max_children reached` > 0 → WP-boot-per-read đang nghẽn → **tiền đề v5 sai** → kích hoạt fallback (§7).
- **MariaDB:** slow query trên miss JOIN, `Threads_running` cao kéo dài khi cold-burst.
- **Redis:** `evicted_keys` tăng (transient bị đẩy khỏi RAM sớm → hit ratio tụt), `keyspace_hits/misses`.
- **Object-cache dropin (R8):** xác nhận transient nằm ở Redis, KHÔNG rơi xuống `wp_options` (kiểm `SELECT COUNT(*) FROM wp_options WHERE option_name LIKE '_transient_rf_wl_%'` phải ~0).

## 6. Tiền điều kiện đúng đắn (verify TRƯỚC khi tin số bench)

- Schema: `UNIQUE(member_id,post_id)` + `KEY(post_id)` đã ALTER (nếu chưa, INSERT IGNORE/dedup + fan-out sai → số vô nghĩa).
- CF/nginx: rule bypass cache `/wp-json/rf/` đã bật (scenario D).
- Object-cache dropin active.

## 7. Fallback nếu FAIL (tiền đề load sai)

Theo thứ tự nhẹ→nặng, chỉ làm khi bench chứng minh cần:
1. **ids-endpoint nhẹ** cho trang duyệt (nếu tải đến từ tô-tim, không phải trang `/wishlist`) — nhưng v5 đã dùng localStorage nên khả năng thấp.
2. **Pure-PHP hit-server** cho GET `/wishlist` (như ý tưởng v4): hit phục vụ 0-WP, miss proxy về WP REST rebuild. Giữ nguyên tầng transient.
3. Chỉ khi cả hai không đủ → xét lại toàn kiến trúc (edge cache card ở CF).

## 8. Ghi chú

- Test này **chạy sau khi implement** (đúng thứ tự đã chốt): code v5 đã xong ở `auth-google.php` (read cache + write invalidate + hook), còn schema ALTER + CF/nginx là bước ops trong Rollout §13 của plan.
- Kết quả bench → cập nhật R4 trong `wishlist_plan_v2.md` từ "🟡 cần đo" sang 🟢/đỏ tùy số thật.

## 9. Go-live R1 verify — curl (copy-paste, chạy TRƯỚC khi mở public)

Mục đích: chứng minh response `/wp-json/rf/v1/wishlist` **không bị cache chéo member** (R1). Lấy `SID` = giá trị cookie `rf_member` của 2 member test KHÁC nhau (login 2 tài khoản → DevTools → Application → Cookies → copy `rf_member`); wishlist 2 member phải khác nhau.

```bash
SITE="https://romsfun.com"          # qua Cloudflare
ORIGIN="http://127.0.0.1:8080"      # thẳng origin nginx (bypass CF) — chỉnh theo setup docker
SID_A="<rf_member cookie member A>"
SID_B="<rf_member cookie member B>"
EP="/wp-json/rf/v1/wishlist"

echo "== 1) Headers qua CF (member A) =="
curl -sSI -H "Cookie: rf_member=$SID_A" "$SITE$EP" \
  | grep -iE 'cache-control|cf-cache-status|age|x-cache|set-cookie'
#  KỲ VỌNG: 'cache-control: private, no-store' ; 'cf-cache-status: DYNAMIC' hoặc 'BYPASS' ;
#           KHÔNG có 'age' > 0 ; KHÔNG set-cookie lạ.

echo "== 2) Body A vs B phải KHÁC nhau (không leak chéo) =="
A=$(curl -sS -H "Cookie: rf_member=$SID_A" "$SITE$EP")
B=$(curl -sS -H "Cookie: rf_member=$SID_B" "$SITE$EP")
[ "$A" != "$B" ] && echo "OK: khác nhau" || echo "FAIL: GIỐNG NHAU → nghi cache chung / leak"

echo "== 3) Lặp A nhiều lần: luôn là wishlist của A, không lẫn B =="
for i in 1 2 3; do curl -sS -H "Cookie: rf_member=$SID_A" "$SITE$EP" | head -c 160; echo; done

echo "== 4) Thẳng origin (bypass CF): 2 session → 2 body, không fastcgi_cache HIT chéo =="
curl -sS -H "Host: romsfun.com" -H "Cookie: rf_member=$SID_A" "$ORIGIN$EP" | head -c 160; echo
curl -sS -H "Host: romsfun.com" -H "Cookie: rf_member=$SID_B" "$ORIGIN$EP" | head -c 160; echo
#  2 dòng phải KHÁC nhau. Nếu có header X-FastCGI-Cache thì phải là MISS/BYPASS, không HIT chéo.

echo "== 5) Guest (không cookie) → 401, không phục vụ cache của ai =="
curl -sS -o /dev/null -w 'HTTP %{http_code}\n' "$SITE$EP"   # KỲ VỌNG: 401

echo "== 6) Object-cache dropin: transient nằm ở Redis, KHÔNG rơi xuống wp_options =="
#  sau khi đã gọi $EP ít nhất 1 lần cho member A:
#  redis-cli -h redis --scan --pattern 'romsfun_*rf_wl_v1_*'      -> phải có key
#  wp db query "SELECT COUNT(*) FROM wp_options WHERE option_name LIKE '_transient_rf_wl_%'"  -> ~0
```

**PASS khi:** (1) `no-store` + `cf-cache-status: DYNAMIC/BYPASS`, không `age`; (2)(4) body A≠B; (3) A ổn định là của A; (5) guest 401; (6) key ở Redis, `wp_options` ~0. **Bất kỳ FAIL nào ở (2)/(4) = R1 leak → CHẶN launch** cho tới khi CF Cache Rule bypass `/wp-json/rf/` + nginx `fastcgi_no_cache` (xem `wishlist_plan_v2.md` §10) đúng.
