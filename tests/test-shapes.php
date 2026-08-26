<?php
/**
 * Tests for the SVG shape registry.
 *
 * Replaces the RTL image tests: with the GIF sets gone there are no half
 * images, no per-set RTL variants and no separate RTL stylesheet -- direction
 * is handled by logical properties in CSS, with no PHP branch to test.
 *
 * @package WP-PostRatings
 */

/**
 * Shape definitions, the legacy mapping and the rendered markup.
 *
 * @covers WP_PostRatings_Shapes
 * @covers WP_PostRatings_Template::resolve_shape
 * @covers WP_PostRatings_Template::fill_percentage
 */
class WP_PostRatings_Shapes_Test extends WP_PostRatings_TestCase {

	/**
	 * Both interaction families ship.
	 *
	 * A scale is one value out of N; an up/down is a pair of opposing actions.
	 * They are different controls, so both have to exist.
	 *
	 * @return void
	 */
	public function test_both_families_ship() {
		$shapes = WP_PostRatings_Shapes::all();

		$scale  = array_filter( $shapes, static fn( $s ) => WP_PostRatings_Shapes::SCALE === $s['type'] );
		$updown = array_filter( $shapes, static fn( $s ) => WP_PostRatings_Shapes::UPDOWN === $s['type'] );

		$this->assertNotEmpty( $scale, 'At least one scale shape ships.' );
		$this->assertNotEmpty( $updown, 'At least one up-down shape ships.' );
	}

	/**
	 * Every shape is well formed.
	 *
	 * @return void
	 */
	public function test_every_shape_is_valid() {
		foreach ( WP_PostRatings_Shapes::all() as $name => $shape ) {
			$this->assertTrue( WP_PostRatings_Shapes::is_valid( $shape ), $name . ' is malformed' );
			$this->assertNotEmpty( $shape['label'], $name . ' has no label' );
		}
	}

	/**
	 * Every shape produces a usable mask.
	 *
	 * @return void
	 */
	public function test_every_shape_produces_a_data_uri() {
		foreach ( WP_PostRatings_Shapes::names() as $name ) {
			// Except a numeric one, which draws the position instead of a mask
			// and is asserted on below.
			if ( WP_PostRatings_Shapes::is_numeric_shape( $name ) ) {
				continue;
			}

			$variants = WP_PostRatings_Shapes::is_updown( $name ) ? array( 'up', 'down' ) : array( '' );

			foreach ( $variants as $variant ) {
				$uri = WP_PostRatings_Shapes::data_uri( $name, $variant );

				$this->assertStringStartsWith( 'data:image/svg+xml,', $uri, $name );
				$this->assertStringContainsString( '%3Cpath', $uri, $name . ' has no path' );
			}
		}
	}

	/**
	 * A data URI carries no quote character.
	 *
	 * An unquoted CSS url() token may not contain one, and leaving it raw makes
	 * the browser discard the whole declaration silently: no mask, no error,
	 * and invisible ratings.
	 *
	 * @return void
	 */
	public function test_a_data_uri_contains_no_quotes() {
		foreach ( WP_PostRatings_Shapes::names() as $name ) {
			$this->assertDoesNotMatchRegularExpression(
				'/[\'"]/',
				WP_PostRatings_Shapes::data_uri( $name ),
				$name . ' would break the url() token'
			);
		}
	}

	/**
	 * The two glyphs of an up/down shape differ.
	 *
	 * @return void
	 */
	public function test_up_and_down_glyphs_differ() {
		foreach ( WP_PostRatings_Shapes::names() as $name ) {
			if ( ! WP_PostRatings_Shapes::is_updown( $name ) ) {
				continue;
			}

			$this->assertNotSame(
				WP_PostRatings_Shapes::data_uri( $name, 'up' ),
				WP_PostRatings_Shapes::data_uri( $name, 'down' ),
				$name . ' uses one glyph for both directions'
			);
		}
	}

	// --- the numeric family -----------------------------------------------

	/**
	 * A numeric shape is a scale, not a third kind of control.
	 *
	 * It answers a scale's question -- one value out of N -- and differs only
	 * in what a point on it is drawn with. Anything that branches on is_updown()
	 * therefore has to go on treating it as a scale, or a five point rating
	 * turns into a two way vote.
	 *
	 * @return void
	 */
	public function test_a_numeric_shape_is_still_a_scale() {
		$this->assertTrue( WP_PostRatings_Shapes::is_numeric_shape( 'number' ), 'The shipped numeric shape is numeric.' );
		$this->assertFalse( WP_PostRatings_Shapes::is_updown( 'number' ), 'And is not an up/down pair.' );
		$this->assertSame(
			WP_PostRatings_Shapes::SCALE,
			WP_PostRatings_Shapes::family( 'number' ),
			'So the settings screen offers it under the scale radio.'
		);
		$this->assertFalse( WP_PostRatings_Shapes::is_numeric_shape( 'star' ), 'A mask shape is not numeric.' );
	}

