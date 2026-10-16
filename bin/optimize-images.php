<?php
/**
 * Plan the WebP backfill over the media library.
 *
 * Run from the host:
 *
 *   docker exec wp_phpfpm php \
 *     /var/www/sites/romsvault/wp-content/themes/romsvault/bin/optimize-images.php \
 *     --dry-run --limit=20 --measure
 *
 * This build only plans. --dry-run is mandatory and nothing is written: no file
 * lands in uploads and no row changes. The write half arrives with its own
 * approval; keeping it out means a mistake here costs a re-read, not a restore.
 *
 * What the plan decides, per slot
 * -------------------------------
 * A slot is one place that points at an attachment: a post's featured image, or
 * position N of its ACF gallery. Most attachments fill exactly one slot and keep
 * their id while their file moves. An attachment filling several needs a copy
 * per slot, so every slot after the first becomes a new attachment and its
 * reference gets rewritten.
 *
 * Which slot keeps the original id is settled by a total order — the slot whose
 * post is already the attachment's post_parent, then featured before gallery,
 * then gallery index, then post id — so two runs produce the same plan and the
 * second is a no-op.
 *
 * The eight console logos in KWO_EXEMPT are left shared. They are placeholders
 * for games with no box art, so copying one of them into sixty files named after
 * sixty different games would leave every one of those names describing
 * something the file is not.
 *
 * @package romsvault
 */

if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit( 1 );
}

// Holding the attachment index and the slot map at once costs a few hundred MB
// across 82k attachments. Cheaper than paging it back out of the database.
ini_set( 'memory_limit', '1024M' );

require_once __DIR__ . '/../../../../wp-load.php';

if ( ! function_exists( 'k_compress_image_to_webp' ) ) {
	require_once __DIR__ . '/../inc/img-compressor.php';
}

/**
 * Console logo placeholders, shared on purpose.
 *
 * Verified against the library on 2026-10-15; the comment is the file each one
 * points at and how many posts use it as their featured image.
 */
const KWO_EXEMPT = array(
	228341, // 2025/05/ps1.png                      60 posts
	227995, // 2025/05/ps2-game.jpg                 58
	228577, // 2025/05/Nintendo-DS.jpg              22
	82393,  // 2021/04/Nintendo_3ds_logo.png        14
	231574, // 2021/04/xbox-360.webp                11
	233048, // 2025/06/xbox.png                     11
	229219, // 2025/05/gba.jpg                       5
	82394,  // 2021/04/...nintendo-gamecube-logo...  2
);

/** Post statuses worth planning. Trash and auto-draft are not. */
const KWO_STATUSES = array( 'publish', 'draft', 'pending', 'private', 'future' );

/** Term meta keys holding an attachment id, and the prefix their file gets. */
const KWO_TERM_KEYS = array(
	'cat_image'         => 'console-',
	'region_image'      => 'region-',
	'collection_banner' => 'collection-',
);

/** Option names holding an attachment id, and the file name each gets. */
const KWO_OPTION_KEYS = array(
	'options_logo'    => 'logo',
	'options_favicon' => 'favicon',
);

/* -------------------------------------------------------------------------
 * Arguments
 * ---------------------------------------------------------------------- */

/**
 * Parse --key=value and --flag out of argv.
 *
 * @param array $argv Raw argv.
 * @return array Map of option name to string or true.
 */
function kwo_parse_args( array $argv ): array {
	$out = array();

	foreach ( array_slice( $argv, 1 ) as $arg ) {
		if ( ! str_starts_with( $arg, '--' ) ) {
			continue;
		}
		$arg = substr( $arg, 2 );
		$eq  = strpos( $arg, '=' );

		if ( false === $eq ) {
			$out[ $arg ] = true;
			continue;
		}
		$out[ substr( $arg, 0, $eq ) ] = substr( $arg, $eq + 1 );
	}

	return $out;
}

/** Print usage. */
function kwo_usage(): void {
	echo <<<TXT
Plan or run the WebP backfill. Exactly one of --dry-run and --write is required.

  --dry-run              plan only; writes no file and changes no row
  --write                carry the plan out
  --pass=posts           posts | conflicts | terms      (default: posts)
  --post-type=rom,emulator,post                         (default: rom,emulator,post)
  --quality=80
  --max-width=1280       0 keeps the original width
  --dest=2026/10         destination folder             (default: current month)
  --workers=4 --worker=0 shard pass=posts by post id
  --limit=0              cap the posts planned, 0 = all
  --measure              dry-run only: encode in memory to report real savings
  --log=PATH             append a tab-separated line per row
  --show=20              how many example rows to print
  --help

The source file is never deleted. Re-running is a no-op: a slot whose
attachment already sits at the destination under the right name is skipped.

TXT;
}

/* -------------------------------------------------------------------------
 * Loading
 * ---------------------------------------------------------------------- */

/**
 * Index every attachment: where its file is, what it is, how big it is.
 *
 * _wp_attachment_metadata is unserialized and dropped immediately, keeping only
 * the fields the plan needs; holding 82k full metadata arrays is what would
 * actually run the process out of memory.
 *
 * @return array Map of attachment id to mime, parent, file, width, height, bytes, scaled.
 */
function kwo_load_attachments(): array {
	global $wpdb;

	$att = array();

	foreach ( $wpdb->get_results( "SELECT ID, post_mime_type, post_parent FROM {$wpdb->posts} WHERE post_type = 'attachment'" ) as $row ) {
		$att[ (int) $row->ID ] = array(
			'mime'   => (string) $row->post_mime_type,
			'parent' => (int) $row->post_parent,
			'file'   => '',
			'width'  => 0,
			'height' => 0,
			'bytes'  => 0,
			'scaled' => false,
		);
	}

	foreach ( $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file'" ) as $row ) {
		$id = (int) $row->post_id;
		if ( isset( $att[ $id ] ) ) {
			$att[ $id ]['file'] = (string) $row->meta_value;
		}
	}

	$offset = 0;
	while ( true ) {
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT post_id, meta_value FROM {$wpdb->postmeta}
			 WHERE meta_key = '_wp_attachment_metadata' ORDER BY post_id LIMIT 5000 OFFSET %d",
			$offset
		) );
		if ( ! $rows ) {
			break;
		}

		foreach ( $rows as $row ) {
			$id = (int) $row->post_id;
			if ( ! isset( $att[ $id ] ) ) {
				continue;
			}
			$meta = maybe_unserialize( $row->meta_value );
			if ( ! is_array( $meta ) ) {
				continue;
			}
			$att[ $id ]['width']  = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
			$att[ $id ]['height'] = isset( $meta['height'] ) ? (int) $meta['height'] : 0;
			$att[ $id ]['bytes']  = isset( $meta['filesize'] ) ? (int) $meta['filesize'] : 0;
			$att[ $id ]['scaled'] = ! empty( $meta['original_image'] );
		}

		unset( $rows );
		$offset += 5000;
	}

	return $att;
}

