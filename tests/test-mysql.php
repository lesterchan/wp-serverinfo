<?php
/**
 * WP_ServerInfo_MySQL.
 *
 * These run against the real MySQL server wp-env brings up, which is the
 * point: the null-row bug they guard against only appears when a variable is
 * genuinely absent from a real server.
 *
 * @package WP-ServerInfo
 */

/**
 * Covers MySQL server introspection against a real database.
 */
class WP_ServerInfo_MySQL_Test extends WP_ServerInfo_TestCase {

	/**
	 * SHOW VARIABLES LIKE returns no row for a variable that does not exist,
	 * and get_row() then hands back null. Reading ->Value off that raised
	 * "Attempt to read property on null" on every render of the General tab
	 * and the dashboard widget -- MySQL 8.0 removed query_cache_size, so this
	 * fired on any modern server.
	 */
	public function test_absent_variable_returns_null_without_warning() {
		$this->assertNull( WP_ServerInfo_MySQL::variable( 'wp_serverinfo_no_such_variable' ) );
	}

	public function test_known_variable_returns_its_value() {
		$this->assertNotNull( WP_ServerInfo_MySQL::variable( 'max_connections' ) );
		$this->assertIsNumeric( WP_ServerInfo_MySQL::max_connections() );
	}

	public function test_max_allowed_packet_is_numeric() {
		$this->assertIsNumeric( WP_ServerInfo_MySQL::max_allowed_packet() );
	}

	/**
	 * The query cache is gone in MySQL 8.0 and present in 5.7 and MariaDB, so
	 * the only thing that holds across all of them is that the accessor
	 * answers safely either way.
	 */
	public function test_query_cache_size_is_numeric_or_null() {
		$value = WP_ServerInfo_MySQL::query_cache_size();

		if ( null !== $value ) {
			$this->assertIsNumeric( $value );
		} else {
			$this->assertNull( $value );
		}
	}

	public function test_version_is_reported() {
		$this->assertNotEmpty( WP_ServerInfo_MySQL::version() );
	}

	public function test_variables_listing_is_not_empty() {
		$variables = WP_ServerInfo_MySQL::variables();

		$this->assertNotEmpty( $variables );

		// property_exists() rather than assertObjectHasAttribute(), which is
		// deprecated in PHPUnit 9.6 and gone in 10, or assertObjectHasProperty(),
		// which only exists from 9.6.11.
		$this->assertTrue( property_exists( $variables[0], 'Variable_name' ) );
	}

	public function test_disk_usage_totals_are_non_negative_integers() {
		$this->assertIsInt( WP_ServerInfo_MySQL::data_usage() );
		$this->assertIsInt( WP_ServerInfo_MySQL::index_usage() );
		$this->assertGreaterThanOrEqual( 0, WP_ServerInfo_MySQL::data_usage() );
		$this->assertGreaterThanOrEqual( 0, WP_ServerInfo_MySQL::index_usage() );
	}

	/**
	 * SHOW TABLE STATUS is the plugin's most expensive query and both disk
	 * usage figures need it, so it is cached for the request.
	 */
	public function test_table_status_is_queried_once_per_request() {
		global $wpdb;

		WP_ServerInfo_MySQL::table_status();

		$before = $wpdb->num_queries;
		WP_ServerInfo_MySQL::data_usage();
		WP_ServerInfo_MySQL::index_usage();
		WP_ServerInfo_MySQL::table_status();

		$this->assertSame( $before, $wpdb->num_queries );
	}
}
