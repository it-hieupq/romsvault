# Quick search index — thiết kế

Trạng thái: chạy trên nswvault.com (commit `97f383b`). Tài liệu đối chiếu với code ngày 21/10/2026.

Code nguồn: `inc/search-index.php` (toàn bộ phía server), block **LIVE SEARCH** trong
`js/function.js` (toàn bộ phía browser). §10 là checklist khi đưa sang site khác.

---

## 1. Mục tiêu

Dropdown quick result cho ô search:

- Chỉ match theo **title và `app_name`**, không bao giờ match `post_content`.
- **Không tốn PHP** trên đường đi của visitor: không bootstrap WordPress, không
  endpoint, không request nào cho mỗi keystroke.
- Dữ liệu là một file JSON build sẵn, full rebuild bằng cron và vá tăng dần
  (incremental) mỗi khi post đổi trạng thái.
- Không rate limit.

Phạm vi: post type `post`, chỉ bài `publish`. `homebrew` và `emulator` không nằm
trong index.

Áp cho **hai ô search**: ô ở header, và ô chính của filter bar
(`#home-filter-search`) có trên mọi archive và trang browse. Index dùng chung một
bản, panel kết quả mỗi ô một cái riêng.

**Quy mô (đo 21/10/2026).**

| | Số game | Raw | Gzip |
|---|---|---|---|
| Local | 547 | 161 KB | 28 KB |
| PROD | 626 | 182 KB | ~30 KB |

Trung bình khoảng 290 byte raw, 50 byte gzip mỗi game. Đó là cỡ một ảnh cover,
và mỗi visitor chỉ tải một lần, nên đưa thẳng cả file cho browser là rẻ. Ở cỡ vài
nghìn game thì vẫn ổn (5.000 game ≈ 250 KB gzip).

---

## 2. Kiến trúc

```
                    ┌──────────────────────────────────────┐
  wp-cron (10 phút) │  inc/search-index.php   (WordPress)  │
  hook đổi status   │  full rebuild  +  incremental patch  │
                    └────────────────┬─────────────────────┘
                                     │ ghi (atomic rename)
                                     ▼
                wp-content/uploads/search-index/index.json
                                     │ nginx serve như file tĩnh
                                     ▼
            GET …/index.json?v=<build>     1 lần/visitor, khi focus
                                     │
                    ┌────────────────┴─────────────────────┐
                    │  js/function.js                      │
                    │  fold 1 lần · filter · rank · cắt 6  │
                    └──────────────────────────────────────┘
```

Không có gì thực thi phía server khi gõ một ký tự. Index được fetch đúng một
lần, lúc visitor focus vào ô search đầu tiên; mọi keystroke sau đó chỉ là quét
substring trên một mảng đã nằm trong RAM.

---

## 3. File index

**Đường dẫn:** `wp-content/uploads/search-index/index.json`. Chọn `uploads` vì
đây là thư mục WordPress bảo đảm ghi được, và redeploy theme không xoá nó.

**Envelope**

```json
{"v": 1792569722, "n": 626, "items": [ … ]}
```

`v` là timestamp lúc build, đồng thời là token cache-busting. `n` là số record.

**Record**

```json
{
  "id": 39563,
  "title": "Momodora: Moonlit Farewell",
  "url": "https://nswvault.com/nintendo-switch/roms/momodora-moonlit-farewell.html",
  "thumbnail": "https://nswvault.com/wp-content/uploads/2025/02/Momodora-Moonlit-Farewell-cover-300x489.jpg",
  "publisher": "PLAYISM",
  "version": "1.1f33.5",
  "size": "276.32 MB",
  "app_name": "…"
}
```

| Field | Nguồn | Ghi chú |
|---|---|---|
| `id` | `$post->ID` | |
| `title` | `get_the_title()` rồi `html_entity_decode` | xem bên dưới |
| `url` | `get_permalink()` | |
| `thumbnail` | `get_the_post_thumbnail_url($id, 'thumbnail')` | không có ảnh thì `""`; nswvault đặt size `thumbnail` là 300×9999 không crop |
| `publisher` | term đầu tiên của taxonomy `publisher`, decode entity | không có thì `""` |
| `version` | post meta `version`, trim | |
| `size` | post meta `_apkfilesize`, trim | |
| `app_name` | post meta `app_name`, trim | **chỉ ghi khi khác `post_title`**, chỉ để match, không hiển thị |

