# Changelog

All notable changes to this project will be documented in this file.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html) — patch releases for fixes, minor for new features, major for breaking changes.

---

## [2.8] — 2024-06-28

### Added
- `package.json` — run tests and linting via npm, pnpm, or yarn
  - `npm test` / `pnpm test` / `yarn test` → PHPUnit via Composer
  - `npm run lint` → PHPCS (WordPress standard)
  - `npm run lint:fix` → PHPCBF auto-fix
  - `npm run check` → lint + test in one shot

---

## [2.7] — 2024-06-28

### Added
- WP-CLI command group `wp fb-throttle`:
  - `wp fb-throttle status` — show current throttle duration and log summary
  - `wp fb-throttle log [--limit=N] [--status=allowed|throttled] [--format=table|json|csv|yaml]`
  - `wp fb-throttle clear` — clear the hit log
  - `wp fb-throttle duration [seconds]` — get or set throttle duration
- `.phpcs.xml` ruleset — PHPCS now runs consistently via `npm run lint`
- 7 new unit tests (36 total, 48 assertions)

---

## [2.6] — 2024-06-28

### Added
- Throttle duration now configurable from **Settings → FB Throttle Log** — no code changes needed
- `nt_get_throttle_duration()` helper — single source of truth for the duration
- `nt_sanitize_throttle_duration()` — clamps input to 1–86400 seconds

### Changed
- `Retry-After` header now reflects the actual configured duration (not hardcoded 60)
- Priority order: dashboard setting → `FACEBOOK_REQUEST_THROTTLE` constant → 60s default

### Upgrade Notice
Fully backward compatible. Existing `FACEBOOK_REQUEST_THROTTLE` defines in `wp-config.php` continue to work — the dashboard value takes priority if set.

---

## [2.5] — 2024-06-28

### Changed
- Applied full WordPress coding standards throughout:
  - snake_case function names, Yoda conditions, tab indentation, WP spacing
- Both `facebookexternalhit` and `meta-externalagent` now throttled via global array
- `$nt_user_agents_to_throttle` is now a configurable global — add custom agents without touching core

### Fixed
- Runtime default initialisation for `$nt_user_agents_to_throttle` to prevent undefined variable notices in edge cases

---

## [2.4] — 2024-06-28

### Added
- Admin hit log page under **Settings → FB Throttle Log** — see the last 100 allowed/throttled hits with timestamp, IP, URI, and user agent
- Testbot URL override — developers can spoof the Facebook UA via a custom URL pattern
- PHPUnit test suite with GitHub Actions CI

### Changed
- `meta-externalagent` added to default throttled user agents

---

## [2.3] and earlier

See the [commit history](https://github.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin/commits/main) for changes prior to v2.4 (before structured releases were introduced).

---

[2.8]: https://github.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin/releases/tag/v2.8
[2.7]: https://github.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin/releases/tag/v2.7
[2.6]: https://github.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin/releases/tag/v2.6
[2.5]: https://github.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin/releases/tag/v2.5
[2.4]: https://github.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin/releases/tag/v2.4
