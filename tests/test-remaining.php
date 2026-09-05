<?php
/**
 * Tests for the remaining entry points without coverage of their own.
 *
 * @package WP-PostRatings
 */

/**
 * Query sorting, WP-Stats panels, the deprecated shims and the small helpers.
 *
 * @covers WP_PostRatings::sorting
 * @covers WP_PostRatings::query_vars
 * @covers WP_PostRatings_WPStats
 * @covers WP_PostRatings_Shapes
 * @covers WP_PostRatings_Template::snippet
 */
class WP_PostRatings_Remaining_Test extends WP_PostRatings_TestCase {

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

		$this->assertContains( 'r_sortby', $vars, 'The sort column query var is registered.' );
		$this->assertContains( 'r_orderby', $vars, 'And the direction one.' );
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

		$this->assertSame( array( $high, $low ), $ids, 'Highest rated sorts by average, best first.' );
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

		$this->assertSame( array( $low, $high ), $ids, 'And the direction is honoured.' );
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

		$this->assertSame( array( $many, $few ), $ids, 'Most rated sorts by vote count, most first.' );
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

		$this->assertNotEmpty( $query->posts, 'A junk sort direction is ignored rather than producing an empty result.' );
		$this->assertSame( '', $query->last_error ?? '', 'A junk direction is ignored rather than reaching ORDER BY.' );
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

