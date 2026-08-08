<?php
/**
 * Tests for the block.
 *
 * @package WP-PostRatings
 */

/**
 * The block, and the promise that it is an addition rather than a replacement.
 *
 * Most of what is worth asserting here is not "the block renders" -- that is
 * one line -- but the three things a later change could quietly break:
 *
 * * the shortcode still works, because it sits in published posts everywhere;
 * * the block and the shortcode render the *same* markup, because they are
 *   meant to share one renderer and nothing else checks that they still do;
 * * neither entry point is implemented in terms of the other, which is what
 *   stops the shortcode's attribute parsing leaking into the block.
 */
class WP_PostRatings_Blocks_Test extends WP_PostRatings_TestCase {

	/**
	 * The block this plugin registers.
	 *
	 * @var string
	 */
	const BLOCK = 'wp-postratings/ratings';

	/**
	 * The shortcode table as it stood before a test edited it.
	 *
	 * @var array
	 */
	private $shortcodes;

	/**
	 * Snapshots the global state these tests deliberately break.
	 *
	 * Two tests below unregister the shortcode or the block on purpose, to
	 * prove neither entry point is implemented in terms of the other. Both
	 * registries are process-global and WP_UnitTestCase restores neither, so
	 * without this the first such test silently disarms every test that runs
	 * after it -- and they fail with `[ratings]` rendering as literal text,
	 * which reads as a broken shortcode rather than a leaky fixture.
	 */
	public function set_up() {
		parent::set_up();

		$this->shortcodes = $GLOBALS['shortcode_tags'];

		$this->restore_block();
	}

	/**
	 * Puts both registries back.
	 */
	public function tear_down() {
		$GLOBALS['shortcode_tags'] = $this->shortcodes;

		$this->restore_block();

		parent::tear_down();
	}

	/**
	 * Returns the block registry to exactly the one registered block.
	 *
	 * Unregisters before registering rather than registering conditionally:
	 * the plugin has already registered it on `init` by the time any test
	 * runs, and registering a second time is a doing_it_wrong notice that the
	 * suite fails on.
	 *
	 * @return void
	 */
	private function restore_block() {
		if ( WP_Block_Type_Registry::get_instance()->is_registered( self::BLOCK ) ) {
			unregister_block_type( self::BLOCK );
		}

		WP_PostRatings_Blocks::register();
	}

	// --- registration ----------------------------------------------------

	/**
	 * The block registers, under the prefixed name.
	 *
	 * The `wp-` prefix is deliberate and is the one place the naming rule for
	 * commands and namespaces does not carry: those drop it, because a
	 * collision there is survivable and visible. A block name is written into
	 * post_content and stays there for the life of the post, so a collision
	 * would render another plugin's block inside somebody's published posts.
	 *
	 * @return void
	 */
	public function test_the_block_registers_under_the_prefixed_name() {
		$registry = WP_Block_Type_Registry::get_instance();

		$this->assertTrue( $registry->is_registered( self::BLOCK ), 'The ratings block registers.' );

		$this->assertFalse( $registry->is_registered( 'postratings/ratings' ), 'The unprefixed name is not also claimed.' );
	}

	/**
	 * The block is dynamic, so it carries a render callback.
	 *
	 * Without one a block saves its markup into post_content, and the whole
	 * reason a shortcode and a block can share a renderer is that it does not.
	 * It is also what makes the editor preview come from core's
	 * /wp/v2/block-renderer/ route rather than from a route of this plugin's.
	 *
	 * @return void
	 */
	public function test_the_block_is_dynamic() {
		$this->assertIsCallable( WP_Block_Type_Registry::get_instance()->get_registered( self::BLOCK )->render_callback, 'The block renders server-side.' );
	}

