# DEPLOYMENT

Các bước đưa từng tính năng lên PROD. Mỗi tính năng một mục: làm theo thứ tự,
lệnh nào có `$VAR` thì thay bằng giá trị thật (không ghi token vào file này).
Sync DB local → PROD: xem [COMMAND.md](./COMMAND.md).

## Live search (quick search index)

Thiết kế: [search-index-plan.md](./search-index-plan.md). Index là JSON tĩnh trong
`wp-content/uploads/search-index/`, không nằm trong git, PROD tự build.

**Yêu cầu:** PHP ≥ 7.4, `wp-content/uploads` ghi được, wp-cron chạy được.

1. Deploy code, rồi purge cache HTML trên Cloudflare. Không cần build Tailwind.
2. Không cần build tay: index tự build ở lần wp-cron đầu tiên (5–20 s, ~150 MB RAM),
   sau đó mỗi giờ. Trước khi public site, kiểm tab **Configs → Live search** đã báo
   số game; cần ngay thì bấm **Rebuild now**.
3. Kiểm file index (200, `application/json`, có gzip):
   ```bash
   curl -sI -H 'Accept-Encoding: gzip' https://romsvault.com/wp-content/uploads/search-index/manifest.json
   curl -s https://romsvault.com/wp-content/uploads/search-index/manifest.json | head -c 120
   ```
   `"n"` phải bằng số rom publish.
4. Kiểm cron: tab Live search không được báo *overdue*.
   ```bash
   wp cron event list --fields=hook,next_run_relative,recurrence | grep rom_search
   ```
   Báo overdue hoặc PROD có `DISABLE_WP_CRON` thì thêm system cron:
   ```bash
   */5 * * * * curl -s https://romsvault.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
   ```
5. Test trên trình duyệt: gõ tên game ở ô header (toàn site); vào một trang console,
   gõ ở ô filter (chỉ ra game của console đó).
6. Tuỳ chọn, Cloudflare cho path `/wp-content/uploads/search-index/`:
   - Cache Rule: Eligible for cache, Edge TTL dài, **giữ query string** trong cache key.
   - Custom Rule chặn hotlink: block khi `Sec-Fetch-Site` ≠ `same-origin` và Referer
     không phải romsvault.com.

**Tắt nhanh / rollback**

- Chỉ tắt dropdown: Configs → Live search → tắt hai toggle. Ô search vẫn là form
  GET thường.
- Gỡ hẳn sau khi revert code:
  ```bash
  wp cron event delete rom_search_index_rebuild
  wp option delete rom_search_index_version
  rm -rf wp-content/uploads/search-index
  ```
