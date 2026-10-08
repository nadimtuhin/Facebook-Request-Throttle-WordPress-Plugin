<?php
/**
 * Plugin Name: Facebook Request Throttle
 * Plugin URI:  https://github.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin
 * Description: Limits the request frequency from Facebook's web crawler and other configurable user agents.
 * Version:     3.1
 * Author:      Nadim Tuhin
 * Author URI:  https://nadimtuhin.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: facebook-request-throttle
 * Domain Path: /languages
 *
 * @package FacebookRequestThrottle
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( "We're sorry, but you can not directly access this file." );
}

if ( ! defined( 'NT_PLUGIN_VERSION' ) ) {
	define( 'NT_PLUGIN_VERSION', '3.1' );
}

// Canonical prefixed constants.
// If the user already defined the legacy name in wp-config.php, inherit that value.
if ( ! defined( 'NT_FACEBOOK_REQUEST_THROTTLE' ) ) {
	define( 'NT_FACEBOOK_REQUEST_THROTTLE', defined( 'FACEBOOK_REQUEST_THROTTLE' ) ? FACEBOOK_REQUEST_THROTTLE : 60.0 );
}

// Max log entries to keep.
if ( ! defined( 'NT_FACEBOOK_REQUEST_THROTTLE_LOG_LIMIT' ) ) {
	define( 'NT_FACEBOOK_REQUEST_THROTTLE_LOG_LIMIT', defined( 'FACEBOOK_REQUEST_THROTTLE_LOG_LIMIT' ) ? FACEBOOK_REQUEST_THROTTLE_LOG_LIMIT : 100 );
}

// Legacy aliases so code using the old unprefixed names keeps working.
if ( ! defined( 'FACEBOOK_REQUEST_THROTTLE' ) ) {
	define( 'FACEBOOK_REQUEST_THROTTLE', NT_FACEBOOK_REQUEST_THROTTLE );
}
if ( ! defined( 'FACEBOOK_REQUEST_THROTTLE_LOG_LIMIT' ) ) {
	define( 'FACEBOOK_REQUEST_THROTTLE_LOG_LIMIT', NT_FACEBOOK_REQUEST_THROTTLE_LOG_LIMIT );
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
 * Priority: dashboard setting → NT_FACEBOOK_REQUEST_THROTTLE constant → 60.
 *
 * @return float
 */
function nt_get_throttle_duration() {
	$saved = get_option( 'nt_throttle_duration', null );
	if ( null !== $saved ) {
		return (float) $saved;
	}
	return (float) NT_FACEBOOK_REQUEST_THROTTLE;
}

/**
 * Check if the current request is from any of the configured user agents.
 *
 * @return string|false The matched agent substring (a truthy string), or false.
 */
function nt_is_request_from_facebook() {
	global $nt_user_agents_to_throttle;
	if ( empty( $nt_user_agents_to_throttle ) ) {
		$nt_user_agents_to_throttle = array( 'meta-externalagent', 'facebookexternalhit' );
	}
	$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	if ( empty( $user_agent ) ) {
		return false;
	}
	foreach ( $nt_user_agents_to_throttle as $agent ) {
		if ( false !== stripos( $user_agent, $agent ) ) {
			return $agent;
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
	$request_path   = wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '', PHP_URL_PATH );
	$file_extension = strtolower( pathinfo( $request_path, PATHINFO_EXTENSION ) );
	return in_array( $file_extension, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true );
}

/**
 * Build the per-agent transient/cache key.
 *
 * Sanitized to [a-z0-9]: custom UA substrings can contain spaces or parens,
 * which are invalid in memcached keys.
 *
 * @param string $agent Matched UA substring.
 * @return string
 */
function nt_throttle_transient_key( $agent ) {
	return 'nt_fb_last_access_' . strtolower( preg_replace( '/[^a-z0-9]/i', '', (string) $agent ) );
}

/**
 * Get the last access time for one throttled crawler bucket.
 *
 * @param string $agent Matched UA substring.
 * @return mixed Transient value or false if not set.
 */
function nt_get_last_access_time( $agent ) {
	return get_transient( nt_throttle_transient_key( $agent ) );
}

/**
 * Set the last access time for one throttled crawler bucket.
 *
 * @param float  $current_time Unix timestamp with microseconds.
 * @param string $agent        Matched UA substring.
 * @return bool
 */
function nt_set_last_access_time( $current_time, $agent ) {
	return set_transient(
		nt_throttle_transient_key( $agent ),
		$current_time,
		nt_get_throttle_duration() + 1
	);
}

/**
 * Claim the throttle slot for an agent bucket. True = request may proceed.
 *
 * @param string $agent        Matched UA substring.
 * @param float  $current_time Unix timestamp with microseconds.
 * @return bool
 */
function nt_acquire_throttle_slot( $agent, $current_time ) {
	$throttle = nt_get_throttle_duration();

	if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
		// Atomic only if the drop-in implements wp_cache_add as add-if-absent
		// (Redis NX, memcached add). The window here is ceil(duration) via key TTL;
		// the DB path below uses float timestamps.
		return wp_cache_add( nt_throttle_transient_key( $agent ), $current_time, 'transient', (int) ceil( $throttle ) );
	}

	// DB transients are read-then-write, so concurrent hits can let one extra request
	// through per window. A persistent object cache closes the gap.
	$last_access_time = nt_get_last_access_time( $agent );
	if ( $last_access_time && ( $current_time - $last_access_time < $throttle ) ) {
		return false;
	}
	nt_set_last_access_time( $current_time, $agent );
	return true;
}

