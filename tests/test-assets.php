<?php
/**
 * Tests for the front end asset gating.
 *
 * The stylesheet and script load only on pages that actually render a rating,
 * whichever of the entry points -- template tag, shortcode, block, widget or
 * comment author strip -- put it there.
 *
 * @package WP-PostRatings
 */

/**
 * When the front end assets are enqueued, and when they are not.
 *
 * @covers WP_PostRatings
 * @covers WP_PostRatings_Template
 */
class WP_PostRatings_Assets_Test extends WP_PostRatings_TestCase {

	/**
	 * Start each test from an empty registry.
	 *
	 * The wp_scripts() and wp_styles() registries are process globals, so a
	 * handle one test enqueues would answer for the next.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		unset( $GLOBALS['wp_scripts'], $GLOBALS['wp_styles'], $GLOBALS['_wp_sidebars_widgets'] );
	}

	/**
	 * Fire the front end enqueue sequence the way a page load does.
	 *
	 * Anything already rendered has run by the time the theme calls
	 * wp_footer(), which is the point of enqueuing there. The output is
	 * discarded: what is printed is core's business, what was enqueued is ours.
	 *
	 * @return void
	 */
	private function load_front_end_page() {
		do_action( 'wp_enqueue_scripts' );

		ob_start();
		do_action( 'wp_footer' );
		ob_end_clean();
	}

	/**
	 * A page that renders no rating carries neither asset.
	 *
	 * @return void
	 */
	public function test_a_bare_page_carries_no_assets() {
		$this->assertFalse( WP_PostRatings_Template::needs_assets(), 'Nothing has rendered a rating yet.' );

		$this->load_front_end_page();

		$this->assertFalse( wp_style_is( 'wp-postratings', 'enqueued' ), 'A page without a rating must not carry the stylesheet.' );
		$this->assertFalse( wp_script_is( 'wp-postratings', 'enqueued' ), 'Nor the script.' );
	}

	/**
	 * A page that renders a rating gets the stylesheet, the script and the
	 * localized data the script votes with.
	 *
	 * @return void
	 */
	public function test_a_page_with_a_rating_carries_both_assets() {
		the_ratings( 'div', $this->make_rated_post(), false );

		$this->load_front_end_page();

		$this->assertTrue( wp_style_is( 'wp-postratings', 'enqueued' ), 'A page with a rating carries the stylesheet.' );
		$this->assertTrue( wp_script_is( 'wp-postratings', 'enqueued' ), 'And the script, or nothing can vote.' );
		$this->assertStringContainsString( 'wpPostRatingsL10n', (string) wp_scripts()->get_data( 'wp-postratings', 'data' ), 'The localized data travels with the script.' );
	}

	/**
	 * The shortcode asks for the assets.
	 *
	 * @return void
	 */
	public function test_the_shortcode_asks_for_the_assets() {
		do_shortcode( '[ratings id="' . $this->make_rated_post() . '"]' );

		$this->assertTrue( WP_PostRatings_Template::needs_assets(), 'A rendered shortcode did not ask for the assets.' );
	}

	/**
	 * The block asks for the assets.
	 *
	 * @return void
	 */
	public function test_the_block_asks_for_the_assets() {
		WP_PostRatings_Blocks::render_ratings( array( 'id' => $this->make_rated_post() ) );

		$this->assertTrue( WP_PostRatings_Template::needs_assets(), 'A rendered block did not ask for the assets.' );
	}

	/**
	 * The widget asks for the assets.
	 *
	 * @return void
	 */
	public function test_the_widget_asks_for_the_assets() {
		$this->make_rated_post();

		ob_start();
		the_widget( 'WP_PostRatings_Widget' );
		ob_end_clean();

		$this->assertTrue( WP_PostRatings_Template::needs_assets(), 'A rendered widget did not ask for the assets.' );
	}

	/**
	 * A post holding the ratings shortcode gets both assets from the head.
	 *
	 * @return void
	 */
	public function test_a_post_holding_the_ratings_shortcode_enqueues_both_assets() {
		$this->shape_a_rating_page( '[ratings]' );

		do_action( 'wp_enqueue_scripts' );

		$this->assertTrue( wp_style_is( 'wp-postratings', 'enqueued' ), 'The ratings shortcode is a reason to load the stylesheet.' );
		$this->assertTrue( wp_script_is( 'wp-postratings', 'enqueued' ), 'And the vote script.' );
	}

	/**
	 * A post holding the block gets both assets from the head.
	 *
	 * The check reads the block comment delimiter out of post_content, so this
	 * holds on a checkout that has never built the block too.
	 *
	 * @return void
	 */
	public function test_a_post_holding_the_ratings_block_enqueues_both_assets() {
		$this->shape_a_rating_page( '<!-- wp:wp-postratings/ratings {"id":1} /-->' );

		do_action( 'wp_enqueue_scripts' );

		$this->assertTrue( wp_style_is( 'wp-postratings', 'enqueued' ), 'The Ratings block is a reason to load the stylesheet.' );
		$this->assertTrue( wp_script_is( 'wp-postratings', 'enqueued' ), 'And the vote script.' );
	}

