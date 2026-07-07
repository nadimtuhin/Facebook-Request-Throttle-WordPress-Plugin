# TASK-07 [docs] — Doc/license reconciliation

Owner: unassigned · Wave: backlog · Deps: TASK-01 (test count must be real first)

<context>
Doc rot (audit unknown-unknown #9): README badge says "44 tests"/MIT while readme.txt says
"56 tests"/GPL-2.0; the LICENSE file is MIT but the plugin header says GPL-2.0-or-later (git
history shows GPL was intended — MIT LICENSE predates the relicense); readme.txt claims
`Tested up to: 7.0` (WordPress 7.0 doesn't exist); changelog references a SECURITY.md that was
never committed. readme also claims PHP 7.4+ but the test suite (PHPUnit ^10) needs PHP ≥ 8.1.
</context>

<decisions>
- LICENSE file → GPL-2.0 text (matches plugin header + readme.txt; WP.org requires GPL-compatible).
- README.md badges → GPL-2.0, real test count (read from phpunit output, don't hand-type a number
  that rots — or drop the count badge entirely).
- `Tested up to:` → the latest real WP version at the time of the change.
- Add SECURITY.md (private disclosure via GitHub security advisories) since the changelog claims it.
- PHP requirement claim: either state "PHP 7.4+ (runtime), 8.1+ (dev/test suite)" or raise
  `Requires PHP` honestly — decide after checking the plugin file for 8.x-only syntax
  (`fn()` arrow fns are 7.4+, fine; check for anything newer).
</decisions>

<tdd>
Docs-only — no unit tests. Proof: grep assertions (no "MIT" in README badges, no "44 tests",
no "Tested up to: 7.0"; LICENSE first line contains "GNU GENERAL PUBLIC LICENSE").
</tdd>

<ac>
- ac(workspace): `bash backlog/checks.sh` → 0 failures (unchanged code).
- ac(behavior): grep checks above pasted into changelog.
</ac>

<changelog>
- `LICENSE` — MIT replaced with full GPL-2.0 text (fetched from SPDX license-list-data; gnu.org
  timed out). Matches plugin header + readme.txt.
- `README.md` — tests badge → live CI workflow badge (no hand-typed count to rot); license badge
  MIT → GPL-2.0; Requirements: WP recommended 7.0 → 6.9, "Tested on PHP 7.4–8.3 / WP 5.0–7.0" →
  honest CI claim (PHP 8.1/8.2/8.3; runtime 7.4+); Development: "44 tests" count dropped;
  changelog "Tested up to WordPress 7.0" → 6.9; License section → GPL-2.0-or-later.
- `readme.txt` — `Tested up to: 7.0` → `6.9` (header + 2.7 changelog entry).
- `SECURITY.md` — created (GitHub Security Advisories, ~7-day response, no bounty). Resolves the
  2.8 changelog claiming a SECURITY.md that never existed.
- Historical test counts inside changelog entries (56/44/29) left as-is — accurate for their
  releases; only factually-impossible claims (WP 7.0) were rewritten.

Behavior/AC proof (pasted, not described):
```
$ grep -in "mit" README.md | grep -iv "limit|submit|permit"   → (no matches)
$ grep -n "44 tests\|44%20passing" README.md                  → (no matches)
$ grep -n "Tested up to" readme.txt                           → 5:Tested up to: 6.9
$ head -1 LICENSE                                             → GNU GENERAL PUBLIC LICENSE
$ ls SECURITY.md                                              → SECURITY.md
$ bash backlog/checks.sh                                      → gate-exit=0
```

Ceilings (named): "Tested up to: 6.9" chosen as latest real WP version — not verified against
wordpress.org from this environment; verify at release time. Note: a sonnet executor attempt at
this task died on a content-filter API error (emitting license text); done directly with curl
instead.
</changelog>