	/**
	 * A numeric shape carries no mask, and says so rather than emitting an empty one.
	 *
	 * An empty data URI wrapped in url() is a parse error, and a browser drops
	 * the whole declaration when it hits one -- silently, which is how an
	 * invisible rating gets shipped.
	 *
	 * @return void
	 */
	public function test_a_numeric_shape_emits_no_mask() {
		$this->assertSame( '', WP_PostRatings_Shapes::data_uri( 'number' ), 'A numeric shape has no data URI.' );

		$markup = WP_PostRatings_Template::ratings_images( 0, 5, 3.7, 'number', '' );

		$this->assertStringNotContainsString( '--wp-postratings-shape:', $markup, 'And emits no mask custom property.' );
		$this->assertStringNotContainsString( 'url(', $markup, 'And no url() at all.' );
	}

	/**
	 * Every position on a numeric scale draws its own number.
	 *
	 * The reason it could not be registered as an ordinary shape: every other
	 * shape is one path repeated once per point, and a set of digits needs the
	 * glyph to differ per position.
	 *
	 * @return void
	 */
	public function test_a_numeric_scale_draws_its_positions() {
		$markup = WP_PostRatings_Template::ratings_images( 0, 5, 3.7, 'number', '' );

		// Once in the track and once in the fill laid over it.
		foreach ( range( 1, 5 ) as $step ) {
			$this->assertSame(
				2,
				substr_count( $markup, '>' . $step . '</i>' ),
				'Position ' . $step . ' is drawn in both the track and the fill.'
			);
		}
	}

	/**
	 * The digits are hidden from assistive technology.
	 *
	 * A label's text is the radio's accessible name. Every other shape adds
	 * nothing to it, because the glyph is an empty element carrying a mask; a
	 * numeric glyph is real text, so left exposed every value announces itself
	 * twice.
	 *
	 * @return void
	 */
	public function test_a_numeric_glyph_is_hidden_from_assistive_technology() {
		$markup = WP_PostRatings_Template::ratings_images_vote( 1, 0, 5, 0, 'number', '', 0, array( '1', '2', '3', '4', '5' ) );

		$this->assertStringContainsString( '<i class="wp-postratings-item" aria-hidden="true">3</i>', $markup, 'The digit is hidden.' );
		$this->assertStringContainsString( '<span>3</span>', $markup, 'And the label beside it still names the value.' );
	}

	/**
	 * A numeric rating is marked by type as well as by name.
	 *
	 * The stylesheet has to reach any numeric shape rather than only the one
	 * this plugin ships, because a site registering its own gets a name of its
	 * own and rules keyed to a name would miss it.
	 *
	 * @return void
	 */
	public function test_a_numeric_rating_is_marked_by_type() {
		add_filter(
			'wp_postratings_shapes',
			static function ( $shapes ) {
				$shapes['points'] = array(
					'type'  => WP_PostRatings_Shapes::NUMERIC,
					'label' => 'Points',
				);
				return $shapes;
			}
		);

		$this->assertTrue( WP_PostRatings_Shapes::is_valid( WP_PostRatings_Shapes::get( 'points' ) ), 'A numeric shape needs no path to be valid.' );

		foreach ( array( 'number', 'points' ) as $name ) {
			$this->assertStringContainsString(
				'wp-postratings-numeric',
				WP_PostRatings_Template::ratings_images( 0, 5, 3.7, $name, '' ),
				$name . ' is not marked as numeric'
			);
		}

		$this->assertStringNotContainsString(
			'wp-postratings-numeric',
			WP_PostRatings_Template::ratings_images( 0, 5, 3.7, 'star', '' ),
			'A mask shape is not marked numeric.'
		);
	}

	// --- the legacy mapping -----------------------------------------------

	/**
	 * All sixteen pre-2.0.0 image sets map onto a real shape.
	 *
	 * @return void
	 */
	public function test_every_legacy_set_maps_to_a_real_shape() {
		$map = WP_PostRatings_Shapes::legacy_map();

		$this->assertCount( 16, $map, 'All sixteen legacy sets are mapped, so no upgrading site loses its shape.' );

		foreach ( $map as $legacy => $shape ) {
			$this->assertNotNull( WP_PostRatings_Shapes::get( $shape ), $legacy . ' maps to nothing' );
		}
	}

