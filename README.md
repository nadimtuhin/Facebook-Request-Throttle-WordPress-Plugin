<div align="center">

# Facebook Request Throttle

![Facebook Request Throttle: slow down Facebook's link-preview crawlers](https://raw.githubusercontent.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin/main/docs/images/hero.svg)

**Facebook's crawler asks again and again.** Dozens of requests in a few seconds, for one shared link.

`WordPress 5.0+` `PHP 7.4+` [`GPL-2.0`](LICENSE) [`★ 25`](https://github.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin)

</div>

<br>

When someone shares your URL on Facebook, `facebookexternalhit` and `meta-externalagent` can hit it dozens of times in seconds. A viral post keeps that going for hours. This plugin lets one crawler request through per window and tells the rest to wait. Regular visitors never touch the throttle.

<br>

![Get started](https://raw.githubusercontent.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin/main/docs/images/h-start.svg)

**1. Download the plugin.** Get the [latest release](https://github.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin/releases/latest) and unzip it into `wp-content/plugins/`.

**2. Activate it.** In WordPress, open **Plugins → Installed Plugins** and click Activate.

**3. Check that it works.** From a copy of this repo, run:

```bash
./testbot https://your-site.com
```

Use your own site's URL. On a fresh 60-second window you see one `200` and then four `429`s for each crawler. Want a different window? Go to **Settings → FB Throttle Log**.

<br>

![How it works](https://raw.githubusercontent.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin/main/docs/images/h-how.svg)

**One crawler request gets through per window.** The window is 60 seconds by default. The rest get `429 Too Many Requests` with a `Retry-After` header.

**Everything else passes.** Real visitors and image files (jpg, png, gif, webp) are never throttled, so previews can still load images.

<br>

![Need help](https://raw.githubusercontent.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin/main/docs/images/h-help.svg)

<details>
<summary>Will it block my real visitors?</summary>

No. It only looks at the User-Agent of Facebook's two crawlers. Everyone else passes straight through.

</details>

<details>
<summary>It does not look like it is working</summary>

Run `./testbot https://your-site.com` and read the status codes. The plugin lists the last 100 throttled requests under **Settings → FB Throttle Log**. If it is empty, no crawler has been throttled yet. See [Limitations](#limitations) for cases where the throttle can miss a hit.

</details>

<details>
<summary>How do I remove it?</summary>

Deactivate and delete it under **Plugins**. On uninstall it removes its options, on every site of a multisite network. Throttle transients expire on their own.

</details>

<details>
<summary>The throttle does not seem to see the crawlers</summary>

If a full-page cache (WP Super Cache, Cloudflare) serves the page, crawlers never reach PHP and the throttle does not see them.

</details>

<br>

![For developers](https://raw.githubusercontent.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin/main/docs/images/h-dev.svg)

Everything below is reference. You don't need it to get started.

[Requirements](#requirements) · [What it does](#what-it-does) · [Configuration](#configuration) · [WP-CLI](#wp-cli) · [Limitations](#limitations) · [Development](#development)

[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org/)
[![CI](https://github.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin/actions/workflows/ci.yml/badge.svg)](https://github.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin/actions/workflows/ci.yml)
[![License](https://img.shields.io/badge/license-GPL--2.0-blue)](./LICENSE)
[![Version](https://img.shields.io/badge/version-3.1-orange)](./CHANGELOG.md)

![Facebook Request Throttle](https://raw.githubusercontent.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin/main/docs/images/banner.png)

A WordPress plugin that rate-limits Facebook's link-preview crawlers.

When a URL is shared on Facebook, `facebookexternalhit` and `meta-externalagent` can request it dozens of times in a few seconds, and a viral post can keep that going for hours. This plugin lets one crawler request through per window (60 seconds by default) and answers the rest with `429 Too Many Requests` and a `Retry-After` header. Regular visitors never touch the throttle.

## Requirements

| | Minimum | Tested |
|---|---|---|
| PHP | 7.4 | 8.1, 8.2, 8.3 (CI) |
| WordPress | 5.0 | 6.9 |

The test suite needs PHP 8.1+ because of PHPUnit 10. The plugin itself runs on 7.4.

## What it does

- Matches `facebookexternalhit` and `meta-externalagent` in the User-Agent, case-insensitively.
- Gives each crawler its own throttle clock, so a burst from one doesn't block the other.
- Returns `429` with `Retry-After` set to the configured window.
- Lets image requests (jpg, jpeg, png, gif, webp) through, so Open Graph previews still render.
- Logs the last 100 throttled requests (time, IP, URI, user agent) under **Settings → FB Throttle Log** and counts allowed hits.
- Adds `wp fb-throttle` WP-CLI commands.
- Updates from GitHub releases: minor versions auto-install, major versions wait for you to click Update.
- Removes its options on uninstall, on every site of a multisite network. Throttle transients expire on their own.

## How it works

![First crawler request in each window gets 200, the rest get 429; image requests always pass](https://raw.githubusercontent.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin/main/docs/images/throttle-window.png)

```
request
  │
  ├─ User-Agent isn't a Facebook crawler ────────► pass through
  ├─ image file (jpg/png/gif/webp) ──────────────► pass through
  ├─ crawler's slot free for this window ────────► 200, start the window
  └─ slot already taken ─────────────────────────► 429 + Retry-After
```

With a persistent object cache (Redis, Memcached) the slot is claimed with an atomic `wp_cache_add`. Without one, the plugin falls back to a database transient.

## Installation

1. Download the [latest release](https://github.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin/releases/latest) and unzip it into `wp-content/plugins/`, or clone the repo there.
2. Activate it under **Plugins → Installed Plugins**.
3. Optional: change the window under **Settings → FB Throttle Log**.

## Configuration

The default window is 60 seconds. Set it in any of these, highest priority first:

1. **Dashboard:** Settings → FB Throttle Log. Accepts 1 to 86400 seconds and applies to the next request.
2. **WP-CLI:** `wp fb-throttle duration 120` writes the same option as the dashboard.
3. **wp-config.php:**

   ```php
   define( 'NT_FACEBOOK_REQUEST_THROTTLE', 120 );
   ```

   The older `FACEBOOK_REQUEST_THROTTLE` name still works.

To throttle other crawlers, add User-Agent substrings to `$nt_user_agents_to_throttle` in `facebook-request-throttle.php`.

## WP-CLI

```bash
wp fb-throttle status                         # window, allowed/throttled counts, last throttled hit
wp fb-throttle log --limit=50 --format=json   # throttled-hit log
wp fb-throttle clear                          # reset the log and the counter
wp fb-throttle duration [<seconds>]           # read or set the window
```

## Limitations

- **User agents can be spoofed.** A fake `facebookexternalhit` request uses the real crawler's slot and can push it into 429s. Facebook crawls from many IPs, so per-IP buckets would defeat the point. Block abusive IPs at the firewall.
- **Without a persistent object cache** the database path is read-then-write, so truly concurrent hits can let one extra request through per window.
- **With several web servers** each node keeps its own clock unless they share an object cache.

## Trying it against a site

`testbot` sends five requests per crawler User-Agent, one second apart:

```bash
./testbot https://example.com
TESTBOT_URL=https://example.com ./testbot
```

On a fresh 60-second window, expect one `200` and then four `429`s for each User-Agent.

## Development

```bash
composer install
composer test          # PHPUnit
vendor/bin/phpcs       # WordPress coding standards
```

CI runs both on PHP 8.1, 8.2 and 8.3. Release steps are in [CONTRIBUTING.md](CONTRIBUTING.md), version history in [CHANGELOG.md](CHANGELOG.md).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
