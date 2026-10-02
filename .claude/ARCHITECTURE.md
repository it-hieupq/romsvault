# RomsFun Theme Architecture

**Version:** 2.0  
**Platform:** WordPress / PHP  
**Generated:** 2026-01-29

---

## Overview

This document maps the **existing** post types, taxonomies, URL structures, and template files in the RomsFun WordPress theme. All information is derived from actual code analysis.

---

## Post Types & Their Structures

### 1. ROM (`rom`)

**Registered in:** `/inc/setting-rom.php`

#### Post Type Configuration
- **Slug:** `rom`
- **Has Archive:** `false`
- **Rewrite:** `false` (custom rewrite rules applied)
- **Supports:** `title`, `editor`, `thumbnail`
- **Show in REST:** `true`

#### Associated Taxonomies
1. **`console`** (Hierarchical)
   - **Rewrite Slug:** `roms`
   - **Hierarchical:** `false`
   - **Template:** `taxonomy-console.php`

2. **`collection`** (Hierarchical)
   - **Rewrite Slug:** `collection`
   - **Hierarchical:** `false`
   - **Template:** `taxonomy-collection.php`

3. **`region`** (Hierarchical)
   - **Rewrite Slug:** Default (no custom slug)
   - **Hierarchical:** `false`

4. **`genre`** (Hierarchical)
   - **Rewrite Slug:** Default (no custom slug)
   - **Hierarchical:** `false`

5. **`dev`** (Developer - Hierarchical)
   - **Rewrite Slug:** Default (no custom slug)
   - **Hierarchical:** `false`

6. **`pub`** (Publisher - Hierarchical)
   - **Rewrite Slug:** Default (no custom slug)
   - **Hierarchical:** `false`

7. **`type`** (Hierarchical)
   - **Rewrite Slug:** Custom (no prefix - direct slug)
   - **Hierarchical:** `false`
   - **Template:** `taxonomy-type.php`
   - **Special:** 301 redirect from `/type/{slug}/` to `/{slug}/`

#### URL Structure
- **Single ROM:** `/roms/{console_slug}/{rom-name}.html`
  - Uses `console_original_slug` ACF field if available, otherwise falls back to console slug
- **Console Archive:** `/roms/{console_slug}/`
- **Type Archive:** `/{type_slug}/` (no prefix)
- **Template Files:**
  - Single: `single-rom.php`
  - Console Taxonomy: `taxonomy-console.php`
  - Collection Taxonomy: `taxonomy-collection.php`
  - Type Taxonomy: `taxonomy-type.php`

---

### 2. Emulator (`emulator`)

**Registered in:** `/inc/setting-emulator.php`

#### Post Type Configuration
- **Slug:** `emulator`
- **Has Archive:** `false`
- **Rewrite:** `emulators/%console%`
- **Supports:** `title`, `editor`, `thumbnail`, `comments`
- **Show in REST:** `true`

#### Associated Taxonomies
1. **`emulator_console`** (Hierarchical)
   - **Rewrite Slug:** `emulator-console`
   - **Hierarchical:** `true`
   - **Template:** `taxonomy-emulator_console.php`

2. **`os`** (Operating System - Hierarchical)
   - **Rewrite Slug:** Default
   - **Hierarchical:** `true`

#### URL Structure
- **Single Emulator:** `/emulators/{emulator-name}.html`
- **Console Archive:** `/emulators/{console_slug}/`
- **Template Files:**
  - Single: `single-emulator.php`
  - Console Taxonomy: `taxonomy-emulator_console.php`

---

### 3. Pack (`pack`)

**Registered in:** `/inc/setting-pack.php`

#### Post Type Configuration
- **Slug:** `pack`
- **Has Archive:** `false`
- **Rewrite:** `packs/%pack_console%`
- **Supports:** `title`, `editor`, `custom-fields`, `thumbnail`, `comments`
- **Show in REST:** `true`

