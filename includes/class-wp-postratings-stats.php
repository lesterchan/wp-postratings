<?php
/**
 * WP-PostRatings class-wp-postratings-stats.php
 *
 * @package WP-PostRatings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The "most rated", "highest rated", "lowest rated" and "highest score" lists.
 *
 * The twenty-one template tags these back are two query shapes -- one over the
 * rating post meta, one aggregating the log table over a time range -- crossed
 * with four orderings and an optional taxonomy filter. They are built here from
 * one query builder rather than twenty-one copies of the same SQL.
 *
 * @since 2.0.0
 */
class WP_PostRatings_Stats {

	/**
	 * Cache group for the query results.
	 */
	const CACHE_GROUP = 'wp-postratings';

	/**
	 * Build the ORDER BY clause for a list type.
	 *
	 * @param string $order_type One of most, highest, lowest, score.
	 *
	 * @return string
	 */
	private static function order_by( $order_type ) {
		$options = WP_PostRatings_Options::get();

		// An up/down scale has no meaningful average, so it ranks on the score.
		$column = ( $options['customrating'] && 2 === (int) $options['max'] ) ? 'ratings_score' : 'ratings_average';

		switch ( $order_type ) {
			case 'most':
				return 'ratings_users DESC, ' . $column . ' DESC';
			case 'lowest':
				return $column . ' ASC, ratings_users DESC';
			case 'score':
				return 'ratings_score DESC, ratings_average DESC';
			case 'highest':
			default:
				return $column . ' DESC, ratings_users DESC';
		}
	}

