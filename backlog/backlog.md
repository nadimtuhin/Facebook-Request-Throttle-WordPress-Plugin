# Backlog — Facebook Request Throttle

Gate: `bash backlog/checks.sh` (phpunit + phpcs). `CHECKS_FULL=1` adds the HTTP smoke
(`php -S` harness + curl: real 429/Retry-After + per-UA buckets).
Baseline at scaffold time (2026-07-07): 56/56 phpunit green, phpcs clean on PHP 8.1.7 —
no baseline exceptions needed; any gate failure is a NEW failure.

## Risk audit (2026-07-07)

### Known unknowns — questions we knew needed answers

1. **Does the throttle hold under concurrent crawler hits?** No: read→check→write transient is
   TOCTOU-racy (`facebook-request-throttle.php` throttle core). → TASK-04.
2. **Multi-server / object-cache behavior?** Transient clock may be per-node when no shared cache;
   unverifiable in unit tests. Documented limitation; persistent object cache is the fix.
3. **Does the GitHub-zip auto-update extract to a wrong-named folder?** Yes — classic pitfall; no
   `upgrader_source_selection` filter. → TASK-05.
4. **Which PHP/WP versions actually pass?** Unknown until TASK-01 — CI never ran the suite.
   Local fact: PHPUnit ^10 requires PHP ≥ 8.1, so the honest matrix is 8.1/8.2/8.3.

### Unknown unknowns — surfaced by the audit

1. **CI is green-by-construction** — `.github/workflows/ci.yml` was node-only, no composer install,
   `continue-on-error: true` on every step. The 56-test suite has never run in CI. → TASK-01.