#### Associated Taxonomies
1. **`pack_console`** (Hierarchical)
   - **Rewrite Slug:** `pack-console`
   - **Hierarchical:** `true`
   - **Template:** `taxonomy-pack_console.php`

#### URL Structure
- **Single Pack:** `/packs/{console_slug}/{pack-name}/`
- **Console Archive:** `/packs/{console_slug}/`
- **Template Files:**
  - Single: `single-pack.php`
  - Console Taxonomy: `taxonomy-pack_console.php`

---

### 4. Library (`library`)

**Registered in:** `/inc/setting-lib.php`

#### Post Type Configuration
- **Slug:** `library`
- **Has Archive:** Not explicitly set
- **Rewrite:** Default
- **Supports:** `title`, `editor`, `thumbnail`, `custom-fields`, `comments`
- **Show in REST:** `true`

#### Associated Taxonomies
1. **`lib_console`** (Hierarchical)
   - **Rewrite Slug:** `libraries`
   - **Hierarchical:** `true`
   - **Template:** `taxonomy-lib_console.php`

2. **`lib_type`** (Hierarchical)
   - **Rewrite Slug:** Default
   - **Hierarchical:** `true`

#### URL Structure
- **Single Library:** `/libraries/{console_slug}/{library-name}.html`
- **Console Archive:** `/libraries/{console_slug}/`
- **Template Files:**
  - Single: `single-library.php`
  - Console Taxonomy: `taxonomy-lib_console.php`

---

### 5. Post (`post`)

**Registered in:** `/inc/setting-post.php`

#### Post Type Configuration
- **Default WordPress Post Type**
- Custom ACF fields added for views tracking

#### URL Structure
- **Default WordPress structure**
- **Template Files:**
  - Single: `single.php`
  - Archive: `archive.php`

---

## Template Hierarchy

### Single Post Templates
| Post Type | Template File | Location |
|-----------|---------------|----------|
| `rom` | `single-rom.php` | Root |
| `emulator` | `single-emulator.php` | Root |
| `pack` | `single-pack.php` | Root |
| `library` | `single-library.php` | Root |
| `post` | `single.php` | Root |

### Taxonomy Templates
| Taxonomy | Template File | Location |
|----------|---------------|----------|
| `console` | `taxonomy-console.php` | Root |
| `collection` | `taxonomy-collection.php` | Root |
| `type` | `taxonomy-type.php` | Root |
| `emulator_console` | `taxonomy-emulator_console.php` | Root |
| `pack_console` | `taxonomy-pack_console.php` | Root |
| `lib_console` | `taxonomy-lib_console.php` | Root |

### Archive Templates
| Type | Template File | Location |
|------|---------------|----------|
| Default | `archive.php` | Root |

### Page Templates
| Template | File | Location |
|----------|------|----------|
| Home | `page-home.php` | Root |
| Collections | `page-collections.php` | Root |
| Emulators | `page-emulators.php` | Root |
| Libraries | `page-libraries.php` | Root |
| Packs | `page-packs.php` | Root |
| ROMs | `page-roms.php` | Root |
| Login | `page-login.php` | Root |
| Signup | `page-signup.php` | Root |

### Special Templates
| Template | File | Location | Purpose |
|----------|------|----------|---------|
| Browse ROMs | `browse-roms.php` | Root | ROM browsing interface |
| Download | `download.php` | Root | Download handling |
| Search | `search.php` | Root | Search results |
| 404 | `404.php` | Root | Not found page |

---

## Custom Rewrite Rules

### ROM Post Type
```php
// Single ROM URL pattern
/roms/{console_original_slug}/{rom-name}.html
// Maps to: index.php?post_type=rom&name={rom-name}

// Type taxonomy (no prefix)
/{type_slug}/
// Maps to: index.php?type={type_slug}

// 301 Redirect from old type URLs
/type/{type_slug}/ → /{type_slug}/
```

