# WordPress Upgrade Handler

[![Tests](https://github.com/wp-forge/wp-upgrade-handler/actions/workflows/tests.yml/badge.svg)](https://github.com/wp-forge/wp-upgrade-handler/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/wp-forge/wp-upgrade-handler/graph/badge.svg)](https://codecov.io/gh/wp-forge/wp-upgrade-handler)

A drop-in library for handling upgrade routines in WordPress plugins and themes.

## Requirements

- PHP 7.4 or later

## Installation

- Run `composer require wp-forge/wp-upgrade-handler`
- Make sure you require the `vendor/autoload.php` file in your project.

## Usage

Here is an example of how to use this library in a WordPress plugin or theme:

```php
<?php

use WP_Forge\UpgradeHandler\UpgradeHandler;

// Define the current plugin version in the code
define( 'MY_PLUGIN_VERSION', '1.4.1' );

// Only handle upgrades in the admin
if ( is_admin() ) {

	// Handle plugin upgrades
	$upgrade_handler = new UpgradeHandler(
		__DIR__ . '/upgrades',              // Directory where upgrade routines live
		get_option( 'my_plugin_version' ),  // Old plugin version (from database)
		MY_PLUGIN_VERSION                   // New plugin version (from code)
	);

	// Runs any required upgrade routines. Returns true if the old version doesn't match the new version
	// (even if there were no routines to run).
	$did_upgrade = $upgrade_handler->maybe_upgrade();

	if ( $did_upgrade ) {
		// If an upgrade occurred, update the new version in the database to prevent running the routine(s) again.
		update_option( 'my_plugin_version', MY_PLUGIN_VERSION, true );
	}
}
```

Routines run in version order (`1.9.0` before `1.10.0`). A routine runs when its version is greater than the old version and less than or equal to the new version.

If you just released version `1.4.1` of your plugin, but created upgrade routines for `1.4.1` and `1.3.9`, anyone upgrading from version `1.3.8` or earlier would have both of those upgrade routines run automatically. If someone is upgrading from version `1.3.9` or greater, then only the `1.4.1` upgrade routine would be run.

### Behavior notes

- Only files named `{version}.php` that start with a digit (e.g. `1.4.1.php`) are treated as routines. Other files in the directory, such as `helpers.php`, are ignored.
- On a fresh install (no stored version), every routine up to the current version runs. Guard against this inside a routine if it should only apply to existing installs.
- Downgrades (old version greater than new version) run no routines, but `maybe_upgrade()` still returns `true`.
- If a routine throws an exception, the remaining routines are skipped and the exception propagates. Because the new version isn't saved until `maybe_upgrade()` returns, the upgrade will be attempted again on the next request. Write routines so they are safe to run more than once.
- Routines are included in an isolated scope: `$this` and the handler's variables are not available. Use WordPress functions and globals as usual.
- Only point the handler at a directory you control, since every matching file in it is executed.

### Creating an Upgrade Routine
As an example, let's assume I just released version `1.4.1` of my plugin. The upgrade routine needs to change an option name in the database. All I need to do after adding the previous code from above is to create a file named `1.4.1.php` in my `upgrades` directory.

The following would be the contents of that file:

```php
<?php

// Rename 'old_option' to 'new_option', if necessary.
$old_option = get_option( 'old_option' );
if( $old_option ) {
    update_option( 'new_option', $old_option );
    delete_option( 'old_option' );
}

```

## Development

```bash
composer install
composer test            # run the test suite
composer test:coverage   # run tests with coverage (requires pcov or Xdebug)
```

Tests run on every push and pull request via GitHub Actions, and coverage is reported to [Codecov](https://codecov.io/gh/wp-forge/wp-upgrade-handler).
