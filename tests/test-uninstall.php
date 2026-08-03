<?php
/**
 * The uninstall routine.
 *
 * There are two kinds of leftover. One is wp_serverinfo_version, the plugin's
 * own row. The other is the per-user meta WordPress records on the plugin's
 * behalf when somebody closes, hides or reorders the dashboard widget, which
 * outlives the plugin unless something clears it.
 *
 * The include itself is not done here: uninstall.php declares functions at file
 * scope, so only one place in the suite may require it, and that place is
 * WP_ServerInfo_TestCase::run_uninstall(). test-metadata.php wants it too.
 *
 * @package WP-ServerInfo
 */

/**
 * Covers the uninstall routine and the multisite loop guarding it.
 */
class WP_ServerInfo_Uninstall_Test extends WP_ServerInfo_TestCase {

	/**
	 * Absolute path to uninstall.php.
	 *
	 * @return string
	 */
	private function uninstall_file() {
		return dirname( __DIR__ ) . '/uninstall.php';
	}

	/**
	 * Create a user carrying dashboard state for our widget and two core ones.
	 *
	 * @return int User ID.
	 */
	private function user_with_dashboard_state() {
		$user_id = $this->create_admin();

		update_user_meta( $user_id, 'closedpostboxes_dashboard', array( WP_SERVERINFO_WIDGET_ID, 'dashboard_activity' ) );
		update_user_meta( $user_id, 'metaboxhidden_dashboard', array( WP_SERVERINFO_WIDGET_ID ) );
		update_user_meta(
			$user_id,
			'meta-box-order_dashboard',
			array(
				'normal' => 'dashboard_right_now,' . WP_SERVERINFO_WIDGET_ID . ',dashboard_activity',
				'side'   => 'dashboard_quick_press',
			)
		);

		return $user_id;
	}

	public function test_widget_is_removed_from_closed_boxes() {
		$user_id = $this->user_with_dashboard_state();

		$this->run_uninstall();

		$this->assertSame(
			array( 'dashboard_activity' ),
			get_user_meta( $user_id, 'closedpostboxes_dashboard', true ),
			'The widget is spliced out of the closed boxes, leaving the others as they were.'
		);
	}

	/**
	 * Removing the last entry should drop the row rather than leave an empty
	 * array behind.
	 */
	public function test_meta_key_is_deleted_when_nothing_else_remains() {
		$user_id = $this->user_with_dashboard_state();

		$this->run_uninstall();

		$this->assertSame( '', get_user_meta( $user_id, 'metaboxhidden_dashboard', true ), 'A meta key holding nothing else is deleted rather than left as an empty array.' );
	}

	/**
	 * The ordering meta is a comma-separated string per column. Deleting the
	 * whole key would be easier but would throw away the user's arrangement
	 * of the core widgets.
	 */
	public function test_widget_is_spliced_out_of_the_ordering_without_losing_others() {
		$user_id = $this->user_with_dashboard_state();

		$this->run_uninstall();

		$this->assertSame(
			array(
				'normal' => 'dashboard_right_now,dashboard_activity',
				'side'   => 'dashboard_quick_press',
			),
			get_user_meta( $user_id, 'meta-box-order_dashboard', true ),
			'The widget is spliced out of the ordering without disturbing the other columns.'
		);
	}

	/**
	 * A widget id that merely contains ours as a substring must survive.
	 */
	public function test_similarly_named_widgets_are_not_removed() {
		$user_id = $this->create_admin();

		update_user_meta( $user_id, 'closedpostboxes_dashboard', array( 'dashboard_serverinfo_extra' ) );
		update_user_meta(
			$user_id,
			'meta-box-order_dashboard',
			array( 'normal' => 'dashboard_serverinfo_extra,my_dashboard_serverinfo' )
		);

		$this->run_uninstall();

		$this->assertSame(
			array( 'dashboard_serverinfo_extra' ),
			get_user_meta( $user_id, 'closedpostboxes_dashboard', true ),
			'A widget whose id merely starts with ours is left alone.'
		);
		$this->assertSame(
			array( 'normal' => 'dashboard_serverinfo_extra,my_dashboard_serverinfo' ),
			get_user_meta( $user_id, 'meta-box-order_dashboard', true ),
			'And one that merely contains it, so the match is exact.'
		);
	}

	public function test_users_without_dashboard_state_are_untouched() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$this->run_uninstall();

