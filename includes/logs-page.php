<?php

// Add logs page to admin menu
add_action('admin_menu', 'nt_sbrt_add_logs_menu');

// Add logs page
function nt_sbrt_add_logs_menu() {
    add_submenu_page(
        'options-general.php',
        'Social Bot Throttle Logs', 
        'Bot Throttle Logs',
        'manage_options',
        'social-bot-throttle-logs',
        'nt_sbrt_logs_page'
    );
}

// Display logs page content
function nt_sbrt_logs_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'sbrt_throttle_log';

    // Handle delete all logs action
    if (isset($_POST['delete_all_logs']) && check_admin_referer('delete_all_logs')) {
        $wpdb->query("TRUNCATE TABLE $table_name");
        add_settings_error(
            'sbrt_logs',
            'logs_deleted',
            __('All logs have been deleted.'),
            'success'
        );
    }
    
    // Get logs with pagination
    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 15;
    $offset = ($page - 1) * $per_page;
    
    // Get statistics
    $total_items = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $allowed_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'allowed'");
    $denied_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'denied'");
    
    $total_pages = ceil($total_items / $per_page);
    
    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name ORDER BY timestamp DESC LIMIT %d OFFSET %d",
        $per_page,
        $offset
    ));
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php _e('Social Bot Throttle Logs'); ?></h1>
        
        <?php settings_errors('sbrt_logs'); ?>

        <div class="postbox">
            <div class="inside">
                <h2><?php _e('Statistics'); ?></h2>
                <div class="stats-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 15px 0;">
                    <div class="stat-box">
                        <h3><?php _e('Total Requests'); ?></h3>
                        <span class="stat-number"><?php echo number_format($total_items); ?></span>
                    </div>
                    <div class="stat-box">
                        <h3><?php _e('Allowed Requests'); ?></h3>
                        <span class="stat-number" style="color: #46b450;"><?php echo number_format($allowed_count); ?></span>
                    </div>
                    <div class="stat-box">
                        <h3><?php _e('Denied Requests'); ?></h3>
                        <span class="stat-number" style="color: #dc3232;"><?php echo number_format($denied_count); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="tablenav top">
            <div class="alignleft actions">
                <form method="post" style="display: inline;">
                    <?php wp_nonce_field('delete_all_logs'); ?>
                    <?php submit_button(__('Delete All Logs'), 'delete', 'delete_all_logs', false, array(
                        'onclick' => 'return confirm("' . esc_js(__('Are you sure you want to delete all logs? This cannot be undone.')) . '");'
                    )); ?>
                </form>
            </div>
        </div>
        
        <?php if (empty($logs)): ?>
            <div class="notice notice-info">
                <p><?php _e('No throttle logs found.'); ?></p>
            </div>
        <?php else: ?>
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php printf(
                                /* translators: %s: Number of items */
                                _n('%s item', '%s items', $total_items),
                                number_format_i18n($total_items)
                            ); ?>
                        </span>
                        <span class="pagination-links">
                            <?php
                            echo paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => __('&laquo;'),
                                'next_text' => __('&raquo;'),
                                'total' => $total_pages,
                                'current' => $page,
                                'add_args' => false,
                            ));
                            ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php _e('Bot'); ?></th>
                        <th scope="col"><?php _e('Request URI'); ?></th>
                        <th scope="col"><?php _e('User Agent'); ?></th>
                        <th scope="col"><?php _e('Status'); ?></th>
                        <th scope="col"><?php _e('Timestamp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html($log->bot_name); ?></td>
                            <td class="column-primary">
                                <strong><?php echo esc_html($log->request_uri); ?></strong>
                                <button type="button" class="toggle-row"><span class="screen-reader-text"><?php _e('Show more details'); ?></span></button>
                            </td>
                            <td><?php echo esc_html($log->user_agent); ?></td>
                            <td>
                                <?php if (isset($log->status) && $log->status === 'allowed'): ?>
                                    <span class="status-allowed"><?php _e('Allowed'); ?></span>
                                <?php else: ?>
                                    <span class="status-denied"><?php _e('Denied'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(get_date_from_gmt($log->timestamp, get_option('date_format') . ' ' . get_option('time_format'))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            

        <?php endif; ?>
    </div>

    <style>
        .stat-box {
            background: #fff;
            padding: 15px;
            border: 1px solid #ccd0d4;
            text-align: center;
        }
        .stat-box h3 {
            margin: 0 0 10px;
            font-size: 14px;
        }
        .stat-number {
            font-size: 24px;
            font-weight: 600;
        }
        .status-allowed {
            color: #46b450;
            font-weight: 600;
        }
        .status-denied {
            color: #dc3232;
            font-weight: 600;
        }
        @media screen and (max-width: 782px) {
            .stats-grid {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
    <?php
}
