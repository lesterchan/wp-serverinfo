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
 * The plugin stores no settings, no tables and no version row: everything it
 * displays is read live from the host on each render, which is the whole point
 * of it. There is no options class either, and nothing here to migrate -- a
 * future release that genuinely needs state introduces a row then, and reads
 * its absence as a fresh install exactly as every other plugin here does.
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

		add_action( 'admin_menu', array( WP_ServerInfo_Admin::class, 'add_page' ) );
		add_action( 'wp_dashboard_setup', array( WP_ServerInfo_Dashboard::class, 'register_widget' ) );
	}
}