Bảy field đầu luôn có mặt (rỗng thì là `""`). `app_name` thì bỏ hẳn khỏi record
khi trùng title. Hiện chỉ 29/547 bài có field này.

`title` được **decode entity** trước khi ghi. `get_the_title()` chạy filter
`the_title`, nên trả về `Dungeons &#038; Degenerate Gamblers`. Field JSON là dữ
liệu chứ không phải markup, và browser sẽ escape thêm một lần khi render, nên để
nguyên entity thì nó hiện ra màn hình thành literal `&#038;`.

Phép so `app_name` với title phải dùng **`post_title` thô** (đã decode), không
dùng title đã qua filter. `wptexturize` đổi `'` thành `’` và `-` thành `–`, nên
mọi title chứa hai ký tự đó sẽ trông khác `app_name` dù hai chuỗi giống hệt.

**Encode:** `wp_json_encode` với `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`.
File chủ yếu là URL, escape `\/` cho từng cái tốn khoảng một phần mười dung
lượng mà không được gì.

**Normalise làm trong JS**, đúng một lần, ngay sau khi fetch xong. Lúc đó dựng
một mảng song song các chuỗi đã fold, mọi keystroke dùng lại mảng này. Phép fold:

- hạ chữ thường;
- `normalize('NFD')` rồi bỏ dấu kết hợp (U+0300–U+036F);
- `‘ ’ ‚ ‛` → `'`, `“ ” „ ‟` → `"`, `– — −` → `-`, `…` → `...`.

Phần ép dấu nháy/gạch là bắt buộc: `wptexturize` đã uốn chúng trong title, còn
user gõ dấu thẳng. Không fold cả hai phía thì `crab's` không bao giờ tìm ra
`Another Crab’s Treasure`.

**Thứ tự:** full rebuild ghi theo `post_date DESC`, mới nhất trước, nên khi điểm
rank bằng nhau thì game mới hơn đứng trước. Incremental upsert **luôn prepend**:
với bài vừa publish thì đúng thứ tự, còn bài cũ vừa sửa sẽ nhảy lên đầu cho tới
lần full rebuild kế tiếp. Chỉ thứ tự giữa các kết quả cùng rank bị ảnh hưởng.

**Ghi atomic:** ghi ra `index.json.tmp` (dùng `LOCK_EX`) rồi `rename()`. Trên cùng
một filesystem, `rename()` là atomic, nên visitor không bao giờ fetch được file
đang ghi dở. Ghi lỗi thì xoá file tmp, trả `-1`, và **không** đổi version. Ghi
thành công thì `update_option('rom_search_index_version', time(), true)` (có
autoload, vì mọi trang đều đọc option này).

---

## 4. Giữ cho index luôn mới

### 4.1 Full rebuild: cron, mặc định mỗi 10 phút

- Schedule riêng tên `rom_search_index`, interval lấy từ `rom_search_cron_interval()`.
- Cron hook `rom_search_index_rebuild` gọi `rom_search_index_build()`.

`rom_search_index_build()` đi qua toàn bộ bài publish bằng `WP_Query` theo từng
trang `rom_search_build_batch()` bài, với `no_found_rows` và `update_post_meta_cache`
/ `update_post_term_cache` bật, để mỗi trang chỉ tốn vài query và RAM không phình
khi catalogue lớn. Cuối cùng ghi file một lần. Nút **Rebuild now** gọi đúng hàm này.

Full rebuild là **nguồn chân lý** của nội dung file. Nó chữa mọi sai lệch do những
thay đổi không bắn hook: SQL trực tiếp, import, đổi tên term publisher, regenerate
thumbnail. Trường hợp xấu nhất với loại thay đổi đó là file cũ trong vòng một chu
kỳ cron.

### 4.2 Incremental patch khi đổi trạng thái

