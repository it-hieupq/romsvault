# Test plan — Tracking beacon `/t` (PROD)

Kiểm chứng đường đi: **browser → `POST /t` → nginx → PHP-FPM → `track.php` → Redis → system cron `flush.php` → MySQL**.

Chạy tuần tự. Mỗi bước có điều kiện pass rõ ràng — sai ở đâu dừng ở đó.

Không dùng WP-CLI: mọi thao tác WordPress đều qua `php -r` + `wp-load.php`.

---

## Chuẩn bị

```bash
ls /www/server/php/*/bin/php          # tim ban PHP dung

export PHP=/www/server/php/83/bin/php
export SITE=/www/wwwroot/romsfun.com
export TRACK=$SITE/wp-content/themes/romsfun/tracking
export RCLI="redis-cli -h 127.0.0.1 -p 6379"     # them -a 'password' neu co requirepass
```

Chọn một post published, traffic thấp làm mẫu:

```bash
cd $SITE && $PHP -r 'require "wp-load.php";
foreach ( get_posts( array( "post_type" => "rom", "posts_per_page" => 5 ) ) as $p ) {
    printf( "%d  %s\n", $p->ID, $p->post_title );
}'
```

```bash
export POST_ID=<dan_id_vao_day>
```

---

## Bước 0 — Trước khi deploy: vét key legacy

Code cũ trên prod còn handler, dùng nó vét lần cuối. Bỏ bước này = mất số đang nằm trong Redis legacy.

```bash
cd $SITE && $PHP -r 'require "wp-load.php"; do_action( "rf_flush_tracking" ); echo "done\n";'
$RCLI HLEN romsfun_rf_track:pending_views
```

**Pass:** `HLEN` về `0`, hoặc key không tồn tại.

---

## Bước 1 — Deploy + dọn cron cũ

```bash
cd $SITE/wp-content/themes/romsfun && git pull && git log --oneline -3
ls tracking/                        # phai co config.php flush.php track.php
```

Xoá event WP-Cron mồ côi (code mới không còn handler, cũng không còn chỗ tự đăng ký lại):

```bash
cd $SITE && $PHP -r 'require "wp-load.php";
wp_clear_scheduled_hook( "rf_flush_tracking" );
var_dump( wp_next_scheduled( "rf_flush_tracking" ) );'
```

**Pass:** in ra `bool(false)`.

---

## Bước 2 — Site và login còn sống

Quan trọng nhất: `rf_redis()` đã chuyển từ `inc/ajax-tracking.php` sang `auth/member-core.php`. Hỏng cái này thì member không đăng nhập được, mà **không có lỗi nào hiện ra** — mọi call site đều bọc `function_exists`.

```bash
curl -s -o /dev/null -w 'trang chu: %{http_code}\n' https://romsfun.com/

cd $SITE && $PHP -r 'require "wp-load.php";
echo "rf_redis defined: ", function_exists( "rf_redis" ) ? "yes" : "NO", "\n";
$r = rf_redis();
echo "connection:       ", $r ? "ok (" . $r->ping() . ")" : "NULL", "\n";
$sid = rf_session_create( 1 );
echo "session_create:   ", $sid ? "ok" : "NULL", "\n";
if ( $sid ) {
    $s = rf_session_get( $sid );
    echo "session_get:      ", $s ? "ok member_id=" . $s["member_id"] : "NULL", "\n";
    rf_session_destroy( $sid );
}'
```

**Pass:** `200`, và cả 4 dòng đều `ok`/`yes`. Sau đó tự đăng nhập Google trên web một lần cho chắc.

---

## Bước 3 — Endpoint `/t`

```bash
nginx -t

curl -s -o /dev/null -w 'status=%{http_code} time=%{time_total}s\n' \
  -X POST -d "id=$POST_ID&a=v" -H 'Host: romsfun.com' http://127.0.0.1/t
```

**Pass:** `204`, dưới `0.05s`.

| Lỗi | Nguyên nhân |
|---|---|
| `404` | Sai `SCRIPT_FILENAME`, hoặc block chưa load — kiểm tra `nginx -T \| grep -A5 'location = /t'`. Tên thư mục theme phải khớp. |
| `502` / `504` | Sai `fastcgi_pass` |
| `403` | Block `deny all` đặt sai vị trí, đang bắt cả `/t` |

Chặn truy cập file trực tiếp:

```bash
for F in track.php flush.php config.php; do
  printf '%-12s -> ' "$F"
  curl -s -o /dev/null -w '%{http_code}\n' -H 'Host: romsfun.com' \
    "http://127.0.0.1/wp-content/themes/romsfun/tracking/$F"
done
```

**Pass:** cả 3 ra `403`. Ra `200` là lộ `config.php`.

