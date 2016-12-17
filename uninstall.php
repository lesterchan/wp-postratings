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
	$ms_sites = function_exists( 'get_sites' ) ? get_sites() : wp_get_sites();

	if ( 0 < count( $ms_sites ) ) {
		foreach ( $ms_sites as $ms_site ) {
			$blog_id = class_exists( 'WP_Site' ) ? $ms_site->blog_id : $ms_site['blog_id'];
			switch_to_blog( $blog_id );
			if ( count( $option_names ) > 0 ) {
				foreach ( $option_names as $option_name ) {
					delete_option( $option_name );
					plugin_uninstalled();
					restore_current_blog();
				}
			}
		}
	}
} else {
	if ( count( $option_names ) > 0 ) {
		foreach ( $option_names as $option_name ) {
			delete_option( $option_name );
			plugin_uninstalled();
		}
	}
}

/**
 * Delete plugin table when uninstalled
 *
 * @access public
 * @return void
 */
function plugin_uninstalled() {
	global $wpdb;

	$table_names = array( 'ratings' );
	if( sizeof( $table_names ) > 0 ) {
		foreach( $table_names as $table_name ) {
			$table = $wpdb->prefix . $table_name;
			$wpdb->query( "DROP TABLE IF EXISTS $table" );
		}
	}

	$post_meta_names = array( 'ratings_users', 'ratings_score', 'ratings_average' );
	if( sizeof( $post_meta_names ) > 0 ) {
		foreach( $post_meta_names as $post_meta_name ) {
			$wpdb->query( "DELETE FROM $wpdb->postmeta WHERE meta_key = '$post_meta_name'" );
		}
	}
}
