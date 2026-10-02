<?php
/**
 * Security Helper Class v2.0
 *
 * Two-step input protection:
 *   Step 1: validate_input()  — Block malicious payloads (die on detection)
 *   Step 2: clean_*_input()   — Sanitize individual fields by type
 *
 * Usage:
 *   $_POST = AppSecurity::validate_input( $_POST );
 *   $_GET  = AppSecurity::validate_input( $_GET );
 *
 *   $name   = AppSecurity::clean_text_input( $_POST['name'] );
 *   $email  = AppSecurity::clean_email_input( $_POST['email'] );
 *   $age    = AppSecurity::clean_number_input( $_POST['age'] );
 *   $price  = AppSecurity::clean_float_input( $_POST['price'] );
 *   $url    = AppSecurity::clean_url_input( $_POST['website'] );
 *   $desc   = AppSecurity::clean_textarea_input( $_POST['description'] );
 *   $file   = AppSecurity::clean_filename_input( $_POST['filename'] );
 *   $slug   = AppSecurity::clean_slug_input( $_POST['slug'] );
 *
 * @package App_Security
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AppSecurity {

    // =========================================================================
    // CONFIGURATION
    // =========================================================================

    /**
     * Max allowed input length per field (bytes). Prevents DoS via huge payloads.
     */
    private static int $max_input_length = 3000;

    /**
     * Max recursion depth for nested arrays (e.g. $_POST['a']['b']['c']...).
     */
    private static int $max_depth = 5;

    /**
     * Max decode loops in normalize_input() to catch multi-layer encoding.
     */
    private static int $max_decode_loops = 10;

    /**
     * When true (default), a blacklist match dies via block_request().
     * When false, the match is only logged and the request continues — the
     * clean_*_input() sanitizers, $wpdb->prepare() and output escaping remain the
     * real defense. Flip to false per-site (filter `rf_security_hard_block`) to
     * avoid hard-blocking legitimate users on a false positive.
     */
    private static $hard_block = true;

    // -------------------------------------------------------------------------
    // Filterable config accessors (tune per-site without editing the class).
    // -------------------------------------------------------------------------

    private static function max_input_length(): int {
        static $v = null;
        if ( $v === null ) {
            $v = function_exists( 'apply_filters' )
                ? (int) apply_filters( 'rf_security_max_input_length', self::$max_input_length )
                : self::$max_input_length;
        }
        return $v;
    }

    private static function max_depth(): int {
        static $v = null;
        if ( $v === null ) {
            $v = function_exists( 'apply_filters' )
                ? (int) apply_filters( 'rf_security_max_depth', self::$max_depth )
                : self::$max_depth;
        }
        return $v;
    }

    private static function hard_block(): bool {
        static $v = null;
        if ( $v === null ) {
            $v = function_exists( 'apply_filters' )
                ? (bool) apply_filters( 'rf_security_hard_block', self::$hard_block )
                : self::$hard_block;
        }
        return $v;
    }

    // =========================================================================
    // BLACKLIST PATTERNS
    // =========================================================================

    /**
     * Trimmed blacklist — only cheap, high-signal, low-false-positive checks.
     *
     * The real protection is elsewhere: prepared statements ($wpdb->prepare),
     * output escaping (esc_*), wp_kses, and the clean_*_input() sanitizers below.
     * A blacklist WAF is bypassable and prone to blocking legitimate input, so we
     * keep only signals that are almost never legitimate. (Raw null-byte is checked
     * directly in scan_payload, before normalization.)
     *
     * Patterns run against NORMALIZED input; \b/\s* keep them robust; /i for case.
     */
    private static array $blacklist_patterns = [
        // Path traversal (2+ segments = almost always an attack) + sensitive files.
        [ 'pattern' => '/(?:\.\.\/|\.\.\\\\){2,}/',        'label' => 'Directory traversal' ],
        [ 'pattern' => '/\/etc\/(?:passwd|shadow|hosts)\b/i', 'label' => 'Sensitive system file access' ],
        [ 'pattern' => '/\/proc\/self\//i',               'label' => '/proc/self access' ],
        // Stored-XSS canary (output is already escaped, but a raw <script is never wanted).
        [ 'pattern' => '/<\s*script/i',                   'label' => 'Script tag' ],
        // High-signal SQLi indicators (redundant with prepare(), kept as a tripwire).
        [ 'pattern' => '/\binformation_schema\b/i',       'label' => 'information_schema access' ],
        [ 'pattern' => '/\bINTO\s+(?:OUTFILE|DUMPFILE)\b/i', 'label' => 'SQL INTO OUTFILE/DUMPFILE' ],
        [ 'pattern' => '/\bLOAD_FILE\s*\(/i',             'label' => 'SQL LOAD_FILE' ],
    ];

    // =========================================================================
    // STEP 1: VALIDATE INPUT (Block malicious payloads)
    // =========================================================================

    /**
     * Validate all input data recursively.
     * Checks both KEYS and VALUES against blacklist patterns.
     *
     * On a blacklist match: dies with a 403 JSON response when hard-block mode is
     * on (default), otherwise logs the threat and lets the request continue. The
     * die path emits JSON via wp_send_json_error(), so hard-block callers must be
     * in an AJAX / REST / JSON context — do not call this on a normal page render.
     * Structural guards (length, nesting depth, null byte) always hard-block.
     *
     * @param mixed $data  Input data ($_POST, $_GET, or any array/string)
     * @param int   $depth Current recursion depth (internal use)
     * @return mixed Validated data (trimmed strings)
     */
    public static function validate_input( $data, $depth = 0 ) {
        // Guard: max depth to prevent array nesting DoS
        if ( $depth > self::max_depth() ) {
            self::block_request( 'Max input nesting depth exceeded', 'Structure' );
        }

        if ( is_array( $data ) ) {
            $clean = [];
            foreach ( $data as $key => $value ) {
                // Validate KEY
                if ( is_string( $key ) ) {
                    self::check_input_length( $key, 'Key' );
                    self::scan_payload( $key, 'Key' );
                }

                // Validate VALUE (recursive)
                $clean[ $key ] = self::validate_input( $value, $depth + 1 );
            }
            return $clean;
        }

        if ( is_string( $data ) ) {
            self::check_input_length( $data, 'Value' );
            self::scan_payload( $data, 'Value' );
            return trim( $data );
        }

        // Non-string, non-array (int, float, bool, null) — pass through
        return $data;
    }

    /**
     * Check input string length against maximum.
     *
     * @param string $input
     * @param string $context
     */
    private static function check_input_length( $input, $context = 'Value' ) {
        $max = self::max_input_length();
        if ( strlen( $input ) > $max ) {
            self::block_request(
                sprintf( 'Input too long (%d bytes, max %d)', strlen( $input ), $max ),
                $context,
                403,
                __( 'Your input is too long.', 'romsfun' )
            );
        }
    }

    /**
     * Scan a single string against all blacklist patterns.
     *
     * @param mixed  $input   The input to check
     * @param string $context 'Key' or 'Value' for logging
     */
    private static function scan_payload( $input, $context = 'Value' ) {
        if ( ! is_string( $input ) || $input === '' ) {
            return;
        }

        // Raw null byte is almost always malicious — check before normalization
        // (which strips it).
        if ( strpos( $input, "\0" ) !== false ) {
            self::block_request( 'Null byte injection', $context );
        }

        $normalized = self::normalize_input( $input );
        foreach ( self::$blacklist_patterns as $entry ) {
            if ( preg_match( $entry['pattern'], $normalized ) ) {
                // Respects hard/soft mode: die in hard-block, log-and-continue in soft.
                self::flag_threat( $entry['label'], $context );
            }
        }
    }

    // =========================================================================
    // INPUT NORMALIZATION
    // =========================================================================

    /**
     * Light normalization for the trimmed pattern set: strip null bytes, peel
     * URL + HTML-entity encoding (so %2e%2e / &lt;script are still caught), and
     * collapse whitespace. The heavy hex/unicode/octal/fullwidth/concat/SQL-comment
     * decoding was removed along with the exec/obfuscation patterns it served — a
     * big CPU win with no loss for the remaining checks.
     *
     * @param string $input Raw input string
     * @return string Normalized string for pattern matching
     */
    private static function normalize_input( $input ) {
        // Strip null bytes (raw, escaped, and encoded forms).
        $output = str_replace( [ "\0", '\0', '%00' ], '', $input );

        // Recursive URL decode (handles %25XX -> %XX -> char).
        $loops = self::$max_decode_loops;
        while ( $loops-- > 0 ) {
            $decoded = rawurldecode( $output );
            if ( $decoded === $output ) {
                break;
            }
            $output = $decoded;
        }

        // HTML entity decode (catches &lt;script, &#x2e; ...).
        $output = html_entity_decode( $output, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        // Collapse whitespace so \s* patterns stay simple.
        return preg_replace( '/\s+/', ' ', $output );
    }

    // =========================================================================
    // STEP 2: CLEAN INPUT FUNCTIONS (Sanitize by data type)
    // =========================================================================

    /**
     * Clean text input (single line).
     * Use for: names, titles, short text fields.
     *
     * @param mixed $data
     * @return string
     */
    public static function clean_text_input( $data ) {
        if ( is_array( $data ) ) {
            return array_map( [ __CLASS__, 'clean_text_input' ], $data );
        }

        if ( ! is_string( $data ) && ! is_numeric( $data ) ) {
            return '';
        }

        $data = (string) $data;
        $data = wp_strip_all_tags( $data );
        $data = preg_replace( '/<\?(?:php)?|\?>/i', '', $data );
        $data = sanitize_text_field( $data );
        $data = trim( $data );

        return $data;
    }

    // =========================================================================
    // STRICT VALIDATORS (Die on invalid input)
    // =========================================================================

    /**
     * Validate positive integer (strict — dies on invalid).
     * Use for: IDs, counts, pagination.
     *
     * @param mixed $id
     * @return int
     */
    public static function validate_number( $id ): int {
        $id = absint( $id );
        if ( $id <= 0 ) {
            // Bad ID is a client error, not an attack — use the shared JSON responder
            // WITHOUT writing a [SECURITY BLOCK] log entry (that would flood the log).
            self::send_error( __( 'Invalid numeric input.', 'romsfun' ), 400 );
        }
        return $id;
    }

    // =========================================================================
    // INTERNAL HELPERS
    // =========================================================================

    /**
     * Single JSON error responder. Every error exit in this class funnels through
     * here so the response shape and status handling live in one place.
     *
     * @param string $message Client-facing message.
     * @param int    $status  HTTP status code.
     */
    private static function send_error( string $message, int $status ): void {
        wp_send_json_error( [ 'message' => $message ], $status );
    }

    /**
     * Write a single security-log line. Shared by block_request() (hard mode) and
     * flag_threat() (soft mode) so logging is identical either way.
     *
     * @param string $reason  What was detected
     * @param string $context Where it was found
     */
    private static function log_threat( $reason, $context = 'Value' ): void {
        error_log( sprintf(
            '[SECURITY BLOCK] Reason: %s | Context: %s | IP: %s | URI: %s | UA: %s | Time: %s',
            $reason,
            $context,
            self::get_client_ip(),
            isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : 'unknown',
            isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( $_SERVER['HTTP_USER_AGENT'], 0, 200 ) : 'unknown',
            current_time( 'mysql' )
        ) );
    }

    /**
     * Block request: log the detailed reason (server-side only) and send a JSON
     * error response (dies).
     *
     * The detailed $reason (e.g. "Directory traversal", "Input too long (3200 bytes)")
     * goes to the log ONLY — never to the client, to avoid leaking WAF internals. The
     * client gets a generic, category-level message so the user knows roughly what to
     * fix. Pass $client_message to override the default for friendlier categories
     * (too-long, rate-limit, etc.).
     *
     * @param string $reason         Detailed reason for the log
     * @param string $context        Where it was found
     * @param int    $status         HTTP status (403 default; 429 for rate-limit)
     * @param string $client_message Generic message shown to the user (optional)
     */
    private static function block_request( $reason, $context = 'Value', int $status = 403, string $client_message = '' ) {
        self::log_threat( $reason, $context );
        self::send_error(
            $client_message !== '' ? $client_message : __( 'Your input contains disallowed content. Please revise it and try again.', 'romsfun' ),
            $status
        );
    }

    /**
     * Route a blacklist match through the active mode: hard-block dies (403 JSON),
     * soft mode logs and returns so the request continues.
     *
     * @param string $reason  Detected pattern label
     * @param string $context 'Key' or 'Value'
     */
    private static function flag_threat( string $reason, string $context ): void {
        if ( self::hard_block() ) {
            self::block_request( $reason, $context );
        } else {
            self::log_threat( $reason, $context );
        }
    }

    /**
     * Get the real client IP.
     *
     * CF-Connecting-IP / X-Forwarded-For are attacker-controlled headers unless the
     * request actually arrived through a trusted proxy. We therefore trust
     * CF-Connecting-IP ONLY when REMOTE_ADDR is inside Cloudflare's published ranges;
     * otherwise we use REMOTE_ADDR. This prevents IP spoofing that would bypass the
     * rate limiter or poison the security log.
     *
     * @return string A validated IP, or 'unknown'.
     */
    public static function get_client_ip(): string {
        $remote = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) $_SERVER['REMOTE_ADDR'] ) : '';

        if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] )
            && $remote !== '' && self::ip_in_ranges( $remote, self::cloudflare_ranges() ) ) {
            $cf = trim( (string) $_SERVER['HTTP_CF_CONNECTING_IP'] );
            if ( filter_var( $cf, FILTER_VALIDATE_IP ) ) {
                return $cf;
            }
        }

        return filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : 'unknown';
    }

    /**
     * Cloudflare edge IP ranges (cloudflare.com/ips). Override via the
     * `rf_cloudflare_ip_ranges` filter if the list changes.
     *
     * @return string[] CIDR ranges (IPv4 + IPv6).
     */
    private static function cloudflare_ranges(): array {
        static $ranges = null;
        if ( $ranges === null ) {
            $ranges = [
                // IPv4
                '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
                '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
                '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
                '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
                // IPv6
                '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
                '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
            ];
            if ( function_exists( 'apply_filters' ) ) {
                $ranges = apply_filters( 'rf_cloudflare_ip_ranges', $ranges );
            }
        }
        return $ranges;
    }

    /**
     * Whether an IP (v4 or v6) falls inside any of the given CIDR ranges.
     */
    private static function ip_in_ranges( string $ip, array $ranges ): bool {
        $ip_bin = @inet_pton( $ip );
        if ( $ip_bin === false ) {
            return false;
        }
        foreach ( $ranges as $cidr ) {
            if ( strpos( $cidr, '/' ) === false ) {
                continue;
            }
            list( $subnet, $bits ) = explode( '/', $cidr, 2 );
            $subnet_bin = @inet_pton( $subnet );
            if ( $subnet_bin === false || strlen( $subnet_bin ) !== strlen( $ip_bin ) ) {
                continue; // v4/v6 mismatch or bad subnet
            }
            $bits  = (int) $bits;
            $bytes = intdiv( $bits, 8 );
            $rem   = $bits % 8;
            if ( $bytes > 0 && strncmp( $ip_bin, $subnet_bin, $bytes ) !== 0 ) {
                continue;
            }
            if ( $rem === 0 ) {
                return true;
            }
            $mask = chr( ( 0xff << ( 8 - $rem ) ) & 0xff );
            if ( ( $ip_bin[ $bytes ] & $mask ) === ( $subnet_bin[ $bytes ] & $mask ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Emit baseline security response headers.
     *
     * Note: X-XSS-Protection is intentionally set to 0 — the legacy browser XSS
     * auditor it enabled is deprecated and can itself introduce vulnerabilities;
     * modern defense is CSP + output escaping. Content-Type is NOT set here (that
     * belongs to the response emitter, e.g. wp_send_json_* sets application/json).
     */
    public static function set_security_headers(): void
    {
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 0');
        }
    }

}