/**
 * Collect every slot pointing at an attachment, plus the posts that own them.
 *
 * The gallery is read straight out of postmeta rather than through get_field():
 * ACF would resolve each id into a full attachment array, which is 31k needless
 * round trips when all the plan wants is the order of the ids.
 *
 * @param array $post_types Post types to scan.
 * @return array{slots:array,posts:array}
 */
function kwo_load_slots( array $post_types ): array {
	global $wpdb;

	$type_ph   = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
	$status_ph = implode( ',', array_fill( 0, count( KWO_STATUSES ), '%s' ) );

	$posts = array();
	$rows  = $wpdb->get_results( $wpdb->prepare(
		"SELECT ID, post_name, post_title, post_type FROM {$wpdb->posts}
		 WHERE post_type IN ($type_ph) AND post_status IN ($status_ph)",
		array_merge( $post_types, KWO_STATUSES )
	) );

	foreach ( $rows as $row ) {
		$posts[ (int) $row->ID ] = array(
			'slug'  => (string) $row->post_name,
			'title' => (string) $row->post_title,
			'type'  => (string) $row->post_type,
		);
	}
	unset( $rows );

	$slots = array();

	// 32 posts carry the same `image` row twice, written by ACF at some point
	// and harmless to the site because get_post_meta( ..., true ) reads only the
	// first. Reading raw rows would see each gallery twice and invent conflicts,
	// so the first row per post wins here too — meta_id order, same as core.
	$seen = array();

	foreach ( $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' ORDER BY meta_id" ) as $row ) {
		$pid = (int) $row->post_id;
		$aid = (int) $row->meta_value;

		if ( $aid > 0 && isset( $posts[ $pid ] ) && ! isset( $seen[ 'f' . $pid ] ) ) {
			$seen[ 'f' . $pid ] = true;
			$slots[ $aid ][]    = array( 'post' => $pid, 'role' => 'featured', 'idx' => 0 );
		}
	}

	foreach ( $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'image' ORDER BY meta_id" ) as $row ) {
		$pid = (int) $row->post_id;
		if ( ! isset( $posts[ $pid ] ) || isset( $seen[ 'g' . $pid ] ) ) {
			continue;
		}
		$seen[ 'g' . $pid ] = true;

		$ids = maybe_unserialize( $row->meta_value );
		if ( ! is_array( $ids ) ) {
			continue;
		}

		$idx = 0;
		foreach ( array_values( $ids ) as $raw ) {
			++$idx;
			$aid = (int) $raw;
			if ( $aid > 0 ) {
				$slots[ $aid ][] = array( 'post' => $pid, 'role' => 'gallery', 'idx' => $idx );
			}
		}
	}

	return array( 'slots' => $slots, 'posts' => $posts );
}

/* -------------------------------------------------------------------------
 * Planning
 * ---------------------------------------------------------------------- */

/**
 * Total order over the slots of one attachment.
 *
 * Deterministic on purpose: the first slot is the one that keeps the attachment
 * id, so an unstable order would hand the id to a different post on a re-run and
 * the backfill would stop being idempotent.
 *
 * @param array $list   Slots of one attachment.
 * @param int   $parent The attachment's current post_parent.
 * @return array Sorted slots.
 */
function kwo_sort_slots( array $list, int $parent ): array {
	usort( $list, static function ( array $a, array $b ) use ( $parent ): int {
		$pa = ( $a['post'] === $parent ) ? 0 : 1;
		$pb = ( $b['post'] === $parent ) ? 0 : 1;
		if ( $pa !== $pb ) {
			return $pa <=> $pb;
		}

		$ra = ( 'featured' === $a['role'] ) ? 0 : 1;
		$rb = ( 'featured' === $b['role'] ) ? 0 : 1;
		if ( $ra !== $rb ) {
			return $ra <=> $rb;
		}

		if ( $a['idx'] !== $b['idx'] ) {
			return $a['idx'] <=> $b['idx'];
		}

		return $a['post'] <=> $b['post'];
	} );

	return $list;
}

/**
 * File name for one slot, without extension.
 *
 * @param string $slug Owning post's resolved slug.
 * @param string $role featured or gallery.
 * @param int    $idx  1-based gallery position.
 * @return string
 */
function kwo_slot_basename( string $slug, string $role, int $idx ): string {
	return 'featured' === $role ? $slug : $slug . '-screenshots-' . $idx;
}

/**
 * Decide the slug every post's files are named after, and make it unique.
 *
 * post_name is the first choice and is never disturbed: it is already unique and
 * it is what the URL says. Drafts do not have one — WordPress only fills it in
 * at publish — so they fall back to sanitize_title() of the title, which is the
 * same value WordPress would store at that moment, and the file name therefore
 * matches the URL the post will eventually get.
 *
 * Uniqueness is settled here rather than on the file name, so a disambiguated
 * post carries one suffix across its featured image and its whole gallery
 * instead of each file being patched on its own.
 *
 * @param array $posts      Post index from kwo_load_slots().
 * @param array $with_slots Post ids that actually own a slot.
 * @param array $reserved   Names already spoken for, e.g. the exempt logos.
 * @return array Map of post id to slug, source and whether it was deduped.
 */