	/**
	 * Build and run one of the ranking queries.
	 *
	 * @param array $args {
	 *     Query arguments.
	 *
	 *     @type string    $source    Either 'meta' or 'range'.
	 *     @type string    $order     One of most, highest, lowest, score.
	 *     @type string    $mode      Post type, or '' for all.
	 *     @type int       $min_votes Minimum number of votes, meta source only.
	 *     @type int       $limit     Number of rows.
	 *     @type string    $time      Time range such as '1 day', range source only.
	 *     @type string    $taxonomy  One of 'category', 'post_tag' or ''.
	 *     @type int|array $terms     Term ids to filter on.
	 * }
	 * @return array
	 */
	private static function query( $args ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'source'    => 'meta',
				'order'     => 'highest',
				'mode'      => '',
				'min_votes' => 0,
				'limit'     => 10,
				'time'      => '1 day',
				'taxonomy'  => '',
				'terms'     => 0,
			)
		);

		$limit = max( 1, (int) $args['limit'] );

		// Post type filter.
		$where = ( ! empty( $args['mode'] ) && 'both' !== $args['mode'] )
			? $wpdb->prepare( "$wpdb->posts.post_type = %s", $args['mode'] )
			: '1=1';

		// Taxonomy filter.
		$taxonomy_join  = '';
		$taxonomy_where = '';

		if ( '' !== $args['taxonomy'] ) {
			$terms = is_array( $args['terms'] ) ? array_map( 'intval', $args['terms'] ) : array( (int) $args['terms'] );
			$terms = array_filter( $terms );

			// An empty list would build "IN ()", which is a syntax error.
			if ( empty( $terms ) ) {
				return array();
			}

			$placeholders = implode( ', ', array_fill( 0, count( $terms ), '%d' ) );

			$taxonomy_join = " INNER JOIN $wpdb->term_relationships ON ($wpdb->posts.ID = $wpdb->term_relationships.object_id)" .
				" INNER JOIN $wpdb->term_taxonomy ON ($wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id)";

			$taxonomy_where = $wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is a generated run of %d, one per term id; prepare() cannot bind a variable-length IN () list.
				" AND $wpdb->term_taxonomy.taxonomy = %s AND $wpdb->term_taxonomy.term_id IN ( $placeholders )",
				array_merge( array( $args['taxonomy'] ), $terms )
			);
		}

		$order_by = self::order_by( $args['order'] );

		/*
		 * The two statements below are assembled from four pieces -- the post
		 * type clause, the taxonomy join and its clause, and the ORDER BY --
		 * each of which is either a $wpdb->prepare() result or a string from an
		 * allow list in this class. $wpdb->prepare() cannot bind an identifier
		 * or a variable-length IN () list, so the assembly has to happen in the
		 * string, and the sniff cannot see that the pieces are already safe.
		 */
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- See the note above: every interpolated piece is a prepare() result or an allow-listed identifier, which the sniff cannot see.
		if ( 'range' === $args['source'] ) {
			// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp -- rating_timestamp holds a site-local timestamp in every row ever written, so the range has to be computed in the same frame.
			$min_time = strtotime( '-' . $args['time'], current_time( 'timestamp' ) );

			$sql = $wpdb->prepare(
				"SELECT COUNT($wpdb->ratings.rating_postid) AS ratings_users,
					SUM($wpdb->ratings.rating_rating) AS ratings_score,
					ROUND(((SUM($wpdb->ratings.rating_rating)/COUNT($wpdb->ratings.rating_postid))), 2) AS ratings_average,
					$wpdb->posts.ID
				FROM $wpdb->posts
				LEFT JOIN $wpdb->ratings ON $wpdb->ratings.rating_postid = $wpdb->posts.ID
				$taxonomy_join
				WHERE rating_timestamp >= %d
					AND $wpdb->posts.post_password = ''
					AND $wpdb->posts.post_date < NOW()
					AND $wpdb->posts.post_status = 'publish'
					$taxonomy_where
					AND $where
				GROUP BY $wpdb->ratings.rating_postid
				ORDER BY $order_by
				LIMIT %d",
				$min_time,
				$limit
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT DISTINCT $wpdb->posts.ID,
					(t1.meta_value+0.00) AS ratings_average,
					(t2.meta_value+0.00) AS ratings_users,
					(t3.meta_value+0.00) AS ratings_score
				FROM $wpdb->posts
				LEFT JOIN $wpdb->postmeta AS t1 ON t1.post_id = $wpdb->posts.ID
				LEFT JOIN $wpdb->postmeta AS t2 ON t1.post_id = t2.post_id
				LEFT JOIN $wpdb->postmeta AS t3 ON t3.post_id = $wpdb->posts.ID
				$taxonomy_join
				WHERE t1.meta_key = 'ratings_average'
					AND t2.meta_key = 'ratings_users'
					AND t3.meta_key = 'ratings_score'
					AND $wpdb->posts.post_password = ''
					AND $wpdb->posts.post_date < NOW()
					AND $wpdb->posts.post_status = 'publish'
					$taxonomy_where
					AND t2.meta_value >= %d
					AND $where
				ORDER BY $order_by
				LIMIT %d",
				(int) $args['min_votes'],
				$limit
			);
		}

		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$cache_key = 'wp_postratings_stats_' . md5( $sql );
		$results   = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false === $results ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is built above by $wpdb->prepare(); the ranking queries span the plugin's own table and the post meta, which no core API can express.
			$results = $wpdb->get_results( $sql, ARRAY_A );
			wp_cache_add( $cache_key, $results, self::CACHE_GROUP, HOUR_IN_SECONDS );
		}

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Render a set of results through a template.
	 *
	 * @param array  $results  Rows from query().
	 * @param string $template Template name, 'mostrated' or 'highestrated'.
	 * @param int    $chars    Truncate post titles to this many characters.
	 *
	 * @return string
	 */
	private static function render( $results, $template, $chars ) {
		if ( empty( $results ) ) {
			return '<li>' . esc_html__( 'N/A', 'wp-postratings' ) . '</li>' . "\n";
		}

		_prime_post_caches( wp_list_pluck( $results, 'ID' ) );

		$temp   = WP_PostRatings_Options::template( $template );
		$output = '';

		foreach ( $results as $row ) {
			$post = (object) array_merge( $row, (array) get_post( $row['ID'] ) );

			$output .= WP_PostRatings_Template::expand( $temp, $post, null, $chars, false ) . "\n";
		}

		return $output;
	}

	/**
	 * Run a list and either echo it or hand it back.
	 *
	 * @param array  $args     Arguments for query().
	 * @param string $template Template name.
	 * @param int    $chars    Title truncation.
	 * @param bool   $display  Whether to echo.
	 *
	 * @return string|void
	 */
	public static function output( $args, $template, $chars, $display ) {
		$output = self::render( self::query( $args ), $template, $chars );

		if ( $display ) {
			WP_PostRatings_Template::render( $output );

			return;
		}

		return $output;
	}

	/**
	 * Total number of votes cast across the site.
	 *
	 * @param bool $display Whether to echo.
	 *
	 * @return string|void
	 */
	public static function ratings_users( $display = true ) {
		global $wpdb;

		$cache_key     = 'get_ratings_users';
		$ratings_users = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false === $ratings_users ) {
			$ratings_users = $wpdb->get_var( "SELECT SUM((meta_value+0.00)) FROM $wpdb->postmeta WHERE meta_key = 'ratings_users'" );
			wp_cache_add( $cache_key, $ratings_users, self::CACHE_GROUP, HOUR_IN_SECONDS );
		}

		if ( $display ) {
			echo esc_html( $ratings_users );

			return;
		}

		return $ratings_users;
	}
}
