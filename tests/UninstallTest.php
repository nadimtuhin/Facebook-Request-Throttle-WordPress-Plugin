<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

if (!defined('WP_UNINSTALL_PLUGIN')) {
    define('WP_UNINSTALL_PLUGIN', true);
}

// ── Multisite stubs (local to this file) ─────────────────────────────────
if (!function_exists('is_multisite')) {
    function is_multisite(): bool
    {
        return $GLOBALS['_nt_is_multisite'] ?? false;
    }
}

if (!function_exists('get_sites')) {
    function get_sites(array $args = []): array
    {
        $GLOBALS['_nt_get_sites_args'] = $args;
        return $GLOBALS['_nt_sites'] ?? [];
    }
}

if (!function_exists('switch_to_blog')) {
    function switch_to_blog(int $site_id): bool
    {
        $GLOBALS['_nt_switched_blogs'][] = "switch:{$site_id}";
        // Point the shared option/transient stores at this site's own arrays
        // so per-site cleanup can be observed independently.
        $GLOBALS['_nt_options_by_site'][$site_id]    ??= [];
        $GLOBALS['_nt_transients_by_site'][$site_id] ??= [];
        $GLOBALS['_nt_options']    = &$GLOBALS['_nt_options_by_site'][$site_id];
        $GLOBALS['_nt_transients'] = &$GLOBALS['_nt_transients_by_site'][$site_id];
        return true;
    }
}

if (!function_exists('restore_current_blog')) {
    function restore_current_blog(): bool
    {
        $GLOBALS['_nt_switched_blogs'][] = 'restore';
        return true;
    }
}

// Require the file exactly once under benign single-site state. A bare
// require can't exercise both branches, so scenarios below call the
// extracted dispatch function directly.
$GLOBALS['_nt_is_multisite'] = false;
require_once __DIR__ . '/../uninstall.php';

class UninstallTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['_nt_options']            = [];
        $GLOBALS['_nt_transients']         = [];
        $GLOBALS['_nt_transient_expiry']   = [];
        $GLOBALS['_nt_options_by_site']    = [];
        $GLOBALS['_nt_transients_by_site'] = [];
        $GLOBALS['_nt_switched_blogs']     = [];
        $GLOBALS['_nt_is_multisite']       = false;
        $GLOBALS['_nt_sites']              = [];
    }

    public function test_single_site_cleanup_runs_once(): void
    {
        $GLOBALS['_nt_options'] = [
            'nt_throttle_duration'              => 5,
            'nt_facebook_throttle_log'           => ['x'],
            'nt_facebook_throttle_allowed_count' => 10,
        ];
        $GLOBALS['_nt_transients'] = [
            'nt_facebook_last_access_time' => 123,
            'nt_github_latest_version'     => '1.0',
            'nt_github_release_data'       => 'x',
        ];

        nt_facebook_throttle_uninstall_dispatch();

        $this->assertSame([], $GLOBALS['_nt_options'], 'options should be cleared on single-site');
        $this->assertSame([], $GLOBALS['_nt_transients'], 'transients should be cleared on single-site');
        $this->assertSame([], $GLOBALS['_nt_switched_blogs'], 'single-site must not switch blogs');
    }

    public function test_multisite_switches_and_cleans_each_site_in_order(): void
    {
        $GLOBALS['_nt_is_multisite'] = true;
        $GLOBALS['_nt_sites']        = [1, 2, 3];

        foreach ($GLOBALS['_nt_sites'] as $site_id) {
            $GLOBALS['_nt_options_by_site'][$site_id] = [
                'nt_throttle_duration'              => 5,
                'nt_facebook_throttle_allowed_count' => 7,
            ];
            $GLOBALS['_nt_transients_by_site'][$site_id] = [
                'nt_facebook_last_access_time' => 999,
            ];
        }

        nt_facebook_throttle_uninstall_dispatch();

        $this->assertSame(
            ['switch:1', 'restore', 'switch:2', 'restore', 'switch:3', 'restore'],
            $GLOBALS['_nt_switched_blogs'],
            'switch_to_blog/restore_current_blog must run once per site, in order'
        );

        foreach ($GLOBALS['_nt_sites'] as $site_id) {
            $this->assertSame([], $GLOBALS['_nt_options_by_site'][$site_id], "site {$site_id} options should be cleaned");
            $this->assertSame([], $GLOBALS['_nt_transients_by_site'][$site_id], "site {$site_id} transients should be cleaned");
        }

        $this->assertSame(0, $GLOBALS['_nt_get_sites_args']['number'] ?? null, "get_sites must be called with number => 0");
    }

    public function test_cleanup_deletes_allowed_count_option_unconditionally(): void
    {
        $GLOBALS['_nt_options'] = [
            'nt_facebook_throttle_allowed_count' => 42,
        ];

        nt_facebook_throttle_uninstall_dispatch();

        $this->assertArrayNotHasKey('nt_facebook_throttle_allowed_count', $GLOBALS['_nt_options']);
    }
}
