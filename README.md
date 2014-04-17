# WP-ServerInfo
Contributors: GamerZ  
Donate link: http://lesterchan.net/site/donation/  
Tags: phpinfo, mysql, php, server, serverinfo, info, information, memcached, memcache  
Requires at least: 2.8  
Tested up to: 3.9  
Stable tag: trunk  

Display your host's PHP, MYSQL & memcached (if installed) information on your WordPress dashboard.

## Description

### Development
* [https://github.com/lesterchan/wp-serverinfo](https://github.com/lesterchan/wp-serverinfo "https://github.com/lesterchan/wp-serverinfo")

### Translations
* [http://dev.wp-plugins.org/browser/wp-serverinfo/i18n/](http://dev.wp-plugins.org/browser/wp-serverinfo/i18n/ "http://dev.wp-plugins.org/browser/wp-serverinfo/i18n/")

### Credits
* Right To Left Language Support by [Kambiz R. Khojasteh](http://persian-programming.com/ "Kambiz R. Khojasteh")

### Donations
* I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appericiate it. If not feel free to use it without any obligations.

## Changelog

## 1.61
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

1. General Info
2. PHP Info
3. MYSQL Info

## Frequently Asked Questions

[WP-ServerInfo Support Forums](http://forums.lesterchan.net/index.php?board=25.0 "WP-ServerInfo Support Forums")
