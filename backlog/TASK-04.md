# TASK-04 [throttle] — Harden the throttle: per-UA buckets + atomic acquire

Owner: claude · Wave: rev.1 · Deps: TASK-02

<context>
Throttle core is a read→check→write on ONE shared transient: (a) TOCTOU race double-allows under
concurrency, (b) a `meta-externalagent` hit throttles `facebookexternalhit` and vice versa
(cross-UA coupling), (c) a spoofed FB UA starves the real crawler. (c) is inherent to UA-based
throttling (FB fans out across IPs; per-IP buckets would defeat the plugin) — document, don't build.
The test bootstrap's transient stubs ignore TTL, so expiry behavior is currently untestable —
fixed here as a prerequisite.
</context>

<decisions>
- **Seam (expensive to move): transient keying scheme + atomicity primitive.**
  - Key: `nt_fb_last_access_<agent>` — one bucket per configured UA substring, **agent sanitized
    to `[a-z0-9]`** (critic finding: raw substrings can contain spaces/parens → invalid memcached
    keys; the key format IS the seam, lock it safe now). Legacy key
    `nt_facebook_last_access_time` is abandoned, no migration: it expires on its own in
    ≤ duration+1s, worst case one extra allowed hit at upgrade time.
  - Atomicity: `wp_cache_add()` (add-if-absent, atomic on memcached/redis) into the `transient`
    group when `wp_using_ext_object_cache()` — interops with `get_transient` under ext cache.
    Without ext cache: keep read→check→write on DB transients and accept the narrow race
    (`// ponytail:` comment names the ceiling; upgrade path = persistent object cache).
- `nt_is_request_from_facebook()` returns the matched agent string (or false) — truthy string
  keeps the existing `if (...)` call site working. Rejected: separate `nt_get_matched_agent()`
  (two functions for one question).
- `nt_facebook_request_throttle( $agent = null )` — null re-detects, so existing call sites and
  tests keep working.
- Bootstrap stubs: `set_transient` stores expiry, `get_transient` honors it against a fake clock
  `$GLOBALS['_nt_test_now']` (falls back to real time). Direct `$GLOBALS['_nt_transients'][k]=v`
  writes have no expiry entry → never expire → existing tests unaffected.
</decisions>

<preflight>
```bash
grep -c "nt_facebook_last_access_time" facebook-request-throttle.php   # >0 before, 0 after
grep -c "wp_cache_add" facebook-request-throttle.php                   # 0 before, >0 after
```
</preflight>

<tdd>
Red tests (ThrottleTest):
- per-UA independence: `facebookexternalhit` throttled while `meta-externalagent` sails through.
- detection returns the matched agent string.
- TTL expiry: transient set with TTL + fake clock advanced past it → next hit allowed.
- ext-cache atomic path: with `_nt_ext_cache=true`, second acquire within window → 429.
Green: per-agent keys + acquire function. The full suite passes after the change, with these
test edits being PART of the change (critic pass 1, confirmed): the two positive-detection
`assertTrue` calls become `assertSame('<agent>', …)` (strict `assertTrue('string')` fails), and
every `nt_facebook_last_access_time` seed/assert re-points to the per-UA key.

Critic pass 1 (opus, pre-code): **GO-WITH-CHANGES** — adopted: (1) sanitized key derivation,
(2) explicit test-edit list above, (3) ceiling comments for drop-in `wp_cache_add` atomicity and
ceil-TTL-vs-float-window divergence. Confirmed: `wp_cache_add` into the `transient` group
interops correctly with `get_transient` under ext object cache (no `_transient_` prefix there —
that's the DB path only).
</tdd>

<dod>
- [x] red tests failed for the right reasons
- [x] green; full suite passes
- [x] TTL-aware stubs (closes audit unknown-unknown #6)
- [x] smoke harness proves real HTTP 429 + Retry-After + independent buckets
- [x] spoof-starvation documented in readme Limitations
- [x] ceilings named (DB-transient race window)
</dod>

<ac>
- ac(workspace): `bash backlog/checks.sh` → 0 failures.
- ac(behavior): `CHECKS_FULL=1 bash backlog/checks.sh` — php -S harness + curl: FB UA hit1=200,
  hit2=429 with `Retry-After`, `meta-externalagent` (other bucket)=200, browser UA=200.
- ac(gates): gate green; output pasted in changelog.
</ac>

<changelog>
- `facebook-request-throttle.php` — `nt_is_request_from_facebook()` returns matched agent
  (string|false); new `nt_throttle_transient_key()` (sanitized `[a-z0-9]` per critic) and
  `nt_acquire_throttle_slot()` (atomic `wp_cache_add` into `transient` group under ext object
  cache; read→check→write DB fallback with ponytail ceiling comments); per-agent get/set;
  `nt_facebook_request_throttle( $agent = null )`.
- `tests/bootstrap.php` — TTL-aware transient stubs with fake clock `$GLOBALS['_nt_test_now']`;
  `wp_using_ext_object_cache` / `wp_cache_add` (add-if-absent) stubs.
- `tests/ThrottleTest.php` — +8 tests (stub TTL honesty, agent-string return, key sanitization,
  hot-bucket 429, per-UA independence ×2 paths, bucket TTL expiry, atomic-add path); legacy key
  seeds re-pointed to `nt_fb_last_access_facebookexternalhit`; two detection asserts →
  `assertSame`.
- `backlog/smoke-harness.php`, `backlog/smoke.sh` — real HTTP proof via `php -S` + curl with
  file-backed transients (wired into `CHECKS_FULL=1` gate tier).
- `readme.txt` — new Limitations section (spoof starvation, DB-race window, multi-server clock).

Behavior/AC proof (pasted, not described):
```
# red (right reasons), before implementation:
1) test_transient_stub_honors_ttl        — Failed asserting that 'v' is false.
2) test_detection_returns_matched_agent_string — Failed asserting that true is identical to 'facebookexternalhit'.
3) test_hot_bucket_throttles_its_own_agent — Failed asserting that exception ... is thrown.
4) test_per_ua_buckets_are_independent   — array has no key 'nt_fb_last_access_metaexternalagent'.
5) test_ext_cache_path_uses_atomic_add   — array has no key 'transient:nt_fb_last_access_facebookexternalhit'.
(+2 errors: nt_throttle_transient_key undefined)

# green:
OK (67 tests, 95 assertions)

# real-thing proof (CHECKS_FULL=1 → php -S + curl against the actual plugin file):
  fb-hit1=200 fb-hit2=429 case-variant='Retry-After: 60' meta-bucket=200 browser=200
  ✓ http smoke (429 + per-UA buckets)
✓ all checks passed
```

Ceilings (named):
- DB-transient path keeps a narrow TOCTOU window (≤1 extra allowed request per window under
  true concurrency); upgrade path = persistent object cache (atomic branch already shipped).
- Atomic path trusts the drop-in's `wp_cache_add` to be true add-if-absent; window there is
  `ceil(duration)` via TTL vs float timestamp math on the DB path.
- Smoke harness exercises the DB path only (no ext-cache drop-in under php -S); the atomic
  branch is covered by stubbed unit tests.
- No full WP install driven locally — harness stubs WP's option/transient API around the real
  plugin file; wp-env/e2e remains future work.
</changelog>
