<?php
/**
 * Plugin Name: Facebook Request Throttle
 * Description: Limits the request frequency from Facebook's web crawler.
 * Version: 2.3
 * Author: Nadim Tuhin
 * Author URI: https://nadimtuhin.com
 */

if (!defined('ABSPATH')) {
    die('We\'re sorry, but you can not directly access this file.');
}

// Number of seconds permitted between each hit from meta-externalagent / facebookexternalhit
define('FACEBOOK_REQUEST_THROTTLE', 60.0);

/**
 * Determine if the incoming request originates from Facebook's web crawler.
 * 
 * @return bool True if the request is from Facebook's crawler, false otherwise.
 */
function nt_is_request_from_facebook() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $facebook_user_agents = ['meta-externalagent', 'facebookexternalhit'];
    
    foreach ($facebook_user_agents as $agent) {
        if (strpos($user_agent, $agent) !== false) {
            return true;
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
 * Get the last access time of Facebook's web crawler
 * 
 * @return float|null
 */
function nt_get_last_access_time() {
    return get_transient('nt_facebook_last_access_time');
}

/**
 * Set the last access time of Facebook's web crawler
 * 
 * @param float $current_time
 * @return bool
 */
function nt_set_last_access_time($current_time) {
    // Set the transient to last just slightly longer than the throttle time
    return set_transient('nt_facebook_last_access_time', $current_time, FACEBOOK_REQUEST_THROTTLE + 1);
}

/**
 * Throttle Facebook crawler requests to prevent overload
 */
function nt_facebook_request_throttle() {
    // Skip throttling for image requests
    if (nt_is_image_request()) {
        return;
    }

    $last_access_time = nt_get_last_access_time();
    $current_time = microtime(true);

    // Check if we need to throttle
    if ($last_access_time && ($current_time - $last_access_time < FACEBOOK_REQUEST_THROTTLE)) {
        nt_send_throttle_response();
    } else {
        // Attempt to set last access time
        if (!nt_set_last_access_time($current_time)) {
            error_log("Failed to set last access time for Facebook web crawler.");
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

// Main logic - only run throttle check for Facebook requests
if (nt_is_request_from_facebook()) {
    nt_facebook_request_throttle();
}