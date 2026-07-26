<?php
/**
 * Plugin Name: WP-ServerInfo
 * Plugin URI: https://lesterchan.net/portfolio/programming/php/
 * Description: Display your host's PHP, MYSQL, memcached & Redis information on your WordPress dashboard.
 * Version: 2.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Lester 'GaMerZ' Chan
 * Author URI: https://lesterchan.net
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-serverinfo
 * Domain Path: /languages
 *
 * @package WP-ServerInfo
 */

/*
	Copyright 2026 Lester Chan  (email : lesterchan@gmail.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


// Prevent direct access.
defined( 'ABSPATH' ) || exit;

// Plugin version.
define( 'WP_SERVERINFO_VERSION', '2.0.0' );

add_action( 'admin_menu', 'serverinfo_menu' );
/**
 * Register the WP-ServerInfo submenu page under the Dashboard menu.
 *
 * @return void
 */
function serverinfo_menu() {
	if ( function_exists( 'add_submenu_page' ) ) {
		add_submenu_page( 'index.php', __( 'WP-ServerInfo', 'wp-serverinfo' ), __( 'WP-ServerInfo', 'wp-serverinfo' ), 'manage_options', 'wp-serverinfo', 'display_serverinfo' );
	}
}


/**
 * Render the WP-ServerInfo admin page as tabbed panels.
 *
 * The active panel is chosen via the `tab` query argument; memcached and Redis
 * tabs appear only when their PHP extension is available.
 *
 * @return void
 */
function display_serverinfo() {
	$tabs = array(
		'general' => __( 'General', 'wp-serverinfo' ),
		'php'     => __( 'PHP', 'wp-serverinfo' ),
		'mysql'   => __( 'MySQL', 'wp-serverinfo' ),
	);
	if ( serverinfo_has_memcached() ) {
		$tabs['memcached'] = __( 'memcached', 'wp-serverinfo' );
	}
	if ( serverinfo_has_redis() ) {
		$tabs['redis'] = __( 'Redis', 'wp-serverinfo' );
	}

	// Read-only tab selection driven by the URL; no state change, so no nonce is needed.
	$active = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
	if ( ! isset( $tabs[ $active ] ) ) {
		$active = 'general';
	}

	echo '<style type="text/css">.wrap .widefat tbody tr:hover td { background-color: #f6f7f7; }</style>' . "\n";
	echo '<div class="wrap">' . "\n";
	echo '<h1>' . esc_html__( 'WP-ServerInfo', 'wp-serverinfo' ) . '</h1>' . "\n";

	echo '<h2 class="nav-tab-wrapper">';
	foreach ( $tabs as $tab => $label ) {
		$url = add_query_arg(
			array(
				'page' => 'wp-serverinfo',
				'tab'  => $tab,
			),
			admin_url( 'index.php' )
		);
		printf(
			'<a href="%s" class="nav-tab%s">%s</a>',
			esc_url( $url ),
			$active === $tab ? ' nav-tab-active' : '',
			esc_html( $label )
		);
	}
	echo '</h2>' . "\n";

	switch ( $active ) {
		case 'php':
			get_phpinfo();
			break;
		case 'mysql':
			get_mysqlinfo();
			break;
		case 'memcached':
			get_memcachedinfo();
			break;
		case 'redis':
			get_redisinfo();
			break;
		default:
			get_generalinfo();
			break;
	}

	echo '</div>' . "\n";
}


/**
 * Output the General Overview panel.
 *
 * @return void
 */
