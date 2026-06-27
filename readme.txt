=== Facebook Request Throttle ===
Contributors: nadimtuhin
Tags: facebook, meta, crawler, throttle, rate-limit, performance, security
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 3.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Rate-limit Facebook and Meta crawlers to protect your server from excessive scraping load.

== Description ==

Facebook's web crawlers (`facebookexternalhit` and `meta-externalagent`) can hammer your
server when it shares a URL — especially on high-traffic posts. This plugin throttles those
requests to one per configured window and returns a proper `429 Too Many Requests` with a
`Retry-After` header so the crawler backs off gracefully.

**Features**

* Throttles `facebookexternalhit` and `meta-externalagent` user agents
* Returns `429 Too Many Requests` with a `Retry-After` header
* Skips throttling for image requests (jpg, jpeg, png, gif, webp)
* Admin log page under **Settings → FB Throttle Log** — last 100 hits with timestamp, status, IP, URI, and user agent
* Configurable throttle duration from the dashboard — no code edits needed
* Backward compatible: `define( 'FACEBOOK_REQUEST_THROTTLE', 120 )` in `wp-config.php` still works

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate via **Plugins → Installed Plugins**.
3. Visit **Settings → FB Throttle Log** to configure the throttle duration.

== Configuration ==

= Dashboard (recommended) =

Go to **Settings → FB Throttle Log** and set the throttle duration in seconds. Default is **60 seconds**. Changes take effect immediately.

= wp-config.php (advanced) =

Define the constant before WordPress loads the plugin:

    define( 'FACEBOOK_REQUEST_THROTTLE', 120 ); // 2 minutes

The dashboard value takes priority. If neither is set, the plugin defaults to 60 seconds.

= Backward compatibility =

Existing installs using `FACEBOOK_REQUEST_THROTTLE` in `wp-config.php` continue to work with zero changes.

== Frequently Asked Questions ==

= Will this affect normal visitors? =

No. The plugin only intercepts requests whose `User-Agent` contains `facebookexternalhit` or `meta-externalagent`. Regular visitors are never affected.

= Does it affect image crawling? =

No. Image requests (jpg, jpeg, png, gif, webp) bypass the throttle entirely so Open Graph images are always served.

= Where do I see what the crawler is doing? =

Go to **Settings → FB Throttle Log**. The last 100 requests are shown with timestamp, allowed/throttled status, IP, URI, and user agent.

= Can I add other crawlers to throttle? =

Yes — edit the `$nt_user_agents_to_throttle` array in the plugin file or hook into it before the plugin runs.

= What does the crawler see when throttled? =

A `429 Too Many Requests` response with a `Retry-After` header set to the configured duration. This is the correct HTTP signal for rate limiting — well-behaved crawlers will back off and retry.

== Changelog ==

= 2.6 =
* Added dashboard setting for throttle duration (Settings → FB Throttle Log)
* `Retry-After` header now reflects the configured duration dynamically
* Backward compatible: `FACEBOOK_REQUEST_THROTTLE` constant still honoured
* 29 PHPUnit tests covering all throttle logic

= 2.5 =
* Added support for `meta-externalagent` (Meta's newer crawler UA)
* Configurable user agent list via `$nt_user_agents_to_throttle`
* Applied WordPress coding standards throughout

= 2.4 =
* Admin log page under Settings → FB Throttle Log
* Log capped at 100 entries, newest first
* Clear log button with nonce protection

== Upgrade Notice ==

= 2.6 =
Throttle duration is now configurable from the dashboard. Existing `FACEBOOK_REQUEST_THROTTLE` constants in `wp-config.php` continue to work.
