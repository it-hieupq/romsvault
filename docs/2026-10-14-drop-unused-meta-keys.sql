-- Drop meta keys the code no longer reads.
--
-- Audited 2026-10-14 against the whole install: every key below was grepped for in the
-- theme, in every plugin and mu-plugin, and across wp-includes/wp-admin and the
-- other themes. None of them is read or written anywhere; they are leftovers from
-- the previous theme, from removed features and from one-off migrations.
--
-- The ACF value row and its '_' reference row are dropped together: without the
-- reference row get_field() cannot resolve the value anyway.
--
-- Take a dump first, this is not reversible:
--   docker exec wp_mariadb mariadb-dump -uroot -proot romsvault \
--     wphh_postmeta wphh_termmeta > meta-backup.sql
--
-- Run the dry run block first to see what each DELETE will touch.

-- ------------------------------------------------------------- dry run
SELECT meta_key, COUNT(*) AS rows_now FROM wphh_postmeta
 WHERE meta_key IN (
    'REGION',
    '_REGION',
    '_alt_titles',
    '_game-genre',
    '_game_region',
    '_hide_ads',
    '_images_tab_gallary',
    '_post_views',
    '_publisher',
    '_region-test',
    '_related_posts',
    '_release_date',
    'alt_titles',
    'android_info',
    'game-genre',
    'game_region',
    'hide_ads',
    'post_views',
    'publisher',
    'region-test',
    'related_posts',
    'release_date',
    'soria_views_count',
    'su_slide_link',
    'pack_image',
    '_pack_image',
    '_grabber_source_url',
    '_grabber_source_igdb',
    '_grabber_source_launchbox',
    '_grabber_source_hackdex',
    '_grabber_source_itch',
    '_grabber_source_romstation',
    'game_id',
    '_game_id',
    '_mame_shortname',
    '_mame_version',
    '_mame_emulator',
    '_mame_playable',
    '_rf_mame_backup',
    '_rfmm_status_backup',
    '_rfmm_gallery_backup',
    '_rfmm_art_key',
    '_rfmm_art_stub',
    '_ils_backup',
    '_rbl_backup',
    '_wpdiscuz_statistics'
 ) GROUP BY meta_key ORDER BY rows_now DESC;
SELECT meta_key, COUNT(*) AS rows_now FROM wphh_termmeta
 WHERE meta_key IN ( 'libretro_system', '_libretro_system' ) GROUP BY meta_key;

-- --------------------------------------------- 1. removed pack feature
-- pack_image: the pack feature is gone and the grabber now writes the
-- screenshots gallery under its real key, "image". Zero rows at audit time;
-- kept here in case an older run left some behind.
--   pack_image                   0 rows
--   _pack_image                  0 rows
DELETE FROM wphh_postmeta WHERE meta_key IN (
    'pack_image',
    '_pack_image'
);
-- expected: 0 rows

-- ----------------------------- 2. dead fields from the previous theme
-- Superseded by the console/region/genre/pub taxonomies and by the ACF
-- group in setting/setting-rom.php.
--   REGION                       13398 rows
--   _REGION                      13388 rows
--   _alt_titles                  256 rows
--   _game-genre                  13198 rows
--   _game_region                 13198 rows
--   _hide_ads                    29984 rows
--   _images_tab_gallary          11909 rows
--   _post_views                  4 rows
--   _publisher                   12121 rows
--   _region-test                 7 rows
--   _related_posts               31461 rows
--   _release_date                11107 rows
--   alt_titles                   256 rows
--   android_info                 11909 rows
--   game-genre                   13198 rows
--   game_region                  13198 rows
--   hide_ads                     29984 rows
--   post_views                   4 rows
--   publisher                    12106 rows
--   region-test                  7 rows
--   related_posts                31461 rows
--   release_date                 11107 rows
--   soria_views_count            16 rows
--   su_slide_link                3 rows
DELETE FROM wphh_postmeta WHERE meta_key IN (
    'REGION',
    '_REGION',
    '_alt_titles',
    '_game-genre',
    '_game_region',
    '_hide_ads',
    '_images_tab_gallary',
    '_post_views',
    '_publisher',
    '_region-test',
    '_related_posts',
    '_release_date',
    'alt_titles',
    'android_info',
    'game-genre',
    'game_region',
    'hide_ads',
    'post_views',
    'publisher',
    'region-test',
    'related_posts',
    'release_date',
    'soria_views_count',
    'su_slide_link'
);
-- expected: 273280 rows

-- ------------------------------- 3. termmeta: unused taxonomy field
-- libretro_system: one term carries it, nothing reads it.
DELETE FROM wphh_termmeta WHERE meta_key IN ( 'libretro_system', '_libretro_system' );
-- expected: 2 rows

-- --------------------------------------- 4. REVIEW BEFORE RUNNING
-- No code reads these either, but unlike block 2 they still hold information:
-- where a post was grabbed from, its MAME details, and the backups taken by
-- earlier migrations. Dropping them loses that provenance for good. Run this
-- block only after deciding you do not want it.
-- Provenance and migration backups.
--   _grabber_source_url          25570 rows
--   _grabber_source_igdb         5772 rows
--   _grabber_source_launchbox    10509 rows
--   _grabber_source_hackdex      3 rows
--   _grabber_source_itch         2 rows
--   _grabber_source_romstation   3 rows
--   game_id                      11373 rows
--   _game_id                     11373 rows
--   _mame_shortname              3487 rows
--   _mame_version                3487 rows
--   _mame_emulator               3396 rows
--   _mame_playable               3396 rows
--   _rf_mame_backup              3399 rows
--   _rfmm_status_backup          2444 rows
--   _rfmm_gallery_backup         2046 rows
--   _rfmm_art_key                2044 rows
--   _rfmm_art_stub               1 rows
--   _ils_backup                  2283 rows
--   _rbl_backup                  1994 rows
--   _wpdiscuz_statistics         606 rows
DELETE FROM wphh_postmeta WHERE meta_key IN (
    '_grabber_source_url',
    '_grabber_source_igdb',
    '_grabber_source_launchbox',
    '_grabber_source_hackdex',
    '_grabber_source_itch',
    '_grabber_source_romstation',
    'game_id',
    '_game_id',
    '_mame_shortname',
    '_mame_version',
    '_mame_emulator',
    '_mame_playable',
    '_rf_mame_backup',
    '_rfmm_status_backup',
    '_rfmm_gallery_backup',
    '_rfmm_art_key',
    '_rfmm_art_stub',
    '_ils_backup',
    '_rbl_backup',
    '_wpdiscuz_statistics'
);
-- expected: 93188 rows