/**
 * Record a crawler hit.
 *
 * Throttled hits get a full entry in the admin log (capped at LOG_LIMIT). Allowed
 * hits only bump a counter option (autoload off): logging each one would rewrite the
 * whole log array on every crawler request.
 *
 * @param string $status 'allowed' or 'throttled'.
 */
function nt_log( $status ) {
	if ( 'allowed' === $status ) {
		$count = (int) get_option( 'nt_facebook_throttle_allowed_count', 0 );
		update_option( 'nt_facebook_throttle_allowed_count', $count + 1, false );
		return;
	}

	$log = get_option( 'nt_facebook_throttle_log', array() );
	array_unshift(
		$log,
		array(
			'time'   => current_time( 'mysql' ),
			'ip'     => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			'ua'     => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'uri'    => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
			'status' => $status,
		)
	);
	$log = array_slice( $log, 0, NT_FACEBOOK_REQUEST_THROTTLE_LOG_LIMIT );
	update_option( 'nt_facebook_throttle_log', $log, false );
}

/**
 * Throttle crawler requests to prevent overload.
 *
 * @param string|null $agent Matched UA substring; null re-detects from the request.
 */
function nt_facebook_request_throttle( $agent = null ) {
	if ( nt_is_image_request() ) {
		return;
	}

	if ( null === $agent ) {
		$agent = nt_is_request_from_facebook();
	}
	if ( false === $agent ) {
		return;
	}

	if ( ! nt_acquire_throttle_slot( $agent, microtime( true ) ) ) {
		nt_log( 'throttled' );
		nt_send_throttle_response();
		// nt_send_throttle_response() calls wp_die(), so execution stops here.
	}

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
		delete_option( 'nt_facebook_throttle_allowed_count' );
		echo '<div class="updated"><p>Log cleared.</p></div>';
	}

	$log      = get_option( 'nt_facebook_throttle_log', array() );
	$allowed  = (int) get_option( 'nt_facebook_throttle_allowed_count', 0 );
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
							<?php esc_html_e( 'Minimum seconds between allowed hits from the same crawler. Default: 60. You can also define NT_FACEBOOK_REQUEST_THROTTLE (or the legacy FACEBOOK_REQUEST_THROTTLE) in wp-config.php. The dashboard value takes priority.', 'facebook-request-throttle' ); ?>
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
				/* translators: %d: allowed hit count */
				esc_html__( 'Allowed hits: %d', 'facebook-request-throttle' ),
				(int) $allowed
			);
			?>
		</p>
		<p>
			<?php
			printf(
				/* translators: %d: max number of log entries */
				esc_html__( 'Shows the last %d throttled (429) requests from Facebook crawlers (facebookexternalhit / meta-externalagent). Allowed hits are counted above, not logged individually.', 'facebook-request-throttle' ),
				(int) NT_FACEBOOK_REQUEST_THROTTLE_LOG_LIMIT
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

$nt_matched_agent = nt_is_request_from_facebook();
if ( false !== $nt_matched_agent ) {
	nt_facebook_request_throttle( $nt_matched_agent );
}

// ── WP-CLI ────────────────────────────────────────────────────────────────────

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * WP-CLI commands for Facebook Request Throttle.
	 *
	 * Single-file plugin, so the class lives next to the functions.
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
			$throttled = count( $log );
			$allowed   = (int) get_option( 'nt_facebook_throttle_allowed_count', 0 );
			$last      = $throttled > 0 ? $log[0]['time'] : 'none';

			WP_CLI::line( 'Throttle duration : ' . $duration . 's' );
			WP_CLI::line( 'Log entries       : ' . ( $allowed + $throttled ) . ' (allowed: ' . $allowed . ', throttled: ' . $throttled . ')' );
			WP_CLI::line( 'Last throttled hit: ' . $last );
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
			delete_option( 'nt_facebook_throttle_allowed_count' );
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

// ── GitHub Update Checker ──────────────────────────────────────────────────────

/**
 * Check GitHub releases for a newer version of this plugin.
 *
 * Thin wrapper over nt_get_github_release_data(), so the notice and the WP update
 * injector read the same cached release.
 *
 * @return string|false Latest version tag (e.g. "2.9") or false on failure.
 */
function nt_check_github_for_update() {
	$release = nt_get_github_release_data();
	return false === $release ? false : $release['version'];
}

/**
 * Show an admin notice when a newer GitHub release exists.
 */
function nt_maybe_show_update_notice() {
	if ( ! current_user_can( 'update_plugins' ) ) {
		return;
	}

	$latest = nt_check_github_for_update();
	if ( false === $latest ) {
		return;
	}

	if ( ! version_compare( $latest, NT_PLUGIN_VERSION, '>' ) ) {
		return;
	}

	// nt_maybe_show_major_update_notice() handles major bumps; skip to avoid two banners.
	if ( nt_is_major_upgrade( NT_PLUGIN_VERSION, $latest ) ) {
		return;
	}

	$url = 'https://github.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin/releases/latest';
	printf(
		'<div class="notice notice-warning"><p><strong>%s</strong> %s <a href="%s" target="_blank" rel="noopener noreferrer">%s &rarr;</a></p></div>',
		esc_html__( 'Facebook Request Throttle:', 'facebook-request-throttle' ),
		/* translators: %s: new version number */
		sprintf( esc_html__( 'Version %s is available on GitHub.', 'facebook-request-throttle' ), esc_html( $latest ) ),
		esc_url( $url ),
		esc_html__( 'Download update', 'facebook-request-throttle' )
	);
}
add_action( 'admin_notices', 'nt_maybe_show_update_notice' );
add_action( 'admin_notices', 'nt_maybe_show_major_update_notice' );

// ── WP Update Integration ──────────────────────────────────────────────────────

/**
 * Fetch release data from GitHub (tag_name; zipball_url when available).
 *
 * Single unauthenticated call to the GitHub API, cached 12 hours via one transient.
 * `zip_url` may be empty: a payload with only `tag_name` still yields a version for
 * the notices. Callers that install the package must check `zip_url` first.
 *
 * @return array{version: string, zip_url: string}|false
 */
function nt_get_github_release_data() {
	$cached = get_transient( 'nt_github_release_data' );
	if ( false !== $cached ) {
		return $cached;
	}

	$response = wp_remote_get(
		'https://api.github.com/repos/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin/releases/latest',
		array(
			'timeout'    => 5,
			'user-agent' => 'Facebook-Request-Throttle-WP-Plugin/' . NT_PLUGIN_VERSION,
		)
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return false;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $body['tag_name'] ) ) {
		return false;
	}

	$data = array(
		'version' => ltrim( $body['tag_name'], 'v' ),
		'zip_url' => $body['zipball_url'] ?? '',
	);

	set_transient( 'nt_github_release_data', $data, 12 * HOUR_IN_SECONDS );
	return $data;
}

