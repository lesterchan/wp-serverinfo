<?php
/**
 * The WP-ServerInfo report screen.
 *
 * @package WP-ServerInfo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the menu entry and renders the tabbed report under Tools.
 *
 * One entry, and it is under Tools rather than being a top-level menu of its
 * own or a page under Settings. Section 4.1 wants one menu entry and no
 * scattering, which the plugin already satisfied -- the widget and the report
 * have always pointed at each other -- and the choice between the three ways of
 * spending that entry follows from what the screen is. There is no settings
 * form on it and nothing to save, so add_options_page() would file a report
 * under Settings; there are no list tables and nothing to manage, so a
 * top-level menu would claim a slot in the sidebar for five read-only tables.
 * add_management_page() puts it where WordPress already keeps read-only
 * screens about the installation, next to Site Health and Export.
 *
 * Before 3.0.0 it was a submenu of index.php, which put a report about the host
 * inside the Dashboard menu and gave it a URL nobody could guess.
 *
 * Replaces the global display_serverinfo() / get_*info() functions from
 * before 3.0.0.
 */
class WP_ServerInfo_Admin {

	/**
	 * The report page slug. Unchanged from 2.0.0; only the parent moved.
	 */
	const PAGE = 'wp-serverinfo';

	/**
	 * The capability the report screen requires.
	 *
	 * The page reports the document root, the server IP and every PHP and MySQL
	 * directive, so it is manage_options and not something weaker. WP-ServerInfo
	 * has never shipped a capability of its own.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * The capability a screen requires, filtered.
	 *
	 * Every capability check in the plugin goes through here, so a site that
	 * wants to hand the report to somebody other than an administrator changes
	 * one thing rather than hunting for current_user_can() calls.
	 *
	 * @since 3.0.0
	 *
	 * @param string $context Which surface is asking: 'report' or 'widget'.
	 * @return string
	 */
	public static function capability( $context = 'report' ) {
		/**
		 * Filters the capability a WP-ServerInfo surface requires.
		 *
		 * @since 3.0.0
		 *
		 * @param string $capability Capability name.
		 * @param string $context    Which surface is asking: 'report' or 'widget'.
		 */
		return (string) apply_filters( 'wp_serverinfo_capability', self::CAPABILITY, $context );
	}

	/**
	 * The report page's own URL.
	 *
	 * @param string $tab Tab slug to link to, or '' for the default tab.
	 * @return string
	 */
	public static function url( $tab = '' ) {
		$args = array( 'page' => self::PAGE );

		if ( '' !== $tab ) {
			$args['tab'] = $tab;
		}

		return add_query_arg( $args, admin_url( 'tools.php' ) );
	}

	/**
	 * Register the report under the Tools menu.
	 *
	 * @return void
	 */
	public static function add_page() {
		add_management_page(
			__( 'WP-ServerInfo', 'wp-serverinfo' ),
			__( 'WP-ServerInfo', 'wp-serverinfo' ),
			self::capability( 'report' ),
			self::PAGE,
			array( self::class, 'render' )
		);
	}

	/**
	 * Build the tab list, hiding panels whose extension is not installed.
	 *
	 * @return array<string,string> Tab slug => label.
	 */
	private static function tabs() {
		$tabs = array(
			'general' => __( 'General', 'wp-serverinfo' ),
			'php'     => __( 'PHP', 'wp-serverinfo' ),
			'mysql'   => __( 'MySQL', 'wp-serverinfo' ),
		);

		if ( WP_ServerInfo_Cache::has_memcached() ) {
			$tabs['memcached'] = __( 'memcached', 'wp-serverinfo' );
		}

		if ( WP_ServerInfo_Cache::has_redis() ) {
			$tabs['redis'] = __( 'Redis', 'wp-serverinfo' );
		}

		return $tabs;
	}

