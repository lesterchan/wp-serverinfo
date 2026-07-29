<?php
/**
 * MySQL server introspection.
 *
 * @package WP-ServerInfo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads the database server's own version, variables and table statistics.
 *
 * Every row here comes back as an array rather than as an object.
 * SHOW VARIABLES and SHOW TABLE STATUS name their columns Variable_name, Value,
 * Data_length and so on, which are MySQL's spellings and not ours to rename, so
 * reading them as array keys means the coding standard is not being asked to
 * accept a property name nobody here chose. wp-dbmanager reads the same rows the
 * same way.
 *
 * Replaces the global get_mysql_*() functions from before 3.0.0.
 */
class WP_ServerInfo_MySQL {

	/**
	 * Cached SHOW TABLE STATUS rows for this request.
	 *
	 * @var array|null
	 */
	private static $table_status = null;

	/**
	 * Get the MySQL server version.
	 *
	 * @return string|null Version string, or null on failure.
	 */
	public static function version() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live server introspection; results must not be cached.
		return $wpdb->get_var( 'SELECT VERSION() AS version' );
	}

	/**
	 * Get every MySQL server variable.
	 *
	 * @return array List of rows, each with a Variable_name and a Value key.
	 */
	public static function variables() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live server introspection; results must not be cached.
		return (array) $wpdb->get_results( 'SHOW VARIABLES', ARRAY_A );
	}

	/**
	 * Read a single MySQL server variable.
	 *
	 * Returns null when the variable does not exist rather than dereferencing
	 * the null row get_row() hands back. MySQL 8.0 removed query_cache_size
	 * entirely, so that is not a hypothetical: on any modern server the code
	 * before 3.0.0 raised "Attempt to read property on null" while rendering
	 * both the General tab and the dashboard widget.
	 *
	 * @param string $name The MySQL variable name.
	 * @return string|null The value, or null when the variable is not defined.
	 */
	public static function variable( $name ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live server introspection; results must not be cached.
		$row = $wpdb->get_row( $wpdb->prepare( 'SHOW VARIABLES LIKE %s', $name ), ARRAY_A );

		return isset( $row['Value'] ) ? $row['Value'] : null;
	}

	/**
	 * Get the max_allowed_packet value.
	 *
	 * @return string|null Value in bytes, or null when undefined.
	 */
	public static function max_allowed_packet() {
		return self::variable( 'max_allowed_packet' );
	}

	/**
	 * Get the max_connections value.
	 *
	 * @return string|null Maximum connections, or null when undefined.
	 */
	public static function max_connections() {
		return self::variable( 'max_connections' );
	}

	/**
	 * Get the query_cache_size value.
	 *
	 * Returns null on MySQL 8.0+, where the query cache was removed.
	 *
	 * @return string|null Value in bytes, or null when undefined.
	 */
	public static function query_cache_size() {
		return self::variable( 'query_cache_size' );
	}

	/**
	 * Get SHOW TABLE STATUS rows, cached for the request.
	 *
	 * @return array List of table status rows, each keyed by column name.
	 */
	public static function table_status() {
		global $wpdb;

		if ( null === self::$table_status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live server introspection, cached for the request.
			self::$table_status = (array) $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );
		}

		return self::$table_status;
	}

	/**
	 * Sum the data length across all database tables.
	 *
	 * @return int Total data length in bytes.
	 */
	public static function data_usage() {
		$data_usage = 0;

		foreach ( self::table_status() as $table ) {
			$data_usage += isset( $table['Data_length'] ) ? (int) $table['Data_length'] : 0;
		}

		return $data_usage;
	}

	/**
	 * Sum the index length across all database tables.
	 *
	 * @return int Total index length in bytes.
	 */
	public static function index_usage() {
		$index_usage = 0;

		foreach ( self::table_status() as $table ) {
			$index_usage += isset( $table['Index_length'] ) ? (int) $table['Index_length'] : 0;
		}

		return $index_usage;
	}
}
