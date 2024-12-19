<?php
/**
 * Social Bot Request Throttle
 *
 * A WordPress plugin that limits request frequency from social media web crawlers
 * by implementing configurable throttling rules.
 *
 * @package   SocialBotThrottle
 * @author    Nadim Tuhin
 * @version   2.4
 * @link      https://nadimtuhin.com
 * @license   GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name: Social Bot Request Throttle
 * Description: Limits the request frequency from various social media web crawlers.
 * Version:     2.4
 * Author:      Nadim Tuhin
 * Author URI:  https://nadimtuhin.com
 */

if (!defined('ABSPATH')) {
    die('We\'re sorry, but you can not directly access this file.');
}

// Define plugin constants
define('SBRT_VERSION', '2.4');
define('SBRT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SBRT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Default throttle values if not set in options
define('DEFAULT_FACEBOOK_THROTTLE', 60.0);
define('DEFAULT_TWITTER_THROTTLE', 60.0); 
define('DEFAULT_PINTEREST_THROTTLE', 60.0);
define('DEFAULT_CUSTOM_THROTTLE', 60.0);

// Load admin functionality
if (is_admin()) {
    require_once SBRT_PLUGIN_DIR . 'includes/settings-page.php';
}

/**
 * Sanitize custom sites array
 */
function nt_sbrt_sanitize_custom_sites($sites) {
    if (!is_array($sites)) {
        return [];
    }
    
    $sanitized = [];
    foreach ($sites as $site) {
        if (!empty($site['name']) && !empty($site['agents'])) {
            $sanitized[] = [
                'name' => sanitize_text_field($site['name']),
                'agents' => sanitize_textarea_field($site['agents']),
                'throttle' => floatval($site['throttle'])
            ];
        }
    }
    return $sanitized;
}

// Bot configurations
function nt_sbrt_get_social_bots_config() {
    $custom_sites = get_option('nt_sbrt_custom_sites', []);
    
    $config = [
        'facebook' => [
            'name' => 'Facebook',
            'agents' => array_filter(explode("\n", get_option('nt_sbrt_facebook_agents', "meta-externalagent\nfacebookexternalhit"))),
            'throttle' => floatval(get_option('nt_sbrt_facebook_throttle', DEFAULT_FACEBOOK_THROTTLE)),
            'transient_key' => 'nt_sbrt_facebook_last_access_time'
        ],
        'twitter' => [
            'name' => 'Twitter',
            'agents' => array_filter(explode("\n", get_option('nt_sbrt_twitter_agents', "Twitterbot"))),
            'throttle' => floatval(get_option('nt_sbrt_twitter_throttle', DEFAULT_TWITTER_THROTTLE)),
            'transient_key' => 'nt_sbrt_twitter_last_access_time'
        ],
        'pinterest' => [
            'name' => 'Pinterest',
            'agents' => array_filter(explode("\n", get_option('nt_sbrt_pinterest_agents', "Pinterest"))),
            'throttle' => floatval(get_option('nt_sbrt_pinterest_throttle', DEFAULT_PINTEREST_THROTTLE)),
            'transient_key' => 'nt_sbrt_pinterest_last_access_time'
        ]
    ];

    // Add custom sites to configuration
    foreach ($custom_sites as $index => $site) {
        $site_key = sanitize_title($site['name']);
        $config[$site_key] = [
            'name' => $site['name'],
            'agents' => array_filter(explode("\n", $site['agents'])),
            'throttle' => floatval($site['throttle']),
            'transient_key' => 'nt_sbrt_' . $site_key . '_last_access_time'
        ];
    }

    return $config;
}

/**
 * Determine if the incoming request originates from a known social media crawler.
 * 
 * @return array|false Returns bot config if request is from known crawler, false otherwise.
 */
function nt_sbrt_identify_bot_request() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $social_bots = nt_sbrt_get_social_bots_config();
    
    foreach ($social_bots as $bot_name => $bot_config) {
        foreach ($bot_config['agents'] as $agent) {
            if (strpos($user_agent, trim($agent)) !== false) {
                return $bot_config;
            }
        }
    }
    return false;
}

/**
 * Check if the request is for an image file
 * 
 * @return bool 
 */
function nt_sbrt_is_image_request() {
    $request_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $file_extension = strtolower(pathinfo($request_path, PATHINFO_EXTENSION));
    $allowed_image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    return in_array($file_extension, $allowed_image_extensions, true);
}

/**
 * Get the last access time for a specific bot
 * 
 * @param string $transient_key
 * @return float|null
 */
function nt_sbrt_get_last_access_time($transient_key) {
    return get_transient($transient_key);
}

/**
 * Set the last access time for a specific bot
 * 
 * @param string $transient_key
 * @param float $current_time
 * @param float $throttle_time
 * @return bool
 */
function nt_sbrt_set_last_access_time($transient_key, $current_time, $throttle_time) {
    // Set the transient to last just slightly longer than the throttle time
    return set_transient($transient_key, $current_time, $throttle_time + 1);
}

/**
 * Send throttle response with appropriate headers
 */
function nt_sbrt_send_throttle_response($bot_config) {
    status_header(429);
    header('Retry-After: 60');
    error_log('Too Many Requests for ' . $bot_config['name']);
    wp_die('Too Many Requests for ' . $bot_config['name'], 'Too Many Requests', ['response' => 429]);
}

// Main logic - only run throttle check for known bot requests
if ($bot_config = nt_sbrt_identify_bot_request()) {
  nt_sbrt_send_throttle_response($bot_config);
}