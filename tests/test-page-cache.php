<?php
/**
 * Tests for living behind a page cache.
 *
 * @package WP-PostRatings
 */

/**
 * The setting, the gate, the shared branch and the routes that correct a
 * cached page.
 *
 * A rendered rating is three answers about one visitor -- the vote count as it
 * stood, which of the three templates they should see, and a nonce that stops
 * verifying within a day -- and a page cache serves one visitor's copy of all
 * three to everybody else. Nothing votes to purge the cache, so nothing
 * corrects it.
 *
 * What is pinned here is the correction path end to end: that it is off unless
 * a site asks for it, that the markup the route hands back is the same branch
 * the page itself would have rendered, and -- the one that matters most -- that
 * a nonce taken from the route actually rates the post. A test that only checks
 * the field is present passes just as well when the value in it is refused.
 *
 * @covers WP_PostRatings
 * @covers WP_PostRatings_API
 * @covers WP_PostRatings_Options
 * @covers WP_PostRatings_Template
 */
class WP_PostRatings_Page_Cache_Test extends WP_PostRatings_TestCase {

	/**
	 * Boot a REST server, and start from an empty script registry.
	 *
	 * Both are process globals, so a handle or a route left behind by one test
	 * would answer for the next.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		unset( $GLOBALS['wp_scripts'], $GLOBALS['wp_styles'] );

		global $wp_rest_server;

		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );

		// Anyone may rate, and nothing is remembered about who has, unless a
		// test says otherwise. Both are what decide which branch renders.
		$this->set_options(
			array(
				'allowtorate'  => 2,
				'check_method' => 0,
			)
		);
	}

	/**
	 * Put the REST server back.
	 *
	 * @return void
	 */
	public function tear_down() {
		global $wp_rest_server;

		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Dispatch against the plugin's namespace.
	 *
	 * @param string $route  Route below the namespace.
	 * @param array  $params Query parameters.
	 * @return WP_REST_Response
	 */
	protected function read( $route, $params = array() ) {
		$request = new WP_REST_Request( 'GET', '/' . WP_PostRatings_API::REST_NAMESPACE . $route );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * The localized data the front end script was handed.
	 *
	 * Decoded rather than grepped, so an assertion about `refresh` being false
	 * cannot pass on the substring of some other key.
	 *
	 * @return array
	 */
	protected function localized_data() {
		$post_id = $this->make_rated_post();

		the_ratings( 'div', $post_id, false );

		do_action( 'wp_enqueue_scripts' );

		ob_start();
		do_action( 'wp_footer' );
		ob_end_clean();

		$data = (string) wp_scripts()->get_data( 'wp-postratings', 'data' );

		$this->assertNotSame( '', $data, 'The script was localized at all.' );

		// wp_localize_script() prints `var wpPostRatingsL10n = {...};`.
		preg_match( '/wpPostRatingsL10n = (.*);$/', $data, $matches );

		$this->assertNotEmpty( $matches, 'The localized object is where it is printed.' );

		return json_decode( $matches[1], true );
	}

	// --- the setting -----------------------------------------------------

	/**
	 * It ships off.
	 *
	 * A site with no page cache must pay nothing for this, and no page cache
	 * can be detected from inside WordPress -- so the default has to be the
	 * answer that costs nothing, and the site owner is the one who knows.
	 *
	 * @return void
	 */
	public function test_the_setting_ships_off() {
		$defaults = WP_PostRatings_Options::defaults();

		$this->assertArrayHasKey( 'page_cache', $defaults, 'The setting exists.' );
		$this->assertSame( 0, $defaults['page_cache'], 'And it is off out of the box.' );
	}

	/**
	 * Ticking the box stores a 1, unticking it stores a 0.
	 *
	 * The second half is the one that breaks. An unticked checkbox posts
	 * nothing at all and the sanitizer keeps whatever the submission did not
	 * mention -- deliberately, because two tabs write this one row -- so the
	 * field renders a hidden 0 sharing its name. Without it the box could be
	 * ticked and never unticked.
	 *
	 * @return void
	 */
	public function test_the_setting_can_be_turned_on_and_off_again() {
		$this->set_options( array( 'page_cache' => 0 ) );

		WP_PostRatings_Options::update(
			WP_PostRatings_Options::sanitize( array( 'page_cache' => '1' ) )
		);

		$this->assertSame( 1, WP_PostRatings_Options::get( 'page_cache' ), 'A ticked box turns it on.' );

		WP_PostRatings_Options::update(
			WP_PostRatings_Options::sanitize( array( 'page_cache' => '0' ) )
		);

		$this->assertSame( 0, WP_PostRatings_Options::get( 'page_cache' ), 'And the hidden 0 turns it off again.' );
	}

	/**
	 * Saving the setting leaves the rest of the row alone.
	 *
	 * @return void
	 */
	public function test_turning_it_on_does_not_disturb_the_other_settings() {
		$this->set_options(
			array(
				'shape' => 'thumb',
				'max'   => 2,
			)
		);

		WP_PostRatings_Options::update(
			WP_PostRatings_Options::sanitize( array( 'page_cache' => '1' ) )
		);

		$this->assertSame( 1, WP_PostRatings_Options::get( 'page_cache' ), 'The setting saved.' );
		$this->assertSame( 'thumb', WP_PostRatings_Options::get( 'shape' ), 'And the shape it was not asked about survived.' );
	}

	// --- the gate --------------------------------------------------------

	/**
	 * With the setting off the script is told not to refresh.
	 *
	 * The flag is '1' or '', not a boolean, and deliberately so:
	 * wp_localize_script() casts every scalar it is handed to a string, so a
	 * boolean false would arrive as '' regardless. Asserting the string is
	 * asserting what the browser is actually given -- and '' is falsy there,
	 * which 'false' would not have been.
	 *
	 * @return void
	 */
	public function test_the_front_end_does_not_refresh_by_default() {
		$data = $this->localized_data();

		$this->assertArrayHasKey( 'refresh', $data, 'The script is told either way.' );
		$this->assertSame( '', $data['refresh'], 'And by default the answer is no.' );
	}

	/**
	 * With the setting on, a logged-out visitor refreshes.
	 *
	 * @return void
	 */
	public function test_the_front_end_refreshes_when_the_site_says_it_caches() {
		$this->set_options( array( 'page_cache' => 1 ) );

		$this->assertSame( '1', $this->localized_data()['refresh'], 'The site said its pages are cached.' );
	}

	/**
	 * A logged-in visitor never refreshes, whatever the setting says.
	 *
	 * Every page cache passes a logged-in request through to PHP, so their
	 * markup was built for them and is already right. Refreshing it would be a
	 * request per page bought for nothing.
	 *
	 * @return void
	 */
	public function test_a_logged_in_visitor_never_refreshes() {
		$this->set_options( array( 'page_cache' => 1 ) );

		wp_set_current_user( $this->create_admin() );

		$this->assertSame( '', $this->localized_data()['refresh'], 'A logged-in visitor bypassed the cache to get here.' );
	}

	/**
	 * The filter can switch it off for one request.
	 *
	 * @return void
	 */
	public function test_the_filter_can_switch_it_off() {
		$this->set_options( array( 'page_cache' => 1 ) );

		add_filter( 'wp_postratings_refreshes_ratings', '__return_false' );

		$this->assertSame( '', $this->localized_data()['refresh'], 'A page excluded from the cache has nothing to correct.' );
	}

	/**
	 * And on, for a cache the setting was never told about.
	 *
	 * @return void
	 */
	public function test_the_filter_can_switch_it_on() {
		add_filter( 'wp_postratings_refreshes_ratings', '__return_true' );

		$this->assertSame( '1', $this->localized_data()['refresh'], 'The filter has the last word.' );
	}

	/**
	 * The script is pointed at the batch route, not the single one.
	 *
	 * A page showing ten ratings must cost one request. Pointing this at
	 * `post/` would still work and would quietly cost ten.
	 *
	 * @return void
	 */
	public function test_the_script_is_pointed_at_the_batch_route() {
		$data = $this->localized_data();

		$this->assertSame(
			rest_url( 'postratings/v1/posts' ),
			$data['restUrl'],
			'One request answers for every rating on the page.'
		);
	}

	// --- the shared branch -----------------------------------------------

	/**
	 * A visitor who may rate gets the control and a working nonce.
	 *
	 * @return void
	 */
	public function test_a_visitor_who_may_rate_gets_a_control_and_a_nonce() {
		$post_id = $this->make_rated_post();

		$block = WP_PostRatings_Template::block( $post_id );

		$this->assertStringContainsString( 'wp-postratings-vote', $block['html'], 'The control is what they should see.' );
		$this->assertNotSame( '', $block['nonce'], 'And a control needs its nonce.' );
		$this->assertSame(
			1,
			wp_verify_nonce( $block['nonce'], 'wp_postratings_' . $post_id . '-nonce' ),
			'A nonce that does not verify is worse than none: the control looks usable and is not.'
		);
	}

	/**
	 * A visitor who has already rated gets the result and no nonce.
	 *
	 * @return void
	 */
	public function test_a_visitor_who_has_rated_gets_the_result_and_no_nonce() {
		$post_id = $this->make_rated_post();

		add_filter( 'wp_postratings_check_rated', '__return_true' );

		$block = WP_PostRatings_Template::block( $post_id );

		$this->assertStringNotContainsString( 'wp-postratings-vote', $block['html'], 'They have had their vote.' );
		$this->assertSame( '', $block['nonce'], 'And nothing to authorise.' );
	}

	/**
	 * A visitor who may not rate gets the refusal, not the results template.
	 *
	 * The two read alike at a glance and are different templates -- the
	 * refusal is the one carrying %RATINGS_PERMISSION%, which is the only
	 * place the reason is said.
	 *
	 * @return void
	 */
	public function test_a_visitor_who_may_not_rate_gets_the_refusal() {
		$post_id = $this->make_rated_post();

		// Registered users only, and nobody is logged in.
		$this->set_options( array( 'allowtorate' => 1 ) );

		$block = WP_PostRatings_Template::block( $post_id );

		$this->assertStringNotContainsString( 'wp-postratings-vote', $block['html'], 'They may not rate.' );
		$this->assertSame( '', $block['nonce'], 'So they get no nonce.' );
		$this->assertSame(
			the_ratings_results( $post_id, 0, 0, 0, 1 ),
			$block['html'],
			'The refusal template, which is the only one that says why.'
		);
	}

	/**
	 * The page and the route answer the branch identically.
	 *
	 * This is the whole reason the branch was moved into one method. The
	 * correction replaces markup the page rendered; if the two disagreed, a
	 * cached page could be corrected into a control whose vote the site would
	 * then refuse -- which is the bug this feature exists to fix, reintroduced
	 * by the fix itself.
	 *
	 * @return void
	 */
	public function test_the_page_and_the_route_render_the_same_branch() {
		$post_id = $this->make_rated_post();

		$rendered = the_ratings( 'div', $post_id, false );
		$data     = $this->read( '/post/' . $post_id )->get_data();

		$this->assertStringContainsString(
			$data['visitor_html'],
			$rendered,
			'The route hands back exactly what the page put inside the wrapper.'
		);
	}

	/**
	 * The wrapper carries a nonce exactly when there is one to carry.
	 *
	 * @return void
	 */
	public function test_the_wrapper_carries_a_nonce_only_when_the_visitor_may_rate() {
		$post_id = $this->make_rated_post();

		$this->assertStringContainsString(
			'data-nonce="',
			the_ratings( 'div', $post_id, false ),
			'A visitor who may rate needs it on the wrapper, which is where the script reads it.'
		);

		add_filter( 'wp_postratings_check_rated', '__return_true' );

		$this->assertStringNotContainsString(
			'data-nonce="',
			the_ratings( 'div', $post_id, false ),
			'A visitor with nothing to vote with is handed no token.'
		);
	}

	// --- the single read -------------------------------------------------

	/**
	 * Reading a post returns what the visitor should see, and its nonce.
	 *
	 * @return void
	 */
	public function test_the_read_route_returns_the_visitor_markup_and_a_nonce() {
		$post_id = $this->make_rated_post( 4, 18 );

		$data = $this->read( '/post/' . $post_id )->get_data();

		$this->assertArrayHasKey( 'visitor_html', $data, 'What this visitor should be looking at.' );
		$this->assertArrayHasKey( 'nonce', $data, 'And what lets them act on it.' );
		$this->assertStringContainsString( 'wp-postratings-vote', $data['visitor_html'], 'They have not rated, so it is the control.' );
		$this->assertSame(
			1,
			wp_verify_nonce( $data['nonce'], 'wp_postratings_' . $post_id . '-nonce' ),
			'The nonce verifies for this post.'
		);
	}

	/**
	 * `html` keeps meaning the read-only result.
	 *
	 * It has meant that since the route shipped and something is reading it.
	 * Making it the control for some visitors and the result for others would
	 * be a silent change of contract dressed as a new feature -- which is why
	 * the visitor's markup arrived as a field of its own.
	 *
	 * @return void
	 */
	public function test_the_html_field_still_means_the_read_only_result() {
		$post_id = $this->make_rated_post( 4, 18 );

		$data = $this->read( '/post/' . $post_id )->get_data();

		$this->assertStringNotContainsString( 'wp-postratings-vote', $data['html'], 'No control in the old field.' );
		$this->assertSame(
			WP_PostRatings_Template::expand( WP_PostRatings_Options::template( 'text' ), $post_id ),
			$data['html'],
			'It is the text template, expanded, exactly as before.'
		);
		$this->assertNotSame( $data['html'], $data['visitor_html'], 'The two answer different questions here.' );
	}

	/**
	 * A visitor who has rated gets no nonce from the route either.
	 *
	 * @return void
	 */
	public function test_the_read_route_withholds_the_nonce_from_somebody_who_has_rated() {
		$post_id = $this->make_rated_post();

		add_filter( 'wp_postratings_check_rated', '__return_true' );

		$data = $this->read( '/post/' . $post_id )->get_data();

		$this->assertTrue( $data['has_rated'], 'The route knows they have.' );
		$this->assertSame( '', $data['nonce'], 'So it hands out nothing to vote with.' );
		$this->assertStringNotContainsString( 'wp-postratings-vote', $data['visitor_html'], 'And shows them the result.' );
	}

	/**
	 * The nonce the route hands back actually rates the post.
	 *
	 * The end of the story, and the assertion the rest of this file is
	 * scaffolding for. A cached page's token expires; the correction is only
	 * worth making if what replaces it is accepted. Every field could be
	 * present and correct-looking and this could still fail.
	 *
	 * @return void
	 */
	public function test_a_nonce_from_the_route_can_be_voted_with() {
		$post_id = $this->make_rated_post( 4, 18 );

		$nonce = $this->read( '/post/' . $post_id )->get_data()['nonce'];

		$request = new WP_REST_Request( 'POST', '/' . WP_PostRatings_API::REST_NAMESPACE . '/post/' . $post_id . '/rate' );
		$request->set_param( 'rate', 5 );
		$request->set_param( 'nonce', $nonce );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'The vote was accepted.' );
		$this->assertSame( 5, (int) get_post_meta( $post_id, 'ratings_users', true ), 'And counted.' );
	}

	/**
	 * A read reports a vote the cached page could not have known about.
	 *
	 * @return void
	 */
	public function test_the_read_route_reports_votes_cast_since_the_page_was_rendered() {
		$post_id = $this->make_rated_post( 1, 5 );

		$stale = the_ratings( 'div', $post_id, false );

		update_post_meta( $post_id, 'ratings_users', 2 );
		update_post_meta( $post_id, 'ratings_score', 10 );
		update_post_meta( $post_id, 'ratings_average', 5 );

		$data = $this->read( '/post/' . $post_id )->get_data();

		$this->assertSame( 2, $data['users'], 'The route reads the totals now, not when the page was built.' );
		$this->assertStringContainsString( '1', wp_strip_all_tags( $stale ), 'The cached copy still says what it said.' );
		$this->assertNotSame( $stale, $data['visitor_html'], 'Which is the whole problem.' );
	}

	// --- the batch read --------------------------------------------------

	/**
	 * The batch route is registered.
	 *
	 * @return void
	 */
	public function test_the_batch_route_is_registered() {
		$this->assertArrayHasKey(
			'/postratings/v1/posts',
			rest_get_server()->get_routes(),
			'Reading several ratings at once is routed.'
		);
	}

	/**
	 * It answers for every post asked about, in one response.
	 *
	 * @return void
	 */
	public function test_the_batch_route_answers_for_every_post() {
		$first  = $this->make_rated_post( 1, 5 );
		$second = $this->make_rated_post( 3, 9 );

		$data = $this->read( '/posts', array( 'ids' => $first . ',' . $second ) )->get_data();

		$this->assertCount( 2, $data['posts'], 'Two posts asked about, two answered.' );

		$by_id = wp_list_pluck( $data['posts'], 'users', 'post_id' );

		$this->assertSame( 1, $by_id[ $first ], 'The first post reads correctly.' );
		$this->assertSame( 3, $by_id[ $second ], 'And so does the second.' );
	}

	/**
	 * Each entry of a batch carries the same fields the single read does.
	 *
	 * @return void
	 */
	public function test_a_batch_entry_is_shaped_like_a_single_read() {
		$post_id = $this->make_rated_post( 4, 18 );

		$single = $this->read( '/post/' . $post_id )->get_data();
		$batch  = $this->read( '/posts', array( 'ids' => (string) $post_id ) )->get_data()['posts'][0];

		$this->assertSame( array_keys( $single ), array_keys( $batch ), 'The two reads answer with the same fields.' );
		$this->assertSame( $single['visitor_html'], $batch['visitor_html'], 'And the same markup.' );
	}

	/**
	 * An id naming nothing ratable is left out, not fatal to the batch.
	 *
	 * The caller is a page correcting the ratings it is showing. A post
	 * deleted since that page was cached must not cost the other nine posts
	 * theirs.
	 *
	 * @return void
	 */
	public function test_the_batch_route_skips_an_id_it_cannot_answer_for() {
		$post_id = $this->make_rated_post();
		$draft   = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$response = $this->read( '/posts', array( 'ids' => $post_id . ',' . $draft . ',999999' ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'The batch still succeeds.' );
		$this->assertCount( 1, $data['posts'], 'Only the post that could be answered for.' );
		$this->assertSame( $post_id, $data['posts'][0]['post_id'], 'And it is the right one.' );
	}

	/**
	 * A repeated id is answered once.
	 *
	 * @return void
	 */
	public function test_the_batch_route_answers_a_repeated_id_once() {
		$post_id = $this->make_rated_post();

		$data = $this->read( '/posts', array( 'ids' => $post_id . ',' . $post_id ) )->get_data();

		$this->assertCount( 1, $data['posts'], 'One post, one answer.' );
	}

	/**
	 * Rubbish in the id list is dropped rather than answered for.
	 *
	 * @return void
	 */
	public function test_the_batch_route_drops_ids_that_are_not_ids() {
		$post_id = $this->make_rated_post();

		$data = $this->read( '/posts', array( 'ids' => $post_id . ',,abc,-4,0' ) )->get_data();

		$this->assertCount( 1, $data['posts'], 'Only the real id survived.' );
		$this->assertSame( $post_id, $data['posts'][0]['post_id'], 'And it is unharmed by the company it kept.' );
	}

	/**
	 * The batch is capped rather than unbounded.
	 *
	 * Every entry is a template expansion and a meta read, and the parameter
	 * arrives from anybody at all.
	 *
	 * @return void
	 */
	public function test_the_batch_route_is_capped() {
		$this->assertSame( 100, WP_PostRatings_API::MAX_BATCH, 'The ceiling is a stated number.' );

		$first = $this->make_rated_post();
		$last  = $this->make_rated_post();

		// A real post at the head of the list and another just past the cap,
		// with ids naming nothing in between. Counting the answers would pass
		// on an uncapped route too; what is asserted is that the id beyond the
		// ceiling was never looked at.
		$ids = array_merge(
			array( $first ),
			range( $last + 1, $last + WP_PostRatings_API::MAX_BATCH ),
			array( $last )
		);

		$data  = $this->read( '/posts', array( 'ids' => implode( ',', $ids ) ) )->get_data();
		$found = wp_list_pluck( $data['posts'], 'post_id' );

		$this->assertContains( $first, $found, 'The id at the head of the list is answered.' );
		$this->assertNotContains( $last, $found, 'The one past the ceiling is not reached at all.' );
	}

	// --- caching the response --------------------------------------------

	/**
	 * Both read responses forbid being stored.
	 *
	 * WordPress sends no-cache headers on a REST response only when the
	 * request is logged in, and these routes exist for the logged-out visitors
	 * that excludes. `has_rated`, `can_rate` and the nonce are all answered for
	 * one visitor -- an intermediary holding one copy would hand the first
	 * visitor's answers to the next and put back the bug the route is here to
	 * fix.
	 *
	 * @return void
	 */
	public function test_the_read_responses_forbid_being_stored() {
		$post_id = $this->make_rated_post();

		foreach ( array( '/post/' . $post_id, '/posts' ) as $route ) {
			$response = $this->read( $route, array( 'ids' => (string) $post_id ) );
			$headers  = $response->get_headers();

			$this->assertArrayHasKey( 'Cache-Control', $headers, $route . ' says how it may be cached.' );
			$this->assertStringContainsString( 'no-store', $headers['Cache-Control'], $route . ' forbids storing it.' );
		}
	}
}
