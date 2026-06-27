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
        $this->assertTrue(nt_is_request_from_facebook());
    }

    public function test_meta_externalagent_detected(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'meta-externalagent/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/crawler)';
        $this->assertTrue(nt_is_request_from_facebook());
    }

    public function test_googlebot_not_detected(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Googlebot/2.1 (+http://www.google.com/bot.html)';
        $this->assertFalse(nt_is_request_from_facebook());
    }

    public function test_empty_ua_not_detected(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = '';
        $this->assertFalse(nt_is_request_from_facebook());
    }

    // ── Image detection ───────────────────────────────────────────────────────

    /** @dataProvider imageExtensionProvider */
    public function test_image_extensions_detected(string $uri): void
    {
        $_SERVER['REQUEST_URI'] = $uri;
        $this->assertTrue(nt_is_image_request());
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
        $this->assertFalse(nt_is_image_request());
    }

    public function test_uri_with_query_string_image(): void
    {
        $_SERVER['REQUEST_URI'] = '/uploads/photo.jpg?w=300';
        $this->assertTrue(nt_is_image_request());
    }

    // ── Throttle logic ────────────────────────────────────────────────────────

    public function test_first_hit_is_allowed(): void
    {
        nt_facebook_request_throttle();

        $this->assertFalse($GLOBALS['_nt_die_called']);
    }

    public function test_first_hit_sets_transient(): void
    {
        nt_facebook_request_throttle();

        $this->assertNotFalse(get_transient('nt_facebook_last_access_time'));
    }

    public function test_second_hit_within_window_is_throttled(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/wp_die:429/');

        $GLOBALS['_nt_transients']['nt_facebook_last_access_time'] = microtime(true) - 5;

        nt_facebook_request_throttle();
    }

    public function test_throttled_response_sends_429_status(): void
    {
        $GLOBALS['_nt_transients']['nt_facebook_last_access_time'] = microtime(true) - 5;

        try {
            nt_facebook_request_throttle();
        } catch (\RuntimeException) {}

        $this->assertSame(429, $GLOBALS['_nt_die_status']);
    }

    public function test_hit_after_window_expires_is_allowed(): void
    {
        $GLOBALS['_nt_transients']['nt_facebook_last_access_time'] = microtime(true) - 61;

        nt_facebook_request_throttle();

        $this->assertFalse($GLOBALS['_nt_die_called']);
    }

    public function test_image_request_bypasses_throttle_within_window(): void
    {
        $GLOBALS['_nt_transients']['nt_facebook_last_access_time'] = microtime(true) - 1;
        $_SERVER['REQUEST_URI'] = '/wp-content/uploads/photo.jpg';

        nt_facebook_request_throttle();

        $this->assertFalse($GLOBALS['_nt_die_called']);
    }

    // ── Throttle duration (configurable) ─────────────────────────────────────

    public function test_default_throttle_duration_is_60(): void
    {
        // No option set — should fall back to constant (60)
        $this->assertSame(60.0, nt_get_throttle_duration());
    }

    public function test_dashboard_setting_overrides_constant(): void
    {
        $GLOBALS['_nt_options']['nt_throttle_duration'] = '120';
        $this->assertSame(120.0, nt_get_throttle_duration());
    }

    public function test_sanitize_clamps_below_minimum(): void
    {
        $this->assertSame(1, nt_sanitize_throttle_duration(0));
        $this->assertSame(1, nt_sanitize_throttle_duration(-50));
    }

    public function test_sanitize_clamps_above_maximum(): void
    {
        $this->assertSame(86400, nt_sanitize_throttle_duration(99999));
    }

    public function test_sanitize_casts_to_int(): void
    {
        $this->assertSame(45, nt_sanitize_throttle_duration('45.9'));
    }

    public function test_throttle_respects_configured_duration(): void
    {
        // Set duration to 30s; last hit was 20s ago — should throttle
        $GLOBALS['_nt_options']['nt_throttle_duration'] = '30';
        $GLOBALS['_nt_transients']['nt_facebook_last_access_time'] = microtime(true) - 20;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/wp_die:429/');

        nt_facebook_request_throttle();
    }

    public function test_throttle_allows_after_configured_duration(): void
    {
        // Set duration to 30s; last hit was 35s ago — should allow
        $GLOBALS['_nt_options']['nt_throttle_duration'] = '30';
        $GLOBALS['_nt_transients']['nt_facebook_last_access_time'] = microtime(true) - 35;

        nt_facebook_request_throttle();

        $this->assertFalse($GLOBALS['_nt_die_called']);
    }

    // ── WP-CLI commands ───────────────────────────────────────────────────────

    private function fresh_cli(): NT_Facebook_Throttle_CLI
    {
        WP_CLI::$lines   = [];
        WP_CLI::$success = [];
        return new NT_Facebook_Throttle_CLI();
    }

    public function test_cli_status_shows_duration(): void
    {
        update_option('nt_throttle_duration', 90);
        $cli = $this->fresh_cli();
        $cli->status();
        $this->assertStringContainsString('90s', WP_CLI::$lines[0]);
    }

    public function test_cli_status_shows_log_counts(): void
    {
        update_option('nt_facebook_throttle_log', [
            ['time' => 't1', 'status' => 'allowed',   'ip' => '', 'uri' => '', 'ua' => ''],
            ['time' => 't2', 'status' => 'throttled',  'ip' => '', 'uri' => '', 'ua' => ''],
        ]);
        $cli = $this->fresh_cli();
        $cli->status();
        $this->assertStringContainsString('allowed: 1', WP_CLI::$lines[1]);
        $this->assertStringContainsString('throttled: 1', WP_CLI::$lines[1]);
    }

    public function test_cli_clear_removes_log(): void
    {
        update_option('nt_facebook_throttle_log', [['time' => 't', 'status' => 'allowed', 'ip' => '', 'uri' => '', 'ua' => '']]);
        $cli = $this->fresh_cli();
        $cli->clear();
        $this->assertEmpty(get_option('nt_facebook_throttle_log', []));
        $this->assertStringContainsString('cleared', WP_CLI::$success[0]);
    }

    public function test_cli_duration_reads_current(): void
    {
        update_option('nt_throttle_duration', 45);
        $cli = $this->fresh_cli();
        $cli->duration([]);
        $this->assertStringContainsString('45s', WP_CLI::$lines[0]);
    }

    public function test_cli_duration_sets_value(): void
    {
        $cli = $this->fresh_cli();
        $cli->duration([120]);
        $this->assertSame(120, (int) get_option('nt_throttle_duration'));
        $this->assertStringContainsString('120s', WP_CLI::$success[0]);
    }

    public function test_cli_duration_clamps_to_max(): void
    {
        $cli = $this->fresh_cli();
        $cli->duration([999999]);
        $this->assertSame(86400, (int) get_option('nt_throttle_duration'));
    }

    public function test_cli_log_empty_message(): void
    {
        update_option('nt_facebook_throttle_log', []);
        $cli = $this->fresh_cli();
        $cli->log([], []);
        $this->assertStringContainsString('No log entries', WP_CLI::$lines[0]);
    }

    // ── Logging ───────────────────────────────────────────────────────────────

    public function test_allowed_hit_creates_log_entry(): void
    {
        nt_facebook_request_throttle();

        $log = get_option('nt_facebook_throttle_log', []);
        $this->assertCount(1, $log);
        $this->assertSame('allowed', $log[0]['status']);
    }

    public function test_throttled_hit_creates_throttled_log_entry(): void
    {
        $GLOBALS['_nt_transients']['nt_facebook_last_access_time'] = microtime(true) - 5;

        try { nt_facebook_request_throttle(); } catch (\RuntimeException) {}

        $log = get_option('nt_facebook_throttle_log', []);
        $this->assertCount(1, $log);
        $this->assertSame('throttled', $log[0]['status']);
    }

    public function test_log_entry_contains_expected_fields(): void
    {
        nt_facebook_request_throttle();

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
        nt_facebook_request_throttle();
        // Second hit — after window
        $GLOBALS['_nt_transients']['nt_facebook_last_access_time'] = microtime(true) - 61;
        $_SERVER['REQUEST_URI'] = '/second/';
        nt_facebook_request_throttle();

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

        nt_facebook_request_throttle();

        $log = get_option('nt_facebook_throttle_log', []);
        $this->assertCount(100, $log);
    }

    // ── Update checker ────────────────────────────────────────────────────────

    private function setHttpResponse(int $code, string $body): void
    {
        $GLOBALS['_nt_http_response'] = [
            'response' => ['code' => $code],
            'body'     => $body,
        ];
    }

    /** @test */
    public function test_update_checker_returns_latest_version_from_github(): void
    {
        delete_transient('nt_github_latest_version');
        $this->setHttpResponse(200, json_encode(['tag_name' => 'v9.9.9']));

        $result = nt_check_github_for_update();

        $this->assertSame('9.9.9', $result);
    }

    /** @test */
    public function test_update_checker_strips_leading_v(): void
    {
        delete_transient('nt_github_latest_version');
        $this->setHttpResponse(200, json_encode(['tag_name' => 'v2.9']));

        $this->assertSame('2.9', nt_check_github_for_update());
    }

    /** @test */
    public function test_update_checker_returns_false_on_http_error(): void
    {
        delete_transient('nt_github_latest_version');
        $GLOBALS['_nt_http_response'] = new WP_Error('http_request_failed', 'timeout');

        $this->assertFalse(nt_check_github_for_update());
    }

    /** @test */
    public function test_update_checker_returns_false_on_non_200(): void
    {
        delete_transient('nt_github_latest_version');
        $this->setHttpResponse(403, '{"message":"rate limited"}');

        $this->assertFalse(nt_check_github_for_update());
    }

    /** @test */
    public function test_update_checker_returns_false_on_missing_tag(): void
    {
        delete_transient('nt_github_latest_version');
        $this->setHttpResponse(200, json_encode(['foo' => 'bar']));

        $this->assertFalse(nt_check_github_for_update());
    }

    /** @test */
    public function test_update_checker_uses_cached_transient(): void
    {
        set_transient('nt_github_latest_version', '3.0.0', 3600);
        // HTTP stub would return something different — transient wins.
        $this->setHttpResponse(200, json_encode(['tag_name' => 'v9.0']));

        $this->assertSame('3.0.0', nt_check_github_for_update());
        delete_transient('nt_github_latest_version');
    }

    /** @test */
    public function test_update_notice_not_shown_when_up_to_date(): void
    {
        delete_transient('nt_github_latest_version');
        // Return same version as plugin — no update available.
        $this->setHttpResponse(200, json_encode(['tag_name' => 'v' . NT_PLUGIN_VERSION]));

        ob_start();
        nt_maybe_show_update_notice();
        $output = ob_get_clean();

        $this->assertEmpty($output);
    }

    /** @test */
    public function test_update_notice_shown_when_newer_version_exists(): void
    {
        delete_transient('nt_github_latest_version');
        $this->setHttpResponse(200, json_encode(['tag_name' => 'v9.9.9']));

        ob_start();
        nt_maybe_show_update_notice();
        $output = ob_get_clean();

        $this->assertStringContainsString('9.9.9', $output);
        $this->assertStringContainsString('notice-warning', $output);
        $this->assertStringContainsString('releases/latest', $output);
    }
}
