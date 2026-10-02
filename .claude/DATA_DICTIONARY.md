# DATA_STRUCTURE.md - Romsfun Theme Data Architecture

> Auto-generated from `/inc/setting-*.php` files

---

## Custom Post Types (CPT)

### `rom`
| Property | Value |
|----------|-------|
| Public | `true` |
| Supports | title, editor, thumbnail |
| REST API | `true` |
| Rewrite | Custom (roms/{console}/{slug}.html) |

**Taxonomies:** console, collection, region, genre, dev, pub, type

---

### `emulator`
| Property | Value |
|----------|-------|
| Public | `true` |
| Supports | title, editor, thumbnail, comments |
| REST API | `true` |
| Rewrite | `emulators/{slug}.html` |

**Taxonomies:** emulator_console, os

---

### `library`
| Property | Value |
|----------|-------|
| Public | `true` |
| Supports | title, editor, thumbnail, custom-fields, comments |
| REST API | `true` |
| Rewrite | `libraries/{lib_console}/{slug}.html` |

**Taxonomies:** lib_console, lib_type

---

### `pack`
| Property | Value |
|----------|-------|
| Public | `true` |
| Supports | title, editor, custom-fields, thumbnail, comments |
| REST API | `true` |
| Rewrite | `packs/{pack_console}/{slug}/` |

**Taxonomies:** pack_console

---

### `ac` (Activation Code)
| Property | Value |
|----------|-------|
| Public | `false` |
| Show UI | `true` |
| Supports | none |

---

## Taxonomies

### For `rom` Post Type

| Taxonomy | Slug | Hierarchical | Rewrite |
|----------|------|--------------|---------|
| Console | `console` | Yes | `roms/{slug}` |
| Collection | `collection` | Yes | `collection/{slug}` |
| Region | `region` | Yes | default |
| Genre | `genre` | Yes | default |
| Developer | `dev` | Yes | default |
| Publisher | `pub` | Yes | default |
| Type | `type` | Yes | `/{slug}` (no prefix) |

### For `emulator` Post Type

| Taxonomy | Slug | Hierarchical | Rewrite |
|----------|------|--------------|---------|
| Console | `emulator_console` | Yes | `emulators/{slug}` |
| OS | `os` | Yes | default |

### For `library` Post Type

| Taxonomy | Slug | Hierarchical | Rewrite |
|----------|------|--------------|---------|
| Console | `lib_console` | Yes | `libraries/{slug}` |
| Type | `lib_type` | Yes | default |

### For `pack` Post Type

| Taxonomy | Slug | Hierarchical | Rewrite |
|----------|------|--------------|---------|
| Console | `pack_console` | Yes | `packs/{slug}` |

---

## ACF Field Groups

### `rom_settings`
**Location:** CPT `rom`, CPT `emulator`

| Field Key | Field Name | Type | Default | Required | Notes |
|-----------|------------|------|---------|----------|-------|
| `hide` | hide | select | `public` | No | Options: public, download, all |
| `file_size` | file_size | text | - | No | |
| `views` | views | number | `0` | No | |
| `downloads` | downloads | number | `0` | No | |
| `rating_count` | rating_count | number | `1` | No | |
| `rating_avg` | rating_avg | number | `5` | No | |
| `hide_ads` | hide_ads | true_false | `false` | No | |
| `released` | released | textarea | - | No | Release date info |
| `related_posts` | related_posts | post_object | - | No | Multiple, returns ID |
| `internal_note` | internal_note | textarea | - | No | |
| `image` | image | gallery | - | No | Screenshots |
| `grabber_gallery` | grabber_gallery | group | - | No | See sub-fields below |
| `grabber_file` | grabber_file | group | - | No | See sub-fields below |
| `download_note` | download_note | textarea | - | No | |
| `download_list` | download_list | repeater | - | No | See sub-fields below |
| `download_list_aff` | download_list_aff | repeater | - | No | Affiliate links |

**Sub-fields for `grabber_gallery`:**
| Field Key | Field Name | Type |
|-----------|------------|------|
| `grabber_input` | grabber_input | text |
| `grabber_button` | grabber_button | text (readonly) |

**Sub-fields for `grabber_file`:**
| Field Key | Field Name | Type |
|-----------|------------|------|
| `grb_file_name` | grb_file_name | text |
| `grb_file_url` | grb_file_url | text |
| `grabber_file_button` | grabber_file_button | text (readonly) |

**Sub-fields for `download_list` / `download_list_aff`:**
| Field Key | Field Name | Type | Required |
|-----------|------------|------|----------|
| `name` | name | text | Yes |
| `url` | url | url | Yes |
| `file_size` | file_size | text | No |
| `file_type` | file_type | text | No |

