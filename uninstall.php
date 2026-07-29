<?php
/**
 * WP-PostRatings uninstall.php
 *
 * @package wp-postratings
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-wp-postratings-options.php';
require_once __DIR__ . '/includes/class-wp-postratings-settings.php';

/**
 * Remove the plugin's options, table, capability and post meta for one site.
 *
 * @return void
 */
function wp_postratings_uninstall_site() {
	global $wpdb;

	foreach ( WP_PostRatings_Options::all_option_names() as $option_name ) {
		delete_option( $option_name );
	}

	// $wpdb->ratings is registered by the bootstrap class, which uninstall.php
	// never loads, so the name is built from the prefix property instead.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Dropping the plugin's own table is the whole job of this file and core has no API for it.
	$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}ratings`" );

	foreach ( array( 'ratings_users', 'ratings_score', 'ratings_average' ) as $meta_key ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- delete_post_meta() wants a post id; this clears the key across every post in one statement.
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE meta_key = %s", $meta_key ) );
	}

	$role = get_role( 'administrator' );

	if ( $role instanceof WP_Role ) {
		$role->remove_cap( WP_PostRatings_Settings::CAPABILITY );
	}
}

if ( is_multisite() ) {
	// 'number' => 0 lifts WP_Site_Query's default cap of 100, which would
	// otherwise leave the options and tables behind on every site past the
	// hundredth while uninstall still reported success.
	$wp_postratings_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $wp_postratings_site_ids as $wp_postratings_site_id ) {
		switch_to_blog( (int) $wp_postratings_site_id );
		wp_postratings_uninstall_site();
		restore_current_blog();
	}
} else {
	wp_postratings_uninstall_site();
}