Dùng `transition_post_status` cho post type `post`, cộng `deleted_post` cho
trường hợp xoá vĩnh viễn (xoá vĩnh viễn không bắn transition). Đây là tối ưu độ
trễ đặt trên nền cron: game vừa publish search ra được ngay, không phải chờ tới
10 phút.

| Chuyển trạng thái | Hành động |
|---|---|
| bất kỳ → `publish` (kể cả update một bài đang publish) | **upsert**: bỏ record cũ cùng `id`, prepend record mới |
| `publish` → trạng thái khác | **remove** record có `id` này |
| xoá vĩnh viễn | **remove** |
| không `publish` → không `publish` | không làm gì, vì bài chưa từng ở trong index |

Bấm Update một bài đang publish cũng bắn `publish → publish`, nên sửa title,
version, size, thumbnail hay publisher đều đi đường upsert. Không cần case riêng
cho edit.

**Gộp ghi:** `rom_search_index_queue($id, $remove)` chỉ xếp id vào một mảng
static. Lần xếp đầu tiên trong request đăng ký `shutdown` (với 0 accepted args,
vì `shutdown` truyền một tham số mà hàm đang dùng `strict_types`). Lúc
`shutdown`, hàm được gọi không tham số và **đọc file một lần, apply hết, ghi một
lần**: bulk edit 50 bài thành đúng một lần ghi. Chỉ gộp ở tầng hook là không đủ,
vì nếu lúc flush vẫn vá từng id thì vẫn là 50 lần read-modify-write.

Lúc flush, post được **đọc lại từ DB** (`get_post`) chứ không tin vào transition
đã xếp hàng. Nhờ vậy cái vào file là trạng thái cuối của post trong request: publish
rồi về draft ngay trong cùng request thì không để lại gì.

Lúc flush mà **chưa có file** (hoặc file hỏng, decode không ra) thì chạy full
build thay vì vá.

Một lần vá là decode file, sửa mảng trong RAM, ghi lại: vài milli giây.

### 4.3 Đặt lịch cron

Hook ở `init` **priority 20**, vì interval có thể lấy từ ACF và ACF đăng ký field
ở `init` trước đó.

- Chưa có event → schedule. Nếu chưa từng build (version = 0) thì cho chạy
  **ngay**, để một lần deploy chưa có index có index ở lần cron đầu tiên. Đã có
  index thì lần chạy đầu cách một interval.
- Đã có event → so `$event->interval` với `rom_search_cron_interval()`. Khớp thì
  thoát ngay; lệch thì clear rồi schedule lại (xem §7.2).

---

## 5. Cache busting

PHP đẩy URL index xuống JS là `…/index.json?v=<build>`, đọc từ option
`rom_search_index_version`. Mỗi lần build thì `v` đổi, tức là cache key đổi. Một
trang HTML mới luôn trỏ tới bản mới, còn bản thân từng URL có thể cache bao lâu
cũng được.

`rom_search_index_url()` trả về chuỗi rỗng khi version = 0 hoặc file không tồn
tại. Khi đó JS không nhận cấu hình search và dropdown không chạy.

Một điểm cần biết, để sau không nhầm là bug: token `?v=` nằm trong HTML, mà HTML
bị edge-cache, nên visitor nhận trang đã cache sẽ fetch đúng bản index của lúc
trang đó được render. Purge-on-publish lo phần quan trọng, còn cửa sổ cron đằng
nào cũng là 10 phút.

**Header của file trên nswvault PROD (kiểm 21/10):** `content-type: application/json`,
có gzip, có `ETag`/`Last-Modified`, **không có `Cache-Control`**, và
`cf-cache-status: DYNAMIC` (Cloudflare không cache `.json` theo mặc định). File
vẫn chạy đúng, browser chỉ revalidate bằng ETag. Muốn serve từ edge và cache dài
thì làm bước 4 ở §13.

---

## 6. File index là public

Cái gì browser fetch được thì `curl` fetch được, nên một lần GET là lấy trọn
index. Nội dung là id, title, URL, thumbnail, publisher, version, size của các
game đã publish. Tất cả đã có sẵn trên các trang archive và trong sitemap.

