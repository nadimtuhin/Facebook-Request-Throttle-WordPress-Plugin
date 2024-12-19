<?php

/**
 * Social Bot Throttle Settings Page
 *
 * @package Social_Bot_Throttle
 * @since 2.4
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'init', 'nt_sbrt_social_bot_throttle_init' );

/**
 * Initialize plugin settings
 *
 * @since 2.4
 * @return void
 */
function nt_sbrt_social_bot_throttle_init() {
    add_action( 'admin_menu', 'nt_sbrt_add_admin_menu' );
    add_action( 'admin_init', 'nt_sbrt_register_settings' );
    add_action( 'admin_enqueue_scripts', 'nt_sbrt_admin_styles' );
    add_action( 'wp_ajax_sbrt_test_bot', 'nt_sbrt_test_bot_ajax' );
}

/**
 * Add menu item to WordPress admin
 *
 * @since 2.4
 * @return void
 */
function nt_sbrt_add_admin_menu() {
    add_options_page(
        esc_html__( 'Social Bot Throttle Settings', 'social-bot-throttle' ),
        esc_html__( 'Social Bot Throttle', 'social-bot-throttle' ),
        'manage_options',
        'social-bot-throttle',
        'nt_sbrt_settings_page'
    );
}

/**
 * Sanitize custom sites array
 *
 * @param array $sites Array of custom site settings.
 * @return array Sanitized settings.
 * @since 2.4
 */
function nt_sbrt_sanitize_custom_sites( $sites ) {
    if ( ! is_array( $sites ) ) {
        return array();
    }
    
    $sanitized = array();
    foreach ( $sites as $site ) {
        if ( empty( $site['name'] ) || empty( $site['agents'] ) ) {
            continue;
        }
        
        $sanitized[] = array(
            'name'            => sanitize_text_field( $site['name'] ),
            'agents'          => sanitize_textarea_field( $site['agents'] ), 
            'throttle'        => floatval( $site['throttle'] ),
            'throttle_images' => isset( $site['throttle_images'] ) ? $site['throttle_images'] : '0'
        );
    }
    return $sanitized;
}

/**
 * Register plugin settings
 *
 * @since 2.4
 * @return void
 */
function nt_sbrt_register_settings() {
    register_setting( 'nt_sbrt_social_bot_throttle', 'nt_sbrt_facebook_throttle', 'floatval' );
    register_setting( 'nt_sbrt_social_bot_throttle', 'nt_sbrt_facebook_agents', 'sanitize_textarea_field' );
    register_setting( 'nt_sbrt_social_bot_throttle', 'nt_sbrt_facebook_throttle_images', 'sanitize_text_field' );
    register_setting( 'nt_sbrt_social_bot_throttle', 'nt_sbrt_twitter_throttle', 'floatval' );
    register_setting( 'nt_sbrt_social_bot_throttle', 'nt_sbrt_twitter_agents', 'sanitize_textarea_field' );
    register_setting( 'nt_sbrt_social_bot_throttle', 'nt_sbrt_twitter_throttle_images', 'sanitize_text_field' );
    register_setting( 'nt_sbrt_social_bot_throttle', 'nt_sbrt_pinterest_throttle', 'floatval' );
    register_setting( 'nt_sbrt_social_bot_throttle', 'nt_sbrt_pinterest_agents', 'sanitize_textarea_field' );
    register_setting( 'nt_sbrt_social_bot_throttle', 'nt_sbrt_pinterest_throttle_images', 'sanitize_text_field' );
    register_setting( 'nt_sbrt_social_bot_throttle', 'nt_sbrt_custom_sites', 'nt_sbrt_sanitize_custom_sites' );
}

/**
 * Add admin styles
 *
 * @param string $hook The current admin page.
 * @return void
 * @since 2.4
 */
