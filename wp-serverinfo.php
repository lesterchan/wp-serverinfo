<?php
/*
Plugin Name: WP-ServerInfo
Plugin URI: https://lesterchan.net/portfolio/programming/php/
Description: Display your host's PHP, MYSQL & memcached (if installed) information on your WordPress dashboard.
Version: 1.66
Author: Lester 'GaMerZ' Chan
Author URI: https://lesterchan.net
Text Domain: wp-serverinfo
*/


/*
    Copyright 2020 Lester Chan  (email : lesterchan@gmail.com)

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


### Create Text Domain For Translations
add_action('init', 'serverinfo_textdomain');
function serverinfo_textdomain() {
    load_plugin_textdomain( 'wp-serverinfo' );
}


### Function: WP-ServerInfo Menu
add_action('admin_menu', 'serverinfo_menu');
function serverinfo_menu() {
    if (function_exists('add_submenu_page')) {
        add_submenu_page('index.php',  __('WP-ServerInfo', 'wp-serverinfo'),  __('WP-ServerInfo', 'wp-serverinfo'), 'add_users', 'wp-serverinfo/wp-serverinfo.php', 'display_serverinfo');
    }
}


### Function: Enqueue ServerInfo JavaScripts In WP-Admin
add_action('admin_enqueue_scripts', 'serverinfo_scripts_admin');
function serverinfo_scripts_admin($hook_suffix) {
    $serverinfo_admin_pages = array('dashboard_page_wp-serverinfo/wp-serverinfo');
    if(in_array($hook_suffix, $serverinfo_admin_pages)) {
        wp_enqueue_script('wp-serverinfo', plugins_url('wp-serverinfo/serverinfo-js.js'), array('jquery'), '1.60', true);
    }
}


### Display WP-ServerInfo Admin Page
function display_serverinfo() {
    get_generalinfo();
    get_phpinfo();
    get_mysqlinfo();
    get_memcachedinfo();
}


### Get General Information
function get_generalinfo() {
    global $is_IIS;
    if( is_rtl() ) : ?>
        <style type="text/css">
            #GeneralOverview table,
            #GeneralOverview th,
            #GeneralOverview td {
                direction: ltr;
                text-align: left;
            }
            #GeneralOverview h2 {
                padding: 0.5em 0 0;
            }
        </style>
    <?php endif;
?>
    <div class="wrap" id="GeneralOverview">
        <h2><?php _e('General Overview','wp-serverinfo'); ?></h2>
        <?php serverinfo_subnavi(); ?>
        <br class="clear" />
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php _e('Variable Name', 'wp-serverinfo'); ?></th>
                    <th><?php _e('Value', 'wp-serverinfo'); ?></th>
                    <th><?php _e('Variable Name', 'wp-serverinfo'); ?></th>
                    <th><?php _e('Value', 'wp-serverinfo'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php _e('OS', 'wp-serverinfo'); ?></td>
                    <td><?php echo PHP_OS; ?></td>
                    <td><?php _e('Database Data Disk Usage', 'wp-serverinfo'); ?></td>
                    <td><?php echo format_filesize(get_mysql_data_usage()); ?></td>
                </tr>
                <tr class="alternate">
                    <td><?php _e('Server', 'wp-serverinfo'); ?></td>
                    <td><?php echo $_SERVER["SERVER_SOFTWARE"]; ?></td>
                    <td><?php _e('Database Index Disk Usage', 'wp-serverinfo'); ?></td>
                    <td><?php echo format_filesize(get_mysql_index_usage()); ?></td>
                </tr>
                <tr>
                    <td>PHP</td>
                    <td>v<?php echo PHP_VERSION; ?></td>
                    <td><?php _e('MYSQL Maximum Packet Size', 'wp-serverinfo'); ?></td>
                    <td><?php echo format_filesize(get_mysql_max_allowed_packet()); ?></td>
                </tr>
                <tr class="alternate">
                    <td>MYSQL</td>
                    <td>v<?php echo get_mysql_version(); ?></td>
                    <td><?php _e('MYSQL Maximum No. Connection', 'wp-serverinfo'); ?></td>
                    <td><?php echo number_format_i18n(get_mysql_max_allowed_connections()); ?></td>
                </tr>
                <tr>
                    <td>GD</td>
                    <td><?php echo get_gd_version(); ?></td>
                    <td><?php _e( 'MYSQL Query Cache Size', 'wp-serverinfo' ); ?></td>
                    <td><?php echo format_filesize( get_mysql_query_cache_size() ); ?></td>
                </tr>
                <tr class="alternate">
                    <td><?php _e('Server Hostname', 'wp-serverinfo'); ?></td>
                    <td><?php echo $_SERVER['SERVER_NAME']; ?></td>
                    <td><?php _e('PHP Short Tag', 'wp-serverinfo'); ?></td>
                    <td><?php echo get_php_short_tag(); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Server IP:Port','wp-serverinfo'); ?></td>
                    <td><?php echo ($is_IIS ? $_SERVER['LOCAL_ADDR'] : $_SERVER['SERVER_ADDR']); ?>:<?php echo $_SERVER['SERVER_PORT']; ?></td>
                    <td><?php _e('PHP Max Script Execute Time', 'wp-serverinfo'); ?></td>
                    <td><?php echo get_php_max_execution(); ?>s</td>
                </tr>
                <tr class="alternate">
                    <td><?php _e('Server Document Root','wp-serverinfo'); ?></td>
                    <td><?php echo $_SERVER['DOCUMENT_ROOT']; ?></td>
                    <td><?php _e('PHP Memory Limit', 'wp-serverinfo'); ?></td>
                    <td><?php echo format_php_size(get_php_memory_limit()); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Server Date/Time', 'wp-serverinfo'); ?></td>
                    <td><?php echo mysql2date(sprintf(__('%s @ %s', 'wp-postratings'), get_option('date_format'), get_option('time_format')), current_time('mysql')); ?></td>
                    <td><?php _e('PHP Max Upload Size', 'wp-serverinfo'); ?></td>
                    <td><?php echo format_php_size(get_php_upload_max()); ?></td>
                </tr>
                <tr class="alternate">
                    <td><?php _e('Server Load', 'wp-serverinfo'); ?></td>
                    <td><?php echo get_serverLoad(); ?></td>
                    <td><?php _e('PHP Max Post Size', 'wp-serverinfo'); ?></td>
                    <td><?php echo format_php_size(get_php_post_max()); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
<?php
}


### Get PHP Information
function get_phpinfo() {
    if( ! class_exists( 'DOMDocument' ) ) {
        echo '<div class="wrap" id="PHPinfo" style="display: none;">';
        echo '<h2>PHP ' . phpversion() . '</h2>';
        serverinfo_subnavi();
        echo 'You need <a href="http://php.net/manual/en/class.domdocument.php" target="_blank">DOMDocument extension</a> to be enabled.';
        echo '</div>';
    } else {
        ob_start();
        phpinfo();
        $phpinfo = ob_get_contents();
        ob_end_clean();

        // Use DOMDocument to parse phpinfo()
        $html = new DOMDocument( '1.0', 'UTF-8' );
        $html->loadHTML( $phpinfo );

        // Style process
        $tables = $html->getElementsByTagName( 'table' );
        foreach( $tables as $table ) {
            $table->setAttribute( 'class', 'widefat' );
        }

        // We only need the <body>
        $xpath = new DOMXPath($html);
        $body = $xpath->query('/html/body');

        // Save HTML fragment
        $phpinfo_html = $html->saveXml( $body->item( 0 ) );

        echo '<div class="wrap" id="PHPinfo" style="display: none;">';
        echo '<h2>PHP ' . phpversion() . '</h2>';
        serverinfo_subnavi();
        echo $phpinfo_html;
        echo '</div>';
    }
}


### Get MYSQL Information
function get_mysqlinfo() {
    global $wpdb;
    $sqlversion = $wpdb->get_var("SELECT VERSION() AS version");
    $mysqlinfo = $wpdb->get_results("SHOW VARIABLES");
    if( is_rtl() ) : ?>
        <style type="text/css">
            #MYSQLinfo,
            #MYSQLinfo table,
            #MYSQLinfo th,
            #MYSQLinfo td {
                direction: ltr;
                text-align: left;
            }
            #MYSQLinfo h2 {
                padding: 0.5em 0 0;
            }
        </style>
    <?php endif;
    echo '<div class="wrap" id="MYSQLinfo" style="display: none;">'."\n";
    echo "<h2>MYSQL $sqlversion</h2>\n";
    serverinfo_subnavi();
    if($mysqlinfo) {
        echo '<br class="clear" />'."\n";
        echo '<table class="widefat" dir="ltr">'."\n";
        echo '<thead><tr><th>'.__('Variable Name', 'wp-serverinfo').'</th><th>'.__('Value', 'wp-serverinfo').'</th></tr></thead><tbody>'."\n";
        foreach($mysqlinfo as $info) {
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>'.$info->Variable_name.'</td><td>'.htmlspecialchars($info->Value).'</td></tr>'."\n";
        }
        echo '</tbody></table>'."\n";
    }
    echo '</div>'."\n";
}


### Get memcached Information (Description from https://boxpanel.blueboxgrp.com/public/the_vault/index.php/memcached_Tips)
function get_memcachedinfo() {
    echo '<div class="wrap" id="memcachedinfo" style="display: none;">'."\n";
    if(class_exists('Memcache')) {
        $memcached_obj = new Memcache;
        $memcached_obj->addServer('localhost', 11211);
        $memcachedinfo = $memcached_obj->getStats();
        if( is_rtl() ) : ?>
            <style type="text/css">
                #memcachedinfo,
                #memcachedinfo table,
                #memcachedinfo th,
                #memcachedinfo td {
                    direction: ltr;
                    text-align: left;
                }
                #memcachedinfo h2 {
                    padding: 0.5em 0 0;
                }
            </style>
        <?php endif;
        echo "<h2>memcached {$memcachedinfo['version']}</h2>\n";
        serverinfo_subnavi();
        if($memcachedinfo) {
            $cache_hit= ( $memcachedinfo['cmd_get'] > 0 ? ( ( $memcachedinfo['get_hits'] / $memcachedinfo['cmd_get'] ) * 100 ) : 0 );
            $cache_hit = round($cache_hit, 2);
            $cache_miss = 100 - $cache_hit;

            $usage = round((($memcachedinfo['bytes']/$memcachedinfo['limit_maxbytes']) * 100), 2);
            $uptime = number_format_i18n(($memcachedinfo['uptime']/60/60/24));

            echo '<br class="clear" />'."\n";
            echo '<table class="widefat" dir="ltr">'."\n";
            echo '<thead><tr><th>'.__('Variable Name', 'wp-serverinfo').'</th><th>'.__('Value', 'wp-serverinfo').'</th><th>'.__('Description', 'wp-serverinfo').'</th></tr></thead><tbody>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>pid</td><td>'.$memcachedinfo['pid'].'</td><td>'.__('Process ID', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>uptime</td><td>'.$uptime.'</td><td>'.__('Number of days since the process was started', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>version</td><td>'.$memcachedinfo['version'].'</td><td>'.__('memcached version', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>rusage_user</td><td>'.$memcachedinfo['rusage_user'].'</td><td>'.__('Seconds the cpu has devoted to the process as the user', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>rusage_system</td><td>'.$memcachedinfo['rusage_system'].'</td><td>'.__('Seconds the cpu has devoted to the process as the system', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>curr_items</td><td>'.number_format_i18n($memcachedinfo['curr_items']).'</td><td>'.__('Total number of items currently in memcached', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>total_items</td><td>'.number_format_i18n($memcachedinfo['total_items']).'</td><td>'.__('Total number of items that have passed through memcached', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>bytes</td><td>'.format_filesize($memcachedinfo['bytes']).' ('.$usage.'%)</td><td>'.__('Memory size currently used by curr_items', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>limit_maxbytes</td><td>'.format_filesize($memcachedinfo['limit_maxbytes']).'</td><td>'.__('Maximum memory size allocated to memcached', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>curr_connections</td><td>'.number_format_i18n($memcachedinfo['curr_connections']).'</td><td>'.__('Total number of open connections to memcached', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>total_connections</td><td>'.number_format_i18n($memcachedinfo['total_connections']).'</td><td>'.__('Total number of connections opened since memcached started running', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>connection_structures</td><td>'.number_format_i18n($memcachedinfo['connection_structures']).'</td><td>'.__('Number of connection structures allocated by the server', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>cmd_get</td><td>'.number_format_i18n($memcachedinfo['cmd_get']).'</td><td>'.__('Total GET commands issued to the server', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>cmd_set</td><td>'.number_format_i18n($memcachedinfo['cmd_set']).'</td><td>'.__('Total SET commands issued to the server', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>cmd_flush</td><td>'.number_format_i18n($memcachedinfo['cmd_flush']).'</td><td>'.__('Total FLUSH commands issued to the server', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>get_hits</td><td>'.number_format_i18n($memcachedinfo['get_hits']).' ('.$cache_hit.'%)</td><td>'.__('Total number of times a GET command was able to retrieve and return data', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>get_misses</td><td>'.number_format_i18n($memcachedinfo['get_misses']).' ('.$cache_miss.'%)</td><td>'.__('Total number of times a GET command was unable to retrieve and return data', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>delete_hits</td><td>'.number_format_i18n($memcachedinfo['delete_hits']).'</td><td>'.__('Total number of times a DELETE command was able to delete data', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>delete_misses</td><td>'.number_format_i18n($memcachedinfo['delete_misses']).'</td><td>'.__('Total number of times a DELETE command was unable to delete data', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>incr_hits</td><td>'.number_format_i18n($memcachedinfo['incr_hits']).'</td><td>'.__('Total number of times a INCR command was able to increment a value', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>incr_misses</td><td>'.number_format_i18n($memcachedinfo['incr_misses']).'</td><td>'.__('Total number of times a INCR command was unable to increment a value', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>decr_hits</td><td>'.number_format_i18n($memcachedinfo['decr_hits']).'</td><td>'.__('Total number of times a DECR command was able to decrement a value', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>decr_misses</td><td>'.number_format_i18n($memcachedinfo['decr_misses']).'</td><td>'.__('Total number of times a DECR command was unable to decrement a value', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>cas_hits</td><td>'.number_format_i18n($memcachedinfo['cas_hits']).'</td><td>'.__('Total number of times a CAS command was able to compare and swap data', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>cas_misses</td><td>'.number_format_i18n($memcachedinfo['cas_misses']).'</td><td>'.__('Total number of times a CAS command was unable to compare and swap data', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>cas_badval</td><td>'.number_format_i18n($memcachedinfo['cas_badval']).'</td><td>'.__('N/A', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>bytes_read</td><td>'.format_filesize($memcachedinfo['bytes_read']).'</td><td>'.__('Total number of bytes input into the server', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>bytes_written</td><td>'.format_filesize($memcachedinfo['bytes_written']).'</td><td>'.__('Total number of bytes written by the server', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>evictions</td><td>'.number_format_i18n($memcachedinfo['evictions']).'</td><td>'.__('Number of valid items removed from cache to free memory for new items', 'wp-serverinfo').'</td></tr>'."\n";
            echo '<tr class="" onmouseover="this.className=\'highlight\'" onmouseout="this.className=\'\'"><td>reclaimed</td><td>'.number_format_i18n($memcachedinfo['reclaimed']).'</td><td>'.__('Number of items reclaimed', 'wp-serverinfo').'</td></tr>'."\n";
            echo '</tbody></table>'."\n";
        }
    }
    echo '</div>'."\n";
}


### WP-Server Sub Navigation
function serverinfo_subnavi($display = true) {
    $output = '<p style="text-align: center">';
    $output .= '<a href="#DisplayGeneral" onclick="toggle_general(); return false;">'.__('Display General Overview', 'wp-serverinfo').'</a>';
    $output .= ' - <a href="#DisplayPHP" onclick="toggle_php(); return false;">'.__('Display PHP Information', 'wp-serverinfo').'</a>';
    $output .= ' - <a href="#DisplayMYSQL" onclick="toggle_mysql(); return false;">'.__('Display MYSQL Information', 'wp-serverinfo').'</a>';
    if(class_exists('Memcache')) {
        $output .= ' - <a href="#Displaymemcached" onclick="toggle_memcached(); return false;">'.__('Display memcached Information', 'wp-serverinfo').'</a>';
    }
    $output .= '</p>';
    if($display) {
        echo $output;
    } else {
        return $output;
    }
}


### Function: Format Bytes Into TiB/GiB/MiB/KiB/Bytes
if(!function_exists('format_filesize')) {
    function format_filesize($rawSize) {
        if($rawSize / 1099511627776 > 1) {
            return number_format_i18n($rawSize/1099511627776, 1).' '.__('TiB', 'wp-serverinfo');
        } elseif($rawSize / 1073741824 > 1) {
            return number_format_i18n($rawSize/1073741824, 1).' '.__('GiB', 'wp-serverinfo');
        } elseif($rawSize / 1048576 > 1) {
            return number_format_i18n($rawSize/1048576, 1).' '.__('MiB', 'wp-serverinfo');
        } elseif($rawSize / 1024 > 1) {
            return number_format_i18n($rawSize/1024, 1).' '.__('KiB', 'wp-serverinfo');
        } elseif($rawSize > 1) {
            return number_format_i18n($rawSize, 0).' '.__('bytes', 'wp-serverinfo');
        } else {
            return __('unknown', 'wp-serverinfo');
        }
    }
}

### Function: Convert PHP Size Format to Localized
function format_php_size($size) {
    if (!is_numeric($size)) {
        if (strpos($size, 'M') !== false) {
            $size = intval($size)*1024*1024;
        } elseif (strpos($size, 'K') !== false) {
            $size = intval($size)*1024;
        } elseif (strpos($size, 'G') !== false) {
            $size = intval($size)*1024*1024*1024;
        }
    }
    return is_numeric($size) ? format_filesize($size) : $size;
}

### Function: Get PHP Short Tag
if(!function_exists('get_php_short_tag')) {
    function get_php_short_tag() {
        if(ini_get('short_open_tag')) {
            $short_tag = __('On', 'wp-serverinfo');
        } else {
            $short_tag = __('Off', 'wp-serverinfo');
        }
        return $short_tag;
    }
}


### Function: Get PHP Max Upload Size
if(!function_exists('get_php_upload_max')) {
    function get_php_upload_max() {
        if(ini_get('upload_max_filesize')) {
            $upload_max = ini_get('upload_max_filesize');
        } else {
            $upload_max = __('N/A', 'wp-serverinfo');
        }
        return $upload_max;
    }
}


### Function: Get PHP Max Post Size
if(!function_exists('get_php_post_max')) {
    function get_php_post_max() {
        if(ini_get('post_max_size')) {
            $post_max = ini_get('post_max_size');
        } else {
            $post_max = __('N/A', 'wp-serverinfo');
        }
        return $post_max;
    }
}


### Function: PHP Maximum Execution Time
if(!function_exists('get_php_max_execution')) {
    function get_php_max_execution() {
        if(ini_get('max_execution_time')) {
            $max_execute = ini_get('max_execution_time');
        } else {
            $max_execute = __('N/A', 'wp-serverinfo');
        }
        return $max_execute;
    }
}


### Function: PHP Memory Limit
if(!function_exists('get_php_memory_limit')) {
    function get_php_memory_limit() {
        if(ini_get('memory_limit')) {
            $memory_limit = ini_get('memory_limit');
        } else {
            $memory_limit = __('N/A', 'wp-serverinfo');
        }
        return $memory_limit;
    }
}


### Function: Get MYSQL Version
if(!function_exists('get_mysql_version')) {
    function get_mysql_version() {
        global $wpdb;
        return $wpdb->get_var("SELECT VERSION() AS version");
    }
}


### Function: Get MYSQL Data Usage
if(!function_exists('get_mysql_data_usage')) {
    function get_mysql_data_usage() {
        global $wpdb;
        $data_usage = 0;
        $tablesstatus = $wpdb->get_results("SHOW TABLE STATUS");
        foreach($tablesstatus as  $tablestatus) {
            $data_usage += $tablestatus->Data_length;
        }

        return $data_usage;
    }
}


### Function: Get MYSQL Index Usage
if(!function_exists('get_mysql_index_usage')) {
    function get_mysql_index_usage() {
        global $wpdb;
        $index_usage = 0;
        $tablesstatus = $wpdb->get_results("SHOW TABLE STATUS");
        foreach($tablesstatus as  $tablestatus) {
            $index_usage +=  $tablestatus->Index_length;
        }

        return $index_usage;
    }
}


### Function: Get MYSQL Max Allowed Packet
if(!function_exists('get_mysql_max_allowed_packet')) {
    function get_mysql_max_allowed_packet() {
        global $wpdb;
        $packet_max_query = $wpdb->get_row("SHOW VARIABLES LIKE 'max_allowed_packet'");

        return $packet_max_query->Value;
    }
}


### Function:Get MYSQL Max Allowed Connections
if(!function_exists('get_mysql_max_allowed_connections')) {
    function get_mysql_max_allowed_connections() {
        global $wpdb;
        $connection_max_query = $wpdb->get_row("SHOW VARIABLES LIKE 'max_connections'");

        return $connection_max_query->Value;
    }
}

### Function:Get MYSQL Query Cache Size
if(!function_exists('get_mysql_query_cache_size')) {
    function get_mysql_query_cache_size() {
        global $wpdb;
        $query_cache_size_query = $wpdb->get_row( "SHOW VARIABLES LIKE 'query_cache_size'" );

        return $query_cache_size_query->Value;
    }
}


### Function: Get GD Version
if(!function_exists('get_gd_version')) {
    function get_gd_version() {
        if (function_exists('gd_info')) {
            $gd = gd_info();
            $gd = $gd["GD Version"];
        } else {
            ob_start();
            phpinfo(8);
            $phpinfo = ob_get_contents();
            ob_end_clean();
            $phpinfo = strip_tags($phpinfo);
            $phpinfo = stristr($phpinfo,"gd version");
            $phpinfo = stristr($phpinfo,"version");
            $gd = substr($phpinfo,0,strpos($phpinfo,"\n"));
        }
        if(empty($gd)) {
            $gd = __('N/A', 'wp-serverinfo');
        }
        return $gd;
    }
}


### Function: Get The Server Load
if(!function_exists('get_serverload')) {
    function get_serverload() {
        $server_load = '';
        if(PHP_OS != 'WINNT' && PHP_OS != 'WIN32') {
            if(@file_exists('/proc/loadavg') ) {
                if ($fh = @fopen( '/proc/loadavg', 'r' )) {
                    $data = @fread( $fh, 6 );
                    @fclose( $fh );
                    $load_avg = explode( " ", $data );
                    $server_load = trim($load_avg[0]);
                }
            } else {
                $data = @system('uptime');
                preg_match('/(.*):{1}(.*)/', $data, $matches);
                $load_arr = explode(',', $matches[2]);
                $server_load = trim($load_arr[0]);
            }
        }
        if(empty($server_load)) {
            $server_load = __('N/A', 'wp-serverinfo');
        }
        return $server_load;
    }
}


### Function: Register ServerInfo Dashboard Widget
add_action('wp_dashboard_setup', 'serverinfo_register_dashboard_widget');
function serverinfo_register_dashboard_widget() {
    if(current_user_can('manage_options')) {
        wp_add_dashboard_widget('dashboard_serverinfo', __('Server Information', 'wp-serverinfo'), 'wp_dashboard_serverinfo');
    }
}


### Function: Print ServerInfo Dashboard Widget
function wp_dashboard_serverinfo() {
    if( is_rtl() ) {
        echo '<style type="text/css"> #wp-serverinfo ul { padding-left: 15px !important; } </style>';
        echo '<div id="wp-serverinfo" style="direction: ltr; text-align: left;">';
    } else {
        echo '<div id="wp-serverinfo">';
    }
    echo '<p><strong>'.__('General', 'wp-serverinfo').'</strong></p>';
    echo '<ul>';
    echo '<li>'. __('OS', 'wp-serverinfo').': <strong>'.PHP_OS.'</strong></li>';
    echo '<li>'. __('Server', 'wp-serverinfo').': <strong>'.$_SERVER["SERVER_SOFTWARE"].'</strong></li>';
    echo '<li>'. __('Hostname', 'wp-serverinfo').': <strong>'.$_SERVER['SERVER_NAME'].'</strong></li>';
    echo '<li>'. __('IP:Port', 'wp-serverinfo').': <strong>'.$_SERVER['SERVER_ADDR'].':'.$_SERVER['SERVER_PORT'].'</strong></li>';
    echo '<li>'. __('Document Root', 'wp-serverinfo').': <strong>'.$_SERVER['DOCUMENT_ROOT'].'</strong></li>';
    echo '</ul>';
    echo '<p><strong>PHP</strong></p>';
    echo '<ul>';
    echo '<li>v<strong>'.PHP_VERSION.'</strong></li>';
    echo '<li>GD: <strong>'.get_gd_version().'</strong></li>';
    echo '<li>'. __('Memory Limit', 'wp-serverinfo').': <strong>'.format_php_size(get_php_memory_limit()).'</strong></li>';
    echo '<li>'. __('Max Script Execute Time', 'wp-serverinfo').': <strong>'.get_php_max_execution().'s</strong></li>';
    echo '<li>'. __('Max Post Size', 'wp-serverinfo').': <strong>'. format_php_size(get_php_post_max()).'</strong></li>';
    echo '<li>'. __('Max Upload Size', 'wp-serverinfo').': <strong>'.format_php_size(get_php_upload_max()).'</strong></li>';
    echo '</ul>';
    echo '<p><strong>MYSQL</strong></p>';
    echo '<ul>';
    echo '<li>v<strong>'.get_mysql_version().'</strong></li>';
    echo '<li>'. __('Maximum No. Connections', 'wp-serverinfo').': <strong>'.number_format_i18n(get_mysql_max_allowed_connections(), 0).'</strong></li>';
    echo '<li>'. __('Maximum Packet Size', 'wp-serverinfo').': <strong>'.format_filesize(get_mysql_max_allowed_packet()).'</strong></li>';
    echo '<li>'. __('Data Disk Usage', 'wp-serverinfo').': <strong>'.format_filesize(get_mysql_data_usage()).'</strong></li>';
    echo '<li>'. __('Index Disk Usage', 'wp-serverinfo').': <strong>'.format_filesize(get_mysql_index_usage()).'</strong></li>';
    echo '</ul>';
    echo '<p class="textright"><a href="'.admin_url('index.php?page=wp-serverinfo/wp-serverinfo.php').'" class="button">'.__('View all', 'wp-serverinfo').'</a></p>';
    echo '</div>';
}