Để file không bị dùng ở trang của người khác, dựng một Cloudflare Custom Rule cho
path `/wp-content/uploads/search-index/`: block khi `Sec-Fetch-Site` không phải
`same-origin` **và** host trong `Referer` không phải site này. Browser gửi
`Sec-Fetch-Site` trên đúng loại fetch module này dùng, và request same-origin
mang `Referer` đầy đủ với referrer policy mặc định, nên traffic hợp lệ đi qua.

nswvault PROD hiện **chưa có** rule này: request `Sec-Fetch-Site: cross-site` với
Referer lạ vẫn nhận 200 (kiểm 21/10).

---

## 7. Cấu hình

### 7.1 Tab "Live search" trong Options

ACF options page (`menu_slug` `options`), đọc lại qua `App_Options_Cache::get($key, $default)`.
Helper này trả `$default` khi field chưa lưu hoặc là chuỗi rỗng.

| Field | Kiểu | Mặc định | Ràng buộc | Ý nghĩa |
|---|---|---|---|---|
| `search_live_header` | true_false | bật | | dropdown cho ô ở header |
| `search_live_filter` | true_false | bật | | dropdown cho ô ở filter bar |
| `search_cron_minutes` | number | 10 | 1–1440 | chu kỳ full rebuild, phút |
| `search_build_batch` | number | 300 | 50–5000, bước 50 | số post mỗi query khi full rebuild |
| `search_index_status` | message | | | hiện trạng index + nút **Rebuild now** |

Hai field số: **có giá trị > 0 thì field thắng, để trống thì dùng constant** (§7.4).

**Tắt một vị trí chỉ tắt phần gợi ý.** Ô input và nút submit là markup thường,
vẫn chạy như form search bình thường, nên tắt chỉ là giảm tính năng. Panel của vị
trí bị tắt **không được in ra** (không để lại div rỗng). Tắt cả hai thì
`rom.search` không được đẩy xuống JS.

Ba hàm đọc cấu hình: `rom_search_location_enabled('header'|'filter')`,
`rom_search_cron_interval()` (trả giây), `rom_search_build_batch()`.

### 7.2 Đổi chu kỳ thì job tự dịch theo

WordPress lưu interval **cùng event đã nằm trong queue**, nên con số mới, dù lưu từ
trang Options hay sửa thẳng constant, **không bao giờ** với tới event đã schedule,
và chu kỳ cũ sẽ sống mãi. Vì vậy hook ở `init` (§4.3) so `$event->interval` với
`rom_search_cron_interval()`. Lệch thì clear rồi schedule lại một interval sau,
không rebuild ngay. Khớp thì thoát.

So ở một chỗ này là phủ **cả hai nguồn**, nên không cần hook `acf/save_post` để đặt
lại cron.

Nó phải là phép **so sánh**, không phải reschedule vô điều kiện. Đặt lại mỗi request
thì hẹn giờ bị đẩy lùi qua mọi lần cron chạy, và rebuild **không bao giờ xảy ra**.
Lỗi này không có triệu chứng nào ngoài việc index âm thầm cũ dần.

### 7.3 Nút Rebuild now

`admin-post.php?action=rom_search_rebuild`, có nonce (`wp_nonce_url` /
`check_admin_referer`), chặn bằng `manage_options`. Chạy `rom_search_index_build()`
rồi redirect về referer (fallback `admin.php?page=options`).

Build **thành công** thì đẩy lần cron kế tiếp ra trọn một chu kỳ kể từ lúc đó. Cron
ở đây là trần về độ tươi chứ không phải lịch cố định, và lần rebuild tay vừa thoả
trần đó, nên bỏ được lần rebuild trùng khi bấm tay ngay trước giờ job đến hạn.
Build **thất bại** thì không đẩy: build lỗi không được mua thêm thời gian cho trạng
thái lỗi, vì retry tự động là thứ dễ cứu nó nhất.

