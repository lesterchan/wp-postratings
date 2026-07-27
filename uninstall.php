<?php
/*
 * Uninstall plugin
 */
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit ();

$option_names = array(
	'postratings_image'
	, 'postratings_max'
	, 'postratings_template_vote'
	, 'postratings_template_text'
	, 'postratings_template_none'
	, 'postratings_logging_method'
	, 'postratings_allowtorate'
	, 'postratings_ratingstext'
	, 'postratings_template_highestrated'
	, 'postratings_ajax_style'
	, 'widget_ratings_highest_rated'
	, 'widget_ratings_most_rated'
	, 'postratings_customrating'
	, 'postratings_ratingsvalue'
	, 'postratings_template_permission'
	, 'postratings_template_mostrated'
	, 'postratings_options'
	, 'widget_ratings'
	, 'widget_ratings-widget'
);


if ( is_multisite() ) {
	// 'number' => 0 lifts WP_SITE_Query's default cap of 100, which would
	// otherwise leave the options and tables behind on every site past the
	// hundredth while still reporting success.
	$site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		plugin_uninstalled( $option_names );
		restore_current_blog();
	}
} else {
	plugin_uninstalled( $option_names );
}

/**
 * Delete the plugin's options, table and post meta for the current site.
 *
 * @param array $option_names Option rows to remove.
 *
 * @return void
 */
function plugin_uninstalled( $option_names ) {
	global $wpdb;

	foreach ( $option_names as $option_name ) {
		delete_option( $option_name );
	}

	$table_names = array( 'ratings' );
	foreach ( $table_names as $table_name ) {
		$table = $wpdb->prefix . $table_name;
		$wpdb->query( "DROP TABLE IF EXISTS `$table`" );
	}

	$post_meta_names = array( 'ratings_users', 'ratings_score', 'ratings_average' );
	foreach ( $post_meta_names as $post_meta_name ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE meta_key = %s", $post_meta_name ) );
	}
}
