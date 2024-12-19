<?php
/**
 * Deactivation functionality
 *
 * @package SocialBotThrottle
 * @since   3.1-rc
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Schedule weekly log cleanup.
register_activation_hook( __FILE__, 'nt_sbrt_schedule_log_cleanup' );

/**
 * Schedule log cleanup on plugin activation.
 *
 * @since 3.1-rc
 */
function nt_sbrt_schedule_log_cleanup() {
    if ( ! wp_next_scheduled( 'nt_sbrt_cleanup_logs' ) ) {
        wp_schedule_event( time(), 'weekly', 'nt_sbrt_cleanup_logs' );
    }
}

// Cleanup logs weekly.
add_action( 'nt_sbrt_cleanup_logs', 'nt_sbrt_do_log_cleanup' );

/**
 * Clean up old logs.
 *
 * @since 3.1-rc
 */
function nt_sbrt_do_log_cleanup() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sbrt_throttle_log';
    $wpdb->query( $wpdb->prepare( 
        "DELETE FROM %i WHERE timestamp < DATE_SUB(NOW(), INTERVAL 1 WEEK)",
        $table_name 
    ) );
}

// Create log table on plugin activation.
register_activation_hook( __FILE__, 'nt_sbrt_create_log_table' );

/**
 * Create log table on plugin activation.
 *
 * @since 3.1-rc
 * @return bool True on success, false on failure.
 */
function nt_sbrt_create_log_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'sbrt_throttle_log';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        bot_name varchar(100) NOT NULL,
        request_uri text NOT NULL,
        user_agent text NOT NULL,
        status varchar(10) NOT NULL DEFAULT 'denied',
        timestamp datetime NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $result = dbDelta( $sql );
    
    if ( is_wp_error( $result ) ) {
        error_log( sprintf( 
            /* translators: %s: Error message */
            esc_html__( 'SBRT: Failed to create log table - %s', 'social-bot-throttle' ),
            $result->get_error_message() 
        ) );
        return false;
    }
    
    return true;
}

// Drop table on plugin deactivation.
register_deactivation_hook( __FILE__, 'nt_sbrt_drop_log_table' );

/**
 * Drop log table on plugin deactivation.
 *
 * @since 3.1-rc
 */
function nt_sbrt_drop_log_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sbrt_throttle_log';
    $wpdb->query( $wpdb->prepare( 
        "DROP TABLE IF EXISTS %i",
        $table_name 
    ) );
}