/**
 * Rename the extracted GitHub-zip source folder to the currently installed plugin's
 * folder name before WP installs it.
 *
 * GitHub zipballs extract to `<owner>-<repo>-<sha>/`. Without the rename, WP installs
 * the update as a separate plugin and deactivates this one. The folder name comes from
 * the current install, so renamed installs work too.
 *
 * @param string|WP_Error $source        Path to the upgrade package source.
 * @param string          $remote_source Path to the upgrade package remote source.
 * @param WP_Upgrader     $upgrader      Upgrader instance.
 * @param array           $hook_extra    Extra arguments passed to hooked filters.
 * @return string|WP_Error
 */
function nt_maybe_rename_update_source( $source, $remote_source, $upgrader, $hook_extra ) {
	if ( is_wp_error( $source ) ) {
		return $source;
	}

	if ( ! isset( $hook_extra['plugin'] ) || plugin_basename( __FILE__ ) !== $hook_extra['plugin'] ) {
		return $source;
	}

	$install_dirname = dirname( plugin_basename( __FILE__ ) );
	$new_source      = trailingslashit( dirname( $source ) ) . $install_dirname;

	if ( trailingslashit( $source ) === trailingslashit( $new_source ) ) {
		return $source;
	}

	global $wp_filesystem;
	if ( ! $wp_filesystem->move( $source, $new_source, true ) ) {
		return new WP_Error( 'nt_rename_failed', esc_html__( 'Facebook Request Throttle: could not rename the update package to the installed plugin folder.', 'facebook-request-throttle' ) );
	}

	return trailingslashit( $new_source );
}
add_filter( 'upgrader_source_selection', 'nt_maybe_rename_update_source', 10, 4 );

