<?php
/**
 * The uninstall routine.
 *
 * The plugin stores nothing of its own, but registering a dashboard widget
 * makes WordPress record the widget id in per-user meta when someone closes,
 * hides or reorders it. That meta outlives the plugin, so uninstall clears it.
 *
 * @package WP-ServerInfo
 */

/**
 * Covers the uninstall routine and the multisite loop guarding it.
 */
class WP_ServerInfo_Uninstall_Test extends WP_UnitTestCase {

	/**
	 * Absolute path to uninstall.php.
	 *
	 * @var string
	 */
	private static $uninstall_file;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$uninstall_file = dirname( __DIR__ ) . '/uninstall.php';

		// uninstall.php bails unless WordPress has declared it is uninstalling,
		// and declares functions at file scope, so it is loaded exactly once.
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wp-serverinfo/wp-serverinfo.php' );
		}

		require_once self::$uninstall_file;
	}

	/**
	 * Create a user carrying dashboard state for our widget and two core ones.
	 *
	 * @return int User ID.
	 */
	private function user_with_dashboard_state() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		update_user_meta( $user_id, 'closedpostboxes_dashboard', array( 'dashboard_serverinfo', 'dashboard_activity' ) );
		update_user_meta( $user_id, 'metaboxhidden_dashboard', array( 'dashboard_serverinfo' ) );
		update_user_meta(
			$user_id,
			'meta-box-order_dashboard',
			array(
				'normal' => 'dashboard_right_now,dashboard_serverinfo,dashboard_activity',
				'side'   => 'dashboard_quick_press',
			)
		);

		return $user_id;
	}

	public function test_widget_is_removed_from_closed_boxes() {
		$user_id = $this->user_with_dashboard_state();

		wp_serverinfo_uninstall_site();

		$this->assertSame(
			array( 'dashboard_activity' ),
			get_user_meta( $user_id, 'closedpostboxes_dashboard', true )
		);
	}

	/**
	 * Removing the last entry should drop the row rather than leave an empty
	 * array behind.
	 */
	public function test_meta_key_is_deleted_when_nothing_else_remains() {
		$user_id = $this->user_with_dashboard_state();

		wp_serverinfo_uninstall_site();

		$this->assertSame( '', get_user_meta( $user_id, 'metaboxhidden_dashboard', true ) );
	}

	/**
	 * The ordering meta is a comma-separated string per column. Deleting the
	 * whole key would be easier but would throw away the user's arrangement
	 * of the core widgets.
	 */
	public function test_widget_is_spliced_out_of_the_ordering_without_losing_others() {
		$user_id = $this->user_with_dashboard_state();

		wp_serverinfo_uninstall_site();

		$this->assertSame(
			array(
				'normal' => 'dashboard_right_now,dashboard_activity',
				'side'   => 'dashboard_quick_press',
			),
			get_user_meta( $user_id, 'meta-box-order_dashboard', true )
		);
	}

	/**
	 * A widget id that merely contains ours as a substring must survive.
	 */
	public function test_similarly_named_widgets_are_not_removed() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		update_user_meta( $user_id, 'closedpostboxes_dashboard', array( 'dashboard_serverinfo_extra' ) );
		update_user_meta(
			$user_id,
			'meta-box-order_dashboard',
			array( 'normal' => 'dashboard_serverinfo_extra,my_dashboard_serverinfo' )
		);

		wp_serverinfo_uninstall_site();

		$this->assertSame(
			array( 'dashboard_serverinfo_extra' ),
			get_user_meta( $user_id, 'closedpostboxes_dashboard', true )
		);
		$this->assertSame(
			array( 'normal' => 'dashboard_serverinfo_extra,my_dashboard_serverinfo' ),
			get_user_meta( $user_id, 'meta-box-order_dashboard', true )
		);
	}

	public function test_users_without_dashboard_state_are_untouched() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		wp_serverinfo_uninstall_site();

		$this->assertSame( '', get_user_meta( $user_id, 'closedpostboxes_dashboard', true ) );
	}

	public function test_running_twice_is_harmless() {
		$user_id = $this->user_with_dashboard_state();

		wp_serverinfo_uninstall_site();
		$after_first = get_user_meta( $user_id, 'meta-box-order_dashboard', true );

		wp_serverinfo_uninstall_site();

		$this->assertSame( $after_first, get_user_meta( $user_id, 'meta-box-order_dashboard', true ) );
	}

	/**
	 * A source-level guard, not a behavioural one.
	 *
	 * The 'number' argument to get_sites() defaults to 100, so a bare call
	 * silently stops at the hundredth site and leaves every site after that
	 * dirty while still reporting success. A single-site test suite cannot
	 * build a 101-site network to catch that, so assert on the source.
	 */
	public function test_multisite_loop_lifts_the_site_query_cap() {
		$source = file_get_contents( self::$uninstall_file );

		$this->assertMatchesRegularExpression( "/'number'\s*=>\s*0/", $source );
		$this->assertMatchesRegularExpression( "/'fields'\s*=>\s*'ids'/", $source );
	}

	/**
	 * Switching blogs pushes onto a stack, so restoring once after the loop
	 * leaves it unwound by one. Also source-level: a single-site run never
	 * enters the branch.
	 */
	public function test_multisite_loop_restores_inside_the_loop() {
		$source = file_get_contents( self::$uninstall_file );

		$this->assertMatchesRegularExpression(
			'/switch_to_blog\(.*?wp_serverinfo_uninstall_site\(\);.*?restore_current_blog\(\);\s*\}/s',
			$source
		);
	}

	public function test_uninstall_file_refuses_to_run_outside_uninstall() {
		$source = file_get_contents( self::$uninstall_file );

		$this->assertStringContainsString( "if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {", $source );
	}

	/**
	 * The file runs with the plugin inactive, so it cannot reference the
	 * plugin's own classes.
	 */
	public function test_uninstall_does_not_depend_on_plugin_classes() {
		$source = file_get_contents( self::$uninstall_file );
		$body   = preg_replace( '#/\*.*?\*/#s', '', $source );

		$this->assertDoesNotMatchRegularExpression( '/\bWP_ServerInfo(_[A-Za-z]+)?::/', $body );
	}
}
