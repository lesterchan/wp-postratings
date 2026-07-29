<?php
/**
 * Tests for the ranking query builder.
 *
 * The twenty-one template tags are two query shapes crossed with four
 * orderings and an optional taxonomy filter. The tags themselves are covered
 * elsewhere; this exercises the combinations they are built from, several of
 * which no tag reaches on its own.
 *
 * @package WP-PostRatings
 */

/**
 * The meta and range query shapes, orderings and filters.
 *
 * @covers WP_PostRatings_Stats
 */
class Test_Postratings_Stats extends WP_PostRatings_TestCase {

	/**
	 * Render rankings as a bare list of post ids.
	 *
	 * Permalinks vary with the rewrite rules, so the templates are stubbed
	 * down to %POST_ID% and the ids read back out.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$options                              = WP_PostRatings_Options::get();
		$options['templates']['highestrated'] = '<li>[%POST_ID%]</li>';
		$options['templates']['mostrated']    = '<li>[%POST_ID%]</li>';
		WP_PostRatings_Options::update( $options );
	}

	/**
	 * Pull the ranked post ids out of rendered output, in order.
	 *
	 * @param string $output Rendered list.
	 *
	 * @return array
	 */
	private function ids( $output ) {
		preg_match_all( '/\[(\d+)\]/', $output, $matches );

		return array_map( 'intval', $matches[1] );
	}

