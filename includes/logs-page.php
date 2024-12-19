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
    <div class="wrap sbrt-logs-page">
        <h1 class="wp-heading-inline"><?php _e('Social Bot Throttle Logs'); ?></h1>
        
        <?php settings_errors('sbrt_logs'); ?>

        <div class="postbox sbrt-stats-box">
            <div class="inside">
                <h2 class="sbrt-section-title"><?php _e('Statistics'); ?></h2>
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-icon dashicons dashicons-chart-bar"></div>
                        <h3><?php _e('Total Requests'); ?></h3>
                        <span class="stat-number"><?php echo number_format($total_items); ?></span>
                    </div>
                    <div class="stat-box">
                        <div class="stat-icon dashicons dashicons-yes-alt"></div>
                        <h3><?php _e('Allowed Requests'); ?></h3>
                        <span class="stat-number allowed"><?php echo number_format($allowed_count); ?></span>
                    </div>
                    <div class="stat-box">
                        <div class="stat-icon dashicons dashicons-dismiss"></div>
                        <h3><?php _e('Denied Requests'); ?></h3>
                        <span class="stat-number denied"><?php echo number_format($denied_count); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="tablenav top">
            <div class="alignleft actions">
                <form method="post" style="display: inline;">
                    <?php wp_nonce_field('delete_all_logs'); ?>
                    <?php submit_button(__('Delete All Logs'), 'delete button-link-delete', 'delete_all_logs', false, array(
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
            <table class="wp-list-table widefat fixed striped sbrt-logs-table">
                <thead>
                    <tr>
                        <th scope="col" class="bot-col"><?php _e('Bot'); ?></th>
                        <th scope="col" class="uri-col"><?php _e('Request URI'); ?></th>
                        <th scope="col" class="agent-col"><?php _e('User Agent'); ?></th>
                        <th scope="col" class="status-col"><?php _e('Status'); ?></th>
                        <th scope="col" class="time-col"><?php _e('Timestamp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="bot-col"><?php echo esc_html($log->bot_name); ?></td>
                            <td class="uri-col column-primary">
                                <strong class="uri-text"><?php echo esc_html($log->request_uri); ?></strong>
                                <button type="button" class="toggle-row">
                                    <span class="screen-reader-text"><?php _e('Show more details'); ?></span>
                                </button>
                            </td>
                            <td class="agent-col">
                                <code class="user-agent"><?php echo esc_html($log->user_agent); ?></code>
                            </td>
                            <td class="status-col">
                                <?php if (isset($log->status) && $log->status === 'allowed'): ?>
                                    <span class="status-badge status-allowed">
                                        <span class="dashicons dashicons-yes"></span>
                                        <?php _e('Allowed'); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-denied">
                                        <span class="dashicons dashicons-no"></span>
                                        <?php _e('Denied'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="time-col">
                                <?php echo esc_html(get_date_from_gmt($log->timestamp, get_option('date_format') . ' ' . get_option('time_format'))); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php endif; ?>
    </div>

    <style>
        .sbrt-logs-page {
            max-width: 1200px;
            margin: 20px auto;
        }

        .sbrt-section-title {
            color: #23282d;
            font-size: 16px;
            margin: 0 0 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .sbrt-stats-box {
            background: #fff;
            border: 1px solid #e5e5e5;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            margin-bottom: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin: 15px 0;
        }

        .stat-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            transition: transform 0.2s ease;
        }

        .stat-box:hover {
            transform: translateY(-2px);
        }

        .stat-icon {
            font-size: 24px;
            margin-bottom: 10px;
            color: #646970;
        }

        .stat-box h3 {
            margin: 0 0 10px;
            font-size: 14px;
            color: #646970;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 600;
            color: #1d2327;
        }

        .stat-number.allowed {
            color: #00a32a;
        }

        .stat-number.denied {
            color: #d63638;
        }

        .sbrt-logs-table {
            border-collapse: collapse;
            margin-top: 20px;
        }

        .sbrt-logs-table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .bot-col {
            width: 15%;
        }

        .uri-col {
            width: 25%;
        }

        .agent-col {
            width: 30%;
        }

        .status-col {
            width: 15%;
        }

        .time-col {
            width: 15%;
        }

        .uri-text {
            word-break: break-all;
        }

        .user-agent {
            display: block;
            padding: 4px 8px;
            background: #f6f7f7;
            border-radius: 4px;
            font-size: 12px;
            word-break: break-all;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge .dashicons {
            font-size: 14px;
            width: 14px;
            height: 14px;
            margin-right: 4px;
        }

        .status-allowed {
            background: #edfaef;
            color: #00a32a;
        }

        .status-denied {
            background: #fcf0f1;
            color: #d63638;
        }

        @media screen and (max-width: 782px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .sbrt-logs-table th.column-primary ~ th,
            .sbrt-logs-table td.column-primary ~ td {
                display: none;
            }

            .sbrt-logs-table th.column-primary,
            .sbrt-logs-table td.column-primary {
                padding-right: 50px;
            }

            .toggle-row {
                display: block;
            }
        }
    </style>
    <?php
}
