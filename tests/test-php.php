<?php
/**
 * WP_ServerInfo_PHP.
 *
 * The environment probes. Several of these guard bugs where a perfectly
 * ordinary value was mistaken for a missing one.
 *
 * @package WP-ServerInfo
 */

/**
 * Covers the PHP and host environment probes.
 */
class WP_ServerInfo_PHP_Test extends WP_ServerInfo_TestCase {

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
	 *
	 * The directive is written with set_time_limit() rather than ini_set():
	 * both reach the same setting, but it is the API WordPress asks for, so
	 * the assertion needs neither a silenced error nor a phpcs exclusion. It
	 * returns false where a host has disabled it, which is the same "not
	 * settable here" answer ini_set() gave.
	 */
	public function test_zero_is_a_value_not_a_missing_directive() {
		$original = (int) ini_get( 'max_execution_time' );

		if ( ! set_time_limit( 0 ) ) {
			$this->markTestSkipped( 'max_execution_time is not settable in this SAPI.' );
		}

		$this->assertSame( '0', WP_ServerInfo_PHP::max_execution() );
		$this->assertStringNotContainsString( 'N/A', WP_ServerInfo_PHP::max_execution() );

		set_time_limit( $original );
	}

	public function test_unset_directive_reports_na() {
		$this->assertSame( 'N/A', WP_ServerInfo_PHP::ini_value( 'wp_serverinfo_no_such_directive' ) );
	}

	public function test_known_directives_are_reported() {
		$this->assertNotSame( 'N/A', WP_ServerInfo_PHP::memory_limit() );
		$this->assertNotSame( 'N/A', WP_ServerInfo_PHP::upload_max() );
		$this->assertNotSame( 'N/A', WP_ServerInfo_PHP::post_max() );
	}

	public function test_short_tag_is_a_localized_on_or_off() {
		$this->assertContains( WP_ServerInfo_PHP::short_tag(), array( 'On', 'Off' ) );
	}

	public function test_gd_version_is_reported_or_na() {
		$gd = WP_ServerInfo_PHP::gd_version();

		$this->assertNotEmpty( $gd, 'The GD version is reported, or N/A, but never an empty string.' );

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
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-wp-serverinfo-php.php' );
		$body   = preg_replace( '#/\*.*?\*/#s', '', $source );

		$this->assertStringNotContainsString( 'phpinfo(', $body );
	}

	public function test_server_value_is_sanitized_and_unslashed() {
		$_SERVER['SERVER_SOFTWARE'] = 'nginx\\/1.25 <b>x</b>';

		$value = WP_ServerInfo_PHP::server_value( 'SERVER_SOFTWARE' );

		$this->assertStringNotContainsString( '<b>', $value );
		$this->assertStringNotContainsString( '\\/', $value );
	}

	public function test_missing_server_key_is_an_empty_string() {
		unset( $_SERVER['SERVER_SOFTWARE'] );

		$this->assertSame( '', WP_ServerInfo_PHP::server_value( 'SERVER_SOFTWARE' ) );
	}

	public function test_server_address_reads_server_addr_by_default() {
		$GLOBALS['is_IIS'] = false;

		$_SERVER['SERVER_ADDR'] = '10.0.0.5';
		$_SERVER['LOCAL_ADDR']  = '192.168.1.1';

		$this->assertSame( '10.0.0.5', WP_ServerInfo_PHP::server_address() );
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

		$this->assertSame( '192.168.1.1', WP_ServerInfo_PHP::server_address() );

		$GLOBALS['is_IIS'] = false;
	}

