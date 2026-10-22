# Quick search index cho romsvault — plan triển khai

Trạng thái: đã duyệt phương án **index chia shard theo tiền tố từ** (21/10/2026).
Thiết kế gốc (nswvault, một file `index.json`): [search-index-design.md](./search-index-design.md).
Plan này chỉ ghi những chỗ romsvault **khác** thiết kế gốc; phần không nhắc tới thì
giữ nguyên như bản gốc.

---

## 1. Vì sao không dùng một file

Đo ngày 21/10/2026 trên 52.478 rom đang publish:

| | Raw | Gzip |
|---|---|---|
| Một file, schema gốc (URL tuyệt đối) | 12,5 MB | 1,7 MB |
| Một file, rút gọn (mảng, path tương đối) | 6,9 MB | 1,6 MB |

Gấp khoảng 55 lần nswvault (~30 KB gzip). Mỗi visitor focus ô search phải tải
1,7 MB và parse 7–12 MB JSON, quá nặng trên mobile. Mỗi lần vá tăng dần cũng phải
đọc rồi ghi lại cả file nhiều MB.

## 2. Ý tưởng: shard theo tiền tố từ

- Tên game được fold (§3) rồi tách thành các từ `[a-z0-9]+`.
- Mỗi rom được ghi vào shard của **từng từ** trong tên, khoá bằng 2 ký tự đầu của
  từ. Ví dụ "Super Mario World" nằm trong `su`, `ma`, `wo`.
- Shard 2 ký tự nào vượt `ROM_SEARCH_SHARD_LIMIT` (1.500 record) thì **chia tiếp
  thành shard 3 ký tự** (`ma` → `mar`, `meg`, …). Manifest đánh dấu khoá đã chia.
- Không đưa vào khoá: từ 1 ký tự và stopword (`ROM_SEARCH_STOPWORDS`: the, of, and,
  no, de, la, le, el, an, in, to). Record vẫn tìm được qua các từ khác trong tên.
- Với khoá đã chia, shard 2 ký tự (`ka.json`) vẫn tồn tại nhưng **chỉ chứa từ đúng
  2 ký tự đó** (ví dụ game tên "Ka"). Manifest liệt kê nó trong `split` để JS biết.
- Ngoài các từ thường, tên còn được đọc thêm một lần sau khi **bỏ mọi ký tự nối**
  (không phải chữ, số, khoảng trắng): "F.E.A.R." cho thêm `fear`, "Pac-Man" cho thêm
  `pacman`, "I/O" cho thêm `io`. JS chỉ cần tách query như thường và so với tập từ
  gộp này.
- Browser tải đúng **một** shard cho mỗi truy vấn: chọn một từ trong query (≥ 2 ký
  tự, không phải stopword; ưu tiên shard **đã tải**, sau đó tới shard nhỏ nhất theo
  manifest), tải shard đó, rồi lọc trong RAM: **mọi** từ trong query phải là tiền tố
  của một từ nào đó trong tên. Shard của bất kỳ từ nào cũng chứa đủ kết quả, nên
  "mario kart" dùng lại `mar` đã tải khi gõ "mario".
- Thứ hạng: tên bắt đầu bằng cả cụm query → tên bắt đầu bằng từ đầu của query →
  còn lại; cùng hạng thì theo thứ tự trong shard (mới nhất trước).

Hệ quả cần biết:

- Chỉ khớp **đầu từ**: `mario` tìm ra "Super Mario World", `ario` thì không.
  Trang `/?s=` không đổi.
- Với khoá đã chia (`ma`, `po`, `st`, `su`, …) gõ 2 ký tự chỉ ra game có đúng từ
  đó; phải gõ tới ký tự thứ 3 mới có kết quả đầy đủ.
- 17 rom không có khoá nào (đo 21/10/2026): tên một ký tự (`D`, `X`, `Z`, `N+`…),
  vốn dưới ngưỡng query 2 ký tự, và tên chỉ có chữ CJK.

