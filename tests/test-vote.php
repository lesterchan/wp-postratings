<?php
/**
 * Tests for casting a vote.
 *
 * The endpoint is reachable logged out, so every guard here protects a public
 * write path.
 *
 * @package WP-PostRatings
 */

/**
 * Vote validation, recording and the logging methods.
 *
 * @covers WP_PostRatings_Rating::process_vote
 * @covers WP_PostRatings_Rating::can_rate
 * @covers WP_PostRatings_Rating::has_rated
 */
class WP_PostRatings_Vote_Test extends WP_PostRatings_TestCase {

	/**
	 * Set up an open, unlogged install so each test states its own guard.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->set_option( 'allowtorate', 2 );
		$this->set_option( 'logging_method', 0 );
	}

	/**
	 * A vote updates the three meta values.
	 *
	 * @return void
	 */
	public function test_a_vote_is_recorded() {
		$post_id = $this->make_rated_post( 0, 0 );

		WP_PostRatings_Rating::process_vote( $post_id, 4 );

		$this->assertSame( 1, (int) get_post_meta( $post_id, 'ratings_users', true ) );
		$this->assertSame( 4, (int) get_post_meta( $post_id, 'ratings_score', true ) );
		$this->assertSame( '4', (string) get_post_meta( $post_id, 'ratings_average', true ) );
	}

	/**
	 * A second vote accumulates onto the first.
	 *
	 * @return void
	 */
	public function test_votes_accumulate() {
		$post_id = $this->make_rated_post( 4, 18 );

		WP_PostRatings_Rating::process_vote( $post_id, 2 );

		$this->assertSame( 5, (int) get_post_meta( $post_id, 'ratings_users', true ) );
		$this->assertSame( 20, (int) get_post_meta( $post_id, 'ratings_score', true ) );
		$this->assertSame( '4', (string) get_post_meta( $post_id, 'ratings_average', true ) );
	}

	/**
	 * A rating past the top of the scale is refused before anything is written.
	 *
	 * It used to be clamped to 0 and then read as $ratings_value[-1], which
	 * warned on PHP 8 and logged a vote worth nothing.
	 *
	 * @return void
	 */
	public function test_an_out_of_range_rating_is_refused() {
		$post_id = $this->make_rated_post( 0, 0 );

		$output = WP_PostRatings_Rating::process_vote( $post_id, 99 );

		$this->assertStringContainsString( 'Invalid Rating', $output );
		$this->assertSame( 0, (int) get_post_meta( $post_id, 'ratings_users', true ) );
	}

	/**
	 * A zero or negative rating writes nothing.
	 *
	 * @return void
	 */
	public function test_a_non_positive_rating_writes_nothing() {
		$post_id = $this->make_rated_post( 0, 0 );

		$this->assertSame( '', WP_PostRatings_Rating::process_vote( $post_id, 0 ) );
		$this->assertSame( '', WP_PostRatings_Rating::process_vote( $post_id, -3 ) );
		$this->assertSame( 0, (int) get_post_meta( $post_id, 'ratings_users', true ) );
	}

	/**
	 * A post that does not exist is refused.
	 *
	 * @return void
	 */
	public function test_an_unknown_post_is_refused() {
		$this->assertStringContainsString( 'Invalid Post ID', WP_PostRatings_Rating::process_vote( 999999, 3 ) );
	}

	/**
	 * A crawler's vote is dropped.
	 *
	 * @return void
	 */
	public function test_a_bot_is_turned_away() {
		$post_id = $this->make_rated_post( 0, 0 );

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; Googlebot/2.1)';

