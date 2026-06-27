<?php
/**
 * Plugin Name: Facebook Request Throttle
 * Description: Limits the request frequency from Facebook's web crawler and other configurable user agents.
 * Version: 2.5
 * Author: Nadim Tuhin
 * Author URI: https://nadimtuhin.com
 */

if (!defined('ABSPATH')) {
    die('We\'re sorry, but you can not directly access this file.');
}

// Number of seconds permitted between each hit
define('FACEBOOK_REQUEST_THROTTLE', 60.0);

// Max log entries to keep
define('FACEBOOK_REQUEST_THROTTLE_LOG_LIMIT', 100);

/**
 * User agents to throttle. Add additional strings here.
 * Each entry is a substring match against the HTTP_USER_AGENT header.
 */
$nt_user_agents_to_throttle = [
    'meta-externalagent',
    'facebookexternalhit',
];

/**
 * Check if the current request is from any of the configured user agents.
 *
 * @return bool
 */
function nt_isRequestFromFacebook() {
    global $nt_user_agents_to_throttle;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (empty($userAgent)) {
        return false;
    }
    foreach ($nt_user_agents_to_throttle as $agent) {
        if (strpos($userAgent, $agent) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Check if the request is for an image file.
 */
function nt_isImageRequest() {
    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $fileExtension = strtolower(pathinfo($requestPath, PATHINFO_EXTENSION));
    return in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
}

/**
 * Get the last access time of the throttled crawler.
 */
function nt_getLastAccessTime() {
    return get_transient('nt_facebook_last_access_time');
}

/**
 * Set the last access time of the throttled crawler.
 */
function nt_setLastAccessTime($currentTime) {
    return set_transient(
        'nt_facebook_last_access_time',
        $currentTime,
        FACEBOOK_REQUEST_THROTTLE + 1
    );
}

/**
 * Append an entry to the admin-visible hit log (capped at LOG_LIMIT entries).
 *
 * @param string $status  'allowed' or 'throttled'
 */
function nt_log($status) {
    $log = get_option('nt_facebook_throttle_log', []);
    array_unshift($log, [
        'time'   => current_time('mysql'),
        'ip'     => $_SERVER['REMOTE_ADDR'] ?? '',
        'ua'     => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'uri'    => $_SERVER['REQUEST_URI'] ?? '',
        'status' => $status,
    ]);
    // Cap to limit
    $log = array_slice($log, 0, FACEBOOK_REQUEST_THROTTLE_LOG_LIMIT);
    update_option('nt_facebook_throttle_log', $log, false);
}

/**
 * Throttle crawler requests to prevent overload.
 */
function nt_facebookRequestThrottle() {
    if (nt_isImageRequest()) {
        return;
    }

    $lastAccessTime = nt_getLastAccessTime();
    $currentTime    = microtime(true);

    if ($lastAccessTime && ($currentTime - $lastAccessTime < FACEBOOK_REQUEST_THROTTLE)) {
        nt_log('throttled');
        nt_sendThrottleResponse();
        // nt_sendThrottleResponse calls wp_die() — execution stops here
    }

    nt_setLastAccessTime($currentTime);
    nt_log('allowed');
}

/**
 * Send throttle response with appropriate headers.
 */
function nt_sendThrottleResponse() {
    status_header(429);
    header('Retry-After: 60');
    wp_die(
        'Too Many Requests',
        'Too Many Requests',
        ['response' => 429]
    );
}

// ── Admin log page ────────────────────────────────────────────────────────────

function nt_admin_menu() {
    add_options_page(
        'Facebook Throttle Log',
        'FB Throttle Log',
        'manage_options',
        'nt-facebook-throttle-log',
        'nt_render_log_page'
    );
}
add_action('admin_menu', 'nt_admin_menu');

function nt_render_log_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Clear log action
    if (isset($_POST['nt_clear_log']) && check_admin_referer('nt_clear_log')) {
        delete_option('nt_facebook_throttle_log');
        echo '<div class="updated"><p>Log cleared.</p></div>';
    }

    $log = get_option('nt_facebook_throttle_log', []);
    ?>
    <div class="wrap">
        <h1>Facebook Request Throttle — Hit Log</h1>
        <p>Shows the last <?php echo FACEBOOK_REQUEST_THROTTLE_LOG_LIMIT; ?> requests from configured crawlers.</p>
        <form method="post">
            <?php wp_nonce_field('nt_clear_log'); ?>
            <input type="submit" name="nt_clear_log" class="button" value="Clear Log">
        </form>
        <br>
        <?php if (empty($log)): ?>
            <p>No hits recorded yet.</p>
        <?php else: ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Status</th>
                        <th>IP</th>
                        <th>URI</th>
                        <th>User Agent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($log as $entry): ?>
                        <tr>
                            <td><?php echo esc_html($entry['time']); ?></td>
                            <td>
                                <?php if ($entry['status'] === 'throttled'): ?>
                                    <span style="color:red;font-weight:bold">throttled (429)</span>
                                <?php else: ?>
                                    <span style="color:green">allowed (200)</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($entry['ip']); ?></td>
                            <td><?php echo esc_html($entry['uri']); ?></td>
                            <td><?php echo esc_html($entry['ua']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

// ── Main ──────────────────────────────────────────────────────────────────────

if (nt_isRequestFromFacebook()) {
    nt_facebookRequestThrottle();
}
