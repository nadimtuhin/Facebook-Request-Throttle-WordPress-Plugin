# TASK-01 [CI] — Make CI real (composer + phpunit + phpcs)

Owner: claude · Wave: rev.1 · Deps: none

<context>
`.github/workflows/ci.yml` is green-by-construction: node-only steps, no composer install, and
`continue-on-error: true` everywhere. The 56-test PHPUnit suite has never run in CI, so every other
fix in this backlog would ship unguarded. Out of scope: `act`-based local workflow execution.
</context>

<decisions>
- Matrix PHP 8.1 / 8.2 / 8.3 — PHPUnit ^10 (composer.json) requires PHP ≥ 8.1, so the readme's
  "7.4+" claim can't be CI-tested with this suite; trimming the readme claim is TASK-07's job.
  Rejected: keeping 7.4/8.0 in the matrix (composer install fails there → permanent red).
- `shivammathur/setup-php@v2`, `composer install`, `vendor/bin/phpunit`, `vendor/bin/phpcs` as
  plain steps. Zero `continue-on-error`, zero `|| echo skipped`. Node job deleted (no JS to test).
</decisions>

<preflight>
```bash
grep -c "continue-on-error" .github/workflows/ci.yml   # 2 before, 0 after
grep -c "composer" .github/workflows/ci.yml            # 0 before, >0 after
```
</preflight>

<tdd>
No unit test — the artifact is the workflow file. Proof = local gate equivalence: the exact
commands CI runs (`composer install`, `vendor/bin/phpunit`, `vendor/bin/phpcs`) pass locally via
`backlog/checks.sh`, and the gate goes red on a seeded failure (proves it can fail).
</tdd>

<dod>
- [x] workflow runs composer install + phpunit + phpcs on PHP 8.1/8.2/8.3
- [x] no continue-on-error / skipped-step patterns remain
- [x] `bash backlog/checks.sh` green locally; seeded failure turns it red
- [x] ceilings named
</dod>

<ac>
- ac(workspace): `bash backlog/checks.sh` → 0 failures on PHP 8.1 (repo's local runtime).
- ac(behavior): deliberately break `nt_is_request_from_facebook` → gate exits non-zero; revert → green.
- ac(gates): `grep continue-on-error .github/workflows/ci.yml` → no matches.
</ac>

<changelog>
- `.github/workflows/ci.yml` — rewritten: matrix PHP 8.1/8.2/8.3, setup-php, composer install,
  phpunit, phpcs; node job and all `continue-on-error` removed.
- `backlog/checks.sh` — gate wired to vendor/bin/phpunit + vendor/bin/phpcs.

Behavior/AC proof (pasted, not described):
```
$ grep -c "continue-on-error" .github/workflows/ci.yml
0
$ grep -c "composer" .github/workflows/ci.yml
2
# seeded break (nt_is_request_from_facebook forced to return true):
  ✗ FAIL: lint (phpcs per .phpcs.xml)
✗ 2 check(s) failed        → gate exit 1 (verified: broken tree exit=1)
# restored:
clean-gate-exit=0
✓ all checks passed
```

Ceilings (named): workflow not executed via `act` locally — actual CI run happens on next push;
matrix drops PHP 7.4/8.0 (PHPUnit ^10 floor is 8.1), readme claim reconciled in TASK-07.
</changelog>