	/**
	 * The colour and finish variants collapse onto one shape.
	 *
	 * The sets stars, stars_crystal, stars_dark, stars_png and stars_flat_png
	 * were one star all along; the difference is a CSS custom property.
	 *
	 * @return void
	 */
	public function test_the_finish_variants_collapse() {
		foreach ( array( 'stars', 'stars_crystal', 'stars_dark', 'stars_png', 'stars_flat_png' ) as $legacy ) {
			$this->assertSame( 'star', WP_PostRatings_Shapes::from_legacy( $legacy ), $legacy );
		}

		$this->assertSame( 'plusminus', WP_PostRatings_Shapes::from_legacy( 'plusminus_crystal' ), 'A finish variant collapses onto the shape it drew.' );
		$this->assertSame( 'heart', WP_PostRatings_Shapes::from_legacy( 'heart_crystal' ), 'For every finish, not just the first.' );
	}

	/**
	 * The numbers set keeps its digits.
	 *
	 * It was the one pre-2.0.0 set with no shape to land on, so it was parked
	 * on circles and every site using it silently lost the thing it had chosen.
	 *
	 * @return void
	 */
	public function test_the_numbers_set_keeps_its_digits() {
		$this->assertSame( 'number', WP_PostRatings_Shapes::from_legacy( 'numbers' ), 'The numbers set maps onto the numeric shape.' );
		$this->assertSame( 'number', WP_PostRatings_Template::resolve_shape( 'numbers' ), 'And the resolver the template goes through agrees.' );
	}

	/**
	 * An up/down set stays an up/down shape.
	 *
	 * Mapping thumbs onto a scale would turn a two-way vote into a five point
	 * rating, which is a different feature.
	 *
	 * @return void
	 */
	public function test_up_down_sets_stay_up_down() {
		foreach ( array( 'thumbs', 'plusminus', 'tickcross', 'updown_crystal' ) as $legacy ) {
			$this->assertTrue(
				WP_PostRatings_Shapes::is_updown( WP_PostRatings_Shapes::from_legacy( $legacy ) ),
				$legacy . ' stopped being an up/down control'
			);
		}
	}

	/**
	 * A folder the site added itself falls back rather than rendering nothing.
	 *
	 * @return void
	 */
	public function test_an_unknown_set_falls_back_to_stars() {
		$this->assertSame( 'star', WP_PostRatings_Shapes::from_legacy( 'my_custom_folder' ), 'An unknown legacy set falls back to stars.' );
		$this->assertSame( 'star', WP_PostRatings_Template::resolve_shape( 'my_custom_folder' ), 'And so does the resolver the template goes through.' );
	}

	/**
	 * A shape name resolves to itself.
	 *
	 * @return void
	 */
	public function test_a_shape_name_resolves_to_itself() {
		$this->assertSame( 'heart', WP_PostRatings_Template::resolve_shape( 'heart' ), 'A shape name resolves to itself rather than to the fallback.' );
	}

	// --- registering a shape ----------------------------------------------

	/**
	 * A site can register its own shape.
	 *
	 * Replaces dropping a folder of images into the plugin directory, which
	 * did not survive an update.
	 *
	 * @return void
	 */
	public function test_a_site_can_register_a_shape() {
		add_filter(
			'wp_postratings_shapes',
			static function ( $shapes ) {
				$shapes['diamond'] = array(
					'type'  => WP_PostRatings_Shapes::SCALE,
					'label' => 'Diamonds',
					'path'  => 'M12 2l10 10-10 10L2 12z',
				);
				return $shapes;
			}
		);

		$this->assertContains( 'diamond', WP_PostRatings_Shapes::names(), 'A registered shape is listed.' );
		$this->assertStringContainsString( 'data:image/svg+xml,', WP_PostRatings_Shapes::data_uri( 'diamond' ), 'And renders as a data URI.' );
	}

	/**
	 * A registered shape is immediately selectable and savable.
	 *
	 * The picker and the sanitizer both read the registry, so they cannot
	 * disagree about what exists -- the failure that made the "numbers" set
	 * unsavable before.
	 *
	 * @return void
	 */
	public function test_a_registered_shape_can_be_saved() {
		add_filter(
			'wp_postratings_shapes',
			static function ( $shapes ) {
				$shapes['diamond'] = array(
					'type'  => WP_PostRatings_Shapes::SCALE,
					'label' => 'Diamonds',
					'path'  => 'M12 2l10 10-10 10L2 12z',
				);
				return $shapes;
			}
		);

		$clean = WP_PostRatings_Options::sanitize( array( 'shape' => 'diamond' ) );

		$this->assertSame( 'diamond', $clean['shape'], 'A registered shape can be saved.' );
	}

