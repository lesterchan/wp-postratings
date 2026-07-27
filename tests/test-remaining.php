<?php
/**
 * Tests for the remaining entry points without coverage of their own.
 *
 * @package WP-PostRatings
 */

/**
 * Query sorting, WP-Stats panels, the deprecated shims and the small helpers.
 *
 * @covers Postratings::sorting
 * @covers Postratings::query_vars
 * @covers Postratings_WPStats
 * @covers Postratings_Template::folder_info
 * @covers Postratings_Template::snippet
 */
class Test_Postratings_Remaining extends WP_PostRatings_TestCase {

	// --- query sorting ----------------------------------------------------

	/**
	 * The rating sort query vars are public.
	 *
	 * They are how a theme asks for a sorted archive by URL.
	 *
	 * @return void
	 */
	public function test_the_sort_query_vars_are_registered() {
		$vars = apply_filters( 'query_vars', array() );

		$this->assertContains( 'r_sortby', $vars );
		$this->assertContains( 'r_orderby', $vars );
	}

	/**
	 * Asking for the highest rated orders by average, best first.
	 *
	 * @return void
	 */
	public function test_highest_rated_sorting_orders_by_average() {
		$low  = $this->make_rated_post( 10, 10 );
		$high = $this->make_rated_post( 2, 10 );

		$query = new WP_Query(
			array(
				'r_sortby'  => 'highest_rated',
				'r_orderby' => 'desc',
				'fields'    => 'ids',
			)
		);

		$ids = array_values( array_intersect( $query->posts, array( $low, $high ) ) );

		$this->assertSame( array( $high, $low ), $ids );
	}

	/**
	 * Ascending gives the reverse order.
	 *
	 * @return void
	 */
	public function test_the_sort_direction_is_honoured() {
		$low  = $this->make_rated_post( 10, 10 );
		$high = $this->make_rated_post( 2, 10 );

		$query = new WP_Query(
			array(
				'r_sortby'  => 'highest_rated',
				'r_orderby' => 'asc',
				'fields'    => 'ids',
			)
		);

		$ids = array_values( array_intersect( $query->posts, array( $low, $high ) ) );

		$this->assertSame( array( $low, $high ), $ids );
	}

	/**
	 * Most rated orders by vote count.
	 *
	 * @return void
	 */
	public function test_most_rated_sorting_orders_by_vote_count() {
		$few  = $this->make_rated_post( 2, 10 );
		$many = $this->make_rated_post( 10, 10 );

		$query = new WP_Query(
			array(
				'r_sortby' => 'most_rated',
				'fields'   => 'ids',
			)
		);

		$ids = array_values( array_intersect( $query->posts, array( $few, $many ) ) );

		$this->assertSame( array( $many, $few ), $ids );
	}

	/**
	 * A junk sort direction falls back rather than reaching the SQL.
	 *
	 * @return void
	 */
	public function test_a_junk_sort_direction_is_ignored() {
		$this->make_rated_post( 2, 10 );

		$query = new WP_Query(
			array(
				'r_sortby'  => 'highest_rated',
				'r_orderby' => 'asc; DROP TABLE wp_posts',
				'fields'    => 'ids',
			)
		);

		$this->assertNotEmpty( $query->posts );
		$this->assertSame( '', $query->last_error ?? '' );
	}

	/**
	 * A query that asks for no rating sort is left alone.
	 *
	 * The filters are added globally, so a stale one would reorder every
	 * subsequent query on the page.
	 *
	 * @return void
	 */
	public function test_the_sort_filters_do_not_leak_to_later_queries() {
		$this->make_rated_post( 2, 10 );

		new WP_Query(
			array(
				'r_sortby' => 'highest_rated',
				'fields'   => 'ids',
			)
		);

		$plain = new WP_Query( array( 'fields' => 'ids' ) );

		$this->assertStringNotContainsString( 'ratings_average', $plain->request );
	}

	// --- WP-Stats ---------------------------------------------------------

	/**
	 * The plugin declares its own display toggles.
	 *
	 * Without this WP-Stats only learns a key exists once its checkbox has
	 * been submitted, so the panels start out off on a fresh install.
	 *
	 * @return void
	 */
	public function test_the_wp_stats_toggles_are_declared() {
		$defaults = Postratings_WPStats::display_defaults( array() );

		$this->assertSame( 1, $defaults['ratings'] );
		$this->assertArrayHasKey( 'rated_highest_post', $defaults );
		$this->assertArrayHasKey( 'rated_most_page', $defaults );
	}

	/**
	 * WP-Stats' own defaults win over the plugin's.
	 *
	 * @return void
	 */
	public function test_wp_stats_own_defaults_win() {
		$defaults = Postratings_WPStats::display_defaults( array( 'ratings' => 0 ) );

		$this->assertSame( 0, $defaults['ratings'] );
	}

	/**
	 * The options screen offers a checkbox per panel.
	 *
	 * @return void
	 */
	public function test_the_wp_stats_options_offer_every_panel() {
		$html = Postratings_WPStats::admin_most( '' );

		foreach ( array( 'rated_highest_post', 'rated_highest_page', 'rated_most_post', 'rated_most_page' ) as $key ) {
			$this->assertStringContainsString( $key, $html );
		}
	}

	/**
	 * A disabled panel contributes nothing to the page.
	 *
	 * @return void
	 */
	public function test_a_disabled_wp_stats_panel_is_skipped() {
		update_option( 'stats_display', array( 'ratings' => 0 ) );

		$this->assertSame( '', Postratings_WPStats::page_general( '' ) );
	}

