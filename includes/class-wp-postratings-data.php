<?php
/**
 * Reading and clearing rating data, with no presentation attached to it.
 *
 * @package WP-PostRatings
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and clears ratings on behalf of every screen and endpoint that has one.
 *
 * A rating lives in two places at once -- three post meta keys holding the
 * running totals, and a row per vote in the logs table -- and "clear this
 * post's ratings" means something different depending on which of the two is
 * meant. The admin screen encoded that in a private method returning a list of
 * notices, so the WP-CLI command could only have copied it, and a copy is how
 * one caller ends up clearing the meta while forgetting the log.
 *
 * Every method here returns data or a count and **prints nothing**. What to say
 * about the result belongs to the caller, because the callers do not agree: a
 * settings-error notice, a `WP_CLI::success()` line, a JSON body.
 */
class WP_PostRatings_Data {

	/**
	 * The post meta keys a rating's running totals live in.
	 *
	 * @var array
	 */
	const META_KEYS = array( 'ratings_users', 'ratings_score', 'ratings_average' );

	/**
	 * A post's rating totals.
	 *
	 * @param int $post_id Post to read.
	 * @return array Users, score and average, all zero for a post nobody rated.
	 */
	public static function get( $post_id ) {
		$post_id = (int) $post_id;

		return array(
			'post_id' => $post_id,
			'users'   => (int) get_post_meta( $post_id, 'ratings_users', true ),
			'score'   => (int) get_post_meta( $post_id, 'ratings_score', true ),
			// The average is the one that is not an integer, and rounding it
			// here would make a 3.5 read as 4 in every caller at once.
			'average' => (float) get_post_meta( $post_id, 'ratings_average', true ),
		);
	}

	/**
	 * The rated posts, best first.
	 *
	 * @param int $limit How many to return.
	 * @return array One entry per post, in the shape get() returns plus a title.
	 */
	public static function top( $limit = 10 ) {
		$posts = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => (int) $limit,
				'fields'         => 'ids',
				// Filtering on ratings_users rather than on the average existing,
				// because **this plugin seeds all three meta keys at zero when a
				// post is published** -- WP_PostRatings::add_meta() is hooked to
				// publish_post and publish_page. So "has a ratings_average row"
				// is true of every post on the site and would list a fresh draft
				// as though somebody had rated it nothing.
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Selecting the rated posts is the whole query.
					'rated'   => array(
						'key'     => 'ratings_users',
						'value'   => 0,
						'compare' => '>',
						'type'    => 'NUMERIC',
					),
					'average' => array(
						'key'     => 'ratings_average',
						'compare' => 'EXISTS',
						'type'    => 'NUMERIC',
					),
				),
				'orderby'        => array( 'average' => 'DESC' ),
			)
		);

		$rated = array();

		foreach ( $posts as $post_id ) {
			$rated[] = array_merge(
				self::get( $post_id ),
				array( 'title' => get_the_title( $post_id ) )
			);
		}

		return $rated;
	}

	/**
	 * Delete log rows for whole posts, or every row there is.
	 *
	 * @param array $post_ids Posts whose log rows should go. Ignored when $all.
	 * @param bool  $all      Whether to clear the entire log.
	 * @return int Rows deleted.
	 */
	public static function delete_logs( $post_ids = array(), $all = false ) {
		global $wpdb;

		if ( $all ) {
			return (int) $wpdb->query( "DELETE FROM {$wpdb->ratings}" );
		}

		return self::delete_log_rows( 'rating_postid', $post_ids );
	}

	/**
	 * Delete individual log rows by their own ids.
	 *
	 * @param array $rating_ids Log rows to delete.
	 * @return int Rows deleted.
	 */
	public static function delete_log_entries( $rating_ids ) {
		return self::delete_log_rows( 'rating_id', $rating_ids );
	}

	/**
	 * Delete log rows matching a list of ids.
	 *
	 * One $wpdb->delete() per id rather than a generated run of %d
	 * placeholders: an IN () list cannot be expressed with $wpdb->prepare()
	 * without interpolating the placeholders into the SQL first, and the lists
	 * here are a page of log rows or a handful of post ids.
	 *
	 * @param string $column Column to match on.
	 * @param array  $ids    Ids to delete.
	 * @return int Rows deleted.
	 */
	private static function delete_log_rows( $column, $ids ) {
		global $wpdb;

		$deleted = 0;

		foreach ( $ids as $id ) {
			$deleted += (int) $wpdb->delete( $wpdb->ratings, array( $column => (int) $id ), array( '%d' ) );
		}

		return $deleted;
	}

	/**
	 * Delete the running totals for some posts, or for every post.
	 *
	 * @param array $post_ids Posts to clear. Ignored when $all.
	 * @param bool  $all      Whether to clear every post's totals.
	 * @return int Posts cleared, or the number of meta rows removed when $all.
	 */
	public static function delete_ratings( $post_ids = array(), $all = false ) {
		global $wpdb;

		if ( $all ) {
			$deleted = 0;

			foreach ( self::META_KEYS as $meta_key ) {
				// delete_post_meta() wants a post id, and "every post" is not
				// one; this clears the key across the table in one statement.
				$deleted += (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", $meta_key ) );
			}

			// Deleting meta rows directly leaves every post's meta cache holding
			// the values that were just removed.
			wp_cache_flush();

			return $deleted;
		}

		foreach ( $post_ids as $post_id ) {
			foreach ( self::META_KEYS as $meta_key ) {
				delete_post_meta( (int) $post_id, $meta_key );
			}
		}

		return count( $post_ids );
	}
}
