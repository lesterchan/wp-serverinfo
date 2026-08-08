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
class WP_ServerInfo_Dashboard {

	/**
	 * Register the widget for administrators.
	 *
	 * @return void
	 */
	public static function register_widget() {
		if ( ! current_user_can( WP_ServerInfo_Admin::capability( 'widget' ) ) ) {
			return;
		}

		wp_add_dashboard_widget(
			WP_SERVERINFO_WIDGET_ID,
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
		/*
		 * The gate that matters is on register_widget() above, because
		 * wp_dashboard() renders only what was registered. This second check is
		 * here because the method is public and prints the document root and the
		 * server's own address: a caller arriving by any other route -- a
		 * do_meta_boxes() on the dashboard screen, a future ajax path -- must not
		 * get them for free. The report screen already checks in both places.
		 */
		if ( ! current_user_can( WP_ServerInfo_Admin::capability( 'widget' ) ) ) {
			return;
		}

		/*
		 * dir="ltr", and nothing else. Every value below is latin text whatever
		 * the admin language is, so the widget has to read left to right even on
		 * an RTL install -- but before 3.0.0 that meant an inline style attribute
		 * and a <style> block carrying padding-left with an !important, all three
		 * of which section 4.4 and section 5 forbid. The attribute says the same
		 * thing to the browser and needs no stylesheet at all; the list padding it
		 * was correcting is core's, and core mirrors it for RTL already.
		 */
		echo '<div id="wp-serverinfo" dir="ltr">' . "\n";

		self::render_section(
			__( 'General', 'wp-serverinfo' ),
			array(
				array( __( 'OS', 'wp-serverinfo' ), PHP_OS ),
				array( __( 'Server', 'wp-serverinfo' ), WP_ServerInfo_PHP::server_value( 'SERVER_SOFTWARE' ) ),
				array( __( 'Hostname', 'wp-serverinfo' ), WP_ServerInfo_PHP::server_value( 'SERVER_NAME' ) ),
				array( __( 'IP:Port', 'wp-serverinfo' ), WP_ServerInfo_PHP::server_address() . ':' . WP_ServerInfo_PHP::server_value( 'SERVER_PORT' ) ),
				array( __( 'Document Root', 'wp-serverinfo' ), WP_ServerInfo_PHP::server_value( 'DOCUMENT_ROOT' ) ),
			)
		);

		self::render_section(
			'PHP',
			array(
				array( '', PHP_VERSION, 'v' ),
				array( 'GD', WP_ServerInfo_PHP::gd_version() ),
				array( __( 'Memory Limit', 'wp-serverinfo' ), WP_ServerInfo_Format::php_size( WP_ServerInfo_PHP::memory_limit() ) ),
				array( __( 'Max Script Execute Time', 'wp-serverinfo' ), WP_ServerInfo_PHP::max_execution() . 's' ),
				array( __( 'Max Post Size', 'wp-serverinfo' ), WP_ServerInfo_Format::php_size( WP_ServerInfo_PHP::post_max() ) ),
				array( __( 'Max Upload Size', 'wp-serverinfo' ), WP_ServerInfo_Format::php_size( WP_ServerInfo_PHP::upload_max() ) ),
			)
		);

		self::render_section(
			'MYSQL',
			array(
				array( '', WP_ServerInfo_MySQL::version(), 'v' ),
				array( __( 'Maximum No. Connections', 'wp-serverinfo' ), WP_ServerInfo_Format::number( WP_ServerInfo_MySQL::max_connections() ) ),
				array( __( 'Maximum Packet Size', 'wp-serverinfo' ), WP_ServerInfo_Format::filesize( WP_ServerInfo_MySQL::max_allowed_packet() ) ),
				array( __( 'Data Disk Usage', 'wp-serverinfo' ), WP_ServerInfo_Format::filesize( WP_ServerInfo_MySQL::data_usage() ) ),
				array( __( 'Index Disk Usage', 'wp-serverinfo' ), WP_ServerInfo_Format::filesize( WP_ServerInfo_MySQL::index_usage() ) ),
			)
		);

		if ( WP_ServerInfo_Cache::has_redis() ) {
			$redis = WP_ServerInfo_Cache::redis_stats();

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
						array( __( 'Used Memory', 'wp-serverinfo' ), $redis['used_memory_human'] ?? WP_ServerInfo_Format::filesize( $redis['used_memory'] ?? 0 ) ),
						array( __( 'Connected Clients', 'wp-serverinfo' ), number_format_i18n( $redis['connected_clients'] ?? 0 ) ),
						array( __( 'Hit Ratio', 'wp-serverinfo' ), WP_ServerInfo_Cache::redis_hit_ratio( $redis ) . '%' ),
					)
				);
			}
		}

		printf(
			"<p><a href=\"%s\" class=\"button\">%s</a></p>\n",
			esc_url( WP_ServerInfo_Admin::url() ),
			esc_html__( 'View all', 'wp-serverinfo' )
		);

		echo '</div>' . "\n";
	}
}
