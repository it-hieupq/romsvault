# SEO_TITLES.md — Yoast title templates & per-term titles

Nguồn sự thật cho **title SEO** của site. Mọi giá trị ở đây đã được ghi vào DB (Yoast), không nằm
trong code theme. Sửa ở Yoast → cập nhật file này trong cùng lượt.

- Ghi lần đầu: **2026-10-19** (templates, title riêng của term, title home, đổi tên/slug console).
  Backup từng bước trong `sites/_cleanup/` đã xoá. Trạng thái **trước** mọi thay đổi này nằm trong
  `wp-docker/romsvault_prod-20261019-1927.sql.gz` (dump `romsvault_prod` ngay trước lần sync 19:27,
  tức dữ liệu sync ngày 2026-10-18). Rollback: lấy row `wpseo_titles`, `wpseo_taxonomy_meta`, postmeta
  `_yoast_wpseo_title` của page #293074 và các term ở § 7 từ dump đó.
- **Ghi option bằng code/SQL thì phải xoá indexable của object đó** (`wphh_yoast_indexable`, theo
  `object_type`/`object_sub_type`/`object_id`): Yoast cache title vào indexable lúc trang được xem lần
  đầu và không làm mới khi option đổi ngoài màn hình admin. Sửa trong admin Yoast thì không cần.
- Yoast `%%sep%%` = `|` (`separator = sc-pipe`), `%%sitename%%` = `ROMSVAULT.COM`.
- Theme **không** đăng ký biến Yoast riêng (`wpseo_register_var_replacement`). Biến dùng được: biến
  core của Yoast + `%%ct_<taxonomy>%%` (taxonomy theme đăng ký) + `%%cf_<meta_key>%%`.

## 1. Nguyên tắc

1. Keyword chính đứng đầu, đúng dạng người chơi gõ: `{game} {console} ROM`, `{console} ROMs`,
   `{console} emulator`, `{emulator} download`.
2. ≤ 60 ký tự. Không gắn brand vào rom/emulator/term (Google tự hiện site name); brand chỉ ở home,
   blog, page.
3. Tối đa 2 phần, 1 dấu `|`. **Mỗi title chỉ có `%%title%%`/`%%term_title%%` một lần.** Viết tắt +
   tên đầy đủ (`NDS` + `Nintendo DS`) là hai query khác nhau, không phải lặp.
4. Định dạng file chỉ được ghi khi **đúng với file site thật sự phát** (xem § 5).
5. Template Yoast không có điều kiện ⇒ template chỉ là fallback; traffic lớn đến từ title viết tay
   cho term nhiều rom (§ 3).

## 2. Templates — option `wpseo_titles`

Chỉ áp dụng khi bài/term **không có** title riêng (`_yoast_wpseo_title` / `wpseo_taxonomy_meta`).

| Key | Template | Ví dụ render |
|---|---|---|
| `title-rom` | `%%title%% %%ct_console%% ROM Download` | Pokemon Emerald GBA ROM Download |
| `title-emulator` | `%%title%% %%sep%% %%ct_emulator_console%% Emulator Download` | melonDS \| Nintendo DS Emulator Download |
| `title-post` | `%%title%% %%sep%% %%sitename%%` | What is Redump in ROMs? \| ROMSVAULT.COM |
| `title-page` | `%%title%% %%page%% %%sep%% %%sitename%%` (giữ mặc định) | Contact Us \| ROMSVAULT.COM |
| `title-tax-console` | `%%term_title%% ROMs %%sep%% Free Download %%page%%` | Sega Saturn ROMs \| Free Download |
| `title-tax-collection` | `%%term_title%% ROMs %%sep%% Full Game Collection %%page%%` | God of War ROMs \| Full Game Collection |
| `title-tax-genre` | `%%term_title%% ROMs %%sep%% Best Games to Download %%page%%` | Action ROMs \| Best Games to Download |
| `title-tax-type` | `%%term_title%% ROMs %%sep%% Free Download %%page%%` | Undub ROMs \| Free Download |
| `title-tax-region` | `%%term_title%% ROMs %%sep%% Free Download %%page%%` | Japan ROMs \| Free Download |
| `title-tax-language` | `%%term_title%% ROMs %%sep%% Free Download %%page%%` | French ROMs \| Free Download |
| `title-tax-emulator_console` | `Best %%term_title%% Emulators %%sep%% Free Download %%page%%` | Best Nintendo DS Emulators \| Free Download |
| `title-tax-os` | `Emulators for %%term_title%% %%sep%% Free Download %%page%%` | Emulators for Android \| Free Download |
| `title-tax-dev` | `%%term_title%% Games %%sep%% ROMs Download %%page%%` | Capcom Games \| ROMs Download |
| `title-tax-pub` | `%%term_title%% Games %%sep%% ROMs Download %%page%%` | SEGA Games \| ROMs Download |
| `title-tax-category` | `%%term_title%% %%page%% %%sep%% %%sitename%%` | News \| ROMSVAULT.COM |