**Số đo build thật (local, 21/10/2026, 52.478 rom):**

| | |
|---|---|
| Số shard | 1.523 (38 khoá 2 ký tự bị chia) |
| Shard gzip | lớn nhất 67 KB (`ni`, 1.470 record), trung bình 4,9 KB, trung vị 0,8 KB |
| Tổng raw trên đĩa | 28,7 MB |
| Manifest | 32 KB raw, 14,9 KB gzip |
| Thời gian build | 18,5 s lần đầu (cache lạnh), ~4,5 s các lần sau |
| RAM đỉnh | 112–137 MB |

## 3. Fold

PHP (lúc build, để ra khoá) và JS (lúc tìm, trên cả query lẫn title) phải fold ra
cùng một chuỗi:

1. decode entity (`html_entity_decode`, `ENT_QUOTES`);
2. bỏ dấu: PHP `remove_accents()`; JS `normalize('NFD')`, bỏ U+0300–U+036F, cộng
   bảng nhỏ cho ký tự NFD không tách được, theo đúng `remove_accents` (ß → s, æ → ae,
   œ → oe, ø → o, ł → l, đ/ð → d, þ → th, ħ → h, ı → i, ĳ → ij, ŋ → n, ŧ → t, ſ → s, ĸ → k);
3. chữ thường;
4. tách từ bằng `[a-z0-9]+`. Dấu nháy uốn, gạch, chấm… đều là dấu ngăn từ, nên
   không cần bảng ép dấu nháy như bản gốc. Phía title còn thêm tập từ "compact" (§2).

## 4. Lưu trữ

Thư mục: `wp-content/uploads/search-index/`.

**Shard** `<key>.json`: mảng record, mới nhất trước (`post_date DESC`). Record là
mảng theo vị trí để file gọn:

```json
[39563, "Super Mario World", "/roms/snes/super-mario-world/", "2025/02/smw-300x420.webp", "SNES", "512 KB"]
```

| # | Field | Nguồn |
|---|---|---|
| 0 | id | `$post->ID` |
| 1 | title | `post_title` thô, decode entity (không qua filter `the_title`, để khỏi chạy 52k lần filter) |
| 2 | path | `get_permalink()` bỏ phần home URL |
| 3 | thumb | ảnh size `thumbnail`, bỏ tiền tố `baseurl` của uploads; URL nằm ngoài uploads thì giữ nguyên tuyệt đối; không có ảnh thì `""` |
| 4 | console | tên term `console` đầu tiên |
| 5 | size | meta `file_size`, fallback `download_list_0_file_size` |

Rom `hide = restricted` vẫn vào index, vì trang archive vẫn hiện chúng.

**Manifest** `manifest.json`:

```json
{"v": 1792569722, "n": 52478, "img": "https://romsvault.com/wp-content/uploads/",
 "split": ["ma", "po", …], "keys": {"su": [1203, "9f3a01bc"], "mar": [1183, "…"]}}
```

- `keys`: khoá → `[số record, hash nội dung]`. JS tải shard bằng
  `<key>.json?h=<hash>`. Hash (crc32b) chỉ đổi khi nội dung shard đổi, nên rebuild
  định kỳ không làm vỡ cache của shard không đổi.
- `v`: thời điểm build, cache-buster của chính manifest (`manifest.json?v=` lấy từ
  option `rom_search_index_version`).

**Ghi:** mỗi file ghi `.tmp` rồi `rename()` (atomic). Full build bỏ qua shard có
hash không đổi, xoá shard không còn khoá, ghi manifest **sau cùng**. Ghi lỗi thì
không đổi version. Cửa sổ lệch giữa manifest cũ và shard mới rất ngắn, và chỉ làm
lệch một vài kết quả.

## 5. Giữ index luôn mới

- **Full rebuild** (cron): mặc định **mỗi giờ** (`ROM_SEARCH_CRON_INTERVAL`), vì một
  lần chạy duyệt cả 52k rom. Batch `WP_Query` 500 bài, prime meta, term và
  thumbnail cache, `wp_cache_flush_runtime()` sau mỗi batch để RAM không phình.