	/**
	 * Render the admin page.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( self::capability( 'report' ) ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-serverinfo' ) );
		}

		$tabs = self::tabs();

		// Read-only tab selection driven by the URL; no state change, so no nonce is needed.
		$active = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation, no state change, so there is no nonce to verify.

		if ( ! isset( $tabs[ $active ] ) ) {
			$active = 'general';
		}

		echo '<div class="wrap">' . "\n";
		echo '<h1>' . esc_html__( 'Server Information', 'wp-serverinfo' ) . '</h1>' . "\n";

		printf( '<nav class="nav-tab-wrapper" aria-label="%s">', esc_attr__( 'Server information sections', 'wp-serverinfo' ) );
		foreach ( $tabs as $tab => $label ) {
			printf(
				'<a href="%s" class="nav-tab%s">%s</a>',
				esc_url( self::url( $tab ) ),
				$active === $tab ? ' nav-tab-active' : '',
				esc_html( $label )
			);
		}
		echo '</nav>' . "\n";

		switch ( $active ) {
			case 'php':
				self::render_php();
				break;
			case 'mysql':
				self::render_mysql();
				break;
			case 'memcached':
				self::render_memcached();
				break;
			case 'redis':
				self::render_redis();
				break;
			default:
				self::render_general();
				break;
		}

		echo '</div>' . "\n";
	}

	/**
	 * Open a panel wrapper.
	 *
	 * The wrapper carries dir="ltr" rather than a stylesheet. Every value in
	 * these tables -- paths, versions, IP addresses, ini directive names -- is
	 * latin text whatever the admin language is, so the panels have to read left
	 * to right even on an RTL install. Before 3.0.0 that was a <style> block
	 * written into the page, with a physical text-align and an !important; the
	 * attribute says the same thing to the browser, needs no stylesheet to ship
	 * and to enqueue, and is what section 5.1 means by writing direction-neutral
	 * CSS instead of mirroring it.
	 *
	 * @param string $id The panel's DOM id, unchanged since 2.0.0 so that anyone
	 *                   styling these screens still finds them.
	 * @return void
	 */
	private static function open_panel( $id ) {
		echo '<div id="' . esc_attr( $id ) . '" class="serverinfo-panel" dir="ltr">' . "\n";
	}

	/**
	 * Render one table row of two label/value pairs.
	 *
	 * @param string $left_label  Left label.
	 * @param string $left_value  Left value.
	 * @param string $right_label Right label.
	 * @param string $right_value Right value.
	 * @return void
	 */
	private static function render_pair_row( $left_label, $left_value, $right_label, $right_value ) {
		printf(
			"<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>\n",
			esc_html( $left_label ),
			esc_html( $left_value ),
			esc_html( $right_label ),
			esc_html( $right_value )
		);
	}

	/**
	 * Output the General Overview panel.
	 *
	 * @return void
	 */
	private static function render_general() {
		self::open_panel( 'GeneralOverview' );

		echo '<h2>' . esc_html__( 'General Overview', 'wp-serverinfo' ) . '</h2>' . "\n";
		echo '<table class="widefat striped">' . "\n";
		printf(
			"<thead><tr><th scope=\"col\">%s</th><th scope=\"col\">%s</th><th scope=\"col\">%s</th><th scope=\"col\">%s</th></tr></thead><tbody>\n",
			esc_html__( 'Variable Name', 'wp-serverinfo' ),
			esc_html__( 'Value', 'wp-serverinfo' ),
			esc_html__( 'Variable Name', 'wp-serverinfo' ),
			esc_html__( 'Value', 'wp-serverinfo' )
		);

		$date_time = mysql2date(
			sprintf(
				/* translators: 1: date format, 2: time format */
				__( '%1$s @ %2$s', 'wp-serverinfo' ),
				get_option( 'date_format' ),
				get_option( 'time_format' )
			),
			current_time( 'mysql' )
		);

		$rows = array(
			array(
				__( 'OS', 'wp-serverinfo' ),
				PHP_OS,
				__( 'Database Data Disk Usage', 'wp-serverinfo' ),
				WP_ServerInfo_Format::filesize( WP_ServerInfo_MySQL::data_usage() ),
			),
			array(
				__( 'Server', 'wp-serverinfo' ),
				WP_ServerInfo_PHP::server_value( 'SERVER_SOFTWARE' ),
				__( 'Database Index Disk Usage', 'wp-serverinfo' ),
				WP_ServerInfo_Format::filesize( WP_ServerInfo_MySQL::index_usage() ),
			),
			array(
				'PHP',
				'v' . PHP_VERSION,
				__( 'MYSQL Maximum Packet Size', 'wp-serverinfo' ),
				WP_ServerInfo_Format::filesize( WP_ServerInfo_MySQL::max_allowed_packet() ),
			),
			array(
				'MYSQL',
				'v' . WP_ServerInfo_MySQL::version(),
				__( 'MYSQL Maximum No. Connection', 'wp-serverinfo' ),
				WP_ServerInfo_Format::number( WP_ServerInfo_MySQL::max_connections() ),
			),
			array(
				'GD',
				WP_ServerInfo_PHP::gd_version(),
				__( 'MYSQL Query Cache Size', 'wp-serverinfo' ),
				WP_ServerInfo_Format::filesize( WP_ServerInfo_MySQL::query_cache_size() ),
			),
			array(
				__( 'Server Hostname', 'wp-serverinfo' ),
				WP_ServerInfo_PHP::server_value( 'SERVER_NAME' ),
				__( 'PHP Short Tag', 'wp-serverinfo' ),
				WP_ServerInfo_PHP::short_tag(),
			),
			array(
				__( 'Server IP:Port', 'wp-serverinfo' ),
				WP_ServerInfo_PHP::server_address() . ':' . WP_ServerInfo_PHP::server_value( 'SERVER_PORT' ),
				__( 'PHP Max Script Execute Time', 'wp-serverinfo' ),
				WP_ServerInfo_PHP::max_execution() . 's',
			),
			array(
				__( 'Server Document Root', 'wp-serverinfo' ),
				WP_ServerInfo_PHP::server_value( 'DOCUMENT_ROOT' ),
				__( 'PHP Memory Limit', 'wp-serverinfo' ),
				WP_ServerInfo_Format::php_size( WP_ServerInfo_PHP::memory_limit() ),
			),
			array(
				__( 'Server Date/Time', 'wp-serverinfo' ),
				$date_time,
				__( 'PHP Max Upload Size', 'wp-serverinfo' ),
				WP_ServerInfo_Format::php_size( WP_ServerInfo_PHP::upload_max() ),
			),
			array(
				__( 'Server Load', 'wp-serverinfo' ),
				WP_ServerInfo_PHP::server_load(),
				__( 'PHP Max Post Size', 'wp-serverinfo' ),
				WP_ServerInfo_Format::php_size( WP_ServerInfo_PHP::post_max() ),
			),
		);

		foreach ( $rows as $row ) {
			self::render_pair_row( $row[0], $row[1], $row[2], $row[3] );
		}

		echo '</tbody></table>' . "\n";
		echo '</div>' . "\n";
	}