2. **UA-spoof starvation** — one global transient clock means a fake `facebookexternalhit` UA can
   force the *real* FB crawler into 429s. Inherent to UA-based throttling (FB fans out across IPs,
   so per-IP buckets would defeat the plugin's purpose). Mitigate the coupling (TASK-04), document
   the rest in readme Limitations.
3. **Case-sensitive UA match** — `strpos`; `FacebookExternalHit` bypasses. → TASK-02.
4. **Cross-UA coupling** — `meta-externalagent` hit throttles `facebookexternalhit` and vice versa
   (single shared transient). → TASK-04.
5. **Double admin notices** — both `admin_notices` callbacks registered → duplicate banners on
   major updates. → TASK-03.
6. **Test bootstrap stubs ignore transient TTL** (`tests/bootstrap.php`) — expiry bugs invisible
   to the suite. → fixed as part of TASK-04.
7. **Multisite uninstall gap** — `uninstall.php` has no per-site loop. → TASK-06.
8. **Two separate unauthenticated GitHub API calls** (2 transients), 60 req/hr IP limit. → TASK-05.
9. **Doc rot** — README badge "44 tests"/MIT vs readme.txt "56 tests"/GPL-2.0; LICENSE file is MIT
   while the plugin header is GPL-2.0 (git history shows GPL intended); `Tested up to: 7.0` doesn't
   exist; SECURITY.md claimed in changelog but missing. → TASK-07.
10. **DB write amplification** — `update_option` on every allowed crawler hit (`nt_log`). → TASK-08.

## Task index

| Task | Title | Status |
|---|---|---|
| TASK-01 | Make CI real (composer + phpunit + phpcs, no continue-on-error) | done (rev.1) |
| TASK-02 | Case-insensitive UA match | done (rev.1) |
| TASK-03 | Single admin notice on major updates | done (rev.1) |
| TASK-04 | Harden the throttle (per-UA buckets, atomic acquire, TTL-aware stubs) | done (rev.1) |
| TASK-05 | GitHub-zip folder rename + consolidate API calls | done (rev.2) |
| TASK-06 | Multisite-aware uninstall | done (rev.2) |
| TASK-07 | Doc/license reconciliation (GPL-2.0 everywhere, real test count, SECURITY.md) | done (rev.2) |
| TASK-08 | Log write amplification strategy | done (rev.2) |

## Rev log

### rev.2 — 2026-07-07 — TASK-05..08 (updater safety, multisite uninstall, log write amplification, docs)

Shipped: `upgrader_source_selection` filter renames GitHub-zip extraction to the CURRENT install
folder name (not a hardcoded slug — closes the real deactivation-on-update bug); consolidated
the two GitHub fetchers into one transient with `zip_url` optional (closes rev.1's zero-banner
MINOR); multisite-aware `uninstall.php` (`get_sites(number=>0)` + switch_to_blog loop, also
deletes TASK-08's counter option); `nt_log()` allowed-hits now bump a single counter option
instead of rewriting the full log array (throttled hits still logged in full — they're the
signal); LICENSE MIT→GPL-2.0, README/readme.txt claim fixes, new SECURITY.md. Version bumped
3.0→3.1 (plugin header, `NT_PLUGIN_VERSION`, `Stable tag`) to match the new changelog entry.
Suite: 70 → 80 tests (new `tests/UninstallTest.php` with self-contained multisite stubs).

Process: pre-code critic pass (opus) on all three code-bearing approaches caught a CRITICAL
before implementation (TASK-05's original hardcoded rename target would have reproduced the
deactivation bug it exists to fix) plus coherence gaps in TASK-08 (CLI/clear/uninstall
counter sync) — all adopted as locked rulings before any code was written. Two sonnet executors
ran in parallel on disjoint file footprints (plugin file + tests vs uninstall.php + new test
file), confirmed zero conflicts at the gate. Post-code critic pass (opus, independent gate
re-run) verdict: **APPROVE** — 0 CRITICAL/HIGH. 3 MINOR fixed post-review (version/changelog
mismatch, CLI "Last hit" label now says "Last throttled hit" since allowed hits carry no
timestamp under the new counter), 2 NIT accepted as-is (dead legacy-transient delete in
uninstall.php — harmless, per-agent keys self-expire; a `=&` reference-binding in the
UninstallTest switch_to_blog stub — harmless given current test execution order, named as an
accepted ceiling rather than fixed speculatively).

Load-bearing gotchas:
- The updater safety fix only matters for non-canonically-named installs; `dirname(
  plugin_basename( __FILE__ ) )` is the seam that makes it correct regardless of install folder
  name — a hardcoded target would have silently reintroduced known-unknown #3.
- Two independent critic passes on TASK-08 both flagged the same root issue from different
  angles: "eliminating log rewrites" changes what every reader (CLI, admin page, uninstall)
  expects to find — the fix isn't just the write path, it's every read path staying coherent.
- A test executor (sonnet) died mid-task on a content-filter API error while emitting GPL-2.0
  license text (TASK-07); recovered by fetching the text via curl instead of generating it.

Proof (gate, full tier, after post-review fixes):
```
OK (80 tests, 128 assertions)
  ✓ unit tests (phpunit)
  ✓ lint (phpcs per .phpcs.xml)
  fb-hit1=200 fb-hit2=429 case-variant='Retry-After: 60' meta-bucket=200 browser=200
  ✓ http smoke (429 + per-UA buckets)
✓ all checks passed
```

### rev.1 — 2026-07-07 — TASK-01..04 (scaffold + CI + top-3 fixes + throttle hardening)

Shipped: real CI (PHP 8.1/8.2/8.3 matrix, zero continue-on-error), `stripos` UA match, single
major-update notice, per-UA throttle buckets with sanitized keys + atomic `wp_cache_add` acquire
under ext object cache, TTL-aware test stubs, HTTP smoke harness in the gate's `CHECKS_FULL` tier.
Suite: 56 → 67 tests, all red→green with pasted proof in each TASK file.

Load-bearing gotchas:
- PHPUnit ^10 floor is PHP 8.1 → CI matrix can't honestly include 7.4/8.0 (readme claim → TASK-07).
- Critic pass 1 (opus) on the TASK-04 seam: raw UA substrings are unsafe as memcached keys →
  bucket key sanitized to `[a-z0-9]` before shipping; `wp_cache_add` into the `transient` group
  confirmed to interop with `get_transient` under ext cache (no `_transient_` prefix there).
- Gate proven able to fail: seeded break in UA detection → exit 1; restored → exit 0.

Critic pass 2 (opus, post-code, independent gate re-run): **APPROVE** — 0 CRITICAL/MAJOR.
1 MINOR (LOW confidence): on a major bump whose GitHub payload lacks `zipball_url`, the generic
notice stands down while the major notice bails → zero banners; practically unreachable on real
releases, and TASK-05's fetcher consolidation eliminates the double-source disagreement — folded
into TASK-05's scope. NITs (accepted): ext-vs-DB window differs by 1s (commented), sanitized-key
collision only for near-identical custom substrings, stub-TTL meta-test is infra coverage.

Proof (gate, full tier):
```
OK (67 tests, 95 assertions)
  ✓ unit tests (phpunit)
  ✓ lint (phpcs per .phpcs.xml)
  fb-hit1=200 fb-hit2=429 case-variant='Retry-After: 60' meta-bucket=200 browser=200
  ✓ http smoke (429 + per-UA buckets)
✓ all checks passed
```
