<?php
/**
 * Plugin bootstrap.
 *
 * @package WP-ServerInfo
 */

/**
 * Covers the main file's contract: constants, hook wiring and the rule that
 * the main file holds no logic.
 */
class WP_ServerInfo_Plugin_Test extends WP_ServerInfo_TestCase {

	/**
	 * Read the main plugin file.
	 *
	 * @return string
	 */
	private function main_file() {
		return wp_serverinfo_test_read( 'wp-serverinfo.php' );
	}

	public function test_version_constant_matches_the_header() {
		preg_match( '/^ \* Version: (.+)$/m', $this->main_file(), $matches );

		$this->assertSame( trim( $matches[1] ), WP_SERVERINFO_VERSION );
	}

	/**
	 * Stable tag in the readme is the source of truth for the version.
	 */
	public function test_version_constant_matches_the_readme_stable_tag() {
		$readme = wp_serverinfo_test_read( 'README.md' );

		preg_match( '/^Stable tag: (.+?)\s*$/m', $readme, $matches );

		$this->assertSame( trim( $matches[1] ), WP_SERVERINFO_VERSION );
	}

	public function test_main_file_constant_points_at_the_main_file() {
		$this->assertSame( dirname( __DIR__ ) . '/wp-serverinfo.php', WP_SERVERINFO_MAIN_FILE );
	}

	/**
	 * Section 2.3's constants, in that order, because the order is the thing an
	 * edit drifts out of. There is no DB_VERSION: no schema, nothing stored.
	 */
	public function test_the_constants_are_defined_in_order() {
		preg_match_all( "/^define\( 'WP_SERVERINFO_([A-Z_]+)'/m", $this->main_file(), $matches );

		$this->assertSame(
			array( 'VERSION', 'SLUG', 'MAIN_FILE', 'DIR', 'URL', 'WIDGET_ID' ),
			$matches[1]
		);
	}

	public function test_the_path_constants_carry_a_trailing_slash() {
		$this->assertSame( dirname( __DIR__ ) . '/', WP_SERVERINFO_DIR );
		$this->assertStringEndsWith( '/', WP_SERVERINFO_URL );
		$this->assertStringEndsWith( '/wp-serverinfo/', WP_SERVERINFO_URL );
	}

	/**
	 * There is no schema counter, because there is no schema and no stored row.
	 *
	 * See STANDARDS.md 2.1: a plugin with no settings and no tables stores
	 * nothing, so it has nothing for a DB version to describe.
	 */
	public function test_there_is_no_db_version_constant() {
		$this->assertFalse( defined( 'WP_SERVERINFO_DB_VERSION' ), 'The schema counter is back without a schema to count.' );
	}

	public function test_the_slug_constant_is_the_directory_name() {
		$this->assertSame( 'wp-serverinfo', WP_SERVERINFO_SLUG );
		$this->assertSame( basename( dirname( __DIR__ ) ), WP_SERVERINFO_SLUG );
	}

	public function test_every_class_is_loaded() {
		foreach (
			array(
				'WP_ServerInfo',
				'WP_ServerInfo_Admin',
				'WP_ServerInfo_Cache',
				'WP_ServerInfo_Dashboard',
				'WP_ServerInfo_Format',
				'WP_ServerInfo_MySQL',
				'WP_ServerInfo_PHP',
			) as $class
		) {
			$this->assertTrue( class_exists( $class ), "$class was not loaded." );
		}
	}

	public function test_get_instance_returns_the_same_object() {
		$this->assertSame( WP_ServerInfo::get_instance(), WP_ServerInfo::get_instance() );
	}

	public function test_the_two_admin_surfaces_are_hooked() {
		$this->assertNotFalse(
			has_action( 'admin_menu', array( WP_ServerInfo_Admin::class, 'add_page' ) )
		);
		$this->assertNotFalse(
			has_action( 'wp_dashboard_setup', array( WP_ServerInfo_Dashboard::class, 'register_widget' ) )
		);
	}

	/**
	 * The requires are ordered so that a class is loaded after everything it
	 * names: the probes first, the two surfaces after those, the bootstrap last.
	 */
	public function test_every_include_is_required_before_the_bootstrap() {
		preg_match_all( '#includes/(class-wp-serverinfo[a-z-]*\.php)#', $this->main_file(), $matches );

		$this->assertSame( 'class-wp-serverinfo.php', end( $matches[1] ) );

		$shipped = array_map( 'basename', (array) glob( dirname( __DIR__ ) . '/includes/class-*.php' ) );

		sort( $shipped );
		$required = $matches[1];
		sort( $required );

		$this->assertSame( $shipped, $required, 'Every class file in includes/ must be required.' );
	}

	/**
	 * The main file is header, constants, requires and boot. Anything else
	 * belongs in includes/.
	 */
	public function test_main_file_declares_no_functions_or_classes() {
		$source = $this->main_file();

		$this->assertDoesNotMatchRegularExpression( '/^\s*function\s+\w+\s*\(/m', $source );
		$this->assertDoesNotMatchRegularExpression( '/^\s*class\s+\w+/m', $source );
	}

	/**
	 * Since WP 6.7 calling load_plugin_textdomain() early triggers
	 * _doing_it_wrong, and WordPress.org-hosted plugins get translations
	 * delivered automatically regardless.
	 */
	public function test_no_textdomain_loader() {
		// Two globs rather than GLOB_BRACE, which is not defined on every
		// platform PHP is built for.
		$files = array_merge(
			glob( dirname( __DIR__ ) . '/*.php' ),
			glob( dirname( __DIR__ ) . '/includes/*.php' )
		);

		foreach ( $files as $file ) {
			$this->assertStringNotContainsString(
				'load_plugin_textdomain',
				file_get_contents( $file ),
				basename( $file ) . ' calls load_plugin_textdomain().'
			);
		}
	}

	/**
	 * Invariant #17: a silence guard in the root and in every subdirectory
	 * shipping PHP.
	 */
	public function test_directories_holding_php_carry_a_silence_guard() {
		$root = dirname( __DIR__ );

		foreach ( array( '', '/includes', '/tests', '/bin' ) as $dir ) {
			$this->assertFileExists( $root . $dir . '/index.php' );
		}
	}

	public function test_plugin_declares_its_floors() {
		$source = $this->main_file();

		$this->assertMatchesRegularExpression( '/^ \* Requires at least: 6\.8$/m', $source );
		$this->assertMatchesRegularExpression( '/^ \* Requires PHP: 8\.2$/m', $source );
	}

	/**
	 * PHP 8.2 is the floor, so a guard for anything below it is unreachable code
	 * documenting a version the plugin no longer supports.
	 */
	public function test_no_dead_back_compat_guard_survives() {
		$code = wp_serverinfo_test_source_code();

		$this->assertStringNotContainsString( 'PHP_VERSION_ID <', $code );
		$this->assertStringNotContainsString( 'version_compare( PHP_VERSION', $code );
		$this->assertStringNotContainsString( 'version_compare( $wp_version', $code );
		$this->assertStringNotContainsString( 'function_exists( \'add_submenu_page\' )', $code );
	}
}