	/**
	 * Output the PHP Information panel.
	 *
	 * @return void
	 */
	private static function render_php() {
		self::open_panel( 'PHPinfo' );

		echo '<h2>PHP ' . esc_html( phpversion() ) . '</h2>' . "\n";

		echo '<table class="widefat striped"><tbody>' . "\n";
		foreach ( WP_ServerInfo_PHP::summary() as $label => $value ) {
			// scope="row" rather than a bolded cell: the label names the row, and
			// core's table styling already emits it bold.
			printf(
				"<tr><th scope=\"row\">%s</th><td>%s</td></tr>\n",
				esc_html( $label ),
				esc_html( $value )
			);
		}
		echo '</tbody></table>' . "\n";

		$ini = WP_ServerInfo_PHP::ini_directives();

		if ( ! empty( $ini ) ) {
			echo '<table class="widefat striped">' . "\n";
			printf(
				"<thead><tr><th scope=\"col\">%s</th><th scope=\"col\">%s</th><th scope=\"col\">%s</th></tr></thead><tbody>\n",
				esc_html__( 'Directive', 'wp-serverinfo' ),
				esc_html__( 'Local Value', 'wp-serverinfo' ),
				esc_html__( 'Master Value', 'wp-serverinfo' )
			);

			foreach ( $ini as $directive => $values ) {
				printf(
					"<tr><td>%s</td><td>%s</td><td>%s</td></tr>\n",
					esc_html( $directive ),
					esc_html( $values['local_value'] ?? '' ),
					esc_html( $values['global_value'] ?? '' )
				);
			}

			echo '</tbody></table>' . "\n";
		}

		echo '</div>' . "\n";
	}

	/**
	 * Output the MYSQL Information panel.
	 *
	 * @return void
	 */
	private static function render_mysql() {
		self::open_panel( 'MYSQLinfo' );

		echo '<h2>MYSQL ' . esc_html( WP_ServerInfo_MySQL::version() ) . '</h2>' . "\n";

		$variables = WP_ServerInfo_MySQL::variables();

		if ( $variables ) {
			echo '<table class="widefat striped">' . "\n";
			printf(
				"<thead><tr><th scope=\"col\">%s</th><th scope=\"col\">%s</th></tr></thead><tbody>\n",
				esc_html__( 'Variable Name', 'wp-serverinfo' ),
				esc_html__( 'Value', 'wp-serverinfo' )
			);

			foreach ( $variables as $variable ) {
				printf(
					"<tr><td>%s</td><td>%s</td></tr>\n",
					esc_html( $variable['Variable_name'] ?? '' ),
					esc_html( $variable['Value'] ?? '' )
				);
			}

			echo '</tbody></table>' . "\n";
		}

		echo '</div>' . "\n";
	}

