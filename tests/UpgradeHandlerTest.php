<?php

namespace WP_Forge\UpgradeHandler\Tests;

use PHPUnit\Framework\TestCase;
use WP_Forge\UpgradeHandler\UpgradeHandler;

/**
 * @covers \WP_Forge\UpgradeHandler\UpgradeHandler
 */
class UpgradeHandlerTest extends TestCase {

	/**
	 * @var string
	 */
	private $dir;

	protected function setUp(): void {
		$this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'upgrade-handler-' . uniqid( '', true );
		mkdir( $this->dir );
		$GLOBALS['upgrade_handler_log'] = array();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['upgrade_handler_log'] );
		$this->remove( $this->dir );
	}

	// ---------------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------------

	/**
	 * Create a routine file that records its own name when run.
	 */
	private function routine( $filename, $dir = null, $body = null ) {
		$dir  = $dir ?: $this->dir;
		$path = $dir . DIRECTORY_SEPARATOR . $filename;
		$body = null === $body ? '$GLOBALS["upgrade_handler_log"][] = ' . var_export( $filename, true ) . ';' : $body;
		file_put_contents( $path, "<?php\n" . $body . "\n" );

		return $path;
	}

	private function handler( $old, $new, $dir = null ) {
		return new UpgradeHandler( $dir ?: $this->dir, $old, $new );
	}

	private function remove( $path ) {
		if ( is_dir( $path ) && ! is_link( $path ) ) {
			foreach ( scandir( $path ) as $item ) {
				if ( '.' !== $item && '..' !== $item ) {
					$this->remove( $path . DIRECTORY_SEPARATOR . $item );
				}
			}
			rmdir( $path );
		} elseif ( file_exists( $path ) || is_link( $path ) ) {
			unlink( $path );
		}
	}

	// ---------------------------------------------------------------------
	// should_upgrade()
	// ---------------------------------------------------------------------

	public function test_should_upgrade_is_false_when_versions_match() {
		$this->assertFalse( $this->handler( '1.0.0', '1.0.0' )->should_upgrade() );
	}

	public function test_should_upgrade_is_true_when_versions_differ() {
		$this->assertTrue( $this->handler( '1.0.0', '1.0.1' )->should_upgrade() );
	}

	public function test_should_upgrade_is_true_on_fresh_install() {
		$this->assertTrue( $this->handler( false, '1.0.0' )->should_upgrade() );
		$this->assertTrue( $this->handler( null, '1.0.0' )->should_upgrade() );
		$this->assertTrue( $this->handler( '', '1.0.0' )->should_upgrade() );
	}

	public function test_versions_are_normalized_to_strings() {
		$this->assertFalse( $this->handler( 2, '2' )->should_upgrade() );
		$this->assertFalse( $this->handler( 1.5, '1.5' )->should_upgrade() );
	}

	// ---------------------------------------------------------------------
	// get_available_upgrade_routines()
	// ---------------------------------------------------------------------

	public function test_available_routines_empty_for_empty_directory() {
		$this->assertSame( array(), $this->handler( '1.0.0', '2.0.0' )->get_available_upgrade_routines() );
	}

	public function test_available_routines_empty_for_missing_directory() {
		$handler = $this->handler( '1.0.0', '2.0.0', $this->dir . '/does-not-exist' );
		$this->assertSame( array(), $handler->get_available_upgrade_routines() );
	}

	public function test_available_routines_empty_for_empty_directory_string() {
		// Must not fall back to scanning the filesystem root.
		$handler = new UpgradeHandler( '', '1.0.0', '2.0.0' );
		$this->assertSame( array(), $handler->get_available_upgrade_routines() );
	}

	public function test_available_routines_are_indexed_by_version() {
		$a = $this->routine( '1.0.0.php' );
		$b = $this->routine( '1.2.php' );

		$this->assertSame(
			array(
				'1.0.0' => $this->dir . DIRECTORY_SEPARATOR . '1.0.0.php',
				'1.2'   => $this->dir . DIRECTORY_SEPARATOR . '1.2.php',
			),
			$this->handler( '0.1', '2.0' )->get_available_upgrade_routines()
		);
		$this->assertFileExists( $a );
		$this->assertFileExists( $b );
	}

	public function test_available_routines_ignore_non_routine_files() {
		$this->routine( '1.0.0.php' );
		$this->routine( 'helpers.php' );
		$this->routine( '.php' );
		$this->routine( 'v2.0.0.php' );
		$this->routine( '1.0.0.php.bak' );
		$this->routine( '1.1.0.txt' );
		$this->routine( '1.2.0.PHP' );
		mkdir( $this->dir . '/1.3.0.php' ); // A directory that looks like a routine.

		$this->assertSame(
			array( '1.0.0' ),
			array_keys( $this->handler( '0.1', '9.0' )->get_available_upgrade_routines() )
		);
	}

	public function test_available_routines_only_strip_the_extension() {
		$this->routine( '1.0.0.php.php' );

		$this->assertSame(
			array( '1.0.0.php' ),
			array_keys( $this->handler( '0.1', '9.0' )->get_available_upgrade_routines() )
		);
	}

	public function test_available_routines_are_sorted_by_version_not_alphabetically() {
		foreach ( array( '1.10.0', '1.2.0', '1.9.0', '1.9.0-beta1', '10.0.0', '2.0.0' ) as $v ) {
			$this->routine( "$v.php" );
		}

		$this->assertSame(
			array( '1.2.0', '1.9.0-beta1', '1.9.0', '1.10.0', '2.0.0', '10.0.0' ),
			array_keys( $this->handler( '0.1', '99.0' )->get_available_upgrade_routines() )
		);
	}

	public function test_available_routines_support_single_number_versions() {
		$this->routine( '2.php' );
		$this->routine( '10.php' );

		$this->assertSame(
			array( '2', '10' ),
			array_map( 'strval', array_keys( $this->handler( '1', '11' )->get_available_upgrade_routines() ) )
		);
	}

	public function test_available_routines_handle_trailing_slashes() {
		$this->routine( '1.0.0.php' );

		$this->assertCount( 1, $this->handler( '0.1', '2.0', $this->dir . '/' )->get_available_upgrade_routines() );
		$this->assertCount( 1, $this->handler( '0.1', '2.0', $this->dir . '\\' )->get_available_upgrade_routines() );
	}

	public function test_available_routines_handle_glob_special_characters_in_path() {
		$special = $this->dir . DIRECTORY_SEPARATOR . 'my[plugin]*?';
		mkdir( $special );
		$this->routine( '1.0.0.php', $special );

		$this->assertSame(
			array( '1.0.0' ),
			array_keys( $this->handler( '0.1', '2.0', $special )->get_available_upgrade_routines() )
		);
	}

	public function test_available_routines_empty_for_unreadable_directory() {
		if ( DIRECTORY_SEPARATOR === '\\' || ( function_exists( 'posix_geteuid' ) && 0 === posix_geteuid() ) ) {
			$this->markTestSkipped( 'Directory permissions cannot be restricted on this platform/user.' );
		}

		$unreadable = $this->dir . DIRECTORY_SEPARATOR . 'unreadable';
		mkdir( $unreadable );
		$this->routine( '1.0.0.php', $unreadable );
		chmod( $unreadable, 0000 );

		// scandir() emits a warning on failure; the handler should still degrade gracefully.
		set_error_handler( function () {
			return true;
		} );
		try {
			$routines = $this->handler( '0.1', '2.0', $unreadable )->get_available_upgrade_routines();
		} finally {
			restore_error_handler();
			chmod( $unreadable, 0755 );
		}

		$this->assertSame( array(), $routines );
	}

	// ---------------------------------------------------------------------
	// get_required_upgrade_routines()
	// ---------------------------------------------------------------------

	private function required( $old, $new, array $versions ) {
		$available = array();
		foreach ( $versions as $v ) {
			$available[ $v ] = "$v.php";
		}

		return array_keys( $this->handler( $old, $new )->get_required_upgrade_routines( $available ) );
	}

	public function test_required_routines_between_old_exclusive_and_new_inclusive() {
		$versions = array( '1.3.8', '1.3.9', '1.4.0', '1.4.1', '1.4.2' );

		$this->assertSame( array( '1.3.9', '1.4.0', '1.4.1' ), $this->required( '1.3.8', '1.4.1', $versions ) );
	}

	public function test_required_routines_skip_already_applied_versions() {
		$this->assertSame( array( '1.4.1' ), $this->required( '1.3.9', '1.4.1', array( '1.3.9', '1.4.1' ) ) );
	}

	public function test_required_routines_skip_future_versions() {
		$this->assertSame( array(), $this->required( '1.0.0', '1.1.0', array( '1.2.0', '2.0.0' ) ) );
	}

	public function test_required_routines_include_all_up_to_new_version_on_fresh_install() {
		$this->assertSame( array( '1.0.0', '1.5.0' ), $this->required( false, '1.5.0', array( '1.0.0', '1.5.0', '1.6.0' ) ) );
	}

	public function test_required_routines_none_on_downgrade() {
		$this->assertSame( array(), $this->required( '2.0.0', '1.0.0', array( '1.0.0', '1.5.0', '2.0.0' ) ) );
	}

	public function test_required_routines_are_sorted_even_if_input_is_not() {
		$this->assertSame(
			array( '1.2.0', '1.9.0', '1.10.0' ),
			$this->required( '1.0.0', '2.0.0', array( '1.10.0', '1.2.0', '1.9.0' ) )
		);
	}

	public function test_required_routines_preserve_file_paths() {
		$required = $this->handler( '1.0.0', '2.0.0' )->get_required_upgrade_routines( array( '1.5.0' => '/some/path/1.5.0.php' ) );

		$this->assertSame( array( '1.5.0' => '/some/path/1.5.0.php' ), $required );
	}

	public function test_required_routines_handle_integer_like_keys() {
		// PHP converts numeric string keys such as "2" to integers.
		$required = $this->handler( '1', '3' )->get_required_upgrade_routines( array( '10' => 'a', '2' => 'b', '3' => 'c' ) );

		$this->assertSame( array( 2 => 'b', 3 => 'c' ), $required );
	}

	public function test_required_routines_include_prerelease_before_release() {
		// 1.5.0-beta1 sorts before 1.5.0, so upgrading to 1.5.0 includes it.
		$this->assertSame( array( '1.5.0-beta1', '1.5.0' ), $this->required( '1.4.0', '1.5.0', array( '1.5.0', '1.5.0-beta1' ) ) );
	}

	// ---------------------------------------------------------------------
	// run_upgrade_routines()
	// ---------------------------------------------------------------------

	public function test_run_routines_runs_in_given_order() {
		$routines = array(
			'1.0.0' => $this->routine( '1.0.0.php' ),
			'1.1.0' => $this->routine( '1.1.0.php' ),
		);

		$this->handler( '0.1', '2.0' )->run_upgrade_routines( $routines );

		$this->assertSame( array( '1.0.0.php', '1.1.0.php' ), $GLOBALS['upgrade_handler_log'] );
	}

	public function test_run_routines_with_nothing_to_run() {
		$this->handler( '0.1', '2.0' )->run_upgrade_routines( array() );

		$this->assertSame( array(), $GLOBALS['upgrade_handler_log'] );
	}

	public function test_run_routines_skips_missing_files() {
		$routines = array(
			'1.0.0' => $this->dir . '/missing.php',
			'1.1.0' => $this->routine( '1.1.0.php' ),
		);

		$this->handler( '0.1', '2.0' )->run_upgrade_routines( $routines );

		$this->assertSame( array( '1.1.0.php' ), $GLOBALS['upgrade_handler_log'] );
	}

	public function test_run_routines_runs_a_file_each_time_it_is_requested() {
		$routines = array( '1.0.0' => $this->routine( '1.0.0.php' ) );
		$handler  = $this->handler( '0.1', '2.0' );

		$handler->run_upgrade_routines( $routines );
		$handler->run_upgrade_routines( $routines );

		$this->assertCount( 2, $GLOBALS['upgrade_handler_log'] );
	}

	public function test_routines_cannot_access_handler_state() {
		$routines = array(
			'1.0.0' => $this->routine(
				'1.0.0.php',
				null,
				'$GLOBALS["upgrade_handler_log"][] = isset( $this ) ? "has-this" : "no-this";'
			),
		);

		$this->handler( '0.1', '2.0' )->run_upgrade_routines( $routines );

		$this->assertSame( array( 'no-this' ), $GLOBALS['upgrade_handler_log'] );
	}

	public function test_routine_local_variables_do_not_leak_between_routines() {
		$routines = array(
			'1.0.0' => $this->routine( '1.0.0.php', null, '$leak = "value";' ),
			'1.1.0' => $this->routine( '1.1.0.php', null, '$GLOBALS["upgrade_handler_log"][] = isset( $leak ) ? "leaked" : "clean";' ),
		);

		$this->handler( '0.1', '2.0' )->run_upgrade_routines( $routines );

		$this->assertSame( array( 'clean' ), $GLOBALS['upgrade_handler_log'] );
	}

	public function test_exception_in_routine_propagates_and_stops_later_routines() {
		$routines = array(
			'1.0.0' => $this->routine( '1.0.0.php', null, 'throw new \RuntimeException( "boom" );' ),
			'1.1.0' => $this->routine( '1.1.0.php' ),
		);

		try {
			$this->handler( '0.1', '2.0' )->run_upgrade_routines( $routines );
			$this->fail( 'Expected exception was not thrown.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'boom', $e->getMessage() );
		}

		$this->assertSame( array(), $GLOBALS['upgrade_handler_log'] );
	}

	// ---------------------------------------------------------------------
	// maybe_upgrade()
	// ---------------------------------------------------------------------

	public function test_maybe_upgrade_does_nothing_when_versions_match() {
		$this->routine( '1.0.0.php' );

		$this->assertFalse( $this->handler( '1.0.0', '1.0.0' )->maybe_upgrade() );
		$this->assertSame( array(), $GLOBALS['upgrade_handler_log'] );
	}

	public function test_maybe_upgrade_runs_only_required_routines_in_version_order() {
		foreach ( array( '1.3.8', '1.3.9', '1.4.1', '1.10.0', '1.4.2' ) as $v ) {
			$this->routine( "$v.php" );
		}

		$this->assertTrue( $this->handler( '1.3.8', '1.4.1' )->maybe_upgrade() );
		$this->assertSame( array( '1.3.9.php', '1.4.1.php' ), $GLOBALS['upgrade_handler_log'] );
	}

	public function test_maybe_upgrade_orders_multi_digit_versions_correctly() {
		foreach ( array( '1.10.0', '1.9.0', '1.2.0' ) as $v ) {
			$this->routine( "$v.php" );
		}

		$this->handler( '1.0.0', '2.0.0' )->maybe_upgrade();

		$this->assertSame( array( '1.2.0.php', '1.9.0.php', '1.10.0.php' ), $GLOBALS['upgrade_handler_log'] );
	}

	public function test_maybe_upgrade_returns_true_even_when_no_routines_apply() {
		$this->routine( '1.0.0.php' );

		$this->assertTrue( $this->handler( '1.0.0', '1.0.1' )->maybe_upgrade() );
		$this->assertSame( array(), $GLOBALS['upgrade_handler_log'] );
	}

	public function test_maybe_upgrade_returns_true_with_missing_directory() {
		$this->assertTrue( $this->handler( '1.0.0', '1.0.1', $this->dir . '/nope' )->maybe_upgrade() );
	}

	public function test_maybe_upgrade_on_fresh_install_runs_all_routines_up_to_current_version() {
		$this->routine( '1.0.0.php' );
		$this->routine( '1.1.0.php' );
		$this->routine( '2.0.0.php' );

		$this->assertTrue( $this->handler( false, '1.1.0' )->maybe_upgrade() );
		$this->assertSame( array( '1.0.0.php', '1.1.0.php' ), $GLOBALS['upgrade_handler_log'] );
	}

	public function test_maybe_upgrade_on_downgrade_returns_true_without_running_routines() {
		$this->routine( '1.0.0.php' );
		$this->routine( '2.0.0.php' );

		$this->assertTrue( $this->handler( '2.0.0', '1.0.0' )->maybe_upgrade() );
		$this->assertSame( array(), $GLOBALS['upgrade_handler_log'] );
	}

	public function test_maybe_upgrade_propagates_routine_exceptions() {
		$this->routine( '1.1.0.php', null, 'throw new \RuntimeException( "failed" );' );

		$this->expectException( \RuntimeException::class );
		$this->handler( '1.0.0', '1.1.0' )->maybe_upgrade();
	}
}
