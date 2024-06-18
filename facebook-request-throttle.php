<?php
/**
 * Plugin Name: User Agent Request Throttle
 * Description: Limits the request frequency from specified user agents.
 * Version: 1.1
 * Author: Nadim Tuhin
 * Author URI: https://nadimtuhin.com
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'We\'re sorry, but you can not directly access this file.' );
}

define('REQUEST_THROTTLE', 2.0); // Number of seconds permitted between each hit from specified user agents

// Array of user agents to throttle
$user_agents_to_throttle = array(
	'facebookexternalhit',
	'Python/3.10 aiohttp/3.9.3',
);

/**
 * Check if the request is from specified user agents
 * 
 * @param array $user_agents
 * @return bool
 */
function nt_isRequestFromUserAgents($user_agents) {
	foreach ($user_agents as $agent) {
		if (!empty($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], $agent) !== false) {
			return true;
		}
	}
	return false;
}

/**
 * Get the last access time of the web crawler
 * 
 * @param string $agent
 * @return float|null
 */
function nt_getLastAccessTime($agent) {
	return get_transient('nt_' . str_replace(' ', '_', $agent) . '_last_access_time');
}

/**
 * Set the last access time of the web crawler
 * 
 * @param string $agent
 * @param float $microTime
 * @return bool
 */
function nt_setLastAccessTime($agent, $microTime) {
	// Set the transient to last just slightly longer than the throttle time
	return set_transient('nt_' . str_replace(' ', '_', $agent) . '_last_access_time', $microTime, REQUEST_THROTTLE + 1);
}

/**
 * Throttle the request from the specified user agent
 * 
 * @param string $agent
 */
function nt_requestThrottle($agent) {
	$lastTime = nt_getLastAccessTime($agent);
	$microTime = microtime(TRUE);

	// Separate the conditions to see which one is triggering
	if (!$lastTime) {
		error_log("No last access time found for $agent.");
	} elseif ($microTime - $lastTime < REQUEST_THROTTLE) {
		header($_SERVER["SERVER_PROTOCOL"] . ' 503 Service Temporarily Unavailable');
		echo 'Service Temporarily Unavailable';
		die;
	}
	
	if (!nt_setLastAccessTime($agent, $microTime)) {
		error_log("Failed to set last access time for $agent web crawler.");
		header($_SERVER["SERVER_PROTOCOL"] . ' 429 Too Many Requests');
		echo 'Too Many Requests';
		die;
	}
}

// Main logic
foreach ($user_agents_to_throttle as $agent) {
	if (nt_isRequestFromUserAgents(array($agent))) {
		nt_requestThrottle($agent);
		break;
	}
}
