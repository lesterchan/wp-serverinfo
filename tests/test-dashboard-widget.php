<?php
/**
 * The Server Information dashboard widget.
 *
 * @package WP-ServerInfo
 */

/**
 * Covers the dashboard widget's registration, access control and markup.
 */
class WP_ServerInfo_Dashboard_Test extends WP_ServerInfo_TestCase {

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/admin.php';
		require_once ABSPATH . 'wp-admin/includes/dashboard.php';

		set_current_screen( 'dashboard' );

		$GLOBALS['wp_meta_boxes'] = array();
	}

	/**
	 * Run wp_dashboard_setup and return the registered widget, or null.
	 *
	 * @return array|null
	 */
	private function register() {
		$GLOBALS['wp_meta_boxes'] = array();
		do_action( 'wp_dashboard_setup' );

		return $GLOBALS['wp_meta_boxes']['dashboard']['normal']['core'][ WP_SERVERINFO_WIDGET_ID ] ?? null;
	}

	/**
	 * Render the widget and return its HTML.
	 *
	 * @return string
	 */
	private function render() {
		$widget = $this->register();

		$this->assertNotNull( $widget, 'The dashboard widget was not registered.' );

		ob_start();
		call_user_func( $widget['callback'] );
		return ob_get_clean();
	}

	public function test_widget_is_registered_for_an_administrator() {
		wp_set_current_user( $this->create_admin() );

		$this->assertNotNull( $this->register(), 'An administrator gets the dashboard widget.' );
	}

	/**
	 * The widget reports the document root, server IP and every PHP limit, so
	 * it must not appear for users who cannot already see that elsewhere.
	 *
	 * @dataProvider data_roles_without_manage_options
	 *
	 * @param string $role Role slug to test with.
	 */
	public function test_widget_is_hidden_from_users_without_manage_options( $role ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );

		$this->assertNull( $this->register(), 'A user without manage_options gets no dashboard widget.' );
	}

	public function data_roles_without_manage_options() {
		return array(
			'editor'      => array( 'editor' ),
			'author'      => array( 'author' ),
			'contributor' => array( 'contributor' ),
			'subscriber'  => array( 'subscriber' ),
		);
	}

	public function test_widget_is_hidden_from_logged_out_visitors() {
		wp_set_current_user( 0 );

		$this->assertNull( $this->register(), 'A logged out visitor gets no dashboard widget.' );
	}

	public function test_widget_renders_each_section() {
		wp_set_current_user( $this->create_admin() );

		$html = $this->render();

		$this->assertStringContainsString( 'General', $html, 'The widget renders the general section.' );
		$this->assertStringContainsString( 'PHP', $html, 'The PHP section.' );
		$this->assertStringContainsString( 'MYSQL', $html, 'And the MySQL section.' );
		$this->assertStringContainsString( 'Memory Limit', $html, 'With the memory limit row.' );
		$this->assertStringContainsString( 'Max Script Execute Time', $html, 'And the execution time row.' );
	}

	/**
	 * The "View all" button follows the report, which moved from the Dashboard
	 * menu to Tools in 3.0.0.
	 */
	public function test_widget_links_to_the_report_under_tools() {
		wp_set_current_user( $this->create_admin() );

		$html = $this->render();

		$this->assertStringContainsString( esc_url( WP_ServerInfo_Admin::url() ), $html, 'The widget links to the report at the URL the admin class builds.' );
		$this->assertStringContainsString( 'tools.php', $html, 'Which is under Tools.' );
		$this->assertStringNotContainsString( 'index.php?page=wp-serverinfo', $html, 'Rather than the Dashboard, where the screen used to live.' );
	}

	/**
	 * Section 4.4 allows no inline style attribute anywhere, and section 5 no
	 * !important. Before 3.0.0 an RTL admin got both: a style attribute on the
	 * wrapper and a <style> block carrying padding-left: 15px !important.
	 */
	public function test_widget_carries_no_inline_styles() {
		wp_set_current_user( $this->create_admin() );

		$html = $this->render();

		$this->assertStringNotContainsString( '<style', $html, 'The widget carries no style block.' );
		$this->assertStringNotContainsString( 'style=', $html, 'And no inline style attribute.' );
		$this->assertStringNotContainsString( '!important', $html, 'And forces nothing.' );
		$this->assertStringContainsString( 'dir="ltr"', $html, 'The version numbers are marked left-to-right, so an RTL locale does not reverse them.' );
	}

	/**
	 * The version rows read "v<strong>8.3.1</strong>" -- the "v" sits outside
	 * the bold. Restructuring the widget in 3.0.0 moved it inside on the
	 * first pass, which is a visible change to a screen nobody diffs.
	 */
	public function test_version_prefix_stays_outside_the_bold() {
		wp_set_current_user( $this->create_admin() );

		$html = $this->render();

		$this->assertStringContainsString( '<li>v<strong>' . PHP_VERSION . '</strong></li>', $html, 'The v prefix sits outside the bold, so the number alone is emphasised.' );
		$this->assertStringNotContainsString( '<strong>v' . PHP_VERSION, $html, 'Rather than inside it, which reads as part of the version.' );
	}

	public function test_widget_markup_is_undamaged() {
		wp_set_current_user( $this->create_admin() );

		$html = $this->render();

		$this->assertStringNotContainsString( 'translators:', $html, 'No translator comment leaked into the markup.' );
		$this->assertStringNotContainsString( '<?php', $html, 'No PHP tag reached the page either.' );
		$this->assertDoesNotMatchRegularExpression( '/&amp;(nbsp|quot|amp|lt|gt);/', $html, 'An entity has been double-escaped somewhere in the widget.' );
		$this->assertDoesNotMatchRegularExpression( '/Undefined [a-z ]*(key|index|variable|property)/', $html, 'A PHP undefined-key diagnostic leaked into the widget.' );
	}
}
