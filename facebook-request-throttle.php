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

// Initialize plugin
function nt_sbrt_social_bot_throttle_init() {
    add_action('admin_menu', 'nt_sbrt_add_admin_menu');
    add_action('admin_init', 'nt_sbrt_register_settings');
}
add_action('init', 'nt_sbrt_social_bot_throttle_init');

// Add menu item
function nt_sbrt_add_admin_menu() {
    add_options_page(
        'Social Bot Throttle Settings',
        'Social Bot Throttle',
        'manage_options',
        'social-bot-throttle',
        'nt_sbrt_settings_page'
    );
}

// Register settings
function nt_sbrt_register_settings() {
    register_setting('nt_sbrt_social_bot_throttle', 'nt_sbrt_facebook_throttle', 'floatval');
    register_setting('nt_sbrt_social_bot_throttle', 'nt_sbrt_facebook_agents', 'sanitize_textarea_field');
    register_setting('nt_sbrt_social_bot_throttle', 'nt_sbrt_twitter_throttle', 'floatval');
    register_setting('nt_sbrt_social_bot_throttle', 'nt_sbrt_twitter_agents', 'sanitize_textarea_field');
    register_setting('nt_sbrt_social_bot_throttle', 'nt_sbrt_pinterest_throttle', 'floatval');
    register_setting('nt_sbrt_social_bot_throttle', 'nt_sbrt_pinterest_agents', 'sanitize_textarea_field');
}

/**
 * Renders the settings page HTML
 *
 * @since 2.4
 * @return void
 */
function nt_sbrt_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Social Bot Throttle Settings', 'social-bot-throttle'); ?></h1>
        <div class="notice notice-info">
            <p><?php echo esc_html__('Configure throttle times and user agents for different social media crawlers.', 'social-bot-throttle'); ?></p>
        </div>
        
        <form method="post" action="options.php">
            <?php
            settings_fields('nt_sbrt_social_bot_throttle');
            do_settings_sections('nt_sbrt_social_bot_throttle');
            ?>
            <div class="card">
                <h2><?php echo esc_html__('Crawler Settings', 'social-bot-throttle'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Facebook Throttle Time (seconds)', 'social-bot-throttle'); ?></th>
                        <td>
                            <input type="number" 
                                   class="regular-text" 
                                   step="0.1" 
                                   min="0"
                                   name="nt_sbrt_facebook_throttle" 
                                   value="<?php echo esc_attr(get_option('nt_sbrt_facebook_throttle', DEFAULT_FACEBOOK_THROTTLE)); ?>" />
                            <p class="description"><?php echo esc_html__('Minimum time between Facebook crawler requests.', 'social-bot-throttle'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Facebook User Agents', 'social-bot-throttle'); ?></th>
                        <td>
                            <textarea name="nt_sbrt_facebook_agents" 
                                      class="large-text code" 
                                      rows="2"><?php echo esc_textarea(get_option('nt_sbrt_facebook_agents', "meta-externalagent\nfacebookexternalhit")); ?></textarea>
                            <p class="description"><?php echo esc_html__('Enter one user agent per line.', 'social-bot-throttle'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Twitter Throttle Time (seconds)', 'social-bot-throttle'); ?></th>
                        <td>
                            <input type="number" 
                                   class="regular-text"
                                   step="0.1"
                                   min="0" 
                                   name="nt_sbrt_twitter_throttle" 
                                   value="<?php echo esc_attr(get_option('nt_sbrt_twitter_throttle', DEFAULT_TWITTER_THROTTLE)); ?>" />
                            <p class="description"><?php echo esc_html__('Minimum time between Twitter crawler requests.', 'social-bot-throttle'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Twitter User Agents', 'social-bot-throttle'); ?></th>
                        <td>
                            <textarea name="nt_sbrt_twitter_agents"
                                      class="large-text code"
                                      rows="2"><?php echo esc_textarea(get_option('nt_sbrt_twitter_agents', "Twitterbot")); ?></textarea>
                            <p class="description"><?php echo esc_html__('Enter one user agent per line.', 'social-bot-throttle'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Pinterest Throttle Time (seconds)', 'social-bot-throttle'); ?></th>
                        <td>
                            <input type="number"
                                   class="regular-text"
                                   step="0.1"
                                   min="0"
                                   name="nt_sbrt_pinterest_throttle" 
                                   value="<?php echo esc_attr(get_option('nt_sbrt_pinterest_throttle', DEFAULT_PINTEREST_THROTTLE)); ?>" />
                            <p class="description"><?php echo esc_html__('Minimum time between Pinterest crawler requests.', 'social-bot-throttle'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Pinterest User Agents', 'social-bot-throttle'); ?></th>
                        <td>
                            <textarea name="nt_sbrt_pinterest_agents"
                                      class="large-text code"
                                      rows="2"><?php echo esc_textarea(get_option('nt_sbrt_pinterest_agents', "Pinterest")); ?></textarea>
                            <p class="description"><?php echo esc_html__('Enter one user agent per line.', 'social-bot-throttle'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Bot configurations
function nt_sbrt_get_social_bots_config() {
    return [
        'facebook' => [
            'agents' => array_filter(explode("\n", get_option('nt_sbrt_facebook_agents', "meta-externalagent\nfacebookexternalhit"))),
            'throttle' => floatval(get_option('nt_sbrt_facebook_throttle', DEFAULT_FACEBOOK_THROTTLE)),
            'transient_key' => 'nt_sbrt_facebook_last_access_time'
        ],
        'twitter' => [
            'agents' => array_filter(explode("\n", get_option('nt_sbrt_twitter_agents', "Twitterbot"))),
            'throttle' => floatval(get_option('nt_sbrt_twitter_throttle', DEFAULT_TWITTER_THROTTLE)),
            'transient_key' => 'nt_sbrt_twitter_last_access_time'
        ],
        'pinterest' => [
            'agents' => array_filter(explode("\n", get_option('nt_sbrt_pinterest_agents', "Pinterest"))),
            'throttle' => floatval(get_option('nt_sbrt_pinterest_throttle', DEFAULT_PINTEREST_THROTTLE)),
            'transient_key' => 'nt_sbrt_pinterest_last_access_time'
        ]
    ];
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
        nt_sbrt_send_throttle_response();
    } else {
        // Attempt to set last access time
        if (!nt_sbrt_set_last_access_time($bot_config['transient_key'], $current_time, $bot_config['throttle'])) {
            error_log("Failed to set last access time for bot crawler.");
            nt_sbrt_send_throttle_response();
        }
    }
}

/**
 * Send throttle response with appropriate headers
 */
function nt_sbrt_send_throttle_response() {
    status_header(429);
    header('Retry-After: 60');
    wp_die('Too Many Requests', 'Too Many Requests', ['response' => 429]);
}

// Main logic - only run throttle check for known bot requests
if ($bot_config = nt_sbrt_identify_bot_request()) {
    nt_sbrt_bot_request_throttle($bot_config);
}