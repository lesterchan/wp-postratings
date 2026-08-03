<?php
/**
 * Tests for the destructive admin actions and the settings AJAX endpoint.
 *
 * These delete rating logs and rating data. Nothing previously checked that
 * they turn away a user without the capability, that they verify their nonce,
 * or that they delete the rows they were asked to and no others.
 *
 * @package WP-PostRatings
 */

/**
 * Bulk delete, the delete data/logs form, and the rating-fields endpoint.
 *
 * @covers WP_PostRatings_Admin::load_manage
 * @covers WP_PostRatings_Settings::ajax_rating_fields
 */
class WP_PostRatings_Admin_Writes_Test extends WP_PostRatings_TestCase {

	/**
	 * Make the endpoints reachable from a test.
	 *
	 * The check_ajax_referer() helper calls a bare die() when wp_doing_ajax() is false,
	 * which no test can catch and which takes the runner down with it. Telling
	 * it this is an AJAX request routes the failure through wp_die(), whose
	 * AJAX handler is then replaced with one that throws.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		wp_set_current_user( $this->create_admin() );
		wp_get_current_user()->add_cap( WP_PostRatings_Settings::capability() );

		require_once ABSPATH . 'wp-admin/includes/admin.php';

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function ( $message ) {
					throw new WPDieException( is_scalar( $message ) ? (string) $message : '' );
				};
			}
		);
		add_filter(
			'wp_die_handler',
			static function () {
				return static function ( $message ) {
					throw new WPDieException( is_scalar( $message ) ? (string) $message : '' );
				};
			}
		);

		// handle_actions() redirects and then exits, which would take the runner
		// with it, so the filter throws to unwind before the exit is reached.
		add_filter( 'wp_redirect', array( $this, 'capture_redirect' ), 10, 1 );
	}

	/**
	 * Redirect target captured from handle_actions().
	 *
	 * @var string
	 */
	private $redirected_to = '';

	/**
	 * Record the redirect, then unwind before the exit that follows it.
	 *
	 * @param string $location Redirect target.
	 *
	 * @return void
	 * @throws WPDieException Always, to stop handle_actions() reaching exit().
	 */
	public function capture_redirect( $location ) {
		$this->redirected_to = $location;

		throw new WPDieException( 'redirect' );
	}

