// Display General Overview
function toggle_general() {
	jQuery('#GeneralOverview').show();
	jQuery('#PHPinfo').hide();
	jQuery('#MYSQLinfo').hide();
	jQuery('#memcachedinfo').hide();
}

// Display PHP Information
function toggle_php() {
	jQuery('#GeneralOverview').hide();
	jQuery('#PHPinfo').show();
	jQuery('#MYSQLinfo').hide();
	jQuery('#memcachedinfo').hide();
}

// Display MYSQL Information
function toggle_mysql() {
	jQuery('#GeneralOverview').hide();
	jQuery('#PHPinfo').hide();
	jQuery('#MYSQLinfo').show();
	jQuery('#memcachedinfo').hide();
}

// Display memcached Information
function toggle_memcached() {
	jQuery('#GeneralOverview').hide();
	jQuery('#PHPinfo').hide();
	jQuery('#MYSQLinfo').hide();
	jQuery('#memcachedinfo').show();
}