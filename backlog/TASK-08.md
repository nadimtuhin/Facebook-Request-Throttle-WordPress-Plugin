# TASK-08 [perf] — Log write amplification

Owner: unassigned · Wave: backlog · Deps: TASK-04 (throttle core settles first)

<context>
`nt_log()` runs `get_option` + `update_option` (a DB write of the full 100-entry array) on EVERY
allowed crawler hit (audit unknown-unknown #10). On a busy site with a 1s-granularity crawler
this is a serialized ~100-row array rewritten once per allowed hit per UA bucket — the throttle
protects the site from Facebook, then spends a DB write per hit on bookkeeping.
</context>

<decisions>
- Direction (pick at implementation time, cheapest that answers the admin's real question
  "is the throttle working?"):
  a) log only throttled hits + keep a single counter option for allowed hits (1 tiny write vs
     full-array rewrite), or
  b) sample allowed hits (1-in-N), or
  c) keep full logging but debounce via object cache and flush periodically (only worth it if
     (a) proves insufficient).
  Bias: (a) — the throttled entries are the signal; allowed hits are volume.
</decisions>

Critic pass 1 (opus) — GO-WITH-CHANGES on direction (a), rulings LOCKED:
- Honest framing: this eliminates the per-hit FULL-ARRAY serialization/rewrite (multi-KB row),
  not the write COUNT (counter still writes once per allowed hit; log was already
  autoload=false). Say so in changelog.
- Counter option: `nt_facebook_throttle_allowed_count`, `update_option(..., false)` (autoload
  off). Name is load-bearing — TASK-06's uninstall cleanup deletes it.
- Coherence (all required): CLI `status()` reads the counter (currently computes allowed =
  total - throttled from the log → would silently show 0); admin `nt_clear_log` AND CLI
  `clear()` also reset the counter; admin page copy updated (log now shows throttled hits only)
  + render "Allowed hits: N"; `wp fb-throttle log --status=allowed` returns no rows — document
  the behavior change in readme/changelog.
- Existing tests that break and must be rewritten (part of green):
  `test_cli_status_shows_log_counts` (seed counter, not allowed rows),
  `test_allowed_hit_creates_log_entry`, `test_log_entry_contains_expected_fields`,
  `test_log_is_newest_first` (allowed hits no longer append — re-anchor on throttled entries /
  counter asserts).
- Information trade documented: per-allowed-hit ip/uri/ua rows are discarded — a decision, not
  a silent regression.

<tdd>
Red: allowed hit does NOT grow the log array but increments `nt_facebook_throttle_allowed_count`;
throttled hit still appends a log row; CLI `status` reports counter-based allowed count; clear
resets counter. Green: implement + rewrite the 4 enumerated tests.
</tdd>

<ac>
- ac(workspace): `bash backlog/checks.sh` → 0 failures.
- ac(behavior): smoke harness — N allowed hits produce ≤1 log-array write (observable via
  file-backed option store write count).
</ac>

<changelog>

**Files touched**: `facebook-request-throttle.php`, `tests/ThrottleTest.php`, `readme.txt`.

**What shipped** (direction (a), per the LOCKED ruling):
- `nt_log( $status )`: allowed hits no longer append to `nt_facebook_throttle_log`; they
  increment a new `nt_facebook_throttle_allowed_count` option
  (`update_option( ..., $count + 1, false )` — autoload off). Throttled hits are unchanged:
  still a full log row, still capped at `NT_FACEBOOK_REQUEST_THROTTLE_LOG_LIMIT`.
- **Honest framing** (per ruling): this eliminates the per-allowed-hit FULL-ARRAY
  serialization/rewrite (a multi-KB row on a busy log), not the write COUNT — the counter
  still writes once per allowed hit, and the log option was already `autoload=false`.
- CLI `status()`: allowed count now read from the counter option instead of computed as
  `total - throttled` from the log (which would have silently reported 0 allowed hits
  post-change). `throttled` is `count($log)`; the printed total is `allowed + throttled`.
