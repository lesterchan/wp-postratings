<?php
/**
 * WP-PostRatings Mutex.
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

function ratings_acquire_lock( $post_id ) {
	$fp = fopen( ratings_lock_file( $post_id ), 'w+' );

	if ( ! flock( $fp, LOCK_EX | LOCK_NB ) ) {
		return false;
	}

	ftruncate( $fp, 0 );
	fwrite( $fp, microtime( true ) );

	return $fp;
}

function ratings_release_lock( $fp, $post_id ) {
	if ( is_resource( $fp ) ) {
		fflush( $fp );
		flock( $fp, LOCK_UN );
		fclose( $fp );
		unlink( ratings_lock_file( $post_id ) );

		return true;
	}

	return false;
}

function ratings_lock_file( $post_id ) {
	return apply_filters( 'wp_postratings_lock_file', get_temp_dir() . '/wp-postratings-' . $post_id . '.lock', $post_id );
}