	/**
	 * An active widget gets both assets from the head.
	 *
	 * The widget renders in the sidebar, well after `wp_enqueue_scripts`, but
	 * whether it is in a sidebar at all is already on record by then.
	 *
	 * @return void
	 */
	public function test_an_active_widget_enqueues_both_assets() {
		wp_set_sidebars_widgets( array( 'sidebar-1' => array( 'ratings-widget-2' ) ) );

		do_action( 'wp_enqueue_scripts' );

		$this->assertTrue( wp_style_is( 'wp-postratings', 'enqueued' ), 'An active widget is a reason to load the stylesheet.' );
		$this->assertTrue( wp_script_is( 'wp-postratings', 'enqueued' ), 'And the vote script.' );
	}

	/**
	 * Both passes running on one request localizes the script once.
	 *
	 * Localized data appends rather than replaces, so an unguarded second
	 * pass would print the script's data twice.
	 *
	 * @return void
	 */
	public function test_the_footer_pass_does_not_double_the_localized_data() {
		$post_id = $this->make_rated_post();
		$this->shape_a_rating_page( '[ratings]' );

		do_action( 'wp_enqueue_scripts' );
		the_ratings( 'div', $post_id, false );

		ob_start();
		do_action( 'wp_footer' );
		ob_end_clean();

		$data = (string) wp_scripts()->get_data( 'wp-postratings', 'data' );

		$this->assertSame( 1, substr_count( $data, 'wpPostRatingsL10n' ), 'The footer pass localized the script a second time.' );
	}

	/**
	 * The comment author strip asks for the assets.
	 *
	 * It bypasses the template expansion the other paths go through, so it has
	 * to ask on its own -- the shapes it draws are CSS masks from the same
	 * stylesheet.
	 *
	 * @return void
	 */
	public function test_the_comment_author_strip_asks_for_the_assets() {
		WP_PostRatings_Template::ratings_images_comment_author( 0, 5, 4, 'star', 'Alice gives a rating of 4' );

		$this->assertTrue( WP_PostRatings_Template::needs_assets(), 'A rendered comment author strip did not ask for the assets.' );
	}

	/**
	 * A render path the detection cannot see says so through the filter.
	 *
	 * Rating markup fetched over AJAX into a page that itself renders no
	 * rating reaches neither pass: the fetch has no `wp_footer`, and the page
	 * carried no widget, shortcode or block for the head to find.
	 *
	 * @return void
	 */
	public function test_the_filter_can_ask_for_the_assets_on_a_page_holding_no_rating() {
		add_filter( 'wp_postratings_needs_assets', '__return_true' );

		do_action( 'wp_enqueue_scripts' );

		$this->assertTrue( wp_style_is( 'wp-postratings', 'enqueued' ), 'The filter asked for the stylesheet and did not get it.' );
		$this->assertTrue( wp_script_is( 'wp-postratings', 'enqueued' ), 'The filter asked for the vote script and did not get it.' );
	}

	/**
	 * The filter sees what the detection found, and can overrule it.
	 *
	 * Turning the head pass off is not a way to keep the assets off a page
	 * that shows a rating: the render still asks, and the footer still
	 * delivers.
	 *
	 * @return void
	 */
	public function test_the_filter_sees_the_detected_value_and_the_footer_still_delivers() {
		$post_id = $this->make_rated_post();
		$this->shape_a_rating_page( '[ratings]' );

		$seen = null;
		add_filter(
			'wp_postratings_needs_assets',
			function ( $needs_assets ) use ( &$seen ) {
				$seen = $needs_assets;
				return false;
			}
		);

		do_action( 'wp_enqueue_scripts' );

		$this->assertTrue( $seen, 'The filter was handed the detected value.' );
		$this->assertFalse( wp_style_is( 'wp-postratings', 'enqueued' ), 'The head pass enqueued over the filter.' );

		the_ratings( 'div', $post_id, false );

		ob_start();
		do_action( 'wp_footer' );
		ob_end_clean();

		$this->assertTrue( wp_style_is( 'wp-postratings', 'enqueued' ), 'The footer pass took the head filter as its own answer.' );
	}

	/**
	 * Put the request into the shape of a singular page carrying a rating.
	 *
	 * The head pass reads the current post, so a test asserting on what it
	 * enqueues first needs a post carrying one.
	 *
	 * @param string $content What the post carries.
	 *
	 * @return void
	 */
	private function shape_a_rating_page( $content ) {
		$GLOBALS['post'] = get_post( self::factory()->post->create( array( 'post_content' => $content ) ) );
	}
}