	/**
	 * A malformed registration is dropped rather than rendering an empty mask.
	 *
	 * @return void
	 */
	public function test_a_malformed_shape_is_dropped() {
		add_filter(
			'wp_postratings_shapes',
			static function ( $shapes ) {
				$shapes['broken'] = array( 'type' => WP_PostRatings_Shapes::SCALE );
				return $shapes;
			}
		);

		$this->assertNotContains( 'broken', WP_PostRatings_Shapes::names(), 'A malformed shape is dropped rather than registered.' );
	}

	// --- colour ------------------------------------------------------------

	/*
	 * The colour used to be chosen by picking an image set: stars_crystal and
	 * stars_dark were the same star in another colour. Collapsing them into CSS
	 * would have left the choice only to people who write CSS, so it survives
	 * as a setting.
	 */

	/**
	 * The built-in colours are hex, and match the stylesheet's own fallbacks.
	 *
	 * There is no site-wide colour setting any more -- a colour per rating says
	 * everything it said. These constants are what an untouched swatch shows and
	 * what an empty rating colour falls back to, so they have to agree with the
	 * var() fallbacks in the stylesheet or the screen lies about the front end.
	 *
	 * @return void
	 */
	public function test_the_built_in_colours_match_the_stylesheet() {
		$this->assertMatchesRegularExpression( '/^#[0-9a-f]{6}$/i', WP_PostRatings_Options::COLOR_RATED, 'The rated colour is a six-digit hex the stylesheet can use.' );
		$this->assertMatchesRegularExpression( '/^#[0-9a-f]{6}$/i', WP_PostRatings_Options::COLOR_UNRATED, 'The unrated colour is a six-digit hex the stylesheet can use.' );

		$css = file_get_contents( dirname( __DIR__ ) . '/css/wp-postratings.css' );

		$this->assertStringContainsString(
			'var( --wp-postratings-color-on, ' . WP_PostRatings_Options::COLOR_RATED . ' )',
			$css,
			'the rated fallback in the stylesheet does not match the constant'
		);
		$this->assertStringContainsString(
			'var( --wp-postratings-color-off, ' . WP_PostRatings_Options::COLOR_UNRATED . ' )',
			$css,
			'the unrated fallback in the stylesheet does not match the constant'
		);

		// The picker's previews pin the same two colours as literals, because an
		// inline custom property on the strip would win over a var() fallback.
		$preview = substr( $css, strpos( $css, '.wp-postratings-shape-preview' ) );
		$preview = substr( $preview, 0, strpos( $preview, '.wp-postratings-shape-row' ) );

		$this->assertStringContainsString( 'color: ' . WP_PostRatings_Options::COLOR_UNRATED . ';', $preview, 'the picker preview unrated colour drifted from the constant' );
		$this->assertStringContainsString( 'color: ' . WP_PostRatings_Options::COLOR_RATED . ';', $preview, 'the picker preview rated colour drifted from the constant' );
	}

	/**
	 * A chosen colour is stored, per rating.
	 *
	 * @return void
	 */
	public function test_a_chosen_colour_is_stored() {
		$clean = WP_PostRatings_Options::sanitize(
			array(
				'ratings' => array(
					'color'     => array( '#e5484d' ),
					'color_off' => array( '#eeeeee' ),
				),
			)
		);

		$this->assertSame( array( '#e5484d' ), $clean['ratings']['color'], 'The voted colour is stored.' );
		$this->assertSame( array( '#eeeeee' ), $clean['ratings']['color_off'], 'And the unvoted one.' );
	}

	/**
	 * Anything that is not a colour leaves the stored value alone.
	 *
	 * The sanitize_hex_color() helper answers null for junk, and writing that
	 * through would blank the property and render every shape invisible.
	 *
	 * @return void
	 */
	public function test_a_junk_colour_keeps_the_stored_value() {
		$this->set_options(
			array(
				'colors' => array(
					'on'  => '#123456',
					'off' => '#abcdef',
				),
			)
		);

		$clean = WP_PostRatings_Options::sanitize(
			array(
				'colors' => array(
					'on'  => 'javascript:alert(1)',
					'off' => '',
				),
			)
		);

		$this->assertSame( '#123456', $clean['colors']['on'], 'A junk colour keeps the stored voted colour.' );
		$this->assertSame( '#abcdef', $clean['colors']['off'], 'And the stored unvoted one.' );
	}