	/**
	 * Render a three-column statistics table.
	 *
	 * @param array $rows List of [ name, value, description ] triples.
	 * @return void
	 */
	private static function render_stats_table( array $rows ) {
		echo '<table class="widefat striped">' . "\n";
		printf(
			"<thead><tr><th scope=\"col\">%s</th><th scope=\"col\">%s</th><th scope=\"col\">%s</th></tr></thead><tbody>\n",
			esc_html__( 'Variable Name', 'wp-serverinfo' ),
			esc_html__( 'Value', 'wp-serverinfo' ),
			esc_html__( 'Description', 'wp-serverinfo' )
		);

		foreach ( $rows as $row ) {
			printf(
				"<tr><td>%s</td><td>%s</td><td>%s</td></tr>\n",
				esc_html( $row[0] ),
				esc_html( $row[1] ),
				esc_html( $row[2] )
			);
		}

		echo '</tbody></table>' . "\n";
	}

	/**
	 * Output the memcached Information panel.
	 *
	 * Stat descriptions from https://boxpanel.blueboxgrp.com/public/the_vault/index.php/memcached_Tips
	 *
	 * @return void
	 */
	private static function render_memcached() {
		self::open_panel( 'memcachedinfo' );

		$info = WP_ServerInfo_Cache::memcached_stats();

		echo '<h2>memcached ' . esc_html( is_array( $info ) ? ( $info['version'] ?? '' ) : '' ) . '</h2>' . "\n";

		if ( ! $info ) {
			echo '<p>' . esc_html__( 'Could not connect to the memcached server.', 'wp-serverinfo' ) . '</p>' . "\n";
			echo '</div>' . "\n";
			return;
		}

		/*
		 * Read every stat through this rather than indexing directly. The stat
		 * set is not fixed: it varies by memcached version and by extension
		 * (Memcache exposes a smaller set than Memcached), and several of the
		 * keys below were only added in 1.4.x. Indexing a key the server did
		 * not report warns on PHP 8 and prints the warning into the table.
		 */
		$stat = function ( $key, $fallback = 0 ) use ( $info ) {
			return isset( $info[ $key ] ) ? $info[ $key ] : $fallback;
		};

		$cmd_get    = (float) $stat( 'cmd_get' );
		$cache_hit  = $cmd_get > 0 ? round( ( (float) $stat( 'get_hits' ) / $cmd_get ) * 100, 2 ) : 0;
		$cache_miss = 100 - $cache_hit;

		$max_bytes = (float) $stat( 'limit_maxbytes' );
		$usage     = $max_bytes > 0 ? round( ( (float) $stat( 'bytes' ) / $max_bytes ) * 100, 2 ) : 0;
		$uptime    = number_format_i18n( (float) $stat( 'uptime' ) / 60 / 60 / 24 );

		self::render_stats_table(
			array(
				array( 'pid', $stat( 'pid' ), __( 'Process ID', 'wp-serverinfo' ) ),
				array( 'uptime', $uptime, __( 'Number of days since the process was started', 'wp-serverinfo' ) ),
				array( 'version', $stat( 'version', '' ), __( 'memcached version', 'wp-serverinfo' ) ),
				array( 'rusage_user', $stat( 'rusage_user' ), __( 'Seconds the cpu has devoted to the process as the user', 'wp-serverinfo' ) ),
				array( 'rusage_system', $stat( 'rusage_system' ), __( 'Seconds the cpu has devoted to the process as the system', 'wp-serverinfo' ) ),
				array( 'curr_items', number_format_i18n( $stat( 'curr_items' ) ), __( 'Total number of items currently in memcached', 'wp-serverinfo' ) ),
				array( 'total_items', number_format_i18n( $stat( 'total_items' ) ), __( 'Total number of items that have passed through memcached', 'wp-serverinfo' ) ),
				array( 'bytes', WP_ServerInfo_Format::filesize( $stat( 'bytes' ) ) . ' (' . $usage . '%)', __( 'Memory size currently used by curr_items', 'wp-serverinfo' ) ),
				array( 'limit_maxbytes', WP_ServerInfo_Format::filesize( $stat( 'limit_maxbytes' ) ), __( 'Maximum memory size allocated to memcached', 'wp-serverinfo' ) ),
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
				array( 'bytes_read', WP_ServerInfo_Format::filesize( $stat( 'bytes_read' ) ), __( 'Total number of bytes input into the server', 'wp-serverinfo' ) ),
				array( 'bytes_written', WP_ServerInfo_Format::filesize( $stat( 'bytes_written' ) ), __( 'Total number of bytes written by the server', 'wp-serverinfo' ) ),
				array( 'evictions', number_format_i18n( $stat( 'evictions' ) ), __( 'Number of valid items removed from cache to free memory for new items', 'wp-serverinfo' ) ),
				array( 'reclaimed', number_format_i18n( $stat( 'reclaimed' ) ), __( 'Number of items reclaimed', 'wp-serverinfo' ) ),
			)
		);

		echo '</div>' . "\n";
	}

