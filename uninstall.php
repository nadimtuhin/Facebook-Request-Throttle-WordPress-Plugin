<?php
/**
 * Fired when the plugin is uninstalled (deleted via Plugins → Delete).
 *
 * Removes all options and transients created by the plugin so the database
 * is left clean after uninstall.
 *
 * @package FacebookRequestThrottle
 */

// Exit if not called by WordPress uninstall routine.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

// Remove stored options.
delete_option( 'nt_throttle_duration' );
delete_option( 'nt_facebook_throttle_log' );

// Remove transients.
delete_transient( 'nt_facebook_last_access_time' );
delete_transient( 'nt_github_latest_version' );
delete_transient( 'nt_github_release_data' );