Qua Cloudflare — chạy **từ máy cá nhân**, không phải trên server:

```bash
curl -s -o /dev/null -w '%{http_code}\n' -X POST -d 'id=<POST_ID>&a=v' https://romsfun.com/t
```

**Pass:** `204`. Ra `403` kèm HTML → Bot Fight Mode đang chặn: Cloudflare → Security → WAF → Custom rules → `(http.request.uri.path eq "/t")` → **Skip** Bot Fight Mode + Managed Rules + Rate Limiting.

---

## Bước 4 — Redis nhận đúng key

```bash
curl -s -o /dev/null -X POST -d "id=$POST_ID&a=v" -H 'Host: romsfun.com' http://127.0.0.1/t
curl -s -o /dev/null -X POST -d "id=$POST_ID&a=d" -H 'Host: romsfun.com' http://127.0.0.1/t

$RCLI HGET rftrk:pending_views $POST_ID
$RCLI HGET rftrk:pending_downloads $POST_ID
```

**Pass:** views tăng, downloads tăng. `a=d` vào downloads, mọi giá trị khác vào views.

---

## Bước 5 — Browser thật

curl chứng minh server chạy; chỉ browser mới chứng minh **JS bắn đúng**.

Mở tab ẩn danh → vào 1 trang single → DevTools → Network → lọc `t` → chờ 1 giây (JS có `setTimeout` 1000ms).

| Mục | Giá trị đúng |
|---|---|
| Method | `POST` |
| Status | `204` |
| Type | `ping` (sendBeacon) |
| Payload | `id=<post id>` , `a=v` |

Console:

```javascript
rfTracking                                  // {endpoint: "/t", postId: "...", trackType: "view"}
sessionStorage.getItem('rf_tracked_view')   // "[12345]"
```

**Pass:** F5 lại trang **không** bắn request thứ hai.

Nếu `rfTracking` vẫn hiện `ajaxUrl` → trang đó là HTML cũ trong cache Cloudflare, không đếm được cho tới khi cache xoay vòng.

---

## Bước 6 — Flush thủ công

```bash
# Ghi lai so TRUOC
cd $SITE && $PHP -r 'require "wp-load.php";
printf( "views=%d downloads=%d\n",
    (int) get_post_meta( getenv( "POST_ID" ), "views", true ),
    (int) get_post_meta( getenv( "POST_ID" ), "downloads", true ) );'

# Chay flush
cd $TRACK && $PHP flush.php; echo "exit=$?"

# Doi chieu so SAU
cd $SITE && $PHP -r 'require "wp-load.php";
printf( "views=%d downloads=%d\n",
    (int) get_post_meta( getenv( "POST_ID" ), "views", true ),
    (int) get_post_meta( getenv( "POST_ID" ), "downloads", true ) );'

$RCLI --scan --pattern 'rftrk:*'
```

**Pass:** `exit=0`, in ra một dòng summary dạng:

```
[rf-flush] 2026-08-10 14:32:11 | views: 3 posts, +12 (0 skipped) | downloads: 1 posts, +1 | 0.04s
```

`views`/`downloads` tăng đúng số đã bắn, Redis chỉ còn `rftrk:last_flush`.

Còn sót `rftrk:pending_views:flushing` = lần ghi DB fail giữa chừng — chạy lại `flush.php`, nó tự nhặt nốt.

> `getenv("POST_ID")` chỉ đọc được nếu đã `export POST_ID` ở phần Chuẩn bị.

---

## Bước 7 — System cron

`flush.php` in summary ra **stdout** và lỗi ra **stderr**. aaPanel bắt cả hai và hiển thị trong mục Log của task, nên **không redirect ra file** — redirect là log trong panel rỗng.

**aaPanel** → Cron → Add task → Shell Script → chu kỳ N phút = **10**:

```bash
cd /www/wwwroot/romsfun.com/wp-content/themes/romsfun/tracking && /www/server/php/83/bin/php flush.php
```

Xem kết quả: aaPanel → Cron → dòng task → **Log**. Mỗi lần chạy phải có một dòng `[rf-flush] …`.

Nếu dùng `crontab -e` thẳng thay vì aaPanel thì mới cần redirect, vì cron hệ thống gửi output qua mail chứ không lưu log:

```
*/10 * * * * cd /www/wwwroot/romsfun.com/wp-content/themes/romsfun/tracking && /www/server/php/83/bin/php flush.php >> /www/wwwlogs/rf-flush.log 2>&1
```

Đợi qua 2 chu kỳ (20 phút) rồi kiểm chứng bằng heartbeat — đáng tin hơn đọc log:

```bash
$RCLI GET rftrk:last_flush | xargs -I{} date -d @{}
```

**Pass:** heartbeat cách hiện tại dưới 10 phút.