- **Patch tăng dần**: như bản gốc (`transition_post_status`, `deleted_post`, gộp ghi
  ở `shutdown`), cho post type `rom`. Khác ở chỗ phải biết shard **cũ** của bài:
  khoá cũ lấy từ title trước khi sửa qua `post_updated` (`$post_before`), và từ post
  object mà `deleted_post` truyền vào. Lúc flush: bỏ id khỏi shard cũ ∪ shard mới,
  publish thì prepend vào shard mới, rồi ghi lại các shard bị đụng và manifest đúng
  một lần. Shard vượt ngưỡng vì patch thì để nguyên, lần full rebuild kế tiếp chia lại.
- Grabber import hàng loạt trong một request chỉ ghi một lần nhờ gộp ở `shutdown`.
- Patch và **pha ghi** của full build chạy dưới cùng một `flock` (`search-index/.lock`),
  nên không bao giờ đan xen read-modify-write. Pha đọc DB của full build (vài giây)
  không bị khoá: patch rơi vào lúc đó sẽ bị build ghi đè, và có lại ở lần build sau.
- Không có manifest, hoặc shard bị đụng không decode được, thì flush **dời lịch full
  rebuild về ngay bây giờ** chứ không build trong request, để request publish không
  phải chờ vài chục giây (đo: 0,01 s).
- Full build lấy toàn bộ id một lần (`post_date DESC, ID DESC`) rồi chia batch bằng
  `post__in`. Phân trang bằng offset sẽ bỏ sót một rom mỗi khi có rom khác rời
  publish giữa lúc build.
- Version luôn tăng ngặt (`max(time(), v cũ + 1)`, `v` cũ đọc trong lock), nên hai lần
  ghi trong cùng một giây vẫn ra hai URL `?v=` khác nhau.
- `n` trong manifest được patch cộng/trừ theo từng rom (có trong shard trước và sau
  patch hay không), full build tính lại từ đầu.

## 6. Bước triển khai

Mỗi bước xong thì dừng lại để duyệt.

1. **Builder và lưu trữ** — `inc/search-index.php` (constants, fold, khoá, record,
   full build, ghi atomic, version, URL manifest), include trong `functions.php`.
   Kiểm: `php -l`; build từ CLI; `n` = 52.478; mọi khoá trong manifest có file và
   số record khớp; đo shard lớn nhất, trung bình (raw và gzip) và thời gian build;
   `curl -I` thấy `application/json` và gzip; build lần hai không ghi lại shard nào.
2. **Patch tăng dần** — hook cho `rom`, hàng đợi, flush ở `shutdown`.
   Kiểm: publish → có trong shard; đổi title → rời shard cũ, vào shard mới; trash →
   biến mất; bulk edit → manifest ghi một lần.
3. **Cron và tab Options "Live search"** — 5 field như bản gốc (mặc định chu kỳ 60
   phút, batch 500), nút Rebuild now (`set_time_limit` vì build mất vài chục giây),
   notice, status field. Đọc bằng `get_field( …, 'option' )`, trống thì dùng constant.
   Kiểm: đổi chu kỳ → event đổi interval, không rebuild ngay; Rebuild now chạy và báo.
4. **Front-end** — `js/main.js` + CSS; panel cho `#header-search` (header) và
   `#searchInput` (filter bar); đẩy `rom.search` qua `wp_localize_script`.
   Kiểm: `node --check`; Chrome: focus thì chỉ tải manifest, gõ 2–3 ký tự thì đúng
   một shard, gõ tiếp không thêm request; ↑/↓/Enter/Esc; hai ô không ảnh hưởng nhau.
5. **Tài liệu và PROD** — cập nhật ARCHITECTURE, DATA_DICTIONARY; deploy, build lần
   đầu trên PROD; Cloudflare rule cho `/wp-content/uploads/search-index/` (chặn
   hotlink, cache dài theo query string).
