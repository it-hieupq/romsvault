v1.0
- build tailwindcss


Remove all acf lib
remove aff_15
add config redis
update lại menu site navigation -> primary

Consolidated Nonce Generation:
- Implemented whitelist validation for nonce actions (comment, rating, report)
- Updated JS handlers to use unified `get_nonce` action
- Adjusted rate limiting for comments (5 comments per 3 minutes)

AppSecurity Integration in `inc/ajax.php`:
- Applied `AppSecurity::setSecurityHeaders()` to all AJAX handlers
- Implemented `AppSecurity::validate_input($_POST)` for initial request sanitization
- Replaced direct nonce checks with `AppSecurity::validate_nonce()`
- Added `AppSecurity::checkRateLimit()` for throttling actions
- Utilized specific sanitization methods: `clean_text_input`, `clean_textarea_input`, `clean_email_input`, `clean_url_input`, `validate_number`

Rom admin management (`inc/setting-rom.php`):
- Added bulk actions to set the ACF `hide` field (Public / Hide Download PC / 404 PC) for multiple roms at once, with a success admin notice
- Added a sortable "Views" column to the rom list table (orders by `views` meta)

Bug fixes:
- Fixed WP 6.7 `_load_textdomain_just_in_time` notices for `acf` / `romsfun` domains: register the Site Options page + field group on `acf/init` (`inc/options.php`) and move `register_nav_menus` to `init` (`functions.php`) so translated labels no longer load before `init`
- Removed a leftover debug `error_log(print_r($wp_rewrite->rules, true))` on `init` (`inc/setting-rom.php`) that flooded `debug.log` with empty lines on every request

Performance — view/download tracking (`inc/ajax-tracking.php`):
- Rewrote AJAX view/download tracking to a Redis write-behind buffer: per-request counts go to Redis (throttled once per visitor/post/6h via `SET NX`, accumulated with `HINCRBY`) instead of a synchronous `postmeta` write on every pageview, which was saturating PHP-FPM and the DB
- A WP-Cron job (every 5 min) flushes buffered counts to `postmeta`/`termmeta` via the WP meta API using an atomic Redis `RENAME` swap; falls back to a direct meta update if Redis is unavailable
- Redis-path errors are logged with a `[RF Tracking]` prefix