function kwo_resolve_slugs( array $posts, array $with_slots, array $reserved ): array {
	$resolved = array();
	$taken    = array_fill_keys( $reserved, true );

	sort( $with_slots );

	// Real post_name first, so nothing derived can take a published post's name.
	foreach ( $with_slots as $pid ) {
		$slug = $posts[ $pid ]['slug'] ?? '';
		if ( '' === $slug ) {
			continue;
		}

		$deduped = isset( $taken[ $slug ] );
		if ( $deduped ) {
			// post_name is unique per post type, so a hit here is two types
			// sharing one slug, or a collision with a reserved logo name.
			$slug .= '-' . $pid;
		}

		$taken[ $slug ]   = true;
		$resolved[ $pid ] = array( 'slug' => $slug, 'source' => 'post_name', 'deduped' => $deduped );
	}

	foreach ( $with_slots as $pid ) {
		if ( '' !== ( $posts[ $pid ]['slug'] ?? '' ) ) {
			continue;
		}

		$title  = trim( $posts[ $pid ]['title'] ?? '' );
		$source = 'title';
		$slug   = '' !== $title ? k_webp_basename( $title ) : '';

		if ( '' === $slug ) {
			$slug   = ( $posts[ $pid ]['type'] ?? 'post' ) . '-' . $pid;
			$source = 'id';
		}

		$deduped = isset( $taken[ $slug ] );
		if ( $deduped ) {
			$slug .= '-' . $pid;
		}

		$taken[ $slug ]   = true;
		$resolved[ $pid ] = array( 'slug' => $slug, 'source' => $source, 'deduped' => $deduped );
	}

	return $resolved;
}

/**
 * Turn the slot map into one planned row per slot.
 *
 * @param array $att   Attachment index.
 * @param array $slots Slot map.
 * @param array $slugs Resolved slugs from kwo_resolve_slugs().
 * @param array $opts  dest, max_width.
 * @return array List of rows.
 */
function kwo_build_plan( array $att, array $slots, array $slugs, array $opts ): array {
	$basedir = wp_get_upload_dir()['basedir'];
	$plan    = array();

	foreach ( $slots as $aid => $list ) {
		$meta   = $att[ $aid ] ?? null;
		$exempt = in_array( $aid, KWO_EXEMPT, true );
		$list   = kwo_sort_slots( $list, (int) ( $meta['parent'] ?? 0 ) );

		foreach ( $list as $order => $slot ) {
			$row = array(
				'att'       => $aid,
				'post'      => $slot['post'],
				'role'      => $slot['role'],
				'idx'       => $slot['idx'],
				'order'     => $order,
				'exempt'    => $exempt,
				'conflict'  => ! $exempt && count( $list ) > 1,
				'src'       => $meta['file'] ?? '',
				'src_bytes' => $meta['bytes'] ?? 0,
				'src_width' => $meta['width'] ?? 0,
				'mime'      => $meta['mime'] ?? '',
				'dst'       => '',
				'action'    => '',
				'note'      => '',
			);

			// An exempt placeholder is planned once; every later slot keeps
			// pointing at it, so there is no work and no reference to rewrite.
			if ( $exempt && $order > 0 ) {
				$row['action'] = 'shared';
				$plan[]        = $row;
				continue;
			}

			if ( null === $meta || '' === $row['src'] ) {
				$row['action'] = 'skip-norow';
				$plan[]        = $row;
				continue;
			}

			if ( $exempt ) {
				$base = k_webp_basename( pathinfo( $row['src'], PATHINFO_FILENAME ) );
			} else {
				$slug = $slugs[ $slot['post'] ]['slug'] ?? '';

				// Guard the slug, not the composed name. An empty slug still
				// produces "-screenshots-3", which looks like a valid name and
				// would collide with every other slugless post's third
				// screenshot — which is exactly what the first dry run found.
				if ( '' === $slug ) {
					$row['action'] = 'skip-noslug';
					$plan[]        = $row;
					continue;
				}

				$base          = kwo_slot_basename( $slug, $slot['role'], $slot['idx'] );
				$row['slug']   = $slug;
				$row['source'] = $slugs[ $slot['post'] ]['source'] ?? '';
			}

			$row['dst'] = $opts['dest'] . '/' . $base . '.webp';

			if ( $row['dst'] === $row['src'] ) {
				$row['action'] = 'skip-done';
				$plan[]        = $row;
				continue;
			}

			if ( 'image/gif' === $row['mime'] ) {
				$row['action'] = 'skip-gif';
				$row['note']   = 'animation would be lost';
				$plan[]        = $row;
				continue;
			}

			if ( ! in_array( $row['mime'], K_WEBP_INPUT_MIMES, true ) ) {
				$row['action'] = 'skip-unsupported';
				$plan[]        = $row;
				continue;
			}

			if ( ! is_file( $basedir . '/' . $row['src'] ) ) {
				$row['action'] = 'skip-missing';
				$plan[]        = $row;
				continue;
			}

			$in_cap = ( null === $opts['max_width'] )
				|| ( $row['src_width'] > 0 && $row['src_width'] <= $opts['max_width'] );

			if ( 'image/webp' === $row['mime'] && $in_cap ) {
				$row['note'] = 'already webp inside the cap, bytes copied as-is';
			}

			$row['action'] = ( 0 === $order ) ? 'reencode' : 'clone';
			$plan[]        = $row;
		}
	}

	return $plan;
}

/**
 * Plan the images a per-post scan cannot reach: term meta, options, and the
 * uploads URLs typed straight into post_content.
 *
 * These get a prefixed name rather than the bare term slug. The console term
 * nintendo-ds would otherwise want nintendo-ds.webp, which is already the name
 * the exempt Nintendo DS placeholder takes.
 *
 * @param array $att  Attachment index.
 * @param array $opts dest, max_width.
 * @return array{rows:array,urls:array}
 */