function get_generalinfo() {
	if ( is_rtl() ) : ?>
		<style type="text/css">
			#GeneralOverview table,
			#GeneralOverview th,
			#GeneralOverview td {
				direction: ltr;
				text-align: left;
			}
			#GeneralOverview h2 {
				padding: 0.5em 0 0;
			}
		</style>
		<?php
	endif;
	?>
	<div id="GeneralOverview">
		<h2><?php esc_html_e( 'General Overview', 'wp-serverinfo' ); ?></h2>
		<table class="widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Variable Name', 'wp-serverinfo' ); ?></th>
					<th><?php esc_html_e( 'Value', 'wp-serverinfo' ); ?></th>
					<th><?php esc_html_e( 'Variable Name', 'wp-serverinfo' ); ?></th>
					<th><?php esc_html_e( 'Value', 'wp-serverinfo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php esc_html_e( 'OS', 'wp-serverinfo' ); ?></td>
					<td><?php echo esc_html( PHP_OS ); ?></td>
					<td><?php esc_html_e( 'Database Data Disk Usage', 'wp-serverinfo' ); ?></td>
					<td><?php echo esc_html( format_filesize( get_mysql_data_usage() ) ); ?></td>
				</tr>
				<tr class="alternate">
					<td><?php esc_html_e( 'Server', 'wp-serverinfo' ); ?></td>
					<td><?php echo esc_html( sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ?? '' ) ) ); ?></td>
					<td><?php esc_html_e( 'Database Index Disk Usage', 'wp-serverinfo' ); ?></td>
					<td><?php echo esc_html( format_filesize( get_mysql_index_usage() ) ); ?></td>
				</tr>
				<tr>
					<td>PHP</td>
					<td>v<?php echo esc_html( PHP_VERSION ); ?></td>
					<td><?php esc_html_e( 'MYSQL Maximum Packet Size', 'wp-serverinfo' ); ?></td>
					<td><?php echo esc_html( format_filesize( get_mysql_max_allowed_packet() ) ); ?></td>
				</tr>
				<tr class="alternate">
					<td>MYSQL</td>
					<td>v<?php echo esc_html( get_mysql_version() ); ?></td>
					<td><?php esc_html_e( 'MYSQL Maximum No. Connection', 'wp-serverinfo' ); ?></td>
					<td><?php echo esc_html( serverinfo_format_number( get_mysql_max_allowed_connections() ) ); ?></td>
				</tr>
				<tr>
					<td>GD</td>
					<td><?php echo esc_html( get_gd_version() ); ?></td>
					<td><?php esc_html_e( 'MYSQL Query Cache Size', 'wp-serverinfo' ); ?></td>
					<td><?php echo esc_html( format_filesize( get_mysql_query_cache_size() ) ); ?></td>
				</tr>
				<tr class="alternate">
					<td><?php esc_html_e( 'Server Hostname', 'wp-serverinfo' ); ?></td>
					<td><?php echo esc_html( sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ?? '' ) ) ); ?></td>
					<td><?php esc_html_e( 'PHP Short Tag', 'wp-serverinfo' ); ?></td>
					<td><?php echo esc_html( get_php_short_tag() ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Server IP:Port', 'wp-serverinfo' ); ?></td>
					<td><?php echo esc_html( serverinfo_get_server_address() ); ?>:<?php echo esc_html( sanitize_text_field( wp_unslash( $_SERVER['SERVER_PORT'] ?? '' ) ) ); ?></td>
					<td><?php esc_html_e( 'PHP Max Script Execute Time', 'wp-serverinfo' ); ?></td>
					<td><?php echo esc_html( get_php_max_execution() ); ?>s</td>
				</tr>
				<tr class="alternate">
					<td><?php esc_html_e( 'Server Document Root', 'wp-serverinfo' ); ?></td>
					<td><?php echo esc_html( sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ?? '' ) ) ); ?></td>
					<td><?php esc_html_e( 'PHP Memory Limit', 'wp-serverinfo' ); ?></td>
					<td><?php echo esc_html( format_php_size( get_php_memory_limit() ) ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Server Date/Time', 'wp-serverinfo' ); ?></td>
					<td><?php echo esc_html( mysql2date( sprintf( /* translators: 1: date format, 2: time format */ __( '%1$s @ %2$s', 'wp-serverinfo' ), get_option( 'date_format' ), get_option( 'time_format' ) ), current_time( 'mysql' ) ) ); ?></td>
					<td><?php esc_html_e( 'PHP Max Upload Size', 'wp-serverinfo' ); ?></td>
					<td><?php echo esc_html( format_php_size( get_php_upload_max() ) ); ?></td>
				</tr>
				<tr class="alternate">
					<td><?php esc_html_e( 'Server Load', 'wp-serverinfo' ); ?></td>
					<td><?php echo esc_html( get_serverLoad() ); ?></td>
					<td><?php esc_html_e( 'PHP Max Post Size', 'wp-serverinfo' ); ?></td>
					<td><?php echo esc_html( format_php_size( get_php_post_max() ) ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>
	<?php
}


/**
 * Output the PHP Information panel, built from structured PHP data.
 *
 * @return void
 */
function get_phpinfo() {
	echo '<div id="PHPinfo">' . "\n";
	echo '<h2>PHP ' . esc_html( phpversion() ) . '</h2>' . "\n";

	// Summary built from structured PHP data (no phpinfo() HTML scraping).
	$summary = array(
		__( 'PHP Version', 'wp-serverinfo' )               => phpversion(),
		__( 'Zend Engine Version', 'wp-serverinfo' )       => zend_version(),
		__( 'Server API', 'wp-serverinfo' )                => php_sapi_name(),
		__( 'Loaded Configuration File', 'wp-serverinfo' ) => php_ini_loaded_file() ? php_ini_loaded_file() : __( 'N/A', 'wp-serverinfo' ),
		__( 'Loaded Extensions', 'wp-serverinfo' )         => implode( ', ', get_loaded_extensions() ),
	);
	echo '<br class="clear" />' . "\n";
	echo '<table class="widefat"><tbody>' . "\n";
	foreach ( $summary as $label => $value ) {
		echo '<tr><td><strong>' . esc_html( $label ) . '</strong></td><td>' . esc_html( $value ) . '</td></tr>' . "\n";
	}
	echo '</tbody></table>' . "\n";

	// Configuration directives from ini_get_all().
	$ini = function_exists( 'ini_get_all' ) ? ini_get_all( null, true ) : false;
	if ( ! empty( $ini ) ) {
		ksort( $ini );
		echo '<br class="clear" />' . "\n";
		echo '<table class="widefat"><thead><tr><th>' . esc_html__( 'Directive', 'wp-serverinfo' ) . '</th><th>' . esc_html__( 'Local Value', 'wp-serverinfo' ) . '</th><th>' . esc_html__( 'Master Value', 'wp-serverinfo' ) . '</th></tr></thead><tbody>' . "\n";
		foreach ( $ini as $directive => $values ) {
			$local  = isset( $values['local_value'] ) ? $values['local_value'] : '';
			$global = isset( $values['global_value'] ) ? $values['global_value'] : '';
			echo '<tr><td>' . esc_html( $directive ) . '</td><td>' . esc_html( $local ) . '</td><td>' . esc_html( $global ) . '</td></tr>' . "\n";
		}
		echo '</tbody></table>' . "\n";
	}
	echo '</div>' . "\n";
}


/**
 * Output the MYSQL Information panel (server variables).
 *
 * @return void
 */
function get_mysqlinfo() {
	global $wpdb;
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live server introspection; results must not be cached.
	$sqlversion = $wpdb->get_var( 'SELECT VERSION() AS version' );
	$mysqlinfo  = $wpdb->get_results( 'SHOW VARIABLES' );
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	if ( is_rtl() ) :
		?>
		<style type="text/css">
			#MYSQLinfo,
			#MYSQLinfo table,
			#MYSQLinfo th,
			#MYSQLinfo td {
				direction: ltr;
				text-align: left;
			}
			#MYSQLinfo h2 {
				padding: 0.5em 0 0;
			}
		</style>
		<?php
	endif;
	echo '<div id="MYSQLinfo">' . "\n";
	echo '<h2>MYSQL ' . esc_html( $sqlversion ) . "</h2>\n";
	if ( $mysqlinfo ) {
		echo '<br class="clear" />' . "\n";
		echo '<table class="widefat" dir="ltr">' . "\n";
		echo '<thead><tr><th>' . esc_html__( 'Variable Name', 'wp-serverinfo' ) . '</th><th>' . esc_html__( 'Value', 'wp-serverinfo' ) . '</th></tr></thead><tbody>' . "\n";
		foreach ( $mysqlinfo as $info ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL column names from SHOW VARIABLES.
			echo '<tr><td>' . esc_html( $info->Variable_name ) . '</td><td>' . esc_html( $info->Value ) . '</td></tr>' . "\n";
		}
		echo '</tbody></table>' . "\n";
	}
	echo '</div>' . "\n";
}


