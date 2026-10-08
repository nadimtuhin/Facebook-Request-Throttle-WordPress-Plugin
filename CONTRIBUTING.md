# Contributing

## Local Dev Setup

```bash
git clone https://github.com/nadimtuhin/Facebook-Request-Throttle-WordPress-Plugin.git
cd Facebook-Request-Throttle-WordPress-Plugin
```

Requirements: PHP 8.1+ (for PHPUnit 10) and [Composer](https://getcomposer.org/)

```bash
composer install
composer test
```

## Coding Standards

- The plugin file follows the WordPress coding standard. Run `vendor/bin/phpcs` before committing; `vendor/bin/phpcbf` fixes most issues.
- Tests use plain PHPUnit with WordPress stubs in `tests/bootstrap.php`. No WordPress install needed.

## Submitting a Pull Request

1. Fork the repo and create a feature branch: `git checkout -b feat/my-change`
2. Make your change and add or update tests.
3. Run `composer test` and `vendor/bin/phpcs`.
4. Squash commits to a single logical commit before opening the PR.
5. Open a PR against `main` with a clear description of the change.

PRs with failing tests or unrelated changes will not be merged.

## Reporting Bugs

Open a [GitHub Issue](../../issues) and include:

- **WordPress version**
- **PHP version**
- **Plugin version**
- **Steps to reproduce** the issue
- **Expected vs actual behaviour**
- Any relevant **error logs** or stack traces

---

## Releasing a New Version

Releases are tagged on `main`. Only maintainers with push access can publish releases.

### Steps

1. **Bump the version** in `facebook-request-throttle.php` (plugin header `Version:` field).

2. **Update `CHANGELOG.md`:** add a new `## [X.Y]` section at the top following the existing format. Include Added / Changed / Fixed / Removed / Upgrade Notice as needed.

3. **Update `readme.txt`:** add an entry under `== Changelog ==` and update `== Upgrade Notice ==`.

4. **Commit directly to `main`** (or merge a `release/vX.Y` branch):
   ```
   git commit -m "chore: release vX.Y"
   git push
   ```

5. **Tag the release commit**:
   ```
   git tag vX.Y <commit-sha>
   git push origin vX.Y
   ```

6. **Create the GitHub release**:
   ```
   gh release create vX.Y \
     --title "vX.Y: short description" \
     --latest \
     --notes "Copy the CHANGELOG section here"
   ```

### Version numbering

| Change type | Example |
|---|---|
| Bug fix, docs, tooling | 2.7 → 2.8 |
| New feature, backward-compatible | 2.8 → 2.9 |
| Breaking change | 2.x → 3.0 |

The changelog follows [Keep a Changelog](https://keepachangelog.com). The updater auto-installs minor bumps and holds major bumps for manual approval, so only use a major bump for breaking changes.

