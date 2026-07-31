<?php
/**
 * Tests for the public template tags.
 *
 * These names and signatures are the plugin's API, so they are pinned here
 * against accidental change.
 *
 * @package WP-PostRatings
 */

/**
 * Rendering, token expansion and the stats tags.
 *
 * @covers ::the_ratings
 * @covers ::the_ratings_results
 * @covers ::the_ratings_vote
 * @covers ::expand_ratings_template
 * @covers WP_PostRatings_Template::expand
 */
class WP_PostRatings_Template_Tags_Test extends WP_PostRatings_TestCase {

	/**
	 * Every documented tag still exists and is callable.
	 *
	 * @return void
	 */
	public function test_the_public_api_is_intact() {
		$tags = array(
			'the_ratings',
			'the_ratings_results',
			'the_ratings_vote',
			'expand_ratings_template',
			'check_rated',
			'check_allowtorate',
			'comment_author_ratings',
			'ratings_images_folder',
			'get_ratings_users',
			'get_most_rated',
			'get_most_rated_category',
			'get_most_rated_range',
			'get_most_rated_range_category',
			'get_highest_rated',
			'get_highest_rated_category',
			'get_highest_rated_range',
			'get_highest_rated_range_category',
			'get_lowest_rated',
			'get_lowest_rated_category',
			'get_lowest_rated_range',
			'get_highest_score',
			'get_highest_score_category',
			'get_highest_score_range',
			'get_highest_score_range_category',
			'get_highest_rated_tag',
			'get_lowest_rated_tag',
		);

		foreach ( $tags as $tag ) {
			$this->assertTrue( function_exists( $tag ), $tag . '() is gone' );
		}
	}

	/**
	 * Every %TOKEN% is replaced, none left literal.
	 *
	 * A missed replacement shows up on the page as the raw token.
	 *
	 * @return void
	 */
	public function test_every_token_is_replaced() {
		$post_id = $this->make_rated_post( 4, 18 );

		$tokens = array(
			'%RATINGS_IMAGES%',
			'%RATINGS_IMAGES_VOTE%',
			'%RATINGS_ALT_TEXT%',
			'%RATINGS_TEXT%',
			'%RATINGS_MAX%',
			'%RATINGS_SCORE%',
			'%RATINGS_AVERAGE%',
			'%RATINGS_PERCENTAGE%',
			'%RATINGS_USERS%',
			'%POST_ID%',
			'%POST_TITLE%',
			'%POST_URL%',
			'%POST_EXCERPT%',
			'%POST_THUMBNAIL%',
		);

		$output = expand_ratings_template( implode( ' ', $tokens ), $post_id, null, 0, false );

		foreach ( $tokens as $token ) {
			$this->assertStringNotContainsString( $token, $output, $token . ' was not replaced' );
		}
	}

	/**
	 * The figures come out as configured.
	 *
	 * @return void
	 */
	public function test_the_figures_are_correct() {
		$post_id = $this->make_rated_post( 4, 18 );

		$this->assertSame( '4', expand_ratings_template( '%RATINGS_USERS%', $post_id, null, 0, false ) );
		$this->assertSame( '18', expand_ratings_template( '%RATINGS_SCORE%', $post_id, null, 0, false ) );
		$this->assertSame( '4.50', expand_ratings_template( '%RATINGS_AVERAGE%', $post_id, null, 0, false ) );
		$this->assertSame( '5', expand_ratings_template( '%RATINGS_MAX%', $post_id, null, 0, false ) );
		$this->assertSame( '90.00', expand_ratings_template( '%RATINGS_PERCENTAGE%', $post_id, null, 0, false ) );
	}

	/**
	 * An unrated post reports zeroes rather than dividing by zero.
	 *
	 * @return void
	 */
	public function test_an_unrated_post_reports_zero() {
		$post_id = $this->make_rated_post( 0, 0 );

		$this->assertSame( '0', expand_ratings_template( '%RATINGS_USERS%', $post_id, null, 0, false ) );
		$this->assertSame( '0.00', expand_ratings_template( '%RATINGS_AVERAGE%', $post_id, null, 0, false ) );
	}

