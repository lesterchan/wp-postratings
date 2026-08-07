<?php
/**
 * Tests for the `wp postratings` WP-CLI command.
 *
 * @package WP-PostRatings
 */

/**
 * The command clears ratings with no browser, no nonce and no capability check
 * in front of it -- WP-CLI runs as whoever is at the shell -- so every
 * subcommand is pinned here, and the destructive one is pinned twice: once for
 * what it removes and once for what it must leave alone.
 *
 * A rating lives in two places, the running totals in post meta and a row per
 * vote in the log, so "cleared" is only true when both tests agree.
 */
class WP_PostRatings_CLI_Test extends WP_PostRatings_TestCase {

	/**
	 * Clears everything the stand-in recorded for the previous test.
	 */
	public function set_up() {
		parent::set_up();

		WP_CLI::$successes     = array();
		WP_CLI::$warnings      = array();
		WP_CLI::$logs          = array();
		WP_CLI::$confirmations = array();
		WP_CLI::$commands      = array();
		WP_CLI::$items         = array();
	}

	/**
	 * Runs one subcommand the way WP-CLI would.
	 *
	 * @param string $subcommand Method to call.
	 * @param array  $args       Positional arguments.
	 * @param array  $assoc_args Associative arguments.
	 * @return void
	 */
	protected function run_command( $subcommand, $args = array(), $assoc_args = array() ) {
		$command = new WP_PostRatings_Command();
		$command->$subcommand( $args, $assoc_args );
	}

	/**
	 * The rows the last format_items() call was given.
	 *
	 * @return array
	 */
	protected function listed_rows() {
		$this->assertNotEmpty( WP_CLI::$items, 'The command formatted a table.' );

		$last = end( WP_CLI::$items );

		return $last['items'];
	}

