# TASK-06 [uninstall] — Multisite-aware uninstall

Owner: unassigned · Wave: backlog · Deps: none

<context>
`uninstall.php` deletes options/transients for the current site only. On multisite, per-site
options (`nt_throttle_duration`, `nt_facebook_throttle_log`) and transients survive on every
other site (audit unknown-unknown #7).
</context>

<decisions>
- In `uninstall.php`: if `is_multisite()`, loop `get_sites( array( 'fields' => 'ids' ) )` with
  `switch_to_blog()` / `restore_current_blog()` around the existing cleanup, else run it once.
  Extract the cleanup into a small function to avoid duplication.
- Ceiling: `get_sites` unbounded — fine below ~10k sites; pass `number => 0` deliberately and
  name it.
</decisions>

Critic pass 1 (opus) — GO, structural rulings LOCKED:
- `get_sites( array( 'fields' => 'ids', 'number' => 0 ) )` — `number => 0` is MANDATORY in code
  (WP_Site_Query defaults to 100; without it sites 101+ silently skipped — correctness bug).
- Extract BOTH the per-site cleanup AND the dispatch (`is_multisite() ? loop : once`) into named
  functions; call dispatch once at file bottom. Tests `define('WP_UNINSTALL_PLUGIN', true)`,
  require the file ONCE under benign stubs, then call the dispatch function directly per
  scenario (a bare require can't exercise both branches). The die-guard path is NOT
  unit-testable in-process (it `die`s) — accept by inspection, don't chase it.
- Cross-task: cleanup must ALSO delete TASK-08's allowed-hit counter option
  `nt_facebook_throttle_allowed_count`, or multisite uninstall reopens the gap this task closes.
- Plugin stores only per-site state (no site options/site transients) — per-site loop is
  sufficient, no network-level cleanup needed.

<tdd>
Red (new tests/UninstallTest.php, self-contained multisite stubs defined in the test file, NOT
in bootstrap.php): single-site → cleanup runs once on current site; multisite with 3 stub site
IDs → switch_to_blog called for each id + restore_current_blog after each, options/transients
deleted per site. Green: extract + dispatch + loop.
</tdd>

<ac>
- ac(workspace): `bash backlog/checks.sh` → 0 failures.
- ac(behavior): manual on a real multisite (document), or accept unit-level proof with stubs
  (stated ceiling).
</ac>

<changelog>
Files touched: `uninstall.php`, new `tests/UninstallTest.php` (this task only; no
other files edited).

Red (`vendor/bin/phpunit --filter UninstallTest` against unmodified `uninstall.php`,
behavioral miss — dispatch function didn't exist yet, not a syntax error):

```
PHPUnit 10.5.63 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.1.7
Configuration: /Users/nadimtuhin/opensource/Facebook-Request-Throttle-WordPress-Plugin/phpunit.xml

EEE                                                                 3 / 3 (100%)

Time: 00:00.018, Memory: 8.00 MB

There were 3 errors:

1) UninstallTest::test_single_site_cleanup_runs_once
Error: Call to undefined function nt_facebook_throttle_uninstall_dispatch()

/Users/nadimtuhin/opensource/Facebook-Request-Throttle-WordPress-Plugin/tests/UninstallTest.php:82

2) UninstallTest::test_multisite_switches_and_cleans_each_site_in_order
Error: Call to undefined function nt_facebook_throttle_uninstall_dispatch()

/Users/nadimtuhin/opensource/Facebook-Request-Throttle-WordPress-Plugin/tests/UninstallTest.php:104

3) UninstallTest::test_cleanup_deletes_allowed_count_option_unconditionally
Error: Call to undefined function nt_facebook_throttle_uninstall_dispatch()

/Users/nadimtuhin/opensource/Facebook-Request-Throttle-WordPress-Plugin/tests/UninstallTest.php:126

ERRORS!
Tests: 3, Assertions: 0, Errors: 3.
```

Green (after extracting `nt_facebook_throttle_uninstall_site()` and
`nt_facebook_throttle_uninstall_dispatch()` in `uninstall.php`):

```
PHPUnit 10.5.63 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.1.7
Configuration: /Users/nadimtuhin/opensource/Facebook-Request-Throttle-WordPress-Plugin/phpunit.xml

...                                                                 3 / 3 (100%)

Time: 00:00.014, Memory: 8.00 MB

OK (3 tests, 12 assertions)
```

Full gate (`bash backlog/checks.sh`), run after the parallel agent's changes to
`facebook-request-throttle.php` / `tests/ThrottleTest.php` / `tests/bootstrap.php`
were already in the working tree — 0 failures, no territory conflicts:

```
▶ unit tests (phpunit)
PHPUnit 10.5.63 by Sebastian Bergmann and contributors.
................................................................. 65 / 70 ( 92%)
.....                                                             70 / 70 (100%)
OK (70 tests, 107 assertions)
  ✓ unit tests (phpunit)
▶ lint (phpcs per .phpcs.xml)
  ✓ lint (phpcs per .phpcs.xml)

✓ all checks passed
```

Ceilings named:
- `get_sites( array( 'fields' => 'ids', 'number' => 0 ) )` is unbounded — fine
  below ~10k sites on a network; on larger networks it loads every site ID into
  memory in one query (documented in the function docblock in `uninstall.php`).
- The `WP_UNINSTALL_PLUGIN` die-guard at the top of `uninstall.php` is not
  unit-testable in-process (it calls `die`) — accepted by inspection, left
  untouched, not exercised by `tests/UninstallTest.php`.
- Real multisite behavior is not driven end-to-end; proof is unit-level against
  self-contained stubs (`is_multisite`, `get_sites`, `switch_to_blog`,
  `restore_current_blog`) defined in `tests/UninstallTest.php` per the locked
  ruling, not a live WP multisite network.
</changelog>
