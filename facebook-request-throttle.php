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
 * Text Domain: social-bot-throttle
 * Domain Path: /languages
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define plugin constants
define( 'SBRT_VERSION', '2.4' );
define( 'SBRT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SBRT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SBRT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Default throttle values
define( 'SBRT_DEFAULT_FACEBOOK_THROTTLE', 60.0 );
define( 'SBRT_DEFAULT_TWITTER_THROTTLE', 60.0 );
define( 'SBRT_DEFAULT_PINTEREST_THROTTLE', 60.0 );
define( 'SBRT_DEFAULT_CUSTOM_THROTTLE', 60.0 );

/**
 * Load plugin textdomain.
 *
 * @since 2.4
 */
function sbrt_load_textdomain() {
    load_plugin_textdomain(
        'social-bot-throttle',
        false,
        dirname( SBRT_PLUGIN_BASENAME ) . '/languages/'
    );
}
add_action( 'plugins_loaded', 'sbrt_load_textdomain' );

/**
 * Initialize plugin
 *
 * @since 2.4
 */
function sbrt_init() {
    add_action( 'admin_menu', 'sbrt_add_admin_menu' );
    add_action( 'admin_init', 'sbrt_register_settings' );
}
add_action( 'init', 'sbrt_init' );

/**
 * Add menu item
 *
 * @since 2.4
 */
function sbrt_add_admin_menu() {
    add_options_page(
        __( 'Social Bot Throttle Settings', 'social-bot-throttle' ),
        __( 'Social Bot Throttle', 'social-bot-throttle' ),
        'manage_options',
        'social-bot-throttle',
        'sbrt_render_settings_page'
    );
}

/**
 * Register settings
 *
 * @since 2.4
 */
function sbrt_register_settings() {
    register_setting( 'sbrt_options', 'sbrt_facebook_throttle', 'floatval' );
    register_setting( 'sbrt_options', 'sbrt_facebook_agents', 'sanitize_textarea_field' );
    register_setting( 'sbrt_options', 'sbrt_twitter_throttle', 'floatval' );
    register_setting( 'sbrt_options', 'sbrt_twitter_agents', 'sanitize_textarea_field' );
    register_setting( 'sbrt_options', 'sbrt_pinterest_throttle', 'floatval' );
    register_setting( 'sbrt_options', 'sbrt_pinterest_agents', 'sanitize_textarea_field' );
    register_setting( 'sbrt_options', 'sbrt_custom_sites', 'sbrt_sanitize_custom_sites' );
}

/**
 * Sanitize custom sites array
 *
 * @since 2.4
 * @param array $sites Custom sites array to sanitize
 * @return array Sanitized custom sites array
 */
function sbrt_sanitize_custom_sites( $sites ) {
    if ( ! is_array( $sites ) ) {
        return array();
    }
    
    $sanitized = array();
    foreach ( $sites as $site ) {
        if ( ! empty( $site['name'] ) && ! empty( $site['agents'] ) ) {
            $sanitized[] = array(
                'name' => sanitize_text_field( $site['name'] ),
                'agents' => sanitize_textarea_field( $site['agents'] ),
                'throttle' => floatval( $site['throttle'] )
            );
        }
    }
    return $sanitized;
}

/**
 * Get bot configurations
 *
 * @since 2.4
 * @return array Bot configurations
 */
function sbrt_get_bot_config() {
    $custom_sites = get_option( 'sbrt_custom_sites', array() );
    
    $config = array(
        'facebook' => array(
            'agents' => array_filter( explode( "\n", get_option( 'sbrt_facebook_agents', "meta-externalagent\nfacebookexternalhit" ) ) ),
            'throttle' => floatval( get_option( 'sbrt_facebook_throttle', SBRT_DEFAULT_FACEBOOK_THROTTLE ) ),
            'transient_key' => 'sbrt_facebook_last_access'
        ),
        'twitter' => array(
            'agents' => array_filter( explode( "\n", get_option( 'sbrt_twitter_agents', "Twitterbot" ) ) ),
            'throttle' => floatval( get_option( 'sbrt_twitter_throttle', SBRT_DEFAULT_TWITTER_THROTTLE ) ),
            'transient_key' => 'sbrt_twitter_last_access'
        ),
        'pinterest' => array(
            'agents' => array_filter( explode( "\n", get_option( 'sbrt_pinterest_agents', "Pinterest" ) ) ),
            'throttle' => floatval( get_option( 'sbrt_pinterest_throttle', SBRT_DEFAULT_PINTEREST_THROTTLE ) ),
            'transient_key' => 'sbrt_pinterest_last_access'
        )
    );

    // Add custom sites
    foreach ( $custom_sites as $site ) {
        $site_key = sanitize_title( $site['name'] );
        $config[ $site_key ] = array(
            'agents' => array_filter( explode( "\n", $site['agents'] ) ),
            'throttle' => floatval( $site['throttle'] ),
            'transient_key' => 'sbrt_' . $site_key . '_last_access'
        );
    }

    return apply_filters( 'sbrt_bot_config', $config );
}

/**
 * Identify bot request
 *
 * @since 2.4
 * @return array|false Bot config if request is from known crawler, false otherwise
 */
function sbrt_identify_bot_request() {
    $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $bots = sbrt_get_bot_config();
    
    foreach ( $bots as $bot_config ) {
        foreach ( $bot_config['agents'] as $agent ) {
            if ( false !== stripos( $user_agent, trim( $agent ) ) ) {
                return $bot_config;
            }
        }
    }
    return false;
}

/**
 * Handle bot request throttling
 *
 * @since 2.4
 * @param array $bot_config Bot configuration
 */
function sbrt_bot_request_throttle( $bot_config ) {
    $current_time = microtime( true );
    $last_access = get_transient( $bot_config['transient_key'] );

    if ( false !== $last_access ) {
        $time_diff = $current_time - $last_access;
        if ( $time_diff < $bot_config['throttle'] ) {
            sbrt_send_throttle_response();
        }
    }

    set_transient( 
        $bot_config['transient_key'], 
        $current_time, 
        (int) $bot_config['throttle'] + 1 
    );
}

/**
 * Send throttle response
 *
 * @since 2.4
 */
function sbrt_send_throttle_response() {
    status_header( 429 );
    header( 'Retry-After: 60' );
    wp_die( 
        esc_html__( 'Too Many Requests', 'social-bot-throttle' ),
        esc_html__( 'Too Many Requests', 'social-bot-throttle' ),
        array( 'response' => 429 )
    );
}

// Check for bot requests
if ( $bot_config = sbrt_identify_bot_request() ) {
    sbrt_bot_request_throttle( $bot_config );
}