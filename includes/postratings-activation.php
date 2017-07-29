<?php
/**
 * WP-PostRatings Activation Hooks.
 *
 * @package WordPress
 * @subpackage WP-PostRatings Plugin
 */

/**
 * Security check
 * Prevent direct access to the file.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ratings_activation( $network_wide ) {
	if ( is_multisite() && $network_wide ) {
		$ms_sites = function_exists( 'get_sites' ) ? get_sites() : wp_get_sites();

		if( 0 < count( $ms_sites ) ) {
			foreach ( $ms_sites as $ms_site ) {
				$blog_id = class_exists( 'WP_Site' ) ? $ms_site->blog_id : $ms_site['blog_id'];
				switch_to_blog( $blog_id );
				ratings_activate();
				restore_current_blog();
			}
		}
	} else {
		ratings_activate();
	}
}

function ratings_activate() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	// Create Post Ratings Table
	$create_sql = "CREATE TABLE $wpdb->ratings (".
		"rating_id INT(11) NOT NULL auto_increment,".
		"rating_postid INT(11) NOT NULL ,".
		"rating_posttitle TEXT NOT NULL,".
		"rating_rating INT(2) NOT NULL ,".
		"rating_timestamp VARCHAR(15) NOT NULL ,".
		"rating_ip VARCHAR(40) NOT NULL ,".
		"rating_host VARCHAR(200) NOT NULL,".
		"rating_username VARCHAR(50) NOT NULL,".
		"rating_userid int(10) NOT NULL default '0',".
		"PRIMARY KEY (rating_id),".
		"KEY rating_userid (rating_userid),".
		"KEY rating_postid_ip (rating_postid, rating_ip)) ".
		"$charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $create_sql );

	// Add In Options (4 Records)
	add_option('postratings_image', 'stars' );
	add_option('postratings_max', '5' );
	add_option('postratings_template_vote', '%RATINGS_IMAGES_VOTE% (<strong>%RATINGS_USERS%</strong> '.__('votes', 'wp-postratings').__(',', 'wp-postratings').' '.__('average', 'wp-postratings').': <strong>%RATINGS_AVERAGE%</strong> '.__('out of', 'wp-postratings').' %RATINGS_MAX%)<br />%RATINGS_TEXT%' );
	add_option('postratings_template_text', '%RATINGS_IMAGES% (<em><strong>%RATINGS_USERS%</strong> '.__('votes', 'wp-postratings').__(',', 'wp-postratings').' '.__('average', 'wp-postratings').': <strong>%RATINGS_AVERAGE%</strong> '.__('out of', 'wp-postratings').' %RATINGS_MAX%'.__(',', 'wp-postratings').' <strong>'.__('rated', 'wp-postratings').'</strong></em>)' );
	add_option('postratings_template_none', '%RATINGS_IMAGES_VOTE% ('.__('No Ratings Yet', 'wp-postratings').')<br />%RATINGS_TEXT%' );
	// Database Upgrade For WP-PostRatings 1.02
	add_option('postratings_logging_method', '3' );
	add_option('postratings_allowtorate', '2' );
	// Database Uprade For WP-PostRatings 1.04
	maybe_add_column($wpdb->ratings, 'rating_userid', "ALTER TABLE $wpdb->ratings ADD rating_userid INT( 10 ) NOT NULL DEFAULT '0';");
	// Database Uprade For WP-PostRatings 1.05
	add_option('postratings_ratingstext', array(__('1 Star', 'wp-postratings'), __('2 Stars', 'wp-postratings'), __('3 Stars', 'wp-postratings'), __('4 Stars', 'wp-postratings'), __('5 Stars', 'wp-postratings')) );
	add_option('postratings_template_highestrated', '<li><a href="%POST_URL%" title="%POST_TITLE%">%POST_TITLE%</a> %RATINGS_IMAGES% (%RATINGS_AVERAGE% '.__('out of', 'wp-postratings').' %RATINGS_MAX%)</li>' );
	// Database Upgrade For WP-PostRatings 1.11
	add_option('postratings_ajax_style', array('loading' => 1, 'fading' => 1) );
	// Database Upgrade For WP-PostRatings 1.20
	add_option('postratings_ratingsvalue', array(1,2,3,4,5) );
	add_option('postratings_customrating', 0 );
	add_option('postratings_template_permission', '%RATINGS_IMAGES% (<em><strong>%RATINGS_USERS%</strong> '.__('votes', 'wp-postratings').__(',', 'wp-postratings').' '.__('average', 'wp-postratings').': <strong>%RATINGS_AVERAGE%</strong> '.__('out of', 'wp-postratings').' %RATINGS_MAX%</em>)<br /><em>'.__('You need to be a registered member to rate this.', 'wp-postratings').'</em>' );
	// Database Upgrade For WP-PostRatings 1.30
	add_option('postratings_template_mostrated', '<li><a href="%POST_URL%"  title="%POST_TITLE%">%POST_TITLE%</a> - %RATINGS_USERS% '.__('votes', 'wp-postratings').'</li>' );
	// Database Upgrade For WP-PostRatings 1.50
	delete_option('widget_ratings_highest_rated');
	delete_option('widget_ratings_most_rated');

	// Index
	$index = $wpdb->get_results( "SHOW INDEX FROM $wpdb->ratings;" );
	$key_name = array();
	if( sizeof( $index ) > 0 ) {
		foreach( $index as $i ) {
			$key_name[]= $i->Key_name;
		}
	}
	if ( ! in_array( 'rating_userid', $key_name ) ) {
		$wpdb->query( "ALTER TABLE $wpdb->ratings ADD INDEX rating_userid (rating_userid);" );
	}
	if ( ! in_array( 'rating_postid_ip', $key_name ) ) {
		$wpdb->query( "ALTER TABLE $wpdb->ratings ADD INDEX rating_postid_ip (rating_postid, rating_ip);" );
	}

	// Set 'manage_ratings' Capabilities To Administrator
	$role = get_role( 'administrator' );
	$role->add_cap( 'manage_ratings' );
}
