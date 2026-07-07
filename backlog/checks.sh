#!/usr/bin/env bash
# Repo gate — 0 failures required before "done".
#   bash backlog/checks.sh               # fast inner loop: phpunit + phpcs
#   CHECKS_FULL=1 bash backlog/checks.sh # + HTTP smoke (php -S harness: real 429/Retry-After, per-UA buckets)
#
# Baseline 2026-07-07: 56/56 phpunit green, phpcs clean (PHP 8.1.7) — no baseline
# exceptions; any failure here is a NEW failure.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

fail=0
run() { local name="$1"; shift; echo "▶ $name"; if "$@"; then echo "  ✓ $name"; else echo "  ✗ FAIL: $name"; fail=$((fail+1)); fi; }

# composer.json pins phpunit ^10 → needs PHP >= 8.1
if [ ! -x vendor/bin/phpunit ]; then
  run "composer install" composer install --no-interaction --quiet
fi

run "unit tests (phpunit)" vendor/bin/phpunit
run "lint (phpcs per .phpcs.xml)" vendor/bin/phpcs

if [ "${CHECKS_FULL:-0}" = "1" ]; then
  run "http smoke (429 + per-UA buckets)" bash backlog/smoke.sh
fi

echo
if [ "$fail" -eq 0 ]; then echo "✓ all checks passed"; else echo "✗ $fail check(s) failed"; exit 1; fi
