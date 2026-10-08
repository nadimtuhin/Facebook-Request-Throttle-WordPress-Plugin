=== Facebook Request Throttle ===
Contributors: nadimtuhin
Tags: facebook, meta, crawler, throttle, rate-limit
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 3.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Rate-limit Facebook and Meta crawlers to protect your server from excessive scraping load.

== Description ==

When a URL is shared on Facebook, its crawlers (`facebookexternalhit` and `meta-externalagent`)
can request it dozens of times in a few seconds. This plugin lets one crawler request through
per window (60 seconds by default) and answers the rest with `429 Too Many Requests` and a
`Retry-After` header. Regular visitors are never throttled.

**Features**

* Throttles `facebookexternalhit` and `meta-externalagent` (case-insensitive), each on its own clock
* Returns `429 Too Many Requests` with a `Retry-After` header
* Skips throttling for image requests (jpg, jpeg, png, gif, webp)
* Admin page under **Settings → FB Throttle Log**: the last 100 throttled requests (time, IP, URI, user agent) plus an allowed-hit counter
* Throttle duration set from the dashboard, WP-CLI or wp-config.php
* WP-CLI: `wp fb-throttle status`, `log`, `clear`, `duration`
* Auto-updates: minor releases install silently; major releases show an admin notice requiring manual approval
* Backward compatible: `define( 'NT_FACEBOOK_REQUEST_THROTTLE', 120 )` in `wp-config.php` still works (legacy `FACEBOOK_REQUEST_THROTTLE` also accepted)

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate via **Plugins → Installed Plugins**.
3. Visit **Settings → FB Throttle Log** to configure the throttle duration.

== Configuration ==

= Dashboard (recommended) =

Go to **Settings → FB Throttle Log** and set the throttle duration in seconds. Default is **60 seconds**. Changes apply to the next request.

= wp-config.php (advanced) =

Define the constant before WordPress loads the plugin:

    define( 'NT_FACEBOOK_REQUEST_THROTTLE', 120 ); // 2 minutes

The dashboard value takes priority. If neither is set, the plugin defaults to 60 seconds.

= Backward compatibility =

Existing installs using `FACEBOOK_REQUEST_THROTTLE` in `wp-config.php` continue to work with zero changes.

== Limitations ==

* **User-agent throttling is spoofable.** Each configured crawler UA gets its own throttle
  bucket, but anyone sending a fake `facebookexternalhit` user agent shares the real crawler's
  bucket and can force it into 429s. Facebook crawls from many IPs, so per-IP buckets would
  defeat the plugin's purpose. If starvation matters to
  you, rate-limit abusive IPs at the server/firewall level.
* **Without a persistent object cache** (Redis/Memcached), the throttle check is a
  read-then-write on a database transient. Under truly concurrent hits, one extra request per
  window can slip through. With a persistent object cache the plugin uses an atomic
  add-if-absent, closing that gap.
* **Multi-server setups** need a shared object cache; otherwise each web node keeps its own
  throttle clock.

== Frequently Asked Questions ==

= Will this affect normal visitors? =

No. The plugin only intercepts requests whose `User-Agent` contains `facebookexternalhit` or `meta-externalagent`. Regular visitors are never affected.

= Does it affect image crawling? =

No. Image requests (jpg, jpeg, png, gif, webp) bypass the throttle entirely so Open Graph images are always served.

= Where do I see what the crawler is doing? =

Go to **Settings → FB Throttle Log**. It lists the last 100 throttled requests with time, IP, URI and user agent, and shows a count of allowed hits.

= Can I add other crawlers to throttle? =

Yes. Add User-Agent substrings to the `$nt_user_agents_to_throttle` array in the plugin file.

= What does the crawler see when throttled? =

A `429 Too Many Requests` response with `Retry-After` set to the configured duration, the standard HTTP rate-limit signal.

= How do updates work? =

Minor releases (e.g. 3.0 → 3.1) are applied automatically via WordPress's built-in background updater. Major releases (e.g. 3.x → 4.x) are blocked from auto-update and shown as an admin notice; click "Update" to install them.

= Does uninstalling the plugin clean up the database? =

Yes. Deleting the plugin from the Plugins screen removes its options and transients on every site of the network.

== Screenshots ==

1. Settings and hit log: the admin page under Settings → FB Throttle Log showing the duration field and the request log table.

== Changelog ==

= 3.1 =
* User-Agent match is case-insensitive
* Each crawler gets its own throttle clock; atomic slot claim when a persistent object cache is present
* GitHub updates install into the existing plugin folder instead of a new one
* One admin notice per major update instead of two
* Hit log records throttled requests only; allowed hits are a counter (`wp fb-throttle log --status=allowed` returns no rows). Saves a database write per allowed hit
* Uninstall cleans every site on multisite

= 3.0 =
* WordPress native auto-update integration: minor releases auto-apply silently, major releases require manual approval
* Admin notice for major updates with link to release notes
* Prefixed constants: `NT_FACEBOOK_REQUEST_THROTTLE` and `NT_FACEBOOK_REQUEST_THROTTLE_LOG_LIMIT` (legacy names kept as aliases)
* `uninstall.php` added: database cleaned on plugin delete
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
* PHP 7.4 minimum declared; tested up to WordPress 6.9

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
Adds WordPress native auto-update support and database cleanup on uninstall. Backward compatible, no action needed. Minor releases will now auto-update; major releases will prompt you.

= 2.6 =
Throttle duration is now configurable from the dashboard. Existing `FACEBOOK_REQUEST_THROTTLE` constants in `wp-config.php` continue to work.