	public function test_server_load_is_a_number_or_na() {
		$load = WP_ServerInfo_PHP::server_load();

		if ( 'N/A' !== $load ) {
			$this->assertIsNumeric( $load, 'When the server reports a load average, it is a number.' );
			$this->assertGreaterThanOrEqual( 0, (float) $load, 'A load average is never negative.' );
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
		WP_ServerInfo_PHP::server_load();
		$printed = ob_get_clean();

		$this->assertSame( '', $printed );
	}

	public function test_server_load_does_not_shell_out_via_system() {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-wp-serverinfo-php.php' );
		$body   = preg_replace( '#/\*.*?\*/#s', '', $source );

		$this->assertDoesNotMatchRegularExpression( '/(?<![a-z_])system\s*\(/', $body, 'Server load shells out through system(), which a hardened host disables.' );
	}

	public function test_summary_reports_the_running_interpreter() {
		$summary = WP_ServerInfo_PHP::summary();

		$this->assertSame( phpversion(), $summary['PHP Version'] );
		$this->assertSame( php_sapi_name(), $summary['Server API'] );
		$this->assertNotEmpty( $summary['Loaded Extensions'], 'The summary reports the extensions of the interpreter actually running.' );
	}

	/**
	 * Neither key set at all is a bare colon in the IP:Port row, not a warning.
	 */
	public function test_server_address_is_empty_when_the_host_reports_neither_key() {
		$GLOBALS['is_IIS'] = false;

		unset( $_SERVER['SERVER_ADDR'], $_SERVER['LOCAL_ADDR'] );

		$this->assertSame( '', WP_ServerInfo_PHP::server_address() );
	}

	/**
	 * A directive whose value is the empty string is not configured, and reads
	 * as N/A -- but "0" is a configured value and must not.
	 */
	public function test_an_empty_directive_reads_as_na_but_zero_does_not() {
		if ( '' !== ini_get( 'error_append_string' ) ) {
			$this->markTestSkipped( 'This host has configured error_append_string, so it is not empty here.' );
		}

		$this->assertSame( 'N/A', WP_ServerInfo_PHP::ini_value( 'error_append_string' ) );
		$this->assertNotSame( 'N/A', WP_ServerInfo_PHP::ini_value( 'precision' ) );
	}

	/**
	 * The four ini getters are one-liners over ini_value(), so the thing worth
	 * asserting is that each reads the directive it says it does rather than
	 * having been copied and half-edited.
	 */
	public function test_each_getter_reads_its_own_directive() {
		$this->assertSame( ini_get( 'memory_limit' ), WP_ServerInfo_PHP::memory_limit() );
		$this->assertSame( ini_get( 'upload_max_filesize' ), WP_ServerInfo_PHP::upload_max() );
		$this->assertSame( ini_get( 'post_max_size' ), WP_ServerInfo_PHP::post_max() );
		$this->assertSame( ini_get( 'max_execution_time' ), WP_ServerInfo_PHP::max_execution() );
	}

	public function test_short_tag_matches_the_running_configuration() {
		$this->assertSame(
			ini_get( 'short_open_tag' ) ? 'On' : 'Off',
			WP_ServerInfo_PHP::short_tag()
		);
	}

	/**
	 * The summary is what the PHP tab's first table is built from, so every row
	 * it promises has to be there -- a missing key renders as an empty cell.
	 */
	public function test_the_summary_holds_every_row_the_php_tab_renders() {
		$summary = WP_ServerInfo_PHP::summary();

		$this->assertSame(
			array(
				'PHP Version',
				'Zend Engine Version',
				'Server API',
				'Loaded Configuration File',
				'Loaded Extensions',
			),
			array_keys( $summary )
		);

		foreach ( $summary as $label => $value ) {
			$this->assertIsString( $value, "{$label} must be a string the template can escape." );
			$this->assertNotSame( '', $value, "{$label} must not be blank." );
		}
	}

	/**
	 * On a host built with no ini file at all php_ini_loaded_file() returns
	 * false, and false printed into a table cell is an empty cell rather than an
	 * answer.
	 */
	public function test_the_summary_never_reports_a_boolean() {
		$summary = WP_ServerInfo_PHP::summary();

		$this->assertSame(
			php_ini_loaded_file() ? php_ini_loaded_file() : 'N/A',
			$summary['Loaded Configuration File']
		);
	}

	/**
	 * The panel is built from ini_get_all() rather than by scraping phpinfo(),
	 * which is what stops it printing environment variables and request headers.
	 */
	public function test_the_directive_list_comes_from_ini_get_all() {
		$ini = WP_ServerInfo_PHP::ini_directives();

		$this->assertArrayHasKey( 'memory_limit', $ini, 'The directive list comes from ini_get_all, so memory_limit is in it.' );
		$this->assertArrayNotHasKey( 'HTTP_COOKIE', $ini, 'The directive list is ini_get_all only; a request header has no business in it.' );
		$this->assertSame( ini_get( 'memory_limit' ), $ini['memory_limit']['local_value'] );
	}

	public function test_ini_directives_are_sorted_and_shaped() {
		$ini = WP_ServerInfo_PHP::ini_directives();

		$this->assertNotEmpty( $ini, 'The directive list is not empty, or the shape assertions below are vacuous.' );

		$keys   = array_keys( $ini );
		$sorted = $keys;
		sort( $sorted );

		$this->assertSame( $sorted, $keys );

		$first = reset( $ini );
		$this->assertIsArray( $first, 'Each directive is an array of its values.' );
		$this->assertArrayHasKey( 'local_value', $first, 'Each directive carries its local_value, which the screen reads.' );
		$this->assertArrayHasKey( 'global_value', $first, 'Each directive carries its global_value, which the screen reads.' );
	}
}