if ( ! function_exists( 'serverinfo_has_memcached' ) ) {
	/**
	 * Determine whether a memcached PHP extension is available.
	 *
	 * @return bool True when the Memcached or Memcache extension is loaded.
	 */
	function serverinfo_has_memcached() {
		return class_exists( 'Memcached' ) || class_exists( 'Memcache' );
	}
}


if ( ! function_exists( 'serverinfo_get_memcached_stats' ) ) {
	/**
	 * Fetch memcached server statistics, preferring Memcached over Memcache.
	 *
	 * @return array|false Flat stats array for the local server, or false when unavailable.
	 */
	function serverinfo_get_memcached_stats() {
		if ( class_exists( 'Memcached' ) ) {
			$memcached_obj = new Memcached();
			$memcached_obj->addServer( 'localhost', 11211 );
			$stats = $memcached_obj->getStats();
			// Memcached::getStats() returns an array keyed by "host:port".
			return is_array( $stats ) && ! empty( $stats ) ? reset( $stats ) : false;
		} elseif ( class_exists( 'Memcache' ) ) {
			$memcached_obj = new Memcache();
			@$memcached_obj->addServer( 'localhost', 11211 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- addServer() can emit a connection warning when memcached is down; suppressed intentionally.
			return $memcached_obj->getStats();
		}
		return false;
	}
}


/**
 * Output the memcached Information panel.
 *
 * Stat descriptions from https://boxpanel.blueboxgrp.com/public/the_vault/index.php/memcached_Tips
 *
 * @return void
 */
function get_memcachedinfo() {
	echo '<div id="memcachedinfo">' . "\n";
	if ( serverinfo_has_memcached() ) {
		$memcachedinfo = serverinfo_get_memcached_stats();
		if ( is_rtl() ) :
			?>
			<style type="text/css">
				#memcachedinfo,
				#memcachedinfo table,
				#memcachedinfo th,
				#memcachedinfo td {
					direction: ltr;
					text-align: left;
				}
				#memcachedinfo h2 {
					padding: 0.5em 0 0;
				}
			</style>
			<?php
		endif;
		echo '<h2>memcached ' . esc_html( $memcachedinfo['version'] ?? '' ) . "</h2>\n";
		if ( $memcachedinfo ) {
			/*
			 * Read every stat through this rather than indexing directly. The
			 * stat set is not fixed: it varies by memcached version and by
			 * extension (Memcache exposes a smaller set than Memcached), and
			 * several of the keys below were only added in 1.4.x. Indexing a
			 * key the server did not report warns on PHP 8 and prints the
			 * warning into the middle of the table.
			 */
			$stat = function ( $key, $fallback = 0 ) use ( $memcachedinfo ) {
				return isset( $memcachedinfo[ $key ] ) ? $memcachedinfo[ $key ] : $fallback;
			};

			$cmd_get    = (float) $stat( 'cmd_get' );
			$cache_hit  = $cmd_get > 0 ? ( ( (float) $stat( 'get_hits' ) / $cmd_get ) * 100 ) : 0;
			$cache_hit  = round( $cache_hit, 2 );
			$cache_miss = 100 - $cache_hit;

			$max_bytes = (float) $stat( 'limit_maxbytes' );
			$usage     = $max_bytes > 0 ? round( ( ( (float) $stat( 'bytes' ) / $max_bytes ) * 100 ), 2 ) : 0;
			$uptime    = number_format_i18n( (float) $stat( 'uptime' ) / 60 / 60 / 24 );

			echo '<br class="clear" />' . "\n";
			echo '<table class="widefat" dir="ltr">' . "\n";
			echo '<thead><tr><th>' . esc_html__( 'Variable Name', 'wp-serverinfo' ) . '</th><th>' . esc_html__( 'Value', 'wp-serverinfo' ) . '</th><th>' . esc_html__( 'Description', 'wp-serverinfo' ) . '</th></tr></thead><tbody>' . "\n";

			// Each row: variable name, pre-formatted value, and description; all three cells are escaped uniformly below.
			$memcached_rows = array(
				array( 'pid', $stat( 'pid' ), __( 'Process ID', 'wp-serverinfo' ) ),
				array( 'uptime', $uptime, __( 'Number of days since the process was started', 'wp-serverinfo' ) ),
				array( 'version', $stat( 'version', '' ), __( 'memcached version', 'wp-serverinfo' ) ),
				array( 'rusage_user', $stat( 'rusage_user' ), __( 'Seconds the cpu has devoted to the process as the user', 'wp-serverinfo' ) ),
				array( 'rusage_system', $stat( 'rusage_system' ), __( 'Seconds the cpu has devoted to the process as the system', 'wp-serverinfo' ) ),
				array( 'curr_items', number_format_i18n( $stat( 'curr_items' ) ), __( 'Total number of items currently in memcached', 'wp-serverinfo' ) ),
				array( 'total_items', number_format_i18n( $stat( 'total_items' ) ), __( 'Total number of items that have passed through memcached', 'wp-serverinfo' ) ),
				array( 'bytes', format_filesize( $stat( 'bytes' ) ) . ' (' . $usage . '%)', __( 'Memory size currently used by curr_items', 'wp-serverinfo' ) ),
				array( 'limit_maxbytes', format_filesize( $stat( 'limit_maxbytes' ) ), __( 'Maximum memory size allocated to memcached', 'wp-serverinfo' ) ),
				array( 'curr_connections', number_format_i18n( $stat( 'curr_connections' ) ), __( 'Total number of open connections to memcached', 'wp-serverinfo' ) ),
				array( 'total_connections', number_format_i18n( $stat( 'total_connections' ) ), __( 'Total number of connections opened since memcached started running', 'wp-serverinfo' ) ),
				array( 'connection_structures', number_format_i18n( $stat( 'connection_structures' ) ), __( 'Number of connection structures allocated by the server', 'wp-serverinfo' ) ),
				array( 'cmd_get', number_format_i18n( $stat( 'cmd_get' ) ), __( 'Total GET commands issued to the server', 'wp-serverinfo' ) ),
				array( 'cmd_set', number_format_i18n( $stat( 'cmd_set' ) ), __( 'Total SET commands issued to the server', 'wp-serverinfo' ) ),
				array( 'cmd_flush', number_format_i18n( $stat( 'cmd_flush' ) ), __( 'Total FLUSH commands issued to the server', 'wp-serverinfo' ) ),
				array( 'get_hits', number_format_i18n( $stat( 'get_hits' ) ) . ' (' . $cache_hit . '%)', __( 'Total number of times a GET command was able to retrieve and return data', 'wp-serverinfo' ) ),
				array( 'get_misses', number_format_i18n( $stat( 'get_misses' ) ) . ' (' . $cache_miss . '%)', __( 'Total number of times a GET command was unable to retrieve and return data', 'wp-serverinfo' ) ),
				array( 'delete_hits', number_format_i18n( $stat( 'delete_hits' ) ), __( 'Total number of times a DELETE command was able to delete data', 'wp-serverinfo' ) ),
				array( 'delete_misses', number_format_i18n( $stat( 'delete_misses' ) ), __( 'Total number of times a DELETE command was unable to delete data', 'wp-serverinfo' ) ),
				array( 'incr_hits', number_format_i18n( $stat( 'incr_hits' ) ), __( 'Total number of times a INCR command was able to increment a value', 'wp-serverinfo' ) ),
				array( 'incr_misses', number_format_i18n( $stat( 'incr_misses' ) ), __( 'Total number of times a INCR command was unable to increment a value', 'wp-serverinfo' ) ),
				array( 'decr_hits', number_format_i18n( $stat( 'decr_hits' ) ), __( 'Total number of times a DECR command was able to decrement a value', 'wp-serverinfo' ) ),
				array( 'decr_misses', number_format_i18n( $stat( 'decr_misses' ) ), __( 'Total number of times a DECR command was unable to decrement a value', 'wp-serverinfo' ) ),
				array( 'cas_hits', number_format_i18n( $stat( 'cas_hits' ) ), __( 'Total number of times a CAS command was able to compare and swap data', 'wp-serverinfo' ) ),
				array( 'cas_misses', number_format_i18n( $stat( 'cas_misses' ) ), __( 'Total number of times a CAS command was unable to compare and swap data', 'wp-serverinfo' ) ),
				array( 'cas_badval', number_format_i18n( $stat( 'cas_badval' ) ), __( 'N/A', 'wp-serverinfo' ) ),
				array( 'bytes_read', format_filesize( $stat( 'bytes_read' ) ), __( 'Total number of bytes input into the server', 'wp-serverinfo' ) ),
				array( 'bytes_written', format_filesize( $stat( 'bytes_written' ) ), __( 'Total number of bytes written by the server', 'wp-serverinfo' ) ),
				array( 'evictions', number_format_i18n( $stat( 'evictions' ) ), __( 'Number of valid items removed from cache to free memory for new items', 'wp-serverinfo' ) ),
				array( 'reclaimed', number_format_i18n( $stat( 'reclaimed' ) ), __( 'Number of items reclaimed', 'wp-serverinfo' ) ),
			);
			foreach ( $memcached_rows as $memcached_row ) {
				echo '<tr><td>' . esc_html( $memcached_row[0] ) . '</td><td>' . esc_html( $memcached_row[1] ) . '</td><td>' . esc_html( $memcached_row[2] ) . '</td></tr>' . "\n";
			}
			echo '</tbody></table>' . "\n";
		}
	}
	echo '</div>' . "\n";
}