	/**
	 * The attributes come from block.json rather than from PHP.
	 *
	 * One per thing `[ratings]` accepts, and no more: the shortcode takes an
	 * id and a results flag, so the block takes an id and a results flag.
	 *
	 * @return void
	 */
	public function test_the_block_declares_the_shortcode_attributes() {
		$attributes = WP_Block_Type_Registry::get_instance()->get_registered( self::BLOCK )->attributes;

		$this->assertArrayHasKey( 'id', $attributes, 'The block takes an id.' );
		$this->assertArrayHasKey( 'results', $attributes, 'And a results flag.' );

		$this->assertSame( 'number', $attributes['id']['type'], 'The id arrives typed, unlike a shortcode attribute.' );
		$this->assertSame( 'boolean', $attributes['results']['type'], 'And so does the flag.' );

		$this->assertSame( 0, $attributes['id']['default'], 'Zero means the post the block sits in, as an empty [ratings] does.' );
		$this->assertFalse( $attributes['results']['default'], 'And the control, not the result, is the default.' );
	}

	// --- the shortcode survives -------------------------------------------

	/**
	 * Adding the block did not unregister the shortcode.
	 *
	 * If this ever fails, the block has stopped being an addition and become a
	 * replacement, and every published post holding `[ratings]` renders
	 * literal text.
	 *
	 * @return void
	 */
	public function test_the_shortcode_is_still_registered() {
		$this->assertTrue( shortcode_exists( 'ratings' ), 'The shortcode survives the block.' );
	}

	// --- the block and the shortcode agree --------------------------------

	/**
	 * The block and the shortcode render the same post identically.
	 *
	 * This is the assertion the whole design rests on. Two entry points that
	 * merely both work can drift; two that produce byte-identical markup are
	 * demonstrably going through one renderer.
	 *
	 * The nonce is per post and not per render, so two renders of the same
	 * post really are comparable byte for byte.
	 *
	 * @return void
	 */
	public function test_the_block_and_the_shortcode_render_the_same_markup() {
		$post_id = $this->make_rated_post();

		$block     = WP_PostRatings_Blocks::render_ratings( array( 'id' => $post_id ) );
		$shortcode = do_shortcode( '[ratings id="' . $post_id . '"]' );

		$this->assertNotSame( '', $block, 'The block rendered something.' );
		$this->assertStringContainsString( 'wp-postratings-' . $post_id, $block, 'And it is that post\'s rating.' );
		$this->assertSame( $shortcode, $block, 'And it is what the shortcode renders.' );
	}

	/**
	 * The same holds for the results-only form.
	 *
	 * @return void
	 */
	public function test_the_block_and_the_shortcode_agree_on_the_results_only_form() {
		$post_id = $this->make_rated_post();

		$block     = WP_PostRatings_Blocks::render_ratings(
			array(
				'id'      => $post_id,
				'results' => true,
			)
		);
		$shortcode = do_shortcode( '[ratings id="' . $post_id . '" results="true"]' );

		$this->assertStringNotContainsString( 'wp-postratings-vote', $block, 'The results-only form offers nothing to click.' );
		$this->assertSame( $shortcode, $block, 'And the two entry points agree.' );
	}

	/**
	 * An id of zero means the post being rendered, in both entry points.
	 *
	 * Zero is the block's default and an empty `[ratings]`'s default, so the
	 * two have to mean the same thing or an empty block and an empty shortcode
	 * rate different posts.
	 *
	 * @return void
	 */
	public function test_a_zero_id_means_the_current_post_in_both() {
		$post_id = $this->make_rated_post();

		// go_to() unsets the $id global and leaves $post holding the queried
		// post, which is the state a singular front end request is really in --
		// and it is that state, not an attribute, that both entry points read
		// when they are given nothing.
		$this->go_to( get_permalink( $post_id ) );

		$block = WP_PostRatings_Blocks::render_ratings( array() );

		$this->assertStringContainsString( 'wp-postratings-' . $post_id, $block, 'An attributeless block rates the post it is in.' );
		$this->assertSame( do_shortcode( '[ratings]' ), $block, 'And an attributeless shortcode rates the same one.' );
	}