> Một lần chạy với 5.000 post đo được ~1.8s, so với chu kỳ 600s — không có nguy cơ chồng lấn. Nếu sau này biên độ đó thu hẹp, bọc lệnh trong `flock -n`: hai tiến trình chạy song song sẽ cùng đọc một buffer và nhân đôi mọi con số.

---

## Bước 8 — Theo dõi sau 24h

```bash
# Redis khong duoc evict (evict = mat luot view that)
$RCLI INFO stats  | grep evicted_keys
$RCLI INFO memory | grep maxmemory_policy

# Buffer phai dao dong roi ve 0 sau moi lan flush, khong tang vo han
$RCLI HLEN rftrk:pending_views

# Key legacy phai dung tang han
$RCLI HLEN romsfun_rf_track:pending_views

# Heartbeat con song
$RCLI GET rftrk:last_flush | xargs -I{} date -d @{}
```

**Pass:** `evicted_keys` = `0`.

⚠️ Nếu `maxmemory_policy` là `allkeys-lru` thì Redis **được phép xoá** key `rftrk:*` khi đầy bộ nhớ, tức mất lượt view chưa kịp vào DB. Cần đổi sang `volatile-lru` — key tracking không có TTL nên sẽ được miễn.

Nếu block nginx có thêm `access_log /www/wwwlogs/romsfun.com.track.log rftrk;` thì đo được tỉ lệ lỗi của `/t`:

```bash
awk '{print $2}' /www/wwwlogs/romsfun.com.track.log | sort | uniq -c | sort -rn
```

**Pass:** ~100% là `204`.

---

## Hai điều lưu ý khi đọc số liệu

1. **Views sẽ tăng nhanh hơn trước.** Code cũ có throttle Redis `SET NX` 6 tiếng/khách/post; `track.php` không có, chỉ chặn trùng bằng `sessionStorage` phía client. Đổi thiết bị, tab ẩn danh, hết session là đếm lại. Đây là thay đổi hành vi có chủ đích, không phải lỗi.

2. **Trang HTML còn trong cache Cloudflare vẫn bắn vào `admin-ajax.php`** và giờ nhận `0` vì handler đã gỡ — không đếm, không báo lỗi ra console của khách. Hết khi cache xoay vòng.

---

## Rollback

Không cần rollback DB — code mới chỉ cộng thêm, không xoá.

```bash
# 1. Vet not so lieu con ket trong Redis
cd $TRACK && $PHP flush.php

# 2. Tat cron tren aaPanel

# 3. Go block nginx (comment location = /t va location deny), roi:
nginx -t && nginx -s reload

# 4. Tra code
cd $SITE/wp-content/themes/romsfun && git revert 0a3cffc 1b90470
```

Lưu ý `0a3cffc` đã xoá `inc/ajax-tracking.php` và chuyển `rf_redis()` sang `auth/member-core.php` — revert riêng nó sẽ khôi phục file cũ trong khi `member-core.php` vẫn giữ hàm, tạo ra **hai định nghĩa `rf_redis()`** → fatal error. Phải revert cả hai commit cùng lúc, đúng thứ tự trên.

---

## Checklist

| # | Hạng mục | Pass khi | ☐ |
|---|---|---|---|
| 1 | Vét key legacy trước deploy | `HLEN` = 0 | ☐ |
| 2 | Event WP-Cron cũ đã xoá | `wp_next_scheduled` = `bool(false)` | ☐ |
| 3 | Trang chủ | `200` | ☐ |
| 4 | `rf_redis` + session create/get | tất cả `ok` | ☐ |
| 5 | Đăng nhập Google trên web | vào được | ☐ |
| 6 | `nginx -t` | syntax is ok | ☐ |
| 7 | `POST /t` | `204`, < 50ms | ☐ |
| 8 | 3 file trong `tracking/` truy cập trực tiếp | `403` | ☐ |
| 9 | `/t` qua Cloudflare | `204`, không challenge | ☐ |
| 10 | Redis `pending_views` / `pending_downloads` | đều tăng | ☐ |
| 11 | Browser: request `/t` | `204`, type `ping` | ☐ |
| 12 | Browser: F5 không bắn lại | sessionStorage chặn | ☐ |
| 13 | `flush.php` thủ công | `exit=0` + summary | ☐ |
| 14 | DB tăng đúng số | khớp số đã bắn | ☐ |
| 15 | Redis sạch sau flush | chỉ còn `rftrk:last_flush` | ☐ |
| 16 | Cron 10 phút | heartbeat < 10 phút, panel Log có dòng mới | ☐ |
| 17 | Sau 24h: `evicted_keys` | `0` | ☐ |
| 18 | Sau 24h: key legacy | dừng tăng | ☐ |
