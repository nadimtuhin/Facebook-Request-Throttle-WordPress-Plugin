<?php
/**
 * Smoke harness router for `php -S` — loads the real plugin file behind minimal WP stubs.
 * Transients are file-backed (NT_SMOKE_STORE env) so throttle state survives across requests.
 * This is proof infrastructure, not shipped code.
 */

declare(strict_types=1);

define( 'ABSPATH', true );

function nt_smoke_store_path(): string {
	return getenv( 'NT_SMOKE_STORE' ) ?: sys_get_temp_dir() . '/nt-smoke-store.json';
}

function nt_smoke_store(): array {
	$raw = @file_get_contents( nt_smoke_store_path() );
	return $raw ? (array) json_decode( $raw, true ) : array();
}

function nt_smoke_store_write( array $s ): void {
	file_put_contents( nt_smoke_store_path(), json_encode( $s ), LOCK_EX );
}

function get_transient( string $k ) {
	$s = nt_smoke_store();
	if ( ! isset( $s[ $k ] ) ) {
		return false;
	}
	if ( null !== $s[ $k ]['exp'] && microtime( true ) >= $s[ $k ]['exp'] ) {
		return false;
	}
	return $s[ $k ]['v'];
}

function set_transient( string $k, $v, int $ttl ): bool {
	$s         = nt_smoke_store();
	$s[ $k ]   = array( 'v' => $v, 'exp' => $ttl > 0 ? microtime( true ) + $ttl : null );
	nt_smoke_store_write( $s );
	return true;
}

function delete_transient( string $k ): bool {
	$s = nt_smoke_store();
	unset( $s[ $k ] );
	nt_smoke_store_write( $s );
	return true;
}

// Options in-memory per request — nt_log writes are not asserted here.
function get_option( string $k, $default = array() ) {
	return $GLOBALS['_opts'][ $k ] ?? $default;
}
function update_option( string $k, $v, bool $autoload = true ): bool {
	$GLOBALS['_opts'][ $k ] = $v;
	return true;
}
function delete_option( string $k ): bool {
	unset( $GLOBALS['_opts'][ $k ] );
	return true;
}

function current_time( string $f ): string { return date( 'Y-m-d H:i:s' ); }
function sanitize_text_field( string $s ): string { return trim( strip_tags( $s ) ); }
function wp_unslash( $v ) { return is_string( $v ) ? stripslashes( $v ) : $v; }
function wp_parse_url( string $url, int $c = -1 ) { return parse_url( $url, $c ); }
function add_action( ...$a ): void {}
function add_filter( ...$a ): void {}
function status_header( int $code ): void { http_response_code( $code ); }

function wp_die( string $msg = '', string $title = '', array $args = array() ): void {
	echo $msg;
	exit;
}

// Loading the plugin runs its main flow: UA detection → throttle (429 exits inside wp_die).
require __DIR__ . '/../facebook-request-throttle.php';

echo "OK\n";
