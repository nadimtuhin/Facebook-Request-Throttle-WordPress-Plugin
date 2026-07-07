# TASK-03 [admin] — Single admin notice on major updates

Owner: claude · Wave: rev.1 · Deps: none

<context>
Both `nt_maybe_show_update_notice` and `nt_maybe_show_major_update_notice` are hooked to
`admin_notices`. On a major update both fire → two banners. The major-notice callback owns the
major case; the generic callback should stand down for majors.
</context>

<decisions>
- Early-return in `nt_maybe_show_update_notice()` when `nt_is_major_upgrade()` is true.
  Rejected: unhooking one callback (loses the minor-update banner entirely); merging both
  callbacks into one (bigger diff, same behavior).
- Existing test `test_update_notice_shown_when_newer_version_exists` uses 9.9.9 (a major) —
  it must move to a minor version (3.1) to keep asserting the generic-notice path honestly.
</decisions>

<preflight>
```bash
# both callbacks currently emit for a major bump (manual: see red test)
grep -n "add_action( 'admin_notices'" facebook-request-throttle.php   # 2 hooks (unchanged after)
```
</preflight>

<tdd>
Red: with latest = 9.9.9 (major vs 3.0), running both callbacks yields exactly ONE
`notice-warning` in combined output — currently two. Also: generic notice still shows for a
minor bump (3.1). Green: early-return.
</tdd>

<dod>
- [x] red test failed for the right reason (2 notices counted)
- [x] green; minor-bump generic notice still renders
- [x] gate green
</dod>

<ac>
- ac(workspace): `bash backlog/checks.sh` → 0 failures.
- ac(behavior): unit-level only — admin_notices needs a full WP admin screen; the two callbacks
  are pure-renderable and asserted via output buffering (stated ceiling).
</ac>

<changelog>
- `facebook-request-throttle.php` — `nt_maybe_show_update_notice()` returns early when
  `nt_is_major_upgrade()` (major banner owned by `nt_maybe_show_major_update_notice()`).
- `tests/ThrottleTest.php` — +`test_major_update_renders_single_notice` (runs BOTH callbacks,
  asserts exactly one `notice-warning`); existing generic-notice test moved from 9.9.9 → 3.1
  so it keeps asserting its own (minor) path.

Behavior/AC proof (pasted, not described):
```
# red (right reason):
1) ThrottleTest::test_major_update_renders_single_notice
Failed asserting that 2 is identical to 1.
# green:
OK (59 tests, 80 assertions)
✓ all checks passed
```

Ceilings (named): asserted via output buffering of the two callbacks, not a rendered WP admin
screen — the hooks wiring (`add_action` lines) is trusted, not driven.
</changelog>
