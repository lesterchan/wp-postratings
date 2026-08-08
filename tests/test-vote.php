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
		$this->set_option( 'check_method', 0 );
	}

	/**
	 * A vote updates the three meta values.
	 *
	 * @return void
	 */
	public function test_a_vote_is_recorded() {
		$post_id = $this->make_rated_post( 0, 0 );

		WP_PostRatings_Rating::process_vote( $post_id, 4 );

		$this->assertSame( 1, (int) get_post_meta( $post_id, 'ratings_users', true ), 'A vote adds a voter.' );
		$this->assertSame( 4, (int) get_post_meta( $post_id, 'ratings_score', true ), 'Its rating to the score.' );
		$this->assertSame( '4', (string) get_post_meta( $post_id, 'ratings_average', true ), 'And the average follows from the two.' );
	}

	/**
	 * A second vote accumulates onto the first.
	 *
	 * @return void
	 */
	public function test_votes_accumulate() {
		$post_id = $this->make_rated_post( 4, 18 );

		WP_PostRatings_Rating::process_vote( $post_id, 2 );

		$this->assertSame( 5, (int) get_post_meta( $post_id, 'ratings_users', true ), 'Five votes are five voters.' );
		$this->assertSame( 20, (int) get_post_meta( $post_id, 'ratings_score', true ), 'Their ratings added up.' );
		$this->assertSame( '4', (string) get_post_meta( $post_id, 'ratings_average', true ), 'And the average of them.' );
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

		$output = $this->refusal( $post_id, 99 );

		$this->assertStringContainsString( 'Invalid Rating', $output, 'A rating off the scale is refused with a reason.' );
		$this->assertSame( 0, (int) get_post_meta( $post_id, 'ratings_users', true ), 'And nothing is recorded.' );
	}

	/**
	 * A zero or negative rating writes nothing.
	 *
	 * @return void
	 */
	public function test_a_non_positive_rating_writes_nothing() {
		$post_id = $this->make_rated_post( 0, 0 );

		$this->assertSame( '(silent)', $this->refusal( $post_id, 0 ), 'A rating of zero writes nothing, and says nothing.' );
		$this->assertSame( '(silent)', $this->refusal( $post_id, -3 ), 'Nor does a negative one.' );
		$this->assertSame( 0, (int) get_post_meta( $post_id, 'ratings_users', true ), 'Leaving the post unrated.' );
	}

	/**
	 * A post that does not exist is refused.
	 *
	 * @return void
	 */
	public function test_an_unknown_post_is_refused() {
		$this->assertStringContainsString( 'Invalid Post ID', $this->refusal( 999999, 3 ), 'An unknown post is refused with a reason.' );
	}

	/**
	 * A crawler's vote is dropped.
	 *
	 * @return void
	 */
	public function test_a_bot_is_turned_away() {
		$post_id = $this->make_rated_post( 0, 0 );

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; Googlebot/2.1)';

		$this->assertSame( '(silent)', $this->refusal( $post_id, 4 ), 'A bot is turned away, without being told why.' );
		$this->assertSame( 0, (int) get_post_meta( $post_id, 'ratings_users', true ), 'And records nothing.' );
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

		$this->assertSame( 1, (int) get_post_meta( $post_id, 'ratings_users', true ), 'A missing user agent is not treated as a bot.' );
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

		$this->assertFalse( WP_PostRatings_Rating::can_rate(), 'With logged-in-only set, a guest is refused.' );
		$this->assertSame( '(silent)', $this->refusal( $post_id, 4 ), 'With logged in voting only, a guest is refused.' );
		$this->assertSame( 0, (int) get_post_meta( $post_id, 'ratings_users', true ), 'And records nothing.' );
	}

	/**
	 * "Guests only" turns a logged-in user away.
	 *
	 * @return void
	 */
	public function test_guests_only_refuses_a_member() {
		$this->set_option( 'allowtorate', 0 );
		wp_set_current_user( self::factory()->user->create() );

		$this->assertFalse( WP_PostRatings_Rating::can_rate(), 'With guests-only set, a member is refused.' );
	}

	// --- logging methods --------------------------------------------------

	/**
	 * Logging by IP blocks a second vote from the same address.
	 *
	 * @return void
	 */
	public function test_logging_by_ip_blocks_a_repeat_vote() {
		$this->set_option( 'check_method', 2 );

		$post_id = $this->make_rated_post( 0, 0 );

		WP_PostRatings_Rating::process_vote( $post_id, 4 );
		$this->assertSame( 1, (int) get_post_meta( $post_id, 'ratings_users', true ), 'The first vote from an address is recorded.' );

		$output = $this->refusal( $post_id, 5 );

		$this->assertStringContainsString( 'Already Rated', $output, 'The second is refused with a reason.' );
		$this->assertSame( 1, (int) get_post_meta( $post_id, 'ratings_users', true ), 'And the count stays where it was.' );
	}

	/**
	 * A different address may still vote.
	 *
	 * @return void
	 */
	public function test_logging_by_ip_allows_a_different_address() {
		$this->set_option( 'check_method', 2 );

		$post_id = $this->make_rated_post( 0, 0 );

		WP_PostRatings_Rating::process_vote( $post_id, 4 );

		$_SERVER['REMOTE_ADDR'] = '198.51.100.99';
		WP_PostRatings_Rating::process_vote( $post_id, 5 );

		$this->assertSame( 2, (int) get_post_meta( $post_id, 'ratings_users', true ), 'A different address is a different voter.' );
	}

	/**
	 * Logging by IP writes a row keyed on the hashed address.
	 *
	 * @return void
	 */
	public function test_the_log_row_stores_a_hashed_address() {
		global $wpdb;

		$this->set_option( 'check_method', 2 );

		$post_id = $this->make_rated_post( 0, 0 );
		WP_PostRatings_Rating::process_vote( $post_id, 4 );

		$stored = $wpdb->get_var( "SELECT rating_ip FROM {$wpdb->ratings}" );

		$this->assertSame( wp_hash( '203.0.113.1' ), $stored, 'The stored address is the hash of it.' );
		$this->assertStringNotContainsString( '203.0.113.1', (string) $stored, 'And the address itself is nowhere in the row.' );
	}

	/**
	 * "Do Not Check" lets a visitor rate again, and still records both votes.
	 *
	 * Until 2.0.0 these were one setting: the lighter checks wrote no row, so a
	 * site that only wanted repeat voting allowed also lost its ratings log, its
	 * logs screen and its WP-Stats figures, with nothing on the screen saying so.
	 *
	 * @return void
	 */
	public function test_not_checking_allows_a_repeat_and_logs_both() {
		global $wpdb;

		$post_id = $this->make_rated_post( 0, 0 );

		WP_PostRatings_Rating::process_vote( $post_id, 4 );
		$output = $this->refusal( $post_id, 5 );

		$this->assertStringNotContainsString( 'Already Rated', $output, 'not checking still blocked a repeat vote' );
		$this->assertSame( 2, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->ratings}" ), 'both votes should be in the log' );
	}

	/**
	 * A row is written whatever the repeat-vote check is set to.
	 *
	 * @return void
	 */
	public function test_every_check_method_logs_the_vote() {
		global $wpdb;

		foreach ( array( 0, 1, 2, 3, 4 ) as $method ) {
			$this->set_option( 'check_method', $method );

			$wpdb->query( "DELETE FROM {$wpdb->ratings}" );

			$post_id = $this->make_rated_post( 0, 0 );

			WP_PostRatings_Rating::process_vote( $post_id, 4 );

			$this->assertSame(
				1,
				(int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->ratings}" ),
				'check method ' . $method . ' wrote no log row'
			);
		}
	}

	/**
	 * A site that would rather not keep the record can say so.
	 *
	 * @return void
	 */
	public function test_the_log_filter_can_turn_the_row_off() {
		global $wpdb;

		add_filter( 'wp_postratings_log_rating', '__return_false' );

		$post_id = $this->make_rated_post( 0, 0 );

		WP_PostRatings_Rating::process_vote( $post_id, 4 );

		remove_filter( 'wp_postratings_log_rating', '__return_false' );

		$this->assertSame( 0, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->ratings}" ), 'the filter did not stop the log row' );
		$this->assertSame( 1, (int) get_post_meta( $post_id, 'ratings_users', true ), 'the vote itself should still count' );
	}

	/**
	 * Logging by username blocks the same user twice.
	 *
	 * @return void
	 */
	public function test_logging_by_username_blocks_the_same_user() {
		$this->set_option( 'check_method', 4 );
		wp_set_current_user( self::factory()->user->create() );

		$post_id = $this->make_rated_post( 0, 0 );

		WP_PostRatings_Rating::process_vote( $post_id, 4 );
		$output = $this->refusal( $post_id, 5 );

		$this->assertStringContainsString( 'Already Rated', $output, 'A second vote from the same user is refused with a reason.' );
		$this->assertSame( 1, (int) get_post_meta( $post_id, 'ratings_users', true ), 'And the count stays where it was.' );
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

		$this->set_option( 'check_method', 2 );

		$post_id = self::factory()->post->create(
			array(
				'post_title'  => "O'Brien's post",
				'post_status' => 'publish',
			)
		);

		WP_PostRatings_Rating::process_vote( $post_id, 4 );

		$this->assertSame( "O'Brien's post", $wpdb->get_var( "SELECT rating_posttitle FROM {$wpdb->ratings}" ), 'The logged title is unslashed once, not left doubled.' );
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
