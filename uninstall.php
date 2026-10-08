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

/**
 * Remove all options and transients created by the plugin for the current site.
 */
function nt_facebook_throttle_uninstall_site() {
	delete_option( 'nt_throttle_duration' );
	delete_option( 'nt_facebook_throttle_log' );
	delete_option( 'nt_facebook_throttle_allowed_count' );

	delete_transient( 'nt_facebook_last_access_time' );
	delete_transient( 'nt_github_latest_version' );
	delete_transient( 'nt_github_release_data' );
}

/**
 * Run the per-site cleanup on every site of a multisite network, or once on
 * a single-site install.
 *
 * `number => 0` fetches every site ID in one query (WP_Site_Query defaults to
 * 100, which would skip sites). All IDs are held in memory; fine below ~10k sites.
 */
function nt_facebook_throttle_uninstall_dispatch() {
	if ( is_multisite() ) {
		$site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			nt_facebook_throttle_uninstall_site();
			restore_current_blog();
		}
	} else {
		nt_facebook_throttle_uninstall_site();
	}
}

nt_facebook_throttle_uninstall_dispatch();
