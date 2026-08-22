<?php
/**
 * WP-PostRatings deprecated.php
 *
 * Internal helpers that moved into classes in 2.0.0. They were never part of
 * the documented API, but they were global functions in a plugin this old, so
 * something out there calls them. Each one forwards to its replacement.
 *
 * The public template tags are not here -- they still live in template-tags.php
 * and are not deprecated.
 *
 * @package WP-PostRatings
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether a rating cookie is set for a post.
 *
 * @deprecated 2.0.0 Use WP_PostRatings_Rating::rated_by_cookie().
 *
 * @param int $post_id Post id.
 *
 * @return bool
 */
function check_rated_cookie( $post_id ) {
	_deprecated_function( __FUNCTION__, '2.0.0', 'WP_PostRatings_Rating::rated_by_cookie()' );

	return WP_PostRatings_Rating::rated_by_cookie( $post_id );
}

/**
 * Whether the visitor's IP has rated a post.
 *
 * @deprecated 2.0.0 Use WP_PostRatings_Rating::rated_by_ip().
 *
 * @param int $post_id Post id.
 *
 * @return int
 */
function check_rated_ip( $post_id ) {
	_deprecated_function( __FUNCTION__, '2.0.0', 'WP_PostRatings_Rating::rated_by_ip()' );

	return WP_PostRatings_Rating::rated_by_ip( $post_id );
}

/**
 * Whether the logged-in user has rated a post.
 *
 * @deprecated 2.0.0 Use WP_PostRatings_Rating::rated_by_username().
 *
 * @param int $post_id Post id.
 *
 * @return int
 */
function check_rated_username( $post_id ) {
	_deprecated_function( __FUNCTION__, '2.0.0', 'WP_PostRatings_Rating::rated_by_username()' );

	return WP_PostRatings_Rating::rated_by_username( $post_id );
}

/**
 * The visitor's address, before hashing.
 *
 * @deprecated 2.0.0 Use WP_PostRatings_Rating::get_raw_ip().
 *
 * @return string
 */
function ratings_get_raw_ipaddress() {
	_deprecated_function( __FUNCTION__, '2.0.0', 'WP_PostRatings_Rating::get_raw_ip()' );

	return WP_PostRatings_Rating::get_raw_ip();
}

/**
 * The visitor's address as stored: hashed.
 *
 * @deprecated 2.0.0 Use WP_PostRatings_Rating::get_ip().
 *
 * @return string
 */
function ratings_get_ipaddress() {
	_deprecated_function( __FUNCTION__, '2.0.0', 'WP_PostRatings_Rating::get_ip()' );

	return WP_PostRatings_Rating::get_ip();
}

/**
 * The visitor's host name.
 *
 * @deprecated 2.0.0 Use WP_PostRatings_Rating::get_hostname().
 *
 * @return string
 */
function ratings_get_hostname() {
	_deprecated_function( __FUNCTION__, '2.0.0', 'WP_PostRatings_Rating::get_hostname()' );

	return WP_PostRatings_Rating::get_hostname();
}

/**
 * Take the advisory lock for a post.
 *
 * @deprecated 2.0.0 Use WP_PostRatings_Rating::acquire_lock().
 *
 * @param int $post_id Post id.
 *
 * @return resource|false
 */
function ratings_acquire_lock( $post_id ) {
	_deprecated_function( __FUNCTION__, '2.0.0', 'WP_PostRatings_Rating::acquire_lock()' );

	return WP_PostRatings_Rating::acquire_lock( $post_id );
}

/**
 * Release the advisory lock for a post.
 *
 * @deprecated 2.0.0 Use WP_PostRatings_Rating::release_lock().
 *
 * @param resource $handle  Lock handle.
 * @param int      $post_id Post id.
 *
 * @return bool
 */
function ratings_release_lock( $handle, $post_id ) {
	_deprecated_function( __FUNCTION__, '2.0.0', 'WP_PostRatings_Rating::release_lock()' );

	return WP_PostRatings_Rating::release_lock( $handle, $post_id );
}

/**
 * Path of the advisory lock file for a post.
 *
 * @deprecated 2.0.0 Use WP_PostRatings_Rating::lock_file().
 *
 * @param int $post_id Post id.
 *
 * @return string
 */
function ratings_lock_file( $post_id ) {
	_deprecated_function( __FUNCTION__, '2.0.0', 'WP_PostRatings_Rating::lock_file()' );

	return WP_PostRatings_Rating::lock_file( $post_id );
}

