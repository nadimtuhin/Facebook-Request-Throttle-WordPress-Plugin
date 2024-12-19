<?php


// Schedule weekly log cleanup
register_activation_hook(__FILE__, 'nt_sbrt_schedule_log_cleanup');
function nt_sbrt_schedule_log_cleanup() {
    if (!wp_next_scheduled('nt_sbrt_cleanup_logs')) {
        wp_schedule_event(time(), 'weekly', 'nt_sbrt_cleanup_logs');
    }
}

// Cleanup logs weekly
add_action('nt_sbrt_cleanup_logs', 'nt_sbrt_do_log_cleanup');
function nt_sbrt_do_log_cleanup() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sbrt_throttle_log';
    $wpdb->query("DELETE FROM $table_name WHERE timestamp < DATE_SUB(NOW(), INTERVAL 1 WEEK)");
}

// Create log table on plugin activation
register_activation_hook(__FILE__, 'nt_sbrt_create_log_table');

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
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Drop table on plugin deactivation
register_deactivation_hook(__FILE__, 'nt_sbrt_drop_log_table');

function nt_sbrt_drop_log_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sbrt_throttle_log';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}