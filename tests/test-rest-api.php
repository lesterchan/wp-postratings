<?php
/**
 * Tests for the `postratings/v1` REST routes.
 *
 * @package WP-PostRatings
 */

/**
 * The routes are open to logged-out visitors on purpose, which makes the checks
 * inside the callbacks the only thing between a post's rating and a scripted
 * one. So the nonce, the repeat-rating guard and the scale bounds are each
 * pinned here rather than assumed from the AJAX path they share.
 *
 * One of these is subtler than it looks. process_vote() answers with a string
 * whether the rating landed or not, so the route decides by watching
 * ratings_users rather than by reading the return -- and the tests that matter
 * most are the ones asserting a refusal moved nothing.
 */
class WP_PostRatings_REST_API_Test extends WP_PostRatings_TestCase {

	/**
	 * Boots the REST server the way core's own REST tests do.
	 */
	public function set_up() {
		parent::set_up();

		global $wp_rest_server;

		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
	}

	/**
	 * Tears the REST server back down so it cannot leak into another test.
	 */
	public function tear_down() {
		global $wp_rest_server;

		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Dispatch a request against the routes under test.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route below the namespace.
	 * @param array  $params Body or query parameters.
	 * @return WP_REST_Response
	 */
	protected function request( $method, $route, $params = array() ) {
		$request = new WP_REST_Request( $method, '/' . WP_PostRatings_API::REST_NAMESPACE . $route );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * The nonce the rendered rating control carries for a post.
	 *
	 * @param int $post_id Post.
	 * @return string
	 */
	protected function post_nonce( $post_id ) {
		return wp_create_nonce( 'wp_postratings_' . $post_id . '-nonce' );
	}

	// --- registration ----------------------------------------------------

	/**
	 * The routes register under the bare noun, not the plugin slug.
	 *
	 * @return void
	 */
	public function test_the_namespace_is_the_bare_noun() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/postratings/v1', $routes, 'The namespace is postratings/v1.' );
		$this->assertArrayNotHasKey( '/wp-postratings/v1', $routes, 'The plugin slug is not also claimed as a namespace.' );
		$this->assertSame( 'postratings/v1', WP_PostRatings_API::REST_NAMESPACE, 'And the constant agrees with what was registered.' );
	}

	/**
	 * Both routes are registered.
	 *
	 * @return void
	 */
	public function test_every_route_is_registered() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/postratings/v1/post/(?P<id>\d+)', $routes, 'Reading a rating is routed.' );
		$this->assertArrayHasKey( '/postratings/v1/post/(?P<id>\d+)/rate', $routes, 'And rating is routed.' );
	}

	// --- reading ---------------------------------------------------------

