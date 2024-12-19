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

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
if (!defined('SBRT_VERSION')) {
    define('SBRT_VERSION', '2.4');
}

if (!defined('SBRT_PLUGIN_DIR')) {
    define('SBRT_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('SBRT_PLUGIN_URL')) {
    define('SBRT_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// Default throttle values
if (!defined('DEFAULT_FACEBOOK_THROTTLE')) {
    define('DEFAULT_FACEBOOK_THROTTLE', 60.0);
}
if (!defined('DEFAULT_TWITTER_THROTTLE')) {
    define('DEFAULT_TWITTER_THROTTLE', 60.0);
}
if (!defined('DEFAULT_PINTEREST_THROTTLE')) {
    define('DEFAULT_PINTEREST_THROTTLE', 60.0);
}
if (!defined('DEFAULT_CUSTOM_THROTTLE')) {
    define('DEFAULT_CUSTOM_THROTTLE', 60.0);
}

require_once SBRT_PLUGIN_DIR . 'includes/deactivation.php';

// Load admin functionality
add_action('plugins_loaded', 'nt_sbrt_load_admin');
function nt_sbrt_load_admin() {
    if (is_admin()) {
        require_once SBRT_PLUGIN_DIR . 'includes/settings-page.php';
        require_once SBRT_PLUGIN_DIR . 'includes/logs-page.php';
    }
}

/**
 * Get bot configurations
 *
 * @return array Bot configuration settings
 */
function nt_sbrt_get_social_bots_config() {
    $custom_sites = get_option('nt_sbrt_custom_sites', array());
    
    $config = array(
        'facebook' => array(
            'name' => 'Facebook',
            'agents' => array_filter(explode("\n", get_option('nt_sbrt_facebook_agents', "meta-externalagent\nfacebookexternalhit"))),
            'throttle' => floatval(get_option('nt_sbrt_facebook_throttle', DEFAULT_FACEBOOK_THROTTLE)),
            'transient_key' => 'nt_sbrt_facebook_last_access_time',
            'throttle_images' => get_option('nt_sbrt_facebook_throttle_images', '0')
        ),
        'twitter' => array(
            'name' => 'Twitter', 
            'agents' => array_filter(explode("\n", get_option('nt_sbrt_twitter_agents', "Twitterbot"))),
            'throttle' => floatval(get_option('nt_sbrt_twitter_throttle', DEFAULT_TWITTER_THROTTLE)),
            'transient_key' => 'nt_sbrt_twitter_last_access_time',
            'throttle_images' => get_option('nt_sbrt_twitter_throttle_images', '0')
        ),
        'pinterest' => array(
            'name' => 'Pinterest',
            'agents' => array_filter(explode("\n", get_option('nt_sbrt_pinterest_agents', "Pinterest"))),
            'throttle' => floatval(get_option('nt_sbrt_pinterest_throttle', DEFAULT_PINTEREST_THROTTLE)),
            'transient_key' => 'nt_sbrt_pinterest_last_access_time',
            'throttle_images' => get_option('nt_sbrt_pinterest_throttle_images', '0')
        )
    );

    // Add custom sites
    if (!empty($custom_sites) && is_array($custom_sites)) {
        foreach ($custom_sites as $site) {
            if (empty($site['name'])) {
                continue;
            }
            
            $site_key = sanitize_title($site['name']);
            $config[$site_key] = array(
                'name' => $site['name'],
                'agents' => array_filter(explode("\n", $site['agents'])),
                'throttle' => floatval($site['throttle']),
                'transient_key' => 'nt_sbrt_' . $site_key . '_last_access_time',
                'throttle_images' => isset($site['throttle_images']) ? $site['throttle_images'] : '0'
            );
        }
    }

    return $config;
}

/**
 * Identify if request is from a known bot
 *
 * @return array|false Bot config if matched, false if not
 */
function nt_sbrt_identify_bot_request() {
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    if (empty($user_agent)) {
        return false;
    }
    
    $social_bots = nt_sbrt_get_social_bots_config();
    
    foreach ($social_bots as $bot_config) {
        if (empty($bot_config['agents'])) {
            continue;
        }
        
        foreach ($bot_config['agents'] as $agent) {
            $agent = trim($agent);
            if (!empty($agent) && false !== strpos($user_agent, $agent)) {
                return $bot_config;
            }
        }
    }
    return false;
}

/**
 * Check if request is for an image
 *
 * @return bool True if image request
 */
function nt_sbrt_is_image_request() {
    if (!isset($_SERVER['REQUEST_URI'])) {
        return false;
    }
    
    $request_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (empty($request_path)) {
        return false;
    }
    
    $file_extension = strtolower(pathinfo($request_path, PATHINFO_EXTENSION));
    $allowed_image_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');
    
    return in_array($file_extension, $allowed_image_extensions, true);
}

/**
 * Get last access time for a bot
 *
 * @param string $transient_key Transient key
 * @return mixed Last access time or false
 */
function nt_sbrt_get_last_access_time($transient_key) {
    if (empty($transient_key)) {
        return false;
    }
    
    $last_access_time = get_transient($transient_key);
    if (false === $last_access_time) {
        error_log(sprintf('SBRT: Failed to get last access time for key: %s', $transient_key));
    }
    
    return $last_access_time;
}

/**
 * Set last access time for a bot
 *
 * @param string $transient_key Transient key
 * @param float $current_time Current timestamp
 * @param float $throttle_time Throttle duration
 * @return bool Success/failure
 */
function nt_sbrt_set_last_access_time($transient_key, $current_time, $throttle_time) {
    if (empty($transient_key)) {
        return false;
    }
    
    $result = set_transient($transient_key, $current_time, (int)$throttle_time + 1);
    if (!$result) {
        error_log(sprintf('SBRT: Failed to set last access time for key: %s', $transient_key));
    }
    
    return $result;
}

/**
 * Log throttled request
 * 
 * @param array $bot_config Bot configuration
 * @param string $request_uri Request URI
 * @param string $user_agent User agent string
 * @param string $status Request status (allowed/denied)
 */
function nt_sbrt_log_throttled_request($bot_config, $request_uri = '', $user_agent = '', $status = 'denied') {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'sbrt_throttle_log';
    
    $wpdb->insert(
        $table_name,
        array(
            'bot_name' => $bot_config['name'],
            'request_uri' => $request_uri ? $request_uri : $_SERVER['REQUEST_URI'],
            'user_agent' => $user_agent ? $user_agent : $_SERVER['HTTP_USER_AGENT'],
            'status' => $status,
            'timestamp' => current_time('mysql')
        ),
        array('%s', '%s', '%s', '%s', '%s')
    );
}

/**
 * Send throttle response
 *
 * @param array $bot_config Bot configuration
 */
function nt_sbrt_send_throttle_response($bot_config) {
    if (!is_array($bot_config) || empty($bot_config['name'])) {
        return;
    }
    
    // Log the throttled request
    nt_sbrt_log_throttled_request($bot_config);
    
    status_header(429);
    header('Retry-After: 60');
    error_log(sprintf('SBRT: Too Many Requests for %s', $bot_config['name']));
    wp_die(
        sprintf('Too Many Requests for %s', esc_html($bot_config['name'])),
        'Too Many Requests',
        array('response' => 429)
    );
}

/**
 * Throttle bot requests to prevent overload
 * 
 * @param array $bot_config
 */
function nt_sbrt_bot_request_throttle($bot_config) {
  // Skip throttling for image requests
  if (nt_sbrt_is_image_request()) {
      return;
  }

  $last_access_time = nt_sbrt_get_last_access_time($bot_config['transient_key']);
  $current_time = microtime(true);

  // Check if we need to throttle
  if ($last_access_time && ($current_time - $last_access_time < $bot_config['throttle'])) {
    nt_sbrt_send_throttle_response($bot_config);
  } else {
      // Attempt to set last access time
      if (!nt_sbrt_set_last_access_time($bot_config['transient_key'], $current_time, $bot_config['throttle'])) {
          error_log("Failed to set last access time for bot crawler.");
          nt_sbrt_send_throttle_response($bot_config);
      }
  }
}

// Initialize throttling
function nt_sbrt_init_throttling() {
    $bot_config = nt_sbrt_identify_bot_request();
    if (!$bot_config) {
        return;
    }

    nt_sbrt_bot_request_throttle($bot_config);
}

add_action('init', 'nt_sbrt_init_throttling', 10);

