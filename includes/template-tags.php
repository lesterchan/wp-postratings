<?php
/**
 * WP-PostRatings template-tags.php
 *
 * The plugin's public API. Themes call these directly, so the names and
 * signatures are fixed; everything here delegates to the classes.
 *
 * The stats tags keep their function_exists() guards, which have always let a
 * theme replace one wholesale.
 *
 * @package wp-postratings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Display the rating widget for a post.
 *
 * @param string $start_tag Wrapping element name.
 * @param int    $custom_id Post id, or 0 for the current post.
 * @param bool   $display   Whether to echo.
 *
 * @return string|void
 */
function the_ratings( $start_tag = 'div', $custom_id = 0, $display = true ) {
	global $id;

	if ( (int) $custom_id > 0 ) {
		$ratings_id = (int) $custom_id;
	} elseif ( 0 === $id ) {
		$ratings_id = get_the_ID();
	} elseif ( null === $id ) {
		global $post;
		$ratings_id = ! empty( $post->ID ) ? $post->ID : 0;
	} else {
		$ratings_id = $id;
	}

	$ratings_id = (int) $ratings_id;

	$ajax_style = (array) WP_PostRatings_Options::get( 'ajax_style' );
	$loading    = '';

	if ( ! empty( $ajax_style['loading'] ) ) {
		// The spinner is a CSS pseudo-element since 2.0.0, so there is no
		// loading.gif to request and it inherits the surrounding text colour.
		/**
		 * Filters the text shown while a vote is in flight.
		 *
		 * @since 1.87
		 *
		 * @param string $loading_text Loading text.
		 */
		$loading_text = apply_filters( 'wp_postratings_loading_alt', esc_html__( 'Loading...', 'wp-postratings' ) );

		// The hidden attribute rather than an inline style: the stylesheet owns
		// the rule, and the script toggles the property instead of a string.
		$loading = '<' . $start_tag . ' id="wp-postratings-' . $ratings_id . '-loading" class="wp-postratings-loading"' .
			' role="status" hidden>' . esc_html( $loading_text ) . '</' . $start_tag . '>';
	}

	$user_voted = check_rated( $ratings_id );

	$attributes = 'id="wp-postratings-' . $ratings_id . '" class="wp-postratings"';

	/** This filter is documented in includes/class-wp-postratings-template.php */
	$disable_richsnippet = apply_filters( 'wp_postratings_disable_richsnippet', false );

	if ( ! $disable_richsnippet && is_singular() && WP_PostRatings_Options::get( 'richsnippet' ) ) {
		/** This filter is documented in includes/class-wp-postratings-template.php */
		$itemtype    = apply_filters( 'wp_postratings_schema_itemtype', 'itemscope itemtype="https://schema.org/Article"' );
		$attributes .= ' ' . $itemtype;
	}

	if ( $user_voted ) {
		$output = "<$start_tag $attributes>" . the_ratings_results( $ratings_id ) . '</' . $start_tag . '>' . $loading;
	} elseif ( ! check_allowtorate() ) {
		$output = "<$start_tag $attributes>" . the_ratings_results( $ratings_id, 0, 0, 0, 1 ) . '</' . $start_tag . '>' . $loading;
	} else {
		$nonce  = wp_create_nonce( 'wp_postratings_' . $ratings_id . '-nonce' );
		$output = "<$start_tag $attributes data-nonce=\"" . esc_attr( $nonce ) . '">' . the_ratings_vote( $ratings_id ) . '</' . $start_tag . '>' . $loading;
	}

	if ( ! $display ) {
		return $output;
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered template markup, escaped as it is built.
	echo $output;
}

/**
 * The read-only rating display for a post.
 *
 * @param int   $post_id     Post id.
 * @param int   $new_user    Voter count to display instead of the stored one.
 * @param int   $new_score   Score to display instead of the stored one.
 * @param float $new_average Average to display instead of the stored one.
 * @param int   $type        1 for the "no permission" template.
 *
 * @return string
 */
function the_ratings_results( $post_id, $new_user = 0, $new_score = 0, $new_average = 0, $type = 0 ) {
	$post_ratings_data = null;

	if ( 0 !== $new_user || 0 !== $new_score || 0 !== $new_average ) {
		$post_ratings_data                  = new stdClass();
		$post_ratings_data->ratings_users   = $new_user;
		$post_ratings_data->ratings_score   = $new_score;
		$post_ratings_data->ratings_average = $new_average;
	}

	$template = 1 === $type ? WP_PostRatings_Options::template( 'permission' ) : WP_PostRatings_Options::template( 'text' );

	return expand_ratings_template( $template, $post_id, $post_ratings_data );
}

/**
 * The clickable rating display for a post.
 *
 * @param int   $post_id     Post id.
 * @param int   $new_user    Voter count to display instead of the stored one.
 * @param int   $new_score   Score to display instead of the stored one.
 * @param float $new_average Average to display instead of the stored one.
 *
 * @return string
 */
function the_ratings_vote( $post_id, $new_user = 0, $new_score = 0, $new_average = 0 ) {
	$post_ratings_data = null;

	if ( 0 !== $new_user || 0 !== $new_score || 0 !== $new_average ) {
		$post_ratings_data                  = new stdClass();
		$post_ratings_data->ratings_users   = $new_user;
		$post_ratings_data->ratings_score   = $new_score;
		$post_ratings_data->ratings_average = $new_average;
	}

	$template = 0 === (int) get_post_meta( $post_id, 'ratings_users', true )
		? WP_PostRatings_Options::template( 'none' )
		: WP_PostRatings_Options::template( 'vote' );

	return expand_ratings_template( $template, $post_id, $post_ratings_data );
}

/**
 * Whether the current visitor is allowed to rate.
 *
 * @return bool
 */
function check_allowtorate() {
	return WP_PostRatings_Rating::can_rate();
}

/**
 * Whether the current visitor has already rated a post.
 *
 * @param int $post_id Post id.
 *
 * @return bool
 */
function check_rated( $post_id ) {
	return WP_PostRatings_Rating::has_rated( $post_id );
}

/**
 * Replace the %TOKEN% variables in a rating template.
 *
 * @param string      $template             Template with %TOKEN% placeholders.
 * @param int|WP_Post $post_data            Post id or object.
 * @param object|null $post_ratings_data    Pre-computed figures.
 * @param int         $max_post_title_chars Truncate the title.
 * @param bool        $is_main_loop         Whether this is the main loop.
 *
 * @return string
 */
function expand_ratings_template( $template, $post_data, $post_ratings_data = null, $max_post_title_chars = 0, $is_main_loop = true ) {
	return WP_PostRatings_Template::expand( $template, $post_data, $post_ratings_data, $max_post_title_chars, $is_main_loop );
}

/**
 * The rating a comment author gave the post being displayed.
 *
 * @param string $comment_author_specific Author name, or '' for the current comment.
 * @param bool   $display                 Unused; both branches return.
 *
 * @return string
 */
function comment_author_ratings( $comment_author_specific = '', $display = true ) {
	unset( $display );

	return WP_PostRatings_Comments::author_ratings( $comment_author_specific );
}

/**
 * Describe an image set.
 *
 * @deprecated 2.0.0 The image sets are gone; shapes are SVG masks now. Kept
 *                   because it was a global function, and answered in the same
 *                   shape from the shape registry so a caller reading ['max']
 *                   or ['custom'] still gets something true.
 *
 * @param string $folder_name Image set folder name, or a shape name.
 *
 * @return array
 */
function ratings_images_folder( $folder_name ) {
	_deprecated_function( __FUNCTION__, '2.0.0', 'WP_PostRatings_Shapes::get()' );

	$shape = WP_PostRatings_Template::resolve_shape( $folder_name );

	return array(
		'max'    => WP_PostRatings_Shapes::is_updown( $shape ) ? 2 : (int) WP_PostRatings_Options::get( 'max' ),
		'custom' => WP_PostRatings_Shapes::is_updown( $shape ) ? 1 : 0,
		'images' => array(),
	);
}

if ( ! function_exists( 'get_ratings_users' ) ) {
	/**
	 * Total number of votes cast.
	 *
	 * @param bool $display Whether to echo.
	 *
	 * @return string|void
	 */
	function get_ratings_users( $display = true ) {
		return WP_PostRatings_Stats::ratings_users( $display );
	}
}

if ( ! function_exists( 'get_most_rated' ) ) {
	/**
	 * Most rated posts.
	 *
	 * @param string $mode      Post type.
	 * @param int    $min_votes Minimum votes.
	 * @param int    $limit     Rows.
	 * @param int    $chars     Title truncation.
	 * @param bool   $display   Whether to echo.
	 *
	 * @return string|void
	 */
	function get_most_rated( $mode = '', $min_votes = 0, $limit = 10, $chars = 0, $display = true ) {
		return WP_PostRatings_Stats::output(
			array(
				'order'     => 'most',
				'mode'      => $mode,
				'min_votes' => $min_votes,
				'limit'     => $limit,
			),
			'mostrated',
			$chars,
			$display
		);
	}
}

if ( ! function_exists( 'get_most_rated_category' ) ) {
	/**
	 * Most rated posts in a category.
	 *
	 * @param int|array $category_id Category id or ids.
	 * @param string    $mode        Post type.
	 * @param int       $min_votes   Minimum votes.
	 * @param int       $limit       Rows.
	 * @param int       $chars       Title truncation.
	 * @param bool      $display     Whether to echo.
	 *
	 * @return string|void
	 */
	function get_most_rated_category( $category_id = 0, $mode = '', $min_votes = 0, $limit = 10, $chars = 0, $display = true ) {
		return WP_PostRatings_Stats::output(
			array(
				'order'     => 'most',
				'mode'      => $mode,
				'min_votes' => $min_votes,
				'limit'     => $limit,
				'taxonomy'  => 'category',
				'terms'     => $category_id,
			),
			'mostrated',
			$chars,
			$display
		);
	}
}

if ( ! function_exists( 'get_most_rated_range' ) ) {
	/**
	 * Most rated posts within a time range.
	 *
	 * @param string $time    Time range, e.g. '1 day'.
	 * @param string $mode    Post type.
	 * @param int    $limit   Rows.
	 * @param int    $chars   Title truncation.
	 * @param bool   $display Whether to echo.
	 *
	 * @return string|void
	 */
	function get_most_rated_range( $time = '1 day', $mode = '', $limit = 10, $chars = 0, $display = true ) {
		return WP_PostRatings_Stats::output(
			array(
				'source' => 'range',
				'order'  => 'most',
				'mode'   => $mode,
				'limit'  => $limit,
				'time'   => $time,
			),
			'mostrated',
			$chars,
			$display
		);
	}
}

if ( ! function_exists( 'get_most_rated_range_category' ) ) {
	/**
	 * Most rated posts within a time range and category.
	 *
	 * @param string    $time        Time range.
	 * @param int|array $category_id Category id or ids.
	 * @param string    $mode        Post type.
	 * @param int       $limit       Rows.
	 * @param int       $chars       Title truncation.
	 * @param bool      $display     Whether to echo.
	 *
	 * @return string|void
	 */
	function get_most_rated_range_category( $time = '1 day', $category_id = 0, $mode = '', $limit = 10, $chars = 0, $display = true ) {
		return WP_PostRatings_Stats::output(
			array(
				'source'   => 'range',
				'order'    => 'most',
				'mode'     => $mode,
				'limit'    => $limit,
				'time'     => $time,
				'taxonomy' => 'category',
				'terms'    => $category_id,
			),
			'mostrated',
			$chars,
			$display
		);
	}
}

if ( ! function_exists( 'get_highest_rated' ) ) {
	/**
	 * Highest rated posts.
	 *
	 * @param string $mode      Post type.
	 * @param int    $min_votes Minimum votes.
	 * @param int    $limit     Rows.
	 * @param int    $chars     Title truncation.
	 * @param bool   $display   Whether to echo.
	 *
	 * @return string|void
	 */
	function get_highest_rated( $mode = '', $min_votes = 0, $limit = 10, $chars = 0, $display = true ) {
		return WP_PostRatings_Stats::output(
			array(
				'order'     => 'highest',
				'mode'      => $mode,
				'min_votes' => $min_votes,
				'limit'     => $limit,
			),
			'highestrated',
			$chars,
			$display
		);
	}
}

if ( ! function_exists( 'get_highest_rated_category' ) ) {
	/**
	 * Highest rated posts in a category.
	 *
	 * @param int|array $category_id Category id or ids.
	 * @param string    $mode        Post type.
	 * @param int       $min_votes   Minimum votes.
	 * @param int       $limit       Rows.
	 * @param int       $chars       Title truncation.
	 * @param bool      $display     Whether to echo.
	 *
	 * @return string|void
	 */
	function get_highest_rated_category( $category_id = 0, $mode = '', $min_votes = 0, $limit = 10, $chars = 0, $display = true ) {
		return WP_PostRatings_Stats::output(
			array(
				'order'     => 'highest',
				'mode'      => $mode,
				'min_votes' => $min_votes,
				'limit'     => $limit,
				'taxonomy'  => 'category',
				'terms'     => $category_id,
			),
			'highestrated',
			$chars,
			$display
		);
	}
}

if ( ! function_exists( 'get_highest_rated_range' ) ) {
	/**
	 * Highest rated posts within a time range.
	 *
	 * @param string $time    Time range.
	 * @param string $mode    Post type.
	 * @param int    $limit   Rows.
	 * @param int    $chars   Title truncation.
	 * @param bool   $display Whether to echo.
	 *
	 * @return string|void
	 */
	function get_highest_rated_range( $time = '1 day', $mode = '', $limit = 10, $chars = 0, $display = true ) {
		return WP_PostRatings_Stats::output(
			array(
				'source' => 'range',
				'order'  => 'highest',
				'mode'   => $mode,
				'limit'  => $limit,
				'time'   => $time,
			),
			'highestrated',
			$chars,
			$display
		);
	}
}

if ( ! function_exists( 'get_highest_rated_range_category' ) ) {
	/**
	 * Highest rated posts within a time range and category.
	 *
	 * @param string    $time        Time range.
	 * @param int|array $category_id Category id or ids.
	 * @param string    $mode        Post type.
	 * @param int       $limit       Rows.
	 * @param int       $chars       Title truncation.
	 * @param bool      $display     Whether to echo.
	 *
	 * @return string|void
	 */
	function get_highest_rated_range_category( $time = '1 day', $category_id = 0, $mode = '', $limit = 10, $chars = 0, $display = true ) {
		return WP_PostRatings_Stats::output(
			array(
				'source'   => 'range',
				'order'    => 'highest',
				'mode'     => $mode,
				'limit'    => $limit,
				'time'     => $time,
				'taxonomy' => 'category',
				'terms'    => $category_id,
			),
			'highestrated',
			$chars,
			$display
		);
	}
}

if ( ! function_exists( 'get_lowest_rated' ) ) {
	/**
	 * Lowest rated posts.
	 *
	 * @param string $mode      Post type.
	 * @param int    $min_votes Minimum votes.
	 * @param int    $limit     Rows.
	 * @param int    $chars     Title truncation.
	 * @param bool   $display   Whether to echo.
	 *
	 * @return string|void
	 */
	function get_lowest_rated( $mode = '', $min_votes = 0, $limit = 10, $chars = 0, $display = true ) {
		return WP_PostRatings_Stats::output(
			array(
				'order'     => 'lowest',
				'mode'      => $mode,
				'min_votes' => $min_votes,
				'limit'     => $limit,
			),
			'highestrated',
			$chars,
			$display
		);
	}
}

if ( ! function_exists( 'get_lowest_rated_category' ) ) {
	/**
	 * Lowest rated posts in a category.
	 *
	 * @param int|array $category_id Category id or ids.
	 * @param string    $mode        Post type.
	 * @param int       $min_votes   Minimum votes.
	 * @param int       $limit       Rows.
	 * @param int       $chars       Title truncation.
	 * @param bool      $display     Whether to echo.
	 *
	 * @return string|void
	 */
	function get_lowest_rated_category( $category_id = 0, $mode = '', $min_votes = 0, $limit = 10, $chars = 0, $display = true ) {
		return WP_PostRatings_Stats::output(
			array(
				'order'     => 'lowest',
				'mode'      => $mode,
				'min_votes' => $min_votes,
				'limit'     => $limit,
				'taxonomy'  => 'category',
				'terms'     => $category_id,
			),
			'highestrated',
			$chars,
			$display
		);
	}
}

if ( ! function_exists( 'get_lowest_rated_range' ) ) {
	/**
	 * Lowest rated posts within a time range.
	 *
	 * @param string $time    Time range.
	 * @param string $mode    Post type.
	 * @param int    $limit   Rows.
	 * @param int    $chars   Title truncation.
	 * @param bool   $display Whether to echo.
	 *
	 * @return string|void
	 */
	function get_lowest_rated_range( $time = '1 day', $mode = '', $limit = 10, $chars = 0, $display = true ) {
		return WP_PostRatings_Stats::output(
			array(
				'source' => 'range',
				'order'  => 'lowest',
				'mode'   => $mode,
				'limit'  => $limit,
				'time'   => $time,
			),
			'highestrated',
			$chars,
			$display
		);
	}
}

if ( ! function_exists( 'get_highest_score' ) ) {
	/**
	 * Posts with the highest total score.
	 *
	 * @param string $mode      Post type.
	 * @param int    $min_votes Minimum votes.
	 * @param int    $limit     Rows.
	 * @param int    $chars     Title truncation.
	 * @param bool   $display   Whether to echo.
	 *
	 * @return string|void
	 */
	function get_highest_score( $mode = '', $min_votes = 0, $limit = 10, $chars = 0, $display = true ) {
		return WP_PostRatings_Stats::output(
			array(
				'order'     => 'score',
				'mode'      => $mode,
				'min_votes' => $min_votes,
				'limit'     => $limit,
			),
			'highestrated',
			$chars,
			$display
		);
	}
}

if ( ! function_exists( 'get_highest_score_category' ) ) {
	/**
	 * Posts with the highest total score in a category.
	 *
	 * @param int|array $category_id Category id or ids.
	 * @param string    $mode        Post type.
	 * @param int       $min_votes   Minimum votes.
	 * @param int       $limit       Rows.
	 * @param int       $chars       Title truncation.
	 * @param bool      $display     Whether to echo.
	 *
	 * @return string|void
	 */
	function get_highest_score_category( $category_id = 0, $mode = '', $min_votes = 0, $limit = 10, $chars = 0, $display = true ) {
		return WP_PostRatings_Stats::output(
			array(
				'order'     => 'score',
				'mode'      => $mode,
				'min_votes' => $min_votes,
				'limit'     => $limit,
				'taxonomy'  => 'category',
				'terms'     => $category_id,
			),
			'highestrated',
			$chars,
			$display
		);
	}
}

if ( ! function_exists( 'get_highest_score_range' ) ) {
	/**
	 * Posts with the highest total score within a time range.
	 *
	 * @param string $time    Time range.
	 * @param string $mode    Post type.
	 * @param int    $limit   Rows.
	 * @param int    $chars   Title truncation.
	 * @param bool   $display Whether to echo.
	 *
	 * @return string|void
	 */
	function get_highest_score_range( $time = '1 day', $mode = '', $limit = 10, $chars = 0, $display = true ) {
		return WP_PostRatings_Stats::output(
			array(
				'source' => 'range',
				'order'  => 'score',
				'mode'   => $mode,
				'limit'  => $limit,
				'time'   => $time,
			),
			'highestrated',
			$chars,
			$display
		);
	}
}

if ( ! function_exists( 'get_highest_score_range_category' ) ) {
	/**
	 * Posts with the highest total score within a time range and category.
	 *
	 * @param string    $time        Time range.
	 * @param int|array $category_id Category id or ids.
	 * @param string    $mode        Post type.
	 * @param int       $limit       Rows.
	 * @param int       $chars       Title truncation.
	 * @param bool      $display     Whether to echo.
	 *
	 * @return string|void
	 */
	function get_highest_score_range_category( $time = '1 day', $category_id = 0, $mode = '', $limit = 10, $chars = 0, $display = true ) {
		return WP_PostRatings_Stats::output(
			array(
				'source'   => 'range',
				'order'    => 'score',
				'mode'     => $mode,
				'limit'    => $limit,
				'time'     => $time,
				'taxonomy' => 'category',
				'terms'    => $category_id,
			),
			'highestrated',
			$chars,
			$display
		);
	}
}

if ( ! function_exists( 'get_highest_rated_tag' ) ) {
	/**
	 * Highest rated posts carrying a tag.
	 *
	 * @param int|array $tag_id    Tag id or ids.
	 * @param string    $mode      Post type.
	 * @param int       $min_votes Minimum votes.
	 * @param int       $limit     Rows.
	 * @param int       $chars     Title truncation.
	 * @param bool      $display   Whether to echo.
	 *
	 * @return string|void
	 */
	function get_highest_rated_tag( $tag_id = 0, $mode = '', $min_votes = 0, $limit = 10, $chars = 0, $display = true ) {
		return WP_PostRatings_Stats::output(
			array(
				'order'     => 'highest',
				'mode'      => $mode,
				'min_votes' => $min_votes,
				'limit'     => $limit,
				'taxonomy'  => 'post_tag',
				'terms'     => $tag_id,
			),
			'highestrated',
			$chars,
			$display
		);
	}
}

if ( ! function_exists( 'get_lowest_rated_tag' ) ) {
	/**
	 * Lowest rated posts carrying a tag.
	 *
	 * @param int|array $tag_id    Tag id or ids.
	 * @param string    $mode      Post type.
	 * @param int       $min_votes Minimum votes.
	 * @param int       $limit     Rows.
	 * @param int       $chars     Title truncation.
	 * @param bool      $display   Whether to echo.
	 *
	 * @return string|void
	 */
	function get_lowest_rated_tag( $tag_id = 0, $mode = '', $min_votes = 0, $limit = 10, $chars = 0, $display = true ) {
		return WP_PostRatings_Stats::output(
			array(
				'order'     => 'lowest',
				'mode'      => $mode,
				'min_votes' => $min_votes,
				'limit'     => $limit,
				'taxonomy'  => 'post_tag',
				'terms'     => $tag_id,
			),
			'highestrated',
			$chars,
			$display
		);
	}
}