	/**
	 * How many log rows a post has.
	 *
	 * @param int $post_id Post to count.
	 * @return int
	 */
	protected function log_count( $post_id ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->ratings} WHERE rating_postid = %d", $post_id )
		);
	}

	// --- registration ----------------------------------------------------

	/**
	 * The command registers under the bare noun, not the plugin slug.
	 *
	 * @return void
	 */
	public function test_the_command_registers_as_postratings() {
		if ( ! defined( 'WP_CLI' ) ) {
			define( 'WP_CLI', true );
		}

		WP_PostRatings::register_command();

		$this->assertArrayHasKey( 'postratings', WP_CLI::$commands, 'The command is registered as `wp postratings`.' );
		$this->assertSame( 'WP_PostRatings_Command', WP_CLI::$commands['postratings'], 'WP_PostRatings_Command is what handles it.' );
		$this->assertArrayNotHasKey( 'wp-postratings', WP_CLI::$commands, 'The plugin slug is not also claimed as a command.' );
	}

	// --- list ------------------------------------------------------------

	/**
	 * Listing returns the rated posts, best first.
	 *
	 * @return void
	 */
	public function test_list_returns_rated_posts_best_first() {
		$worse  = $this->make_rated_post( 4, 8 );
		$better = $this->make_rated_post( 4, 20 );

		$this->run_command( 'list_' );

		$rows = wp_list_pluck( $this->listed_rows(), 'id' );

		$this->assertSame( $better, $rows[0], 'The best rated post is listed first.' );
		$this->assertContains( $worse, $rows, 'And the other one is listed too.' );
	}

	/**
	 * A post nobody rated is left out entirely.
	 *
	 * @return void
	 */
	public function test_list_leaves_out_unrated_posts() {
		$rated   = $this->make_rated_post();
		$unrated = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$this->run_command( 'list_' );

		$rows = wp_list_pluck( $this->listed_rows(), 'id' );

		$this->assertContains( $rated, $rows, 'The rated post is listed.' );
		$this->assertNotContains( $unrated, $rows, 'A post with no rating is not listed as scoring zero.' );
	}

	/**
	 * The limit is honoured.
	 *
	 * @return void
	 */
	public function test_list_honours_the_limit() {
		$this->make_rated_post( 4, 20 );
		$this->make_rated_post( 4, 16 );
		$this->make_rated_post( 4, 12 );

		$this->run_command( 'list_', array(), array( 'limit' => 2 ) );

		$this->assertCount( 2, $this->listed_rows(), 'Only as many posts as were asked for.' );
	}

	/**
	 * A site where nothing is rated says so rather than printing an empty table.
	 *
	 * @return void
	 */
	public function test_list_with_nothing_rated_is_not_an_error() {
		$this->run_command( 'list_' );

		$this->assertNotEmpty( WP_CLI::$successes, 'Finding nothing is reported on the success channel.' );
		$this->assertEmpty( WP_CLI::$items, 'No table is printed when there is nothing to put in it.' );
	}

	// --- get -------------------------------------------------------------

	/**
	 * Getting a post returns its totals.
	 *
	 * @return void
	 */
	public function test_get_returns_the_totals() {
		$post_id = $this->make_rated_post( 4, 18 );

		$this->run_command( 'get', array( $post_id ) );

		$values = wp_list_pluck( $this->listed_rows(), 'value', 'field' );

		$this->assertSame( 4, $values['users'], 'The number of raters.' );
		$this->assertSame( 18, $values['score'], 'The total score.' );
		$this->assertSame( 4.5, $values['average'], 'And the average, unrounded.' );
	}

	/**
	 * An id that matches no post stops the command.
	 *
	 * @return void
	 */
	public function test_get_errors_on_an_unknown_post() {
		$this->expectException( RuntimeException::class );

		$this->run_command( 'get', array( 123456 ) );
	}

	// --- delete ----------------------------------------------------------

	/**
	 * Deleting a post's ratings clears both its totals and its log rows.
	 *
	 * @return void
	 */
	public function test_delete_clears_both_halves_of_a_rating() {
		$post_id = $this->make_rated_post();
		$this->log_rating( $post_id );

		$this->run_command( 'delete', array( $post_id ), array( 'yes' => true ) );

		$this->assertSame( 0, WP_PostRatings_Data::get( $post_id )['users'], 'The running totals are gone.' );
		$this->assertSame( 0, $this->log_count( $post_id ), 'And so are the log rows.' );
	}

	/**
	 * --what=logs leaves the totals every post displays alone.
	 *
	 * @return void
	 */
	public function test_delete_what_logs_keeps_the_totals() {
		$post_id = $this->make_rated_post( 4, 18 );
		$this->log_rating( $post_id );

		$this->run_command(
			'delete',
			array( $post_id ),
			array(
				'yes'  => true,
				'what' => 'logs',
			)
		);

		$this->assertSame( 0, $this->log_count( $post_id ), 'The log rows are gone.' );
		$this->assertSame( 4, WP_PostRatings_Data::get( $post_id )['users'], 'But the post still displays its rating.' );
	}

	/**
	 * --what=data leaves the log alone.
	 *
	 * @return void
	 */
	public function test_delete_what_data_keeps_the_log() {
		$post_id = $this->make_rated_post();
		$this->log_rating( $post_id );

		$this->run_command(
			'delete',
			array( $post_id ),
			array(
				'yes'  => true,
				'what' => 'data',
			)
		);

		$this->assertSame( 0, WP_PostRatings_Data::get( $post_id )['users'], 'The totals are gone.' );
		$this->assertSame( 1, $this->log_count( $post_id ), 'But the log still records who rated it.' );
	}

	/**
	 * Deleting one post's ratings leaves every other post alone.
	 *
	 * @return void
	 */
	public function test_delete_touches_only_the_posts_it_was_given() {
		$target   = $this->make_rated_post();
		$survivor = $this->make_rated_post();
		$this->log_rating( $target );
		$this->log_rating( $survivor );

		$this->run_command( 'delete', array( $target ), array( 'yes' => true ) );

		$this->assertSame( 0, WP_PostRatings_Data::get( $target )['users'], 'The named post is cleared.' );
		$this->assertSame( 4, WP_PostRatings_Data::get( $survivor )['users'], 'The other post keeps its rating.' );
		$this->assertSame( 1, $this->log_count( $survivor ), 'And keeps its log rows.' );
	}

	/**
	 * --all clears every post.
	 *
	 * @return void
	 */
	public function test_delete_all_clears_every_post() {
		$first  = $this->make_rated_post();
		$second = $this->make_rated_post();
		$this->log_rating( $first );
		$this->log_rating( $second );

		$this->run_command(
			'delete',
			array(),
			array(
				'all' => true,
				'yes' => true,
			)
		);

		$this->assertSame( 0, WP_PostRatings_Data::get( $first )['users'], 'The first post is cleared.' );
		$this->assertSame( 0, WP_PostRatings_Data::get( $second )['users'], 'And so is the second.' );
		$this->assertSame( 0, $this->log_count( $first ), 'The whole log is cleared with them.' );
	}

	/**
	 * Naming no post and passing no --all is an error, not a silent success.
	 *
	 * @return void
	 */
	public function test_delete_with_no_target_is_an_error() {
		$post_id = $this->make_rated_post();

		try {
			$this->run_command( 'delete', array(), array( 'yes' => true ) );
			$this->fail( 'A delete naming nothing stops rather than reporting success.' );
		} catch ( RuntimeException $e ) {
			unset( $e );
		}

		$this->assertSame( 4, WP_PostRatings_Data::get( $post_id )['users'], 'And nothing was cleared.' );
	}

	/**
	 * Without --yes the command asks, and a script that cannot answer clears
	 * nothing.
	 *
	 * @return void
	 */
	public function test_delete_without_yes_asks_first_and_clears_nothing() {
		$post_id = $this->make_rated_post();

		try {
			$this->run_command( 'delete', array( $post_id ) );
			$this->fail( 'The command stops at the confirmation instead of clearing.' );
		} catch ( RuntimeException $e ) {
			unset( $e );
		}

		$this->assertNotEmpty( WP_CLI::$confirmations, 'It asked before doing anything.' );
		$this->assertSame( 4, WP_PostRatings_Data::get( $post_id )['users'], 'And the rating is still there.' );
	}
}
