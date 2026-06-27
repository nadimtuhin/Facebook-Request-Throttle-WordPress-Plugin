<?php
/**
 * Plugin Name: Facebook Request Throttle
 * Description: Limits the request frequency from Facebook's web crawler and other configurable user agents.
 * Version:     2.6
 * Author:      Nadim Tuhin
 * Author URI:  https://nadimtuhin.com
 * License:     MIT
 *
 * @package FacebookRequestThrottle
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( "We're sorry, but you can not directly access this file." );
}

// Fallback constant — define this in wp-config.php to override the dashboard setting.
if ( ! defined( 'FACEBOOK_REQUEST_THROTTLE' ) ) {
	define( 'FACEBOOK_REQUEST_THROTTLE', 60.0 );
}

// Max log entries to keep.
if ( ! defined( 'FACEBOOK_REQUEST_THROTTLE_LOG_LIMIT' ) ) {
	define( 'FACEBOOK_REQUEST_THROTTLE_LOG_LIMIT', 100 );
}

/**
 * User agents to throttle. Add additional substrings here.
 *
 * @var string[]
 */
$nt_user_agents_to_throttle = array(
	'meta-externalagent',
	'facebookexternalhit',
);

/**
 * Get the configured throttle duration in seconds.
 *
 * Priority: dashboard setting → FACEBOOK_REQUEST_THROTTLE constant → 60.
 *
 * @return float
 */
function nt_get_throttle_duration() {
	$saved = get_option( 'nt_throttle_duration', null );
	if ( null !== $saved ) {
		return (float) $saved;
	}
	return (float) FACEBOOK_REQUEST_THROTTLE;
}

/**
 * Check if the current request is from any of the configured user agents.
 *
 * @return bool
 */