/**
 * Determine if a version bump is a major upgrade (different major version).
 *
 * @param string $from Current version.
 * @param string $to   Latest version.
 * @return bool True if major version changed.
 */
function nt_is_major_upgrade( $from, $to ) {
	$from_major = (int) explode( '.', $from )[0];
	$to_major   = (int) explode( '.', $to )[0];
	return $to_major > $from_major;
}

/**
 * Inject GitHub release into WP's plugin update transient.
 *
 * @param object $transient WP update transient.
 * @return object Modified transient.
 */
function nt_inject_plugin_update( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	$release = nt_get_github_release_data();
	if ( false === $release || empty( $release['zip_url'] ) ) {
		return $transient;
	}

	if ( ! version_compare( $release['version'], NT_PLUGIN_VERSION, '>' ) ) {
		return $transient;
	}

	$plugin_file = plugin_basename( __FILE__ );

	$transient->response[ $plugin_file ] = (object) array(
		'slug'        => 'facebook-request-throttle',
		'plugin'      => $plugin_file,
		'new_version' => $release['version'],
		'url'         => 'https://github.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin',
		'package'     => $release['zip_url'],
	);

	return $transient;
}
add_filter( 'pre_set_site_transient_update_plugins', 'nt_inject_plugin_update' );

/**
 * Auto-update minor/patch bumps; require manual tap for major version changes.
 *
 * @param bool|null $update Whether to auto-update.
 * @param object    $item   Update item.
 * @return bool|null
 */
function nt_auto_update_policy( $update, $item ) {
	if ( isset( $item->slug ) && 'facebook-request-throttle' === $item->slug ) {
		return ! nt_is_major_upgrade( NT_PLUGIN_VERSION, $item->new_version );
	}
	return $update;
}
add_filter( 'auto_update_plugin', 'nt_auto_update_policy', 10, 2 );

/**
 * Show admin notice only for major upgrades (minors auto-update silently).
 */
function nt_maybe_show_major_update_notice() {
	if ( ! current_user_can( 'update_plugins' ) ) {
		return;
	}

	$release = nt_get_github_release_data();
	if ( false === $release ) {
		return;
	}

	if ( ! version_compare( $release['version'], NT_PLUGIN_VERSION, '>' ) ) {
		return;
	}

	if ( ! nt_is_major_upgrade( NT_PLUGIN_VERSION, $release['version'] ) ) {
		return;
	}

	$url = 'https://github.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin/releases/latest';
	printf(
		'<div class="notice notice-warning"><p><strong>%s</strong> %s <a href="%s" target="_blank" rel="noopener noreferrer">%s &rarr;</a></p></div>',
		esc_html__( 'Facebook Request Throttle:', 'facebook-request-throttle' ),
		sprintf(
			/* translators: %s: new version number */
			esc_html__( 'Major update %s is available. Click Update on the Plugins screen.', 'facebook-request-throttle' ),
			esc_html( $release['version'] )
		),
		esc_url( $url ),
		esc_html__( 'View release notes', 'facebook-request-throttle' )
	);
}