function kwo_build_terms_plan( array $att, array $opts ): array {
	global $wpdb;

	$basedir = wp_get_upload_dir()['basedir'];
	$rows    = array();

	$keys_ph = implode( ',', array_fill( 0, count( KWO_TERM_KEYS ), '%s' ) );
	$found   = $wpdb->get_results( $wpdb->prepare(
		"SELECT term_id, meta_key, meta_value FROM {$wpdb->termmeta} WHERE meta_key IN ($keys_ph)",
		array_keys( KWO_TERM_KEYS )
	) );

	foreach ( $found as $row ) {
		$aid = (int) $row->meta_value;
		if ( $aid <= 0 || ! isset( $att[ $aid ] ) ) {
			continue;
		}

		$term = get_term( (int) $row->term_id );
		$slug = ( $term instanceof WP_Term ) ? $term->slug : 'term-' . (int) $row->term_id;
		$base = KWO_TERM_KEYS[ $row->meta_key ] . $slug;

		if ( 'collection_banner' === $row->meta_key ) {
			$base .= '-banner';
		}

		$rows[] = kwo_simple_row( $att, $aid, k_webp_basename( $base ), 'termmeta:' . $row->meta_key, $opts, $basedir );
	}

	foreach ( KWO_OPTION_KEYS as $option => $base ) {
		$aid = (int) get_option( $option );
		if ( $aid > 0 && isset( $att[ $aid ] ) ) {
			$rows[] = kwo_simple_row( $att, $aid, $base, 'option:' . $option, $opts, $basedir );
		}
	}

	$urls = array();
	foreach ( $wpdb->get_results( "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_content LIKE '%/wp-content/uploads/%'" ) as $row ) {
		if ( preg_match_all( '#/wp-content/uploads/[^\s"\'<>\)]+#', $row->post_content, $m ) ) {
			foreach ( $m[0] as $url ) {
				$urls[ $url ][] = (int) $row->ID;
			}
		}
	}

	return array( 'rows' => $rows, 'urls' => $urls );
}

/**
 * One planned row for an attachment reached by something other than a post slot.
 *
 * @param array  $att     Attachment index.
 * @param int    $aid     Attachment id.
 * @param string $base    Target file name without extension.
 * @param string $source  Where the reference lives, for the report.
 * @param array  $opts    dest.
 * @param string $basedir Uploads base directory.
 * @return array
 */
function kwo_simple_row( array $att, int $aid, string $base, string $source, array $opts, string $basedir ): array {
	$meta = $att[ $aid ];

	$row = array(
		'att'       => $aid,
		'post'      => 0,
		'role'      => $source,
		'idx'       => 0,
		'order'     => 0,
		'exempt'    => false,
		'conflict'  => false,
		'src'       => $meta['file'],
		'src_bytes' => $meta['bytes'],
		'src_width' => $meta['width'],
		'mime'      => $meta['mime'],
		'dst'       => $opts['dest'] . '/' . $base . '.webp',
		'action'    => 'reencode',
		'note'      => '',
	);

	if ( '' === $row['src'] ) {
		$row['action'] = 'skip-norow';
	} elseif ( $row['dst'] === $row['src'] ) {
		$row['action'] = 'skip-done';
	} elseif ( 'image/gif' === $row['mime'] ) {
		$row['action'] = 'skip-gif';
	} elseif ( ! in_array( $row['mime'], K_WEBP_INPUT_MIMES, true ) ) {
		$row['action'] = 'skip-unsupported';
	} elseif ( ! is_file( $basedir . '/' . $row['src'] ) ) {
		$row['action'] = 'skip-missing';
	}

	return $row;
}

/* -------------------------------------------------------------------------
 * Reporting
 * ---------------------------------------------------------------------- */

/** Rows that would put bytes on disk. */
function kwo_is_write( array $row ): bool {
	return in_array( $row['action'], array( 'reencode', 'clone' ), true );
}

/**
 * Encode the planned rows in memory to report what they would really cost.
 *
 * Writes nothing: the blob is sized and dropped. Only worth asking for on a
 * small --limit, since it is the same work the real run does.
 *
 * @param array $plan Planned rows, modified in place.
 * @param array $opts quality, max_width.
 * @return array{before:int,after:int,done:int,failed:int}
 */
function kwo_measure( array &$plan, array $opts ): array {
	$basedir = wp_get_upload_dir()['basedir'];
	$stat    = array( 'before' => 0, 'after' => 0, 'done' => 0, 'failed' => 0 );

	foreach ( $plan as &$row ) {
		if ( ! kwo_is_write( $row ) ) {
			continue;
		}

		$data = @file_get_contents( $basedir . '/' . $row['src'] );
		if ( false === $data ) {
			$row['note'] = 'unreadable';
			++$stat['failed'];
			continue;
		}

		try {
			$res = k_compress_image_to_webp( $data, $opts['quality'], $opts['max_width'] );
		} catch ( RuntimeException $e ) {
			$row['note'] = 'encode failed: ' . $e->getMessage();
			++$stat['failed'];
			unset( $data );
			continue;
		}

		// The real byte count, not the one metadata claims: 11k rows carry no
		// filesize at all and would otherwise report a nonsense ratio.
		$out = $res['bytes'];

		// Count the thumbnail too, or this total undershoots what the write
		// actually puts on disk and the two runs disagree.
		if ( $res['width'] > 300 ) {
			try {
				$small = k_compress_image_to_webp( $data, $opts['quality'], 300, false );
				$out  += $small['bytes'];
				unset( $small );
			} catch ( RuntimeException $e ) {
				$row['note'] = 'thumbnail encode failed: ' . $e->getMessage();
			}
		}

		$row['in_bytes']  = strlen( $data );
		$row['out_bytes'] = $out;
		$row['out_width'] = $res['width'];

		$stat['before'] += $row['in_bytes'];
		$stat['after']  += $out;
		++$stat['done'];

		unset( $data, $res );
	}
	unset( $row );

	return $stat;
}

/**
 * Format one example row's size column.
 *
 * @param array $row Planned row.
 * @return string
 */
function kwo_size_cell( array $row ): string {
	if ( ! isset( $row['out_bytes'], $row['in_bytes'] ) || $row['in_bytes'] <= 0 ) {
		return $row['src_bytes'] > 0 ? size_format( $row['src_bytes'], 1 ) : '-';
	}

	// Signed, because a small PNG can come out of a lossy WebP encode larger
	// than it went in and "--27%" reads as a saving at a glance.
	$delta = (int) round( ( $row['out_bytes'] / $row['in_bytes'] - 1 ) * 100 );

	return sprintf(
		'%s -> %s (%+d%%)',
		size_format( $row['in_bytes'], 1 ),
		size_format( $row['out_bytes'], 1 ),
		$delta
	);
}

/**
 * Print the plan.
 *
 * @param array $plan  Planned rows.
 * @param array $posts Post index, empty for the terms pass.
 * @param array $opts  Run options.
 * @param array $stat  Measurement totals, empty when --measure was not given.
 */
