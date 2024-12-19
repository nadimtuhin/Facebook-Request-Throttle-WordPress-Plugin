=== Social Bot Request Throttle ===
Contributors: nadimtuhin
Tags: social media, throttle, facebook, twitter, pinterest, crawler, bot, performance
Requires at least: 5.2
Tested up to: 6.4
Requires PHP: 7.2
Stable tag: 3.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Limit request frequency from social media web crawlers to prevent server overload.

== Description ==

Social Bot Request Throttle helps protect your WordPress site from excessive crawling by social media bots. It implements configurable throttling rules for various social media platforms including Facebook, Twitter, and Pinterest.

= Key Features =

* Configurable throttling for Facebook, Twitter, and Pinterest bots
* Custom bot throttling support
* Optional image request throttling
* Request logging and monitoring
* Customizable throttle intervals
* HTTP 429 response for rate-limited requests

= Supported Bots =

* Facebook (meta-externalagent, facebookexternalhit)
* Twitter (Twitterbot)
* Pinterest
* Custom bots (configurable)

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/social-bot-throttle` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Configure throttle settings through the plugin's settings page.

== Frequently Asked Questions ==

= What is the default throttle time? =

The default throttle time is 60 seconds for all social media bots.

= Can I customize throttle settings for different bots? =

Yes, you can set custom throttle intervals for each supported social media platform and add your own custom bot configurations.

= Does this affect regular users? =

No, the plugin only throttles requests from identified social media bots. Regular user traffic is not affected.

== Changelog ==

= 3.2 =
* Added custom bot support
* Improved logging functionality
* Added image request throttling option
* Enhanced error handling and logging

== Upgrade Notice ==

= 3.2 =
This version adds custom bot support and improved logging functionality. Upgrade for better control over social media bot traffic.

== Screenshots ==

1. Plugin settings page
2. Bot request logs
3. Custom bot configuration

== Development ==

* [GitHub Repository](https://nadimtuhin.com/social-bot-throttle)
* Report issues and contribute: [GitHub Issues](https://nadimtuhin.com/social-bot-throttle/issues) 