	/**
	 * The colours reach the page from the rating, not from a site-wide row.
	 *
	 * There is no inline stylesheet any more: a colour per rating says
	 * everything the old site-wide pair said, so it is written onto the strip
	 * being rendered and the stylesheet's var() fallbacks cover the rest.
	 *
	 * @return void
	 */
	public function test_the_colours_reach_the_page() {
		$options                     = WP_PostRatings_Options::get();
		$options['ratings']['color'] = array( '', '#e5484d', '', '', '' );
		WP_PostRatings_Options::update( $options );

		$html = WP_PostRatings_Template::ratings_images( 0, 5, 2, 'star', '' );

		$this->assertStringContainsString( '--wp-postratings-color-on:#e5484d', $html, 'the rating colour did not reach the strip' );

		// Nothing is emitted for a step with no colour of its own, so the
		// stylesheet's own fallback stands.
		$html = WP_PostRatings_Template::ratings_images( 0, 5, 3, 'star', '' );

		$this->assertStringNotContainsString( '--wp-postratings-color-on', $html, 'a step with no colour of its own emitted one' );
	}

	/**
	 * Hovering uses the voted colour unless a theme says otherwise.
	 *
	 * Nothing is filled on the voting control yet, so a separate hover shade
	 * would distinguish states that are rarely on screen together.
	 *
	 * @return void
	 */
	public function test_hover_defaults_to_the_voted_colour() {
		$css = file_get_contents( WP_POSTRATINGS_DIR . 'css/wp-postratings.css' );

		$this->assertStringContainsString(
			'var( --wp-postratings-color-hover, var( --wp-postratings-color-on',
			$css,
			'Hover falls back to the voted colour rather than to nothing.'
		);
	}

	/**
	 * The stylesheet declares no defaults block of its own.
	 *
	 * Every property carries its default as a var() fallback instead, which is
	 * what keeps the injected settings from losing a specificity contest.
	 *
	 * @return void
	 */
	public function test_the_stylesheet_has_no_defaults_block() {
		$css = file_get_contents( WP_POSTRATINGS_DIR . 'css/wp-postratings.css' );

		$this->assertDoesNotMatchRegularExpression( '/^:root\s*\{/m', $css, 'The stylesheet ships no :root defaults block; the colours come from the options.' );
	}

	// --- fill --------------------------------------------------------------

	/**
	 * The fill is a true percentage, not a rounded half.
	 *
	 * The GIF sets could only show whole and half images, so 3.7 of 5 was
	 * snapped to 3.5. A clip width can say 74%.
	 *
	 * @return void
	 */
	public function test_the_fill_is_a_true_percentage() {
		$this->assertSame( 74.0, WP_PostRatings_Template::fill_percentage( 3.7, 5 ), 'A fractional rating is a fractional percentage.' );
		$this->assertSame( 80.0, WP_PostRatings_Template::fill_percentage( 4, 5 ), 'A whole one too.' );
		$this->assertSame( 0.0, WP_PostRatings_Template::fill_percentage( 0, 5 ), 'Nothing rated is nothing filled.' );
		$this->assertSame( 100.0, WP_PostRatings_Template::fill_percentage( 5, 5 ), 'And the top of the scale is full.' );
	}

	/**
	 * The fill is clamped and never divides by zero.
	 *
	 * @return void
	 */
	public function test_the_fill_is_clamped() {
		$this->assertSame( 100.0, WP_PostRatings_Template::fill_percentage( 99, 5 ), 'A rating past the scale is clamped to full.' );
		$this->assertSame( 0.0, WP_PostRatings_Template::fill_percentage( -3, 5 ), 'A negative one to empty.' );
		$this->assertSame( 0.0, WP_PostRatings_Template::fill_percentage( 3, 0 ), 'And a scale of zero is not divided by.' );
	}

	/**
	 * The rendered strip carries the fill as a custom property.
	 *
	 * @return void
	 */
	public function test_the_strip_carries_the_fill() {
		$html = WP_PostRatings_Template::ratings_images( 0, 5, 3.7, 'star', 'Alt' );

		$this->assertStringContainsString( '--wp-postratings-fill:74%', $html, 'The strip carries the fill as a custom property.' );
		$this->assertStringContainsString( 'role="img"', $html, 'And is announced as an image.' );
		$this->assertStringContainsString( 'aria-label="Alt"', $html, 'With the label it was given.' );
	}

