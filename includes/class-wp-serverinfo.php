<?php
/**
 * Plugin bootstrap.
 *
 * @package WP-ServerInfo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires the two admin surfaces up to WordPress.
 *
 * The plugin stores no settings and no tables: everything it displays is read
 * live from the host on each render, which is the whole point of it. The one
 * row it does keep is wp_serverinfo_version, so that a future release can tell
 * what it is upgrading from -- see WP_ServerInfo_Options.
 */
class WP_ServerInfo {

	/**
	 * Sole instance.
	 *
	 * @var WP_ServerInfo|null
	 */
	private static $instance = null;

	/**
	 * Get the sole instance, creating it on first call.
	 *
	 * @return WP_ServerInfo
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	private function __construct() {
		WP_ServerInfo_Options::register();

		add_action( 'admin_menu', array( WP_ServerInfo_Admin::class, 'register_menu' ) );
		add_action( 'wp_dashboard_setup', array( WP_ServerInfo_Dashboard::class, 'register_widget' ) );
	}
}
