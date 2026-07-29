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
	 * Set one plugin setting.
	 *
	 * @param string $key   Top level setting name.
	 * @param mixed  $value Value to store.
	 *
	 * @return void
	 */
	protected function set_option( $key, $value ) {
		$options         = WP_PostRatings_Options::get();
		$options[ $key ] = $value;
		WP_PostRatings_Options::update( $options );
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
