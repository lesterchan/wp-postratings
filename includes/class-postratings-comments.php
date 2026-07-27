<?php
/**
 * WP-PostRatings class-postratings-comments.php
 *
 * @package wp-postratings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows the rating a comment author gave the post they commented on.
 *
 * @since 2.0.0
 */
class Postratings_Comments {

	/**
	 * Ratings for the post currently in the loop, keyed by username and by
	 * hashed IP.
	 *
	 * @var array
	 */
	private static $ratings = array();

	/**
	 * Hook into the loop and the comment output.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'loop_start', array( __CLASS__, 'collect' ) );
		add_filter( 'comment_text', array( __CLASS__, 'append_to_comment' ) );
	}

	/**
	 * Load every rating recorded against the post being displayed.
	 *
	 * @return void
	 */
	public static function collect() {
		global $wpdb, $post;

		self::$ratings = array();

		if ( is_feed() || is_admin() || empty( $post->ID ) ) {
			return;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT rating_username, rating_rating, rating_ip FROM {$wpdb->ratings} WHERE rating_postid = %d",
				$post->ID
			)
		);

		if ( ! is_array( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			self::$ratings[ stripslashes( $row->rating_username ) ] = $row->rating_rating;
			self::$ratings[ $row->rating_ip ]                       = $row->rating_rating;
		}

		// Kept in sync for anything reading the old global directly.
		$GLOBALS['comment_authors_ratings'] = self::$ratings;
	}

	/**
	 * Look up one comment author's rating.
	 *
	 * The map is keyed by the *hashed* IP, which is what the log table stores.
	 * Looking it up with a raw address never matched, so this fallback had been
	 * dead since ratings started being hashed.
	 *
	 * @param string $comment_author Author name.
	 *
	 * @return int
	 */
	public static function rating_for( $comment_author ) {
		$logging_method = (int) Postratings_Options::get( 'logging_method' );

		$rating = isset( self::$ratings[ $comment_author ] ) ? (int) self::$ratings[ $comment_author ] : 0;

		// Logging by username means the IP was never recorded to match against.
		if ( 4 === $logging_method || 0 !== $rating ) {
			return $rating;
		}

		$comment_author_ip = get_comment_author_IP();

		if ( empty( $comment_author_ip ) ) {
			return 0;
		}

		$hashed = wp_hash( $comment_author_ip );

		return isset( self::$ratings[ $hashed ] ) ? (int) self::$ratings[ $hashed ] : 0;
	}

	/**
	 * The rating images for a comment author.
	 *
	 * @param string $comment_author_specific Author name, or '' for the current comment.
	 *
	 * @return string
	 */
	public static function author_ratings( $comment_author_specific = '' ) {
		if ( 'comment' !== get_comment_type() ) {
			return '';
		}

		$options        = Postratings_Options::get();
		$ratings_max    = (int) $options['max'];
		$ratings_custom = (int) $options['customrating'];

		$comment_author = '' !== $comment_author_specific ? $comment_author_specific : get_comment_author();
		$rating         = self::rating_for( $comment_author );

		if ( 0 === $rating ) {
			return '';
		}

		$display_rating = $rating;

		if ( $ratings_custom && 2 === $ratings_max && $display_rating > 0 ) {
			$display_rating = '+' . $display_rating;
		}

		/* translators: 1: comment author, 2: rating they gave. */
		$image_alt = sprintf( __( '%1$s gives a rating of %2$s', 'wp-postratings' ), $comment_author, $display_rating );

		return Postratings_Template::ratings_images_comment_author( $ratings_custom, $ratings_max, $rating, $options['image'], $image_alt );
	}

	/**
	 * Append the author's rating to their comment.
	 *
	 * Off unless a theme opts in through the filter.
	 *
	 * @param string $comment_text Comment markup.
	 *
	 * @return string
	 */
	public static function append_to_comment( $comment_text ) {
		global $comment;

		if ( ! apply_filters( 'wp_postratings_display_comment_author_ratings', false ) ) {
			return $comment_text;
		}

		if ( is_feed() || is_admin() || empty( $comment ) || 'comment' !== get_comment_type() ) {
			return $comment_text;
		}

		$images = self::author_ratings();
		$author = get_comment_author();

		$output = '<div class="post-ratings-comment-author">';

		if ( '' !== $images ) {
			/* translators: %s: comment author. */
			$output .= sprintf( esc_html__( '%s ratings for this post:', 'wp-postratings' ), esc_html( $author ) ) . ' ' . $images;
		} else {
			/* translators: %s: comment author. */
			$output .= sprintf( esc_html__( '%s did not rate this post.', 'wp-postratings' ), esc_html( $author ) );
		}

		$output .= '</div>';

		return $comment_text . $output;
	}
}
