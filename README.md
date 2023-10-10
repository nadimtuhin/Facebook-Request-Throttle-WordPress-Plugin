# Facebook Request Throttle WordPress Plugin

Facebook Request Throttle is a WordPress plugin designed to limit the request frequency from Facebook's web crawler. This ensures that your website does not experience excessive loads from rapid and frequent requests by Facebook's web crawler.

## Features:
- Throttle the request rate from Facebook's web crawler.
- Return a `503 Service Temporarily Unavailable` response if the requests are too frequent.
- Return a `429 Too Many Requests` response if there's a problem recording the access time of the Facebook web crawler.
- Logging capabilities to help diagnose any potential issues.

## Installation:
1. Download the plugin code.
2. Upload the `facebook-request-throttle` folder to the `/wp-content/plugins/` directory of your WordPress site.
3. Activate the plugin through the 'Plugins' menu in WordPress.

## Configuration:
By default, the plugin limits requests from Facebook's web crawler to once every 2 seconds. If you wish to change this frequency, you can modify the `FACEBOOK_REQUEST_THROTTLE` constant value in the main plugin file.

```php
define('FACEBOOK_REQUEST_THROTTLE', 2.0); // Number of seconds permitted between each hit from facebookexternalhit
```

## Usage:
Once activated, the plugin will automatically monitor incoming requests. If a request is identified as coming from Facebook's web crawler and violates the throttle limit, the appropriate HTTP response will be returned.

## Troubleshooting:
1. **No last access time found**: If you see this error message in your logs, it means the plugin couldn't retrieve the last access time. This could be a fresh request from the web crawler after a long time.
2. **Failed to set last access time for Facebook web crawler**: If this error message appears in your logs, it means the plugin failed to store the last access time. This could be due to a variety of reasons, including database issues or transient failures.

## Author:
Nadim Tuhin  
[Website](https://nadimtuhin.com)

## License:
This project is open-source. Feel free to use, modify, and distribute it as you see fit. However, proper attribution is appreciated.

## Contributing:
If you find any issues or would like to add new features, feel free to open a pull request or raise an issue on this GitHub repository.

---

For any additional assistance or queries, visit the author's [website](https://nadimtuhin.com).
