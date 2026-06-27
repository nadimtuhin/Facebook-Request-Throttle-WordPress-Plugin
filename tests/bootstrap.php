<?php
/**
 * Test bootstrap: stub all WP functions the plugin calls.
 * Loaded once by PHPUnit before any test class.
 */

// Global state — reset per test via WPStubs::reset()
$GLOBALS['_nt_transients'] = [];
$GLOBALS['_nt_options']    = [];
$GLOBALS['_nt_die_called'] = false;
$GLOBALS['_nt_die_status'] = null;

function get_transient(string $k): mixed
{
    return $GLOBALS['_nt_transients'][$k] ?? false;
}

function set_transient(string $k, mixed $v, int $ttl): bool
{
    $GLOBALS['_nt_transients'][$k] = $v;
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
function add_options_page(): void {}
function wp_nonce_field(): void {}
function check_admin_referer(string $action): bool { return true; }
function esc_html(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }

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

require_once __DIR__ . '/../facebook-request-throttle.php';