	/**
	 * Reading a post returns its totals and rendered markup.
	 *
	 * @return void
	 */
	public function test_reading_a_post_returns_its_totals() {
		$post_id = $this->make_rated_post( 4, 18 );

		$response = $this->request( 'GET', '/post/' . $post_id );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'Reading a rating succeeds.' );
		$this->assertSame( 4, $data['users'], 'It carries the number of raters.' );
		$this->assertSame( 18, $data['score'], 'And the total score.' );
		$this->assertSame( 4.5, $data['average'], 'And the average.' );
		$this->assertNotSame( '', $data['html'], 'And the markup a visitor would see.' );
	}

	/**
	 * A post nobody rated reads as zero rather than failing.
	 *
	 * @return void
	 */
	public function test_reading_an_unrated_post_is_zero_not_an_error() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$response = $this->request( 'GET', '/post/' . $post_id );

		$this->assertSame( 200, $response->get_status(), 'An unrated post is still a post.' );
		$this->assertSame( 0, $response->get_data()['users'], 'Nobody has rated it.' );
	}

	/**
	 * An id that matches no post is a 404.
	 *
	 * @return void
	 */
	public function test_an_unknown_post_is_rejected() {
		$response = $this->request( 'GET', '/post/123456' );

		$this->assertSame( 404, $response->get_status(), 'An id matching no post is refused.' );
	}

	// --- rating ----------------------------------------------------------

	/**
	 * A valid rating is recorded and the totals come back updated.
	 *
	 * @return void
	 */
	public function test_a_rating_is_recorded() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$response = $this->request(
			'POST',
			'/post/' . $post_id . '/rate',
			array(
				'rate'  => 5,
				'nonce' => $this->post_nonce( $post_id ),
			)
		);

		$this->assertSame( 200, $response->get_status(), 'The rating is accepted.' );
		$this->assertSame( 1, $response->get_data()['users'], 'One person has now rated it.' );
		$this->assertSame( 1, WP_PostRatings_Data::get( $post_id )['users'], 'And that is what was stored.' );
	}

	/**
	 * Without the post's nonce the rating is refused and nothing is recorded.
	 *
	 * @return void
	 */
	public function test_a_rating_without_the_nonce_is_refused() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$response = $this->request(
			'POST',
			'/post/' . $post_id . '/rate',
			array(
				'rate'  => 5,
				'nonce' => 'not-the-nonce',
			)
		);

		$this->assertSame( 403, $response->get_status(), 'A bad nonce is refused.' );
		$this->assertSame( 'wp_postratings_bad_nonce', $response->get_data()['code'], 'And says why.' );
		$this->assertSame( 0, WP_PostRatings_Data::get( $post_id )['users'], 'No rating was recorded.' );
	}

	/**
	 * A nonce minted for one post does not rate another.
	 *
	 * @return void
	 */
	public function test_another_posts_nonce_does_not_rate_here() {
		$target = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$other  = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$response = $this->request(
			'POST',
			'/post/' . $target . '/rate',
			array(
				'rate'  => 5,
				'nonce' => $this->post_nonce( $other ),
			)
		);

		$this->assertSame( 403, $response->get_status(), 'The nonce is scoped to the post it was minted for.' );
		$this->assertSame( 0, WP_PostRatings_Data::get( $target )['users'], 'So nothing was recorded.' );
	}

	/**
	 * A rating off the end of the scale is refused.
	 *
	 * @return void
	 */
	public function test_a_rating_outside_the_scale_is_refused() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$response = $this->request(
			'POST',
			'/post/' . $post_id . '/rate',
			array(
				'rate'  => 99,
				'nonce' => $this->post_nonce( $post_id ),
			)
		);

		$this->assertSame( 400, $response->get_status(), 'A rating off the end of the scale is refused.' );
		$this->assertSame( 0, WP_PostRatings_Data::get( $post_id )['users'], 'And records nothing.' );
	}

	/**
	 * Rating twice is refused, and the second attempt changes nothing.
	 *
	 * This is the test the whole design of rate() rests on: process_vote()
	 * returns a string either way, so a route reading its return rather than
	 * watching the totals would report the refusal as a success.
	 *
	 * @return void
	 */
	public function test_rating_twice_is_refused() {
		// check_method, not logging_method: the legacy row was
		// postratings_logging_method and the migration renamed it, so setting
		// the old name here would write a key nothing reads and leave the test
		// passing on whatever the default happened to be.
		$this->set_option( 'check_method', 2 );

		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$rate = function () use ( $post_id ) {
			return $this->request(
				'POST',
				'/post/' . $post_id . '/rate',
				array(
					'rate'  => 4,
					'nonce' => $this->post_nonce( $post_id ),
				)
			);
		};

		$this->assertSame( 200, $rate()->get_status(), 'The first rating is accepted.' );

		$second = $rate();

		$this->assertSame( 400, $second->get_status(), 'The second is refused.' );
		$this->assertSame( 1, WP_PostRatings_Data::get( $post_id )['users'], 'And the count did not move again.' );
	}

	// --- the AJAX endpoint it sits beside --------------------------------

	/**
	 * The AJAX action stays registered, because sites are still calling it.
	 *
	 * @return void
	 */
	public function test_the_ajax_endpoint_is_still_registered() {
		$this->assertNotFalse( has_action( 'wp_ajax_wp_postratings', array( 'WP_PostRatings_Rating', 'handle_vote' ) ), 'The logged-in AJAX action survives the REST routes.' );
		$this->assertNotFalse( has_action( 'wp_ajax_nopriv_wp_postratings', array( 'WP_PostRatings_Rating', 'handle_vote' ) ), 'And so does the logged-out one.' );
	}
}
