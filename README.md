# Facebook Request Throttle

[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org/)
[![Tests](https://img.shields.io/badge/tests-29%20passing-brightgreen)](./tests)
[![License](https://img.shields.io/badge/license-MIT-blue)](./LICENSE)
[![Version](https://img.shields.io/badge/version-2.6-orange)](./CHANGELOG.md)

> Rate-limit Facebook and Meta crawlers to protect your server from excessive scraping load.

Facebook's web crawlers (`facebookexternalhit` and `meta-externalagent`) can hammer your
server when a URL gets shared — especially on viral posts. This plugin throttles those
requests to one per configured window and returns a proper `429 Too Many Requests` with a
`Retry-After` header so the crawler backs off gracefully.

---

## Features

- **Throttles** `facebookexternalhit` and `meta-externalagent` user agents
- **Returns** `429 Too Many Requests` with a `Retry-After` header
- **Skips** image requests (jpg, jpeg, png, gif, webp) — Open Graph images always served
- **Admin log** under Settings → FB Throttle Log — last 100 hits with timestamp, status, IP, URI, and user agent
- **Dashboard-configurable** throttle duration — no code edits needed
- **Backward compatible** — `define( 'FACEBOOK_REQUEST_THROTTLE', 120 )` in `wp-config.php` still works

---

## Installation

1. Clone or download into your plugins directory:
   ```
   wp-content/plugins/Facebook-Request-Throttle-WordPress-Plugin/
   ```
2. Activate via **Plugins → Installed Plugins** in the WordPress admin.
3. Visit **Settings → FB Throttle Log** to set your throttle duration.

---

## Configuration

### Dashboard (recommended)

Go to **Settings → FB Throttle Log** and set the throttle duration in seconds.
Default is **60 seconds**. Changes take effect immediately — no code edits needed.

### wp-config.php (advanced)

```php
define( 'FACEBOOK_REQUEST_THROTTLE', 120 ); // 2 minutes
```

The dashboard value takes priority over the constant. If neither is set, the plugin defaults to 60 seconds.

### Backward compatibility

Existing installs using `FACEBOOK_REQUEST_THROTTLE` in `wp-config.php` continue to work with zero changes.

---

## How it works

```
Facebook crawler hits your site
        │
        ▼
Is the User-Agent facebookexternalhit or meta-externalagent?
        │
   No ──┴── Yes
   │         │
   │         ▼
   │   Is it an image request?
   │         │
   │    Yes ─┴─ No
   │    │        │
   │    │        ▼
   │    │   Was the last hit within the throttle window?
   │    │         │
   │    │    Yes ─┴─ No
   │    │    │        │
   │    │    ▼        ▼
   │    │  429 +   200 OK +
   │    │  Retry-  update
   │    │  After   timestamp
   │    │
   └────┴──→ Pass through
```

---

## Testing the plugin

Use the included `testbot` script to send test requests from both Facebook/Meta user agents:

```bash
./testbot https://yoursite.com
```

Or via environment variable:

```bash
TESTBOT_URL=https://yoursite.com ./testbot
```

---

## Development

```bash
composer install
composer test        # PHPUnit — 29 tests
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for contribution guidelines.

---

## Changelog

### 2.6
- Dashboard setting for throttle duration (Settings → FB Throttle Log)
- `Retry-After` header reflects configured duration dynamically
- 29 PHPUnit tests covering all throttle and sanitize logic

### 2.5
- Added `meta-externalagent` support (Meta's newer crawler UA)
- Configurable user agent list via `$nt_user_agents_to_throttle`
- WordPress coding standards applied throughout

### 2.4
- Admin log page with clear button and nonce protection
- Log capped at 100 entries, newest first

---

## License

MIT — see [LICENSE](LICENSE).