function kwo_report( array $plan, array $posts, array $opts, array $stat ): void {
	$by_action = array();
	$targets   = array();
	$writes    = 0;

	foreach ( $plan as $row ) {
		$by_action[ $row['action'] ] = ( $by_action[ $row['action'] ] ?? 0 ) + 1;

		if ( kwo_is_write( $row ) ) {
			++$writes;
			$targets[ $row['dst'] ][] = $row['att'];
		}
	}

	ksort( $by_action );

	echo "\naction breakdown\n";
	foreach ( $by_action as $action => $n ) {
		printf( "  %-20s %9s\n", $action, number_format_i18n( $n ) );
	}
	printf( "  %-20s %9s\n", str_repeat( '-', 18 ), str_repeat( '-', 9 ) );
	printf( "  %-20s %9s\n", 'files to write', number_format_i18n( $writes ) );

	// Two slots resolving to one file name would overwrite each other during the
	// real run, so the dry run has to be the thing that catches it.
	$clashes = array_filter( $targets, static fn( array $ids ): bool => count( $ids ) > 1 );
	printf( "\ntarget name clashes : %d%s\n", count( $clashes ), $clashes ? '   <-- MUST be 0 before any write' : '' );

	foreach ( array_slice( $clashes, 0, 10, true ) as $dst => $ids ) {
		$head = array_slice( $ids, 0, 6 );
		printf(
			"  %-56s <- att %s%s\n",
			$dst,
			implode( ', ', $head ),
			count( $ids ) > 6 ? sprintf( ' ... +%d more', count( $ids ) - 6 ) : ''
		);
	}

	// Where each name came from. A spike in 'title' or 'id' means drafts, which
	// have no post_name until they are published.
	$sources = array();
	foreach ( $plan as $row ) {
		if ( isset( $row['source'] ) && '' !== $row['source'] ) {
			$sources[ $row['source'] ] = ( $sources[ $row['source'] ] ?? 0 ) + 1;
		}
	}
	if ( $sources ) {
		ksort( $sources );
		echo "\nslug source\n";
		foreach ( $sources as $source => $n ) {
			printf( "  %-20s %9s\n", $source, number_format_i18n( $n ) );
		}
	}

	$conflict_atts  = array();
	$conflict_posts = array();
	$clones         = 0;

	foreach ( $plan as $row ) {
		if ( empty( $row['conflict'] ) ) {
			continue;
		}
		$conflict_atts[ $row['att'] ] = true;
		if ( 'clone' === $row['action'] ) {
			++$clones;
			$conflict_posts[ $row['post'] ] = true;
		}
	}

	printf(
		"\nconflicts: %d attachments -> %d new attachments, %d posts rewritten\n",
		count( $conflict_atts ),
		$clones,
		count( $conflict_posts )
	);

	$shown = 0;
	foreach ( $plan as $row ) {
		if ( empty( $row['conflict'] ) || $shown >= 8 ) {
			continue;
		}
		printf(
			"  att#%-7d %7d/%-11s -> %-46s %s\n",
			$row['att'],
			$row['post'],
			'featured' === $row['role'] ? 'featured' : 'gallery:' . $row['idx'],
			$row['dst'],
			'reencode' === $row['action'] ? 'KEEP ID' : strtoupper( $row['action'] )
		);
		++$shown;
	}

	$show = (int) $opts['show'];
	if ( $show > 0 ) {
		printf( "\nexamples (up to %d rows)\n", $show );
		$n = 0;

		foreach ( $plan as $row ) {
			if ( $n >= $show ) {
				break;
			}

			$label = 0 === $row['post']
				? $row['role']
				: sprintf(
					'%s#%d %s',
					$posts[ $row['post'] ]['type'] ?? '?',
					$row['post'],
					'featured' === $row['role'] ? 'featured' : 'gallery:' . $row['idx']
				);

			printf(
				"  %-17s %-30s %-46s -> %-44s %s %s\n",
				$row['action'],
				$label,
				$row['src'],
				$row['dst'] ?: '-',
				kwo_size_cell( $row ),
				$row['note']
			);
			++$n;
		}
	}

	if ( $stat ) {
		$pct = $stat['before'] > 0 ? round( ( 1 - $stat['after'] / $stat['before'] ) * 100, 1 ) : 0;

		printf( "\nmeasured on %s encoded rows (%d failed)\n", number_format_i18n( $stat['done'] ), $stat['failed'] );
		printf( "  before : %s\n", size_format( $stat['before'], 2 ) );
		printf( "  after  : %s  (-%s%%)\n", size_format( $stat['after'], 2 ), $pct );
	}
}

/* -------------------------------------------------------------------------
 * Writing
 * ---------------------------------------------------------------------- */

/**
 * The attachment metadata WordPress expects for a single-size WebP.
 *
 * image_meta carries core's empty defaults rather than being left out: the
 * EXIF block was stripped during encoding, and consumers index into it without
 * checking.
 *
 * @param string $file   Uploads-relative path of the full size.
 * @param int    $width  Full-size width.
 * @param int    $height Full-size height.
 * @param int    $bytes  Full-size byte count.
 * @param array  $thumb  Thumbnail entry, or empty when the image is too small.
 * @return array
 */
function kwo_build_metadata( string $file, int $width, int $height, int $bytes, array $thumb ): array {
	return array(
		'width'      => $width,
		'height'     => $height,
		'file'       => $file,
		'filesize'   => $bytes,
		'sizes'      => $thumb ? array( 'thumbnail' => $thumb ) : array(),
		'image_meta' => array(
			'aperture'          => '0',
			'credit'            => '',
			'camera'            => '',
			'caption'           => '',
			'created_timestamp' => '0',
			'copyright'         => '',
			'focal_length'      => '0',
			'iso'               => '0',
			'shutter_speed'     => '0',
			'title'             => '',
			'orientation'       => '0',
			'keywords'          => array(),
		),
	);
}

/**
 * Put one blob on disk, creating its directory and leaving it world readable.
 *
 * @param string $abs  Absolute destination path.
 * @param string $blob Bytes to write.
 * @return bool
 */