/**
 * The read-only rating strip.
 *
 * @deprecated 2.0.0 Use WP_PostRatings_Template::ratings_images().
 *
 * @param int    $ratings_custom Whether the set is custom.
 * @param int    $ratings_max    Steps on the scale.
 * @param float  $post_rating    Rating to display.
 * @param string $ratings_image  Image set.
 * @param string $image_alt      Alt text.
 * @param int    $insert_half    Half image position.
 *
 * @return string
 */
function get_ratings_images( $ratings_custom, $ratings_max, $post_rating, $ratings_image, $image_alt, $insert_half ) {
	_deprecated_function( __FUNCTION__, '2.0.0', 'WP_PostRatings_Template::ratings_images()' );

	return WP_PostRatings_Template::ratings_images( $ratings_custom, $ratings_max, $post_rating, $ratings_image, $image_alt, $insert_half );
}

/**
 * The clickable rating strip.
 *
 * @deprecated 2.0.0 Use WP_PostRatings_Template::ratings_images_vote().
 *
 * @param int    $post_id        Post id.
 * @param int    $ratings_custom Whether the set is custom.
 * @param int    $ratings_max    Steps on the scale.
 * @param float  $post_rating    Current rating.
 * @param string $ratings_image  Image set.
 * @param string $image_alt      Alt text.
 * @param int    $insert_half    Half image position.
 * @param array  $ratings_texts  Per-rating labels.
 *
 * @return string
 */
function get_ratings_images_vote( $post_id, $ratings_custom, $ratings_max, $post_rating, $ratings_image, $image_alt, $insert_half, $ratings_texts ) {
	_deprecated_function( __FUNCTION__, '2.0.0', 'WP_PostRatings_Template::ratings_images_vote()' );

	return WP_PostRatings_Template::ratings_images_vote( $post_id, $ratings_custom, $ratings_max, $post_rating, $ratings_image, $image_alt, $insert_half, $ratings_texts );
}

/**
 * The rating strip beside a comment author.
 *
 * @deprecated 2.0.0 Use WP_PostRatings_Template::ratings_images_comment_author().
 *
 * @param int    $ratings_custom        Whether the set is custom.
 * @param int    $ratings_max           Steps on the scale.
 * @param int    $comment_author_rating Rating given.
 * @param string $ratings_image         Image set.
 * @param string $image_alt             Alt text.
 *
 * @return string
 */
function get_ratings_images_comment_author( $ratings_custom, $ratings_max, $comment_author_rating, $ratings_image, $image_alt ) {
	_deprecated_function( __FUNCTION__, '2.0.0', 'WP_PostRatings_Template::ratings_images_comment_author()' );

	return WP_PostRatings_Template::ratings_images_comment_author( $ratings_custom, $ratings_max, $comment_author_rating, $ratings_image, $image_alt );
}

/**
 * The excerpt used by %POST_EXCERPT%.
 *
 * @deprecated 2.0.0 Use WP_PostRatings_Template::post_excerpt().
 *
 * @param int    $post_id      Post id.
 * @param string $post_excerpt Stored excerpt.
 * @param string $post_content Post content.
 *
 * @return string
 */
function ratings_post_excerpt( $post_id, $post_excerpt, $post_content ) {
	_deprecated_function( __FUNCTION__, '2.0.0', 'WP_PostRatings_Template::post_excerpt()' );

	return WP_PostRatings_Template::post_excerpt( $post_id, $post_excerpt, $post_content );
}

if ( ! function_exists( 'snippet_text' ) ) {
	/**
	 * Truncate text to a character count.
	 *
	 * @deprecated 2.0.0 Use WP_PostRatings_Template::snippet().
	 *
	 * @param string $text   Text to shorten.
	 * @param int    $length Maximum length.
	 *
	 * @return string
	 */
	function snippet_text( $text, $length = 0 ) {
		_deprecated_function( __FUNCTION__, '2.0.0', 'WP_PostRatings_Template::snippet()' );

		return WP_PostRatings_Template::snippet( $text, $length );
	}
}

/**
 * Append the rating widget to post content.
 *
 * @deprecated 2.0.0 Use the_ratings() or the [ratings] shortcode.
 *
 * @param string $content Post content.
 *
 * @return string
 */
function add_ratings_to_content( $content ) {
	_deprecated_function( __FUNCTION__, '2.0.0', 'the_ratings()' );

	if ( is_feed() ) {
		return $content;
	}

	return $content . the_ratings( 'div', 0, false );
}
