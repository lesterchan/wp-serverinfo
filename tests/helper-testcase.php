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
	 * Super admin on a network, and that reverses what this helper used to say.
	 * The old reasoning was that `manage_options` belongs to a site
	 * administrator on a network exactly as on a single site, and that the
	 * server this site runs on is something they may already see. The second
	 * half is what does not hold: the report is the document root, the server's
	 * own address, the loaded php.ini, every ini directive and every MySQL
	 * server variable, and core closes Site Health -- strictly less information
	 * -- to that same person, because `view_site_health_checks` needs
	 * `install_plugins`, which `map_meta_cap()` resolves to `do_not_allow` on
	 * multisite for anyone who is not a super admin.
	 *
	 * So the network gate is `manage_network_options` now, and the fixture has
	 * to represent the operator who actually holds it. A network that wants to
	 * delegate the screen says so through `wp_serverinfo_capability`.
	 *
	 * Every administrator the suite creates goes through this, so the network
	 * question is answered in one place rather than at each call site. Tests
	 * that assert the *unprivileged* path set their own subscriber or editor
	 * explicitly and must not be routed through here.
	 *
	 * @return int The new user's ID.
	 */
	protected function create_admin() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		if ( is_multisite() ) {
			grant_super_admin( $user_id );
		}

		return $user_id;
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