		$this->assertSame( '', WP_PostRatings_Rating::process_vote( $post_id, 4 ) );
		$this->assertSame( 0, (int) get_post_meta( $post_id, 'ratings_users', true ) );
	}

	/**
	 * An absent user agent is not a fatal.
	 *
	 * @return void
	 */
	public function test_a_missing_user_agent_is_tolerated() {
		unset( $_SERVER['HTTP_USER_AGENT'] );

		$post_id = $this->make_rated_post( 0, 0 );

		WP_PostRatings_Rating::process_vote( $post_id, 4 );

		$this->assertSame( 1, (int) get_post_meta( $post_id, 'ratings_users', true ) );
	}

	// --- who may rate -----------------------------------------------------

	/**
	 * "Logged-in users only" turns a guest away.
	 *
	 * @return void
	 */
	public function test_logged_in_only_refuses_a_guest() {
		$this->set_option( 'allowtorate', 1 );
		wp_set_current_user( 0 );

		$post_id = $this->make_rated_post( 0, 0 );

		$this->assertFalse( WP_PostRatings_Rating::can_rate() );
		$this->assertSame( '', WP_PostRatings_Rating::process_vote( $post_id, 4 ) );
		$this->assertSame( 0, (int) get_post_meta( $post_id, 'ratings_users', true ) );
	}

	/**
	 * "Guests only" turns a logged-in user away.
	 *
	 * @return void
	 */
	public function test_guests_only_refuses_a_member() {
		$this->set_option( 'allowtorate', 0 );
		wp_set_current_user( self::factory()->user->create() );

		$this->assertFalse( WP_PostRatings_Rating::can_rate() );
	}

	// --- logging methods --------------------------------------------------

	/**
	 * Logging by IP blocks a second vote from the same address.
	 *
	 * @return void
	 */
	public function test_logging_by_ip_blocks_a_repeat_vote() {
		$this->set_option( 'logging_method', 2 );

		$post_id = $this->make_rated_post( 0, 0 );

		WP_PostRatings_Rating::process_vote( $post_id, 4 );
		$this->assertSame( 1, (int) get_post_meta( $post_id, 'ratings_users', true ) );

		$output = WP_PostRatings_Rating::process_vote( $post_id, 5 );

		$this->assertStringContainsString( 'Already Rated', $output );
		$this->assertSame( 1, (int) get_post_meta( $post_id, 'ratings_users', true ) );
	}

	/**
	 * A different address may still vote.
	 *
	 * @return void
	 */
	public function test_logging_by_ip_allows_a_different_address() {
		$this->set_option( 'logging_method', 2 );

		$post_id = $this->make_rated_post( 0, 0 );

		WP_PostRatings_Rating::process_vote( $post_id, 4 );

		$_SERVER['REMOTE_ADDR'] = '198.51.100.99';
		WP_PostRatings_Rating::process_vote( $post_id, 5 );

		$this->assertSame( 2, (int) get_post_meta( $post_id, 'ratings_users', true ) );
	}

	/**
	 * Logging by IP writes a row keyed on the hashed address.
	 *
	 * @return void
	 */
	public function test_the_log_row_stores_a_hashed_address() {
		global $wpdb;

		$this->set_option( 'logging_method', 2 );

		$post_id = $this->make_rated_post( 0, 0 );
		WP_PostRatings_Rating::process_vote( $post_id, 4 );

		$stored = $wpdb->get_var( "SELECT rating_ip FROM {$wpdb->ratings}" );

		$this->assertSame( wp_hash( '203.0.113.1' ), $stored );
		$this->assertStringNotContainsString( '203.0.113.1', (string) $stored );
	}

	/**
	 * "Do not log" writes no row and does not block a repeat.
	 *
	 * @return void
	 */
	public function test_not_logging_writes_no_row() {
		global $wpdb;

		$post_id = $this->make_rated_post( 0, 0 );

		WP_PostRatings_Rating::process_vote( $post_id, 4 );

		$this->assertSame( 0, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->ratings}" ) );
	}

	/**
	 * Logging by username blocks the same user twice.
	 *
	 * @return void
	 */
	public function test_logging_by_username_blocks_the_same_user() {
		$this->set_option( 'logging_method', 4 );
		wp_set_current_user( self::factory()->user->create() );

		$post_id = $this->make_rated_post( 0, 0 );

		WP_PostRatings_Rating::process_vote( $post_id, 4 );
		$output = WP_PostRatings_Rating::process_vote( $post_id, 5 );

		$this->assertStringContainsString( 'Already Rated', $output );
		$this->assertSame( 1, (int) get_post_meta( $post_id, 'ratings_users', true ) );
	}

	/**
	 * The stored post title is not double-slashed.
	 *
	 * Slashing ran before $wpdb->prepare(), so a quoted title was stored with
	 * a literal backslash.
	 *
	 * @return void
	 */
	public function test_the_logged_title_is_not_double_slashed() {
		global $wpdb;

		$this->set_option( 'logging_method', 2 );

		$post_id = self::factory()->post->create(
			array(
				'post_title'  => "O'Brien's post",
				'post_status' => 'publish',
			)
		);

		WP_PostRatings_Rating::process_vote( $post_id, 4 );

		$this->assertSame( "O'Brien's post", $wpdb->get_var( "SELECT rating_posttitle FROM {$wpdb->ratings}" ) );
	}

	/**
	 * The renamed rate action fires with its documented arguments.
	 *
	 * @return void
	 */
	public function test_the_rate_post_action_fires_under_its_prefixed_name() {
		$seen = array();

		add_action(
			'wp_postratings_rate_post',
			static function ( $user_id, $post_id, $value ) use ( &$seen ) {
				$seen = array( $user_id, $post_id, $value );
			},
			10,
			3
		);

		$post_id = $this->make_rated_post( 0, 0 );
		WP_PostRatings_Rating::process_vote( $post_id, 3 );

		$this->assertSame( array( 0, $post_id, 3 ), $seen, 'wp_postratings_rate_post did not fire' );
	}

	/**
	 * The unprefixed name it replaced is gone rather than deprecated.
	 *
	 * @return void
	 */
	public function test_the_unprefixed_rate_post_action_no_longer_fires() {
		$fired = false;

		add_action(
			'rate_post',
			static function () use ( &$fired ) {
				$fired = true;
			}
		);

		$post_id = $this->make_rated_post( 0, 0 );
		WP_PostRatings_Rating::process_vote( $post_id, 3 );

		$this->assertFalse( $fired, 'rate_post was dropped outright in 2.0.0, with no shim' );
	}
}
