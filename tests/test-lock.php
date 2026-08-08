<?php
/**
 * Tests for the vote lock.
 *
 * Mirrors the equivalent suite in WP-Polls: the two plugins implement the same
 * advisory lock, so they are held to the same contract.
 *
 * @package WP-PostRatings
 */

/**
 * The per-post advisory lock around a rating.
 *
 * @covers WP_PostRatings_Rating::acquire_lock
 * @covers WP_PostRatings_Rating::release_lock
 * @covers WP_PostRatings_Rating::lock_file
 */
class WP_PostRatings_Lock_Test extends WP_PostRatings_TestCase {

	/**
	 * The lock file is named per site and per post.
	 *
	 * Two posts sharing one lock would serialise unrelated votes, and two sites
	 * sharing one would do it across a whole network.
	 *
	 * @return void
	 */
	public function test_lock_files_are_named_per_site_and_post() {
		$first  = WP_PostRatings_Rating::lock_file( 1 );
		$second = WP_PostRatings_Rating::lock_file( 2 );

		$this->assertNotSame( $first, $second, 'Two posts get two lock files.' );
		$this->assertStringContainsString( 'wp-blog-' . get_current_blog_id() . '-', $first, 'Named for the site.' );
		$this->assertStringContainsString( '-wp-postratings-1.lock', $first, 'And the post.' );
		$this->assertStringStartsWith( get_temp_dir(), $first, 'Written to the temporary directory rather than into the plugin.' );
	}

	/**
	 * The lock path is filterable.
	 *
	 * @return void
	 */
	public function test_lock_file_is_filterable() {
		add_filter(
			'wp_postratings_lock_file',
			static function ( $path, $post_id ) {
				unset( $path );
				return get_temp_dir() . '/custom-' . $post_id . '.lock';
			},
			10,
			2
		);

		$this->assertSame( get_temp_dir() . '/custom-9.lock', WP_PostRatings_Rating::lock_file( 9 ), 'The lock file is filterable.' );
	}

	/**
	 * Acquiring the lock creates the file and hands back a handle.
	 *
	 * @return void
	 */
	public function test_acquiring_the_lock_creates_the_file() {
		$handle = WP_PostRatings_Rating::acquire_lock( 1 );

		$this->assertIsResource( $handle, 'Acquiring the lock hands back an open handle.' );
		$this->assertFileExists( WP_PostRatings_Rating::lock_file( 1 ), 'Acquiring the lock creates the lock file.' );

		WP_PostRatings_Rating::release_lock( $handle, 1 );
	}

	/**
	 * Releasing the lock closes the handle and removes the file.
	 *
	 * A lock file left behind is harmless, but one left *locked* would refuse
	 * every later vote on that post for the life of the process.
	 *
	 * @return void
	 */
	public function test_releasing_the_lock_removes_the_file() {
		$handle = WP_PostRatings_Rating::acquire_lock( 1 );
		$path   = WP_PostRatings_Rating::lock_file( 1 );

		$this->assertTrue( WP_PostRatings_Rating::release_lock( $handle, 1 ), 'Releasing a held lock reports success.' );
		$this->assertFileDoesNotExist( $path, 'Releasing the lock removes the file rather than leaving it behind.' );
	}

	/**
	 * Releasing something that is not a handle reports failure.
	 *
	 * @return void
	 */
	public function test_releasing_a_non_handle_is_refused() {
		$this->assertFalse( WP_PostRatings_Rating::release_lock( false, 1 ), 'Releasing something that is not a handle is refused rather than fatal.' );
		$this->assertFalse( WP_PostRatings_Rating::release_lock( null, 1 ), 'Releasing null is refused rather than fatal.' );
	}

	/**
	 * A second holder of the same lock is turned away, not blocked.
	 *
	 * This is the whole point of the mutex: the second concurrent vote on a
	 * post has to fail fast rather than wait, so LOCK_NB has to stay. Opening
	 * the file again in the same process shares the lock, so the contending
	 * attempt is made against a separate handle.
	 *
	 * @return void
	 */
	public function test_a_contending_lock_fails_fast() {
		$path = WP_PostRatings_Rating::lock_file( 1 );

		$this->assertNotEmpty( $path, 'The lock file path is not empty, or every assertion below would be about the wrong file.' );

		$holder = WP_PostRatings_Rating::acquire_lock( 1 );

		$this->assertIsResource( $holder, 'the first attempt should take the lock' );
		$this->assertFalse( WP_PostRatings_Rating::acquire_lock( 1 ), 'the lock was not exclusive' );

		WP_PostRatings_Rating::release_lock( $holder, 1 );
	}

	/**
	 * The lock is taken and given back around a real vote.
	 *
	 * Before 2.0.0 the release was unreachable: every branch below it exits, so
	 * each rating left its lock file in the temp directory forever.
	 *
	 * @return void
	 */
	public function test_voting_leaves_no_lock_behind() {
		$post_id = $this->make_rated_post( 0, 0 );

		$this->set_option( 'allowtorate', 2 );
		$this->set_option( 'check_method', 0 );

		$this->cast_vote( $post_id, 4 );

		$this->assertFileDoesNotExist( WP_PostRatings_Rating::lock_file( $post_id ), 'A completed vote leaves no lock file behind.' );
	}

	/**
	 * A vote that cannot take the lock is refused rather than double counted.
	 *
	 * @return void
	 */
	public function test_a_vote_that_cannot_lock_is_refused() {
		$post_id = $this->make_rated_post( 0, 0 );

		$this->set_option( 'allowtorate', 2 );
		$this->set_option( 'check_method', 0 );

		// Hold the lock from a separate handle, as a concurrent request would.
		$holder = WP_PostRatings_Rating::acquire_lock( $post_id );

		$output = $this->cast_vote( $post_id, 4 );

		WP_PostRatings_Rating::release_lock( $holder, $post_id );

		$this->assertStringContainsString( 'Unable to obtain lock', $output, 'A vote that cannot take the lock says so.' );
		$this->assertSame( 0, (int) get_post_meta( $post_id, 'ratings_users', true ), 'And records nothing.' );
	}

	/**
	 * Cast a vote through the endpoint's testable half.
	 *
	 * @param int $post_id Post to rate.
	 * @param int $rate    Position on the scale.
	 *
	 * process_vote() throws on a refusal now rather than returning the reason,
	 * so this catches and hands the message back -- which is what every caller
	 * here was already asserting on.
	 *
	 * @return string
	 */
	private function cast_vote( $post_id, $rate ) {
		try {
			return WP_PostRatings_Rating::process_vote( $post_id, $rate );
		} catch ( InvalidArgumentException $e ) {
			return $e->getMessage();
		}
	}
}