	/**
	 * An enabled panel reports the vote total.
	 *
	 * @return void
	 */
	public function test_an_enabled_wp_stats_panel_reports_the_total() {
		update_option( 'stats_display', array( 'ratings' => 1 ) );

		$this->make_rated_post( 4, 18 );
		wp_cache_flush();

		$this->assertStringContainsString( 'WP-PostRatings', Postratings_WPStats::page_general( '' ) );
	}

	// --- image sets -------------------------------------------------------

	/**
	 * A normal set is described as non-custom.
	 *
	 * @return void
	 */
	public function test_a_normal_image_set_is_described() {
		$info = Postratings_Template::folder_info( 'stars' );

		$this->assertSame( 0, $info['custom'] );
		$this->assertContains( 'rating_on.gif', $info['images'] );
	}

	/**
	 * A per-step set is described as custom, with its own scale.
	 *
	 * @return void
	 */
	public function test_a_custom_image_set_is_described() {
		$info = Postratings_Template::folder_info( 'thumbs' );

		$this->assertSame( 1, $info['custom'] );
		$this->assertSame( 2, $info['max'] );
	}

	/**
	 * A folder that is not there does not warn.
	 *
	 * @return void
	 */
	public function test_a_missing_image_set_is_safe() {
		$info = Postratings_Template::folder_info( 'does-not-exist' );

		$this->assertSame( array(), $info['images'] );
	}

	/**
	 * Every shipped set is listed.
	 *
	 * @return void
	 */
	public function test_every_shipped_set_is_listed() {
		$folders = Postratings_Template::image_folders();

		foreach ( array( 'stars', 'thumbs', 'numbers', 'bars' ) as $expected ) {
			$this->assertContains( $expected, $folders );
		}
	}

	// --- helpers ----------------------------------------------------------

	/**
	 * Long text is truncated with an ellipsis, short text is not.
	 *
	 * @return void
	 */
	public function test_the_snippet_helper_truncates() {
		$this->assertSame( 'abcde...', Postratings_Template::snippet( 'abcdefghij', 5 ) );
		$this->assertSame( 'abc', Postratings_Template::snippet( 'abc', 10 ) );
	}

	/**
	 * A password protected post reports that rather than leaking its content.
	 *
	 * @return void
	 */
	public function test_a_protected_post_excerpt_is_withheld() {
		$post_id = self::factory()->post->create(
			array(
				'post_password' => 'secret',
				'post_content'  => 'Confidential body copy.',
				'post_status'   => 'publish',
			)
		);

		$excerpt = Postratings_Template::post_excerpt( $post_id, '', 'Confidential body copy.' );

		$this->assertStringNotContainsString( 'Confidential', $excerpt );
		$this->assertStringContainsString( 'protected post', $excerpt );
	}

	/**
	 * Shortcodes are stripped from a generated excerpt.
	 *
	 * Otherwise the rating shortcode would recurse into itself.
	 *
	 * @return void
	 */
	public function test_shortcodes_are_stripped_from_the_excerpt() {
		$excerpt = Postratings_Template::post_excerpt( 0, '', 'Before [ratings] after.' );

		$this->assertStringNotContainsString( '[ratings]', $excerpt );
	}

	/**
	 * The shortcode says so rather than rendering inside a feed.
	 *
	 * @return void
	 */
	public function test_the_shortcode_is_inert_in_a_feed() {
		$this->go_to( home_url( '/?feed=rss2' ) );

		$this->assertTrue( is_feed() );
		$this->assertStringContainsString( 'visit this post to rate it', do_shortcode( '[ratings]' ) );
	}

	// --- back compatibility -----------------------------------------------

	/**
	 * The deprecated shims still forward to their replacements.
	 *
	 * They were never documented API, but they were global functions in a
	 * plugin this old, so something out there calls them.
	 *
	 * @return void
	 */
	public function test_the_deprecated_shims_forward() {
		$this->setExpectedDeprecated( 'ratings_get_ipaddress' );
		$this->setExpectedDeprecated( 'ratings_get_raw_ipaddress' );
		$this->setExpectedDeprecated( 'ratings_lock_file' );
		$this->setExpectedDeprecated( 'snippet_text' );

		$this->assertSame( Postratings_Rating::get_raw_ip(), ratings_get_raw_ipaddress() );
		$this->assertSame( Postratings_Rating::get_ip(), ratings_get_ipaddress() );
		$this->assertSame( Postratings_Rating::lock_file( 7 ), ratings_lock_file( 7 ) );
		$this->assertSame( Postratings_Template::snippet( 'abc', 10 ), snippet_text( 'abc', 10 ) );
	}

	/**
	 * The image builders still exist under their old names.
	 *
	 * @return void
	 */
	public function test_the_deprecated_image_builders_forward() {
		$this->setExpectedDeprecated( 'get_ratings_images' );

		$expected = Postratings_Template::ratings_images( 0, 5, 3, 'stars', 'Alt', 0 );

		$this->assertSame( $expected, get_ratings_images( 0, 5, 3, 'stars', 'Alt', 0 ) );
	}

	/**
	 * The plugin's constants are defined.
	 *
	 * @return void
	 */
	public function test_the_path_constants_are_defined() {
		$this->assertStringEndsWith( '/', WP_POSTRATINGS_DIR );
		$this->assertStringEndsWith( '/', WP_POSTRATINGS_URL );
		$this->assertSame( WP_POSTRATINGS_VERSION, '2.0.0' );
	}

	/**
	 * The version agrees between the constant and the plugin header.
	 *
	 * @return void
	 */
	public function test_the_version_agrees_with_the_header() {
		$data = get_file_data( WP_POSTRATINGS_MAIN_FILE, array( 'Version' => 'Version' ) );

		$this->assertSame( WP_POSTRATINGS_VERSION, $data['Version'] );
	}
}