function nt_sbrt_admin_styles( $hook ) {
    if ( 'settings_page_social-bot-throttle' !== $hook ) {
        return;
    }
    
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_script( 'wp-color-picker' );
    
    // Add custom styles.
    wp_enqueue_style(
        'sbrt-admin-styles',
        SBRT_PLUGIN_URL . 'assets/css/admin-styles.css',
        array(),
        SBRT_VERSION
    );

    // Add inline styles for checkbox and button alignment.
    $custom_css = "
        .sbrt-field input[type='checkbox'] {
            margin-top: 2px;
            vertical-align: middle;
        }
        .button .dashicons {
            vertical-align: middle;
            margin-top: -2px;
            margin-right: 3px;
        }
        .sbrt-test-btn {
            margin-top: 10px;
            margin-bottom: 10px;
        }
    ";
    wp_add_inline_style( 'sbrt-admin-styles', $custom_css );
}

/**
 * AJAX handler for bot testing
 *
 * @since 2.4
 * @return void
 */
function nt_sbrt_test_bot_ajax() {
    check_ajax_referer( 'sbrt_test_bot', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $user_agent = sanitize_text_field( $_POST['user_agent'] );
    
    $args = array(
        'user-agent' => $user_agent,
        'timeout'    => 30,
    );

    $response = wp_remote_get( home_url(), $args );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( array(
            'status'  => 500,
            'message' => $response->get_error_message(),
        ) );
    }

    wp_send_json_success( array(
        'status'  => wp_remote_retrieve_response_code( $response ),
        'headers' => wp_remote_retrieve_headers( $response ),
        'body'    => wp_remote_retrieve_body( $response ),
    ) );
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
                      <div class="sbrt-field">
                          <label class="sbrt-label"><?php echo esc_html__('Throttle images', 'social-bot-throttle'); ?></label>
                          <input type="checkbox" name="nt_sbrt_facebook_throttle_images" value="1" <?php checked(get_option('nt_sbrt_facebook_throttle_images', '1') === '1', true); ?> />
                          <p class="sbrt-help-text"><?php echo esc_html__('Allow Facebook crawler to access images.', 'social-bot-throttle'); ?></p>
                      </div>
                      <button type="button" class="button button-secondary sbrt-test-btn" data-bot="facebook">
                          <?php echo esc_html__('Test Facebook Throttle', 'social-bot-throttle'); ?>
                      </button>
                      <div class="sbrt-test-results" id="facebook-test-results"></div>
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
                      <div class="sbrt-field">
                          <label class="sbrt-label"><?php echo esc_html__('Throttle images', 'social-bot-throttle'); ?></label>
                          <input type="checkbox" name="nt_sbrt_twitter_throttle_images" value="1" <?php checked(get_option('nt_sbrt_twitter_throttle_images', '1') === '1', true); ?> />
                          <p class="sbrt-help-text"><?php echo esc_html__('Allow Twitter crawler to access images.', 'social-bot-throttle'); ?></p>
                      </div>
                      <button type="button" class="button button-secondary sbrt-test-btn" data-bot="twitter">
                          <?php echo esc_html__('Test Twitter Throttle', 'social-bot-throttle'); ?>
                      </button>
                      <div class="sbrt-test-results" id="twitter-test-results"></div>
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
                      <div class="sbrt-field">
                          <label class="sbrt-label"><?php echo esc_html__('Throttle images', 'social-bot-throttle'); ?></label>
                          <input type="checkbox" name="nt_sbrt_pinterest_throttle_images" value="1" <?php checked(get_option('nt_sbrt_pinterest_throttle_images', '1') === '1', true); ?> />
                          <p class="sbrt-help-text"><?php echo esc_html__('Allow Pinterest crawler to access images.', 'social-bot-throttle'); ?></p>
                      </div>
                      <button type="button" class="button button-secondary sbrt-test-btn" data-bot="pinterest">
                          <?php echo esc_html__('Test Pinterest Throttle', 'social-bot-throttle'); ?>
                      </button>
                      <div class="sbrt-test-results" id="pinterest-test-results"></div>
                  </div>
              </div>

              <div class="sbrt-custom-sites">
                  <h3><?php echo esc_html__('Custom Sites', 'social-bot-throttle'); ?></h3>
                  <div id="custom-sites">
                      <?php foreach ($custom_sites as $index => $site): ?>
                      <div class="sbrt-custom-site">
                          <div class="sbrt-custom-site-header">
                              <h3>
                                  <?php echo esc_html($site['name'] ?: __('New Custom Site', 'social-bot-throttle')); ?>
                              </h3>
                              <span class="sbrt-toggle-indicator"></span>
                          </div>
                          <div class="sbrt-custom-site-content">
                              <div class="sbrt-field">
                                  <label><?php echo esc_html__('Site Name', 'social-bot-throttle'); ?></label>
                                  <input type="text"
                                         class="regular-text"
                                         name="nt_sbrt_custom_sites[<?php echo $index; ?>][name]"
                                         value="<?php echo esc_attr($site['name']); ?>" />
                              </div>
                              <div class="sbrt-field">
                                  <label><?php echo esc_html__('Throttle Time (seconds)', 'social-bot-throttle'); ?></label>
                                  <input type="number"
                                         class="small-text"
                                         step="0.1"
                                         min="0"
                                         name="nt_sbrt_custom_sites[<?php echo $index; ?>][throttle]"
                                         value="<?php echo esc_attr($site['throttle']); ?>" />
                              </div>
                              <div class="sbrt-field">
                                  <label><?php echo esc_html__('User Agents', 'social-bot-throttle'); ?></label>
                                  <textarea name="nt_sbrt_custom_sites[<?php echo $index; ?>][agents]"
                                            class="large-text code"
                                            rows="4"><?php echo esc_textarea($site['agents']); ?></textarea>
                                  <p class="sbrt-help-text"><?php echo esc_html__('Enter one user agent per line.', 'social-bot-throttle'); ?></p>
                              </div>
                              <div class="sbrt-field">
                                  <label><?php echo esc_html__('Throttle images', 'social-bot-throttle'); ?></label>
                                  <input type="checkbox" name="nt_sbrt_custom_sites[<?php echo $index; ?>][throttle_images]" value="1" <?php checked(isset($site['allow_images']) && $site['allow_images'] === '1', true); ?> />
                                  <p class="sbrt-help-text"><?php echo esc_html__('Allow this custom site to access images.', 'social-bot-throttle'); ?></p>
                              </div>
                              <button type="button" class="button button-secondary sbrt-test-btn" data-bot="custom-<?php echo $index; ?>">
                                  <?php echo esc_html__('Test Bot Throttle', 'social-bot-throttle'); ?>
                              </button>
                              <div class="sbrt-test-results" id="custom-<?php echo $index; ?>-test-results"></div>
                              <button type="button" class="button button-link-delete sbrt-remove-btn">
                                  <span class="dashicons dashicons-trash"></span> <?php echo esc_html__('Remove Site', 'social-bot-throttle'); ?>
                              </button>
                          </div>
                      </div>
                      <?php endforeach; ?>
                  </div>
                  <div class="sbrt-add-new">
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
              <div class="sbrt-custom-site-header">
                  <h3><?php echo esc_html__('New Custom Site', 'social-bot-throttle'); ?></h3>
                  <span class="sbrt-toggle-indicator"></span>
              </div>
              <div class="sbrt-custom-site-content">
                  <div class="sbrt-field">
                      <label><?php echo esc_html__('Site Name', 'social-bot-throttle'); ?></label>
                      <input type="text" class="regular-text" name="nt_sbrt_custom_sites[INDEX][name]" />
                  </div>
                  <div class="sbrt-field">
                      <label><?php echo esc_html__('Throttle Time (seconds)', 'social-bot-throttle'); ?></label>
                      <input type="number" class="small-text" step="0.1" min="0" 
                             name="nt_sbrt_custom_sites[INDEX][throttle]" 
                             value="<?php echo DEFAULT_CUSTOM_THROTTLE; ?>" />
                  </div>
                  <div class="sbrt-field">
                      <label><?php echo esc_html__('User Agents', 'social-bot-throttle'); ?></label>
                      <textarea name="nt_sbrt_custom_sites[INDEX][agents]" 
                                class="large-text code" 
                                rows="4"></textarea>
                      <p class="sbrt-help-text"><?php echo esc_html__('Enter one user agent per line.', 'social-bot-throttle'); ?></p>
                  </div>
                  <div class="sbrt-field">
                      <label><?php echo esc_html__('Throttle images', 'social-bot-throttle'); ?></label>
                      <input type="checkbox" name="nt_sbrt_custom_sites[INDEX][throttle_images]" value="1" />
                      <p class="sbrt-help-text"><?php echo esc_html__('Allow this custom site to access images.', 'social-bot-throttle'); ?></p>
                  </div>
                  <button type="button" class="button button-secondary sbrt-test-btn" data-bot="custom-INDEX">
                      <?php echo esc_html__('Test Bot Throttle', 'social-bot-throttle'); ?>
                  </button>
                  <div class="sbrt-test-results" id="custom-INDEX-test-results"></div>
                  <button type="button" class="button button-link-delete sbrt-remove-btn">
                      <span class="dashicons dashicons-trash"></span> <?php echo esc_html__('Remove Site', 'social-bot-throttle'); ?>
                  </button>
              </div>
          </div>
      `;

      $('#add-custom-site').click(function() {
          var newSite = siteTemplate.replace(/INDEX/g, $('.sbrt-custom-site').length);
          $('#custom-sites').append(newSite);
          updateSiteNumbers();
      });

      $(document).on('click', '.sbrt-remove-btn', function() {
          $(this).closest('.sbrt-custom-site').remove();
          updateSiteNumbers();
      });

      $(document).on('click', '.sbrt-custom-site-header', function() {
          $(this).closest('.sbrt-custom-site').toggleClass('closed');
      });

      $(document).on('input', 'input[name$="[name]"]', function() {
          var siteName = $(this).val() || '<?php echo esc_html__('New Custom Site', 'social-bot-throttle'); ?>';
          $(this).closest('.sbrt-custom-site').find('.sbrt-custom-site-header h3').text(siteName);
      });

      function updateSiteNumbers() {
          $('.sbrt-custom-site').each(function(index) {
              $(this).find('input, textarea, select').each(function() {
                  var name = $(this).attr('name');
                  $(this).attr('name', name.replace(/\[\d+\]/, '[' + index + ']'));
              });
          });
      }

      // Test button functionality
      $('.sbrt-test-btn').click(function() {
          var bot = $(this).data('bot');
          var resultsDiv = $('#' + bot + '-test-results');
          var userAgent = '';
          
          // Get the first user agent for the bot type
          if (bot === 'facebook') {
              userAgent = $('textarea[name="nt_sbrt_facebook_agents"]').val().split('\n')[0];
          } else if (bot === 'twitter') {
              userAgent = $('textarea[name="nt_sbrt_twitter_agents"]').val().split('\n')[0];
          } else if (bot === 'pinterest') {
              userAgent = $('textarea[name="nt_sbrt_pinterest_agents"]').val().split('\n')[0];
          } else if (bot.startsWith('custom-')) {
              var index = bot.split('-')[1];
              userAgent = $('textarea[name="nt_sbrt_custom_sites[' + index + '][agents]"]').val().split('\n')[0];
          }

          if (!userAgent) {
              resultsDiv.prepend('<div class="sbrt-test-result error">Please configure at least one user agent.</div>');
              return;
          }

          $(this).prop('disabled', true);
          resultsDiv.prepend('<div class="sbrt-test-result">Testing with user agent: ' + userAgent + '</div>');

          $.ajax({
              url: ajaxurl,
              type: 'POST',
              data: {
                  action: 'sbrt_test_bot',
                  nonce: '<?php echo wp_create_nonce('sbrt_test_bot'); ?>',
                  user_agent: userAgent
              },
              success: function(response) {
                  var status = response.data.status;
                  var statusClass = status === 429 ? 'success' : (status === 200 ? 'info' : 'error');
                  var timestamp = new Date().toLocaleString();
                  
                  resultsDiv.prepend(
                      '<div class="sbrt-test-result ' + statusClass + '">' +
                      '[' + timestamp + '] Status: ' + status +
                      (status === 429 ? ' (Throttled successfully)' : '') +
                      '</div>'
                  );
              },
              error: function(xhr) {
                  var timestamp = new Date().toLocaleString();
                  resultsDiv.prepend(
                      '<div class="sbrt-test-result error">' +
                      '[' + timestamp + '] Error: Failed to test bot' +
                      '</div>'
                  );
              },
              complete: function() {
                  $('.sbrt-test-btn[data-bot="' + bot + '"]').prop('disabled', false);
              }
          });
      });
  });
  </script>
  <?php
}