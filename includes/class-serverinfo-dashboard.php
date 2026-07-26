<?php
/**
 * The Server Information dashboard widget.
 *
 * @package WP-ServerInfo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the at-a-glance widget on the Dashboard.
 *
 * This is a core dashboard widget registered with wp_add_dashboard_widget(),
 * not a sidebar widget, so there is no WP_Widget subclass to convert to.
 *
 * Replaces the global wp_dashboard_serverinfo() function from before 3.0.0.
 */
class ServerInfo_Dashboard {

	/**
	 * Register the widget for administrators.
	 *
	 * @return void
	 */
	public static function register_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'dashboard_serverinfo',
			__( 'Server Information', 'wp-serverinfo' ),
			array( self::class, 'render' )
		);
	}

	/**
	 * Render one labelled list item.
	 *
	 * @param string $label  Label text, or '' for an unlabelled item.
	 * @param string $value  Value text, emitted in bold.
	 * @param string $prefix Text placed before the bold value, outside it.
	 *                       The version rows read "v<strong>8.3.1</strong>",
	 *                       with the "v" deliberately not bold.
	 * @return void
	 */
	private static function render_item( $label, $value, $prefix = '' ) {
		if ( '' === $label ) {
			printf( "<li>%s<strong>%s</strong></li>\n", esc_html( $prefix ), esc_html( $value ) );
			return;
		}

		printf( "<li>%s: <strong>%s</strong></li>\n", esc_html( $label ), esc_html( $value ) );
	}

	/**
	 * Render a section heading and its list of items.
	 *
	 * @param string $heading Section heading.
	 * @param array  $items   List of [ label, value ] pairs, optionally with a
	 *                        third prefix element.
	 * @return void
	 */
	private static function render_section( $heading, array $items ) {
		echo '<p><strong>' . esc_html( $heading ) . '</strong></p>' . "\n";
		echo '<ul>' . "\n";

		foreach ( $items as $item ) {
			self::render_item( $item[0], $item[1], $item[2] ?? '' );
		}

		echo '</ul>' . "\n";
	}

	/**
	 * Render the widget contents.
	 *
	 * @return void
	 */
	public static function render() {
		if ( is_rtl() ) {
			// Server values are latin text whatever the admin language is.
			echo '<style>#wp-serverinfo ul { padding-left: 15px !important; }</style>' . "\n";
			echo '<div id="wp-serverinfo" style="direction: ltr; text-align: left;">' . "\n";
		} else {
			echo '<div id="wp-serverinfo">' . "\n";
		}

		self::render_section(
			__( 'General', 'wp-serverinfo' ),
			array(
				array( __( 'OS', 'wp-serverinfo' ), PHP_OS ),
				array( __( 'Server', 'wp-serverinfo' ), ServerInfo_PHP::server_value( 'SERVER_SOFTWARE' ) ),
				array( __( 'Hostname', 'wp-serverinfo' ), ServerInfo_PHP::server_value( 'SERVER_NAME' ) ),
				array( __( 'IP:Port', 'wp-serverinfo' ), ServerInfo_PHP::server_address() . ':' . ServerInfo_PHP::server_value( 'SERVER_PORT' ) ),
				array( __( 'Document Root', 'wp-serverinfo' ), ServerInfo_PHP::server_value( 'DOCUMENT_ROOT' ) ),
			)
		);

		self::render_section(
			'PHP',
			array(
				array( '', PHP_VERSION, 'v' ),
				array( 'GD', ServerInfo_PHP::gd_version() ),
				array( __( 'Memory Limit', 'wp-serverinfo' ), ServerInfo_Format::php_size( ServerInfo_PHP::memory_limit() ) ),
				array( __( 'Max Script Execute Time', 'wp-serverinfo' ), ServerInfo_PHP::max_execution() . 's' ),
				array( __( 'Max Post Size', 'wp-serverinfo' ), ServerInfo_Format::php_size( ServerInfo_PHP::post_max() ) ),
				array( __( 'Max Upload Size', 'wp-serverinfo' ), ServerInfo_Format::php_size( ServerInfo_PHP::upload_max() ) ),
			)
		);

		self::render_section(
			'MYSQL',
			array(
				array( '', ServerInfo_MySQL::version(), 'v' ),
				array( __( 'Maximum No. Connections', 'wp-serverinfo' ), ServerInfo_Format::number( ServerInfo_MySQL::max_connections() ) ),
				array( __( 'Maximum Packet Size', 'wp-serverinfo' ), ServerInfo_Format::filesize( ServerInfo_MySQL::max_allowed_packet() ) ),
				array( __( 'Data Disk Usage', 'wp-serverinfo' ), ServerInfo_Format::filesize( ServerInfo_MySQL::data_usage() ) ),
				array( __( 'Index Disk Usage', 'wp-serverinfo' ), ServerInfo_Format::filesize( ServerInfo_MySQL::index_usage() ) ),
			)
		);

		if ( ServerInfo_Cache::has_redis() ) {
			$redis = ServerInfo_Cache::redis_stats();

			if ( $redis ) {
				self::render_section(
					'Redis',
					array(
						array( '', $redis['redis_version'] ?? '', 'v' ),
						array(
							__( 'Uptime', 'wp-serverinfo' ),
							sprintf(
								/* translators: %s: number of days */
								__( '%s days', 'wp-serverinfo' ),
								number_format_i18n( $redis['uptime_in_days'] ?? 0 )
							),
						),
						array( __( 'Used Memory', 'wp-serverinfo' ), $redis['used_memory_human'] ?? ServerInfo_Format::filesize( $redis['used_memory'] ?? 0 ) ),
						array( __( 'Connected Clients', 'wp-serverinfo' ), number_format_i18n( $redis['connected_clients'] ?? 0 ) ),
						array( __( 'Hit Ratio', 'wp-serverinfo' ), ServerInfo_Cache::redis_hit_ratio( $redis ) . '%' ),
					)
				);
			}
		}

		printf(
			"<p class=\"textright\"><a href=\"%s\" class=\"button\">%s</a></p>\n",
			esc_url( admin_url( 'index.php?page=' . ServerInfo_Admin::SLUG ) ),
			esc_html__( 'View all', 'wp-serverinfo' )
		);

		echo '</div>' . "\n";
	}
}