- CLI `clear()` and admin `nt_clear_log` both now also `delete_option(
  'nt_facebook_throttle_allowed_count' )` — coherent reset.
- Admin log page: renders `Allowed hits: N`; copy updated to say the log now shows
  throttled (429) requests only, with allowed hits counted above.
- `wp fb-throttle log --status=allowed` now returns no rows (no code change needed — the
  existing `array_filter` on `$e['status']` naturally returns empty since the log no longer
  contains 'allowed' entries). Documented in `readme.txt` under a new `= 3.1 =` changelog
  heading (heading pattern `= X.X =` already existed, so not skipped).
- Information trade (per ruling, documented, not a silent regression): per-allowed-hit
  ip/uri/ua rows are no longer recorded — only a count. Throttled-hit detail is unchanged.
- Rewrote the 4 enumerated tests: `test_cli_status_shows_log_counts` (seeds the counter,
  not allowed log rows), `test_allowed_hit_creates_log_entry` → renamed
  `test_allowed_hit_increments_allowed_counter`, `test_log_entry_contains_expected_fields`
  and `test_log_is_newest_first` (both re-anchored on throttled hits). Also updated
  `test_cli_clear_removes_log` to assert the counter resets (implied by the "CLI clear()
  also resets the counter" coherence rule) and added two new tests for the admin-page
  coherence rules (`test_admin_page_shows_allowed_hits_count`,
  `test_admin_clear_log_resets_allowed_counter`).

**Red (before implementation)** — `vendor/bin/phpunit --filter 'test_allowed_hit_increments_allowed_counter|test_throttled_hit_creates_throttled_log_entry|test_log_entry_contains_expected_fields|test_log_is_newest_first|test_cli_status_shows_log_counts|test_cli_clear_removes_log|test_admin_page_shows_allowed_hits_count|test_admin_clear_log_resets_allowed_counter|test_log_capped_at_limit'`:
```
FFF....FF                                                           9 / 9 (100%)

There were 5 failures:

1) ThrottleTest::test_cli_status_shows_log_counts
Failed asserting that 'Log entries       : 1 (allowed: 0, throttled: 1)' [ASCII](length: 48) contains "allowed: 1" [ASCII](length: 10).

2) ThrottleTest::test_cli_clear_removes_log
Failed asserting that 4 is identical to 0.

3) ThrottleTest::test_allowed_hit_increments_allowed_counter
Failed asserting that 0 is identical to 1.

4) ThrottleTest::test_admin_page_shows_allowed_hits_count
Failed asserting that '...<p>No hits recorded yet.</p>...' [UTF-8](length: 1115) contains "Allowed hits: 7" [ASCII](length: 15).

5) ThrottleTest::test_admin_clear_log_resets_allowed_counter
Failed asserting that 5 is identical to 0.

Tests: 9, Assertions: 15, Failures: 5.
```
(Failed for the right reasons: the counter option didn't exist yet — allowed hits were
still appending to the log, CLI/admin clear didn't know about a counter, and the admin
page had no "Allowed hits" line.)

**Green (after implementation)** — full gate:
```
OK (80 tests, 128 assertions)
  ✓ unit tests (phpunit)
  ✓ lint (phpcs per .phpcs.xml)
✓ all checks passed
```

**Ceilings named**: none new — no ponytail comments added; the change is a straight
option-vs-array-write swap with no deferred edge cases.

**ac(behavior) — smoke harness note**: not exercised as an automated file-backed-option
write-count harness in this pass (would require instrumenting `update_option()` calls,
out of scope for this diff). The unit-level proof stands in for it: `nt_log('allowed')`
is asserted to leave `nt_facebook_throttle_log` untouched (`assertCount(0, ...)`) while
bumping the counter by exactly 1 per call — i.e. one small-option write per allowed hit,
zero log-array writes.
</changelog>