function kwo_put( string $abs, string $blob ): bool {
	if ( ! wp_mkdir_p( dirname( $abs ) ) ) {
		return false;
	}
	if ( false === file_put_contents( $abs, $blob ) ) {
		return false;
	}

	// The CLI runs as root inside the container while nginx serves the same
	// bind mount, so an inherited 0600 would 403 every new image.
	@chmod( $abs, 0644 );

	return true;
}

/**
 * Write the full size and, when it is worth having, the 300px thumbnail.
 *
 * The thumbnail is encoded from the original buffer rather than from the blob
 * just produced, so it is not a second lossy pass. This theme registers exactly
 * one size — add_image_size( 'thumbnail', 300, 9999, false ) in functions.php —
 * and k_remove_image_sizes() unregisters the rest, so there is nothing else to
 * generate.
 *
 * @param string $data    Original image bytes.
 * @param array  $row     Planned row.
 * @param array  $opts    quality, max_width.
 * @param string $basedir Uploads base directory.
 * @return array{meta:array,bytes:int} Metadata ready to store, and bytes written.
 * @throws RuntimeException On an encode or write failure.
 */
function kwo_write_files( string $data, array $row, array $opts, string $basedir ): array {
	$full = k_compress_image_to_webp( $data, $opts['quality'], $opts['max_width'] );

	if ( ! kwo_put( $basedir . '/' . $row['dst'], $full['blob'] ) ) {
		throw new RuntimeException( 'could not write ' . $row['dst'] );
	}

	$written = $full['bytes'];
	$thumb   = array();

	if ( $full['width'] > 300 ) {
		$small = k_compress_image_to_webp( $data, $opts['quality'], 300, false );
		$name  = sprintf(
			'%s-%dx%d.webp',
			pathinfo( $row['dst'], PATHINFO_FILENAME ),
			$small['width'],
			$small['height']
		);

		if ( ! kwo_put( $basedir . '/' . dirname( $row['dst'] ) . '/' . $name, $small['blob'] ) ) {
			throw new RuntimeException( 'could not write thumbnail for ' . $row['dst'] );
		}

		$written += $small['bytes'];
		$thumb    = array(
			'file'      => $name,
			'width'     => $small['width'],
			'height'    => $small['height'],
			'mime-type' => 'image/webp',
			'filesize'  => $small['bytes'],
		);
	}

	return array(
		'meta'  => kwo_build_metadata( $row['dst'], $full['width'], $full['height'], $full['bytes'], $thumb ),
		'bytes' => $written,
	);
}

/**
 * Point the attachment row at the file that was just written.
 *
 * $wpdb->update rather than wp_update_post(): the latter fires save_post on
 * every one of 80k rows, which drags ACF, revisions and the cache layer along
 * for a change none of them care about.
 *
 * @param int    $aid    Attachment id.
 * @param array  $meta   Metadata from kwo_write_files().
 * @param string $dst    Uploads-relative path.
 * @param int    $parent Post to adopt the attachment, 0 to leave it alone.
 */
function kwo_point_attachment_at( int $aid, array $meta, string $dst, int $parent ): void {
	global $wpdb;

	update_post_meta( $aid, '_wp_attached_file', $dst );
	update_post_meta( $aid, '_wp_attachment_metadata', $meta );

	$fields = array(
		'post_mime_type' => 'image/webp',
		'guid'           => wp_get_upload_dir()['baseurl'] . '/' . $dst,
		'post_title'     => pathinfo( $dst, PATHINFO_FILENAME ),
	);

	if ( $parent > 0 ) {
		$fields['post_parent'] = $parent;
	}

	$wpdb->update( $wpdb->posts, $fields, array( 'ID' => $aid ) );
	clean_post_cache( $aid );
}

/**
 * Create a second attachment for a slot that cannot share the first one.
 *
 * @param array  $row  Planned row.
 * @param array  $meta Metadata from kwo_write_files().
 * @param string $abs  Absolute path of the written file.
 * @return int New attachment id, 0 on failure.
 */
function kwo_clone_attachment( array $row, array $meta, string $abs ): int {
	$new_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/webp',
			'post_title'     => pathinfo( $row['dst'], PATHINFO_FILENAME ),
			'post_status'    => 'inherit',
			'post_parent'    => $row['post'],
			'guid'           => wp_get_upload_dir()['baseurl'] . '/' . $row['dst'],
		),
		$abs,
		$row['post']
	);

	if ( is_wp_error( $new_id ) || ! $new_id ) {
		return 0;
	}

	update_post_meta( $new_id, '_wp_attached_file', $row['dst'] );
	update_post_meta( $new_id, '_wp_attachment_metadata', $meta );

	$alt = get_post_meta( $row['att'], '_wp_attachment_image_alt', true );
	if ( '' !== $alt ) {
		update_post_meta( $new_id, '_wp_attachment_image_alt', $alt );
	}

	return (int) $new_id;
}

/**
 * Swap a cloned attachment into the slot that asked for it.
 *
 * The gallery is read with get_post_meta( ..., true ), the same first-row-wins
 * rule the planner used and the one the site renders with, and the ids are
 * written back as strings so the stored array keeps the shape ACF gave it.
 *
 * @param array $row    Planned row.
 * @param int   $new_id Replacement attachment id.
 * @return bool Whether the reference now points at $new_id.
 */
function kwo_rewrite_reference( array $row, int $new_id ): bool {
	if ( 'featured' === $row['role'] ) {
		update_post_meta( $row['post'], '_thumbnail_id', $new_id );
		return true;
	}

	$ids = get_post_meta( $row['post'], 'image', true );
	if ( ! is_array( $ids ) ) {
		return false;
	}

	$ids = array_values( $ids );
	$at  = $row['idx'] - 1;

	// The position has to still hold the attachment the plan saw, or the
	// gallery changed under the run and guessing would corrupt it.
	if ( ! isset( $ids[ $at ] ) || (int) $ids[ $at ] !== (int) $row['att'] ) {
		return false;
	}

	$ids[ $at ] = (string) $new_id;
	update_post_meta( $row['post'], 'image', $ids );

	return true;
}

/**
 * Carry out the plan.
 *
 * Files land before rows change. A crash in between leaves an unreferenced file
 * in the destination, which the next run overwrites; the reverse order would
 * leave a row pointing at a file that does not exist.
 *
 * @param array    $plan Planned rows.
 * @param array    $opts quality, max_width.
 * @param resource $log  Log handle, or null.
 * @return array Counters.
 */
