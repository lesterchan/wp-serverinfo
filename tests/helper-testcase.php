<?php
/**
 * Shared base class for the WP-ServerInfo test cases.
 *
 * @package WP-ServerInfo
 */

/**
 * Cleans up the plugin's row between tests and owns the uninstall include.
 */
abstract class WP_ServerInfo_TestCase extends WP_UnitTestCase {

	/**
	 * Start each test with no stored row, whatever the last one wrote.
	 *
	 * WP_ServerInfo_Options::maybe_upgrade() runs on plugins_loaded, so by the
	 * time any test starts the row already exists -- which is fine for most of
	 * them and wrong for the two that assert on its absence.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		delete_option( WP_ServerInfo_Options::VERSION );
	}

	/**
	 * Drop the row again, so a test cannot leak its markers into the next.
	 *
	 * @return void
	 */
	public function tear_down() {
		delete_option( WP_ServerInfo_Options::VERSION );

		parent::tear_down();
	}

	/**
	 * Run the uninstall routine.
	 *
	 * The uninstall file declares functions at file scope, so it can only be
	 * included once in a process; a second require_once is a silent no-op and
	 * would leave a test asserting against rows nothing had removed. The first
	 * caller includes the file, which is what exercises its multisite branch,
	 * and every later caller runs the routine for the current site directly.
	 *
	 * This is why only one place in the suite may include uninstall.php, and it
	 * is here: test-metadata.php and test-uninstall.php both want it.
	 *
	 * @return void
	 */
	protected function run_uninstall() {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wp-serverinfo/wp-serverinfo.php' );
		}

		if ( function_exists( 'wp_serverinfo_uninstall_site' ) ) {
			wp_serverinfo_uninstall_site();

			return;
		}

		require dirname( __DIR__ ) . '/uninstall.php';
	}

	/**
	 * Every option row the plugin owns, read straight from the table.
	 *
	 * A LIKE rather than a list of names: a row added later and forgotten in
	 * uninstall.php is exactly the failure this is here to catch.
	 *
	 * @return string[]
	 */
	protected function stored_option_names() {
		global $wpdb;

		return (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( 'wp_serverinfo_' ) . '%'
			)
		);
	}
}
