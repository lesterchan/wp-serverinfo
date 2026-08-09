# WP-ServerInfo
Contributors: GamerZ  
Donate link: https://lesterchan.net/site/donation/  
Tags: phpinfo, mysql, php, memcached, redis  
Requires at least: 6.8  
Tested up to: 7.0  
Stable tag: 3.0.0  
Requires PHP: 8.2  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display your host's PHP, MYSQL, memcached & Redis information on your WordPress dashboard.

## Description
WP-ServerInfo reports what your host is actually giving you: the operating system and web server, the PHP version and every one of its limits, the MySQL version and its variables, and the statistics of your memcached and Redis servers if you run them.

There is nothing to configure and no settings screen. Every figure is read live from the host each time you open the page, so there is nothing stored, nothing to keep in step and nothing that can go stale.

### Features
* A Server Information widget on your Dashboard with the figures you look up most often
* A full report at `Tools -> WP-ServerInfo`, in five tabs
 * General — OS, web server, hostname, IP and port, document root, server date and load, and the headline PHP and MySQL numbers side by side
 * PHP — version, Zend Engine, SAPI, loaded configuration file, loaded extensions, and every `php.ini` directive with its local and master value
 * MySQL — version and every server variable
 * memcached — the full statistics set, each row explained, when the Memcached or Memcache extension is installed
 * Redis — version, uptime, memory, clients, hit ratio and eviction figures, when the phpredis extension is installed
* Sizes and counts formatted in your own locale, and `memory_limit = -1` reported as Unlimited rather than as a number

### Donations
I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.

## Installation

1. Install and activate the plugin.

That is the whole of it. There is no settings screen. The Server Information widget appears on your Dashboard and the full report is at `WP-Admin -> Tools -> WP-ServerInfo`. Both require the `manage_options` capability, because between them they report your document root, your server's IP address and your whole PHP and MySQL configuration.

## Usage
Activate the plugin and you are done.

The Server Information widget appears on your Dashboard, and the full report is at `Tools -> WP-ServerInfo`. Both require the `manage_options` capability, because between them they report your document root, your server's IP address and your whole PHP and MySQL configuration.

### The memcached and Redis tabs
Each tab appears only when the matching PHP extension is loaded, and by default each reports on the server running on this machine — `localhost:11211` for memcached, `127.0.0.1:6379` for Redis. If your cache lives somewhere else, point the plugin at it from your theme's `functions.php` or a small must-use plugin:

```php
add_filter(
	'wp_serverinfo_redis_server',
	function ( $server ) {
		$server['host']    = 'redis.internal';
		$server['port']    = 6379;
		$server['timeout'] = 1;

		return $server;
	}
);
```

`wp_serverinfo_memcached_server` takes the same shape, with `host` and `port`. Either filter may return only the keys it wants to change; the rest keep their defaults. A `unix://` socket path works as the Redis `host`, with `port` set to `0`.

### Handing the screens to somebody else
Every capability check goes through one filter, so a site that wants a non-administrator to see the report changes it in one place:

```php
add_filter(
	'wp_serverinfo_capability',
	function ( $capability, $context ) {
		return 'report' === $context ? 'edit_pages' : $capability;
	},
	10,
	2
);
```

`$context` is `report` for the Tools screen and `widget` for the Dashboard widget, so the two can be answered differently.

## Frequently Asked Questions

### Where did the screen go? It used to be under Dashboard
It moved to `Tools -> WP-ServerInfo` in 3.0.0. It is a report about your host rather than a Dashboard panel, and Tools is where WordPress keeps that kind of screen. The Dashboard widget has not moved, and its "View all" button goes to the new address.

### The memcached or Redis tab is missing
The tab appears only when the corresponding PHP extension is loaded — Memcached or Memcache for one, phpredis for the other. Check the PHP tab's Loaded Extensions row. Installing an object cache *drop-in* is not the same thing as having the extension.

### The tab is there but says it could not connect
The extension is installed but nothing answered on the address the plugin tried. By default that is this machine on the default port; if your cache is on another host, see "The memcached and Redis tabs" above.

### Server Load says N/A
The load average is a Unix idea, so it is always N/A on Windows. On other hosts the plugin tries `sys_getloadavg()`, then `/proc/loadavg`, then `uptime` through a shell, and shared hosts commonly disable all three.

### MYSQL Query Cache Size is empty
The query cache was removed in MySQL 8.0, so there is no such variable to report. Before 3.0.0 this raised "Attempt to read property on null" instead.

### Does the plugin store anything in my database?
One row, and a tiny one. There is nothing to configure, so the plugin stores no settings at all — only `wp_serverinfo_version`, which records the version last run so that an upgrade knows what it is upgrading from. Deleting the plugin from the Plugins screen removes it, along with the per-user Dashboard state WordPress records when somebody closes, hides or reorders the widget.

### Is the PHP tab safe to look at over the shoulder of a client?
It is built from `ini_get_all()`, so it lists directives and their values and nothing else. Before 2.0.0 it scraped `phpinfo()` output, which also printed your environment variables and request headers.

## Screenshots

1. Tools -> WP-ServerInfo, the General tab: the host, the web server and the document root
2. The PHP tab, which is phpinfo() inside the admin
3. The MySQL tab, every server variable and its value
4. The dashboard widget, a summary of all three