Kết quả báo qua transient `rom_search_rebuild_notice_<user_id>` (sống 60 giây), hiện
bằng `admin_notices`: thành công thì "Search index rebuilt: N games.", lỗi thì nhắc
kiểm quyền ghi `wp-content/uploads`. Không đưa kết quả lên query string, vì URL có
thể bị copy và chia sẻ.

Field `search_index_status` render ở thời điểm hiển thị, qua
`acf/render_field/key=search_index_status`, nên số liệu và nonce luôn mới. Nó hiện:

- chưa có index: "No index yet…";
- có index: số game, dung lượng, build cách đây bao lâu;
- lịch: "Next scheduled rebuild in X", hoặc "Rebuild is X overdue — wp-cron may not
  be running" khi đã quá hạn (ở local thường là do loopback wp-cron không tới
  được site), hoặc "No rebuild is scheduled".

### 7.4 Constants

Khai báo bằng `defined() || define()` trong `inc/search-index.php`, nên
`wp-config.php` override được từng cái theo môi trường mà không sửa theme.

| Constant | Giá trị | Ý nghĩa |
|---|---|---|
| `ROM_SEARCH_CRON_INTERVAL` | `10 * MINUTE_IN_SECONDS` | mặc định khi `search_cron_minutes` trống |
| `ROM_SEARCH_BUILD_BATCH` | `300` | mặc định khi `search_build_batch` trống |
| `ROM_SEARCH_MAX_RESULTS` | `6` | số record trong dropdown |
| `ROM_SEARCH_QUERY_MIN` | `2` | query ngắn hơn thì không hiện gì |
| `ROM_SEARCH_DEBOUNCE_MS` | `100` | debounce khi gõ |

Hằng nội bộ (`const`, không override): `ROM_SEARCH_VERSION_OPTION` =
`rom_search_index_version`, `ROM_SEARCH_REBUILD_ACTION` = `rom_search_rebuild`,
`ROM_SEARCH_CRON_HOOK` = `rom_search_index_rebuild`.

### 7.5 Dữ liệu đẩy xuống JS

Trong `rom_enqueue_scripts()` (`functions.php`), qua `wp_localize_script('rom-main', 'rom', …)`:

```js
rom.search = {
  index:    "https://…/search-index/index.json?v=1792569722",
  min:      2,    // ROM_SEARCH_QUERY_MIN
  max:      6,    // ROM_SEARCH_MAX_RESULTS
  debounce: 100,  // ROM_SEARCH_DEBOUNCE_MS
  at:       { header: true, filter: true }
}
```

Chỉ đẩy khi URL index không rỗng **và** ít nhất một vị trí đang bật. JS coi việc
thiếu `rom.search` là "không đụng vào ô search". Constant là nguồn duy nhất, không
chép đôi trong JS.

---

## 8. Front-end (`js/function.js`)

**Markup cần có**

| | Ô input | Panel (nhận class `open`) | Chỗ ghi kết quả | Click ngoài vùng này thì đóng |
|---|---|---|---|---|
| Header | `#header-search-input` | `#search-results` | `#search-results-inner` | `#header-search` |
| Filter bar | `#home-filter-search` | `#filter-search-results` | `#filter-search-results-inner` | `.relative` gần nhất bọc input |

Cả hai panel có `role="listbox"` và `aria-label`, class `search-results-dropdown`;
panel ở filter bar thêm `search-results-dropdown-inline`. Input dùng
`autocomplete="off"` và nằm trong một `<form method="GET">` với `name="s"`.

**Hành vi**

- `ensureIndex()` fetch index đúng một lần, lazy, lúc `focus` vào bất kỳ ô nào
  (và lúc gõ, nếu chưa fetch). Cờ `searchStarted`/`searchFailed` chặn fetch lần
  hai. Fetch dùng `credentials: 'omit'`, vì đây là file tĩnh public và không có
  cookie thì edge cache không có gì để vary.
- Khi file về: dựng `searchKeys` (title và `app_name` đã fold) một lần, rồi báo cho
  **mọi** ô đã đăng ký.
