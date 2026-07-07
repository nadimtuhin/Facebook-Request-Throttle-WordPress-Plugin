# TASK-05 [updater] — GitHub-zip folder rename + consolidate API calls

Owner: unassigned · Wave: backlog · Deps: none

<context>
`nt_inject_plugin_update()` feeds WP the GitHub `zipball_url`. GitHub zipballs extract to
`nadimtuhin-Facebook-Request-Throttle-WordPress-Plugin-<sha>/`, not the installed folder name —
WP installs it as a NEW plugin and deactivates the old one (classic pitfall; audit known-unknown
#3). Separately, `nt_check_github_for_update()` and `nt_get_github_release_data()` make two
unauthenticated calls to the same endpoint with two transients — the anonymous GitHub API limit
is 60 req/hr per IP.
</context>

<decisions>
- Add an `upgrader_source_selection` filter that renames the extracted source dir to
  `facebook-request-throttle/` when `$hook_extra['plugin']` matches this plugin.
- Collapse the two fetchers: `nt_check_github_for_update()` becomes a thin wrapper over
  `nt_get_github_release_data()['version']`; single transient `nt_github_release_data`.
- Also closes rev.1 critic-2 MINOR: the generic notice's major-bump stand-down and the major
  notice currently read DIFFERENT sources (version transient vs release-data transient) — a
  payload with `tag_name` but no `zipball_url` yields zero banners. One source ends that
  disagreement; add a red test for it (release data false → generic notice still shows... decide:
  simplest is both notices keying off the same consolidated fetcher).
</decisions>

Critic pass 1 (opus) — GO-WITH-CHANGES, rulings LOCKED:
- **CRITICAL**: rename target must be `dirname( plugin_basename( __FILE__ ) )` (the CURRENT
  install folder), NOT a hardcoded `facebook-request-throttle/` — a hardcoded slug changes the
  folder name on non-canonically-named installs, WP deletes the old dir, `active_plugins` points
  at the dead basename → plugin deactivates (the exact bug this task exists to fix).
- Filter contract: `add_filter( 'upgrader_source_selection', ..., 10, 4 )` (else `$hook_extra`
  missing); pass `$source` through if it's a WP_Error / `!isset($hook_extra['plugin'])` /
  doesn't match `plugin_basename( __FILE__ )`; use `global $wp_filesystem; $wp_filesystem->move(
  $source, $new, true )` (NOT `rename()` — breaks on FTP/SSH2 filesystems); return
  `trailingslashit()` of new path, or WP_Error on move failure.
- To actually close the rev.1 zero-banner MINOR: consolidated fetcher requires ONLY `tag_name`;
  `zip_url` becomes optional (`$body['zipball_url'] ?? ''`); `nt_inject_plugin_update()` gates
  separately on non-empty `zip_url`. Consolidation alone only makes the bug consistent.
- Existing tests that break and must be rewritten (part of green): update-checker tests stubbing
  `tag_name`-only payloads + seeding transient `nt_github_latest_version` (~5 tests around
  ThrottleTest ~:432-:510) — re-point to `nt_github_release_data` key; zip-optional fetcher keeps
  tag-only payloads working.
- New bootstrap stubs needed: `trailingslashit`, `$wp_filesystem` spy with `move()`.

<tdd>
Red: (1) filter renames to the current install dirname only when hook_extra matches this plugin;
other plugins'/themes' sources pass through untouched; WP_Error passes through. (2) consolidated
fetcher returns version from tag-only payload (zip optional); injector skips when zip_url empty
but notices still render. Green: implement filter + consolidated fetcher + test rewrites.
</tdd>

<ac>
- ac(workspace): `bash backlog/checks.sh` → 0 failures.
- ac(behavior): real proof needs a WP install performing an actual update — document as manual
  test in changelog (install v(N-1) zip, trigger update, plugin folder name unchanged, plugin
  stays active).
</ac>

<changelog>

**Files touched**: `facebook-request-throttle.php`, `tests/ThrottleTest.php`, `tests/bootstrap.php`.

**What shipped**:
- `nt_maybe_rename_update_source()` hooked on `upgrader_source_selection` (priority 10, 4 args).
  Renames the extracted GitHub-zip folder to `dirname( plugin_basename( __FILE__ ) )` — the
  CURRENT install's folder name, never a hardcoded slug. Passes through untouched for
  `WP_Error` sources, missing `$hook_extra['plugin']`, and other plugins/themes. Uses
  `global $wp_filesystem; $wp_filesystem->move( $source, $new, true )` (not `rename()`),
  returns `trailingslashit()` of the new path, or a `WP_Error` if the move fails.
- Consolidated `nt_check_github_for_update()` into a thin wrapper over
  `nt_get_github_release_data()['version']`; single transient `nt_github_release_data`
  everywhere (deleted the separate `nt_github_latest_version` transient + duplicate HTTP
  fetch — one GitHub API call now, not two).
- `nt_get_github_release_data()`: `zip_url` is now optional (`$body['zipball_url'] ?? ''`);
  only `tag_name` is required to resolve a version.
- `nt_inject_plugin_update()` now gates separately on non-empty `zip_url` (`empty( $transient
  ) || empty( $release['zip_url'] )`), so both the generic notice and the injector read the
  same consolidated data — closes the rev.1 critic-2 MINOR (tag-only payload no longer yields
  zero banners; the generic notice still renders, only the injector/package step is skipped).
- Bootstrap: added `trailingslashit()` stub and an `NT_Test_WP_Filesystem_Spy` class
  (`move($from,$to,$overwrite)` records calls); `$GLOBALS['wp_filesystem']` reset to a fresh
  spy in `ThrottleTest::setUp()`.
- Rewrote the ~8 existing update-checker/notice tests that seeded `nt_github_latest_version`
  or asserted on it, re-pointing them at `nt_github_release_data`.

**Red (before implementation)** — `vendor/bin/phpunit --filter 'test_source_rename|test_get_github_release_data_zip_url_optional|test_inject_plugin_update_skips_when_zip_url_empty|test_update_notice_shown_even_when_zip_url_missing|test_update_checker_'`:
```
There were 5 errors:

