<?php
/**
 * Uninstall WP-ServerInfo.
 *
 * Runs with the plugin inactive, so nothing here may depend on the plugin's
 * own classes or functions being loaded.
 *
 * WP-ServerInfo stores no options, no tables and no cron events -- everything
 * it displays is read live from the host. What it does leave behind is the
 * per-user dashboard state WordPress writes on its behalf: closing, hiding or
 * reordering the Server Information widget records its id in user meta, and
 * that meta survives the plugin being deleted.
 *
 * @package WP-ServerInfo
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

/**
 * The dashboard widget id.
 *
 * Must match the id WP_ServerInfo_Dashboard::register_widget() passes to
 * wp_add_dashboard_widget(); that class is not loaded during uninstall.
 */
define( 'WP_SERVERINFO_WIDGET_ID', 'dashboard_serverinfo' );

/**
 * Remove the widget id from a user's dashboard meta.
 *
 * @param int $user_id User ID.
 * @return void
 */
function wp_serverinfo_clean_user_dashboard_meta( $user_id ) {
	/*
	 * closedpostboxes_ and metaboxhidden_ hold a flat array of widget ids.
	 * meta-box-order_ holds one comma-separated id string per column, so it
	 * needs splitting rather than an in_array() check -- deleting the whole
	 * key instead would throw away the user's ordering of the core widgets.
	 */
	foreach ( array( 'closedpostboxes_dashboard', 'metaboxhidden_dashboard' ) as $meta_key ) {
		$value = get_user_meta( $user_id, $meta_key, true );

		if ( ! is_array( $value ) || ! in_array( WP_SERVERINFO_WIDGET_ID, $value, true ) ) {
			continue;
		}

		$value = array_values( array_diff( $value, array( WP_SERVERINFO_WIDGET_ID ) ) );

		if ( empty( $value ) ) {
			delete_user_meta( $user_id, $meta_key );
		} else {
			update_user_meta( $user_id, $meta_key, $value );
		}
	}

	$order = get_user_meta( $user_id, 'meta-box-order_dashboard', true );

	if ( ! is_array( $order ) ) {
		return;
	}

	$changed = false;

	foreach ( $order as $column => $ids ) {
		if ( ! is_string( $ids ) || '' === $ids ) {
			continue;
		}

		$kept = array_filter(
			explode( ',', $ids ),
			function ( $id ) {
				return WP_SERVERINFO_WIDGET_ID !== $id;
			}
		);

		$kept = implode( ',', $kept );

		if ( $kept !== $ids ) {
			$order[ $column ] = $kept;
			$changed          = true;
		}
	}

	if ( $changed ) {
		update_user_meta( $user_id, 'meta-box-order_dashboard', $order );
	}
}

/**
 * Clean up the current site.
 *
 * @return void
 */
function wp_serverinfo_uninstall_site() {
	$meta_keys = array(
		'closedpostboxes_dashboard',
		'metaboxhidden_dashboard',
		'meta-box-order_dashboard',
	);

	$user_ids = array();

	foreach ( $meta_keys as $meta_key ) {
		/*
		 * Query by meta key so this touches only users who actually have
		 * dashboard state, rather than every user on the site.
		 *
		 * No 'number' argument: unlike WP_Site_Query, WP_User_Query does not
		 * default to a cap, so omitting it returns every match. Passing
		 * 'number' => 0 here would return nothing.
		 */
		$found = get_users(
			array(
				'fields'       => 'ids',
				'meta_key'     => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one-time uninstall; this is how we avoid walking every user.
				'meta_compare' => 'EXISTS',
			)
		);

		$user_ids = array_merge( $user_ids, $found );
	}

	foreach ( array_unique( $user_ids ) as $user_id ) {
		wp_serverinfo_clean_user_dashboard_meta( (int) $user_id );
	}
}

if ( is_multisite() ) {
	/*
	 * 'number' => 0 lifts WP_Site_Query's default cap of 100, which would
	 * otherwise leave the meta behind on every site past the hundredth while
	 * still reporting a successful uninstall. 'fields' => 'ids' avoids
	 * hydrating WP_Site objects the loop never looks at.
	 */
	$wp_serverinfo_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $wp_serverinfo_site_ids as $wp_serverinfo_site_id ) {
		// switch_to_blog() pushes onto a stack, so the restore belongs inside
		// the loop -- restoring once at the end leaves the stack unwound by one.
		switch_to_blog( (int) $wp_serverinfo_site_id );

		wp_serverinfo_uninstall_site();

		restore_current_blog();
	}
} else {
	wp_serverinfo_uninstall_site();
}
