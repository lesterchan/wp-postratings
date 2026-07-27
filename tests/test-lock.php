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
 * @covers Postratings_Rating::acquire_lock
 * @covers Postratings_Rating::release_lock
 * @covers Postratings_Rating::lock_file
 */
class Test_Postratings_Lock extends WP_PostRatings_TestCase {

	/**
	 * The lock file is named per site and per post.
	 *
	 * Two posts sharing one lock would serialise unrelated votes, and two sites
	 * sharing one would do it across a whole network.
	 *
	 * @return void
	 */
	public function test_lock_files_are_named_per_site_and_post() {
		$first  = Postratings_Rating::lock_file( 1 );
		$second = Postratings_Rating::lock_file( 2 );

		$this->assertNotSame( $first, $second );
		$this->assertStringContainsString( 'wp-blog-' . get_current_blog_id() . '-', $first );
		$this->assertStringContainsString( '-wp-postratings-1.lock', $first );
		$this->assertStringStartsWith( get_temp_dir(), $first );
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

		$this->assertSame( get_temp_dir() . '/custom-9.lock', Postratings_Rating::lock_file( 9 ) );
	}

	/**
	 * Acquiring the lock creates the file and hands back a handle.
	 *
	 * @return void
	 */
	public function test_acquiring_the_lock_creates_the_file() {
		$handle = Postratings_Rating::acquire_lock( 1 );

		$this->assertIsResource( $handle );
		$this->assertFileExists( Postratings_Rating::lock_file( 1 ) );

		Postratings_Rating::release_lock( $handle, 1 );
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
		$handle = Postratings_Rating::acquire_lock( 1 );
		$path   = Postratings_Rating::lock_file( 1 );

		$this->assertTrue( Postratings_Rating::release_lock( $handle, 1 ) );
		$this->assertFileDoesNotExist( $path );
	}

	/**
	 * Releasing something that is not a handle reports failure.
	 *
	 * @return void
	 */
	public function test_releasing_a_non_handle_is_refused() {
		$this->assertFalse( Postratings_Rating::release_lock( false, 1 ) );
		$this->assertFalse( Postratings_Rating::release_lock( null, 1 ) );
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
		$path = Postratings_Rating::lock_file( 1 );

		$holder = fopen( $path, 'w+' );
		$this->assertTrue( flock( $holder, LOCK_EX | LOCK_NB ) );

		$contender = fopen( $path, 'w+' );
		$this->assertFalse( flock( $contender, LOCK_EX | LOCK_NB ), 'the lock was not exclusive' );

		flock( $holder, LOCK_UN );
		fclose( $holder );
		fclose( $contender );
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
		$this->set_option( 'logging_method', 0 );

		$this->cast_vote( $post_id, 4 );

		$this->assertFileDoesNotExist( Postratings_Rating::lock_file( $post_id ) );
	}

	/**
	 * A vote that cannot take the lock is refused rather than double counted.
	 *
	 * @return void
	 */
	public function test_a_vote_that_cannot_lock_is_refused() {
		$post_id = $this->make_rated_post( 0, 0 );

		$this->set_option( 'allowtorate', 2 );
		$this->set_option( 'logging_method', 0 );

		// Hold the lock from a separate handle, as a concurrent request would.
		$holder = fopen( Postratings_Rating::lock_file( $post_id ), 'w+' );
		flock( $holder, LOCK_EX | LOCK_NB );

		$output = $this->cast_vote( $post_id, 4 );

		flock( $holder, LOCK_UN );
		fclose( $holder );

		$this->assertStringContainsString( 'Unable to obtain lock', $output );
		$this->assertSame( 0, (int) get_post_meta( $post_id, 'ratings_users', true ) );
	}

	/**
	 * Cast a vote through the endpoint's testable half.
	 *
	 * @param int $post_id Post to rate.
	 * @param int $rate    Position on the scale.
	 *
	 * @return string
	 */
	private function cast_vote( $post_id, $rate ) {
		return Postratings_Rating::process_vote( $post_id, $rate );
	}
}
