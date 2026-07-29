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
 * @covers Postratings_Shapes
 * @covers Postratings_Template::resolve_shape
 * @covers Postratings_Template::fill_percentage
 */
class Test_Postratings_Shapes extends WP_PostRatings_TestCase {

	/**
	 * Both interaction families ship.
	 *
	 * A scale is one value out of N; an up/down is a pair of opposing actions.
	 * They are different controls, so both have to exist.
	 *
	 * @return void
	 */
	public function test_both_families_ship() {
		$shapes = Postratings_Shapes::all();

		$scale  = array_filter( $shapes, static fn( $s ) => Postratings_Shapes::SCALE === $s['type'] );
		$updown = array_filter( $shapes, static fn( $s ) => Postratings_Shapes::UPDOWN === $s['type'] );

		$this->assertNotEmpty( $scale );
		$this->assertNotEmpty( $updown );
	}

	/**
	 * Every shape is well formed.
	 *
	 * @return void
	 */
	public function test_every_shape_is_valid() {
		foreach ( Postratings_Shapes::all() as $name => $shape ) {
			$this->assertTrue( Postratings_Shapes::is_valid( $shape ), $name . ' is malformed' );
			$this->assertNotEmpty( $shape['label'], $name . ' has no label' );
		}
	}

	/**
	 * Every shape produces a usable mask.
	 *
	 * @return void
	 */
	public function test_every_shape_produces_a_data_uri() {
		foreach ( Postratings_Shapes::names() as $name ) {
			$variants = Postratings_Shapes::is_updown( $name ) ? array( 'up', 'down' ) : array( '' );

			foreach ( $variants as $variant ) {
				$uri = Postratings_Shapes::data_uri( $name, $variant );

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
		foreach ( Postratings_Shapes::names() as $name ) {
			$this->assertDoesNotMatchRegularExpression(
				'/[\'"]/',
				Postratings_Shapes::data_uri( $name ),
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
		foreach ( Postratings_Shapes::names() as $name ) {
			if ( ! Postratings_Shapes::is_updown( $name ) ) {
				continue;
			}

			$this->assertNotSame(
				Postratings_Shapes::data_uri( $name, 'up' ),
				Postratings_Shapes::data_uri( $name, 'down' ),
				$name . ' uses one glyph for both directions'
			);
		}
	}

	// --- the legacy mapping -----------------------------------------------

	/**
	 * All sixteen pre-2.0.0 image sets map onto a real shape.
	 *
	 * @return void
	 */
	public function test_every_legacy_set_maps_to_a_real_shape() {
		$map = Postratings_Shapes::legacy_map();

		$this->assertCount( 16, $map );

		foreach ( $map as $legacy => $shape ) {
			$this->assertNotNull( Postratings_Shapes::get( $shape ), $legacy . ' maps to nothing' );
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
			$this->assertSame( 'star', Postratings_Shapes::from_legacy( $legacy ), $legacy );
		}

		$this->assertSame( 'plusminus', Postratings_Shapes::from_legacy( 'plusminus_crystal' ) );
		$this->assertSame( 'heart', Postratings_Shapes::from_legacy( 'heart_crystal' ) );
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
				Postratings_Shapes::is_updown( Postratings_Shapes::from_legacy( $legacy ) ),
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
		$this->assertSame( 'star', Postratings_Shapes::from_legacy( 'my_custom_folder' ) );
		$this->assertSame( 'star', Postratings_Template::resolve_shape( 'my_custom_folder' ) );
	}

	/**
	 * A shape name resolves to itself.
	 *
	 * @return void
	 */
	public function test_a_shape_name_resolves_to_itself() {
		$this->assertSame( 'heart', Postratings_Template::resolve_shape( 'heart' ) );
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
					'type'  => Postratings_Shapes::SCALE,
					'label' => 'Diamonds',
					'path'  => 'M12 2l10 10-10 10L2 12z',
				);
				return $shapes;
			}
		);

		$this->assertContains( 'diamond', Postratings_Shapes::names() );
		$this->assertStringContainsString( 'data:image/svg+xml,', Postratings_Shapes::data_uri( 'diamond' ) );
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
					'type'  => Postratings_Shapes::SCALE,
					'label' => 'Diamonds',
					'path'  => 'M12 2l10 10-10 10L2 12z',
				);
				return $shapes;
			}
		);

		$clean = Postratings_Options::sanitize( array( 'image' => 'diamond' ) );

		$this->assertSame( 'diamond', $clean['image'] );
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
				$shapes['broken'] = array( 'type' => Postratings_Shapes::SCALE );
				return $shapes;
			}
		);

		$this->assertNotContains( 'broken', Postratings_Shapes::names() );
	}

	// --- colour ------------------------------------------------------------

	/*
	 * The colour used to be chosen by picking an image set: stars_crystal and
	 * stars_dark were the same star in another colour. Collapsing them into CSS
	 * would have left the choice only to people who write CSS, so it survives
	 * as a setting.
	 */

	/**
	 * The colours ship as a setting, with sensible defaults.
	 *
	 * @return void
	 */
	public function test_the_colours_are_a_setting() {
		$colors = Postratings_Options::get( 'colors' );

		$this->assertMatchesRegularExpression( '/^#[0-9a-f]{3,6}$/i', $colors['on'] );
		$this->assertMatchesRegularExpression( '/^#[0-9a-f]{3,6}$/i', $colors['off'] );
	}

	/**
	 * A chosen colour is stored.
	 *
	 * @return void
	 */
	public function test_a_chosen_colour_is_stored() {
		$clean = Postratings_Options::sanitize(
			array(
				'colors' => array(
					'on'  => '#e5484d',
					'off' => '#eeeeee',
				),
			)
		);

		$this->assertSame( '#e5484d', $clean['colors']['on'] );
		$this->assertSame( '#eeeeee', $clean['colors']['off'] );
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
		$this->set_option(
			'colors',
			array(
				'on'  => '#123456',
				'off' => '#abcdef',
			)
		);

		$clean = Postratings_Options::sanitize(
			array(
				'colors' => array(
					'on'  => 'javascript:alert(1)',
					'off' => '',
				),
			)
		);

		$this->assertSame( '#123456', $clean['colors']['on'] );
		$this->assertSame( '#abcdef', $clean['colors']['off'] );
	}

	/**
	 * The chosen colours reach the page as custom properties.
	 *
	 * Emitted once as inline CSS rather than on every element, so a page with
	 * fifty ratings carries one declaration rather than fifty.
	 *
	 * @return void
	 */
	public function test_the_colours_reach_the_page() {
		$this->set_option(
			'colors',
			array(
				'on'  => '#e5484d',
				'off' => '#eeeeee',
			)
		);

		$css = Postratings::color_css();

		$this->assertStringContainsString( '--wp-postratings-color-on: #e5484d', $css );
		$this->assertStringContainsString( '--wp-postratings-color-off: #eeeeee', $css );

		// Scoped to the wrapper, as wp-polls does, not to :root.
		$this->assertStringContainsString( '.post-ratings {', $css );
		$this->assertStringNotContainsString( ':root', $css );
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
			$css
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

		$this->assertDoesNotMatchRegularExpression( '/^:root\s*\{/m', $css );
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
		$this->assertSame( 74.0, Postratings_Template::fill_percentage( 3.7, 5 ) );
		$this->assertSame( 80.0, Postratings_Template::fill_percentage( 4, 5 ) );
		$this->assertSame( 0.0, Postratings_Template::fill_percentage( 0, 5 ) );
		$this->assertSame( 100.0, Postratings_Template::fill_percentage( 5, 5 ) );
	}

	/**
	 * The fill is clamped and never divides by zero.
	 *
	 * @return void
	 */
	public function test_the_fill_is_clamped() {
		$this->assertSame( 100.0, Postratings_Template::fill_percentage( 99, 5 ) );
		$this->assertSame( 0.0, Postratings_Template::fill_percentage( -3, 5 ) );
		$this->assertSame( 0.0, Postratings_Template::fill_percentage( 3, 0 ) );
	}

	/**
	 * The rendered strip carries the fill as a custom property.
	 *
	 * @return void
	 */
	public function test_the_strip_carries_the_fill() {
		$html = Postratings_Template::ratings_images( 0, 5, 3.7, 'star', 'Alt' );

		$this->assertStringContainsString( '--wp-postratings-fill:74%', $html );
		$this->assertStringContainsString( 'role="img"', $html );
		$this->assertStringContainsString( 'aria-label="Alt"', $html );
	}

	/**
	 * No shipped markup references an image file.
	 *
	 * @return void
	 */
	public function test_the_markup_carries_no_images() {
		$html  = Postratings_Template::ratings_images( 0, 5, 3, 'star', 'Alt' );
		$html .= Postratings_Template::ratings_images_vote( 1, 0, 5, 0, 'star', '', 0, array() );

		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( '.gif', $html );
		$this->assertStringNotContainsString( '/images/', $html );
	}
}
