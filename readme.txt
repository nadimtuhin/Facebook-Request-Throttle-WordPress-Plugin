=== Facebook Request Throttle ===
Contributors: nadimtuhin
Tags: facebook, meta, crawler, throttle, rate-limit, performance, security
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 3.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

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
* Auto-updates: minor releases install silently; major releases show an admin notice requiring manual approval
* Backward compatible: `define( 'NT_FACEBOOK_REQUEST_THROTTLE', 120 )` in `wp-config.php` still works (legacy `FACEBOOK_REQUEST_THROTTLE` also accepted)

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate via **Plugins → Installed Plugins**.
3. Visit **Settings → FB Throttle Log** to configure the throttle duration.

== Configuration ==

= Dashboard (recommended) =

Go to **Settings → FB Throttle Log** and set the throttle duration in seconds. Default is **60 seconds**. Changes take effect immediately.

= wp-config.php (advanced) =

Define the constant before WordPress loads the plugin:

    define( 'NT_FACEBOOK_REQUEST_THROTTLE', 120 ); // 2 minutes

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

= How do updates work? =

Minor releases (e.g. 3.0 → 3.1) are applied automatically via WordPress's built-in background updater. Major releases (e.g. 3.x → 4.x) are blocked from auto-update and shown as an admin notice — you tap "Update" manually to approve them.

= Does uninstalling the plugin clean up the database? =

Yes. Deleting the plugin via the Plugins dashboard removes all stored options and transients (`nt_throttle_duration`, `nt_facebook_throttle_log`, and the GitHub release cache).

== Screenshots ==

1. **Settings & Hit Log** — the admin page under Settings → FB Throttle Log showing the duration field and the request log table.

== Changelog ==

= 3.0 =
* WordPress native auto-update integration: minor releases auto-apply silently, major releases require manual approval
* Admin notice for major updates with link to release notes
* Prefixed constants: `NT_FACEBOOK_REQUEST_THROTTLE` and `NT_FACEBOOK_REQUEST_THROTTLE_LOG_LIMIT` (legacy names kept as aliases)
* `uninstall.php` added — database cleaned on plugin delete
* `$_SERVER` values now properly sanitized with `sanitize_text_field()` / `wp_unslash()`
* 56 PHPUnit tests

= 2.9 =
* WP-CLI commands: `wp fb-throttle status`, `log`, `clear`, `duration`
* GitHub update checker with 12-hour transient cache

= 2.8 =
* Community files: CONTRIBUTING.md, CODE_OF_CONDUCT.md, SECURITY.md, CHANGELOG.md
* GitHub Actions CI workflow (PHP 7.4, 8.0, 8.1, 8.2)

= 2.7 =
* PHPCS clean under WordPress coding standard
* `index.php` silence file added
* PHP 7.4 minimum declared; tested up to WordPress 7.0

= 2.6 =
* Dashboard setting for throttle duration (Settings → FB Throttle Log)
* `Retry-After` header reflects configured duration dynamically
* Backward compatible: `FACEBOOK_REQUEST_THROTTLE` constant still honoured

= 2.5 =
* Support for `meta-externalagent` (Meta's newer crawler UA)
* Configurable user agent list via `$nt_user_agents_to_throttle`
* WordPress coding standards applied throughout

= 2.4 =
* Admin log page under Settings → FB Throttle Log
* Log capped at 100 entries, newest first
* Clear log button with nonce protection

== Upgrade Notice ==

= 3.0 =
Adds WordPress native auto-update support and database cleanup on uninstall. Backward compatible — no action needed. Minor releases will now auto-update; major releases will prompt you.

= 2.6 =
Throttle duration is now configurable from the dashboard. Existing `FACEBOOK_REQUEST_THROTTLE` constants in `wp-config.php` continue to work.