function kwo_execute( array $plan, array $opts, $log ): array {
	$basedir = wp_get_upload_dir()['basedir'];

	$stat = array(
		'reencoded' => 0,
		'cloned'    => 0,
		'skipped'   => 0,
		'failed'    => 0,
		'before'    => 0,
		'after'     => 0,
	);

	foreach ( $plan as $row ) {
		if ( ! kwo_is_write( $row ) ) {
			++$stat['skipped'];
			kwo_log( $log, $row, $row['action'], '' );
			continue;
		}

		$src = $basedir . '/' . $row['src'];

		try {
			$data = @file_get_contents( $src );
			if ( false === $data ) {
				throw new RuntimeException( 'unreadable source' );
			}

			$result = kwo_write_files( $data, $row, $opts, $basedir );
			$in     = strlen( $data );
			unset( $data );

			if ( 'clone' === $row['action'] ) {
				$new_id = kwo_clone_attachment( $row, $result['meta'], $basedir . '/' . $row['dst'] );
				if ( ! $new_id ) {
					throw new RuntimeException( 'wp_insert_attachment failed' );
				}
				if ( ! kwo_rewrite_reference( $row, $new_id ) ) {
					throw new RuntimeException( 'reference moved, slot not rewritten; attachment ' . $new_id . ' left orphaned' );
				}

				++$stat['cloned'];
				kwo_log( $log, $row, 'cloned', 'new att ' . $new_id );
			} else {
				$parent = 0;
				$att    = get_post( $row['att'] );
				if ( $att && 0 === (int) $att->post_parent ) {
					$parent = $row['post'];
				}

				kwo_point_attachment_at( $row['att'], $result['meta'], $row['dst'], $parent );

				++$stat['reencoded'];
				kwo_log( $log, $row, 'reencoded', '' );
			}

			$stat['before'] += $in;
			$stat['after']  += $result['bytes'];
		} catch ( Throwable $e ) {
			++$stat['failed'];
			kwo_log( $log, $row, 'FAILED', $e->getMessage() );
			fwrite( STDERR, sprintf( "[fail] att#%d %s: %s\n", $row['att'], $row['src'], $e->getMessage() ) );
		}
	}

	return $stat;
}

/**
 * Append one tab-separated line to the run log.
 *
 * @param resource|null $log    Log handle.
 * @param array         $row    Planned row.
 * @param string        $status What happened.
 * @param string        $note   Extra detail.
 */
function kwo_log( $log, array $row, string $status, string $note ): void {
	if ( ! $log ) {
		return;
	}

	fwrite( $log, implode( "\t", array(
		gmdate( 'Y-m-d H:i:s' ),
		$status,
		'att' . $row['att'],
		'post' . $row['post'],
		'featured' === $row['role'] ? 'featured' : $row['role'] . ':' . $row['idx'],
		$row['src'],
		$row['dst'],
		$note,
	) ) . "\n" );
}

/** How many destination names more than one slot wants. Must be 0 to write. */
function kwo_clash_count( array $plan ): int {
	$targets = array();

	foreach ( $plan as $row ) {
		if ( kwo_is_write( $row ) ) {
			$targets[ $row['dst'] ][] = $row['att'];
		}
	}

	return count( array_filter( $targets, static fn( array $ids ): bool => count( $ids ) > 1 ) );
}

/**
 * Report the plan and, when asked, carry it out.
 *
 * @param array         $plan    Planned rows.
 * @param array         $posts   Post index, empty for the terms pass.
 * @param array         $opts    Run options.
 * @param bool          $write   Whether to execute.
 * @param bool          $measure Dry-run byte measurement.
 * @param resource|null $log     Log handle.
 * @param float         $started Start time.
 */
function kwo_finish( array $plan, array $posts, array $opts, bool $write, bool $measure, $log, float $started ): void {
	$stat = ( ! $write && $measure ) ? kwo_measure( $plan, $opts ) : array();
	kwo_report( $plan, $posts, $opts, $stat );

	$elapsed = static function () use ( $started ): void {
		printf( "\n[done] %.1fs  peak memory %s\n", microtime( true ) - $started, size_format( memory_get_peak_usage( true ) ) );
	};

	if ( ! $write ) {
		echo "\nnothing was written.\n";
		$elapsed();
		return;
	}

	// Two slots wanting one file name means the second would overwrite the
	// first and a post would silently end up showing another post's image.
	$clashes = kwo_clash_count( $plan );
	if ( $clashes > 0 ) {
		fwrite( STDERR, sprintf( "Refusing to write: %d destination names are claimed twice.\n", $clashes ) );
		exit( 1 );
	}

	$res = kwo_execute( $plan, $opts, $log );
	$pct = $res['before'] > 0 ? round( ( 1 - $res['after'] / $res['before'] ) * 100, 1 ) : 0;

	echo "\nwritten\n";
	printf( "  reencoded : %s\n", number_format_i18n( $res['reencoded'] ) );
	printf( "  cloned    : %s\n", number_format_i18n( $res['cloned'] ) );
	printf( "  skipped   : %s\n", number_format_i18n( $res['skipped'] ) );
	printf( "  failed    : %s\n", number_format_i18n( $res['failed'] ) );
	printf( "  bytes     : %s -> %s  (-%s%%)\n", size_format( $res['before'], 2 ), size_format( $res['after'], 2 ), $pct );

	$elapsed();
}

/* -------------------------------------------------------------------------
 * Run
 * ---------------------------------------------------------------------- */

$args = kwo_parse_args( $argv );

if ( isset( $args['help'] ) ) {
	kwo_usage();
	exit( 0 );
}

$dry_run = isset( $args['dry-run'] );
$write   = isset( $args['write'] );

// Never inferred: a typo in a long command line should stop the run, not pick
// the destructive reading of it.
if ( $dry_run === $write ) {
	fwrite( STDERR, "Pass exactly one of --dry-run or --write. See --help.\n" );
	exit( 1 );
}

if ( ! extension_loaded( 'imagick' ) ) {
	fwrite( STDERR, "The imagick extension is not loaded.\n" );
	exit( 1 );
}

