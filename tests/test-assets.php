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

		unset( $GLOBALS['wp_scripts'], $GLOBALS['wp_styles'] );
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
}
