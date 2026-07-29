<?php
/**
 * WP_ServerInfo_Options.
 *
 * The plugin's whole storage layer: one autoloaded row holding two upgrade
 * markers. WP-ServerInfo has nothing a site owner can configure, so there is no
 * settings row and no sanitiser (STANDARDS.md 2.1), and half of what is asserted
 * here is that the absence stays an absence.
 *
 * @package WP-ServerInfo
 */

/**
 * Covers the version markers and the upgrade routine that writes them.
 */
class WP_ServerInfo_Options_Test extends WP_ServerInfo_TestCase {

	public function test_the_row_name_is_the_prefixed_one() {
		$this->assertSame( 'wp_serverinfo_version', WP_ServerInfo_Options::VERSION );
	}

	/**
	 * Section 2.2 defines OPTION and GROUP only where a plugin has settings, and
	 * this one has none. A constant naming a row that never exists would be an
	 * invitation to create it.
	 */
	public function test_no_settings_constants_are_defined() {
		$this->assertFalse( defined( 'WP_ServerInfo_Options::OPTION' ) );
		$this->assertFalse( defined( 'WP_ServerInfo_Admin::GROUP' ) );
	}

	public function test_markers_are_an_empty_array_before_the_first_upgrade() {
		$this->assertSame( array(), WP_ServerInfo_Options::markers() );
	}

	/**
	 * A row that somebody has scribbled a scalar into must not be handed on as
	 * one; every caller indexes it.
	 */
	public function test_a_malformed_row_reads_as_an_empty_array() {
		update_option( WP_ServerInfo_Options::VERSION, 'not an array' );

		$this->assertSame( array(), WP_ServerInfo_Options::markers() );
	}

	public function test_the_upgrade_writes_both_markers() {
		WP_ServerInfo_Options::maybe_upgrade();

		$this->assertSame(
			array(
				'plugin' => WP_SERVERINFO_VERSION,
				'db'     => WP_SERVERINFO_DB_VERSION,
			),
			WP_ServerInfo_Options::markers()
		);
	}

	public function test_the_row_is_autoloaded() {
		WP_ServerInfo_Options::maybe_upgrade();

		$this->assertContains(
			WP_ServerInfo_Options::VERSION,
			array_keys( wp_load_alloptions() ),
			'The markers are read on every request, so the row is autoloaded.'
		);
	}

	/**
	 * Once the markers agree the routine must not write again, or it would dirty
	 * the alloptions cache on every single request.
	 *
	 * Counted through the update filter rather than by comparing query counts:
	 * whether a repeat read costs a query depends on the object cache, so that
	 * comparison would be a test of the environment.
	 */
	public function test_a_second_upgrade_writes_nothing() {
		WP_ServerInfo_Options::maybe_upgrade();

		$writes = 0;

		add_filter(
			'pre_update_option_' . WP_ServerInfo_Options::VERSION,
			function ( $value ) use ( &$writes ) {
				++$writes;

				return $value;
			}
		);

		WP_ServerInfo_Options::maybe_upgrade();
		WP_ServerInfo_Options::maybe_upgrade();

		$this->assertSame( 0, $writes, 'The markers already agree, so nothing should be written.' );
	}

	/**
	 * A row left behind by an older version is brought forward, not merged into.
	 */
	public function test_stale_markers_are_brought_up_to_the_running_version() {
		update_option(
			WP_ServerInfo_Options::VERSION,
			array(
				'plugin' => '2.0.0',
				'db'     => '0',
			)
		);

		WP_ServerInfo_Options::maybe_upgrade();

		$markers = WP_ServerInfo_Options::markers();

		$this->assertSame( WP_SERVERINFO_VERSION, $markers['plugin'] );
		$this->assertSame( WP_SERVERINFO_DB_VERSION, $markers['db'] );
	}

	/**
	 * Half-written markers are the case the single update_option() exists to
	 * make impossible, so a row holding only one of the two still upgrades.
	 */
	public function test_a_row_holding_only_one_marker_still_upgrades() {
		update_option( WP_ServerInfo_Options::VERSION, array( 'plugin' => WP_SERVERINFO_VERSION ) );

		WP_ServerInfo_Options::maybe_upgrade();

		$this->assertSame(
			array( 'db', 'plugin' ),
			array_keys( WP_ServerInfo_Options::markers() )
		);
	}

	/**
	 * Both markers go out in one call, so an upgrade that dies half way through
	 * cannot have recorded itself as finished.
	 */
	public function test_the_markers_are_written_in_a_single_call() {
		$source = wp_serverinfo_test_read( 'includes/class-wp-serverinfo-options.php' );

		$this->assertSame( 1, preg_match_all( '/update_option\(/', $source ) );
	}

	/**
	 * The check is hooked to plugins_loaded rather than to activation: an
	 * activation hook does not fire for a plugin that was network-activated
	 * before this version, nor for one dropped into mu-plugins.
	 */
	public function test_the_upgrade_check_runs_on_plugins_loaded() {
		$this->assertNotFalse(
			has_action( 'plugins_loaded', array( 'WP_ServerInfo_Options', 'maybe_upgrade' ) )
		);
	}
}