if ( ! function_exists( 'serverinfo_has_redis' ) ) {
	/**
	 * Determine whether the phpredis extension is available.
	 *
	 * @return bool True when the Redis class is loaded.
	 */
	function serverinfo_has_redis() {
		return class_exists( 'Redis' );
	}
}


if ( ! function_exists( 'serverinfo_get_redis_stats' ) ) {
	/**
	 * Fetch Redis server info from the local instance via phpredis.
	 *
	 * @return array|false Associative INFO array, or false when unavailable.
	 */
	function serverinfo_get_redis_stats() {
		if ( ! class_exists( 'Redis' ) ) {
			return false;
		}
		try {
			$redis = new Redis();
			// Short timeout so an unreachable server does not stall the admin page.
			if ( ! $redis->connect( '127.0.0.1', 6379, 1 ) ) {
				return false;
			}
			$info = $redis->info();
			$redis->close();
		} catch ( Throwable $e ) {
			/*
			 * Throwable, not Exception: phpredis throws RedisException (an
			 * Exception) for connection trouble, but connect() also raises
			 * ValueError/TypeError on PHP 8 for a malformed host or port.
			 * Those are Errors, not Exceptions, so they escaped the old catch
			 * and took the whole dashboard down with them.
			 */
			return false;
		}
		return is_array( $info ) && ! empty( $info ) ? $info : false;
	}
}


/**
 * Output the Redis Information panel.
 *
 * @return void
 */