	/**
	 * Number of rows in the log table.
	 *
	 * @return int
	 */
	private function log_count() {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->ratings}" );
	}

	/**
	 * Drive the log screen's action handling for a request.
	 *
	 * @param array $get $_GET for the request.
	 * @param array $post $_POST for the request.
	 *
	 * @return void
	 */
	private function handle( $get = array(), $post = array() ) {
		$_GET     = $get;
		$_POST    = $post;
		$_REQUEST = array_merge( $get, $post );

		$this->redirected_to = '';

		try {
			WP_PostRatings_Admin::load_manage();
		} catch ( WPDieException $e ) {
			unset( $e );
		}
	}

	// --- bulk delete ------------------------------------------------------

	/**
	 * The selected log rows are deleted and no others.
	 *
	 * @return void
	 */
	public function test_bulk_delete_removes_only_the_selected_rows() {
		global $wpdb;

		$post_id = $this->make_rated_post();
		$this->log_rating( $post_id, 1, 'One' );
		$this->log_rating( $post_id, 2, 'Two' );
		$this->log_rating( $post_id, 3, 'Three' );

		$ids = $wpdb->get_col( "SELECT rating_id FROM {$wpdb->ratings} ORDER BY rating_id ASC" );

		$this->handle(
			array(
				'action'    => 'delete',
				'rating_id' => array( $ids[0], $ids[2] ),
				'_wpnonce'  => wp_create_nonce( 'bulk-ratings' ),
			)
		);

		$remaining = $wpdb->get_col( "SELECT rating_username FROM {$wpdb->ratings}" );

		$this->assertSame( array( 'Two' ), $remaining, 'A bulk delete removes only the rows that were ticked.' );
	}

	/**
	 * A bulk delete without a valid nonce deletes nothing.
	 *
	 * @return void
	 */
	public function test_bulk_delete_requires_a_nonce() {
		$post_id = $this->make_rated_post();
		$this->log_rating( $post_id );

		$this->handle(
			array(
				'action'    => 'delete',
				'rating_id' => array( 1 ),
				'_wpnonce'  => 'not-a-nonce',
			)
		);

		$this->assertSame( 1, $this->log_count(), 'rows were deleted without a valid nonce' );
	}

	/**
	 * A user without the capability cannot delete.
	 *
	 * @return void
	 */
	public function test_bulk_delete_requires_the_capability() {
		$post_id = $this->make_rated_post();
		$this->log_rating( $post_id );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->handle(
			array(
				'action'    => 'delete',
				'rating_id' => array( 1 ),
				'_wpnonce'  => wp_create_nonce( 'bulk-ratings' ),
			)
		);

		$this->assertSame( 1, $this->log_count(), 'a subscriber deleted rating logs' );
	}

	// --- the delete data/logs form ----------------------------------------

	/**
	 * "Logs only" clears the log but leaves the rating data alone.
	 *
	 * @return void
	 */
	public function test_deleting_logs_only_keeps_the_rating_data() {
		$post_id = $this->make_rated_post( 4, 18 );
		$this->log_rating( $post_id );

		$this->handle(
			array(),
			array(
				'wp_postratings_delete' => '1',
				'delete_datalog'        => '1',
				'delete_postid'         => (string) $post_id,
				'_wpnonce'              => wp_create_nonce( 'wp_postratings_logs' ),
			)
		);

		$this->assertSame( 0, $this->log_count(), 'Deleting the logs empties the log.' );
		$this->assertSame( 4, (int) get_post_meta( $post_id, 'ratings_users', true ), 'And leaves the rating totals on the post.' );
	}

	/**
	 * "Data only" clears the meta but leaves the log alone.
	 *
	 * @return void
	 */
	public function test_deleting_data_only_keeps_the_logs() {
		$post_id = $this->make_rated_post( 4, 18 );
		$this->log_rating( $post_id );

		$this->handle(
			array(),
			array(
				'wp_postratings_delete' => '1',
				'delete_datalog'        => '2',
				'delete_postid'         => (string) $post_id,
				'_wpnonce'              => wp_create_nonce( 'wp_postratings_logs' ),
			)
		);

		$this->assertSame( 1, $this->log_count(), 'Deleting the data leaves the log alone.' );
		$this->assertSame( '', get_post_meta( $post_id, 'ratings_users', true ), 'While the rating totals are cleared.' );
	}

	/**
	 * Only the named post is cleared.
	 *
	 * @return void
	 */
	public function test_only_the_named_post_is_cleared() {
		$target = $this->make_rated_post( 4, 18 );
		$other  = $this->make_rated_post( 6, 20 );

		$this->log_rating( $target );
		$this->log_rating( $other );

		$this->handle(
			array(),
			array(
				'wp_postratings_delete' => '1',
				'delete_datalog'        => '3',
				'delete_postid'         => (string) $target,
				'_wpnonce'              => wp_create_nonce( 'wp_postratings_logs' ),
			)
		);

		$this->assertSame( 1, $this->log_count(), 'the other post lost its log rows' );
		$this->assertSame( 6, (int) get_post_meta( $other, 'ratings_users', true ), 'Another post keeps its totals.' );
		$this->assertSame( '', get_post_meta( $target, 'ratings_users', true ), 'While the named post loses its own.' );
	}

	/**
	 * "all" clears every post.
	 *
	 * @return void
	 */
	public function test_all_clears_everything() {
		$first  = $this->make_rated_post( 4, 18 );
		$second = $this->make_rated_post( 6, 20 );

		$this->log_rating( $first );
		$this->log_rating( $second );

		$this->handle(
			array(),
			array(
				'wp_postratings_delete' => '1',
				'delete_datalog'        => '3',
				'delete_postid'         => 'all',
				'_wpnonce'              => wp_create_nonce( 'wp_postratings_logs' ),
			)
		);

		$this->assertSame( 0, $this->log_count(), 'Clearing everything empties the log.' );
		$this->assertSame( '', get_post_meta( $first, 'ratings_users', true ), 'And clears the first post.' );
		$this->assertSame( '', get_post_meta( $second, 'ratings_users', true ), 'And the second, so it really is everything.' );
	}

	/**
	 * Clearing all rating data flushes the caches holding it.
	 *
	 * The meta rows are deleted with one query, so every post's meta cache
	 * still held the values that had just been removed.
	 *
	 * @return void
	 */
	public function test_clearing_all_data_flushes_the_meta_cache() {
		$post_id = $this->make_rated_post( 4, 18 );

		// Warm the cache the way a page render would.
		$this->assertSame( 4, (int) get_post_meta( $post_id, 'ratings_users', true ), 'The meta cache is flushed, so the cleared value is what is read back.' );

		$this->handle(
			array(),
			array(
				'wp_postratings_delete' => '1',
				'delete_datalog'        => '2',
				'delete_postid'         => 'all',
				'_wpnonce'              => wp_create_nonce( 'wp_postratings_logs' ),
			)
		);

		$this->assertSame( '', get_post_meta( $post_id, 'ratings_users', true ), 'the meta cache went stale' );
	}

	/**
	 * An ID list that parses to nothing deletes nothing.
	 *
	 * An empty list would build "IN ()", a syntax error rather than a no-op.
	 *
	 * @return void
	 */
	public function test_an_unparseable_id_list_deletes_nothing() {
		$post_id = $this->make_rated_post( 4, 18 );
		$this->log_rating( $post_id );

		$this->handle(
			array(),
			array(
				'wp_postratings_delete' => '1',
				'delete_datalog'        => '3',
				'delete_postid'         => 'nonsense',
				'_wpnonce'              => wp_create_nonce( 'wp_postratings_logs' ),
			)
		);

		$this->assertSame( 1, $this->log_count(), 'An unparseable id list deletes no log rows.' );
		$this->assertSame( 4, (int) get_post_meta( $post_id, 'ratings_users', true ), 'And no rating totals.' );
	}

	/**
	 * The delete form requires its nonce.
	 *
	 * @return void
	 */
	public function test_the_delete_form_requires_a_nonce() {
		$post_id = $this->make_rated_post( 4, 18 );
		$this->log_rating( $post_id );

		$this->handle(
			array(),
			array(
				'wp_postratings_delete' => '1',
				'delete_datalog'        => '3',
				'delete_postid'         => 'all',
				'_wpnonce'              => 'not-a-nonce',
			)
		);

		$this->assertSame( 1, $this->log_count(), 'rows were deleted without a valid nonce' );
	}

	// --- the rating fields endpoint ---------------------------------------

	/**
	 * Run the rating-fields endpoint and return its markup.
	 *
	 * @param array $get $_GET for the request.
	 *
	 * @return string
	 */
	private function call_rating_fields( $get ) {
		$_GET     = $get;
		$_REQUEST = $get;

		$depth  = ob_get_level();
		$output = '';

		try {
			ob_start();
			WP_PostRatings_Settings::ajax_rating_fields();
		} catch ( WPDieException $e ) {
			unset( $e );
		} finally {
			while ( ob_get_level() > $depth ) {
				$output = ob_get_clean() . $output;
			}
		}

		return $output;
	}

	/**
	 * The endpoint builds a row per step of the scale.
	 *
	 * @return void
	 */
	public function test_the_endpoint_builds_the_rating_rows() {
		$html = $this->call_rating_fields(
			array(
				'action'      => 'wp_postratings_rating_fields',
				'_ajax_nonce' => wp_create_nonce( 'wp_postratings_rating_fields' ),
				'custom'      => '0',
				'max'         => '3',
				'shape'       => 'star',
			)
		);

		$this->assertStringContainsString( 'postratings_ratingstext_1', $html, 'The first rating row is built.' );
		$this->assertStringContainsString( 'postratings_ratingstext_3', $html, 'And the last of the scale.' );
		$this->assertStringNotContainsString( 'postratings_ratingstext_4', $html, 'But not one past it.' );
	}

	/**
	 * An up/down scale is labelled Vote Down / Vote Up.
	 *
	 * @return void
	 */
	public function test_an_up_down_scale_gets_its_own_labels() {
		$html = $this->call_rating_fields(
			array(
				'action'      => 'wp_postratings_rating_fields',
				'_ajax_nonce' => wp_create_nonce( 'wp_postratings_rating_fields' ),
				'custom'      => '1',
				'max'         => '2',
				'shape'       => 'thumb',
			)
		);

		$this->assertStringContainsString( 'Vote Down', $html, 'An up-down scale labels its negative end.' );
		$this->assertStringContainsString( 'Vote Up', $html, 'And its positive end.' );
	}

	/**
	 * An image set that is not on disk is refused.
	 *
	 * @return void
	 */
	public function test_an_unknown_image_set_is_refused() {
		$html = $this->call_rating_fields(
			array(
				'action'      => 'wp_postratings_rating_fields',
				'_ajax_nonce' => wp_create_nonce( 'wp_postratings_rating_fields' ),
				'custom'      => '0',
				'max'         => '5',
				'shape'       => 'not-a-shape',
			)
		);

		$this->assertStringNotContainsString( 'postratings_ratingstext_1', $html, 'An unknown image set builds nothing rather than a broken row.' );
	}

	/**
	 * A pre-2.0.0 image set name is still accepted, and maps to its shape.
	 *
	 * An install that has not run the migration yet sends the old value from
	 * its own settings screen.
	 *
	 * @return void
	 */
	public function test_a_legacy_set_name_is_accepted() {
		$html = $this->call_rating_fields(
			array(
				'action'      => 'wp_postratings_rating_fields',
				'_ajax_nonce' => wp_create_nonce( 'wp_postratings_rating_fields' ),
				'custom'      => '0',
				'max'         => '5',
				'shape'       => 'stars_crystal',
			)
		);

		$this->assertStringContainsString( 'wp-postratings-shape-star', $html, 'A legacy set name is mapped to the shape it became.' );
	}

	/**
	 * The endpoint requires the capability.
	 *
	 * @return void
	 */
	public function test_the_endpoint_requires_the_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$html = $this->call_rating_fields(
			array(
				'action'      => 'wp_postratings_rating_fields',
				'_ajax_nonce' => wp_create_nonce( 'wp_postratings_rating_fields' ),
				'custom'      => '0',
				'max'         => '5',
				'shape'       => 'star',
			)
		);

		$this->assertStringNotContainsString( 'postratings_ratingstext_1', $html, 'Without the capability the endpoint builds nothing.' );
	}

	/**
	 * The endpoint requires its nonce.
	 *
	 * @return void
	 */
	public function test_the_endpoint_requires_a_nonce() {
		$html = $this->call_rating_fields(
			array(
				'action'      => 'wp_postratings_rating_fields',
				'_ajax_nonce' => 'not-a-nonce',
				'custom'      => '0',
				'max'         => '5',
				'shape'       => 'star',
			)
		);

		$this->assertStringNotContainsString( 'postratings_ratingstext_1', $html, 'And without a nonce.' );
	}

	/**
	 * An absurd scale is clamped rather than looping a hundred thousand times.
	 *
	 * @return void
	 */
	public function test_an_absurd_scale_is_clamped() {
		$html = $this->call_rating_fields(
			array(
				'action'      => 'wp_postratings_rating_fields',
				'_ajax_nonce' => wp_create_nonce( 'wp_postratings_rating_fields' ),
				'custom'      => '0',
				'max'         => '100000',
				'shape'       => 'star',
			)
		);

		$this->assertStringNotContainsString( 'postratings_ratingstext_101', $html, 'An absurd scale is clamped rather than built out in full.' );
	}
}