$log = null;
if ( is_string( $args['log'] ?? null ) && '' !== $args['log'] ) {
	$log = fopen( $args['log'], 'a' );
	if ( ! $log ) {
		fwrite( STDERR, 'Cannot open the log at ' . $args['log'] . "\n" );
		exit( 1 );
	}
}

$max_width = isset( $args['max-width'] ) ? (int) $args['max-width'] : K_WEBP_MAX_WIDTH;

$opts = array(
	'pass'      => is_string( $args['pass'] ?? null ) ? $args['pass'] : 'posts',
	'dest'      => is_string( $args['dest'] ?? null ) ? trim( $args['dest'], '/' ) : current_time( 'Y/m' ),
	'quality'   => isset( $args['quality'] ) ? (int) $args['quality'] : K_WEBP_QUALITY,
	'max_width' => $max_width > 0 ? $max_width : null,
	'workers'   => max( 1, (int) ( $args['workers'] ?? 1 ) ),
	'worker'    => max( 0, (int) ( $args['worker'] ?? 0 ) ),
	'limit'     => max( 0, (int) ( $args['limit'] ?? 0 ) ),
	'show'      => isset( $args['show'] ) ? max( 0, (int) $args['show'] ) : 20,
);

if ( ! in_array( $opts['pass'], array( 'posts', 'conflicts', 'terms' ), true ) ) {
	fwrite( STDERR, "Unknown --pass. Use posts, conflicts or terms.\n" );
	exit( 1 );
}

// pass=conflicts handles several slots of one attachment and has to stay
// single-threaded: sharding it would let one worker read a source while
// another is rewriting where that source lives.
if ( 'posts' !== $opts['pass'] && $opts['workers'] > 1 ) {
	fwrite( STDERR, "--workers only applies to --pass=posts.\n" );
	exit( 1 );
}

$post_types = is_string( $args['post-type'] ?? null )
	? array_values( array_filter( array_map( 'trim', explode( ',', $args['post-type'] ) ) ) )
	: array( 'rom', 'emulator', 'post' );

$started = microtime( true );

printf(
	"[plan] pass=%s dest=%s quality=%d max_width=%s worker=%d/%d limit=%s\n",
	$opts['pass'],
	$opts['dest'],
	$opts['quality'],
	null === $opts['max_width'] ? 'none' : $opts['max_width'],
	$opts['worker'],
	$opts['workers'],
	$opts['limit'] ?: 'all'
);

$att = kwo_load_attachments();

if ( 'terms' === $opts['pass'] ) {
	$terms = kwo_build_terms_plan( $att, $opts );
	$plan  = $terms['rows'];

	printf(
		"[scan] attachments=%s  term/option images=%d  posts with a hardcoded uploads URL=%d\n",
		number_format_i18n( count( $att ) ),
		count( $plan ),
		count( $terms['urls'] )
	);

	kwo_finish( $plan, array(), $opts, $write, isset( $args['measure'] ), $log, $started );

	// Rewriting these is not part of this pass: the ids inside post_content are
	// already handled through their slots, and the bare URLs need a separate
	// search and replace that has to be approved on its own.
	printf( "\nURLs still hardcoded in post_content, not rewritten here: %d\n", count( $terms['urls'] ) );
	foreach ( array_slice( $terms['urls'], 0, 10, true ) as $url => $pids ) {
		printf( "  %-78s posts %s\n", $url, implode( ',', array_unique( $pids ) ) );
	}

	exit( 0 );
}

$loaded = kwo_load_slots( $post_types );

// The eight shared logos name themselves after their current file, so those
// names are off the table before any post slug is resolved against them.
$reserved = array();
foreach ( KWO_EXEMPT as $exempt_id ) {
	if ( ! empty( $att[ $exempt_id ]['file'] ) ) {
		$reserved[] = k_webp_basename( pathinfo( $att[ $exempt_id ]['file'], PATHINFO_FILENAME ) );
	}
}

$with_slots = array();
foreach ( $loaded['slots'] as $slot_list ) {
	foreach ( $slot_list as $slot ) {
		$with_slots[ $slot['post'] ] = true;
	}
}

$slugs = kwo_resolve_slugs( $loaded['posts'], array_keys( $with_slots ), $reserved );
$plan  = kwo_build_plan( $att, $loaded['slots'], $slugs, $opts );

$deduped = count( array_filter( $slugs, static fn( array $s ): bool => $s['deduped'] ) );

printf(
	"[scan] posts=%s  with images=%s  attachments=%s  slots=%s  slugs deduped=%d\n",
	number_format_i18n( count( $loaded['posts'] ) ),
	number_format_i18n( count( $with_slots ) ),
	number_format_i18n( count( $att ) ),
	number_format_i18n( count( $plan ) ),
	$deduped
);

// A conflicted attachment is read by several slots at once, and the slot that
// keeps the id rewrites _wp_attached_file out from under the others. They are
// planned together and run single-threaded for that reason, which also keeps
// the sharded pass free of any cross-worker ordering.
$want_conflicts = ( 'conflicts' === $opts['pass'] );

$plan = array_values( array_filter(
	$plan,
	static fn( array $row ): bool => ! empty( $row['conflict'] ) === $want_conflicts
) );

if ( ! $want_conflicts && $opts['workers'] > 1 ) {
	$plan = array_values( array_filter(
		$plan,
		static fn( array $row ): bool => ( $row['post'] % $opts['workers'] ) === $opts['worker']
	) );
}

if ( $opts['limit'] > 0 ) {
	// Take whole posts: cutting mid-post would hide half a gallery from the
	// report and make the example rows misleading.
	$allowed = array();
	foreach ( $plan as $row ) {
		if ( ! isset( $allowed[ $row['post'] ] ) && count( $allowed ) >= $opts['limit'] ) {
			continue;
		}
		$allowed[ $row['post'] ] = true;
	}

	$plan = array_values( array_filter(
		$plan,
		static fn( array $row ): bool => isset( $allowed[ $row['post'] ] )
	) );
}

printf( "[plan] rows in this pass: %s\n", number_format_i18n( count( $plan ) ) );

kwo_finish( $plan, $loaded['posts'], $opts, $write, isset( $args['measure'] ), $log, $started );
