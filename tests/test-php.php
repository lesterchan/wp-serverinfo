<?php
/**
 * ServerInfo_PHP.
 *
 * The environment probes. Several of these guard bugs where a perfectly
 * ordinary value was mistaken for a missing one.
 *
 * @package WP-ServerInfo
 */

/**
 * Covers the PHP and host environment probes.
 */
class Test_ServerInfo_PHP extends WP_UnitTestCase {

	/**
	 * $_SERVER keys these tests overwrite, restored in tear_down.
	 *
	 * @var array
	 */
	private $server_backup = array();

	public function set_up() {
		parent::set_up();

		$this->server_backup = $_SERVER;
	}

	public function tear_down() {
		$_SERVER = $this->server_backup;

		parent::tear_down();
	}

	/**
	 * The old getters used `if ( ini_get( $x ) )`, so the string "0" read as
	 * absent. max_execution_time is 0 on any host with no script timeout, and
	 * those installs displayed "N/A" -- rendered "N/As", because the template
	 * appends the unit.
	 */
	public function test_zero_is_a_value_not_a_missing_directive() {
		$original = ini_get( 'max_execution_time' );

		if ( false === @ini_set( 'max_execution_time', '0' ) ) {
			$this->markTestSkipped( 'max_execution_time is not settable in this SAPI.' );
		}

		$this->assertSame( '0', ServerInfo_PHP::max_execution() );
		$this->assertStringNotContainsString( 'N/A', ServerInfo_PHP::max_execution() );

		@ini_set( 'max_execution_time', $original );
	}

	public function test_unset_directive_reports_na() {
		$this->assertSame( 'N/A', ServerInfo_PHP::ini_value( 'wp_serverinfo_no_such_directive' ) );
	}

	public function test_known_directives_are_reported() {
		$this->assertNotSame( 'N/A', ServerInfo_PHP::memory_limit() );
		$this->assertNotSame( 'N/A', ServerInfo_PHP::upload_max() );
		$this->assertNotSame( 'N/A', ServerInfo_PHP::post_max() );
	}

	public function test_short_tag_is_a_localized_on_or_off() {
		$this->assertContains( ServerInfo_PHP::short_tag(), array( 'On', 'Off' ) );
	}

	public function test_gd_version_is_reported_or_na() {
		$gd = ServerInfo_PHP::gd_version();

		$this->assertNotEmpty( $gd );

		if ( function_exists( 'gd_info' ) ) {
			$info = gd_info();
			$this->assertSame( $info['GD Version'], $gd );
		} else {
			$this->assertSame( 'N/A', $gd );
		}
	}

	/**
	 * 2.0.0 removed phpinfo() scraping from the PHP tab for information
	 * disclosure, but the GD probe kept a scraping fallback that buffered the
	 * whole PHP configuration. It could never have worked anyway: gd_info()
	 * and the GD version come from the same extension.
	 */
	public function test_gd_probe_does_not_scrape_phpinfo() {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-serverinfo-php.php' );
		$body   = preg_replace( '#/\*.*?\*/#s', '', $source );

		$this->assertStringNotContainsString( 'phpinfo(', $body );
	}

	public function test_server_value_is_sanitized_and_unslashed() {
		$_SERVER['SERVER_SOFTWARE'] = 'nginx\\/1.25 <b>x</b>';

		$value = ServerInfo_PHP::server_value( 'SERVER_SOFTWARE' );

		$this->assertStringNotContainsString( '<b>', $value );
		$this->assertStringNotContainsString( '\\/', $value );
	}

	public function test_missing_server_key_is_an_empty_string() {
		unset( $_SERVER['SERVER_SOFTWARE'] );

		$this->assertSame( '', ServerInfo_PHP::server_value( 'SERVER_SOFTWARE' ) );
	}

	public function test_server_address_reads_server_addr_by_default() {
		$GLOBALS['is_IIS'] = false;

		$_SERVER['SERVER_ADDR'] = '10.0.0.5';
		$_SERVER['LOCAL_ADDR']  = '192.168.1.1';

		$this->assertSame( '10.0.0.5', ServerInfo_PHP::server_address() );
	}

	/**
	 * IIS does not populate SERVER_ADDR. The dashboard widget read it
	 * unconditionally while the General tab branched on $is_IIS, so the widget
	 * rendered a bare ":80" on IIS.
	 */
	public function test_server_address_reads_local_addr_on_iis() {
		$GLOBALS['is_IIS'] = true;

		$_SERVER['SERVER_ADDR'] = '';
		$_SERVER['LOCAL_ADDR']  = '192.168.1.1';

		$this->assertSame( '192.168.1.1', ServerInfo_PHP::server_address() );

		$GLOBALS['is_IIS'] = false;
	}

	public function test_server_load_is_a_number_or_na() {
		$load = ServerInfo_PHP::server_load();

		if ( 'N/A' !== $load ) {
			$this->assertIsNumeric( $load );
			$this->assertGreaterThanOrEqual( 0, (float) $load );
		} else {
			$this->assertSame( 'N/A', $load );
		}
	}

	/**
	 * The system() call writes its output to the response instead of
	 * returning it, so on hosts where it was enabled the raw uptime line was
	 * printed into the middle of the General table.
	 */
	public function test_server_load_never_emits_output() {
		ob_start();
		ServerInfo_PHP::server_load();
		$printed = ob_get_clean();

		$this->assertSame( '', $printed );
	}

	public function test_server_load_does_not_shell_out_via_system() {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-serverinfo-php.php' );
		$body   = preg_replace( '#/\*.*?\*/#s', '', $source );

		$this->assertDoesNotMatchRegularExpression( '/(?<![a-z_])system\s*\(/', $body );
	}

	public function test_summary_reports_the_running_interpreter() {
		$summary = ServerInfo_PHP::summary();

		$this->assertSame( phpversion(), $summary['PHP Version'] );
		$this->assertSame( php_sapi_name(), $summary['Server API'] );
		$this->assertNotEmpty( $summary['Loaded Extensions'] );
	}

	public function test_ini_directives_are_sorted_and_shaped() {
		$ini = ServerInfo_PHP::ini_directives();

		$this->assertNotEmpty( $ini );

		$keys   = array_keys( $ini );
		$sorted = $keys;
		sort( $sorted );

		$this->assertSame( $sorted, $keys );

		$first = reset( $ini );
		$this->assertIsArray( $first );
		$this->assertArrayHasKey( 'local_value', $first );
		$this->assertArrayHasKey( 'global_value', $first );
	}
}
