<?php
/**
 * Tests for comment author ratings.
 *
 * This feature was silently broken for years: the lookup map is keyed by the
 * hashed IP the log table stores, but was read with a raw address, so the IP
 * fallback never matched once ratings began being hashed.
 *
 * @package WP-PostRatings
 */

/**
 * Collecting, looking up and rendering a commenter's rating.
 *
 * @covers WP_PostRatings_Comments
 */
class WP_PostRatings_Comments_Test extends WP_PostRatings_TestCase {

	/**
	 * Post being commented on.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Set up a post with comments and ratings.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->post_id = $this->make_rated_post( 2, 7 );
	}

	/**
	 * Put the given post into the loop so collect() has something to read.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return void
	 */
	private function enter_loop( $post_id ) {
		global $post;

		$post = get_post( $post_id );
		setup_postdata( $post );

		WP_PostRatings_Comments::collect();
	}

	/**
	 * Make a comment the current one.
	 *
	 * @param string $author Comment author name.
	 * @param string $ip     Comment author IP.
	 *
	 * @return void
	 */
	private function enter_comment( $author, $ip = '203.0.113.50' ) {
		global $comment;

		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'   => $this->post_id,
				'comment_author'    => $author,
				'comment_author_IP' => $ip,
				'comment_type'      => 'comment',
				'comment_approved'  => 1,
			)
		);

		$comment = get_comment( $comment_id );
	}

	/**
	 * A rating logged under the author's name is found.
	 *
	 * @return void
	 */
	public function test_a_rating_is_matched_by_username() {
		$this->log_rating( $this->post_id, 4, 'Alice' );
		$this->enter_loop( $this->post_id );

		$this->assertSame( 4, WP_PostRatings_Comments::rating_for( 'Alice' ), 'A logged in rating is matched by username.' );
	}

	/**
	 * A rating logged as a guest is matched by the commenter's hashed IP.
	 *
	 * This is the path that had been dead: the map is keyed by wp_hash( $ip )
	 * because that is what the log table stores, and the lookup used the raw
	 * address.
	 *
	 * @return void
	 */
	public function test_a_guest_rating_is_matched_by_hashed_ip() {
		$this->log_rating( $this->post_id, 5, 'Guest', '203.0.113.50' );
		$this->enter_loop( $this->post_id );
		$this->enter_comment( 'Someone Else', '203.0.113.50' );

		$this->assertSame( 5, WP_PostRatings_Comments::rating_for( 'Someone Else' ), 'And a guest rating by the hash of the address.' );
	}

	/**
	 * The raw address is never used as a key.
	 *
	 * Pins the bug directly: were the map keyed on the unhashed address, this
	 * lookup would succeed.
	 *
	 * @return void
	 */
	public function test_the_raw_address_is_not_a_key() {
		$this->log_rating( $this->post_id, 5, 'Guest', '203.0.113.50' );
		$this->enter_loop( $this->post_id );

		$this->assertSame( 0, WP_PostRatings_Comments::rating_for( '203.0.113.50' ), 'The raw address is not a key, so it cannot be looked up with.' );
	}

	/**
	 * Logging by username ignores the IP entirely.
	 *
	 * @return void
	 */
	public function test_logging_by_username_ignores_the_ip() {
		$this->set_options( array( 'check_method' => 4 ) );

		$this->log_rating( $this->post_id, 5, 'Guest', '203.0.113.50' );
		$this->enter_loop( $this->post_id );
		$this->enter_comment( 'Someone Else', '203.0.113.50' );

		$this->assertSame( 0, WP_PostRatings_Comments::rating_for( 'Someone Else' ), 'Logging by username ignores the address entirely.' );
	}

	/**
	 * A commenter who never rated gets nothing.
	 *
	 * @return void
	 */
	public function test_a_commenter_who_never_rated_gets_nothing() {
		$this->enter_loop( $this->post_id );

		$this->assertSame( 0, WP_PostRatings_Comments::rating_for( 'Nobody' ), 'A commenter who never rated has no rating.' );
	}

	/**
	 * A username stored slashed is matched unslashed.
	 *
	 * Rows written before 2.0.0 carry the backslash.
	 *
	 * @return void
	 */
	public function test_a_slashed_username_is_matched() {
		$this->log_rating( $this->post_id, 3, "O\\'Brien" );
		$this->enter_loop( $this->post_id );

		$this->assertSame( 3, WP_PostRatings_Comments::rating_for( "O'Brien" ), 'A slashed username is matched, so an apostrophe does not lose the rating.' );
	}

	/**
	 * Looking up before the loop has run does not warn.
	 *
	 * @return void
	 */
	public function test_a_lookup_without_a_loop_is_safe() {
		$this->assertIsInt( WP_PostRatings_Comments::rating_for( 'Anyone' ), 'A lookup outside the loop answers with a number rather than warning on a missing comment.' );
	}

	/**
	 * The rendered strip shows the rating the author gave.
	 *
	 * @return void
	 */
	public function test_the_rendered_strip_reflects_the_rating() {
		$this->log_rating( $this->post_id, 4, 'Alice' );
		$this->enter_loop( $this->post_id );
		$this->enter_comment( 'Alice' );

		$html = WP_PostRatings_Comments::author_ratings();

		$this->assertStringContainsString( 'wp-postratings-item', $html, 'The strip is rendered.' );
		$this->assertStringContainsString( 'Alice gives a rating of 4', $html, 'Reading as the rating that was given.' );
	}

	/**
	 * A commenter with no rating renders nothing at all.
	 *
	 * @return void
	 */
	public function test_no_rating_renders_nothing() {
		$this->enter_loop( $this->post_id );
		$this->enter_comment( 'Nobody' );

		$this->assertSame( '', WP_PostRatings_Comments::author_ratings(), 'No rating renders nothing rather than an empty strip.' );
	}

	/**
	 * The comment filter is off unless a theme opts in.
	 *
	 * @return void
	 */
	public function test_the_comment_filter_is_opt_in() {
		$this->log_rating( $this->post_id, 4, 'Alice' );
		$this->enter_loop( $this->post_id );
		$this->enter_comment( 'Alice' );

		$this->assertSame( 'Body.', WP_PostRatings_Comments::append_to_comment( 'Body.' ), 'Without the option the comment body comes back untouched.' );

		add_filter( 'wp_postratings_display_comment_author_ratings', '__return_true' );

		$this->assertStringContainsString( 'wp-postratings-comment-author', WP_PostRatings_Comments::append_to_comment( 'Body.' ), 'With it the strip is appended.' );
	}

	/**
	 * A commenter's name is escaped in the appended markup.
	 *
	 * @return void
	 */
	public function test_the_appended_author_name_is_escaped() {
		add_filter( 'wp_postratings_display_comment_author_ratings', '__return_true' );

		$this->enter_loop( $this->post_id );
		$this->enter_comment( '<script>alert(1)</script>' );

		$html = WP_PostRatings_Comments::append_to_comment( 'Body.' );

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html, 'An author name carrying markup is not rendered as markup.' );
		$this->assertStringContainsString( '&lt;script&gt;', $html, 'It is escaped and shown as text instead.' );
	}

	/**
	 * The unprefixed global the procedural version kept is gone.
	 *
	 * @return void
	 */
	public function test_the_legacy_global_is_no_longer_written() {
		$this->log_rating( $this->post_id, 4, 'Alice' );
		$this->enter_loop( $this->post_id );

		$this->assertArrayNotHasKey( 'comment_authors_ratings', $GLOBALS, 'an unprefixed global survived 2.0.0' );
		$this->assertSame( 4, WP_PostRatings_Comments::rating_for( 'Alice' ), 'the map itself should still answer' );
	}

	/**
	 * Entering a second post clears the previous post's ratings.
	 *
	 * A stale map would attribute one post's ratings to another's commenters.
	 *
	 * @return void
	 */
	public function test_the_map_is_reset_between_posts() {
		$this->log_rating( $this->post_id, 4, 'Alice' );
		$this->enter_loop( $this->post_id );

		$other = $this->make_rated_post( 0, 0 );
		$this->enter_loop( $other );

		$this->assertSame( 0, WP_PostRatings_Comments::rating_for( 'Alice' ), 'The map is rebuilt per post, so a rating cannot answer for another post.' );
	}
}