- `attachLiveSearch(input, dropdown, inner, outside)` gắn **một** ô vào panel của
  nó. Index dùng chung; trạng thái mở, timer debounce, con trỏ bàn phím và query
  đang chờ thì thuộc từng ô, nên hai ô không đè selection hay đóng panel của nhau.
  Hàm trả `{ hide }`; nút đóng search ở header dùng nó để đóng panel đi cùng.
- Mỗi lần `input`:
  - query (đã trim) ngắn hơn `min` → ẩn panel;
  - fetch đã lỗi → ẩn panel;
  - index chưa về → giữ query, hiện skeleton (3 dòng), áp query ngay khi file về;
  - có index → sau debounce `debounce` ms thì filter và render.
- Filter và rank: prefix của title (0) → prefix của `app_name` (1) → substring của
  title (2) → substring của `app_name` (3). Cùng rank thì giữ thứ tự trong file
  (mới nhất trước). Cắt ở `max`.
- Mỗi kết quả là một `<a class="search-result-item" role="option">` gồm thumbnail
  (`loading="lazy"`, không có thì placeholder), title, và dòng meta
  `publisher · vVERSION · SIZE` (chỉ những phần có giá trị). Mọi giá trị đi qua
  escape HTML/attribute trước khi vào `innerHTML`.
- Không có kết quả: `No games found for "<query>"`.
- Dưới cùng là link "View all results for …", **cố tình site-wide** (`/?s=`),
  khớp với nội dung dropdown đang là site-wide. Nút submit của filter bar mới là
  thứ lọc trong term.
- Bàn phím: ↑/↓ chọn vòng tròn; <kbd>Enter</kbd> chỉ điều hướng khi đang có item
  được chọn, không chọn gì thì vẫn submit form như cũ; <kbd>Esc</kbd> đóng panel.
- Click ra ngoài vùng `outside` thì đóng panel.
- Fetch lỗi thì ẩn dropdown; form vẫn submit bình thường.

**CSS** (`assets/css/input.css`, block LIVE SEARCH RESULTS DROPDOWN):
`.search-results-dropdown` (ẩn, `position:absolute; top:100%`, `.open` thì hiện
với animation 150 ms), `.search-results-dropdown-inline` (viền và bo riêng cho panel
nằm dưới một ô), `.search-result-item` / `.active`, `.search-result-thumb`,
`.search-result-thumb-placeholder`, `.search-result-info`, `.search-result-title`,
`.search-result-meta`, `.search-result-dot`, `.search-no-results`,
`.search-view-all`, `.search-skeleton`, `.skeleton-pulse`.

---

## 9. Khác gì so với live search gọi server

1. **Không match post content.** `s=` của `WP_Query` quét `post_content`, index này
   thì không. Một từ chỉ có trong body sẽ không ra quick result. Trang `/?s=` không
   đổi và vẫn match content.
2. **Thứ tự kết quả** là rank prefix-first, không phải relevance của MySQL.
3. **Có thể cũ tới một chu kỳ cron** với thay đổi mà incremental patch không thấy
   (§4.1). Đổi trạng thái thì có hiệu lực ngay.
4. **Mỗi visitor có mở ô search tải một lần ~30 KB gzip**, thay cho một request mỗi
   keystroke.
5. **Không rate limit**; §6 nói rõ index phơi ra những gì.

---

## 10. Đưa sang site khác

Copy `inc/search-index.php`, block LIVE SEARCH trong `js/function.js`, block CSS ở §8,
tab ACF ở §7.1, rồi sửa những chỗ phụ thuộc site sau:

| Chỗ | nswvault đang dùng | Việc cần làm |
|---|---|---|
| Post type | `post` (build query, `rom_search_index_apply`, hai hook §4.2) | đổi cho khớp |
| Meta version / size | `version`, `_apkfilesize` | đổi meta key, hoặc bỏ field |
| Meta tên phụ | `app_name` | đổi key hoặc bỏ field (JS tự bỏ qua khi không có) |
| Taxonomy publisher | `publisher` | đổi taxonomy hoặc bỏ field |
| Size thumbnail | `thumbnail` (300px rộng) | chọn size nhỏ có sẵn của site đích |
| Đọc option | `App_Options_Cache::get()` | thay bằng `get_field($key, 'option')` hoặc helper của site, giữ quy tắc "trống thì dùng default" |
| ACF options page | `menu_slug` `options` | gắn tab vào options page của site đích; sửa fallback redirect ở §7.3 |
| Tiền tố | `rom_` / `ROM_` / object JS `rom` | đổi nếu site đích dùng tiền tố khác |
| Textdomain | `rom` | đổi |
| ID markup | bảng ở §8 | khớp với header / form search của site đích |
| Include + localize | `functions.php` | thêm vào danh sách include; thêm khối `search` vào dữ liệu localize (§7.5) |

Nếu site đích có live search cũ qua `admin-ajax.php`, xoá handler đó cùng mọi rate
limiter chỉ nó dùng, và xoá code JS gọi nó (FormData, POST, AbortController,
debounce cũ).

Không cần `.gitignore`: file index nằm trong `wp-content/uploads/`, ngoài thư mục
theme.

---

## 11. Danh sách file (nswvault)

| File | Vai trò |
|---|---|
| `inc/search-index.php` | constants, builder, patcher, cron, nút rebuild, notice, status field |
| `functions.php` | include module; đẩy `rom.search` xuống JS |
| `js/function.js` | fetch một lần, fold, filter trong RAM, debounce, gắn nhiều ô |
| `setting/options.php` | tab **Live search** + 5 field |
| `header.php` | panel header, in có điều kiện |
| `template-parts/search-filter-bar.php` | panel cho ô search của filter bar, in có điều kiện |
| `assets/css/input.css` | style dropdown, biến thể `-inline` (build ra `output.css`) |

Không sửa nginx.

---

## 12. Verify

1. `php -l` mọi file PHP đã sửa; `node --check js/function.js`.
2. Build index từ CLI (`rom_search_index_build()` qua `wp-load.php`). Assert file
   tồn tại, decode được, `n` = số bài publish, và có `app_name` đúng ở những bài
   mà `app_name` khác title. Ghi lại size raw và gzip.
3. `curl -I` URL index: 200, `content-type: application/json`, có gzip.
4. Publish một bài draft → search ra ngay; trash nó → biến mất ngay; bulk edit vài
   bài → file chỉ được ghi một lần (mtime đổi một lần, `v` đổi một lần).
5. Phá một record trong file rồi chạy cron event bằng tay → file được ghi lại đúng.
6. Đổi `search_cron_minutes` → event có interval mới, không rebuild ngay.
7. Trên Chrome: focus ô search → đúng **một** request index, gõ bao nhiêu cũng không
   thêm request. Query một ký tự không hiện gì; tên game ra kết quả; thử `crab's`
   và tên có dấu; ↑/↓, <kbd>Enter</kbd>, <kbd>Esc</kbd>, click ra ngoài, link
   "View all results" chạy đúng; hai ô (header, filter bar) không ảnh hưởng nhau.
8. Tắt từng vị trí trong Options → panel của vị trí đó không còn trong HTML, form
   vẫn submit được.

---

## 13. Triển khai PROD

1. Deploy code.
2. Build index một lần từ CLI hoặc bấm **Rebuild now**, không chờ cron.
3. Xác nhận URL index trả 200 và dropdown chạy.
4. Cloudflare:
   - Custom Rule ở §6 cho path `/wp-content/uploads/search-index/`.
   - Cache Rule cho cùng path: Eligible for cache, Edge TTL dài (ví dụ 1 năm),
     giữ query string trong cache key. Mỗi build có `?v=` mới nên cache dài vẫn an
     toàn.
   - Nếu có rule cache HTML loại trừ theo đuôi file thì kiểm `json` không bị rule
     HTML bắt nhầm.

   nswvault PROD hiện chưa làm bước 4 (kiểm 21/10: `DYNAMIC`, không `Cache-Control`,
   cross-site vẫn 200).
5. Kiểm wp-cron thật sự chạy (status field không báo "overdue"). Nếu site tắt
   `DISABLE_WP_CRON` thì phải có system cron gọi `wp-cron.php`.
