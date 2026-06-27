# Changelog

All notable changes to this project will be documented in this file.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html) — patch releases for fixes, minor for new features, major for breaking changes.

---

## [3.0] — 2026-06-28

### Added
- **WP auto-update integration** — plugin now appears in WordPress's native update system
  - Minor/patch releases (e.g. 3.0 → 3.1) auto-apply silently via WP's background updater
  - Major releases (e.g. 3.x → 4.x) require a manual tap in Plugins dashboard; admin notice shown
- `nt_get_github_release_data()` — fetches tag + zipball URL from GitHub, 12h transient cache
- `nt_inject_plugin_update()` — hooks `pre_set_site_transient_update_plugins` to inject GitHub release
- `nt_auto_update_policy()` — hooks `auto_update_plugin` to allow minors, block majors
- `nt_is_major_upgrade()` — compares major version segments

### Changed
- Admin notice now only fires on **major** version bumps; minor updates are silent

---

## [2.9] — 2026-06-28

### Added
- GitHub update checker: `nt_check_github_for_update()` hits the GitHub releases API and caches the result for 12 hours via a WP transient
- `nt_maybe_show_update_notice()` hooked to `admin_notices` — shows a warning banner (visible only to users with `update_plugins` capability) when a newer version is available on GitHub
- `NT_PLUGIN_VERSION` constant for consistent version comparisons across the plugin

### Changed
- Tested up to WordPress 7.0 (55% market share as of June 2025)

### Upgrade Notice
No breaking changes. Existing installations will automatically begin showing update notices when a new GitHub release is published.

---

## [2.8] — 2026-06-28

### Added
- `package.json` — run tests and linting via npm, pnpm, or yarn
  - `npm test` / `pnpm test` / `yarn test` → PHPUnit via Composer
  - `npm run lint` → PHPCS (WordPress standard)
  - `npm run lint:fix` → PHPCBF auto-fix
  - `npm run check` → lint + test in one shot

---

## [2.7] — 2026-06-28

### Added
- WP-CLI command group `wp fb-throttle`:
  - `wp fb-throttle status` — show current throttle duration and log summary
  - `wp fb-throttle log [--limit=N] [--status=allowed|throttled] [--format=table|json|csv|yaml]`
  - `wp fb-throttle clear` — clear the hit log
  - `wp fb-throttle duration [seconds]` — get or set throttle duration
- `.phpcs.xml` ruleset — PHPCS now runs consistently via `npm run lint`

---

## [2.6] — 2026-06-27

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

## [2.5] — 2026-06-27

### Changed
- Applied full WordPress coding standards throughout:
  - snake_case function names, Yoda conditions, tab indentation, WP spacing
- Both `facebookexternalhit` and `meta-externalagent` now throttled via global array
- `$nt_user_agents_to_throttle` is now a configurable global — add custom agents without touching core

### Fixed
- Runtime default initialisation for `$nt_user_agents_to_throttle` to prevent undefined variable notices in edge cases

---

## [2.4] — 2026-06-27

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

[3.0]: https://github.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin/releases/tag/v3.0
[2.9]: https://github.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin/releases/tag/v2.9
[2.8]: https://github.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin/releases/tag/v2.8
[2.7]: https://github.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin/releases/tag/v2.7
[2.6]: https://github.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin/releases/tag/v2.6
[2.5]: https://github.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin/releases/tag/v2.5
[2.4]: https://github.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin/releases/tag/v2.4