function get_redisinfo() {
	echo '<div id="Redisinfo">' . "\n";
	if ( serverinfo_has_redis() ) {
		$redisinfo = serverinfo_get_redis_stats();
		echo '<h2>Redis ' . esc_html( $redisinfo['redis_version'] ?? '' ) . "</h2>\n";
		if ( $redisinfo ) {
			$hits    = isset( $redisinfo['keyspace_hits'] ) ? (int) $redisinfo['keyspace_hits'] : 0;
			$misses  = isset( $redisinfo['keyspace_misses'] ) ? (int) $redisinfo['keyspace_misses'] : 0;
			$lookups = $hits + $misses;
			$hit_pct = $lookups > 0 ? round( ( $hits / $lookups ) * 100, 2 ) : 0;

			echo '<br class="clear" />' . "\n";
			echo '<table class="widefat" dir="ltr">' . "\n";
			echo '<thead><tr><th>' . esc_html__( 'Variable Name', 'wp-serverinfo' ) . '</th><th>' . esc_html__( 'Value', 'wp-serverinfo' ) . '</th><th>' . esc_html__( 'Description', 'wp-serverinfo' ) . '</th></tr></thead><tbody>' . "\n";

			// Each row: variable name, pre-formatted value, and description; all three cells are escaped uniformly below.
			$redis_rows = array(
				array( 'redis_version', $redisinfo['redis_version'] ?? '', __( 'Redis server version', 'wp-serverinfo' ) ),
				array( 'redis_mode', $redisinfo['redis_mode'] ?? '', __( 'Server mode (standalone, sentinel or cluster)', 'wp-serverinfo' ) ),
				array( 'uptime_in_days', number_format_i18n( $redisinfo['uptime_in_days'] ?? 0 ), __( 'Number of days since the server was started', 'wp-serverinfo' ) ),
				array( 'connected_clients', number_format_i18n( $redisinfo['connected_clients'] ?? 0 ), __( 'Number of client connections', 'wp-serverinfo' ) ),
				array( 'used_memory', $redisinfo['used_memory_human'] ?? format_filesize( $redisinfo['used_memory'] ?? 0 ), __( 'Memory allocated by Redis', 'wp-serverinfo' ) ),
				array( 'used_memory_peak', $redisinfo['used_memory_peak_human'] ?? format_filesize( $redisinfo['used_memory_peak'] ?? 0 ), __( 'Peak memory consumed by Redis', 'wp-serverinfo' ) ),
				array( 'maxmemory', $redisinfo['maxmemory_human'] ?? format_filesize( $redisinfo['maxmemory'] ?? 0 ), __( 'Memory limit configured for Redis', 'wp-serverinfo' ) ),
				array( 'maxmemory_policy', $redisinfo['maxmemory_policy'] ?? '', __( 'Eviction policy applied when the memory limit is reached', 'wp-serverinfo' ) ),
				array( 'total_connections_received', number_format_i18n( $redisinfo['total_connections_received'] ?? 0 ), __( 'Total number of connections accepted by the server', 'wp-serverinfo' ) ),
				array( 'total_commands_processed', number_format_i18n( $redisinfo['total_commands_processed'] ?? 0 ), __( 'Total number of commands processed by the server', 'wp-serverinfo' ) ),
				array( 'instantaneous_ops_per_sec', number_format_i18n( $redisinfo['instantaneous_ops_per_sec'] ?? 0 ), __( 'Number of commands processed per second', 'wp-serverinfo' ) ),
				array( 'keyspace_hits', number_format_i18n( $hits ) . ' (' . $hit_pct . '%)', __( 'Number of successful lookups of keys in the main dictionary', 'wp-serverinfo' ) ),
				array( 'keyspace_misses', number_format_i18n( $misses ), __( 'Number of failed lookups of keys in the main dictionary', 'wp-serverinfo' ) ),
				array( 'expired_keys', number_format_i18n( $redisinfo['expired_keys'] ?? 0 ), __( 'Total number of key expiration events', 'wp-serverinfo' ) ),
				array( 'evicted_keys', number_format_i18n( $redisinfo['evicted_keys'] ?? 0 ), __( 'Number of evicted keys due to the memory limit', 'wp-serverinfo' ) ),
				array( 'connected_slaves', number_format_i18n( $redisinfo['connected_slaves'] ?? 0 ), __( 'Number of connected replicas', 'wp-serverinfo' ) ),
			);
			foreach ( $redis_rows as $redis_row ) {
				echo '<tr><td>' . esc_html( $redis_row[0] ) . '</td><td>' . esc_html( $redis_row[1] ) . '</td><td>' . esc_html( $redis_row[2] ) . '</td></tr>' . "\n";
			}
			echo '</tbody></table>' . "\n";
		}
	}
	echo '</div>' . "\n";
}


if ( ! function_exists( 'format_filesize' ) ) {
	/**
	 * Format a byte count into a localized TiB/GiB/MiB/KiB/bytes string.
	 *
	 * @param int|float $raw_size Size in bytes.
	 * @return string Human-readable, localized size.
	 */
	function format_filesize( $raw_size ) {
		if ( ! is_numeric( $raw_size ) || $raw_size < 0 ) {
			return __( 'unknown', 'wp-serverinfo' );
		}

		$raw_size = (float) $raw_size;

		/*
		 * The comparison is >= rather than >, so that a value sitting exactly
		 * on a unit boundary uses that unit. With >, 1073741824 failed the GiB
		 * test by a hair and fell through to the MiB branch, displaying one
		 * gibibyte as "1,024.0 MiB".
		 */
		if ( $raw_size / 1099511627776 >= 1 ) {
			return number_format_i18n( $raw_size / 1099511627776, 1 ) . ' ' . __( 'TiB', 'wp-serverinfo' );
		} elseif ( $raw_size / 1073741824 >= 1 ) {
			return number_format_i18n( $raw_size / 1073741824, 1 ) . ' ' . __( 'GiB', 'wp-serverinfo' );
		} elseif ( $raw_size / 1048576 >= 1 ) {
			return number_format_i18n( $raw_size / 1048576, 1 ) . ' ' . __( 'MiB', 'wp-serverinfo' );
		} elseif ( $raw_size / 1024 >= 1 ) {
			return number_format_i18n( $raw_size / 1024, 1 ) . ' ' . __( 'KiB', 'wp-serverinfo' );
		} else {
			return number_format_i18n( $raw_size, 0 ) . ' ' . __( 'bytes', 'wp-serverinfo' );
		}
	}
}

if ( ! function_exists( 'serverinfo_format_number' ) ) {
	/**
	 * Localize a count, falling back to "N/A" when the value is unavailable.
	 *
	 * Guards number_format_i18n() against the null that a missing MySQL
	 * variable now yields, which would otherwise raise a deprecation on
	 * PHP 8.1+ for passing null to a non-nullable parameter.
	 *
	 * @param mixed $value The value to format.
	 * @return string Localized number, or a localized "N/A".
	 */
	function serverinfo_format_number( $value ) {
		if ( ! is_numeric( $value ) ) {
			return __( 'N/A', 'wp-serverinfo' );
		}

		return number_format_i18n( $value );
	}
}

/**
 * Convert a PHP shorthand size (e.g. "128M") into a localized size string.
 *
 * @param string|int $size PHP size value, shorthand or numeric.
 * @return string Localized size, "Unlimited", or the original value when unparseable.
 */