`%%page%%` chỉ in "Page N of M" (kèm separator) trên trang phân trang.

Noindex taxonomy: `pub`, `dev` (15k term, nội dung mỏng), `post_format`. Template `pack` /
`pack_console` (CPT cũ của romsfun) đã xoá; còn sót key breadcrumb `taxonomy-pack_console-ptparent`,
vô hại.

## 3. Title viết tay — option `wpseo_taxonomy_meta[{taxonomy}][{term_id}]['wpseo_title']`

### 3.1 Console — mọi console có rom (định dạng theo § 5)

| Slug | Rom | Title | Định dạng / keyword |
|---|---|---|---|
| `playstation-2` | 5.452 | PS2 ISO & ROMs \| PlayStation 2 Games Download | Redump ISO |
| `playstation` | 5.243 | PS1 ROMs & PSX ISOs \| PlayStation Games Download | Redump ISO; psx |
| `nintendo-ds` | 5.048 | NDS ROMs \| Nintendo DS Games Download | cartridge |
| `nes` | 3.154 | NES ROMs \| Nintendo Entertainment System Games | cartridge |
| `windows` | 3.118 | PC Games for Windows \| GOG & MagiPack Download | GOG, MagiPack |
| `playstation-portable` | 2.870 | PSP ISO & ROMs \| PlayStation Portable Games | Redump ISO |
| `ps3` | 2.807 | PS3 ISO & PKG \| PlayStation 3 Games Download | ISO decrypted, PSN PKG |
| `game-boy-advance` | 2.708 | GBA ROMs \| Game Boy Advance Games Download | cartridge |
| `super-nintendo` | 2.505 | SNES ROMs \| Super Nintendo Games Download | cartridge |
| `nintendo-wii` | 2.497 | Wii RVZ ROMs & WiiWare WAD \| Nintendo Wii Games | RVZ, WiiWare WAD |
| `xbox-360` | 2.391 | Xbox 360 ISO & XBLA ROMs \| Free Games Download | Redump ISO, XBLA |
| `playstation-4` | 1.814 | PS4 PKG ROMs \| PlayStation 4 Games Download | PKG (mã CUSA) |
| `nintendo-3ds` | 1.693 | Decrypted 3DS ROMs & CIA \| Nintendo 3DS Games | decrypted .3ds, CIA |
| `sega-genesis` | 1.349 | Genesis ROMs \| Sega Mega Drive Games Download | cartridge; genesis + mega drive |
| `xbox` | 1.205 | Xbox XISO ROMs \| Play on Xemu Emulator | XISO; xemu |
| `ps-vita` | 1.202 | PS Vita VPK & NoNpDrm ROMs \| Free Games Download | NoNpDrm, VPK |
| `game-boy` | 1.157 | GB ROMs \| Original Game Boy Games Download | cartridge |
| `game-boy-color` | 1.138 | GBC ROMs \| Game Boy Color Games Download | cartridge |
| `gamecube` | 875 | GameCube RVZ ROMs \| Play on Dolphin Emulator | RVZ; dolphin |
| `nintendo-64` | 871 | N64 ROMs \| Nintendo 64 Games Download | cartridge |
| `wii-u` | 842 | Wii U WUX ROMs \| Play on Cemu Emulator | WUX; cemu |
| `teknoparrot` | 399 | TeknoParrot ROMs \| Arcade Games for PC | arcade PC |
| `sega-cd` | 230 | Sega CD CHD ROMs \| Mega-CD Games Download | CHD; mega-cd |