## Changelog
### 3.0.0
* BREAKING: Requires WordPress 6.8 and PHP 8.2, up from 4.0 and 7.2.
* BREAKING: The report moved from `Dashboard -> WP-ServerInfo` to `Tools -> WP-ServerInfo`. The page slug is unchanged, so the URL goes from `index.php?page=wp-serverinfo` to `tools.php?page=wp-serverinfo`
* BREAKING: All thirty global functions are gone, with no deprecation shims — `format_filesize()`, `format_php_size()`, `display_serverinfo()`, `get_mysql_version()`, `get_serverload()`, `get_gd_version()` and the rest are now methods on `WP_ServerInfo_Format`, `WP_ServerInfo_MySQL`, `WP_ServerInfo_PHP` and `WP_ServerInfo_Cache`. They were never a documented API, and their generic names collided with other plugins
* NEW: `wp_serverinfo_memcached_server` and `wp_serverinfo_redis_server` filters to point the memcached and Redis panels at a server other than localhost
* NEW: `wp_serverinfo_capability` filter, so the report and the widget can be handed to somebody other than an administrator, separately
* NEW: The memcached and Redis panels say the server is unreachable instead of rendering an empty panel
* BREAKING: On multisite the report and the dashboard widget now require `manage_network_options` rather than `manage_options`. A site administrator on a network is a tenant of it, and this screen prints the document root, the server's own address, the loaded `php.ini`, every ini directive and every MySQL server variable — WordPress itself closes Site Health, which reports strictly less, to that same person. On a single site nothing changes. A network that does want to delegate it can, through `wp_serverinfo_capability`
* FIXED: The PHP tab printed the value of every ini directive, including the ones that hold credentials rather than configuration — `mysqli.default_pw`, a redis DSN in `session.save_path`, an ssmtp password in `sendmail_path`, and the licence keys and API tokens that monitoring extensions add. Those values now read `[hidden]`; the directive is still listed, so a configured secret can still be told apart from an absent one. `wp_serverinfo_secret_directives` filters the list
* NEW: A `wp_serverinfo_version` row recording the version last run, and an `uninstall.php` that deletes it on a single site and across a network
* NEW: Uninstalling now clears the per-user dashboard state WordPress records for the Server Information widget, which previously stayed in the database forever
* FIXED: "Attempt to read property on null" on every render of the General tab and the dashboard widget, because MySQL 8.0 removed `query_cache_size` and the missing row was dereferenced anyway
* FIXED: Lowercase PHP shorthand sizes (`128m`, `1g`) displayed as the raw string instead of a formatted size
* FIXED: `memory_limit = -1` displayed as "unknown" rather than "Unlimited"
* FIXED: `max_execution_time = 0`, meaning no script timeout, displayed as "N/As"
* FIXED: A value sitting exactly on a unit boundary used the unit below it, so one gibibyte displayed as "1,024.0 MiB"
* FIXED: The server load probe ran `system('uptime')`, which prints its output rather than returning it, so the raw uptime line was printed into the General table on hosts where `system()` was enabled
* FIXED: The dashboard widget showed a bare ":80" for the server IP on IIS
* FIXED: PHP 8 warnings from the memcached panel when the server did not report every statistic
* CHANGED: Read the server load from `sys_getloadavg()` where available, falling back to `/proc/loadavg` and then a shell
* CHANGED: Restructure the plugin into `includes/` with one class per concern, all prefixed `WP_ServerInfo_`; the main file is now just the header, constants and boot
* CHANGED: Remove the dead `phpinfo()` scraping fallback in the GD version probe
* CHANGED: The screens use core's own table and tab classes, and carry no inline styles at all. The RTL handling is one `dir` attribute rather than a stylesheet with a physical `text-align` and an `!important`
* CHANGED: Add a PHPUnit test suite, both single site and multisite, and GitHub Actions CI
* FIXED: The server load average was formatted by only one of the four ways it is read, so what the General table showed depended on which one your host took. Every branch answers with a number now and it is formatted once, with the decimal separator your locale uses rather than always a point

## Upgrade Notice

### 3.0.0

Requires WordPress 6.8 and PHP 8.2, up from WordPress 4.0 and PHP 7.2.

**The screen moved from Dashboard to Tools**, from `/wp-admin/index.php?page=wp-serverinfo` to `/wp-admin/tools.php?page=wp-serverinfo`. The page slug is unchanged. It is a report about your host rather than a Dashboard panel, and Tools is where WordPress keeps that sort of screen. The Server Information dashboard widget stays where it was, and its "View all" button follows the page.

**All thirty global functions are gone.** `format_filesize()`, `format_php_size()`, `get_mysql_version()`, `get_serverload()`, `get_gd_version()`, `display_serverinfo()` and the rest are methods on `WP_ServerInfo_Format`, `WP_ServerInfo_MySQL`, `WP_ServerInfo_PHP` and `WP_ServerInfo_Cache`. They were never a documented API and there are no shims, so a theme or snippet calling one will fatal. The names were generic enough to collide anyway — WP-DownloadManager defines a `format_filesize()` of its own, and whichever plugin loaded first won.

**Three new filters.** `wp_serverinfo_memcached_server` and `wp_serverinfo_redis_server` are the only way to point those two panels at a server that is not on localhost; `wp_serverinfo_capability` hands the screen to somebody other than an administrator. All three are new rather than renamed, so nothing existing breaks.

**The plugin now stores one row**, `wp_serverinfo_version`, recording the version last run so a future upgrade knows what it is upgrading from. There is still no settings screen. Deleting the plugin removes that row and also clears the per-user dashboard state WordPress recorded whenever somebody closed, hid or reordered the widget, which 2.0.0 left in the database forever.
