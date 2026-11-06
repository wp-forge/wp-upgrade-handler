<?php

namespace WP_Forge\UpgradeHandler;

/**
 * Class UpgradeHandler
 *
 * Runs version-named upgrade routines (e.g. `1.4.1.php`) when the stored version
 * of a plugin or theme differs from the version defined in code.
 *
 * @package WP_Forge\UpgradeHandler
 */
class UpgradeHandler {

	/**
	 * The previous version.
	 *
	 * @var string
	 */
	protected $old_version;

	/**
	 * The current version.
	 *
	 * @var string
	 */
	protected $new_version;

	/**
	 * The directory containing upgrade routines.
	 *
	 * @var string
	 */
	protected $upgrade_dir;

	/**
	 * Class constructor.
	 *
	 * Versions are cast to strings so that values like `false` (what `get_option()` returns when the option
	 * doesn't exist yet) or numeric versions compare predictably.
	 *
	 * @param string $upgrade_dir The directory containing upgrade routines.
	 * @param string $old_version The previous version. An empty value is treated as "older than every routine".
	 * @param string $new_version The current version.
	 */
	public function __construct( $upgrade_dir, $old_version, $new_version ) {
		$this->upgrade_dir = (string) $upgrade_dir;
		$this->old_version = (string) $old_version;
		$this->new_version = (string) $new_version;
	}

	/**
	 * Detect when an upgrade is necessary and run the updates.
	 *
	 * If a routine throws, the exception is not caught: remaining routines are skipped and the caller can
	 * avoid saving the new version so the upgrade is attempted again.
	 *
	 * @return bool Whether or not the version changed (i.e. the stored version should now be updated).
	 *              This is true even when there were no routines to run.
	 */
	public function maybe_upgrade() {
		$should_upgrade = $this->should_upgrade();
		if ( $should_upgrade ) {
			$available_routines = $this->get_available_upgrade_routines();
			$required_routines  = $this->get_required_upgrade_routines( $available_routines );
			$this->run_upgrade_routines( $required_routines );
		}

		return $should_upgrade;
	}

	/**
	 * Check if we should upgrade.
	 *
	 * @return bool
	 */
	public function should_upgrade() {
		return $this->old_version !== $this->new_version;
	}

	/**
	 * Get a collection of available upgrade routines.
	 *
	 * Only regular files named `{version}.php` are considered, where the version starts with a digit.
	 * This prevents helper files (e.g. `helpers.php`) from being run as upgrade routines.
	 *
	 * @return array A collection of filepaths indexed by versions, sorted from oldest to newest.
	 */
	public function get_available_upgrade_routines() {
		$routines = array();
		$dir      = rtrim( $this->upgrade_dir, '/\\' );

		if ( '' === $dir || ! is_dir( $dir ) ) {
			return $routines;
		}

		$filenames = scandir( $dir );
		if ( ! $filenames ) {
			return $routines;
		}

		foreach ( $filenames as $filename ) {
			$filepath = $dir . DIRECTORY_SEPARATOR . $filename;
			$version  = basename( $filename, '.php' );
			if (
				'.php' === substr( $filename, -4 )
				&& preg_match( '/^\d[\w.+-]*$/', $version )
				&& is_file( $filepath )
			) {
				$routines[ $version ] = $filepath;
			}
		}

		return $this->sort_routines( $routines );
	}

	/**
	 * Get a collection of the required upgrade routines.
	 *
	 * A routine is required when its version is greater than the old version and less than or equal to the new version.
	 *
	 * @param array $available_routines A collection of available upgrade routines.
	 *
	 * @return array A collection of filepaths indexed by versions, sorted from oldest to newest.
	 */
	public function get_required_upgrade_routines( array $available_routines ) {
		$routines = array();
		foreach ( $available_routines as $version => $file ) {
			if ( $this->filter_upgrade_routines( $version ) ) {
				$routines[ $version ] = $file;
			}
		}

		return $this->sort_routines( $routines );
	}

	/**
	 * Run an ordered set of upgrade routines.
	 *
	 * Each routine is included in an isolated scope, so it can't access or modify this object's state.
	 *
	 * @param array $routines A collection of filepaths indexed by versions.
	 */
	public function run_upgrade_routines( array $routines ) {
		foreach ( $routines as $file ) {
			if ( is_file( $file ) ) {
				self::include_routine( $file );
			}
		}
	}

	/**
	 * Filter to find the versions for which we need to run an upgrade routine.
	 *
	 * @param string|int $version The version of the routine.
	 *
	 * @return bool Whether or not to keep the routine.
	 */
	protected function filter_upgrade_routines( $version ) {
		$version = (string) $version;

		return version_compare( $this->old_version, $version, '<' ) && version_compare( $this->new_version, $version, '>=' );
	}

	/**
	 * Sort routines by version, oldest first.
	 *
	 * Uses version comparison rather than string comparison so that `1.9.0` runs before `1.10.0`.
	 *
	 * @param array $routines A collection of filepaths indexed by versions.
	 *
	 * @return array
	 */
	protected function sort_routines( array $routines ) {
		uksort(
			$routines,
			function ( $a, $b ) {
				return version_compare( (string) $a, (string) $b );
			}
		);

		return $routines;
	}

	/**
	 * Include a routine file without exposing `$this` or any local variables to it.
	 *
	 * @param string $file The absolute path to the routine.
	 */
	private static function include_routine( $file ) {
		require $file;
	}

}
