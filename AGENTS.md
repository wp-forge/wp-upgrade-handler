# AGENTS.md

Guidance for AI coding agents working in this repository.

## Overview

`wp-forge/wp-upgrade-handler` is a small Composer library for WordPress plugins and themes. It runs version-named upgrade routines (e.g. `upgrades/1.4.1.php`) when the stored version differs from the version in code. It has no runtime dependencies and does not require WordPress to run its tests.

## Layout

- `includes/UpgradeHandler.php` — the whole library (namespace `WP_Forge\UpgradeHandler`, PSR-4 from `includes/`).
- `tests/UpgradeHandlerTest.php` — PHPUnit tests (namespace `WP_Forge\UpgradeHandler\Tests`).
- `README.md` — user-facing docs. Keep it in sync with behaviour changes.
- `.github/workflows/tests.yml` — CI (PHP 7.4–8.4, 8.5 experimental) with Codecov upload.
- `.github/dependabot.yml` — weekly composer and github-actions updates.

## Commands

```bash
composer install
composer test            # phpunit
composer test:coverage   # needs pcov or Xdebug; writes coverage.xml
```

## Conventions

- Support PHP 7.4+. Do not use newer syntax (typed properties are OK, but no enums, readonly, match, etc.) unless `composer.json` is bumped.
- Follow WordPress coding style (tabs, spaces inside parentheses, `array()` syntax) to match the existing code.
- Public method names are snake_case and part of the public API; don't rename or change signatures without a major version bump.
- Don't add runtime dependencies. Dev dependencies go in `require-dev`.
- PHPUnit is pinned to `^9.6`; `phpunit.xml.dist` uses the 9.x schema.

## Behaviour to preserve

- Routines are files named `{version}.php` whose version starts with a digit; everything else in the directory is ignored.
- Routines run in `version_compare` order when `old < version <= new`.
- `maybe_upgrade()` returns whether the versions differ, not whether a routine ran.
- Routines are included in an isolated scope (no `$this`). Exceptions from routines propagate.

## Testing

- Every behaviour change needs a test. Tests build temporary directories of routine files and record execution through `$GLOBALS['upgrade_handler_log']`.
- Keep coverage high; CI reports to Codecov.
- Tests must pass with `failOnWarning` and `failOnRisky` enabled.