		$this->assertSame( '', get_user_meta( $user_id, 'closedpostboxes_dashboard', true ), 'A user with no dashboard state is left untouched rather than given an empty one.' );
	}

	public function test_running_twice_is_harmless() {
		$user_id = $this->user_with_dashboard_state();

		$this->run_uninstall();
		$after_first = get_user_meta( $user_id, 'meta-box-order_dashboard', true );

		$this->run_uninstall();

		$this->assertSame( $after_first, get_user_meta( $user_id, 'meta-box-order_dashboard', true ), 'Running the uninstaller twice leaves the same result as running it once.' );
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
		$source = file_get_contents( $this->uninstall_file() );

		$this->assertMatchesRegularExpression( "/'number'\s*=>\s*0/", $source, 'uninstall.php lifts the site query cap, or a network past the default is half-uninstalled.' );
		$this->assertMatchesRegularExpression( "/'fields'\s*=>\s*'ids'/", $source, 'uninstall.php asks for ids only, which is what makes the unlimited query affordable.' );
	}

	/**
	 * Switching blogs pushes onto a stack, so restoring once after the loop
	 * leaves it unwound by one. Also source-level: a single-site run never
	 * enters the branch.
	 */
	public function test_multisite_loop_restores_inside_the_loop() {
		$source = file_get_contents( $this->uninstall_file() );

		$this->assertMatchesRegularExpression(
			'/switch_to_blog\(.*?wp_serverinfo_uninstall_site\(\);.*?restore_current_blog\(\);\s*\}/s',
			$source,
			'The restore sits inside the loop; once after it leaves the stack unwound by one.'
		);
	}

	public function test_uninstall_file_refuses_to_run_outside_uninstall() {
		$source = file_get_contents( $this->uninstall_file() );

		$this->assertStringContainsString( "if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {", $source, 'uninstall.php refuses to run outside the uninstall context.' );
	}

	/**
	 * The file runs with the plugin inactive, so it cannot reference the
	 * plugin's own classes or constants.
	 */
	public function test_uninstall_does_not_depend_on_plugin_classes() {
		$source = file_get_contents( $this->uninstall_file() );
		$body   = preg_replace( '#/\*.*?\*/#s', '', $source );
		$body   = preg_replace( '#//[^\n]*#', '', $body );

		$this->assertDoesNotMatchRegularExpression( '/\bWP_ServerInfo(_[A-Za-z]+)?::/', $body, 'uninstall.php names a plugin class, so it depends on the plugin having been loaded.' );
		$this->assertDoesNotMatchRegularExpression( '/\bWP_SERVERINFO_[A-Z_]+/', $body, 'uninstall.php names a plugin constant, so it depends on the plugin having been loaded.' );
	}

	/**
	 * A version row left by a pre-release build is still deleted.
	 *
	 * Written by hand, because nothing in the plugin writes it any more: it
	 * stores nothing at all (STANDARDS.md 2.1). An early build of the unreleased
	 * 3.0.0 did write it, and uninstall is the only thing that will ever take it
	 * off a site that ran that build.
	 */
	public function test_the_version_row_is_deleted() {
		update_option( 'wp_serverinfo_version', array( 'plugin' => '3.0.0' ) );

		$this->assertIsArray( get_option( 'wp_serverinfo_version' ), 'The fixture really does have a version row for uninstall to delete.' );

		$this->run_uninstall();

		$this->assertFalse( get_option( 'wp_serverinfo_version' ), 'Uninstall deletes the version row.' );
	}

	/**
	 * The users are walked in pages, so a site with more of them than one page
	 * holds still gets cleaned all the way through.
	 *
	 * Source-level for the page size, behavioural for the walk: building 501
	 * users to cross the real boundary would cost more than the assertion is
	 * worth, but a bare get_users() with no paging at all is a different shape
	 * and this catches that.
	 */
	public function test_the_user_walk_is_paged() {
		$source = file_get_contents( $this->uninstall_file() );

		$this->assertMatchesRegularExpression( "/'paged'\s*=>/", $source, 'The user walk is paged rather than loading every user at once.' );
		$this->assertMatchesRegularExpression( "/'number'\s*=>\s*\\\$per_page/", $source, 'The user walk asks for a page at a time.' );

		$user_ids = self::factory()->user->create_many( 3 );

		foreach ( $user_ids as $user_id ) {
			update_user_meta( $user_id, 'metaboxhidden_dashboard', array( WP_SERVERINFO_WIDGET_ID ) );
		}

		$this->run_uninstall();

		foreach ( $user_ids as $user_id ) {
			$this->assertSame( '', get_user_meta( $user_id, 'metaboxhidden_dashboard', true ), 'The per-user dashboard meta is removed for every user the walk reaches.' );
		}
	}

	/**
	 * Section 9 allows a phpcs suppression only in includes/, and only with a
	 * reason. The old version of this file carried one for an unindexed meta_key
	 * lookup; the paged walk exists so that it does not have to.
	 */
	public function test_the_uninstaller_suppresses_no_sniffs() {
		$this->assertStringNotContainsString( 'phpcs:', file_get_contents( $this->uninstall_file() ), 'The uninstaller suppresses no sniff, so nothing is hidden from the standard.' );
	}
}
