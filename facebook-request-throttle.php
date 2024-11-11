<?php
/**
 * Plugin Name: Facebook Request Throttle
 * Description: Limits the request frequency from Facebook's web crawler.
 * Version: 2.1
 * Author: Nadim Tuhin
 * Author URI: https://nadimtuhin.com
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'We\'re sorry, but you can not directly access this file.' );
}


define('FACEBOOK_REQUEST_THROTTLE', 60.0); // Number of seconds (60) permitted between each hit from meta-externalagent / facebookexternalhit

/**
 * Check if the request is from Facebook's web crawler
 * 
 * @return bool
 */

function nt_isRequestFromFacebook() {
  return !empty($_SERVER['HTTP_USER_AGENT']) && 
         (strpos($_SERVER['HTTP_USER_AGENT'], 'meta-externalagent') !== false || 
          strpos($_SERVER['HTTP_USER_AGENT'], 'facebookexternalhit') !== false);
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
* @param float $microTime
* @return bool
*/
function nt_setLastAccessTime($microTime) {
  // Set the transient to last just slightly longer than the throttle time
  return set_transient('nt_facebook_last_access_time', $microTime, FACEBOOK_REQUEST_THROTTLE + 1);
}

function nt_facebookRequestThrottle() {
  $lastTime = nt_getLastAccessTime();
  $microTime = microtime(TRUE);

  // Separate the conditions to see which one is triggering
  if (!$lastTime) {
      error_log("No last access time found.");
  } elseif ($microTime - $lastTime < FACEBOOK_REQUEST_THROTTLE) {
      header($_SERVER["SERVER_PROTOCOL"] . ' 503 Service Temporarily Unavailable');
      echo 'Service Temporarily Unavailable';
      die;
  }
  
  if (!nt_setLastAccessTime($microTime)) {
      error_log("Failed to set last access time for Facebook web crawler.");
      header($_SERVER["SERVER_PROTOCOL"] . ' 429 Too Many Requests');
      echo 'Too Many Requests';
      die;
  }
}

// Main logic
if (nt_isRequestFromFacebook()) {
  nt_facebookRequestThrottle();
}