	/**
	 * Output the Redis Information panel.
	 *
	 * @return void
	 */
	private static function render_redis() {
		self::open_panel( 'Redisinfo' );

		$info = WP_ServerInfo_Cache::redis_stats();

		echo '<h2>Redis ' . esc_html( is_array( $info ) ? ( $info['redis_version'] ?? '' ) : '' ) . '</h2>' . "\n";

		if ( ! $info ) {
			echo '<p>' . esc_html__( 'Could not connect to the Redis server.', 'wp-serverinfo' ) . '</p>' . "\n";
			echo '</div>' . "\n";
			return;
		}

		$hits    = isset( $info['keyspace_hits'] ) ? (int) $info['keyspace_hits'] : 0;
		$misses  = isset( $info['keyspace_misses'] ) ? (int) $info['keyspace_misses'] : 0;
		$hit_pct = WP_ServerInfo_Cache::redis_hit_ratio( $info );

		self::render_stats_table(
			array(
				array( 'redis_version', $info['redis_version'] ?? '', __( 'Redis server version', 'wp-serverinfo' ) ),
				array( 'redis_mode', $info['redis_mode'] ?? '', __( 'Server mode (standalone, sentinel or cluster)', 'wp-serverinfo' ) ),
				array( 'uptime_in_days', number_format_i18n( $info['uptime_in_days'] ?? 0 ), __( 'Number of days since the server was started', 'wp-serverinfo' ) ),
				array( 'connected_clients', number_format_i18n( $info['connected_clients'] ?? 0 ), __( 'Number of client connections', 'wp-serverinfo' ) ),
				array( 'used_memory', $info['used_memory_human'] ?? WP_ServerInfo_Format::filesize( $info['used_memory'] ?? 0 ), __( 'Memory allocated by Redis', 'wp-serverinfo' ) ),
				array( 'used_memory_peak', $info['used_memory_peak_human'] ?? WP_ServerInfo_Format::filesize( $info['used_memory_peak'] ?? 0 ), __( 'Peak memory consumed by Redis', 'wp-serverinfo' ) ),
				array( 'maxmemory', $info['maxmemory_human'] ?? WP_ServerInfo_Format::filesize( $info['maxmemory'] ?? 0 ), __( 'Memory limit configured for Redis', 'wp-serverinfo' ) ),
				array( 'maxmemory_policy', $info['maxmemory_policy'] ?? '', __( 'Eviction policy applied when the memory limit is reached', 'wp-serverinfo' ) ),
				array( 'total_connections_received', number_format_i18n( $info['total_connections_received'] ?? 0 ), __( 'Total number of connections accepted by the server', 'wp-serverinfo' ) ),
				array( 'total_commands_processed', number_format_i18n( $info['total_commands_processed'] ?? 0 ), __( 'Total number of commands processed by the server', 'wp-serverinfo' ) ),
				array( 'instantaneous_ops_per_sec', number_format_i18n( $info['instantaneous_ops_per_sec'] ?? 0 ), __( 'Number of commands processed per second', 'wp-serverinfo' ) ),
				array( 'keyspace_hits', number_format_i18n( $hits ) . ' (' . $hit_pct . '%)', __( 'Number of successful lookups of keys in the main dictionary', 'wp-serverinfo' ) ),
				array( 'keyspace_misses', number_format_i18n( $misses ), __( 'Number of failed lookups of keys in the main dictionary', 'wp-serverinfo' ) ),
				array( 'expired_keys', number_format_i18n( $info['expired_keys'] ?? 0 ), __( 'Total number of key expiration events', 'wp-serverinfo' ) ),
				array( 'evicted_keys', number_format_i18n( $info['evicted_keys'] ?? 0 ), __( 'Number of evicted keys due to the memory limit', 'wp-serverinfo' ) ),
				array( 'connected_slaves', number_format_i18n( $info['connected_slaves'] ?? 0 ), __( 'Number of connected replicas', 'wp-serverinfo' ) ),
			)
		);

		echo '</div>' . "\n";
	}
}
