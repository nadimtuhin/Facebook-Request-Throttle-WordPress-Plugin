<?php
/**
 * Plugin Name: Facebook Request Throttle
 * Description: Limits the request frequency from Facebook's web crawler.
 * Version:     2.4
 * Author:      Nadim Tuhin
 * Author URI:  https://nadimtuhin.com
 * License:     MIT
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( "We're sorry, but you can not directly access this file." );
}

// Number of seconds permitted between each hit from meta-externalagent / facebookexternalhit.
define( 'FACEBOOK_REQUEST_THROTTLE', 60.0 );

// Max log entries to keep.
define( 'FACEBOOK_REQUEST_THROTTLE_LOG_LIMIT', 100 );

/**
 * Check if the request is from Facebook's web crawler.
 *
 * @return bool
 */
function nt_is_request_from_facebook() {
	$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	return ! empty( $user_agent ) && (
		false !== strpos( $user_agent, 'meta-externalagent' ) ||
		false !== strpos( $user_agent, 'facebookexternalhit' )
	);
}

/**
 * Check if the request is for an image file.
 *
 * @return bool
 */
function nt_is_image_request() {
	$request_path  = parse_url( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$file_extension = strtolower( pathinfo( $request_path, PATHINFO_EXTENSION ) );
	return in_array( $file_extension, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true );
}

/**
 * Get the last access time of Facebook's web crawler.
 *
 * @return mixed Transient value or false if not set.
 */
function nt_get_last_access_time() {
	return get_transient( 'nt_facebook_last_access_time' );
}

/**
 * Set the last access time of Facebook's web crawler.
 *
 * @param float $current_time Unix timestamp with microseconds.
 * @return bool
 */
function nt_set_last_access_time( $current_time ) {
	return set_transient(
		'nt_facebook_last_access_time',
		$current_time,
		FACEBOOK_REQUEST_THROTTLE + 1
	);
}

/**
 * Append an entry to the admin-visible hit log (capped at LOG_LIMIT entries).
 *
 * @param string $status 'allowed' or 'throttled'.
 */
function nt_log( $status ) {
	$log = get_option( 'nt_facebook_throttle_log', array() );
	array_unshift(
		$log,
		array(
			'time'   => current_time( 'mysql' ),
			'ip'     => isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '',           // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'ua'     => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '',   // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'uri'    => isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '',           // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'status' => $status,
		)
	);
	$log = array_slice( $log, 0, FACEBOOK_REQUEST_THROTTLE_LOG_LIMIT );
	update_option( 'nt_facebook_throttle_log', $log, false );
}

/**
 * Throttle Facebook crawler requests to prevent overload.
 */
function nt_facebook_request_throttle() {
	if ( nt_is_image_request() ) {
		return;
	}

	$last_access_time = nt_get_last_access_time();
	$current_time     = microtime( true );

	if ( $last_access_time && ( $current_time - $last_access_time < FACEBOOK_REQUEST_THROTTLE ) ) {
		nt_log( 'throttled' );
		nt_send_throttle_response();
		// nt_send_throttle_response() calls wp_die() — execution stops here.
	}

	nt_set_last_access_time( $current_time );
	nt_log( 'allowed' );
}

/**
 * Send throttle response with appropriate headers.
 */
function nt_send_throttle_response() {
	status_header( 429 );
	header( 'Retry-After: 60' );
	wp_die(
		'Too Many Requests',
		'Too Many Requests',
		array( 'response' => 429 )
	);
}

// ── Admin log page ────────────────────────────────────────────────────────────

/**
 * Register the settings page under Settings menu.
 */
function nt_admin_menu() {
	add_options_page(
		'Facebook Throttle Log',
		'FB Throttle Log',
		'manage_options',
		'nt-facebook-throttle-log',
		'nt_render_log_page'
	);
}
add_action( 'admin_menu', 'nt_admin_menu' );

/**
 * Render the admin log page.
 */
function nt_render_log_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['nt_clear_log'] ) && check_admin_referer( 'nt_clear_log' ) ) {
		delete_option( 'nt_facebook_throttle_log' );
		echo '<div class="updated"><p>Log cleared.</p></div>';
	}

	$log = get_option( 'nt_facebook_throttle_log', array() );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Facebook Request Throttle — Hit Log', 'facebook-request-throttle' ); ?></h1>
		<p>
			<?php
			printf(
				/* translators: %d: max number of log entries */
				esc_html__( 'Shows the last %d requests from Facebook crawlers (facebookexternalhit / meta-externalagent).', 'facebook-request-throttle' ),
				(int) FACEBOOK_REQUEST_THROTTLE_LOG_LIMIT
			);
			?>
		</p>
		<form method="post">
			<?php wp_nonce_field( 'nt_clear_log' ); ?>
			<input type="submit" name="nt_clear_log" class="button" value="<?php esc_attr_e( 'Clear Log', 'facebook-request-throttle' ); ?>">
		</form>
		<br>
		<?php if ( empty( $log ) ) : ?>
			<p><?php esc_html_e( 'No hits recorded yet.', 'facebook-request-throttle' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'facebook-request-throttle' ); ?></th>
						<th><?php esc_html_e( 'Status', 'facebook-request-throttle' ); ?></th>
						<th><?php esc_html_e( 'IP', 'facebook-request-throttle' ); ?></th>
						<th><?php esc_html_e( 'URI', 'facebook-request-throttle' ); ?></th>
						<th><?php esc_html_e( 'User Agent', 'facebook-request-throttle' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $log as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( $entry['time'] ); ?></td>
							<td>
								<?php if ( 'throttled' === $entry['status'] ) : ?>
									<span style="color:red;font-weight:bold"><?php esc_html_e( 'throttled (429)', 'facebook-request-throttle' ); ?></span>
								<?php else : ?>
									<span style="color:green"><?php esc_html_e( 'allowed (200)', 'facebook-request-throttle' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $entry['ip'] ); ?></td>
							<td><?php echo esc_html( $entry['uri'] ); ?></td>
							<td><?php echo esc_html( $entry['ua'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

// ── Main ──────────────────────────────────────────────────────────────────────

if ( nt_is_request_from_facebook() ) {
	nt_facebook_request_throttle();
}