---

### `console_settings`
**Location:** Taxonomy `console`

| Field Key | Field Name | Type | Default | Notes |
|-----------|------------|------|---------|-------|
| `cat_image` | cat_image | image | - | Returns URL |
| `console_original_slug` | console_original_slug | text | - | For custom URL rewrite |
| `console_rom_downloads` | console_rom_downloads | number | `1` | |
| `console_guide` | console_guide | wysiwyg | - | |

---

### `region_settings`
**Location:** Taxonomy `region`

| Field Key | Field Name | Type |
|-----------|------------|------|
| `region_image` | region_image | image |

---

### `collection_settings`
**Location:** Taxonomy `collection`

| Field Key | Field Name | Type | Notes |
|-----------|------------|------|-------|
| `collection_banner` | collection_banner | image | Returns array |

---

### `emulator_console_settings`
**Location:** Taxonomy `emulator_console`

| Field Key | Field Name | Type | Default | Notes |
|-----------|------------|------|---------|-------|
| `emulator_console_image` | emulator_console_image | image | - | Returns URL |
| `emulator_console_downloads` | emulator_console_downloads | number | `0` | |

---

### `lib_settings`
**Location:** CPT `library`

| Field Key | Field Name | Type | Default | Required | Notes |
|-----------|------------|------|---------|----------|-------|
| `hide` | hide | select | `public` | No | Options: public, download, all |
| `views` | views | number | `0` | No | |
| `lib_size` | lib_size | text | - | Yes | |
| `rating_count` | rating_count | number | `1` | No | |
| `rating_avg` | rating_avg | number | `5` | No | |
| `download_note` | download_note | textarea | - | No | |
| `lib_screenshot` | lib_screenshot | gallery | - | No | Returns array |
| `download_list` | download_list | repeater | - | No | |

---

### `group_pack_settings`
**Location:** CPT `pack`

| Field Key | Field Name | Type | Default | Required | Notes |
|-----------|------------|------|---------|----------|-------|
| `hide` | hide | select | `public` | No | Options: public, download, all |
| `downloads` | downloads | number | `0` | No | |
| `views` | views | number | `0` | No | |
| `field_pack_size` | pack_size | text | - | Yes | |
| `field_pack_number_of_files` | pack_number_of_files | text | - | Yes | |
| `rating_count` | rating_count | number | `1` | No | |
| `rating_avg` | rating_avg | number | `5` | No | |
| `field_pack_request` | pack_request | wysiwyg | - | No | |
| `field_pack_guide` | pack_guide | wysiwyg | - | No | |
| `field_pack_note` | pack_note | wysiwyg | - | No | |
| `pack_image` | image | gallery | - | No | Screenshots |
| `download_list` | download_list | repeater | - | No | |

---

### `ac_settings`
**Location:** CPT `ac` (Activation Code)

| Field Key | Field Name | Type | Notes |
|-----------|------------|------|-------|
| `ac_code` | ac_code | text | |
| `ac_max` | ac_max | number | Max activations |
| `ac_users` | ac_users | user | Multiple users |

---

### `post_settings`
**Location:** CPT `post` (WordPress default)

| Field Key | Field Name | Type | Default |
|-----------|------------|------|---------|
| `views` | views | number | `0` |

---

### `home_settings`
**Location:** Page templates `page-home.php`, `page-home-2.php`

| Field Key | Field Name | Type | Notes |
|-----------|------------|------|-------|
| `home_content` | home_content | flexible_content | See layouts below |

**Flexible Content Layouts:**

#### Layout: `home_console_groups`
| Field Key | Field Name | Type | Required |
|-----------|------------|------|----------|
| `home_console_groups_title` | home_console_groups_title | text | Yes |

#### Layout: `home_intro`
| Field Key | Field Name | Type | Required |
|-----------|------------|------|----------|
| `home_intro_title` | home_intro_title | text | Yes |
| `home_intro_desc` | home_intro_desc | wysiwyg | Yes |

#### Layout: `home_roms_collection`
| Field Key | Field Name | Type | Required | Notes |
|-----------|------------|------|----------|-------|
| `home_roms_collection_title` | home_roms_collection_title | text | Yes | |
| `home_roms_collection_items` | home_roms_collection_items | taxonomy | Yes | Multi-select, collection |
| `home_roms_collection_link` | home_roms_collection_link | link | Yes | |

