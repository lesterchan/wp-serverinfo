<?php
/**
 * Uninstall WP-ServerInfo.
 *
 * Runs with the plugin inactive, so nothing here may depend on the plugin's own
 * classes or constants being loaded. The option name and the widget id are
 * therefore spelled out rather than read from WP_SERVERINFO_WIDGET_ID or a
 * class constant; tests/test-metadata.php asserts that the two copies still
 * agree.
 *
 * There are two kinds of leftover. One is a version row that only early
 * pre-release 3.0.0 builds ever wrote -- the released plugin stores nothing --
 * removed here so those installs are cleaned up too. The other is per-user
 * dashboard state WordPress writes on its behalf: closing, hiding or reordering
 * the Server Information widget records its id in user meta, and that meta
 * survives the plugin being deleted.
 *
 * @package WP-ServerInfo
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove the widget id from a user's dashboard meta.
 *
 * @param int $user_id User ID.
 * @return void
 */
function wp_serverinfo_clean_user_dashboard_meta( $user_id ) {
	// Must match WP_SERVERINFO_WIDGET_ID, which is not loaded during uninstall.
	$widget_id = 'dashboard_serverinfo';

	/*
	 * closedpostboxes_ and metaboxhidden_ hold a flat array of widget ids.
	 * meta-box-order_ holds one comma-separated id string per column, so it
	 * needs splitting rather than an in_array() check -- deleting the whole
	 * key instead would throw away the user's ordering of the core widgets.
	 */
	foreach ( array( 'closedpostboxes_dashboard', 'metaboxhidden_dashboard' ) as $meta_key ) {
		$value = get_user_meta( $user_id, $meta_key, true );

		if ( ! is_array( $value ) || ! in_array( $widget_id, $value, true ) ) {
			continue;
		}

		$value = array_values( array_diff( $value, array( $widget_id ) ) );

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
			function ( $id ) use ( $widget_id ) {
				return $widget_id !== $id;
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
 * Delete the plugin's rows and dashboard state for the current site.
 *
 * One option row only: WP-ServerInfo has nothing a site owner can configure and
 * so stores no settings row at all (STANDARDS.md 2.1). If it ever grows one, it
 * gets deleted here too -- tests/test-metadata.php asserts over wp_options with
 * a LIKE rather than naming rows, so a forgotten row fails the suite.
 *
 * @return void
 */
function wp_serverinfo_uninstall_site() {
	delete_option( 'wp_serverinfo_version' );

	/*
	 * The users are walked in pages rather than asked for by meta key. Querying
	 * closedpostboxes_dashboard directly would touch only the users who actually
	 * have dashboard state, but that is an unindexed meta_key lookup -- the one
	 * query WordPress asks plugins not to write, and there is no way to spell it
	 * that is not that query. Paging keeps this to a bounded number of IDs in
	 * memory on a site with a large user table, which is what the meta filter
	 * was really buying.
	 */
	$per_page = 500;
	$paged    = 1;

	do {
		$user_ids = get_users(
			array(
				'fields' => 'ids',
				'number' => $per_page,
				'paged'  => $paged,
			)
		);

		$found = count( $user_ids );

		foreach ( $user_ids as $user_id ) {
			wp_serverinfo_clean_user_dashboard_meta( (int) $user_id );
		}

		++$paged;
	} while ( $found === $per_page );
}

if ( is_multisite() ) {
	/*
	 * 'number' => 0 lifts WP_Site_Query's default cap of 100, which would
	 * otherwise leave the rows behind on every site past the hundredth while
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
		// the loop -- restoring once at the end leaves it unwound by one.
		switch_to_blog( (int) $wp_serverinfo_site_id );

		wp_serverinfo_uninstall_site();

		restore_current_blog();
	}
} else {
	wp_serverinfo_uninstall_site();
}
