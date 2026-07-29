<?php
/**
 * Plugin Name: WP-ServerInfo
 * Plugin URI: https://lesterchan.net/portfolio/programming/php/
 * Description: Display your host's PHP, MYSQL, memcached & Redis information on your WordPress dashboard.
 * Version: 3.0.0
 * Requires at least: 6.8
 * Requires PHP: 8.2
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
	Copyright 2026  Lester Chan  (email : lesterchan@gmail.com)

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

defined( 'ABSPATH' ) || exit;

/**
 * WP-ServerInfo version.
 */
define( 'WP_SERVERINFO_VERSION', '3.0.0' );

/**
 * WP-ServerInfo main file.
 */
define( 'WP_SERVERINFO_MAIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-serverinfo-format.php';
require_once __DIR__ . '/includes/class-serverinfo-php.php';
require_once __DIR__ . '/includes/class-serverinfo-mysql.php';
require_once __DIR__ . '/includes/class-serverinfo-cache.php';
require_once __DIR__ . '/includes/class-serverinfo-admin.php';
require_once __DIR__ . '/includes/class-serverinfo-dashboard.php';
require_once __DIR__ . '/includes/class-serverinfo.php';

ServerInfo::get_instance();