	/**
	 * Expanding a template restores the global $post it reassigns.
	 *
	 * It used to leak the other post to the rest of the loop.
	 *
	 * @return void
	 */
	public function test_the_global_post_is_restored() {
		global $post;

		$current = $this->make_rated_post( 1, 5 );
		$other   = $this->make_rated_post( 2, 6 );

		$post = get_post( $current );
		setup_postdata( $post );

		expand_ratings_template( '%POST_EXCERPT%', $other, null, 0, false );

		$this->assertSame( $current, (int) $post->ID, 'the global $post leaked' );

		wp_reset_postdata();
	}

	/**
	 * The vote markup carries data attributes, not inline handlers.
	 *
	 * The old inline onmouseover/onclick interpolated per-rating text through
	 * esc_js( esc_attr( ... ) ) into a JS string inside an HTML attribute.
	 *
	 * @return void
	 */
	public function test_the_vote_markup_has_no_inline_handlers() {
		$post_id = $this->make_rated_post( 0, 0 );

		$output = the_ratings_vote( $post_id );

		$this->assertStringNotContainsString( 'onmouseover', $output );
		$this->assertStringNotContainsString( 'onclick', $output );
		$this->assertStringContainsString( 'data-post-id="' . $post_id . '"', $output );
		$this->assertStringContainsString( 'role="radiogroup"', $output );
		$this->assertStringContainsString( 'type="radio"', $output );
	}

	/**
	 * A rating label carrying markup is escaped exactly once.
	 *
	 * The labels are visible text in a <span> now rather than being pushed
	 * through esc_js( esc_attr( ... ) ) into a JS string inside an attribute,
	 * which is where the double escaping used to happen.
	 *
	 * @return void
	 */
	public function test_rating_labels_are_escaped_once() {
		$options                    = WP_PostRatings_Options::get();
		$options['ratings']['text'] = array( "O'Brien & \"co\"", 'b', 'c', 'd', 'e' );
		WP_PostRatings_Options::update( $options );

		$post_id = $this->make_rated_post( 0, 0 );
		$output  = the_ratings_vote( $post_id );

		$this->assertStringContainsString( 'O&#039;Brien &amp; &quot;co&quot;', $output );
		$this->assertStringNotContainsString( '&amp;amp;', $output, 'the label was double encoded' );
	}

	/**
	 * A visitor who has rated sees the results, not the voting strip.
	 *
	 * @return void
	 */
	public function test_a_rated_visitor_sees_results() {
		$this->set_option( 'check_method', 2 );

		$post_id = $this->make_rated_post( 1, 4 );
		$this->log_rating( $post_id, 4, 'Guest', '203.0.113.1' );

		$output = the_ratings( 'div', $post_id, false );

		$this->assertStringNotContainsString( 'wp-postratings-vote', $output );
		$this->assertStringContainsString( 'wp-postratings', $output );
	}

	/**
	 * A visitor who may not rate gets the permission template.
	 *
	 * @return void
	 */
	public function test_a_visitor_without_permission_sees_the_notice() {
		$this->set_option( 'allowtorate', 1 );
		wp_set_current_user( 0 );

		$post_id = $this->make_rated_post( 1, 4 );

		$this->assertStringContainsString( 'registered member', the_ratings( 'div', $post_id, false ) );
	}

	/**
	 * A post with no ratings uses the "none" template.
	 *
	 * @return void
	 */
	public function test_an_unrated_post_uses_the_none_template() {
		$post_id = $this->make_rated_post( 0, 0 );

		$this->assertStringContainsString( 'No Ratings Yet', the_ratings_vote( $post_id ) );
	}

	/**
	 * The shortcode renders and is not the raw tag.
	 *
	 * @return void
	 */
	public function test_the_shortcode_renders() {
		$post_id = $this->make_rated_post( 4, 18 );

		$output = do_shortcode( '[ratings id="' . $post_id . '"]' );

		$this->assertStringContainsString( 'wp-postratings-' . $post_id, $output );
		$this->assertStringNotContainsString( '[ratings', $output );
	}

