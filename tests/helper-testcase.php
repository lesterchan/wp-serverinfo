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
	 * Creates a user who may actually reach the plugin's screens.
	 *
	 * The Tools screen and the dashboard widget both take `manage_options`,
	 * which core's map_meta_cap() does not touch under multisite, so no
	 * grant_super_admin() here: a site administrator holds it on a network
	 * exactly as on a single site. The plugin reports on the server the site
	 * runs on, which every site administrator on a network may already see.
	 *
	 * Every administrator the suite creates goes through this, so the network
	 * question is answered in one place rather than at each call site. Tests
	 * that assert the *unprivileged* path set their own subscriber or editor
	 * explicitly and must not be routed through here.
	 *
	 * @return int The new user's ID.
	 */
	protected function create_admin() {
		return self::factory()->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Start each test with no stored row, whatever the last one wrote.
	 *
	 * The plugin stores nothing now (STANDARDS.md 2.1), so nothing should ever
	 * put this row back. Clearing it anyway keeps a test that writes one by hand
	 * -- the pre-release cleanup case -- from leaking into the next.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		delete_option( 'wp_serverinfo_version' );
	}

	/**
	 * Drop the row again, so a test cannot leak it into the next.
	 *
	 * @return void
	 */
	public function tear_down() {
		delete_option( 'wp_serverinfo_version' );

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