function nt_is_request_from_facebook() {
	global $nt_user_agents_to_throttle;
	if ( empty( $nt_user_agents_to_throttle ) ) {
		$nt_user_agents_to_throttle = array( 'meta-externalagent', 'facebookexternalhit' );
	}
	$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( empty( $user_agent ) ) {
		return false;
	}
	foreach ( $nt_user_agents_to_throttle as $agent ) {
		if ( false !== strpos( $user_agent, $agent ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Check if the request is for an image file.
 *
 * @return bool
 */
function nt_is_image_request() {
	$request_path   = wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$file_extension = strtolower( pathinfo( $request_path, PATHINFO_EXTENSION ) );
	return in_array( $file_extension, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true );
}

/**
 * Get the last access time of the throttled crawler.
 *
 * @return mixed Transient value or false if not set.
 */
function nt_get_last_access_time() {
	return get_transient( 'nt_facebook_last_access_time' );
}

/**
 * Set the last access time of the throttled crawler.
 *
 * @param float $current_time Unix timestamp with microseconds.
 * @return bool
 */
function nt_set_last_access_time( $current_time ) {
	return set_transient(
		'nt_facebook_last_access_time',
		$current_time,
		nt_get_throttle_duration() + 1
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
			'ip'     => isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '',          // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'ua'     => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '',  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'uri'    => isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '',          // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'status' => $status,
		)
	);
	$log = array_slice( $log, 0, FACEBOOK_REQUEST_THROTTLE_LOG_LIMIT );
	update_option( 'nt_facebook_throttle_log', $log, false );
}

/**
 * Throttle crawler requests to prevent overload.
 */
function nt_facebook_request_throttle() {
	if ( nt_is_image_request() ) {
		return;
	}

	$last_access_time = nt_get_last_access_time();
	$current_time     = microtime( true );
	$throttle         = nt_get_throttle_duration();

	if ( $last_access_time && ( $current_time - $last_access_time < $throttle ) ) {
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
	$retry_after = (int) nt_get_throttle_duration();
	status_header( 429 );
	header( 'Retry-After: ' . $retry_after );
	wp_die(
		'Too Many Requests',
		'Too Many Requests',
		array( 'response' => 429 )
	);
}

// ── Admin settings + log page ─────────────────────────────────────────────────

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
 * Register plugin settings.
 */
function nt_register_settings() {
	register_setting(
		'nt_throttle_settings',
		'nt_throttle_duration',
		array(
			'type'              => 'number',
			'default'           => 60,
			'sanitize_callback' => 'nt_sanitize_throttle_duration',
		)
	);
}
add_action( 'admin_init', 'nt_register_settings' );

/**
 * Sanitize the throttle duration setting.
 *
 * @param mixed $value Raw input value.
 * @return int Clamped integer between 1 and 86400.
 */
function nt_sanitize_throttle_duration( $value ) {
	$value = (int) $value;
	return max( 1, min( 86400, $value ) );
}

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

	$log      = get_option( 'nt_facebook_throttle_log', array() );
	$throttle = (int) nt_get_throttle_duration();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Facebook Request Throttle', 'facebook-request-throttle' ); ?></h1>

		<h2><?php esc_html_e( 'Settings', 'facebook-request-throttle' ); ?></h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'nt_throttle_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="nt_throttle_duration">
							<?php esc_html_e( 'Throttle duration (seconds)', 'facebook-request-throttle' ); ?>
						</label>
					</th>
					<td>
						<input
							type="number"
							id="nt_throttle_duration"
							name="nt_throttle_duration"
							value="<?php echo esc_attr( $throttle ); ?>"
							min="1"
							max="86400"
							class="small-text"
						>
						<p class="description">
							<?php esc_html_e( 'Minimum seconds between allowed hits from the same crawler. Default: 60. You can also define FACEBOOK_REQUEST_THROTTLE in wp-config.php — the dashboard value takes priority.', 'facebook-request-throttle' ); ?>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<h2><?php esc_html_e( 'Hit Log', 'facebook-request-throttle' ); ?></h2>
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

// ── WP-CLI ────────────────────────────────────────────────────────────────────

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * WP-CLI commands for Facebook Request Throttle.
	 *
	 * Single-file plugin — class intentionally co-located with functions.
	 *
	 * @package FacebookRequestThrottle
	 */
	// phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- single-file plugin

	/**
	 * WP-CLI command handler.
	 *
	 * @package FacebookRequestThrottle
	 */
	class NT_Facebook_Throttle_CLI {

		/**
		 * Show current configuration and log summary.
		 *
		 * ## EXAMPLES
		 *
		 *   wp fb-throttle status
		 *
		 * @when after_wp_load
		 */
		public function status() {
			$duration  = (int) nt_get_throttle_duration();
			$log       = get_option( 'nt_facebook_throttle_log', array() );
			$total     = count( $log );
			$throttled = count( array_filter( $log, fn( $e ) => 'throttled' === $e['status'] ) );
			$allowed   = $total - $throttled;
			$last      = $total > 0 ? $log[0]['time'] : 'none';

			WP_CLI::line( 'Throttle duration : ' . $duration . 's' );
			WP_CLI::line( 'Log entries       : ' . $total . ' (allowed: ' . $allowed . ', throttled: ' . $throttled . ')' );
			WP_CLI::line( 'Last hit          : ' . $last );
		}

		/**
		 * Display the hit log.
		 *
		 * ## OPTIONS
		 *
		 * [--limit=<n>]
		 * : Number of entries to show. Default: 20.
		 *
		 * [--status=<status>]
		 * : Filter by status: allowed or throttled.
		 *
		 * [--format=<format>]
		 * : Output format: table, csv, json, yaml. Default: table.
		 *
		 * ## EXAMPLES
		 *
		 *   wp fb-throttle log
		 *   wp fb-throttle log --limit=50 --status=throttled --format=json
		 *
		 * @when after_wp_load
		 * @param array $args       Positional args.
		 * @param array $assoc_args Named args.
		 */
		public function log( $args, $assoc_args ) {
			$limit  = (int) ( $assoc_args['limit'] ?? 20 );
			$status = $assoc_args['status'] ?? '';
			$format = $assoc_args['format'] ?? 'table';

			$log = get_option( 'nt_facebook_throttle_log', array() );

			if ( $status ) {
				$log = array_values( array_filter( $log, fn( $e ) => $e['status'] === $status ) );
			}

			$log = array_slice( $log, 0, $limit );

			if ( empty( $log ) ) {
				WP_CLI::line( 'No log entries found.' );
				return;
			}

			WP_CLI\Utils\format_items( $format, $log, array( 'time', 'status', 'ip', 'uri', 'ua' ) );
		}

		/**
		 * Clear the hit log.
		 *
		 * ## EXAMPLES
		 *
		 *   wp fb-throttle clear
		 *
		 * @when after_wp_load
		 */
		public function clear() {
			delete_option( 'nt_facebook_throttle_log' );
			WP_CLI::success( 'Log cleared.' );
		}

		/**
		 * Get or set the throttle duration.
		 *
		 * ## OPTIONS
		 *
		 * [<seconds>]
		 * : New throttle duration in seconds (1–86400). Omit to read current value.
		 *
		 * ## EXAMPLES
		 *
		 *   wp fb-throttle duration
		 *   wp fb-throttle duration 120
		 *
		 * @when after_wp_load
		 * @param array $args Positional args.
		 */
		public function duration( $args ) {
			if ( empty( $args ) ) {
				WP_CLI::line( (int) nt_get_throttle_duration() . 's' );
				return;
			}

			$value = nt_sanitize_throttle_duration( $args[0] );
			update_option( 'nt_throttle_duration', $value );
			WP_CLI::success( 'Throttle duration set to ' . $value . 's.' );
		}
	}

	WP_CLI::add_command( 'fb-throttle', 'NT_Facebook_Throttle_CLI' );
}