	/**
	 * The results-only shortcode omits the voting strip.
	 *
	 * @return void
	 */
	public function test_the_results_shortcode_omits_voting() {
		$post_id = $this->make_rated_post( 4, 18 );

		$output = do_shortcode( '[ratings id="' . $post_id . '" results="true"]' );

		$this->assertStringNotContainsString( 'wp-postratings-vote', $output );
	}

	/**
	 * The markup references no image files at all.
	 *
	 * Every shape is a CSS mask since 2.0.0, so a plugin installed under any
	 * directory name cannot 404 its own artwork -- there is none to fetch.
	 *
	 * @return void
	 */
	public function test_the_markup_references_no_images() {
		$post_id = $this->make_rated_post( 4, 18 );

		$output = expand_ratings_template( '%RATINGS_IMAGES%%RATINGS_IMAGES_VOTE%', $post_id, null, 0, false );

		$this->assertStringNotContainsString( '<img', $output );
		$this->assertStringNotContainsString( '/images/', $output );
		$this->assertStringContainsString( 'data:image/svg+xml,', $output );
	}

	// --- stats ------------------------------------------------------------

	/**
	 * The ranking lists order as their names promise.
	 *
	 * @return void
	 */
	public function test_the_rankings_order_correctly() {
		// Ten voters averaging 1.0, versus two averaging 5.0.
		$low  = $this->make_rated_post( 10, 10 );
		$high = $this->make_rated_post( 2, 10 );

		// %POST_ID% is unambiguous; permalinks vary with the rewrite rules.
		$options                              = WP_PostRatings_Options::get();
		$options['templates']['highestrated'] = '<li>[%POST_ID%]</li>';
		$options['templates']['mostrated']    = '<li>[%POST_ID%]</li>';
		WP_PostRatings_Options::update( $options );

		$this->assertSame(
			array( $high, $low ),
			$this->ranked_ids( get_highest_rated( '', 0, 10, 0, false ) ),
			'highest rated did not lead with the better average'
		);

		$this->assertSame(
			array( $low, $high ),
			$this->ranked_ids( get_lowest_rated( '', 0, 10, 0, false ) ),
			'lowest rated did not lead with the worse average'
		);

		$this->assertSame(
			array( $low, $high ),
			$this->ranked_ids( get_most_rated( '', 0, 10, 0, false ) ),
			'most rated did not lead with the higher vote count'
		);
	}

	/**
	 * Pull the post ids out of a rendered ranking, in order.
	 *
	 * @param string $output Rendered list.
	 *
	 * @return array
	 */
	private function ranked_ids( $output ) {
		preg_match_all( '/\[(\d+)\]/', $output, $matches );

		return array_map( 'intval', $matches[1] );
	}

	/**
	 * A list with nothing to show says so rather than emitting an empty list.
	 *
	 * @return void
	 */
	public function test_an_empty_ranking_reports_na() {
		$this->assertStringContainsString( 'N/A', get_highest_rated( 'nonexistent_type', 0, 10, 0, false ) );
	}

	/**
	 * A category filter with no terms cannot build "IN ()".
	 *
	 * @return void
	 */
	public function test_an_empty_term_list_is_safe() {
		$this->assertStringContainsString( 'N/A', get_highest_rated_category( array(), '', 0, 10, 0, false ) );
		$this->assertStringContainsString( 'N/A', get_highest_rated_category( 0, '', 0, 10, 0, false ) );
	}

	/**
	 * The minimum-votes floor is applied.
	 *
	 * @return void
	 */
	public function test_minimum_votes_filters_the_list() {
		$this->make_rated_post( 1, 5 );

		$this->assertStringContainsString( 'N/A', get_highest_rated( '', 50, 10, 0, false ) );
	}

	/**
	 * The total vote count adds up.
	 *
	 * @return void
	 */
	public function test_the_vote_total_adds_up() {
		$this->make_rated_post( 4, 18 );
		$this->make_rated_post( 6, 20 );

		wp_cache_flush();

		$this->assertSame( 10, (int) get_ratings_users( false ) );
	}
}