	/**
	 * Record a vote in the log, dated relative to now.
	 *
	 * Range queries aggregate the log table rather than the post meta, so they
	 * need rows with plausible timestamps. current_time( 'timestamp' ) matches
	 * what the plugin writes, which is what the query compares against.
	 *
	 * @param int $post_id     Post rated.
	 * @param int $rating      Score given.
	 * @param int $days_ago    How long ago the vote was cast.
	 *
	 * @return void
	 */
	private function log_vote_at( $post_id, $rating, $days_ago ) {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->ratings} VALUES (%d, %d, %s, %d, %d, %s, %s, %s, %d)",
				0,
				$post_id,
				'Fixture',
				$rating,
				// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Matches what the plugin stores in rating_timestamp.
				current_time( 'timestamp' ) - ( $days_ago * DAY_IN_SECONDS ),
				wp_hash( '203.0.113.' . wp_rand( 1, 250 ) ),
				'example.net',
				'Guest',
				0
			)
		);
	}

	// --- the meta shape ---------------------------------------------------

	/**
	 * Each ordering ranks by the column its name promises.
	 *
	 * @return void
	 */
	public function test_each_ordering_ranks_by_its_own_column() {
		$many_low  = $this->make_rated_post( 10, 10 );  // average 1, score 10.
		$few_high  = $this->make_rated_post( 2, 10 );   // average 5, score 10.
		$mid_score = $this->make_rated_post( 4, 16 );   // average 4, score 16.

		$highest = $this->ids( WP_PostRatings_Stats::output( array( 'order' => 'highest' ), 'highestrated', 0, false ) );
		$lowest  = $this->ids( WP_PostRatings_Stats::output( array( 'order' => 'lowest' ), 'highestrated', 0, false ) );
		$most    = $this->ids( WP_PostRatings_Stats::output( array( 'order' => 'most' ), 'mostrated', 0, false ) );
		$score   = $this->ids( WP_PostRatings_Stats::output( array( 'order' => 'score' ), 'highestrated', 0, false ) );

		$this->assertSame( $few_high, $highest[0], 'highest did not lead with the best average' );
		$this->assertSame( $many_low, $lowest[0], 'lowest did not lead with the worst average' );
		$this->assertSame( $many_low, $most[0], 'most did not lead with the highest vote count' );
		$this->assertSame( $mid_score, $score[0], 'score did not lead with the highest total' );
	}

	/**
	 * An up/down scale ranks on the score, since its average means nothing.
	 *
	 * @return void
	 */
	public function test_an_up_down_scale_ranks_on_score() {
		$this->set_option( 'customrating', 1 );
		$this->set_option( 'max', 2 );

		$low  = $this->make_rated_post( 10, 2 );
		$high = $this->make_rated_post( 10, 40 );

		$ids = $this->ids( WP_PostRatings_Stats::output( array( 'order' => 'highest' ), 'highestrated', 0, false ) );

		$this->assertSame( $high, $ids[0] );
		$this->assertContains( $low, $ids );
	}

	/**
	 * The minimum vote count excludes thinly rated posts.
	 *
	 * @return void
	 */
	public function test_the_minimum_vote_count_is_applied() {
		$thin  = $this->make_rated_post( 1, 5 );
		$solid = $this->make_rated_post( 20, 80 );

		$ids = $this->ids( WP_PostRatings_Stats::output( array( 'min_votes' => 5 ), 'highestrated', 0, false ) );

		$this->assertContains( $solid, $ids );
		$this->assertNotContains( $thin, $ids );
	}

	/**
	 * The limit caps the number of rows.
	 *
	 * @return void
	 */
	public function test_the_limit_caps_the_rows() {
		$this->make_rated_post( 4, 18 );
		$this->make_rated_post( 6, 20 );
		$this->make_rated_post( 8, 24 );

		$ids = $this->ids( WP_PostRatings_Stats::output( array( 'limit' => 2 ), 'highestrated', 0, false ) );

		$this->assertCount( 2, $ids );
	}

	/**
	 * A post type filter is honoured.
	 *
	 * @return void
	 */
	public function test_the_post_type_filter_is_honoured() {
		$post = $this->make_rated_post( 4, 18, 'post' );
		$page = $this->make_rated_post( 4, 18, 'page' );

		$posts = $this->ids( WP_PostRatings_Stats::output( array( 'mode' => 'post' ), 'highestrated', 0, false ) );
		$pages = $this->ids( WP_PostRatings_Stats::output( array( 'mode' => 'page' ), 'highestrated', 0, false ) );

		$this->assertContains( $post, $posts );
		$this->assertNotContains( $page, $posts );
		$this->assertContains( $page, $pages );
		$this->assertNotContains( $post, $pages );
	}

	/**
	 * Unpublished, future and password protected posts never appear.
	 *
	 * @return void
	 */
	public function test_only_public_published_posts_are_ranked() {
		$published = $this->make_rated_post( 4, 18 );

		$draft = $this->make_rated_post( 9, 45 );
		wp_update_post(
			array(
				'ID'          => $draft,
				'post_status' => 'draft',
			)
		);

		$protected = $this->make_rated_post( 9, 45 );
		wp_update_post(
			array(
				'ID'            => $protected,
				'post_password' => 'secret',
			)
		);

		$future = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
			)
		);
		update_post_meta( $future, 'ratings_users', 9 );
		update_post_meta( $future, 'ratings_score', 45 );
		update_post_meta( $future, 'ratings_average', 5 );

		$ids = $this->ids( WP_PostRatings_Stats::output( array(), 'highestrated', 0, false ) );

		$this->assertContains( $published, $ids );
		$this->assertNotContains( $draft, $ids );
		$this->assertNotContains( $protected, $ids );
		$this->assertNotContains( $future, $ids );
	}

	// --- taxonomy filters -------------------------------------------------

	/**
	 * A category filter restricts the list to that category.
	 *
	 * @return void
	 */
	public function test_a_category_filter_restricts_the_list() {
		$category = self::factory()->category->create();

		$inside  = $this->make_rated_post( 4, 18 );
		$outside = $this->make_rated_post( 6, 28 );

		wp_set_post_categories( $inside, array( $category ) );

		$ids = $this->ids(
			WP_PostRatings_Stats::output(
				array(
					'taxonomy' => 'category',
					'terms'    => $category,
				),
				'highestrated',
				0,
				false
			)
		);

		$this->assertContains( $inside, $ids );
		$this->assertNotContains( $outside, $ids );
	}

	/**
	 * Several categories may be given at once.
	 *
	 * @return void
	 */
	public function test_several_categories_may_be_given() {
		$first  = self::factory()->category->create();
		$second = self::factory()->category->create();

		$a = $this->make_rated_post( 4, 18 );
		$b = $this->make_rated_post( 6, 28 );

		wp_set_post_categories( $a, array( $first ) );
		wp_set_post_categories( $b, array( $second ) );

		$ids = $this->ids(
			WP_PostRatings_Stats::output(
				array(
					'taxonomy' => 'category',
					'terms'    => array( $first, $second ),
				),
				'highestrated',
				0,
				false
			)
		);

		$this->assertContains( $a, $ids );
		$this->assertContains( $b, $ids );
	}

	/**
	 * A tag filter uses the post_tag taxonomy, not categories.
	 *
	 * @return void
	 */
	public function test_a_tag_filter_uses_the_tag_taxonomy() {
		$tag      = self::factory()->tag->create();
		$category = self::factory()->category->create();

		$tagged      = $this->make_rated_post( 4, 18 );
		$categorised = $this->make_rated_post( 6, 28 );

		wp_set_post_tags( $tagged, array( $tag ) );
		wp_set_post_categories( $categorised, array( $category ) );

		$ids = $this->ids(
			WP_PostRatings_Stats::output(
				array(
					'taxonomy' => 'post_tag',
					'terms'    => $tag,
				),
				'highestrated',
				0,
				false
			)
		);

		$this->assertSame( array( $tagged ), $ids );
	}

	/**
	 * An empty term list yields nothing rather than a SQL error.
	 *
	 * Interpolating an empty list would build "IN ()".
	 *
	 * @return void
	 */
	public function test_an_empty_term_list_is_not_a_syntax_error() {
		global $wpdb;

		$this->make_rated_post( 4, 18 );

		$suppress = $wpdb->suppress_errors( true );

		$output = WP_PostRatings_Stats::output(
			array(
				'taxonomy' => 'category',
				'terms'    => array(),
			),
			'highestrated',
			0,
			false
		);

		$wpdb->suppress_errors( $suppress );

		$this->assertStringContainsString( 'N/A', $output );
		$this->assertSame( '', $wpdb->last_error );
	}

	/**
	 * A term list of only zeroes is treated as empty.
	 *
	 * The wp_parse_id_list() helper maps unparseable entries to 0, and the
	 * widget passes its comma separated field straight through.
	 *
	 * @return void
	 */
	public function test_a_term_list_of_zeroes_is_treated_as_empty() {
		$this->make_rated_post( 4, 18 );

		$output = WP_PostRatings_Stats::output(
			array(
				'taxonomy' => 'category',
				'terms'    => array( 0, 0 ),
			),
			'highestrated',
			0,
			false
		);

		$this->assertStringContainsString( 'N/A', $output );
	}

	// --- the range shape --------------------------------------------------

	/**
	 * The range shape counts only votes inside the window.
	 *
	 * @return void
	 */
	public function test_the_range_shape_counts_only_recent_votes() {
		$recent = $this->make_rated_post( 0, 0 );
		$stale  = $this->make_rated_post( 0, 0 );

		$this->log_vote_at( $recent, 5, 1 );
		$this->log_vote_at( $stale, 5, 90 );

		$ids = $this->ids(
			WP_PostRatings_Stats::output(
				array(
					'source' => 'range',
					'time'   => '7 days',
				),
				'mostrated',
				0,
				false
			)
		);

		$this->assertContains( $recent, $ids );
		$this->assertNotContains( $stale, $ids );
	}

	/**
	 * A wider window takes both in.
	 *
	 * @return void
	 */
	public function test_a_wider_window_includes_older_votes() {
		$recent = $this->make_rated_post( 0, 0 );
		$stale  = $this->make_rated_post( 0, 0 );

		$this->log_vote_at( $recent, 5, 1 );
		$this->log_vote_at( $stale, 5, 90 );

		$ids = $this->ids(
			WP_PostRatings_Stats::output(
				array(
					'source' => 'range',
					'time'   => '1 year',
				),
				'mostrated',
				0,
				false
			)
		);

		$this->assertContains( $recent, $ids );
		$this->assertContains( $stale, $ids );
	}

	/**
	 * The range shape ranks by the votes inside the window, not lifetime meta.
	 *
	 * @return void
	 */
	public function test_the_range_shape_ranks_on_the_window() {
		// Busy lifetime, quiet week.
		$lifetime = $this->make_rated_post( 100, 500 );
		// Quiet lifetime, busy week.
		$recent = $this->make_rated_post( 2, 4 );

		$this->log_vote_at( $lifetime, 5, 200 );

		foreach ( range( 1, 3 ) as $day ) {
			$this->log_vote_at( $recent, 5, $day );
		}

		$ids = $this->ids(
			WP_PostRatings_Stats::output(
				array(
					'source' => 'range',
					'time'   => '7 days',
				),
				'mostrated',
				0,
				false
			)
		);

		$this->assertSame( $recent, $ids[0] );
	}

	/**
	 * The range shape accepts a taxonomy filter too.
	 *
	 * No template tag combines a time range with a tag, so this shape is only
	 * reachable through the builder.
	 *
	 * @return void
	 */
	public function test_the_range_shape_accepts_a_taxonomy_filter() {
		$category = self::factory()->category->create();

		$inside  = $this->make_rated_post( 0, 0 );
		$outside = $this->make_rated_post( 0, 0 );

		wp_set_post_categories( $inside, array( $category ) );

		$this->log_vote_at( $inside, 5, 1 );
		$this->log_vote_at( $outside, 5, 1 );

		$ids = $this->ids(
			WP_PostRatings_Stats::output(
				array(
					'source'   => 'range',
					'time'     => '7 days',
					'taxonomy' => 'category',
					'terms'    => $category,
				),
				'mostrated',
				0,
				false
			)
		);

		$this->assertSame( array( $inside ), $ids );
	}

	/**
	 * Each ordering works against the range shape as well.
	 *
	 * @return void
	 */
	public function test_the_orderings_work_against_the_range_shape() {
		$low  = $this->make_rated_post( 0, 0 );
		$high = $this->make_rated_post( 0, 0 );

		$this->log_vote_at( $low, 1, 1 );
		$this->log_vote_at( $high, 5, 1 );

		$highest = $this->ids(
			WP_PostRatings_Stats::output(
				array(
					'source' => 'range',
					'order'  => 'highest',
					'time'   => '7 days',
				),
				'highestrated',
				0,
				false
			)
		);
		$lowest  = $this->ids(
			WP_PostRatings_Stats::output(
				array(
					'source' => 'range',
					'order'  => 'lowest',
					'time'   => '7 days',
				),
				'highestrated',
				0,
				false
			)
		);

		$this->assertSame( $high, $highest[0] );
		$this->assertSame( $low, $lowest[0] );
	}

	// --- caching ----------------------------------------------------------

	/**
	 * Results are cached per distinct query, not globally.
	 *
	 * Two different rankings sharing a cache entry would serve one list for
	 * the other.
	 *
	 * @return void
	 */
	public function test_different_queries_do_not_share_a_cache_entry() {
		$this->make_rated_post( 10, 10 );
		$this->make_rated_post( 2, 10 );

		$highest = WP_PostRatings_Stats::output( array( 'order' => 'highest' ), 'highestrated', 0, false );
		$lowest  = WP_PostRatings_Stats::output( array( 'order' => 'lowest' ), 'highestrated', 0, false );

		$this->assertNotSame( $highest, $lowest );
	}

	/**
	 * A list with nothing to show says so.
	 *
	 * @return void
	 */
	public function test_an_empty_ranking_says_so() {
		$this->assertStringContainsString( 'N/A', WP_PostRatings_Stats::output( array( 'mode' => 'nonexistent' ), 'highestrated', 0, false ) );
	}

	/**
	 * Output can be echoed instead of returned.
	 *
	 * @return void
	 */
	public function test_output_can_be_echoed() {
		$post_id = $this->make_rated_post( 4, 18 );

		ob_start();
		WP_PostRatings_Stats::output( array(), 'highestrated', 0, true );
		$echoed = ob_get_clean();

		$this->assertStringContainsString( '[' . $post_id . ']', $echoed );
	}
}