	/**
	 * The vote control opens showing the score so far, not an empty scale.
	 *
	 * 2.0.0 discarded the rating here, so a post averaging 3.15 of 5 rendered
	 * five unrated shapes beside its own text saying "average: 3.15 out of 5".
	 * The read-only strip had carried the fill all along, which is why the
	 * widget looked right and the control beside the post did not.
	 *
	 * The share is per shape rather than one percentage for the row. A strip laid
	 * over the row has to assume the row's geometry, and a theme putting padding
	 * on a label breaks that assumption without touching anything the strip can
	 * see -- which is exactly what happened when this was tried the other way.
	 *
	 * @return void
	 */
	public function test_the_vote_control_opens_at_the_score_so_far() {
		$html = WP_PostRatings_Template::ratings_images_vote( 1, 0, 5, 3.15, 'star', '', 0, array() );

		// 3.15 of 5: three full, then 15% of the fourth, then nothing.
		$this->assertSame( 3, substr_count( $html, '--wp-postratings-step-fill:100%' ), 'The steps below the score are filled.' );
		$this->assertStringContainsString( '--wp-postratings-step-fill:15%', $html, 'The step the score falls inside carries its share of it.' );
		$this->assertStringContainsString( '--wp-postratings-step-fill:0%', $html, 'And the steps above it carry none.' );
		$this->assertStringContainsString( 'type="radio"', $html, 'The radios are still what does the rating.' );
	}

	/**
	 * A post nobody has rated fills nothing.
	 *
	 * @return void
	 */
	public function test_an_unrated_post_fills_no_step() {
		$html = WP_PostRatings_Template::ratings_images_vote( 1, 0, 5, 0, 'star', '', 0, array() );

		$this->assertSame( 5, substr_count( $html, '--wp-postratings-step-fill:0%' ), 'Every step of an unrated post is empty.' );
		$this->assertStringNotContainsString( '--wp-postratings-step-fill:100%', $html, 'And none of them is filled.' );
	}

	/**
	 * An up/down rating is drawn as the side it actually went.
	 *
	 * @return void
	 */
	public function test_an_up_down_rating_shows_the_side_it_went() {
		$down = WP_PostRatings_Template::ratings_images( 1, 2, -1, 'thumb', 'Alt' );
		$up   = WP_PostRatings_Template::ratings_images( 1, 2, 1, 'thumb', 'Alt' );

		$this->assertStringContainsString( 'shape:var(--wp-postratings-shape-down)', $down, 'A negative rating is a thumbs down.' );
		$this->assertStringNotContainsString( 'shape:var(--wp-postratings-shape-up)', $down, 'And nothing about it points up.' );

		$this->assertStringContainsString( 'shape:var(--wp-postratings-shape-up)', $up, 'A positive rating is a thumbs up.' );
		$this->assertStringNotContainsString( 'shape:var(--wp-postratings-shape-down)', $up, 'And nothing about it points down.' );
	}

	/**
	 * Zero is neither side, so it is drawn as neither.
	 *
	 * An up/down average of zero is a tie -- as many down votes as up -- or a post
	 * nobody has rated. Both were drawn as a thumbs down, because the test asked
	 * whether the rating was above zero and called everything else down.
	 *
	 * @return void
	 */
	public function test_a_tie_takes_neither_side() {
		$html = WP_PostRatings_Template::ratings_images( 1, 2, 0, 'thumb', 'Alt' );

		$this->assertStringContainsString( 'wp-postratings-undecided', $html, 'A tie is drawn as an undecided pair.' );
		$this->assertStringContainsString( 'shape:var(--wp-postratings-shape-up)', $html, 'Which shows the up glyph.' );
		$this->assertStringContainsString( 'shape:var(--wp-postratings-shape-down)', $html, 'And the down glyph beside it.' );
		$this->assertStringNotContainsString( 'wp-postratings-single', $html, 'Rather than picking a side it cannot know.' );
	}

	/**
	 * A scale's score is not a direction, and must not be read as one.
	 *
	 * Switching a site to an up/down pair leaves every post's existing totals in
	 * the meta, where they belong -- but those came off a scale, so they are all
	 * positive. Read as a direction they said "up" for every post that had ever
	 * been rated, and a down vote could not shift it: four votes averaging 3.75
	 * out of five, minus one, is still comfortably positive.
	 *
	 * The tell is arithmetic. Every up/down vote is worth -1 or +1, so a genuine
	 * pair always has abs( score ) <= users, and a scale breaks that as soon as
	 * anybody votes above 1.
	 *
	 * @return void
	 */
	public function test_a_scale_score_is_not_read_as_a_direction() {
		$post_id = $this->make_rated_post( 4, 15 );

		$this->set_options( array( 'shape' => 'thumb' ) );
		$this->set_options( array( 'max' => 2 ) );
		$this->set_options( array( 'customrating' => 1 ) );

		$before = WP_PostRatings::render_ratings( $post_id, true );

		$this->assertStringContainsString( 'wp-postratings-undecided', $before, 'A scale score claims neither direction.' );

		// The one down vote a reader can cast against it does not turn it around,
		// so the point is that it never claimed to have been turned around.
		update_post_meta( $post_id, 'ratings_users', 5 );
		update_post_meta( $post_id, 'ratings_score', 14 );
		update_post_meta( $post_id, 'ratings_average', 2.8 );

		$after = WP_PostRatings::render_ratings( $post_id, true );

		$this->assertStringContainsString( 'wp-postratings-undecided', $after, 'And still claims neither after one.' );
		$this->assertStringContainsString( '5', $after, 'The totals are reported as stored.' );
	}

