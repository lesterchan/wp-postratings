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
 * @covers WP_PostRatings_Rating::is_ratable
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
		$this->assertStringContainsString( 'Cannot Be Rated', $this->refusal( 999999, 3 ), 'An unknown post is refused with a reason.' );
	}

	/**
	 * The old guard accepted every other row in wp_posts, so an unauthenticated
	 * visitor could seed a rating on a post nobody has
	 * published -- it then arrived already rated on the day it went live -- and
	 * record() copies the unpublished title into the log table, where the Logs
	 * screen shows it.
	 *
	 * @dataProvider data_unratable_rows
	 *
	 * @param array  $post_args Arguments for the row to try.
	 * @param string $why       What the row stands for.
	 * @return void
	 */
	public function test_a_post_nobody_can_read_cannot_be_rated( array $post_args, $why ) {
		wp_set_current_user( 0 );

		// Authored by somebody, so that the refusal is the status doing the
		// work rather than a post_author of 0 matching a logged-out 0.
		$post_args['post_author'] = self::factory()->user->create( array( 'role' => 'editor' ) );

		$post_id = self::factory()->post->create( $post_args );

		$this->assertStringContainsString( 'Cannot Be Rated', $this->refusal( $post_id, 3 ), $why );
		$this->assertSame( '', get_post_meta( $post_id, 'ratings_users', true ), 'And no rating meta is seeded on it.' );
	}

	/**
	 * Rows that exist and must not be ratable.
	 *
	 * @return array
	 */
	public function data_unratable_rows() {
		return array(
			'a draft'       => array( array( 'post_status' => 'draft' ), 'A draft is not published.' ),
			'a pending'     => array( array( 'post_status' => 'pending' ), 'Nor is a pending post.' ),
			'a private'     => array( array( 'post_status' => 'private' ), 'A private post is not publicly viewable.' ),
			'a trashed'     => array( array( 'post_status' => 'trash' ), 'Nor is one in the trash.' ),
			'an auto-draft' => array( array( 'post_status' => 'auto-draft' ), 'An auto-draft is not something anybody has read.' ),
		);
	}

	public function test_an_ordinary_published_post_is_still_ratable() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		WP_PostRatings_Rating::process_vote( $post_id, 4 );

		$this->assertSame( '1', get_post_meta( $post_id, 'ratings_users', true ), 'The feature still works for the posts it is for.' );
	}

	/**
	 * Somebody who can already read the post may rate it, published or not.
	 *
	 * Sites rate their own unpublished posts -- an editorial queue where the
	 * readers are the editors -- and 2.0.0 refused every one of them, because
	 * the guard asked whether the post was public and never who was asking.
	 *
	 * @dataProvider data_unpublished_rows
	 *
	 * @param array  $post_args Arguments for the row to try.
	 * @param string $why       What the status stands for.
	 * @return void
	 */
	public function test_an_editor_may_rate_an_unpublished_post( array $post_args, $why ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$post_id = self::factory()->post->create( $post_args );

		$this->assertSame( $post_args['post_status'], get_post_status( $post_id ), 'The row is in the status the case is about.' );

		WP_PostRatings_Rating::process_vote( $post_id, 4 );

		$this->assertSame( '1', get_post_meta( $post_id, 'ratings_users', true ), $why );
	}

	/**
	 * Statuses an editorial queue actually uses.
	 *
	 * A scheduled post carries its date, because inserting one dated in the past
	 * publishes it and the case would then prove nothing.
	 *
	 * @return array
	 */
	public function data_unpublished_rows() {
		return array(
			'a draft'     => array( array( 'post_status' => 'draft' ), 'An editor may rate a draft.' ),
			'a pending'   => array( array( 'post_status' => 'pending' ), 'And one awaiting review.' ),
			'a private'   => array( array( 'post_status' => 'private' ), 'And a private post, which is published to exactly them.' ),
			'a scheduled' => array(
				array(
					'post_status' => 'future',
					'post_date'   => '2099-01-01 00:00:00',
				),
				'And one that is written and waiting for its date.',
			),
		);
	}

	/**
	 * Being logged in is not the same as being able to read the post.
	 *
	 * @return void
	 */
	public function test_a_subscriber_may_not_rate_a_draft() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_author' => self::factory()->user->create( array( 'role' => 'editor' ) ),
			)
		);

		$this->assertStringContainsString( 'Cannot Be Rated', $this->refusal( $post_id, 3 ), 'A subscriber cannot read somebody else\'s draft, so cannot rate it.' );
		$this->assertSame( '', get_post_meta( $post_id, 'ratings_users', true ), 'And seeds nothing on it.' );
	}

	/**
	 * The filter has the last word in both directions.
	 *
	 * @return void
	 */
	public function test_the_ratable_filter_decides() {
		$published = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$draft     = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		add_filter( 'wp_postratings_is_ratable', '__return_false' );
		$this->assertStringContainsString( 'Cannot Be Rated', $this->refusal( $published, 3 ), 'A filter can refuse a post the plugin would have allowed.' );
		remove_filter( 'wp_postratings_is_ratable', '__return_false' );

		add_filter( 'wp_postratings_is_ratable', '__return_true' );
		WP_PostRatings_Rating::process_vote( $draft, 4 );
		remove_filter( 'wp_postratings_is_ratable', '__return_true' );

		$this->assertSame( '1', get_post_meta( $draft, 'ratings_users', true ), 'And allow one it would have refused.' );
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
