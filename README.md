# WP-ServerInfo
Contributors: GamerZ  
Donate link: https://lesterchan.net/site/donation/  
Tags: phpinfo, mysql, php, memcached, redis  
Requires at least: 6.0  
Tested up to: 7.0  
Stable tag: 3.0.0  
Requires PHP: 7.4  
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display your host's PHP, MYSQL, memcached & Redis information on your WordPress dashboard.

## Description

### Development
[https://github.com/lesterchan/wp-serverinfo](https://github.com/lesterchan/wp-serverinfo "https://github.com/lesterchan/wp-serverinfo")

### Credits
* Plugin icon by [Picol](https://picol.org) from [Flaticon](https://www.flaticon.com)

### Donations
I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.

## Changelog
### 3.0.0
* NEW: `serverinfo_memcached_server` and `serverinfo_redis_server` filters to point the memcached and Redis panels at a server other than localhost
* NEW: The memcached and Redis panels say the server is unreachable instead of rendering an empty panel
* NEW: Uninstalling now clears the per-user dashboard state WordPress records for the Server Information widget, which previously stayed in the database forever
* FIX: "Attempt to read property on null" on every render of the General tab and the dashboard widget, because MySQL 8.0 removed `query_cache_size` and the missing row was dereferenced anyway
* FIX: Lowercase PHP shorthand sizes (`128m`, `1g`) displayed as the raw string instead of a formatted size
* FIX: `memory_limit = -1` displayed as "unknown" rather than "Unlimited"
* FIX: `max_execution_time = 0`, meaning no script timeout, displayed as "N/As"
* FIX: A value sitting exactly on a unit boundary used the unit below it, so one gibibyte displayed as "1,024.0 MiB"
* FIX: The server load probe ran `system('uptime')`, which prints its output rather than returning it, so the raw uptime line was printed into the General table on hosts where `system()` was enabled
* FIX: The dashboard widget showed a bare ":80" for the server IP on IIS
* FIX: PHP 8 warnings from the memcached panel when the server did not report every statistic
* CHANGE: Read the server load from `sys_getloadavg()` where available, falling back to `/proc/loadavg` and then a shell
* CHANGE: Restructure the plugin into `includes/` with one class per concern; the main file is now just the header, constants and boot
* CHANGE: Remove the seventeen global functions (`format_filesize()`, `get_mysql_version()`, `get_serverload()` and friends) with no deprecation shims. They were never a documented API, and their generic names collided with other plugins
* CHANGE: Remove the dead `phpinfo()` scraping fallback in the GD version probe
* CHANGE: Add a PHPUnit test suite and GitHub Actions CI

### 2.0.0
* NEW: Redis information panel and dashboard-widget section (via the phpredis extension)
* NEW: Redesigned admin page with native WordPress tabs (bookmarkable, no JavaScript required)
* NEW: WordPress 7.0
* FIX: Escape server/PHP/MySQL/memcached output to prevent XSS
* FIX: Guard $_SERVER access to avoid warnings when keys are unset
* CHANGE: Remove jQuery dependency and inline the vanilla JS (removed serverinfo-js.js / serverinfo-js.dev.js)
* CHANGE: Require the manage_options capability for the admin page (was add_users)
* CHANGE: Modernize the admin page slug to wp-serverinfo
* CHANGE: Prefer the Memcached extension, falling back to Memcache
* CHANGE: Add direct-access (ABSPATH) guard
* CHANGE: Rebuild PHP tab from ini_get_all() instead of scraping phpinfo() output (no longer exposes environment variables / request headers)
* CHANGE: Bring the codebase up to WordPress Coding Standards (phpcbf + docblocks)

### 1.66
* NEW: Remove get_php_magic_quotes_gpc() and SERVER_ADMIN

### 1.65
* NEW: Bump version to force update

### 1.64
* FIXED: Remove safe mode and replace it with MySQL Query Cache Size

### 1.63
* FIXED: Uses WordPress Polyglots for translation rather than po/mo

### 1.62
* FIXED: phpinfo() display issue. Now uses DOMDocument to parse it

### 1.61
* FIXED: PHP notices & remove eregi()

### 1.60 (09-01-2011)
* NEW: Added memcached info if your PHP is compiled with memcached extension
* NEW: Ported readme.html to readme.txt

### 1.50 (01-06-2009)

* NEW: Works For WordPress 2.8
* NEW: Minified Javascript Instead Of Packed Javascript
* NEW: Renamed serverinfo-js-packed.js To serverinfo-js.js
* NEW: Renamed serverinfo-js.js To pserverinfo-js.dev.js
* NEW: Added "View all" Link To WP-ServerInfo Page On WP-ServerInfo Dashboard Widget
* FIXED: Server Date/Time Too Fast

### 1.40 (12-12-2008)
* NEW: Works For WordPress 2.7 Only
* NEW: Load Admin JS And CSS Only In WP-ServerInfo Dashboard Page
* NEW: Right To Left Language Support by Kambiz R. Khojasteh
* NEW: Uses plugins_url()
* FIXED: SSL Support
* FIXED: In "General Overview", Used format_filesize() To Format Size Related PHP Values by Kambiz R. Khojasteh

### 1.31 (16-07-2008)
* NEW: Works For WordPress 2.6

### 1.30 (01-06-2008)
* NEW: Works With WordPress 2.5 Only
* NEW: Uses /wp-serverinfo/ Folder Instead Of /serverinfo/
* NEW: Uses wp-serverinfo.php Instead Of serverinfo.php
* NEW: Renamed serverinfo-js.php To serverinfo-js.js
* NEW: Uses serverinfo-js-packed.js
* NEW: Removed serverinfo-css.css

### 1.00 (01-02-2007)
* NEW: Initial Release

## Installation

1. Open `wp-content/plugins` Folder
2. Put: `Folder: wp-serverinfo`
3. Activate `WP-ServerInfo` Plugin
4. Go to `WP-Admin -> Dashboard -> WP-ServerInfo`

## Upgrading

1. Deactivate `WP-ServerInfo` Plugin
2. Open `wp-content/plugins` Folder
3. Put/Overwrite: `Folder: wp-serverinfo`
4. Activate `WP-ServerInfo` Plugin

## Upgrade Notice

N/A

## Screenshots
1. Dashboard
2. General Info
3. PHP Info
4. MYSQL Info
5. Memcached Info

## Frequently Asked Questions

[WP-ServerInfo Support Forums](https://wordpress.org/support/plugin/wp-serverinfo/ "WP-ServerInfo Support Forums")