### Emulator Post Type
```php
// Single Emulator URL pattern
/emulators/{emulator-name}.html
// Maps to: index.php?post_type=emulator&name={emulator-name}

// Console archive
/emulators/{console_slug}/
// Maps to: index.php?emulator_console={console_slug}
```

### Pack Post Type
```php
// Single Pack URL pattern
/packs/{console_slug}/{pack-name}/
// Maps to: index.php?post_type=pack&name={pack-name}

// Console archive
/packs/{console_slug}/
// Maps to: index.php?pack_console={console_slug}
```

### Library Post Type
```php
// Single Library URL pattern
/libraries/{console_slug}/{library-name}.html
// Maps to: index.php?post_type=library&name={library-name}
```

---

## Key Features

### 1. Console Original Slug System
- ROMs use `console_original_slug` ACF field for custom console slugs in URLs
- Falls back to default taxonomy slug if not set
- Allows for SEO-friendly URL customization

### 2. Hide Status System
- Applied to: `rom`, `pack`, `library`
- Three states:
  - `public` - Fully visible
  - `download` - Hide Download on PC
  - `all` - 404 on PC
- Admin filter available for ROMs

### 3. AJAX Admin Features
- Quick edit download links
- Change post status
- Change hide status
- Edit text fields (Yoast SEO)

### 4. Download System
- Repeater fields for download lists
- Separate affiliate download lists
- File grabber functionality for ROMs

### 5. Rating System
- All post types have `rating_count` and `rating_avg` fields
- Default values: count=1, avg=5

### 6. AJAX Tracking System
- **File:** `inc/ajax-tracking.php`
- **Purpose:** Tracks Views and Downloads asynchronously to bypass caching (Cloudflare/Page Cache)
- **Supported Types:** `rom`, `emulator`, `pack`, `library`, `post`
- **Mechanism:**
  - **Views:** Updates `views` meta field
  - **Downloads:** 
    - Updates post `downloads` meta
    - Updates taxonomy aggregate counts (`console_rom_downloads`, `emulator_console_downloads`)

### 7. Security & Token System
- **File:** `inc/download-token.php`
- **Download Tokens:**
  - Generates time-sensitive tokens (300s duration)
  - Uses XOR encryption with User IP and Timestamp
  - Validated via `dl_token` cookie or query parameter
- **Cloudflare Integration:**
  - Detects real IP via `HTTP_CF_CONNECTING_IP`
  - Hooks into Cloudflare cache purge to clear download pages when updated

---

## Directory Structure

```
romsfun-v2/
├── inc/                          # Settings & configurations
│   ├── setting-rom.php          # ROM post type & taxonomies
│   ├── setting-emulator.php     # Emulator post type
│   ├── setting-pack.php         # Pack post type
│   ├── setting-lib.php          # Library post type
│   ├── setting-post.php         # Post enhancements
│   ├── ajax-tracking.php        # AJAX tracking handler
│   ├── download-token.php       # Security token generator
│   └── [378 other files]
├── template-parts/              # Reusable template components
├── assets/                      # CSS, JS, images
├── class/                       # PHP classes
├── js/                          # JavaScript files
├── single-*.php                 # Single post templates
├── taxonomy-*.php               # Taxonomy archive templates
├── page-*.php                   # Page templates
└── [other theme files]
```

---

## Notes

1. **No Archive Templates:** Most custom post types have `has_archive` set to `false` and use custom taxonomy archives instead.

2. **Rewrite Rule Flushing:** The theme automatically flushes rewrite rules on:
   - Theme switch (`after_switch_theme`)
   - Pack console edit (`edited_pack_console`)

3. **Preview Links:** Custom preview link handling for ROM post type to ensure proper preview URLs.

4. **Hierarchical Taxonomies:** Most taxonomies are hierarchical but use `hierarchical => false` in rewrite rules for flat URL structure.

5. **REST API:** All post types and major taxonomies are exposed to the REST API (`show_in_rest => true`).
