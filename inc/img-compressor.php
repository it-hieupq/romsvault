<?php
/**
 * In-memory WebP compression.
 *
 * Ported from the standalone img-compressor tool. Only its encode pipeline and
 * buffer MIME sniff came across: the HTTP half — session, CSRF, API key, CORS,
 * $_FILES ingestion, JSON/binary responses — means nothing inside WordPress, and
 * its slugifyFilename() is what sanitize_title() already does, the same way
 * post_name was generated in the first place.
 *
 * Nothing here reads or writes the database or the filesystem. A caller hands in
 * a buffer and gets a buffer back, so the grabber can encode a freshly
 * downloaded image before anything lands on disk.
 *
 * Measured on this library: WebP at q80 alone saves ~15%, because the JPEGs are
 * already compressed. Capping the width is where the saving is — ~42% at 1280.
 * So max_width is the setting that matters, not quality.
 *
 * @package romsvault
 */

defined( 'ABSPATH' ) || exit;

/** Encoder quality, 1-100. */
const K_WEBP_QUALITY = 80;

/**
 * Bounding box in pixels; an image is scaled down, ratio kept, until it fits.
 *
 * The same box the library was brought into. The templates never need more:
 * the rom and emulator hero render inside a four-of-twelve column and the
 * screenshot strip at 200px tall, with the lightbox opening the same file.
 * Capping width alone let tall box art keep a 2,600px height and weigh over a
 * megabyte, which is why there is a height limit at all.
 */
const K_WEBP_MAX_WIDTH  = 1280;
const K_WEBP_MAX_HEIGHT = 1000;

/** libwebp effort level. 4 is the tool's setting: near-6 ratio at half the time. */
const K_WEBP_METHOD = '4';

/** Refuse a buffer larger than this. The largest image in the library is 4 MB. */
const K_WEBP_MAX_BYTES = 33554432; // 32 MB

/**
 * Formats the encoder accepts.
 *
 * GIF is accepted only with a single frame. Imagick reads every frame but
 * getImageBlob() writes only the first, so an animated GIF would silently lose
 * its animation; k_compress_image_to_webp() refuses those, and callers move them
 * as they are. BMP is here because grabbed box art sometimes arrives as a BMP
 * saved under a .jpg name.
 */
const K_WEBP_INPUT_MIMES = array(
	'image/jpeg',
	'image/pjpeg',
	'image/jpg',
	'image/png',
	'image/x-png',
	'image/webp',
	'image/avif',
	'image/gif',
	'image/bmp',
	'image/x-ms-bmp',
);

/**
 * Identify an image buffer without touching the disk.
 *
 * finfo is the fast path, but it is not guaranteed to be installed, so the magic
 * bytes are checked directly before falling back to letting Imagick ping the
 * blob. Returns 'application/octet-stream' when nothing recognises it.
 *
 * @param string $data Raw image bytes.
 * @return string MIME type.
 */
function k_detect_image_mime( string $data ): string {
	if ( '' === $data ) {
		return 'application/octet-stream';
	}

	if ( function_exists( 'finfo_open' ) ) {
		// No finfo_close(): it is deprecated as of PHP 8.4 and the handle is an
		// object that goes away with the scope anyway.
		$finfo = @finfo_open( FILEINFO_MIME_TYPE );
		if ( false !== $finfo ) {
			$mime = @finfo_buffer( $finfo, $data );
			if ( is_string( $mime ) && '' !== $mime ) {
				return $mime;
			}
		}
	}

	if ( str_starts_with( $data, "\xFF\xD8\xFF" ) ) {
		return 'image/jpeg';
	}
	if ( str_starts_with( $data, "\x89PNG\x0D\x0A\x1A\x0A" ) ) {
		return 'image/png';
	}
	if ( str_starts_with( $data, 'GIF8' ) ) {
		return 'image/gif';
	}
	if ( str_starts_with( $data, 'BM' ) && strlen( $data ) > 26 ) {
		return 'image/bmp';
	}
	if ( strlen( $data ) >= 12 && str_starts_with( $data, 'RIFF' ) && 'WEBP' === substr( $data, 8, 4 ) ) {
		return 'image/webp';
	}
	if ( strlen( $data ) >= 12 && in_array( substr( $data, 4, 8 ), array( 'ftypavif', 'ftypavis' ), true ) ) {
		return 'image/avif';
	}

	try {
		$probe = new Imagick();
		$probe->pingImageBlob( $data );
		$format = strtolower( $probe->getImageFormat() );
		$probe->clear();
		$probe->destroy();

		switch ( $format ) {
			case 'jpeg':
			case 'jpg':
				return 'image/jpeg';
			case 'png':
				return 'image/png';
			case 'gif':
				return 'image/gif';
			case 'webp':
				return 'image/webp';
			case 'avif':
				return 'image/avif';
			case 'bmp':
			case 'bmp3':
				return 'image/bmp';
		}
	} catch ( Throwable $e ) {
		// Corrupt or unreadable; fall through to the unknown type.
		unset( $e );
	}

	return 'application/octet-stream';
}

