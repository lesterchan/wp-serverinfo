<?php
/**
 * The tabbed admin screen.
 *
 * Rendering is driven through WordPress's own menu hook rather than by
 * calling the render method directly, so these keep working if the callback
 * moves again.
 *
 * @package WP-ServerInfo
 */

/**
 * Covers the markup, tab routing and access control of the admin screen.
 */
class Test_ServerInfo_Admin_Screen extends WP_UnitTestCase {

	/**
	 * Menu hook the submenu page renders on.
	 *
	 * @var string
	 */
	private $hook = 'dashboard_page_wp-serverinfo';

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/admin.php';

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'dashboard' );

		// wp-admin/menu.php is what makes a page under index.php hook as
		// dashboard_page_* rather than admin_page_*; seed just that entry.
		$GLOBALS['admin_page_hooks']['index.php'] = 'dashboard';

		do_action( 'admin_menu' );
	}

	public function tear_down() {
		unset( $_GET['tab'] );
		parent::tear_down();
	}

	/**
	 * Render a tab and return its HTML.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	private function render( $tab = 'general' ) {
		$_GET['page'] = 'wp-serverinfo';
		$_GET['tab']  = $tab;

		ob_start();
		do_action( $this->hook );
		return ob_get_clean();
	}

	public function test_page_is_registered_under_the_dashboard_menu() {
		global $submenu;

		$slugs = wp_list_pluck( $submenu['index.php'], 2 );

		$this->assertContains( 'wp-serverinfo', $slugs );
	}

	/**
	 * The page exposes the whole server configuration, so it is
	 * manage_options and not something weaker.
	 */
	public function test_page_requires_manage_options() {
		global $submenu;

		foreach ( $submenu['index.php'] as $item ) {
			if ( 'wp-serverinfo' === $item[2] ) {
				$this->assertSame( 'manage_options', $item[1] );
				return;
			}
		}

		$this->fail( 'The WP-ServerInfo submenu page was not registered.' );
	}

	/**
	 * @dataProvider data_tabs
	 *
	 * @param string $tab      Tab slug.
	 * @param string $panel_id DOM id the tab's panel renders with.
	 */
	public function test_every_tab_renders_a_panel( $tab, $panel_id ) {
		$html = $this->render( $tab );

		$this->assertStringContainsString( 'id="' . $panel_id . '"', $html );
		$this->assertStringContainsString( 'nav-tab-active', $html );
	}

	public function data_tabs() {
		return array(
			'general' => array( 'general', 'GeneralOverview' ),
			'php'     => array( 'php', 'PHPinfo' ),
			'mysql'   => array( 'mysql', 'MYSQLinfo' ),
		);
	}

	public function test_unknown_tab_falls_back_to_general() {
		$html = $this->render( 'no-such-tab' );

		$this->assertStringContainsString( 'GeneralOverview', $html );
	}

	/**
	 * The tab comes straight off the query string.
	 */
	public function test_tab_parameter_cannot_inject_markup() {
		$html = $this->render( '"><script>alert(1)</script>' );

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringContainsString( 'GeneralOverview', $html );
	}

	public function test_general_tab_links_to_every_other_tab() {
		$html = $this->render( 'general' );

		foreach ( array( 'general', 'php', 'mysql' ) as $tab ) {
			$this->assertStringContainsString( 'tab=' . $tab, $html );
		}
	}

	/**
	 * 2.0.0 rebuilt this panel from ini_get_all() specifically so it stops
	 * dumping phpinfo()'s environment and request-header sections.
	 */
	public function test_php_tab_does_not_leak_the_environment() {
		$html = $this->render( 'php' );

		$this->assertStringContainsString( 'Loaded Extensions', $html );
		$this->assertStringNotContainsString( 'HTTP_COOKIE', $html );
		$this->assertStringNotContainsString( 'DB_PASSWORD', $html );
		$this->assertStringNotContainsString( DB_PASSWORD, $html );
	}

	/**
	 * A /* translators: *\/ comment that drifts into HTML context gets printed
	 * to the screen, and double-escaping shows up as a literal &amp;.
	 *
	 * @dataProvider data_tabs
	 *
	 * @param string $tab Tab slug.
	 */
	public function test_rendered_markup_is_undamaged( $tab ) {
		$html = $this->render( $tab );

		$this->assertStringNotContainsString( 'translators:', $html );
		$this->assertStringNotContainsString( '<?php', $html );
		$this->assertDoesNotMatchRegularExpression( '/&amp;(nbsp|quot|amp|lt|gt);/', $html );
		$this->assertDoesNotMatchRegularExpression( '/Undefined [a-z ]*(key|index|variable|property)/', $html );
	}

	public function test_memcached_and_redis_tabs_appear_only_with_their_extension() {
		$html = $this->render( 'general' );

		$this->assertSame(
			ServerInfo_Cache::has_memcached(),
			false !== strpos( $html, 'tab=memcached' )
		);
		$this->assertSame(
			ServerInfo_Cache::has_redis(),
			false !== strpos( $html, 'tab=redis' )
		);
	}
}