	/**
	 * Totals a signed pair could have produced are read as a direction.
	 *
	 * The guard above must not swallow the real thing: three votes at -1, -1, +1
	 * is a score of -1 over 3 users, which is within range and is a down vote.
	 *
	 * @return void
	 */
	public function test_a_signed_score_still_reads_as_a_direction() {
		$post_id = $this->make_rated_post( 3, -1 );

		$this->set_options( array( 'shape' => 'thumb' ) );
		$this->set_options( array( 'max' => 2 ) );
		$this->set_options( array( 'customrating' => 1 ) );

		$html = WP_PostRatings::render_ratings( $post_id, true );

		$this->assertStringContainsString( 'shape:var(--wp-postratings-shape-down)', $html, 'Two down votes against one up is a thumbs down.' );
		$this->assertStringNotContainsString( 'wp-postratings-undecided', $html, 'Not an undecided pair.' );
	}

	/**
	 * With no verdict to show, the reader sees their own vote.
	 *
	 * Rating a post down and landing on a tie drew nothing, which looks exactly
	 * like the control that was clicked -- so the vote appeared not to have been
	 * recorded at all. A tie points nowhere, but the reader's own vote does.
	 *
	 * @return void
	 */
	public function test_a_post_with_no_verdict_shows_the_readers_own_vote() {
		$post_id = $this->make_rated_post( 2, 0 );

		$this->set_options( array( 'shape' => 'thumb' ) );
		$this->set_options( array( 'max' => 2 ) );
		$this->set_options( array( 'customrating' => 1 ) );

		$_COOKIE[ 'rated_' . $post_id ] = '-1';

		$html = WP_PostRatings::render_ratings( $post_id, true );

		unset( $_COOKIE[ 'rated_' . $post_id ] );

		$this->assertStringContainsString( 'shape:var(--wp-postratings-shape-down)', $html, 'A tie shows the down vote the reader cast.' );
		$this->assertStringNotContainsString( 'wp-postratings-undecided', $html, 'Rather than the blank pair.' );
		$this->assertStringContainsString( 'You rated this down', $html, 'And says whose vote it is, so the glyph is not read as the verdict.' );
		$this->assertStringContainsString( '2', $html, 'The totals are still reported.' );
	}

	/**
	 * A verdict outranks the reader's own vote.
	 *
	 * Only the vacancy is filled. A post rated up by everybody else must not read
	 * as a thumbs down to the one visitor who disagreed -- that would misrepresent
	 * the post to make one reader feel heard.
	 *
	 * @return void
	 */
	public function test_a_verdict_outranks_the_readers_own_vote() {
		$post_id = $this->make_rated_post( 3, 1 );

		$this->set_options( array( 'shape' => 'thumb' ) );
		$this->set_options( array( 'max' => 2 ) );
		$this->set_options( array( 'customrating' => 1 ) );

		$_COOKIE[ 'rated_' . $post_id ] = '-1';

		$html = WP_PostRatings::render_ratings( $post_id, true );

		unset( $_COOKIE[ 'rated_' . $post_id ] );

		$this->assertStringContainsString( 'shape:var(--wp-postratings-shape-up)', $html, 'Two votes up against one down is a thumbs up.' );
		$this->assertStringNotContainsString( 'You rated this', $html, 'And the label describes the verdict, not the reader.' );
	}

	/**
	 * Nothing knows how the reader voted, so nothing is claimed.
	 *
	 * @return void
	 */
	public function test_a_tie_stays_blank_for_a_reader_who_has_not_voted() {
		$post_id = $this->make_rated_post( 2, 0 );

		$this->set_options( array( 'shape' => 'thumb' ) );
		$this->set_options( array( 'max' => 2 ) );
		$this->set_options( array( 'customrating' => 1 ) );

		$html = WP_PostRatings::render_ratings( $post_id, true );

		$this->assertStringContainsString( 'wp-postratings-undecided', $html, 'A tie nobody here voted in draws the blank pair.' );
		$this->assertStringNotContainsString( 'You rated this', $html, 'And claims nothing about the reader.' );
	}

