<?php
/**
 * Shared fixture helpers.
 *
 * The plugin owns wp_ratings, which WP_UnitTestCase's transaction does not
 * know about, so it is cleared and reseeded explicitly.
 *
 * @package WP-PostRatings
 */

/**
 * Base class carrying the rating fixtures.
 */
abstract class WP_PostRatings_TestCase extends WP_UnitTestCase {

	/**
	 * Diagnostics raised while rendering an admin screen.
	 *
	 * @var array
	 */
	protected $admin_page_notices = array();

	/**
	 * Creates a user who may actually reach the plugin's screens.
	 *
	 * The screens take `manage_ratings`, the plugin's own capability, which
	 * activation adds to the administrator role. map_meta_cap() only remaps the
	 * capabilities core knows about, so a custom one means the same thing on a
	 * network as on a single site and no grant_super_admin() is wanted. The
	 * tests that add or revoke the capability by hand are asserting the gate
	 * itself and keep doing that explicitly.
	 *
	 * Every administrator the suite creates goes through this, so the network
	 * question is answered in one place rather than at each call site. Tests
	 * that assert the *unprivileged* path set their own subscriber or editor
	 * explicitly and must not be routed through here.
	 *
	 * @return int The new user's ID.
	 */
	protected function create_admin() {
		return self::factory()->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Reset the log table and the settings before each test.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->ratings}" );

		delete_option( WP_PostRatings_Options::OPTION );
		delete_option( WP_PostRatings_Options::VERSION );
		WP_PostRatings_Options::update( WP_PostRatings_Options::defaults() );
		WP_PostRatings_Options::update_markers();

		$_SERVER['REMOTE_ADDR']     = '203.0.113.1';
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (phpunit)';

		// The assets flag is request-scoped in production; here the process is
		// the request, so it is put back by hand.
		$needs_assets = new ReflectionProperty( 'WP_PostRatings_Template', 'needs_assets' );
		$needs_assets->setValue( null, false );