		$this->assertStringNotContainsString( 'ratings_average', $plain->request, 'The sort filters are removed, so a later query is unaffected.' );
	}

	// --- WP-Stats ---------------------------------------------------------

	/**
	 * The section entry carries exactly the three keys WP-Stats asks for.
	 *
	 * @return void
	 */
	public function test_the_wp_stats_section_has_the_contract_shape() {
		$sections = apply_filters( 'wp_stats_sections', array() );

		$this->assertArrayHasKey( 'wp_postratings', $sections, 'the section is keyed by the plugin slug with underscores' );
		$this->assertSame(
			array( 'title', 'priority', 'render' ),
			array_keys( $sections['wp_postratings'] ),
			'the entry must carry title, priority and render, in that order and nothing else'
		);
		$this->assertIsString( $sections['wp_postratings']['title'], 'The WP-Stats section entry carries a title string.' );
		$this->assertIsInt( $sections['wp_postratings']['priority'], 'The WP-Stats section entry carries an integer priority.' );
		$this->assertIsCallable( $sections['wp_postratings']['render'], 'The WP-Stats section entry carries something callable to render with.' );
	}

	/**
	 * Another plugin's entry is left alone.
	 *
	 * @return void
	 */
	public function test_the_wp_stats_filter_keeps_a_sibling_entry() {
		$sections = WP_PostRatings_WPStats::register_section( array( 'wp_polls' => array( 'title' => 'Polls' ) ) );

		$this->assertArrayHasKey( 'wp_polls', $sections, 'a contributor must never drop a sibling' );
		$this->assertArrayHasKey( 'wp_postratings', $sections, 'Adding this plugin entry leaves a sibling plugin entry in place.' );
	}

	/**
	 * Opting out contributes nothing at all, not an empty section.
	 *
	 * @return void
	 */
	public function test_a_disabled_wp_stats_section_is_not_offered() {
		$this->set_options( array( 'stats_display' => 0 ) );

		$this->assertSame( array(), WP_PostRatings_WPStats::register_section( array() ), 'With the section disabled there is nothing to register.' );
	}

	/**
	 * The section body reports the vote total, and echoes rather than returns.
	 *
	 * @return void
	 */
	public function test_the_wp_stats_section_echoes_the_vote_total() {
		$this->make_rated_post( 4, 18 );
		wp_cache_flush();

		ob_start();
		$returned = WP_PostRatings_WPStats::render();
		$html     = ob_get_clean();

		$this->assertNull( $returned, 'render() echoes; it does not return markup' );
		$this->assertStringContainsString( 'Highest Rated Post', $html, 'The section names the highest rated post.' );
		$this->assertStringContainsString( '4', $html, 'And gives its figure.' );
	}

	/**
	 * A shared row a sibling has already deleted means on, not off.
	 *
	 * All seven contributing plugins fold stats_display in and delete it, so
	 * six of them find nothing. Reading that as a deliberate opt-out is how a
	 * site that updated WP-Stats first loses its ratings block with no error
	 * anywhere. This is the guard for that.
	 *
	 * @return void
	 */
	public function test_an_already_deleted_shared_row_migrates_to_on() {
		$this->build_install_without_stats_settings();

		delete_option( 'stats_display' );

		WP_PostRatings_Options::maybe_migrate();

		$this->assertSame( 1, (int) WP_PostRatings_Options::get( 'stats_display' ), 'an absent shared row must mean on' );
	}

	/**
	 * A shared row that is present and switched off is still honoured.
	 *
	 * @return void
	 */
	public function test_a_shared_row_that_says_off_migrates_to_off() {
		$this->build_install_without_stats_settings();

		update_option( 'stats_display', array( 'polls' => 1 ) );

		WP_PostRatings_Options::maybe_migrate();

		$this->assertSame( 0, (int) WP_PostRatings_Options::get( 'stats_display' ), 'a stored opt-out must survive the migration' );
	}

	/**
	 * The shared rows are deleted once they have been folded in.
	 *
	 * @return void
	 */
	public function test_the_shared_rows_are_deleted_by_the_migration() {
		$this->build_install_without_stats_settings();

		update_option( 'stats_display', array( 'ratings' => 1 ) );
		update_option( 'stats_mostlimit', '7' );

		WP_PostRatings_Options::maybe_migrate();

		$this->assertSame( 7, (int) WP_PostRatings_Options::get( 'stats_most_limit' ), 'The shared row is read before the migration deletes it.' );
		$this->assertFalse( get_option( 'stats_display' ), 'the shared row should be gone' );
		$this->assertFalse( get_option( 'stats_mostlimit' ), 'the shared row should be gone' );
	}

	/**
	 * A settings row with no WP-Stats keys yet, as an un-migrated site has.
	 *
	 * @return void
	 */
	private function build_install_without_stats_settings() {
		$options = WP_PostRatings_Options::defaults();

		unset( $options['stats_display'], $options['stats_most_limit'] );

		update_option( WP_PostRatings_Options::OPTION, $options );
	}

	/**
	 * The list length comes from this plugin's own setting.
	 *
	 * @return void
	 */
	public function test_the_wp_stats_limit_comes_from_our_own_row() {
		$this->set_options( array( 'stats_most_limit' => 3 ) );

		$this->assertSame( 3, WP_PostRatings_WPStats::most_limit(), 'And the limit is read from the row of this plugin, not the shared one.' );
	}

	// --- shapes -----------------------------------------------------------

	/**
	 * The shape registry answers for every shipped shape.
	 *
	 * @return void
	 */
	public function test_every_shipped_shape_is_listed() {
		$names = WP_PostRatings_Shapes::names();

		foreach ( array( 'star', 'heart', 'thumb', 'plusminus' ) as $expected ) {
			$this->assertContains( $expected, $names, $expected . ' is shipped but not listed by names().' );
		}
	}

	/**
	 * A scale shape is not an up/down one, and the reverse.
	 *
	 * @return void
	 */
	public function test_the_two_families_are_distinguished() {
		$this->assertFalse( WP_PostRatings_Shapes::is_updown( 'star' ), 'A star is a scale shape, not an up-down one.' );
		$this->assertTrue( WP_PostRatings_Shapes::is_updown( 'thumb' ), 'A thumb is an up-down shape.' );
	}

	/**
	 * An unknown shape name answers null rather than warning.
	 *
	 * @return void
	 */
	public function test_an_unknown_shape_is_safe() {
		$this->assertNull( WP_PostRatings_Shapes::get( 'does-not-exist' ), 'An unknown shape reads back null rather than raising a notice.' );
		$this->assertFalse( WP_PostRatings_Shapes::is_updown( 'does-not-exist' ), 'An unknown shape is not treated as up-down by default.' );
		$this->assertSame( '', WP_PostRatings_Shapes::data_uri( 'does-not-exist' ), 'An unknown shape yields nothing rather than a broken data URI.' );
	}

	// --- helpers ----------------------------------------------------------

	/**
	 * Long text is truncated with an ellipsis, short text is not.
	 *
	 * @return void
	 */
	public function test_the_snippet_helper_truncates() {
		$this->assertSame( 'abcde...', WP_PostRatings_Template::snippet( 'abcdefghij', 5 ), 'A string past the budget is cut and given an ellipsis.' );
		$this->assertSame( 'abc', WP_PostRatings_Template::snippet( 'abc', 10 ), 'A string within it is returned whole.' );
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

		$excerpt = WP_PostRatings_Template::post_excerpt( $post_id, '', 'Confidential body copy.' );

		$this->assertStringNotContainsString( 'Confidential', $excerpt, 'A protected post does not leak its excerpt.' );
		$this->assertStringContainsString( 'protected post', $excerpt, 'It says it is protected instead.' );
	}

	/**
	 * Shortcodes are stripped from a generated excerpt.
	 *
	 * Otherwise the rating shortcode would recurse into itself.
	 *
	 * @return void
	 */
	public function test_shortcodes_are_stripped_from_the_excerpt() {
		$excerpt = WP_PostRatings_Template::post_excerpt( 0, '', 'Before [ratings] after.' );

		$this->assertStringNotContainsString( '[ratings]', $excerpt, 'A shortcode is stripped from the excerpt rather than rendered in it.' );
	}

	/**
	 * The shortcode says so rather than rendering inside a feed.
	 *
	 * @return void
	 */
	public function test_the_shortcode_is_inert_in_a_feed() {
		$this->go_to( home_url( '/?feed=rss2' ) );

		$this->assertTrue( is_feed(), 'The fixture really is a feed request, or the shortcode test below proves nothing.' );
		$this->assertStringContainsString( 'visit this post to rate it', do_shortcode( '[ratings]' ), 'In a feed the shortcode says where to vote rather than offering to.' );
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

		$this->assertSame( WP_PostRatings_Rating::get_raw_ip(), ratings_get_raw_ipaddress(), 'The raw address shim forwards.' );
		$this->assertSame( WP_PostRatings_Rating::get_ip(), ratings_get_ipaddress(), 'The hashed address shim.' );
		$this->assertSame( WP_PostRatings_Rating::lock_file( 7 ), ratings_lock_file( 7 ), 'The lock file shim.' );
		$this->assertSame( WP_PostRatings_Template::snippet( 'abc', 10 ), snippet_text( 'abc', 10 ), 'And the snippet shim.' );
	}

	/**
	 * The image builders still exist under their old names.
	 *
	 * @return void
	 */
	public function test_the_deprecated_image_builders_forward() {
		$this->setExpectedDeprecated( 'get_ratings_images' );

		$expected = WP_PostRatings_Template::ratings_images( 0, 5, 3, 'stars', 'Alt', 0 );

		$this->assertSame( $expected, get_ratings_images( 0, 5, 3, 'stars', 'Alt', 0 ), 'The deprecated image builder forwards to the shape renderer.' );
	}

	/**
	 * The plugin's constants are defined.
	 *
	 * @return void
	 */
	public function test_the_path_constants_are_defined() {
		$this->assertStringEndsWith( '/', WP_POSTRATINGS_DIR, 'The directory constant is slash terminated, so it can be concatenated.' );
		$this->assertStringEndsWith( '/', WP_POSTRATINGS_URL, 'And so is the URL constant.' );
		$this->assertSame( WP_POSTRATINGS_VERSION, '2.1.0', 'The version constant is this release.' );
	}

	/**
	 * The version agrees between the constant and the plugin header.
	 *
	 * @return void
	 */
	public function test_the_version_agrees_with_the_header() {
		$data = get_file_data( WP_POSTRATINGS_MAIN_FILE, array( 'Version' => 'Version' ) );

		$this->assertSame( WP_POSTRATINGS_VERSION, $data['Version'], 'And agrees with the plugin header.' );
	}
}
