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
 * Check if the request is from Facebook's web crawler
 * 
 * @return bool
 */
function nt_isRequestFromFacebook() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return !empty($userAgent) && (
        strpos($userAgent, 'meta-externalagent') !== false || 
        strpos($userAgent, 'facebookexternalhit') !== false
    );
}

/**
 * Check if the request is for an image file
 * 
 * @return bool 
 */
function nt_isImageRequest() {
    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $fileExtension = strtolower(pathinfo($requestPath, PATHINFO_EXTENSION));
    $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    return in_array($fileExtension, $allowedImageExtensions);
}

/**
 * Get the last access time of Facebook's web crawler
 * 
 * @return float|null
 */
function nt_getLastAccessTime() {
    return get_transient('nt_facebook_last_access_time');
}

/**
 * Set the last access time of Facebook's web crawler
 * 
 * @param float $currentTime
 * @return bool
 */
function nt_setLastAccessTime($currentTime) {
    // Set the transient to last just slightly longer than the throttle time
    return set_transient(
        'nt_facebook_last_access_time', 
        $currentTime, 
        FACEBOOK_REQUEST_THROTTLE + 1
    );
}

/**
 * Throttle Facebook crawler requests to prevent overload
 */
function nt_facebookRequestThrottle() {
    // Skip throttling for image requests
    if (nt_isImageRequest()) {
        return;
    }

    $lastAccessTime = nt_getLastAccessTime();
    $currentTime = microtime(TRUE);

    // Check if we need to throttle
    if (!$lastAccessTime) {
        error_log("No last access time found.");
    } elseif ($currentTime - $lastAccessTime < FACEBOOK_REQUEST_THROTTLE) {
        nt_sendThrottleResponse();
    }
    
    // Attempt to set last access time
    if (!nt_setLastAccessTime($currentTime)) {
        error_log("Failed to set last access time for Facebook web crawler.");
        nt_sendThrottleResponse();
    }
}

/**
 * Send throttle response with appropriate headers
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

// Main logic - only run throttle check for Facebook requests
if (nt_isRequestFromFacebook()) {
  nt_facebookRequestThrottle();
}