		wp_cache_flush();
	}

	/**
	 * Clear anything a test left behind.
	 *
	 * @return void
	 */
	public function tear_down() {
		foreach ( glob( get_temp_dir() . '/wp-blog-*-wp-postratings-*.lock' ) as $stale ) {
			wp_delete_file( $stale );
		}

		// The comment global is not part of WP_UnitTestCase's rollback, so a
		// comment left in place leaks into the next test's lookups.
		unset( $GLOBALS['comment'] );

		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();

		parent::tear_down();
	}

	/**
	 * Run the uninstaller's option work, repeatably.
	 *
	 * Deliberately not a require of uninstall.php. That file reaches
	 * WP_PostRatings_Install::uninstall_site(), which drops wp_ratings - DDL,
	 * which MySQL commits, so it survives the transaction wrapping the rest of
	 * the suite and one test would take the table away from every test after
	 * it. The deletions are performed here instead, over the same
	 * WP_PostRatings_Options::all_option_names() list the uninstaller loops
	 * over, so a row missing from that list still fails the shared uninstall
	 * test. That uninstall.php itself delegates to the installer, lifts the
	 * site query cap and restores inside the loop is asserted against the
	 * source in test-uninstall.php, and the table, capability and post meta it
	 * also removes are covered there behaviourally.
	 *
	 * @return void
	 */
	protected function run_uninstall() {
		foreach ( WP_PostRatings_Options::all_option_names() as $option_name ) {
			delete_option( $option_name );
		}
	}

	/**
	 * Set one plugin setting.
	 *
	 * Merged into the stored row rather than over the defaults, because the
	 * suite layers overrides across separate calls and a defaults merge would
	 * silently drop the earlier ones.
	 *
	 * @param array $overrides Setting names to values.
	 *
	 * @return void
	 */
	protected function set_options( array $overrides = array() ) {
		WP_PostRatings_Options::update( array_merge( WP_PostRatings_Options::get(), $overrides ) );
	}

	/**
	 * Rate a post and hand back the refusal, or '' when it was accepted.
	 *
	 * WP_PostRatings_Rating::process_vote() returns on success and throws on
	 * every refusal, so a test
	 * about a refusal has to catch. The refusals that say nothing to a visitor
	 * throw with an empty message, which is why '' is ambiguous here on its own
	 * -- assert on what was stored as well.
	 *
	 * @param int $post_id Post to rate.
	 * @param int $rate    Position on the scale.
	 *
	 * @return string The refusal message, or '' if the rating was accepted.
	 */
	protected function refusal( $post_id, $rate ) {
		try {
			WP_PostRatings_Rating::process_vote( $post_id, $rate );
		} catch ( InvalidArgumentException $e ) {
			return '' === $e->getMessage() ? '(silent)' : $e->getMessage();
		}

		return '';
	}

	/**
	 * Create a post carrying rating meta.
	 *
	 * @param int    $users Number of voters.
	 * @param int    $score Total score.
	 * @param string $type  Post type.
	 *
	 * @return int Post id.
	 */
	protected function make_rated_post( $users = 4, $score = 18, $type = 'post' ) {
		// Dated an hour ago on purpose. The ranking queries filter on
		// "post_date < NOW()", and the factory's default stamps the current
		// second, so a post created mid-test can fail a strict comparison
		// against a NOW() evaluated in the same second.
		$post_id = self::factory()->post->create(
			array(
				'post_title'    => 'Fixture & <em>markup</em>',
				'post_type'     => $type,
				'post_status'   => 'publish',
				'post_date'     => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ),
			)
		);

		update_post_meta( $post_id, 'ratings_users', $users );
		update_post_meta( $post_id, 'ratings_score', $score );
		update_post_meta( $post_id, 'ratings_average', $users ? round( $score / $users, 2 ) : 0 );

		return $post_id;
	}

	/**
	 * Insert a row into the rating log.
	 *
	 * @param int    $post_id  Post rated.
	 * @param int    $rating   Score given.
	 * @param string $username Name recorded.
	 * @param string $ip       Raw address; stored hashed, as the plugin does.
	 * @param int    $user_id  User id, 0 for a guest.
	 *
	 * @return void
	 */
	protected function log_rating( $post_id, $rating = 4, $username = 'Guest', $ip = '203.0.113.1', $user_id = 0 ) {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->ratings} VALUES (%d, %d, %s, %d, %d, %s, %s, %s, %d)",
				0,
				$post_id,
				'Fixture post title',
				$rating,
				1767225600,
				wp_hash( $ip ),
				'example.net',
				$username,
				$user_id
			)
		);
	}

	/**
	 * Render an admin screen, collecting any diagnostics it raises.
	 *
	 * Asserting the screen is merely non-empty is a smoke test; collecting the
	 * notices is what proves it is clean under PHP 8.
	 *
	 * @param callable $callback Renderer to invoke.
	 * @param array    $get      $_GET for the request.
	 * @param array    $post     $_POST for the request.
	 *
	 * @return string
	 */
	protected function render_admin_screen( $callback, $get = array(), $post = array() ) {
		global $wpdb, $wp_locale, $hook_suffix;

		$this->admin_page_notices = array();

		$_GET     = $get;
		$_POST    = $post;
		$_REQUEST = array_merge( $get, $post );

		set_error_handler(
			function ( $errno, $errstr, $errfile, $errline ) {
				if ( false !== strpos( (string) $errfile, 'wp-postratings' ) ) {
					$this->admin_page_notices[] = $errstr . ' in ' . basename( $errfile ) . ':' . $errline;
				}
				return true;
			}
		);

		try {
			ob_start();
			call_user_func( $callback );
			return ob_get_clean();
		} finally {
			restore_error_handler();
			$_GET     = array();
			$_POST    = array();
			$_REQUEST = array();
		}
	}

	/**
	 * Assert a rendered admin screen carries none of the damage that parses,
	 * lints clean and still ships.
	 *
	 * @param string $html Rendered markup.
	 *
	 * @return void
	 */
	protected function assertAdminScreenClean( $html ) {
		$this->assertSame( array(), $this->admin_page_notices, 'the screen raised diagnostics' );
		$this->assertStringNotContainsString( 'translators:', $html, 'a translators comment reached HTML context' );
		$this->assertStringNotContainsString( '<?php', $html, 'an unclosed PHP tag reached the output' );
		$this->assertDoesNotMatchRegularExpression( '/&amp;(nbsp|quot|amp|lt|gt);/', $html, 'a value was double encoded' );
		$this->assertStringNotContainsString( 'Access Denied', $html, 'the capability check rejected the request' );
	}
}
