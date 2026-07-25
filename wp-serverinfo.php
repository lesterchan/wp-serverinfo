<?php
/*
Plugin Name: WP-ServerInfo
Plugin URI: https://lesterchan.net/portfolio/programming/php/
Description: Display your host's PHP, MYSQL & memcached (if installed) information on your WordPress dashboard.
Version: 1.66.1
Author: Lester 'GaMerZ' Chan
Author URI: https://lesterchan.net
Text Domain: wp-serverinfo
Requires at least: 4.0
Requires PHP: 7.2
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


// Prevent Direct Access
defined( 'ABSPATH' ) || exit;

// Version
define( 'WP_SERVERINFO_VERSION', '1.66.1' );

// Create Text Domain For Translations
add_action( 'init', 'serverinfo_textdomain' );
function serverinfo_textdomain() {
	load_plugin_textdomain( 'wp-serverinfo' );
}


// Function: WP-ServerInfo Menu
add_action( 'admin_menu', 'serverinfo_menu' );
function serverinfo_menu() {
	if ( function_exists( 'add_submenu_page' ) ) {
		add_submenu_page( 'index.php', __( 'WP-ServerInfo', 'wp-serverinfo' ), __( 'WP-ServerInfo', 'wp-serverinfo' ), 'manage_options', 'wp-serverinfo', 'display_serverinfo' );
	}
}


// Function: Enqueue ServerInfo JavaScript In WP-Admin
add_action( 'admin_enqueue_scripts', 'serverinfo_scripts_admin' );
function serverinfo_scripts_admin( $hook_suffix ) {
	$serverinfo_admin_pages = array( 'dashboard_page_wp-serverinfo' );
	if ( in_array( $hook_suffix, $serverinfo_admin_pages, true ) ) {
		$script = <<<'JS'
function serverinfo_toggle(id) {
	var sections = ['GeneralOverview', 'PHPinfo', 'MYSQLinfo', 'memcachedinfo'];
	for (var i = 0; i < sections.length; i++) {
		var el = document.getElementById(sections[i]);
		if (el) {
			el.style.display = (sections[i] === id) ? '' : 'none';
		}
	}
}
document.addEventListener('DOMContentLoaded', function () {
	document.addEventListener('click', function (e) {
		var link = e.target.closest('.serverinfo-nav');
		if (link) {
			e.preventDefault();
			serverinfo_toggle(link.getAttribute('data-target'));
		}
	});
});
JS;
		wp_register_script( 'wp-serverinfo', false, array(), WP_SERVERINFO_VERSION, true );
		wp_enqueue_script( 'wp-serverinfo' );
		wp_add_inline_script( 'wp-serverinfo', $script );
	}
}


// Display WP-ServerInfo Admin Page
function display_serverinfo() {
	echo '<style type="text/css">#GeneralOverview .widefat tbody tr:hover td, #PHPinfo .widefat tbody tr:hover td, #MYSQLinfo .widefat tbody tr:hover td, #memcachedinfo .widefat tbody tr:hover td { background-color: #f6f7f7; }</style>' . "\n";
	get_generalinfo();
	get_phpinfo();
	get_mysqlinfo();
	get_memcachedinfo();
}


// Get General Information
function get_generalinfo() {
	global $is_IIS;
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
	<div class="wrap" id="GeneralOverview">
		<h2><?php esc_html_e( 'General Overview', 'wp-serverinfo' ); ?></h2>
		<?php serverinfo_subnavi(); ?>
		<br class="clear" />
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
					<td><?php echo esc_html( number_format_i18n( get_mysql_max_allowed_connections() ) ); ?></td>
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
					<td><?php echo esc_html( sanitize_text_field( wp_unslash( $is_IIS ? ( $_SERVER['LOCAL_ADDR'] ?? '' ) : ( $_SERVER['SERVER_ADDR'] ?? '' ) ) ) ); ?>:<?php echo esc_html( sanitize_text_field( wp_unslash( $_SERVER['SERVER_PORT'] ?? '' ) ) ); ?></td>
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


// Get PHP Information
function get_phpinfo() {
	echo '<div class="wrap" id="PHPinfo" style="display: none;">' . "\n";
	echo '<h2>PHP ' . esc_html( phpversion() ) . '</h2>' . "\n";
	serverinfo_subnavi();

	// Summary built from structured PHP data (no phpinfo() HTML scraping)
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

	// Configuration directives from ini_get_all()
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


// Get MYSQL Information
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
	echo '<div class="wrap" id="MYSQLinfo" style="display: none;">' . "\n";
	echo '<h2>MYSQL ' . esc_html( $sqlversion ) . "</h2>\n";
	serverinfo_subnavi();
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


// Function: Whether A memcached PHP Extension Is Available
if ( ! function_exists( 'serverinfo_has_memcached' ) ) {
	function serverinfo_has_memcached() {
		return class_exists( 'Memcached' ) || class_exists( 'Memcache' );
	}
}


// Function: Get memcached Server Statistics (Prefers Memcached, Falls Back To Memcache)
if ( ! function_exists( 'serverinfo_get_memcached_stats' ) ) {
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


// Get memcached Information (Description from https://boxpanel.blueboxgrp.com/public/the_vault/index.php/memcached_Tips)
function get_memcachedinfo() {
	echo '<div class="wrap" id="memcachedinfo" style="display: none;">' . "\n";
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
		serverinfo_subnavi();
		if ( $memcachedinfo ) {
			$cache_hit  = ( $memcachedinfo['cmd_get'] > 0 ? ( ( $memcachedinfo['get_hits'] / $memcachedinfo['cmd_get'] ) * 100 ) : 0 );
			$cache_hit  = round( $cache_hit, 2 );
			$cache_miss = 100 - $cache_hit;

			$usage  = ( $memcachedinfo['limit_maxbytes'] > 0 ) ? round( ( ( $memcachedinfo['bytes'] / $memcachedinfo['limit_maxbytes'] ) * 100 ), 2 ) : 0;
			$uptime = number_format_i18n( ( $memcachedinfo['uptime'] / 60 / 60 / 24 ) );

			echo '<br class="clear" />' . "\n";
			echo '<table class="widefat" dir="ltr">' . "\n";
			echo '<thead><tr><th>' . esc_html__( 'Variable Name', 'wp-serverinfo' ) . '</th><th>' . esc_html__( 'Value', 'wp-serverinfo' ) . '</th><th>' . esc_html__( 'Description', 'wp-serverinfo' ) . '</th></tr></thead><tbody>' . "\n";

			// Each row: variable name, pre-formatted value, description. All three cells are escaped uniformly below.
			$memcached_rows = array(
				array( 'pid', $memcachedinfo['pid'], __( 'Process ID', 'wp-serverinfo' ) ),
				array( 'uptime', $uptime, __( 'Number of days since the process was started', 'wp-serverinfo' ) ),
				array( 'version', $memcachedinfo['version'], __( 'memcached version', 'wp-serverinfo' ) ),
				array( 'rusage_user', $memcachedinfo['rusage_user'], __( 'Seconds the cpu has devoted to the process as the user', 'wp-serverinfo' ) ),
				array( 'rusage_system', $memcachedinfo['rusage_system'], __( 'Seconds the cpu has devoted to the process as the system', 'wp-serverinfo' ) ),
				array( 'curr_items', number_format_i18n( $memcachedinfo['curr_items'] ), __( 'Total number of items currently in memcached', 'wp-serverinfo' ) ),
				array( 'total_items', number_format_i18n( $memcachedinfo['total_items'] ), __( 'Total number of items that have passed through memcached', 'wp-serverinfo' ) ),
				array( 'bytes', format_filesize( $memcachedinfo['bytes'] ) . ' (' . $usage . '%)', __( 'Memory size currently used by curr_items', 'wp-serverinfo' ) ),
				array( 'limit_maxbytes', format_filesize( $memcachedinfo['limit_maxbytes'] ), __( 'Maximum memory size allocated to memcached', 'wp-serverinfo' ) ),
				array( 'curr_connections', number_format_i18n( $memcachedinfo['curr_connections'] ), __( 'Total number of open connections to memcached', 'wp-serverinfo' ) ),
				array( 'total_connections', number_format_i18n( $memcachedinfo['total_connections'] ), __( 'Total number of connections opened since memcached started running', 'wp-serverinfo' ) ),
				array( 'connection_structures', number_format_i18n( $memcachedinfo['connection_structures'] ), __( 'Number of connection structures allocated by the server', 'wp-serverinfo' ) ),
				array( 'cmd_get', number_format_i18n( $memcachedinfo['cmd_get'] ), __( 'Total GET commands issued to the server', 'wp-serverinfo' ) ),
				array( 'cmd_set', number_format_i18n( $memcachedinfo['cmd_set'] ), __( 'Total SET commands issued to the server', 'wp-serverinfo' ) ),
				array( 'cmd_flush', number_format_i18n( $memcachedinfo['cmd_flush'] ), __( 'Total FLUSH commands issued to the server', 'wp-serverinfo' ) ),
				array( 'get_hits', number_format_i18n( $memcachedinfo['get_hits'] ) . ' (' . $cache_hit . '%)', __( 'Total number of times a GET command was able to retrieve and return data', 'wp-serverinfo' ) ),
				array( 'get_misses', number_format_i18n( $memcachedinfo['get_misses'] ) . ' (' . $cache_miss . '%)', __( 'Total number of times a GET command was unable to retrieve and return data', 'wp-serverinfo' ) ),
				array( 'delete_hits', number_format_i18n( $memcachedinfo['delete_hits'] ), __( 'Total number of times a DELETE command was able to delete data', 'wp-serverinfo' ) ),
				array( 'delete_misses', number_format_i18n( $memcachedinfo['delete_misses'] ), __( 'Total number of times a DELETE command was unable to delete data', 'wp-serverinfo' ) ),
				array( 'incr_hits', number_format_i18n( $memcachedinfo['incr_hits'] ), __( 'Total number of times a INCR command was able to increment a value', 'wp-serverinfo' ) ),
				array( 'incr_misses', number_format_i18n( $memcachedinfo['incr_misses'] ), __( 'Total number of times a INCR command was unable to increment a value', 'wp-serverinfo' ) ),
				array( 'decr_hits', number_format_i18n( $memcachedinfo['decr_hits'] ), __( 'Total number of times a DECR command was able to decrement a value', 'wp-serverinfo' ) ),
				array( 'decr_misses', number_format_i18n( $memcachedinfo['decr_misses'] ), __( 'Total number of times a DECR command was unable to decrement a value', 'wp-serverinfo' ) ),
				array( 'cas_hits', number_format_i18n( $memcachedinfo['cas_hits'] ), __( 'Total number of times a CAS command was able to compare and swap data', 'wp-serverinfo' ) ),
				array( 'cas_misses', number_format_i18n( $memcachedinfo['cas_misses'] ), __( 'Total number of times a CAS command was unable to compare and swap data', 'wp-serverinfo' ) ),
				array( 'cas_badval', number_format_i18n( $memcachedinfo['cas_badval'] ), __( 'N/A', 'wp-serverinfo' ) ),
				array( 'bytes_read', format_filesize( $memcachedinfo['bytes_read'] ), __( 'Total number of bytes input into the server', 'wp-serverinfo' ) ),
				array( 'bytes_written', format_filesize( $memcachedinfo['bytes_written'] ), __( 'Total number of bytes written by the server', 'wp-serverinfo' ) ),
				array( 'evictions', number_format_i18n( $memcachedinfo['evictions'] ), __( 'Number of valid items removed from cache to free memory for new items', 'wp-serverinfo' ) ),
				array( 'reclaimed', number_format_i18n( $memcachedinfo['reclaimed'] ), __( 'Number of items reclaimed', 'wp-serverinfo' ) ),
			);
			foreach ( $memcached_rows as $memcached_row ) {
				echo '<tr><td>' . esc_html( $memcached_row[0] ) . '</td><td>' . esc_html( $memcached_row[1] ) . '</td><td>' . esc_html( $memcached_row[2] ) . '</td></tr>' . "\n";
			}
			echo '</tbody></table>' . "\n";
		}
	}
	echo '</div>' . "\n";
}


// WP-Server Sub Navigation
function serverinfo_subnavi( $display = true ) {
	$output  = '<p style="text-align: center">';
	$output .= '<a href="#GeneralOverview" class="serverinfo-nav" data-target="GeneralOverview">' . esc_html__( 'Display General Overview', 'wp-serverinfo' ) . '</a>';
	$output .= ' - <a href="#PHPinfo" class="serverinfo-nav" data-target="PHPinfo">' . esc_html__( 'Display PHP Information', 'wp-serverinfo' ) . '</a>';
	$output .= ' - <a href="#MYSQLinfo" class="serverinfo-nav" data-target="MYSQLinfo">' . esc_html__( 'Display MYSQL Information', 'wp-serverinfo' ) . '</a>';
	if ( serverinfo_has_memcached() ) {
		$output .= ' - <a href="#memcachedinfo" class="serverinfo-nav" data-target="memcachedinfo">' . esc_html__( 'Display memcached Information', 'wp-serverinfo' ) . '</a>';
	}
	$output .= '</p>';
	if ( $display ) {
		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup; dynamic labels escaped via esc_html__() above.
	} else {
		return $output;
	}
}


// Function: Format Bytes Into TiB/GiB/MiB/KiB/Bytes
if ( ! function_exists( 'format_filesize' ) ) {
	function format_filesize( $raw_size ) {
		if ( $raw_size / 1099511627776 > 1 ) {
			return number_format_i18n( $raw_size / 1099511627776, 1 ) . ' ' . __( 'TiB', 'wp-serverinfo' );
		} elseif ( $raw_size / 1073741824 > 1 ) {
			return number_format_i18n( $raw_size / 1073741824, 1 ) . ' ' . __( 'GiB', 'wp-serverinfo' );
		} elseif ( $raw_size / 1048576 > 1 ) {
			return number_format_i18n( $raw_size / 1048576, 1 ) . ' ' . __( 'MiB', 'wp-serverinfo' );
		} elseif ( $raw_size / 1024 > 1 ) {
			return number_format_i18n( $raw_size / 1024, 1 ) . ' ' . __( 'KiB', 'wp-serverinfo' );
		} elseif ( $raw_size > 1 ) {
			return number_format_i18n( $raw_size, 0 ) . ' ' . __( 'bytes', 'wp-serverinfo' );
		} else {
			return __( 'unknown', 'wp-serverinfo' );
		}
	}
}

// Function: Convert PHP Size Format to Localized
function format_php_size( $size ) {
	if ( ! is_numeric( $size ) ) {
		if ( strpos( $size, 'M' ) !== false ) {
			$size = intval( $size ) * 1024 * 1024;
		} elseif ( strpos( $size, 'K' ) !== false ) {
			$size = intval( $size ) * 1024;
		} elseif ( strpos( $size, 'G' ) !== false ) {
			$size = intval( $size ) * 1024 * 1024 * 1024;
		}
	}
	return is_numeric( $size ) ? format_filesize( $size ) : $size;
}

// Function: Get PHP Short Tag
if ( ! function_exists( 'get_php_short_tag' ) ) {
	function get_php_short_tag() {
		if ( ini_get( 'short_open_tag' ) ) {
			$short_tag = __( 'On', 'wp-serverinfo' );
		} else {
			$short_tag = __( 'Off', 'wp-serverinfo' );
		}
		return $short_tag;
	}
}


// Function: Get PHP Max Upload Size
if ( ! function_exists( 'get_php_upload_max' ) ) {
	function get_php_upload_max() {
		if ( ini_get( 'upload_max_filesize' ) ) {
			$upload_max = ini_get( 'upload_max_filesize' );
		} else {
			$upload_max = __( 'N/A', 'wp-serverinfo' );
		}
		return $upload_max;
	}
}


// Function: Get PHP Max Post Size
if ( ! function_exists( 'get_php_post_max' ) ) {
	function get_php_post_max() {
		if ( ini_get( 'post_max_size' ) ) {
			$post_max = ini_get( 'post_max_size' );
		} else {
			$post_max = __( 'N/A', 'wp-serverinfo' );
		}
		return $post_max;
	}
}


// Function: PHP Maximum Execution Time
if ( ! function_exists( 'get_php_max_execution' ) ) {
	function get_php_max_execution() {
		if ( ini_get( 'max_execution_time' ) ) {
			$max_execute = ini_get( 'max_execution_time' );
		} else {
			$max_execute = __( 'N/A', 'wp-serverinfo' );
		}
		return $max_execute;
	}
}


// Function: PHP Memory Limit
if ( ! function_exists( 'get_php_memory_limit' ) ) {
	function get_php_memory_limit() {
		if ( ini_get( 'memory_limit' ) ) {
			$memory_limit = ini_get( 'memory_limit' );
		} else {
			$memory_limit = __( 'N/A', 'wp-serverinfo' );
		}
		return $memory_limit;
	}
}


// Function: Get MYSQL Version
if ( ! function_exists( 'get_mysql_version' ) ) {
	function get_mysql_version() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live server introspection; results must not be cached.
		return $wpdb->get_var( 'SELECT VERSION() AS version' );
	}
}


// Function: Get MYSQL Table Status (Cached Per Request)
if ( ! function_exists( 'serverinfo_get_table_status' ) ) {
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


// Function: Get MYSQL Data Usage
if ( ! function_exists( 'get_mysql_data_usage' ) ) {
	function get_mysql_data_usage() {
		$data_usage = 0;
		foreach ( serverinfo_get_table_status() as $tablestatus ) {
			$data_usage += $tablestatus->Data_length; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL column name from SHOW TABLE STATUS.
		}

		return $data_usage;
	}
}


// Function: Get MYSQL Index Usage
if ( ! function_exists( 'get_mysql_index_usage' ) ) {
	function get_mysql_index_usage() {
		$index_usage = 0;
		foreach ( serverinfo_get_table_status() as $tablestatus ) {
			$index_usage += $tablestatus->Index_length; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL column name from SHOW TABLE STATUS.
		}

		return $index_usage;
	}
}


// Function: Get MYSQL Max Allowed Packet
if ( ! function_exists( 'get_mysql_max_allowed_packet' ) ) {
	function get_mysql_max_allowed_packet() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live server introspection; results must not be cached.
		$packet_max_query = $wpdb->get_row( "SHOW VARIABLES LIKE 'max_allowed_packet'" );

		return $packet_max_query->Value; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL column name from SHOW VARIABLES.
	}
}


// Function:Get MYSQL Max Allowed Connections
if ( ! function_exists( 'get_mysql_max_allowed_connections' ) ) {
	function get_mysql_max_allowed_connections() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live server introspection; results must not be cached.
		$connection_max_query = $wpdb->get_row( "SHOW VARIABLES LIKE 'max_connections'" );

		return $connection_max_query->Value; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL column name from SHOW VARIABLES.
	}
}

// Function:Get MYSQL Query Cache Size
if ( ! function_exists( 'get_mysql_query_cache_size' ) ) {
	function get_mysql_query_cache_size() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live server introspection; results must not be cached.
		$query_cache_size_query = $wpdb->get_row( "SHOW VARIABLES LIKE 'query_cache_size'" );

		return $query_cache_size_query ? $query_cache_size_query->Value : 0; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL column name from SHOW VARIABLES.
	}
}


// Function: Get GD Version
if ( ! function_exists( 'get_gd_version' ) ) {
	function get_gd_version() {
		if ( function_exists( 'gd_info' ) ) {
			$gd = gd_info();
			$gd = $gd['GD Version'];
		} else {
			// phpcs:disable WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_phpinfo, WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- last-resort GD version probe when gd_info() is unavailable; output is parsed, not displayed.
			ob_start();
			phpinfo( 8 );
			$phpinfo = ob_get_contents();
			ob_end_clean();
			$phpinfo = strip_tags( $phpinfo );
			$phpinfo = stristr( $phpinfo, 'gd version' );
			$phpinfo = stristr( $phpinfo, 'version' );
			$gd      = substr( $phpinfo, 0, strpos( $phpinfo, "\n" ) );
			// phpcs:enable WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_phpinfo, WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
		}
		if ( empty( $gd ) ) {
			$gd = __( 'N/A', 'wp-serverinfo' );
		}
		return $gd;
	}
}


// Function: Get The Server Load
if ( ! function_exists( 'get_serverload' ) ) {
	function get_serverload() {
		$server_load = '';
		// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions, WordPress.PHP.DiscouragedPHPFunctions -- probing the host for load average; @-suppression and low-level/shell calls are intentional and gated behind availability and disable_functions checks.
		if ( PHP_OS !== 'WINNT' && PHP_OS !== 'WIN32' ) {
			if ( @file_exists( '/proc/loadavg' ) ) {
				$fh = @fopen( '/proc/loadavg', 'r' );
				if ( $fh ) {
					$data = @fread( $fh, 6 );
					@fclose( $fh );
					$load_avg    = explode( ' ', $data );
					$server_load = trim( $load_avg[0] );
				}
			} else {
				$disabled_functions = array_map( 'trim', explode( ',', ini_get( 'disable_functions' ) ) );
				if ( function_exists( 'system' ) && ! in_array( 'system', $disabled_functions, true ) ) {
					$data = @system( 'uptime' );
					if ( false !== $data ) {
						preg_match( '/(.*):{1}(.*)/', $data, $matches );
						if ( isset( $matches[2] ) ) {
							$load_arr    = explode( ',', $matches[2] );
							$server_load = trim( $load_arr[0] );
						}
					}
				} elseif ( function_exists( 'exec' ) && ! in_array( 'exec', $disabled_functions, true ) ) {
					$data = @exec( 'uptime 2>&1', $output, $return_var );
					if ( 0 === $return_var && ! empty( $data ) ) {
						preg_match( '/load average[s]?:\s*([0-9\.]+)/', $data, $matches );
						if ( isset( $matches[1] ) ) {
							$server_load = $matches[1];
						}
					}
				} elseif ( function_exists( 'shell_exec' ) && ! in_array( 'shell_exec', $disabled_functions, true ) ) {
					$data = @shell_exec( 'uptime 2>&1' );
					if ( ! empty( $data ) ) {
						preg_match( '/load average[s]?:\s*([0-9\.]+)/', $data, $matches );
						if ( isset( $matches[1] ) ) {
							$server_load = $matches[1];
						}
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


// Function: Register ServerInfo Dashboard Widget
add_action( 'wp_dashboard_setup', 'serverinfo_register_dashboard_widget' );
function serverinfo_register_dashboard_widget() {
	if ( current_user_can( 'manage_options' ) ) {
		wp_add_dashboard_widget( 'dashboard_serverinfo', __( 'Server Information', 'wp-serverinfo' ), 'wp_dashboard_serverinfo' );
	}
}


// Function: Print ServerInfo Dashboard Widget
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
	echo '<li>' . esc_html__( 'IP:Port', 'wp-serverinfo' ) . ': <strong>' . esc_html( sanitize_text_field( wp_unslash( $_SERVER['SERVER_ADDR'] ?? '' ) ) ) . ':' . esc_html( sanitize_text_field( wp_unslash( $_SERVER['SERVER_PORT'] ?? '' ) ) ) . '</strong></li>';
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
	echo '<li>' . esc_html__( 'Maximum No. Connections', 'wp-serverinfo' ) . ': <strong>' . esc_html( number_format_i18n( get_mysql_max_allowed_connections(), 0 ) ) . '</strong></li>';
	echo '<li>' . esc_html__( 'Maximum Packet Size', 'wp-serverinfo' ) . ': <strong>' . esc_html( format_filesize( get_mysql_max_allowed_packet() ) ) . '</strong></li>';
	echo '<li>' . esc_html__( 'Data Disk Usage', 'wp-serverinfo' ) . ': <strong>' . esc_html( format_filesize( get_mysql_data_usage() ) ) . '</strong></li>';
	echo '<li>' . esc_html__( 'Index Disk Usage', 'wp-serverinfo' ) . ': <strong>' . esc_html( format_filesize( get_mysql_index_usage() ) ) . '</strong></li>';
	echo '</ul>';
	echo '<p class="textright"><a href="' . esc_url( admin_url( 'index.php?page=wp-serverinfo' ) ) . '" class="button">' . esc_html__( 'View all', 'wp-serverinfo' ) . '</a></p>';
	echo '</div>';
}
