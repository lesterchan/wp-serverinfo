<?php
/**
 * The tabbed report screen.
 *
 * Rendering is driven through WordPress's own menu hook rather than by
 * calling the render method directly, so these keep working if the callback
 * moves again.
 *
 * @package WP-ServerInfo
 */

/**
 * Covers the markup, tab routing and access control of the report screen.
 */
class WP_ServerInfo_Admin_Test extends WP_ServerInfo_TestCase {

	/**
	 * Menu hook the page renders on.
	 *
	 * The page is parented on tools.php by add_management_page(), and that menu's
	 * slug is 'tools', so the load hook is tools_page_* not admin_page_*.
	 *
	 * @var string
	 */
	private $hook = 'tools_page_wp-serverinfo';

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/admin.php';

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'tools' );

		/*
		 * The menu globals are reset first. add_submenu_page() appends to
		 * $submenu unconditionally, and nothing in WP_UnitTestCase clears it, so
		 * running admin_menu once per test would otherwise leave the page
		 * registered as many times as there are tests in this class -- and the
		 * "exactly once" assertion below would pass or fail depending on
		 * execution order.
		 */
		$GLOBALS['menu']              = array();
		$GLOBALS['submenu']           = array();
		$GLOBALS['_registered_pages'] = array();
		$GLOBALS['_parent_pages']     = array();

		// wp-admin/menu.php is what makes a page under tools.php hook as
		// tools_page_* rather than admin_page_*; seed just that entry.
		$GLOBALS['admin_page_hooks']['tools.php'] = 'tools';

		do_action( 'admin_menu' );
	}

	public function tear_down() {
		unset( $_GET['tab'], $_GET['page'] );

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

	/**
	 * The submenu entry, or null when it was not registered.
	 *
	 * @return array|null
	 */
	private function menu_entry() {
		global $submenu;

		foreach ( $submenu['tools.php'] ?? array() as $item ) {
			if ( WP_ServerInfo_Admin::PAGE === $item[2] ) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * One menu entry, and it is under Tools.
	 *
	 * Before 3.0.0 the page hung off index.php, putting a report about the host
	 * inside the Dashboard menu. It is not under Settings either: there is no
	 * settings form on it, and section 4.1 reserves add_options_page() for a
	 * plugin whose only admin surface is settings.
	 */
	public function test_page_is_registered_once_under_the_tools_menu() {
		global $submenu, $menu;

		$this->assertNotNull( $this->menu_entry(), 'The report was not registered under Tools.' );

		$parents = array();

		foreach ( (array) $submenu as $parent => $items ) {
			foreach ( $items as $item ) {
				if ( WP_ServerInfo_Admin::PAGE === $item[2] ) {
					$parents[] = $parent;
				}
			}
		}

		$this->assertSame(
			array( 'tools.php' ),
			$parents,
			'The report belongs under Tools, exactly once and nowhere else.'
		);

		$this->assertNotContains(
			WP_ServerInfo_Admin::PAGE,
			wp_list_pluck( (array) $menu, 2 ),
			'A read-only report does not earn a top-level menu.'
		);
	}

	/**
	 * The page exposes the whole server configuration, so it is
	 * manage_options and not something weaker.
	 */
	public function test_page_requires_manage_options() {
		$entry = $this->menu_entry();

		$this->assertNotNull( $entry, 'The WP-ServerInfo page was not registered.' );
		$this->assertSame( 'manage_options', $entry[1], 'The menu entry requires manage_options.' );
		$this->assertSame( 'manage_options', WP_ServerInfo_Admin::CAPABILITY, 'And the constant says the same, so the two cannot drift.' );
	}

	/**
	 * Every check goes through the one filter, so a site hands the screen over
	 * in a single place (section 2.7).
	 */
	public function test_the_capability_is_filterable_per_context() {
		$seen = array();

		add_filter(
			'wp_serverinfo_capability',
			function ( $capability, $context ) use ( &$seen ) {
				$seen[] = $context;

				return 'edit_posts';
			},
			10,
			2
		);

		$this->assertSame( 'edit_posts', WP_ServerInfo_Admin::capability( 'report' ), 'The filter can set the report capability.' );
		$this->assertSame( 'edit_posts', WP_ServerInfo_Admin::capability( 'widget' ), 'And the widget capability.' );
		$this->assertSame( array( 'report', 'widget' ), $seen, 'It is told which context is asking, each time it is asked.' );
	}

	/**
	 * The page URL is built in one place, so the widget's link and the tab links
	 * cannot drift apart the way they would if each spelled out tools.php.
	 */
	public function test_the_page_url_is_under_tools() {
		$this->assertSame( admin_url( 'tools.php?page=wp-serverinfo' ), WP_ServerInfo_Admin::url(), 'The report lives under Tools, per the menu rule.' );
		$this->assertSame(
			admin_url( 'tools.php?page=wp-serverinfo&tab=mysql' ),
			WP_ServerInfo_Admin::url( 'mysql' ),
			'And a tab is addressed by argument rather than by a screen of its own.'
		);
		$this->assertStringNotContainsString(
			'index.php',
			WP_ServerInfo_Admin::url(),
			'The page left the Dashboard menu in 3.0.0.'
		);
	}

	/**
	 * @dataProvider data_tabs
	 *
	 * @param string $tab      Tab slug.
	 * @param string $panel_id DOM id the tab's panel renders with.
	 */
	public function test_every_tab_renders_a_panel( $tab, $panel_id ) {
		$html = $this->render( $tab );

		$this->assertStringContainsString( 'id="' . $panel_id . '"', $html, 'The ' . $panel_id . ' panel is missing from its tab.' );
		$this->assertStringContainsString( 'nav-tab-active', $html, 'And the tab being viewed is marked active.' );
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

		$this->assertStringContainsString( 'GeneralOverview', $html, 'An unknown tab falls back to General rather than rendering an empty screen.' );
	}

	/**
	 * The tab comes straight off the query string.
	 */
	public function test_tab_parameter_cannot_inject_markup() {
		$html = $this->render( '"><script>alert(1)</script>' );

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html, 'A hostile tab argument never renders as markup.' );
		$this->assertStringContainsString( 'GeneralOverview', $html, 'And the screen still falls back to General rather than breaking.' );
	}

	public function test_general_tab_links_to_every_other_tab() {
		$html = $this->render( 'general' );

		foreach ( array( 'general', 'php', 'mysql' ) as $tab ) {
			$this->assertStringContainsString( 'tab=' . $tab, $html, 'The general tab does not link to the ' . $tab . ' tab.' );
		}
	}

	/**
	 * 2.0.0 rebuilt this panel from ini_get_all() specifically so it stops
	 * dumping phpinfo()'s environment and request-header sections.
	 */
	public function test_php_tab_does_not_leak_the_environment() {
		$html = $this->render( 'php' );

		$this->assertStringContainsString( 'Loaded Extensions', $html, 'The PHP tab reports the interpreter configuration.' );
		$this->assertStringNotContainsString( 'HTTP_COOKIE', $html, 'Without leaking request headers.' );
		$this->assertStringNotContainsString( 'DB_PASSWORD', $html, 'Or naming the database password constant.' );
		$this->assertStringNotContainsString( DB_PASSWORD, $html, 'Or, worse, printing its value.' );
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

		$this->assertStringNotContainsString( 'translators:', $html, 'No translator comment leaked into the markup.' );
		$this->assertStringNotContainsString( '<?php', $html, 'No PHP tag reached the page, which would mean a template was echoed unparsed.' );
		$this->assertDoesNotMatchRegularExpression( '/&amp;(nbsp|quot|amp|lt|gt);/', $html, 'An entity has been double-escaped somewhere in the screen.' );
		$this->assertDoesNotMatchRegularExpression( '/Undefined [a-z ]*(key|index|variable|property)/', $html, 'A PHP undefined-key diagnostic leaked into the screen.' );
	}

	/**
	 * Section 4.4 forbids inline style, width, valign and align attributes and
	 * allows only core classes. Before 3.0.0 the screen wrote its own <style>
	 * block into the page, including an RTL branch with a physical text-align.
	 *
	 * @dataProvider data_tabs
	 *
	 * @param string $tab Tab slug.
	 */
	public function test_screen_uses_core_classes_and_no_inline_styling( $tab ) {
		$html = $this->render( $tab );

		$this->assertStringNotContainsString( '<style', $html, 'The screen carries no style block.' );
		$this->assertStringNotContainsString( 'style=', $html, 'And no inline style attribute.' );
		$this->assertStringNotContainsString( '!important', $html, 'And forces nothing, so a theme or a plugin can restyle it.' );
		$this->assertDoesNotMatchRegularExpression( '/\s(width|valign|align)=/', $html, 'The screen uses a presentational attribute where a core class belongs.' );

		$this->assertStringContainsString( 'class="wrap"', $html, 'It uses the core page wrapper.' );
		$this->assertStringContainsString( 'nav-tab-wrapper', $html, 'The core tab nav.' );
		$this->assertStringContainsString( 'class="widefat striped"', $html, 'And the core table classes, so it looks like the rest of wp-admin.' );
		$this->assertSame( 1, preg_match_all( '/<h1[ >]/', $html ), 'One h1 per screen.' );
	}

	public function test_memcached_and_redis_tabs_appear_only_with_their_extension() {
		$html = $this->render( 'general' );

		$this->assertSame(
			WP_ServerInfo_Cache::has_memcached(),
			false !== strpos( $html, 'tab=memcached' ),
			'The memcached tab is offered exactly when the extension is loaded.'
		);
		$this->assertSame(
			WP_ServerInfo_Cache::has_redis(),
			false !== strpos( $html, 'tab=redis' ),
			'And the redis tab likewise, so neither is a dead link.'
		);
	}
}
