<?php
/**
 * WP-PostRatings class-wp-postratings-comments.php
 *
 * @package WP-PostRatings
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shows the rating a comment author gave the post they commented on.
 *
 * @since 2.0.0
 */
class WP_PostRatings_Comments {

	/**
	 * Ratings for the post currently in the loop, keyed by username and by
	 * hashed IP.
	 *
	 * @var array
	 */
	private static $ratings = array();

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'loop_start', array( __CLASS__, 'collect' ) );
		add_filter( 'comment_text', array( __CLASS__, 'append_to_comment' ), 10, 2 );
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

		/**
		 * Filters whether to append each comment author's rating to their comment.
		 *
		 * Checked here as well as at display time, because the map below is only
		 * ever read on behalf of that display: with the feature off -- the
		 * default -- the query would fetch every rating the post has, on every
		 * loop, for nothing.
		 *
		 * @since 1.83
		 *
		 * @param bool $display Whether to display them. Off by default.
		 */
		if ( ! apply_filters( 'wp_postratings_display_comment_author_ratings', false ) ) {
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

		// The unprefixed $comment_authors_ratings global this used to keep in
		// step is gone with 2.0.0. It was an implementation detail of the
		// procedural version, never a documented one, and a global variable
		// named after nothing in particular is exactly what §2.4 forbids.
		// WP_PostRatings_Comments::rating_for() answers the same question.
	}

	/**
	 * Look up one comment author's rating.
	 *
	 * The map is keyed by the *hashed* IP, which is what the log table stores.
	 * Looking it up with a raw address never matched, so this fallback had been
	 * dead since ratings started being hashed.
	 *
	 * @param string          $comment_author Author name.
	 * @param WP_Comment|null $comment        Comment being displayed, or null for the current one.
	 *
	 * @return int
	 */
	public static function rating_for( $comment_author, $comment = null ) {
		$check_method = (int) WP_PostRatings_Options::get( 'check_method' );

		$rating = isset( self::$ratings[ $comment_author ] ) ? (int) self::$ratings[ $comment_author ] : 0;

		// Checking by username means the IP was never matched against.
		if ( 4 === $check_method || 0 !== $rating ) {
			return $rating;
		}

		// get_comment_author_IP() falls back to the current comment, and outside
		// a comment loop there is nothing to fall back to: it errors on PHP 8. A
		// block theme never sets that global, so the comment is passed in.
		$comment = $comment ? $comment : ( isset( $GLOBALS['comment'] ) ? $GLOBALS['comment'] : null );

		if ( empty( $comment ) ) {
			return 0;
		}

		$comment_author_ip = get_comment_author_IP( $comment );

		if ( empty( $comment_author_ip ) ) {
			return 0;
		}

		$hashed = wp_hash( $comment_author_ip );

		return isset( self::$ratings[ $hashed ] ) ? (int) self::$ratings[ $hashed ] : 0;
	}

	/**
	 * The rating images for a comment author.
	 *
	 * @param string          $comment_author_specific Author name, or '' for the current comment.
	 * @param WP_Comment|null $comment                 Comment being displayed, or null for the current one.
	 *
	 * @return string
	 */
	public static function author_ratings( $comment_author_specific = '', $comment = null ) {
		if ( 'comment' !== get_comment_type( $comment ) ) {
			return '';
		}

		$options        = WP_PostRatings_Options::get();
		$ratings_max    = (int) $options['max'];
		$ratings_custom = (int) $options['customrating'];

		$comment_author = '' !== $comment_author_specific ? $comment_author_specific : get_comment_author( $comment );
		$rating         = self::rating_for( $comment_author, $comment );

		if ( 0 === $rating ) {
			return '';
		}

		$display_rating = $rating;

		if ( $ratings_custom && 2 === $ratings_max && $display_rating > 0 ) {
			$display_rating = '+' . $display_rating;
		}

		/* translators: 1: comment author, 2: rating they gave. */
		$image_alt = sprintf( __( '%1$s gives a rating of %2$s', 'wp-postratings' ), $comment_author, $display_rating );

		return WP_PostRatings_Template::ratings_images_comment_author( $ratings_custom, $ratings_max, $rating, $options['shape'], $image_alt );
	}

	/**
	 * Append the author's rating to their comment.
	 *
	 * Off unless a theme opts in through the filter.
	 *
	 * **The comment comes from the filter, not from a global.** A classic theme
	 * walks its comments with a loop that sets `$GLOBALS['comment']`; a block
	 * theme renders each one through the comment-template block, which passes the
	 * comment down as block context and never touches that global. Reading the
	 * global was therefore reading nothing on every default theme since 2022, and
	 * this returned the comment untouched -- the feature looked switched off. The
	 * filter has passed the comment since 2.9, so take it from there and keep the
	 * global as the fallback for anything calling this directly.
	 *
	 * @param string          $comment_text   Comment markup.
	 * @param WP_Comment|null $comment_object Comment being displayed.
	 *
	 * @return string
	 */
	public static function append_to_comment( $comment_text, $comment_object = null ) {
		/** This filter is documented in includes/class-wp-postratings-comments.php */
		if ( ! apply_filters( 'wp_postratings_display_comment_author_ratings', false ) ) {
			return $comment_text;
		}

		$comment = $comment_object ? $comment_object : ( isset( $GLOBALS['comment'] ) ? $GLOBALS['comment'] : null );

		if ( is_feed() || is_admin() || empty( $comment ) || 'comment' !== get_comment_type( $comment ) ) {
			return $comment_text;
		}

		$images = self::author_ratings( '', $comment );
		$author = get_comment_author( $comment );

		$output = '<div class="wp-postratings-comment-author">';

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
