<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ThrottleTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['_nt_transients'] = [];
        $GLOBALS['_nt_options']    = [];
        $GLOBALS['_nt_die_called'] = false;
        $GLOBALS['_nt_die_status'] = null;

        $_SERVER['HTTP_USER_AGENT'] = 'facebookexternalhit/1.1';
        $_SERVER['REQUEST_URI']     = '/some-page/';
        $_SERVER['REMOTE_ADDR']     = '127.0.0.1';
    }

    // ── UA detection ──────────────────────────────────────────────────────────

    public function test_facebookexternalhit_detected(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)';
        $this->assertTrue(nt_isRequestFromFacebook());
    }

    public function test_meta_externalagent_detected(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'meta-externalagent/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/crawler)';
        $this->assertTrue(nt_isRequestFromFacebook());
    }

    public function test_googlebot_not_detected(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Googlebot/2.1 (+http://www.google.com/bot.html)';
        $this->assertFalse(nt_isRequestFromFacebook());
    }

    public function test_empty_ua_not_detected(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = '';
        $this->assertFalse(nt_isRequestFromFacebook());
    }

    // ── Image detection ───────────────────────────────────────────────────────

    /** @dataProvider imageExtensionProvider */
    public function test_image_extensions_detected(string $uri): void
    {
        $_SERVER['REQUEST_URI'] = $uri;
        $this->assertTrue(nt_isImageRequest());
    }

    public static function imageExtensionProvider(): array
    {
        return [
            'jpg'  => ['/uploads/photo.jpg'],
            'jpeg' => ['/uploads/photo.jpeg'],
            'png'  => ['/uploads/logo.png'],
            'gif'  => ['/uploads/anim.gif'],
            'webp' => ['/uploads/image.webp'],
        ];
    }

    public function test_html_page_not_image(): void
    {
        $_SERVER['REQUEST_URI'] = '/about-us/';
        $this->assertFalse(nt_isImageRequest());
    }

    public function test_uri_with_query_string_image(): void
    {
        $_SERVER['REQUEST_URI'] = '/uploads/photo.jpg?w=300';
        $this->assertTrue(nt_isImageRequest());
    }

    // ── Throttle logic ────────────────────────────────────────────────────────

    public function test_first_hit_is_allowed(): void
    {
        nt_facebookRequestThrottle();

        $this->assertFalse($GLOBALS['_nt_die_called']);
    }

    public function test_first_hit_sets_transient(): void
    {
        nt_facebookRequestThrottle();

        $this->assertNotFalse(get_transient('nt_facebook_last_access_time'));
    }

    public function test_second_hit_within_window_is_throttled(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/wp_die:429/');

        $GLOBALS['_nt_transients']['nt_facebook_last_access_time'] = microtime(true) - 5;

        nt_facebookRequestThrottle();
    }

    public function test_throttled_response_sends_429_status(): void
    {
        $GLOBALS['_nt_transients']['nt_facebook_last_access_time'] = microtime(true) - 5;

        try {
            nt_facebookRequestThrottle();
        } catch (\RuntimeException) {}

        $this->assertSame(429, $GLOBALS['_nt_die_status']);
    }

    public function test_hit_after_window_expires_is_allowed(): void
    {
        $GLOBALS['_nt_transients']['nt_facebook_last_access_time'] = microtime(true) - 61;

        nt_facebookRequestThrottle();

        $this->assertFalse($GLOBALS['_nt_die_called']);
    }

    public function test_image_request_bypasses_throttle_within_window(): void
    {
        $GLOBALS['_nt_transients']['nt_facebook_last_access_time'] = microtime(true) - 1;
        $_SERVER['REQUEST_URI'] = '/wp-content/uploads/photo.jpg';

        nt_facebookRequestThrottle();

        $this->assertFalse($GLOBALS['_nt_die_called']);
    }

    // ── Logging ───────────────────────────────────────────────────────────────

    public function test_allowed_hit_creates_log_entry(): void
    {
        nt_facebookRequestThrottle();

        $log = get_option('nt_facebook_throttle_log', []);
        $this->assertCount(1, $log);
        $this->assertSame('allowed', $log[0]['status']);
    }

    public function test_throttled_hit_creates_throttled_log_entry(): void
    {
        $GLOBALS['_nt_transients']['nt_facebook_last_access_time'] = microtime(true) - 5;

        try { nt_facebookRequestThrottle(); } catch (\RuntimeException) {}

        $log = get_option('nt_facebook_throttle_log', []);
        $this->assertCount(1, $log);
        $this->assertSame('throttled', $log[0]['status']);
    }

    public function test_log_entry_contains_expected_fields(): void
    {
        nt_facebookRequestThrottle();

        $entry = get_option('nt_facebook_throttle_log', [])[0];
        $this->assertArrayHasKey('time',   $entry);
        $this->assertArrayHasKey('ip',     $entry);
        $this->assertArrayHasKey('ua',     $entry);
        $this->assertArrayHasKey('uri',    $entry);
        $this->assertArrayHasKey('status', $entry);
    }

    public function test_log_is_newest_first(): void
    {
        // First hit
        nt_facebookRequestThrottle();
        // Second hit — after window
        $GLOBALS['_nt_transients']['nt_facebook_last_access_time'] = microtime(true) - 61;
        $_SERVER['REQUEST_URI'] = '/second/';
        nt_facebookRequestThrottle();

        $log = get_option('nt_facebook_throttle_log', []);
        $this->assertSame('/second/', $log[0]['uri']);
    }

    public function test_log_capped_at_limit(): void
    {
        // Pre-fill with 100 entries
        $GLOBALS['_nt_options']['nt_facebook_throttle_log'] = array_fill(
            0, 100,
            ['time' => '', 'ip' => '', 'ua' => '', 'uri' => '', 'status' => 'allowed']
        );

        nt_facebookRequestThrottle();

        $log = get_option('nt_facebook_throttle_log', []);
        $this->assertCount(100, $log);
    }
}