#### Layout: `home_roms`
| Field Key | Field Name | Type | Default | Required |
|-----------|------------|------|---------|----------|
| `home_roms_title` | home_roms_title | text | - | Yes |
| `home_roms_number` | home_roms_number | number | `8` | Yes |
| `home_roms_sort` | home_roms_sort | select | - | Yes | Options: latest, popular |
| `home_roms_layout` | home_roms_layout | select | - | Yes | Options: carousel, list |

#### Layout: `home_new_posts`
| Field Key | Field Name | Type | Default | Required |
|-----------|------------|------|---------|----------|
| `home_new_posts_title` | home_new_posts_title | text | - | Yes |
| `home_new_posts_number` | home_new_posts_number | number | `10` | Yes |
| `home_new_posts_link` | home_new_posts_link | link | - | Yes |

---

### `console_group_fields`
**Location:** Options page `console-group`

| Field Key | Field Name | Type | Notes |
|-----------|------------|------|-------|
| `console_groups` | title | repeater | See sub-fields below |

**Sub-fields for `console_groups`:**
| Field Key | Field Name | Type | Required | Notes |
|-----------|------------|------|----------|-------|
| `console_groups_title` | title | text | Yes | |
| `console_groups_consoles` | consoles | taxonomy | Yes | Multi-select, console taxonomy |

---

## Common Field Patterns

### Hide Field (Visibility Control)
Used in: `rom`, `library`, `pack`

```php
'choices' => [
    'public'   => 'Public',        // Fully visible
    'download' => 'Hide Download PC', // Hide download on PC
    'all'      => '404 PC',        // Show 404 on PC
]
```

### Rating Fields
Used in: `rom`, `library`, `pack`

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `rating_count` | number | `1` | Number of ratings |
| `rating_avg` | number | `5` | Average rating (1-5) |

### Download List Repeater
Used in: `rom`, `library`, `pack`

Standard structure for file downloads with name, URL, size, and type.

---

## URL Structure

| Content Type | URL Pattern |
|--------------|-------------|
| Rom Single | `/roms/{console}/{slug}.html` |
| Console Archive | `/roms/{console}/` |
| Emulator Single | `/emulators/{slug}.html` |
| Emulator Console | `/emulators/{console}/` |
| Library Single | `/libraries/{lib_console}/{slug}.html` |
| Library Console | `/libraries/{lib_console}/` |
| Pack Single | `/packs/{pack_console}/{slug}/` |
| Pack Console | `/packs/{pack_console}/` |
| Type Archive | `/{type_slug}/` |
| Collection | `/collection/{slug}/` |

---

## Notes

- All field keys use underscores (snake_case)
- Taxonomy fields typically return IDs for performance
- Image fields return URL or array depending on use case
- The `hide` field controls PC-specific visibility
- `console_original_slug` enables custom URL slugs different from term slug

---

## AJAX Endpoints (Frontend to Backend Actions)

Defined in `/inc/ajax.php`:

| Action | Function | Auth Required | Nonce | Rate Limit / Prevention | Description |
|--------|--------|---------------|-------|-------------------------|-------------|
| `k_comment` | `k_comment` | No | `k_comment_action` | IP Rate limit (5 per 180s) | Submits a comment. Validates input lengths (1000/50), checks spam words, validates parent hierarchy (max 2 levels). Inserts as moderation hold (`comment_approved = 0`). |
| `k_rating` | `k_rating` | No | `k_rating_action` | Cookie (1 year expiration) | Handles 1-5 star rating. Validates rating (0.5 to 5.0). Updates `rating_count` and `rating_avg` ACF fields. |
| `k_update_post_views`| `k_update_post_views` | No | None | None | Increments the `views` ACF field for a given post. |
| `k_get_download` | `k_get_download` | No | None | Token generation | Extracts URL and file index, verifies post and download type, applies affiliate logic, and returns HTML for download button alongside a tokenized link. |
| `handle_report_form` | `handle_report_form` | No | `report_form_action` | Cookie (5 mins expiration) | Handles report submission. Verifies domain matching and input sizes (500 chars). Inserts directly to custom WP DB table `$wpdb->prefix . 'user_report'`. |
| `get_download_token`| `handle_get_download_token` | No | None | None | Returns string token. |
| `get_nonce` | No | None | None | Returns fresh nonce based on action type (`comment`, `rating`, `report`). |

**AJAX Validation & Security Standards:**
- Sets secure headers using `AppSecurity::setSecurityHeaders()`.
- Centralized sanitization: `AppSecurity::validate_input()`, `clean_text_input()`, `validate_number()`, `esc_url_raw()`.
- Nonce protection on specific state-mutating requests (`comment`, `rating`, `report`).
- Immediate failure handling ("Early Returns") via `wp_send_json_error()` returning a descriptive `message`.
