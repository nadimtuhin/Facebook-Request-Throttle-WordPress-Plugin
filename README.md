# Facebook Request Throttle

A WordPress plugin that rate-limits requests from Facebook's web crawler (`facebookexternalhit` / `meta-externalagent`) to prevent excessive server load.

## Features

- Throttles Facebook/Meta crawlers to one request per 60 seconds (configurable)
- Returns `429 Too Many Requests` with a `Retry-After` header when throttled
- Skips throttling for image requests (jpg, jpeg, png, gif, webp)
- Admin log page — **Settings → FB Throttle Log** — shows the last 100 hits with timestamp, status (allowed/throttled), IP, URI, and user agent
- Covers both `facebookexternalhit` and `meta-externalagent` user agents

## Installation

1. Download or clone this repository into your WordPress plugins directory:
   ```
   wp-content/plugins/Facebook-Request-Throttle-WordPress-Plugin/
   ```
2. Activate the plugin via **Plugins** in the WordPress admin.

## Configuration

Edit `facebook-request-throttle.php` and change the `FACEBOOK_REQUEST_THROTTLE` constant:

```php
define('FACEBOOK_REQUEST_THROTTLE', 60.0); // seconds between allowed hits
```

## Testing the plugin

Use the included `testbot` script to send test requests from both Facebook/Meta user agents:

```bash
./testbot https://yoursite.com
```

Or set the URL via environment variable:

```bash
TESTBOT_URL=https://yoursite.com ./testbot
```

## Development

```bash
composer install
composer test      # runs PHPUnit (PHP 8.1+)
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for contribution guidelines.

## License

MIT — see [LICENSE](LICENSE).
