# TASK-02 [throttle] — Case-insensitive UA match

Owner: claude · Wave: rev.1 · Deps: none

<context>
`nt_is_request_from_facebook()` uses `strpos`, so `FacebookExternalHit/1.1` (real-world casing
variants exist; UA strings are not case-normalized by clients) bypasses the throttle entirely.
One-line fix; the per-UA return-value change is TASK-04's job, not this one.
</context>

<decisions>
- `stripos` instead of `strpos`. Rejected: `strtolower` both sides (more churn, same effect);
  regex (overkill for substring match).
</decisions>

<preflight>
```bash
grep -n "strpos" facebook-request-throttle.php   # 1 hit before, 0 after
```
</preflight>

<tdd>
`tests/ThrottleTest.php` — red: `FacebookExternalHit/1.1` and `META-EXTERNALAGENT/1.1` must be
detected; both currently return false. Green: `stripos`.
</tdd>

<dod>
- [x] red test failed for the right reason (assertTrue got false, not an error)
- [x] green with stripos, all existing tests still pass
- [x] gate green
</dod>

<ac>
- ac(workspace): `bash backlog/checks.sh` → 0 failures.
- ac(behavior): smoke harness — `FacebookExternalHit/1.1` second hit → 429 (covered in TASK-04's
  CHECKS_FULL smoke).
</ac>

<changelog>
- `facebook-request-throttle.php` — `strpos` → `stripos` in `nt_is_request_from_facebook()`.
- `tests/ThrottleTest.php` — +2 tests: `FacebookExternalHit/1.1`, `META-EXTERNALAGENT/1.1`
  (assertNotFalse so they survive TASK-04's string return).

Behavior/AC proof (pasted, not described):
```
# red (right reason):
1) ThrottleTest::test_facebookexternalhit_detected_case_insensitive
Failed asserting that false is not false.
2) ThrottleTest::test_meta_externalagent_detected_case_insensitive
Failed asserting that false is not false.
# green:
OK (58 tests, 79 assertions)
✓ all checks passed
```

Ceilings (named): substring match still throttles any UA *containing* the configured strings —
intended behavior, not a bug. HTTP-level proof lands with TASK-04's smoke harness.
</changelog>
