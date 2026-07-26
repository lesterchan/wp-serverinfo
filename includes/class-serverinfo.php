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
 * The plugin stores nothing -- no options, no tables, no cron -- so there is
 * no activation, upgrade or uninstall path to run. Everything it displays is
 * read live from the host on each render, which is the whole point of it.
 */
class ServerInfo {

	/**
	 * Sole instance.
	 *
	 * @var ServerInfo|null
	 */
	private static $instance = null;

	/**
	 * Get the sole instance, creating it on first call.
	 *
	 * @return ServerInfo
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
		add_action( 'admin_menu', array( ServerInfo_Admin::class, 'register_menu' ) );
		add_action( 'wp_dashboard_setup', array( ServerInfo_Dashboard::class, 'register_widget' ) );
	}
}