1) ThrottleTest::test_source_rename_moves_matching_plugin_to_current_folder_name
Error: Call to undefined function nt_maybe_rename_update_source()

2) ThrottleTest::test_source_rename_passes_through_other_plugin_sources
Error: Call to undefined function nt_maybe_rename_update_source()

3) ThrottleTest::test_source_rename_passes_through_when_hook_extra_missing_plugin
Error: Call to undefined function nt_maybe_rename_update_source()

4) ThrottleTest::test_source_rename_passes_through_wp_error
Error: Call to undefined function nt_maybe_rename_update_source()

5) ThrottleTest::test_source_rename_returns_wp_error_on_move_failure
Error: Call to undefined function nt_maybe_rename_update_source()

There were 2 failures:

1) ThrottleTest::test_update_checker_uses_cached_transient
Failed asserting that two strings are identical.
--- Expected
+++ Actual
@@ @@
-'3.0.0'
+'9.0'

2) ThrottleTest::test_get_github_release_data_zip_url_optional
Failed asserting that false is of type array.

Tests: 14, Assertions: 9, Errors: 5, Failures: 2.
```
(Failed for the right reasons: the rename filter didn't exist yet, and the fetcher still
read/required the old transient/zip-mandatory shape.)

**Green (after implementation)** — full gate:
```
OK (78 tests, 124 assertions)
```
```
$ vendor/bin/phpcs; echo "EXIT: $?"
EXIT: 0
```

**Ceilings named**: none new beyond the existing ponytail comments in the throttle-core
functions (untouched by this task).

**Manual test (ac(behavior), not automated)**: real proof needs a live WP install performing
an actual GitHub-zip update — install v(N-1), trigger the update from the Plugins dashboard,
confirm (a) the plugin folder name is unchanged after the update and (b) the plugin remains
active (not reinstalled as a new/deactivated entry). Not exercised in this pass — documented
here as the outstanding manual verification step.
</changelog>