function format_php_size( $size ) {
	$size = is_string( $size ) ? trim( $size ) : $size;

	// -1 is PHP's "no limit" sentinel for memory_limit; it is not a size.
	if ( '-1' === (string) $size ) {
		return __( 'Unlimited', 'wp-serverinfo' );
	}

	if ( ! is_numeric( $size ) ) {
		/*
		 * PHP's shorthand suffixes are case-insensitive, so "128m" is exactly
		 * as valid as "128M" in php.ini. The old hand-rolled parser only
		 * matched uppercase and handed anything else straight through, so a
		 * host configured in lowercase displayed the raw string "128m".
		 * wp_convert_hr_to_bytes() lowercases before matching.
		 */
		$bytes = wp_convert_hr_to_bytes( $size );

		if ( $bytes > 0 ) {
			return format_filesize( $bytes );
		}

		return $size;
	}

	return format_filesize( $size );
}

if ( ! function_exists( 'serverinfo_get_server_address' ) ) {
	/**
	 * Get the server's own IP address.
	 *
	 * IIS does not set SERVER_ADDR; it exposes the same value as LOCAL_ADDR.
	 * Both display surfaces need the same rule, so it lives in one place.
	 *
	 * @return string The server IP, or an empty string when unavailable.
	 */
	function serverinfo_get_server_address() {
		global $is_IIS;

		$key = $is_IIS ? 'LOCAL_ADDR' : 'SERVER_ADDR';

		return sanitize_text_field( wp_unslash( $_SERVER[ $key ] ?? '' ) );
	}
}


if ( ! function_exists( 'serverinfo_ini_value' ) ) {
	/**
	 * Read an ini directive, distinguishing "unset" from a falsy value.
	 *
	 * The getters below each used `if ( ini_get( $x ) )`, which treats the
	 * perfectly ordinary value "0" as absent. max_execution_time is 0 on any
	 * host that disables the script timeout, so those installs displayed
	 * "N/A" -- and, because the template appends a unit, the string "N/As".
	 *
	 * @param string $directive The ini directive name.
	 * @return string The configured value, or a localized "N/A" when truly unset.
	 */
	function serverinfo_ini_value( $directive ) {
		$value = ini_get( $directive );

		if ( false === $value || '' === $value ) {
			return __( 'N/A', 'wp-serverinfo' );
		}

		return $value;
	}
}


if ( ! function_exists( 'get_php_short_tag' ) ) {
	/**
	 * Get the short_open_tag ini state as a localized On/Off string.
	 *
	 * @return string Localized "On" or "Off".
	 */
	function get_php_short_tag() {
		if ( ini_get( 'short_open_tag' ) ) {
			$short_tag = __( 'On', 'wp-serverinfo' );
		} else {
			$short_tag = __( 'Off', 'wp-serverinfo' );
		}
		return $short_tag;
	}
}


if ( ! function_exists( 'get_php_upload_max' ) ) {
	/**
	 * Get the upload_max_filesize ini value.
	 *
	 * @return string The configured value, or a localized "N/A".
	 */
	function get_php_upload_max() {
		return serverinfo_ini_value( 'upload_max_filesize' );
	}
}


if ( ! function_exists( 'get_php_post_max' ) ) {
	/**
	 * Get the post_max_size ini value.
	 *
	 * @return string The configured value, or a localized "N/A".
	 */
	function get_php_post_max() {
		return serverinfo_ini_value( 'post_max_size' );
	}
}


if ( ! function_exists( 'get_php_max_execution' ) ) {
	/**
	 * Get the max_execution_time ini value.
	 *
	 * @return string The configured value, or a localized "N/A".
	 */
	function get_php_max_execution() {
		return serverinfo_ini_value( 'max_execution_time' );
	}
}


if ( ! function_exists( 'get_php_memory_limit' ) ) {
	/**
	 * Get the memory_limit ini value.
	 *
	 * @return string The configured value, or a localized "N/A".
	 */
	function get_php_memory_limit() {
		return serverinfo_ini_value( 'memory_limit' );
	}
}


if ( ! function_exists( 'get_mysql_version' ) ) {
	/**
	 * Get the MySQL server version.
	 *
	 * @return string|null Version string, or null on failure.
	 */
	function get_mysql_version() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live server introspection; results must not be cached.
		return $wpdb->get_var( 'SELECT VERSION() AS version' );
	}
}


if ( ! function_exists( 'serverinfo_get_mysql_variable' ) ) {
	/**
	 * Read a single MySQL server variable.
	 *
	 * Returns null when the variable does not exist rather than dereferencing
	 * the null row get_row() hands back. MySQL 8.0 removed query_cache_size
	 * entirely, so that is not a hypothetical: on any modern server the old
	 * code raised "Attempt to read property on null" while rendering both the
	 * General tab and the dashboard widget.
	 *
	 * @param string $name The MySQL variable name.
	 * @return string|null The value, or null when the variable is not defined.
	 */
	function serverinfo_get_mysql_variable( $name ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live server introspection; results must not be cached.
		$row = $wpdb->get_row( $wpdb->prepare( 'SHOW VARIABLES LIKE %s', $name ) );

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL column name from SHOW VARIABLES.
		return isset( $row->Value ) ? $row->Value : null;
	}
}


