<?php
/**
 * Logs page functionality
 *
 * @package SocialBotThrottle
 * @since   3.2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Add logs page to admin menu.
add_action( 'admin_menu', 'nt_sbrt_add_logs_menu' );

/**
 * Add logs page to admin menu.
 *
 * @since 3.2
 */
function nt_sbrt_add_logs_menu() {
    add_submenu_page(
        'options-general.php',
        esc_html__( 'Social Bot Throttle Logs', 'social-bot-throttle' ),
        esc_html__( 'Bot Throttle Logs', 'social-bot-throttle' ),
        'manage_options',
        'social-bot-throttle-logs',
        'nt_sbrt_logs_page'
    );
}

/**
 * Display logs page content.
 *
 * @since 3.2
 */
function nt_sbrt_logs_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'social-bot-throttle' ) );
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'sbrt_throttle_log';

    // Handle delete all logs action.
    if ( isset( $_POST['delete_all_logs'] ) && check_admin_referer( 'delete_all_logs' ) ) {
        $wpdb->query( $wpdb->prepare( "TRUNCATE TABLE %i", $table_name ) );
        add_settings_error(
            'sbrt_logs',
            'logs_deleted',
            esc_html__( 'All logs have been deleted.', 'social-bot-throttle' ),
            'success'
        );
    }
    
    // Get logs with pagination.
    $page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
    $per_page = 15;
    $offset = ( $page - 1 ) * $per_page;
    
    // Get statistics.
    $total_items = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i", $table_name ) );
    $allowed_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status = %s", $table_name, 'allowed' ) );
    $denied_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status = %s", $table_name, 'denied' ) );
    
    $total_pages = ceil( $total_items / $per_page );
    
    $logs = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM %i ORDER BY timestamp DESC LIMIT %d OFFSET %d",
        $table_name,
        $per_page,
        $offset
    ) );
    
    ?>
    <div class="wrap sbrt-logs-page">
        <h1 class="wp-heading-inline"><?php esc_html_e( 'Social Bot Throttle Logs', 'social-bot-throttle' ); ?></h1>
        
        <?php settings_errors( 'sbrt_logs' ); ?>

        <div class="postbox sbrt-stats-box">
            <div class="inside">
                <h2 class="sbrt-section-title"><?php esc_html_e( 'Statistics', 'social-bot-throttle' ); ?></h2>
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-icon dashicons dashicons-chart-bar"></div>
                        <h3><?php esc_html_e( 'Total Requests', 'social-bot-throttle' ); ?></h3>
                        <span class="stat-number"><?php echo esc_html( number_format( $total_items ) ); ?></span>
                    </div>
                    <div class="stat-box">
                        <div class="stat-icon dashicons dashicons-yes-alt"></div>
                        <h3><?php esc_html_e( 'Allowed Requests', 'social-bot-throttle' ); ?></h3>
                        <span class="stat-number allowed"><?php echo esc_html( number_format( $allowed_count ) ); ?></span>
                    </div>
                    <div class="stat-box">
                        <div class="stat-icon dashicons dashicons-dismiss"></div>
                        <h3><?php esc_html_e( 'Denied Requests', 'social-bot-throttle' ); ?></h3>
                        <span class="stat-number denied"><?php echo esc_html( number_format( $denied_count ) ); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="tablenav top">
            <div class="alignleft actions">
                <form method="post" style="display: inline;">
                    <?php wp_nonce_field( 'delete_all_logs' ); ?>
                    <?php submit_button( 
                        esc_html__( 'Delete All Logs', 'social-bot-throttle' ), 
                        'delete button-link-delete', 
                        'delete_all_logs', 
                        false, 
                        array(
                            'onclick' => sprintf( 
                                'return confirm("%s");',
                                esc_js( __( 'Are you sure you want to delete all logs? This cannot be undone.', 'social-bot-throttle' ) )
                            )
                        )
                    ); ?>
                </form>
            </div>
        </div>
        
        <?php if ( empty( $logs ) ) : ?>
            <div class="notice notice-info">
                <p><?php esc_html_e( 'No throttle logs found.', 'social-bot-throttle' ); ?></p>
            </div>
        <?php else : ?>
            <?php if ( $total_pages > 1 ) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php
                            printf(
                                /* translators: %s: Number of items */
                                esc_html( _n( '%s item', '%s items', $total_items, 'social-bot-throttle' ) ),
                                esc_html( number_format_i18n( $total_items ) )
                            );
                            ?>
                        </span>
                        <span class="pagination-links">
                            <?php
                            echo wp_kses_post( paginate_links( array(
                                'base'      => add_query_arg( 'paged', '%#%' ),
                                'format'    => '',
                                'prev_text' => __( '&laquo;', 'social-bot-throttle' ),
                                'next_text' => __( '&raquo;', 'social-bot-throttle' ),
                                'total'     => $total_pages,
                                'current'   => $page,
                                'add_args'  => false,
                            ) ) );
                            ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
            <table class="wp-list-table widefat fixed striped sbrt-logs-table">
                <thead>
                    <tr>
                        <th scope="col" class="bot-col"><?php esc_html_e( 'Bot', 'social-bot-throttle' ); ?></th>
                        <th scope="col" class="uri-col"><?php esc_html_e( 'Request URI', 'social-bot-throttle' ); ?></th>
                        <th scope="col" class="agent-col"><?php esc_html_e( 'User Agent', 'social-bot-throttle' ); ?></th>
                        <th scope="col" class="status-col"><?php esc_html_e( 'Status', 'social-bot-throttle' ); ?></th>
                        <th scope="col" class="time-col"><?php esc_html_e( 'Timestamp', 'social-bot-throttle' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $logs as $log ) : ?>
                        <tr>
                            <td class="bot-col"><?php echo esc_html( $log->bot_name ); ?></td>
                            <td class="uri-col column-primary">
                                <strong class="uri-text"><?php echo esc_html( $log->request_uri ); ?></strong>
                                <button type="button" class="toggle-row">
                                    <span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'social-bot-throttle' ); ?></span>
                                </button>
                            </td>
                            <td class="agent-col">
                                <code class="user-agent"><?php echo esc_html( $log->user_agent ); ?></code>
                            </td>
                            <td class="status-col">
                                <?php if ( isset( $log->status ) && 'allowed' === $log->status ) : ?>
                                    <span class="status-badge status-allowed">
                                        <span class="dashicons dashicons-yes"></span>
                                        <?php esc_html_e( 'Allowed', 'social-bot-throttle' ); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="status-badge status-denied">
                                        <span class="dashicons dashicons-no"></span>
                                        <?php esc_html_e( 'Denied', 'social-bot-throttle' ); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="time-col">
                                <?php echo esc_html( get_date_from_gmt( $log->timestamp, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?>
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
