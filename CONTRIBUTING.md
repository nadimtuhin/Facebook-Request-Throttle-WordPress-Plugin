# Contributing

## Local Dev Setup

```bash
git clone https://github.com/your-org/Facebook-Request-Throttle-WordPress-Plugin.git
cd Facebook-Request-Throttle-WordPress-Plugin
```

Requirements: PHP 7.4+, [Composer](https://getcomposer.org/)

```bash
composer install
composer test
```

## Coding Standards

- Follow **PSR-12**.
- Match the style of existing code — formatting, naming, docblock conventions.
- Run `composer lint` (if available) before committing.

## Submitting a Pull Request

1. Fork the repo and create a feature branch: `git checkout -b feat/my-change`
2. Make your changes and add/update tests.
3. Ensure `composer test` passes locally.
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