	/**
	 * The reply to a vote reports the vote that was just cast.
	 *
	 * Goes through process_vote() rather than seeding $_COOKIE, because the seeded
	 * form is exactly what this cannot prove. setcookie() speaks only to the next
	 * request, so the reply rendered further down the request that casts a vote
	 * cannot read the cookie it has just set -- and that reply is the one thing the
	 * voter sees. record() therefore fills $_COOKIE in as well, and a test that
	 * sets the cookie itself passes whether that line exists or not.
	 *
	 * @return void
	 */
	public function test_the_reply_to_a_vote_reports_the_vote_just_cast() {
		// One up vote already, so the down vote below lands on a tie: the case
		// where the post itself has no direction left to show.
		$post_id = $this->make_rated_post( 1, 1 );

		$this->set_options( array( 'shape' => 'thumb' ) );
		$this->set_options( array( 'max' => 2 ) );
		$this->set_options( array( 'customrating' => 1 ) );
		$this->set_options( array( 'allowtorate' => 2 ) );
		$this->set_options( array( 'check_method' => 1 ) );
		$this->set_options(
			array(
				'ratings' => array(
					'value' => array( -1, 1 ),
					'text'  => array( 'Vote Down', 'Vote Up' ),
				),
			)
		);

		unset( $_COOKIE[ 'rated_' . $post_id ] );

		$reply = WP_PostRatings_Rating::process_vote( $post_id, 1 );

		$this->assertSame( 2, (int) get_post_meta( $post_id, 'ratings_users', true ), 'The vote counted.' );
		$this->assertSame( 0, (int) get_post_meta( $post_id, 'ratings_score', true ), 'And left the post tied.' );

		$this->assertStringContainsString( 'shape:var(--wp-postratings-shape-down)', $reply, 'The reply shows the down vote that was cast.' );
		$this->assertStringContainsString( 'You rated this down', $reply, 'And says it is the voter own vote.' );
		$this->assertStringNotContainsString( 'wp-postratings-undecided', $reply, 'Not the blank pair the tie alone would draw.' );
	}

	/**
	 * An address never stands in for the reader.
	 *
	 * Matching a repeat vote on an address is over-strict when it is wrong, which
	 * is why has_rated() may do it. Being wrong here instead tells a visitor who
	 * has never voted "You rated this down" -- a false statement about them. And
	 * behind a proxy or a mobile network with no forwarded-for header configured,
	 * every visitor shares one address, so one person's vote answers for all of
	 * them. This was written with an IP lookup first and did exactly that on a
	 * live site.
	 *
	 * @return void
	 */
	public function test_an_address_is_never_taken_for_the_readers_own_vote() {
		$post_id = $this->make_rated_post( 2, 0 );

		$this->set_options( array( 'shape' => 'thumb' ) );
		$this->set_options( array( 'max' => 2 ) );
		$this->set_options( array( 'customrating' => 1 ) );
		$this->set_options( array( 'check_method' => 3 ) );

		// Somebody else voted down from the address this request also comes from.
		$this->log_rating( $post_id, -1, 'Somebody Else', '203.0.113.1' );

		$_SERVER['REMOTE_ADDR'] = '203.0.113.1';

		$this->assertNull( WP_PostRatings_Rating::own_vote( $post_id ), 'A shared address is not this reader.' );

		$html = WP_PostRatings::render_ratings( $post_id, true );

		$this->assertStringNotContainsString( 'You rated this', $html, 'So nothing is claimed about them.' );
		$this->assertStringContainsString( 'wp-postratings-undecided', $html, 'And the tie stays blank.' );
	}

	/**
	 * No shipped markup references an image file.
	 *
	 * @return void
	 */
	public function test_the_markup_carries_no_images() {
		$html  = WP_PostRatings_Template::ratings_images( 0, 5, 3, 'star', 'Alt' );
		$html .= WP_PostRatings_Template::ratings_images_vote( 1, 0, 5, 0, 'star', '', 0, array() );

		$this->assertStringNotContainsString( '<img', $html, 'The markup has no image element.' );
		$this->assertStringNotContainsString( '.gif', $html, 'No GIF.' );
		$this->assertStringNotContainsString( '/images/', $html, 'And nothing under the images directory the plugin no longer ships.' );
	}
}