Dùng template `title-tax-console` (định dạng không rõ hoặc quá ít rom): `sega-saturn` (1.183),
`dreamcast` (711), `nintendo-virtual-boy` (5), `nintendo-pokemon-mini` (5). Console 0 rom (`switch`,
`unknown`) không đặt title.

### 3.2 Type

| Slug | Rom | Title |
|---|---|---|
| `hack` | 3.596 | ROM Hacks \| Best Hacked Games Download |
| `fan-translation` | 3.339 | English Patched ROMs \| Fan Translations Download |
| `homebrew` | 279 | Homebrew ROMs \| Free Indie Console Games |

Term không có ở bảng § 3 dùng template § 2.

**Home** (static page #293074, postmeta `_yoast_wpseo_title`):
`Download Free ROMs & ISOs for Retro Consoles | ROMSVAULT.COM`

## 4. Ngoài Yoast

- Rom: 48.766 bài có `_yoast_wpseo_title` / `_yoast_wpseo_metadesc` riêng, viết theo mẫu từng console
  bằng tool **Insert SEO** (`inc/insert-seo.php`, thay `[title]`). Template `title-rom` chỉ phủ phần
  còn lại (~3.700 bài).
- Trang download (`/…/download/{n}/`): `k_change_download_title()` / `k_change_download_desc()`
  (`inc/utility.php`) đọc `_yoast_wpseo_title` của bài, chỉ thay `%%title%%` và bỏ `%%ct_console%%`
  ⇒ đừng đặt biến Yoast khác vào title riêng của rom.
- Rom `windows` không có title riêng render thành "{T} Windows ROM Download" (template không có điều
  kiện) ⇒ bài Windows mới nên được gán title riêng qua Insert SEO.
- Tên term lọt nguyên vào `%%ct_console%%` / `%%term_title%%` và h1 archive ⇒ giữ tên theo quy ước § 7
  (không ngoặc).

## 5. Định dạng file theo hệ (đo 2026-10-19, `download_list_0_file_type`)

| Hệ | File thật site phát |
|---|---|
| NES / SNES / GB / GBA / N64 | No-Intro (cartridge) |
| Nintendo DS | (trống) / DSi decrypted 241 |
| Nintendo 3DS | No-Intro decrypted 903, eShop 478, decrypted 147, CIA 118, Citra 34 |
| GameCube | RVZ 809 |
| Nintendo Wii | RVZ 1.859, WiiWare WAD 544, WAD 46 |
| Wii U | WUX 830 |
| Xbox | XISO 1.072 |
| Xbox 360 | Redump ISO 1.446, XBLA 725 |
| Windows | GOG 1.311, MagiPack 858, fan game 662 |
| PS1 / PS2 / PSP | Redump ISO |
| PS3 | ISO decrypted 1.812, PSN PKG 889 |
| PS4 | `file_type` trống; tên file mang mã CUSA (dump PKG), 7 bài ghi rõ PKG |
| PS Vita | NoNpDrm 640, PSV PSN 342, VPK 109, PSN 47 |
| Sega Genesis / Game Boy Color | cartridge (`file_type` phần lớn trống) |
| Sega CD | CHD 227 |
| Sega Saturn / Dreamcast | `file_type` phần lớn trống, CHD chỉ 61 / 42 ⇒ không ghi định dạng |
| TeknoParrot | trống (arcade PC) |

Link download gần như toàn bộ là `.zip` (NDS: zip 5.028 / 7z 15 / rar 2) — định dạng ở trên là file
bên trong.

## 6. Quy tắc định dạng file trong title

- **Ghi** cho hệ đĩa / digital, nơi định dạng là query riêng và thường đi cùng tên emulator đang
  trend: Wii (RVZ, WiiWare WAD), GameCube (RVZ + Dolphin), Wii U (WUX + Cemu), 3DS (Decrypted, CIA),
  Xbox (XISO + Xemu), Xbox 360 (ISO, XBLA), PS2/PS1/PSP (ISO), PS3 (ISO, PKG), PS4 (PKG),
  PS Vita (VPK, NoNpDrm), Sega CD (CHD), Windows (GOG, MagiPack).
- **Không ghi** cho hệ cartridge (NES, SNES, GB, GBC, GBA, N64, NDS, Genesis): đuôi file trùng tên viết
  tắt hệ (`.gba`, `.nds`…), người chơi tìm "gba roms" chứ không tìm theo đuôi.
- **Không ghi** khi chưa đo được (Saturn, Dreamcast). Đo lại bằng `download_list_0_file_type` trước khi
  thêm định dạng mới; bản đầu của Wii (`WBFS`) và 3DS (`CIA`) từng sai đúng ở điểm này.

## 7. Tên & slug console (chuẩn hoá 2026-10-19)

Trạng thái trước khi đổi: `wp-docker/romsvault_prod-20261019-1927.sql.gz` (13 term ở bảng dưới +
`nintendo-satellaview` term_id 21585 và 6 termmeta của nó).

**Quy ước tên** (hiện ở chip card, breadcrumb, dropdown filter, h1 `{tên} ROMs`, `%%ct_console%%`, nút
"Download {tên} Emulator"): tên ngắn đúng như người chơi gọi, không ngoặc, đúng hoa/thường thương hiệu.
`emulator_console` dùng **cùng tên** với `console`.

| Console slug | Tên cũ | Tên mới | emulator_console |
|---|---|---|---|
| `playstation` | PlayStation (PS) | PS1 | `playstation-ps` → `playstation`, "PS1" |
| `game-boy` | Game Boy (GB) | Game Boy | "Game Boy" |
| `xbox` | XBOX | Xbox | "Xbox" |
| `nintendo-wii` | Nintendo Wii | Wii | "Wii" (sẵn) |
| `super-nintendo` | SNES | — | `snes` → `super-nintendo` |
| `dreamcast` | Sega Dreamcast | — | `sega-dreamcast` → `dreamcast` |
| `switch` | Nintendo Switch | — | `nintendo-switch` → `switch` |
| `wii-u` | Wii U | — | "WiiU" → "Wii U" |
| `playstation-4` | PS4 | — | "PlayStation 4" → "PS4" |

Đã xoá: `nintendo-satellaview` (0 rom). Giữ bắt buộc: `unknown` — `default_term` của taxonomy console
(`setting/setting-rom.php`), rom nào cũng phải có console vì console nằm trong permalink.

**Slug console không đổi**: nó nằm trong URL mọi rom (`/roms/{console}/{rom}/`), WP không tự redirect
khi đổi slug term. Lệch kiểu (`playstation-2` vs `ps3`) không ảnh hưởng SEO.

**Bất biến — slug `emulator_console` phải bằng slug `console`**: `single-rom.php` (nút emulator + khối
Emulators) tìm emulator bằng `get_term_by( 'slug', $console->slug, 'emulator_console' )`. Trước khi
chuẩn hoá, PS1 (5.243 rom) và SNES (2.505 rom) mất nút emulator vì lệch slug. Thêm console/emulator
console mới ⇒ đặt cùng slug. Đổi slug `emulator_console` an toàn: rewrite emulator bỏ qua đoạn console
(`setting/setting-emulator.php`), URL cũ vẫn 200 với canonical sang URL mới.

Sau khi đổi tên term bằng code/SQL: xoá transient `console_terms`, `{tax}_terms_of_*`
(`caching/caching-rom.php`, Redis db 3, TTL 1 ngày) — dropdown filter đọc tên từ đó.

**h1 archive** (`template-parts/rom-filter.php`): `{tên} ROMs`, bỏ hậu tố nếu tên đã có "rom";
riêng `windows` → `Windows PC Games` (game PC không phải ROM).
