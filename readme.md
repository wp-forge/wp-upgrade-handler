# WordPress Upgrade Handler

A drop-in library for handling upgrade routines in WordPress plugins and themes.

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

	// Returns true if the old version doesn't match the new version
	$did_upgrade = $upgrade_handler->maybe_upgrade();

	if ( $did_upgrade ) {
		// If an upgrade occurred, update the new version in the database to prevent running the routine(s) again.
		update_option( 'my_plugin_version', MY_PLUGIN_VERSION, true );
	}
}
```

If you just released version `1.4.1` of your plugin, but created upgrade routines for `1.4.1` and `1.3.9`, anyone upgrading from version `1.3.8` or earlier would have both of those upgrade routines run automatically. If someone is upgrading from version `1.3.9` or greater, then only the `1.4.1` upgrade routine would be run.

### Creating an Upgrade Routine
As an example, let's assume I just released version `1.4.1` of my plugin. The upgrade routine needs to change an option name in the database. All I need to do after adding the previous code from above is to create a file named `1.4.1.php` in my `upgrades` directory.

The following would be the contents of that file:

```php
<?php

// Rename 'old_option' to 'new_option', if necessary.
$old_option = get_option( 'old_option' );
if( $old_option ) {
    update_option( 'new_option', get_option( 'old_option' ) );
    delete_option( 'old_option' );
}

```
