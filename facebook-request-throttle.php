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

// Initialize plugin
function nt_sbrt_social_bot_throttle_init() {
    add_action('admin_menu', 'nt_sbrt_add_admin_menu');
    add_action('admin_init', 'nt_sbrt_register_settings');
    add_action('admin_enqueue_scripts', 'nt_sbrt_admin_styles');
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
    register_setting('nt_sbrt_social_bot_throttle', 'nt_sbrt_custom_sites', 'nt_sbrt_sanitize_custom_sites');
}

// Add admin styles
function nt_sbrt_admin_styles($hook) {
    if ($hook !== 'settings_page_social-bot-throttle') {
        return;
    }
    
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');
    
    // Add custom styles
    echo '<style>
        .sbrt-settings-wrap { max-width: 1200px; margin: 20px auto; }
        .sbrt-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .sbrt-card h2 { margin-top: 0; padding-bottom: 12px; border-bottom: 1px solid #eee; }
        .sbrt-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .sbrt-bot-config { background: #f8f9fa; border-radius: 4px; padding: 15px; }
        .sbrt-bot-config h3 { margin-top: 0; }
        .sbrt-input { width: 100%; max-width: 300px; }
        .sbrt-textarea { width: 100%; min-height: 80px; font-family: monospace; }
        .sbrt-custom-sites { margin-top: 30px; }
        .sbrt-custom-site { background: #f8f9fa; border-radius: 4px; padding: 20px; margin-bottom: 15px; }
        .sbrt-custom-site h4 { margin-top: 0; }
        .sbrt-buttons { margin-top: 20px; }
        .sbrt-remove-btn { color: #dc3232; }
        .sbrt-help-text { color: #666; font-style: italic; margin: 5px 0; }
    </style>';
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

/**
 * Renders the settings page HTML
 *
 * @since 2.4
 * @return void
 */
function nt_sbrt_settings_page() {
    $custom_sites = get_option('nt_sbrt_custom_sites', []);
    ?>
    <div class="wrap sbrt-settings-wrap">
        <h1><?php echo esc_html__('Social Bot Throttle Settings', 'social-bot-throttle'); ?></h1>
        
        <div class="notice notice-info is-dismissible">
            <p><strong><?php echo esc_html__('How it works:', 'social-bot-throttle'); ?></strong> 
            <?php echo esc_html__('Configure throttle times and user agents for different social media crawlers. The plugin will limit their request frequency based on these settings.', 'social-bot-throttle'); ?></p>
        </div>
        
        <form method="post" action="options.php">
            <?php
            settings_fields('nt_sbrt_social_bot_throttle');
            do_settings_sections('nt_sbrt_social_bot_throttle');
            ?>
            
            <div class="sbrt-card">
                <h2><?php echo esc_html__('Social Media Crawlers', 'social-bot-throttle'); ?></h2>
                
                <div class="sbrt-grid">
                    <!-- Facebook Settings -->
                    <div class="sbrt-bot-config">
                        <h3><span class="dashicons dashicons-facebook"></span> <?php echo esc_html__('Facebook', 'social-bot-throttle'); ?></h3>
                        <div class="sbrt-field">
                            <label class="sbrt-label"><?php echo esc_html__('Throttle Time (seconds)', 'social-bot-throttle'); ?></label>
                            <input type="number" 
                                   class="sbrt-input"
                                   step="0.1"
                                   min="0"
                                   name="nt_sbrt_facebook_throttle"
                                   value="<?php echo esc_attr(get_option('nt_sbrt_facebook_throttle', DEFAULT_FACEBOOK_THROTTLE)); ?>" />
                            <p class="sbrt-help-text"><?php echo esc_html__('Minimum time between Facebook crawler requests.', 'social-bot-throttle'); ?></p>
                        </div>
                        <div class="sbrt-field">
                            <label class="sbrt-label"><?php echo esc_html__('User Agents', 'social-bot-throttle'); ?></label>
                            <textarea name="nt_sbrt_facebook_agents"
                                      class="sbrt-textarea"><?php echo esc_textarea(get_option('nt_sbrt_facebook_agents', "meta-externalagent\nfacebookexternalhit")); ?></textarea>
                            <p class="sbrt-help-text"><?php echo esc_html__('Enter one user agent per line.', 'social-bot-throttle'); ?></p>
                        </div>
                    </div>

                    <!-- Twitter Settings -->
                    <div class="sbrt-bot-config">
                        <h3><span class="dashicons dashicons-twitter"></span> <?php echo esc_html__('Twitter', 'social-bot-throttle'); ?></h3>
                        <div class="sbrt-field">
                            <label class="sbrt-label"><?php echo esc_html__('Throttle Time (seconds)', 'social-bot-throttle'); ?></label>
                            <input type="number"
                                   class="sbrt-input"
                                   step="0.1"
                                   min="0"
                                   name="nt_sbrt_twitter_throttle"
                                   value="<?php echo esc_attr(get_option('nt_sbrt_twitter_throttle', DEFAULT_TWITTER_THROTTLE)); ?>" />
                            <p class="sbrt-help-text"><?php echo esc_html__('Minimum time between Twitter crawler requests.', 'social-bot-throttle'); ?></p>
                        </div>
                        <div class="sbrt-field">
                            <label class="sbrt-label"><?php echo esc_html__('User Agents', 'social-bot-throttle'); ?></label>
                            <textarea name="nt_sbrt_twitter_agents"
                                      class="sbrt-textarea"><?php echo esc_textarea(get_option('nt_sbrt_twitter_agents', "Twitterbot")); ?></textarea>
                            <p class="sbrt-help-text"><?php echo esc_html__('Enter one user agent per line.', 'social-bot-throttle'); ?></p>
                        </div>
                    </div>

                    <!-- Pinterest Settings -->
                    <div class="sbrt-bot-config">
                        <h3><span class="dashicons dashicons-pinterest"></span> <?php echo esc_html__('Pinterest', 'social-bot-throttle'); ?></h3>
                        <div class="sbrt-field">
                            <label class="sbrt-label"><?php echo esc_html__('Throttle Time (seconds)', 'social-bot-throttle'); ?></label>
                            <input type="number"
                                   class="sbrt-input"
                                   step="0.1"
                                   min="0"
                                   name="nt_sbrt_pinterest_throttle"
                                   value="<?php echo esc_attr(get_option('nt_sbrt_pinterest_throttle', DEFAULT_PINTEREST_THROTTLE)); ?>" />
                            <p class="sbrt-help-text"><?php echo esc_html__('Minimum time between Pinterest crawler requests.', 'social-bot-throttle'); ?></p>
                        </div>
                        <div class="sbrt-field">
                            <label class="sbrt-label"><?php echo esc_html__('User Agents', 'social-bot-throttle'); ?></label>
                            <textarea name="nt_sbrt_pinterest_agents"
                                      class="sbrt-textarea"><?php echo esc_textarea(get_option('nt_sbrt_pinterest_agents', "Pinterest")); ?></textarea>
                            <p class="sbrt-help-text"><?php echo esc_html__('Enter one user agent per line.', 'social-bot-throttle'); ?></p>
                        </div>
                    </div>
                </div>

                <div class="sbrt-custom-sites">
                    <h3><?php echo esc_html__('Custom Sites', 'social-bot-throttle'); ?></h3>
                    <div id="custom-sites">
                        <?php foreach ($custom_sites as $index => $site): ?>
                        <div class="sbrt-custom-site">
                            <h4><?php echo esc_html__('Custom Site', 'social-bot-throttle'); ?> <?php echo $index + 1; ?></h4>
                            <div class="sbrt-field">
                                <label class="sbrt-label"><?php echo esc_html__('Site Name', 'social-bot-throttle'); ?></label>
                                <input type="text"
                                       class="sbrt-input"
                                       name="nt_sbrt_custom_sites[<?php echo $index; ?>][name]"
                                       value="<?php echo esc_attr($site['name']); ?>" />
                            </div>
                            <div class="sbrt-field">
                                <label class="sbrt-label"><?php echo esc_html__('Throttle Time (seconds)', 'social-bot-throttle'); ?></label>
                                <input type="number"
                                       class="sbrt-input"
                                       step="0.1"
                                       min="0"
                                       name="nt_sbrt_custom_sites[<?php echo $index; ?>][throttle]"
                                       value="<?php echo esc_attr($site['throttle']); ?>" />
                            </div>
                            <div class="sbrt-field">
                                <label class="sbrt-label"><?php echo esc_html__('User Agents', 'social-bot-throttle'); ?></label>
                                <textarea name="nt_sbrt_custom_sites[<?php echo $index; ?>][agents]"
                                          class="sbrt-textarea"><?php echo esc_textarea($site['agents']); ?></textarea>
                                <p class="sbrt-help-text"><?php echo esc_html__('Enter one user agent per line.', 'social-bot-throttle'); ?></p>
                            </div>
                            <button type="button" class="button sbrt-remove-btn remove-site">
                                <span class="dashicons dashicons-trash"></span> <?php echo esc_html__('Remove Site', 'social-bot-throttle'); ?>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="sbrt-buttons">
                        <button type="button" class="button button-secondary" id="add-custom-site">
                            <span class="dashicons dashicons-plus-alt"></span> <?php echo esc_html__('Add Custom Site', 'social-bot-throttle'); ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php submit_button(null, 'primary', 'submit', true, ['id' => 'sbrt-save-settings']); ?>
        </form>
    </div>

    <script>
    jQuery(document).ready(function($) {
        var siteTemplate = `
            <div class="sbrt-custom-site">
                <h4><?php echo esc_html__('Custom Site', 'social-bot-throttle'); ?></h4>
                <div class="sbrt-field">
                    <label class="sbrt-label"><?php echo esc_html__('Site Name', 'social-bot-throttle'); ?></label>
                    <input type="text" class="sbrt-input" name="nt_sbrt_custom_sites[INDEX][name]" />
                </div>
                <div class="sbrt-field">
                    <label class="sbrt-label"><?php echo esc_html__('Throttle Time (seconds)', 'social-bot-throttle'); ?></label>
                    <input type="number" class="sbrt-input" step="0.1" min="0" 
                           name="nt_sbrt_custom_sites[INDEX][throttle]" 
                           value="<?php echo DEFAULT_CUSTOM_THROTTLE; ?>" />
                </div>
                <div class="sbrt-field">
                    <label class="sbrt-label"><?php echo esc_html__('User Agents', 'social-bot-throttle'); ?></label>
                    <textarea name="nt_sbrt_custom_sites[INDEX][agents]" class="sbrt-textarea"></textarea>
                    <p class="sbrt-help-text"><?php echo esc_html__('Enter one user agent per line.', 'social-bot-throttle'); ?></p>
                </div>
                <button type="button" class="button sbrt-remove-btn remove-site">
                    <span class="dashicons dashicons-trash"></span> <?php echo esc_html__('Remove Site', 'social-bot-throttle'); ?>
                </button>
            </div>
        `;

        $('#add-custom-site').click(function() {
            var newSite = siteTemplate.replace(/INDEX/g, $('.sbrt-custom-site').length);
            $('#custom-sites').append(newSite);
            updateSiteNumbers();
        });

        $(document).on('click', '.remove-site', function() {
            $(this).closest('.sbrt-custom-site').remove();
            updateSiteNumbers();
        });

        function updateSiteNumbers() {
            $('.sbrt-custom-site').each(function(index) {
                $(this).find('h4').text('<?php echo esc_html__('Custom Site', 'social-bot-throttle'); ?> ' + (index + 1));
                $(this).find('input, textarea').each(function() {
                    var name = $(this).attr('name');
                    $(this).attr('name', name.replace(/\[\d+\]/, '[' + index + ']'));
                });
            });
        }
    });
    </script>
    <?php
}

// Bot configurations
function nt_sbrt_get_social_bots_config() {
    $custom_sites = get_option('nt_sbrt_custom_sites', []);
    
    $config = [
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

    // Add custom sites to configuration
    foreach ($custom_sites as $index => $site) {
        $site_key = sanitize_title($site['name']);
        $config[$site_key] = [
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
function nt_sbrt_send_throttle_response() {
    status_header(429);
    header('Retry-After: 60');
    wp_die('Too Many Requests', 'Too Many Requests', ['response' => 429]);
}

// Main logic - only run throttle check for known bot requests
if ($bot_config = nt_sbrt_identify_bot_request()) {
    nt_sbrt_bot_request_throttle($bot_config);
}