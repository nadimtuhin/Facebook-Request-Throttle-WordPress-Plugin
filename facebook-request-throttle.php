<?php
/**
 * Plugin Name: Social Bot Request Throttle
 * Description: Limits the request frequency from various social media web crawlers.
 * Version: 2.4
 * Author: Nadim Tuhin
 * Author URI: https://nadimtuhin.com
 */

if (!defined('ABSPATH')) {
    die('We\'re sorry, but you can not directly access this file.');
}

// Number of seconds permitted between each hit from different crawlers
define('FACEBOOK_REQUEST_THROTTLE', 60.0);
define('TWITTER_REQUEST_THROTTLE', 60.0);
define('PINTEREST_REQUEST_THROTTLE', 60.0);

// Bot configurations
$GLOBALS['social_bots'] = [
    'facebook' => [
        'agents' => ['meta-externalagent', 'facebookexternalhit'],
        'throttle' => FACEBOOK_REQUEST_THROTTLE,
        'transient_key' => 'nt_facebook_last_access_time'
    ],
    'twitter' => [
        'agents' => ['Twitterbot'],
        'throttle' => TWITTER_REQUEST_THROTTLE,
        'transient_key' => 'nt_twitter_last_access_time'
    ],
    'pinterest' => [
        'agents' => ['Pinterest'],
        'throttle' => PINTEREST_REQUEST_THROTTLE,
        'transient_key' => 'nt_pinterest_last_access_time'
    ]
];

/**
 * Determine if the incoming request originates from a known social media crawler.
 * 
 * @return array|false Returns bot config if request is from known crawler, false otherwise.
 */
function nt_identify_bot_request() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    foreach ($GLOBALS['social_bots'] as $bot_name => $bot_config) {
        foreach ($bot_config['agents'] as $agent) {
            if (strpos($user_agent, $agent) !== false) {
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
function nt_is_image_request() {
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
function nt_get_last_access_time($transient_key) {
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
function nt_set_last_access_time($transient_key, $current_time, $throttle_time) {
    // Set the transient to last just slightly longer than the throttle time
    return set_transient($transient_key, $current_time, $throttle_time + 1);
}

/**
 * Throttle bot requests to prevent overload
 * 
 * @param array $bot_config
 */
function nt_bot_request_throttle($bot_config) {
    // Skip throttling for image requests
    if (nt_is_image_request()) {
        return;
    }

    $last_access_time = nt_get_last_access_time($bot_config['transient_key']);
    $current_time = microtime(true);

    // Check if we need to throttle
    if ($last_access_time && ($current_time - $last_access_time < $bot_config['throttle'])) {
        nt_send_throttle_response();
    } else {
        // Attempt to set last access time
        if (!nt_set_last_access_time($bot_config['transient_key'], $current_time, $bot_config['throttle'])) {
            error_log("Failed to set last access time for bot crawler.");
            nt_send_throttle_response();
        }
    }
}

/**
 * Send throttle response with appropriate headers
 */
function nt_send_throttle_response() {
    status_header(429);
    header('Retry-After: 60');
    wp_die('Too Many Requests', 'Too Many Requests', ['response' => 429]);
}

// Main logic - only run throttle check for known bot requests
if ($bot_config = nt_identify_bot_request()) {
    nt_bot_request_throttle($bot_config);
}