/**
 * Encode an image buffer to WebP, entirely in memory.
 *
 * A WebP input already inside the width cap is returned byte for byte rather
 * than re-encoded: a second lossy pass costs quality and buys nothing. The
 * caller still gets the dimensions it needs to write attachment metadata.
 *
 * @param string   $data             Raw image bytes.
 * @param int      $quality          WebP quality, 1-100.
 * @param int|null $max_width        Box width; null leaves width unbounded.
 * @param int|null $max_height       Box height; null leaves height unbounded.
 * @param bool     $passthrough_webp Return a WebP already inside the box untouched.
 * @return array{blob:string,mime:string,width:int,height:int,bytes:int,original_bytes:int,original_mime:string,resized:bool,passthrough:bool}
 * @throws RuntimeException When the buffer is empty, oversized, not a supported
 *                          image, or Imagick cannot decode it.
 */
function k_compress_image_to_webp(
	string $data,
	int $quality = K_WEBP_QUALITY,
	?int $max_width = K_WEBP_MAX_WIDTH,
	?int $max_height = K_WEBP_MAX_HEIGHT,
	bool $passthrough_webp = true
): array {
	$original_bytes = strlen( $data );

	if ( 0 === $original_bytes ) {
		throw new RuntimeException( 'Empty image buffer.' );
	}
	if ( $original_bytes > K_WEBP_MAX_BYTES ) {
		throw new RuntimeException( sprintf(
			'Buffer of %s exceeds the %s limit.',
			size_format( $original_bytes ),
			size_format( K_WEBP_MAX_BYTES )
		) );
	}
	if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
		throw new RuntimeException( 'The imagick extension is not loaded.' );
	}

	$mime = k_detect_image_mime( $data );
	if ( ! in_array( $mime, K_WEBP_INPUT_MIMES, true ) ) {
		throw new RuntimeException( sprintf( 'Unsupported input type %s.', $mime ) );
	}

	$quality    = max( 1, min( 100, $quality ) );
	$max_width  = ( null !== $max_width && $max_width > 0 ) ? $max_width : null;
	$max_height = ( null !== $max_height && $max_height > 0 ) ? $max_height : null;

	$imagick = new Imagick();

	try {
		$imagick->readImageBlob( $data );

		if ( 'image/gif' === $mime && $imagick->getNumberImages() > 1 ) {
			throw new RuntimeException( 'Animated GIF; move it as-is instead of flattening it.' );
		}

		// EXIF orientation has to be resolved before stripImage() drops the tag,
		// or a rotated photo would come out sideways with nothing left to say so.
		$imagick->autoOrient();

		$width   = $imagick->getImageWidth();
		$height  = $imagick->getImageHeight();
		$resized = false;

		// The tighter of the two limits wins, so the result is inside the box on
		// both axes and the aspect ratio is untouched.
		$scale = min(
			null !== $max_width ? $max_width / $width : 1,
			null !== $max_height ? $max_height / $height : 1,
			1
		);

		if ( 'image/webp' === $mime && $passthrough_webp && $scale >= 1 ) {
			return array(
				'blob'           => $data,
				'mime'           => 'image/webp',
				'width'          => $width,
				'height'         => $height,
				'bytes'          => $original_bytes,
				'original_bytes' => $original_bytes,
				'original_mime'  => $mime,
				'resized'        => false,
				'passthrough'    => true,
			);
		}

		if ( $scale < 1 ) {
			// Lanczos: the sharpest of the practical filters for downscaling
			// box art and screenshots, which is all this library holds.
			$imagick->resizeImage(
				max( 1, (int) round( $width * $scale ) ),
				max( 1, (int) round( $height * $scale ) ),
				Imagick::FILTER_LANCZOS,
				1
			);
			$resized = true;
		}

		$imagick->stripImage();
		$imagick->setImageFormat( 'webp' );
		$imagick->setImageCompressionQuality( $quality );
		$imagick->setOption( 'webp:method', K_WEBP_METHOD );

		$blob = $imagick->getImageBlob();

		return array(
			'blob'           => $blob,
			'mime'           => 'image/webp',
			'width'          => $imagick->getImageWidth(),
			'height'         => $imagick->getImageHeight(),
			'bytes'          => strlen( $blob ),
			'original_bytes' => $original_bytes,
			'original_mime'  => $mime,
			'resized'        => $resized,
			'passthrough'    => false,
		);
	} catch ( ImagickException $e ) {
		throw new RuntimeException( 'Imagick failed: ' . $e->getMessage(), 0, $e );
	} finally {
		// Imagick holds the decoded pixels outside the PHP heap, so memory_limit
		// does not protect a long batch run. Release them on every path.
		$imagick->clear();
		$imagick->destroy();
	}
}

/**
 * Turn arbitrary text into the file-name half of an uploads path.
 *
 * sanitize_title() does the accent folding and most of the stripping but keeps
 * underscores, so "Nintendo_3ds_logo" would survive as-is. Collapsing them to
 * hyphens matches what the original tool produced and what post_name looks like.
 *
 * @param string $raw Title, slug or bare file name without its extension.
 * @return string Slug, never empty.
 */
function k_webp_basename( string $raw ): string {
	$slug = sanitize_title( $raw );
	$slug = str_replace( '_', '-', $slug );
	$slug = preg_replace( '/-+/', '-', $slug );
	$slug = trim( (string) $slug, '-' );

	return '' !== $slug ? $slug : 'image';
}
