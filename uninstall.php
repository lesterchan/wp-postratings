<?php
/**
 * WP-PostRatings uninstall.php
 *
 * @package wp-postratings
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-postratings-options.php';

/**
 * Remove the plugin's options, table, capability and post meta for one site.
 *
 * @return void
 */
function postratings_uninstall_site() {
	global $wpdb;

	foreach ( Postratings_Options::all_option_names() as $option_name ) {
		delete_option( $option_name );
	}

	$table = $wpdb->prefix . 'ratings';

	$wpdb->query( "DROP TABLE IF EXISTS `$table`" );

	foreach ( array( 'ratings_users', 'ratings_score', 'ratings_average' ) as $meta_key ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE meta_key = %s", $meta_key ) );
	}

	$role = get_role( 'administrator' );

	if ( $role instanceof WP_Role ) {
		$role->remove_cap( 'manage_ratings' );
	}
}

if ( is_multisite() ) {
	// 'number' => 0 lifts WP_Site_Query's default cap of 100, which would
	// otherwise leave the options and tables behind on every site past the
	// hundredth while uninstall still reported success.
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		postratings_uninstall_site();
		restore_current_blog();
	}
} else {
	postratings_uninstall_site();
}
