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
class WP_ServerInfo_Plugin_Test extends WP_UnitTestCase {

	/**
	 * Read the main plugin file.
	 *
	 * @return string
	 */
	private function main_file() {
		return file_get_contents( dirname( __DIR__ ) . '/wp-serverinfo.php' );
	}

	public function test_version_constant_matches_the_header() {
		preg_match( '/^ \* Version: (.+)$/m', $this->main_file(), $matches );

		$this->assertSame( trim( $matches[1] ), WP_SERVERINFO_VERSION );
	}

	/**
	 * Stable tag in the readme is the source of truth for the version.
	 */
	public function test_version_constant_matches_the_readme_stable_tag() {
		$readme = file_get_contents( dirname( __DIR__ ) . '/README.md' );

		preg_match( '/^Stable tag: (.+?)\s*$/m', $readme, $matches );

		$this->assertSame( trim( $matches[1] ), WP_SERVERINFO_VERSION );
	}

	public function test_main_file_constant_points_at_the_main_file() {
		$this->assertSame( dirname( __DIR__ ) . '/wp-serverinfo.php', WP_SERVERINFO_MAIN_FILE );
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
}