	// --- neither is implemented in terms of the other ---------------------

	/**
	 * The block does not render by running the shortcode.
	 *
	 * Routing a block through do_shortcode() would make it inherit shortcode
	 * parsing it has no way to produce, and would break it outright the day
	 * anybody unregistered the shortcode. So: unregister the shortcode, and
	 * assert the block carries on rendering.
	 *
	 * @return void
	 */
	public function test_the_block_renders_with_the_shortcode_unregistered() {
		$post_id = $this->make_rated_post();

		remove_shortcode( 'ratings' );

		$this->assertStringContainsString( 'wp-postratings-' . $post_id, WP_PostRatings_Blocks::render_ratings( array( 'id' => $post_id ) ), 'The block does not need the shortcode.' );
	}

	/**
	 * The shortcode does not render by running the block.
	 *
	 * The other direction of the same rule, and the one a later "tidy-up" is
	 * likelier to break, because making the shortcode a thin wrapper over the
	 * block reads as removing duplication.
	 *
	 * @return void
	 */
	public function test_the_shortcode_renders_with_the_block_unregistered() {
		$post_id = $this->make_rated_post();

		unregister_block_type( self::BLOCK );

		$this->assertStringContainsString( 'wp-postratings-' . $post_id, do_shortcode( '[ratings id="' . $post_id . '"]' ), 'The shortcode does not need the block.' );
	}

	// --- the shared renderer ---------------------------------------------

	/**
	 * In a feed, both entry points return the note instead of a control.
	 *
	 * The guard lives in the shared renderer rather than in the shortcode
	 * precisely so the block gets it too: a dynamic block renders in a feed,
	 * and a rating control in a feed reader votes nowhere.
	 *
	 * @return void
	 */
	public function test_a_feed_gets_the_note_from_both_entry_points() {
		$post_id = $this->make_rated_post();

		$this->go_to( home_url( '/?feed=rss2' ) );

		$this->assertTrue( is_feed(), 'The fixture really is a feed request.' );
		$this->assertStringContainsString( 'visit this post to rate it', WP_PostRatings_Blocks::render_ratings( array( 'id' => $post_id ) ), 'The block returns the note.' );
		$this->assertStringContainsString( 'visit this post to rate it', do_shortcode( '[ratings id="' . $post_id . '"]' ), 'And so does the shortcode.' );
	}

	// --- rendering through the block parser -------------------------------

	/**
	 * A post holding the block comment renders the rating.
	 *
	 * The tests above call the callback directly, which does not prove the
	 * registration wired it to the name that gets saved into post_content.
	 * This goes through do_blocks(), the way a published post does.
	 *
	 * @return void
	 */
	public function test_a_saved_block_renders_through_the_block_parser() {
		$post_id = $this->make_rated_post();

		$rendered = do_blocks( '<!-- wp:wp-postratings/ratings {"id":' . $post_id . '} /-->' );

		$this->assertStringContainsString( 'wp-postratings-' . $post_id, $rendered, 'The saved block renders its rating.' );
	}

	/**
	 * A saved block carrying the results flag renders the read-only form.
	 *
	 * The parser hands the attributes over as JSON, so this is also the one
	 * place the boolean survives a round trip through post_content rather than
	 * being handed to the callback by a test.
	 *
	 * @return void
	 */
	public function test_a_saved_block_carries_the_results_flag_through_the_parser() {
		$post_id = $this->make_rated_post();

		$rendered = do_blocks( '<!-- wp:wp-postratings/ratings {"id":' . $post_id . ',"results":true} /-->' );

		$this->assertStringNotContainsString( 'wp-postratings-vote', $rendered, 'The saved block offers nothing to click.' );
		$this->assertStringContainsString( do_shortcode( '[ratings id="' . $post_id . '" results="true"]' ), $rendered, 'And renders what the equivalent shortcode renders.' );
	}
}