if ( ! function_exists( 'serverinfo_get_table_status' ) ) {
	/**
	 * Get SHOW TABLE STATUS rows, cached in a static for the request.
	 *
	 * @return array List of table status row objects.
	 */
	function serverinfo_get_table_status() {
		global $wpdb;
		static $tablesstatus = null;
		if ( is_null( $tablesstatus ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live server introspection, cached in a static for the request.
			$tablesstatus = $wpdb->get_results( 'SHOW TABLE STATUS' );
		}
		return $tablesstatus;
	}
}


if ( ! function_exists( 'get_mysql_data_usage' ) ) {
	/**
	 * Sum the data length across all database tables.
	 *
	 * @return int Total data length in bytes.
	 */
	function get_mysql_data_usage() {
		$data_usage = 0;
		foreach ( serverinfo_get_table_status() as $tablestatus ) {
			$data_usage += $tablestatus->Data_length; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL column name from SHOW TABLE STATUS.
		}

		return $data_usage;
	}
}


if ( ! function_exists( 'get_mysql_index_usage' ) ) {
	/**
	 * Sum the index length across all database tables.
	 *
	 * @return int Total index length in bytes.
	 */
	function get_mysql_index_usage() {
		$index_usage = 0;
		foreach ( serverinfo_get_table_status() as $tablestatus ) {
			$index_usage += $tablestatus->Index_length; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL column name from SHOW TABLE STATUS.
		}

		return $index_usage;
	}
}


if ( ! function_exists( 'get_mysql_max_allowed_packet' ) ) {
	/**
	 * Get the MySQL max_allowed_packet value.
	 *
	 * @return string|null Value in bytes, or null on failure.
	 */
	function get_mysql_max_allowed_packet() {
		return serverinfo_get_mysql_variable( 'max_allowed_packet' );
	}
}


if ( ! function_exists( 'get_mysql_max_allowed_connections' ) ) {
	/**
	 * Get the MySQL max_connections value.
	 *
	 * @return string|null Maximum connections, or null on failure.
	 */
	function get_mysql_max_allowed_connections() {
		return serverinfo_get_mysql_variable( 'max_connections' );
	}
}

if ( ! function_exists( 'get_mysql_query_cache_size' ) ) {
	/**
	 * Get the MySQL query_cache_size value.
	 *
	 * Returns 0 on MySQL 8.0+, where the query cache was removed.
	 *
	 * @return string|int Value in bytes, or 0 when unavailable.
	 */
	function get_mysql_query_cache_size() {
		return serverinfo_get_mysql_variable( 'query_cache_size' );
	}
}


if ( ! function_exists( 'get_gd_version' ) ) {
	/**
	 * Get the installed GD library version.
	 *
	 * @return string GD version, or a localized "N/A" when undetectable.
	 */
	function get_gd_version() {
		/*
		 * There is no fallback here on purpose. gd_info() is defined whenever
		 * the GD extension is loaded, so if it is missing there is no GD and
		 * no version to report. The old code answered that case by buffering
		 * phpinfo() and scraping the result -- which could not succeed for the
		 * same reason, and dumped the entire PHP configuration into an output
		 * buffer to do it. 2.0.0 removed phpinfo() scraping from the PHP tab
		 * for exactly that reason; this call site was missed.
		 */
		if ( ! function_exists( 'gd_info' ) ) {
			return __( 'N/A', 'wp-serverinfo' );
		}

		$gd = gd_info();

		if ( empty( $gd['GD Version'] ) ) {
			return __( 'N/A', 'wp-serverinfo' );
		}

		return $gd['GD Version'];
	}
}


if ( ! function_exists( 'get_serverload' ) ) {
	/**
	 * Get the current server load average (Unix-like hosts only).
	 *
	 * @return string 1-minute load average, or a localized "N/A".
	 */
	function get_serverload() {
		$server_load = '';

		// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions, WordPress.PHP.DiscouragedPHPFunctions -- probing the host for load average; @-suppression and low-level/shell calls are intentional and gated behind availability and disable_functions checks.
		if ( PHP_OS !== 'WINNT' && PHP_OS !== 'WIN32' ) {
			$disabled_functions = array_map( 'trim', explode( ',', ini_get( 'disable_functions' ) ) );

			/*
			 * Ordered cascade, cheapest and safest first. Each step runs only
			 * if everything before it came back empty.
			 *
			 * sys_getloadavg() is the native answer and needs neither a
			 * readable /proc nor a shell, so it goes first -- on most hosts
			 * the shell fallbacks below are now never reached at all. They
			 * stay because shared hosts do disable it, but they are last
			 * resort, not the main path.
			 *
			 * The old code chained these with if/else on whether
			 * /proc/loadavg *existed*, so a host where the file was present
			 * but unreadable fell through to no fallback at all and simply
			 * reported "N/A".
			 *
			 * The system() branch it tried first is gone. system() writes the
			 * command's output straight to the response instead of returning
			 * it, so on any host where it was enabled the raw uptime line was
			 * being printed into the middle of the General table -- exec()
			 * captures the same string without emitting anything.
			 */
			if ( function_exists( 'sys_getloadavg' ) && ! in_array( 'sys_getloadavg', $disabled_functions, true ) ) {
				$load_avg = sys_getloadavg();
				if ( is_array( $load_avg ) && isset( $load_avg[0] ) ) {
					$server_load = number_format( (float) $load_avg[0], 2, '.', '' );
				}
			}

			if ( '' === $server_load && file_exists( '/proc/loadavg' ) ) {
				$fh = @fopen( '/proc/loadavg', 'r' );
				if ( $fh ) {
					$data = @fread( $fh, 6 );
					@fclose( $fh );
					if ( is_string( $data ) && '' !== $data ) {
						$load_avg    = explode( ' ', $data );
						$server_load = trim( $load_avg[0] );
					}
				}
			}

			if ( '' === $server_load && function_exists( 'exec' ) && ! in_array( 'exec', $disabled_functions, true ) ) {
				$data = @exec( 'uptime 2>&1', $output, $return_var );
				if ( 0 === $return_var && ! empty( $data ) ) {
					preg_match( '/load average[s]?:\s*([0-9\.]+)/', $data, $matches );
					if ( isset( $matches[1] ) ) {
						$server_load = $matches[1];
					}
				}
			}

			if ( '' === $server_load && function_exists( 'shell_exec' ) && ! in_array( 'shell_exec', $disabled_functions, true ) ) {
				$data = @shell_exec( 'uptime 2>&1' );
				if ( ! empty( $data ) ) {
					preg_match( '/load average[s]?:\s*([0-9\.]+)/', $data, $matches );
					if ( isset( $matches[1] ) ) {
						$server_load = $matches[1];
					}
				}
			}
		}
		// phpcs:enable WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions, WordPress.PHP.DiscouragedPHPFunctions
		if ( empty( $server_load ) ) {
			$server_load = __( 'N/A', 'wp-serverinfo' );
		}
		return $server_load;
	}
}


add_action( 'wp_dashboard_setup', 'serverinfo_register_dashboard_widget' );
/**
 * Register the Server Information dashboard widget for administrators.
 *
 * @return void
 */
function serverinfo_register_dashboard_widget() {
	if ( current_user_can( 'manage_options' ) ) {
		wp_add_dashboard_widget( 'dashboard_serverinfo', __( 'Server Information', 'wp-serverinfo' ), 'wp_dashboard_serverinfo' );
	}
}


/**
 * Render the Server Information dashboard widget contents.
 *
 * @return void
 */
function wp_dashboard_serverinfo() {
	if ( is_rtl() ) {
		echo '<style type="text/css"> #wp-serverinfo ul { padding-left: 15px !important; } </style>';
		echo '<div id="wp-serverinfo" style="direction: ltr; text-align: left;">';
	} else {
		echo '<div id="wp-serverinfo">';
	}
	echo '<p><strong>' . esc_html__( 'General', 'wp-serverinfo' ) . '</strong></p>';
	echo '<ul>';
	echo '<li>' . esc_html__( 'OS', 'wp-serverinfo' ) . ': <strong>' . esc_html( PHP_OS ) . '</strong></li>';
	echo '<li>' . esc_html__( 'Server', 'wp-serverinfo' ) . ': <strong>' . esc_html( sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ?? '' ) ) ) . '</strong></li>';
	echo '<li>' . esc_html__( 'Hostname', 'wp-serverinfo' ) . ': <strong>' . esc_html( sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ?? '' ) ) ) . '</strong></li>';
	// IIS populates LOCAL_ADDR rather than SERVER_ADDR. The General tab already
	// branched on $is_IIS; this widget did not, so the whole row rendered as
	// just ":80" on IIS.
	echo '<li>' . esc_html__( 'IP:Port', 'wp-serverinfo' ) . ': <strong>' . esc_html( serverinfo_get_server_address() ) . ':' . esc_html( sanitize_text_field( wp_unslash( $_SERVER['SERVER_PORT'] ?? '' ) ) ) . '</strong></li>';
	echo '<li>' . esc_html__( 'Document Root', 'wp-serverinfo' ) . ': <strong>' . esc_html( sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ?? '' ) ) ) . '</strong></li>';
	echo '</ul>';
	echo '<p><strong>PHP</strong></p>';
	echo '<ul>';
	echo '<li>v<strong>' . esc_html( PHP_VERSION ) . '</strong></li>';
	echo '<li>GD: <strong>' . esc_html( get_gd_version() ) . '</strong></li>';
	echo '<li>' . esc_html__( 'Memory Limit', 'wp-serverinfo' ) . ': <strong>' . esc_html( format_php_size( get_php_memory_limit() ) ) . '</strong></li>';
	echo '<li>' . esc_html__( 'Max Script Execute Time', 'wp-serverinfo' ) . ': <strong>' . esc_html( get_php_max_execution() ) . 's</strong></li>';
	echo '<li>' . esc_html__( 'Max Post Size', 'wp-serverinfo' ) . ': <strong>' . esc_html( format_php_size( get_php_post_max() ) ) . '</strong></li>';
	echo '<li>' . esc_html__( 'Max Upload Size', 'wp-serverinfo' ) . ': <strong>' . esc_html( format_php_size( get_php_upload_max() ) ) . '</strong></li>';
	echo '</ul>';
	echo '<p><strong>MYSQL</strong></p>';
	echo '<ul>';
	echo '<li>v<strong>' . esc_html( get_mysql_version() ) . '</strong></li>';
	echo '<li>' . esc_html__( 'Maximum No. Connections', 'wp-serverinfo' ) . ': <strong>' . esc_html( serverinfo_format_number( get_mysql_max_allowed_connections() ) ) . '</strong></li>';
	echo '<li>' . esc_html__( 'Maximum Packet Size', 'wp-serverinfo' ) . ': <strong>' . esc_html( format_filesize( get_mysql_max_allowed_packet() ) ) . '</strong></li>';
	echo '<li>' . esc_html__( 'Data Disk Usage', 'wp-serverinfo' ) . ': <strong>' . esc_html( format_filesize( get_mysql_data_usage() ) ) . '</strong></li>';
	echo '<li>' . esc_html__( 'Index Disk Usage', 'wp-serverinfo' ) . ': <strong>' . esc_html( format_filesize( get_mysql_index_usage() ) ) . '</strong></li>';
	echo '</ul>';
	if ( serverinfo_has_redis() ) {
		$redisinfo = serverinfo_get_redis_stats();
		if ( $redisinfo ) {
			$hits    = isset( $redisinfo['keyspace_hits'] ) ? (int) $redisinfo['keyspace_hits'] : 0;
			$misses  = isset( $redisinfo['keyspace_misses'] ) ? (int) $redisinfo['keyspace_misses'] : 0;
			$lookups = $hits + $misses;
			$hit_pct = $lookups > 0 ? round( ( $hits / $lookups ) * 100, 2 ) : 0;
			echo '<p><strong>Redis</strong></p>';
			echo '<ul>';
			echo '<li>v<strong>' . esc_html( $redisinfo['redis_version'] ?? '' ) . '</strong></li>';
			echo '<li>' . esc_html__( 'Uptime', 'wp-serverinfo' ) . ': <strong>' . esc_html( number_format_i18n( $redisinfo['uptime_in_days'] ?? 0 ) ) . ' ' . esc_html__( 'days', 'wp-serverinfo' ) . '</strong></li>';
			echo '<li>' . esc_html__( 'Used Memory', 'wp-serverinfo' ) . ': <strong>' . esc_html( $redisinfo['used_memory_human'] ?? format_filesize( $redisinfo['used_memory'] ?? 0 ) ) . '</strong></li>';
			echo '<li>' . esc_html__( 'Connected Clients', 'wp-serverinfo' ) . ': <strong>' . esc_html( number_format_i18n( $redisinfo['connected_clients'] ?? 0 ) ) . '</strong></li>';
			echo '<li>' . esc_html__( 'Hit Ratio', 'wp-serverinfo' ) . ': <strong>' . esc_html( $hit_pct ) . '%</strong></li>';
			echo '</ul>';
		}
	}
	echo '<p class="textright"><a href="' . esc_url( admin_url( 'index.php?page=wp-serverinfo' ) ) . '" class="button">' . esc_html__( 'View all', 'wp-serverinfo' ) . '</a></p>';
	echo '</div>';
}
