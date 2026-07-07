<?php
/**
 * Test bootstrap: stub all WP functions the plugin calls.
 * Loaded once by PHPUnit before any test class.
 */

// Global state — reset per test via WPStubs::reset()
$GLOBALS['_nt_transients']       = [];
$GLOBALS['_nt_transient_expiry'] = [];
$GLOBALS['_nt_options']          = [];
$GLOBALS['_nt_cache']            = [];
$GLOBALS['_nt_ext_cache']        = false;
$GLOBALS['_nt_die_called']       = false;
$GLOBALS['_nt_die_status']       = null;

// Fake clock for TTL checks — tests set $GLOBALS['_nt_test_now'] to control it.
function nt_stub_now(): float
{
    return $GLOBALS['_nt_test_now'] ?? microtime(true);
}

// TTL-aware: direct $GLOBALS['_nt_transients'][$k] = $v writes have no expiry entry → never expire.
function get_transient(string $k): mixed
{
    if (!array_key_exists($k, $GLOBALS['_nt_transients'])) {
        return false;
    }
    $exp = $GLOBALS['_nt_transient_expiry'][$k] ?? null;
    if ($exp !== null && nt_stub_now() >= $exp) {
        unset($GLOBALS['_nt_transients'][$k], $GLOBALS['_nt_transient_expiry'][$k]);
        return false;
    }
    return $GLOBALS['_nt_transients'][$k];
}

function set_transient(string $k, mixed $v, int $ttl): bool
{
    $GLOBALS['_nt_transients'][$k]       = $v;
    $GLOBALS['_nt_transient_expiry'][$k] = $ttl > 0 ? nt_stub_now() + $ttl : null;
    return true;
}

function delete_transient(string $k): bool
{
    unset($GLOBALS['_nt_transients'][$k], $GLOBALS['_nt_transient_expiry'][$k]);
    return true;
}

function wp_using_ext_object_cache(): bool
{
    return $GLOBALS['_nt_ext_cache'] ?? false;
}

// add-if-absent, like a real persistent object cache drop-in.
function wp_cache_add(string $key, mixed $value, string $group = '', int $ttl = 0): bool
{
    $k     = $group . ':' . $key;
    $entry = $GLOBALS['_nt_cache'][$k] ?? null;
    if ($entry !== null && ($entry['exp'] === null || nt_stub_now() < $entry['exp'])) {
        return false;
    }
    $GLOBALS['_nt_cache'][$k] = ['v' => $value, 'exp' => $ttl > 0 ? nt_stub_now() + $ttl : null];
    return true;
}

function get_option(string $k, mixed $default = []): mixed
{
    return $GLOBALS['_nt_options'][$k] ?? $default;
}

function update_option(string $k, mixed $v, bool $autoload = true): bool
{
    $GLOBALS['_nt_options'][$k] = $v;
    return true;
}

function delete_option(string $k): bool
{
    unset($GLOBALS['_nt_options'][$k]);
    return true;
}

function current_time(string $format): string
{
    return date('Y-m-d H:i:s');
}

function current_user_can(string $cap): bool { return true; }
function add_action(string $h, callable $cb): void {}
function add_filter(string $h, callable $cb, int $priority = 10, int $args = 1): void {}
function add_options_page(): void {}
function wp_nonce_field(): void {}
function check_admin_referer(string $action): bool { return true; }
function esc_html(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }
function register_setting(string $group, string $option, array $args = []): void {}
function settings_fields(string $group): void {}
function submit_button(): void {}
function esc_attr(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_HTML401); }
function esc_html_e(string $s): void { echo htmlspecialchars($s, ENT_QUOTES); }
function esc_attr_e(string $s): void { echo htmlspecialchars($s, ENT_QUOTES | ENT_HTML401); }
function esc_html__(string $s): string { return $s; }
function printf_esc(string $fmt, mixed ...$args): void { printf($fmt, ...$args); }
function wp_parse_url(string $url, int $component = -1): mixed { return parse_url($url, $component); }
function wp_unslash(mixed $value): mixed { return is_string($value) ? stripslashes($value) : $value; }
function sanitize_text_field(string $str): string { return trim(strip_tags($str)); }
function trailingslashit(string $s): string { return rtrim($s, '/\\') . '/'; }

// $wp_filesystem spy — records move() calls for the TASK-05 upgrader_source_selection filter.
class NT_Test_WP_Filesystem_Spy
{
    public array $moves = [];
    public bool $moveReturn = true;

    public function move(string $from, string $to, bool $overwrite = false): bool
    {
        $this->moves[] = ['from' => $from, 'to' => $to, 'overwrite' => $overwrite];
        return $this->moveReturn;
    }
}
$GLOBALS['wp_filesystem'] = new NT_Test_WP_Filesystem_Spy();

// WP-CLI stubs — only defined when tests opt-in via $GLOBALS['_nt_test_wpcli']
if ( ! class_exists( 'WP_CLI' ) ) {
	class WP_CLI {
		public static array $lines   = [];
		public static array $success = [];
		public static function line( string $msg ): void    { self::$lines[]   = $msg; }
		public static function success( string $msg ): void { self::$success[] = $msg; }
		public static function add_command(): void {}
	}
}
if ( ! defined( 'WP_CLI' ) ) {
	define( 'WP_CLI', true );
}

function status_header(int $code): void
{
    $GLOBALS['_nt_die_status'] = $code;
}

function wp_die(string $msg, string $title, array $args): never
{
    $GLOBALS['_nt_die_called'] = true;
    $GLOBALS['_nt_die_status'] = $args['response'];
    throw new \RuntimeException("wp_die:{$args['response']}");
}

// Load plugin (constants guard against double-define)
define('ABSPATH', true);

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

// HTTP stubs — tests override $GLOBALS['_nt_http_response'] to control the response.
function wp_remote_get(string $url, array $args = []): array|WP_Error
{
    return $GLOBALS['_nt_http_response'] ?? new WP_Error('http_request_failed', 'No stub set');
}
function wp_remote_retrieve_response_code(array|WP_Error $r): int
{
    return is_array($r) ? ($r['response']['code'] ?? 0) : 0;
}
function wp_remote_retrieve_body(array|WP_Error $r): string
{
    return is_array($r) ? ($r['body'] ?? '') : '';
}
function is_wp_error(mixed $v): bool
{
    return $v instanceof WP_Error;
}

class WP_Error
{
    public function __construct(public string $code = '', public string $message = '') {}
}

function esc_url(string $url): string { return htmlspecialchars($url, ENT_QUOTES); }
function wp_kses_post(string $s): string { return $s; }
function plugin_basename(string $file): string { return basename(dirname($file)) . '/' . basename($file); }

require_once __DIR__ . '/../facebook-request-throttle.php';
