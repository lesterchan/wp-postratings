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

		$this->assertSame( 'plusminus', WP_PostRatings_Shapes::from_legacy( 'plusminus_crystal' ) );
		$this->assertSame( 'heart', WP_PostRatings_Shapes::from_legacy( 'heart_crystal' ) );
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
		$this->assertSame( 'star', WP_PostRatings_Shapes::from_legacy( 'my_custom_folder' ) );
		$this->assertSame( 'star', WP_PostRatings_Template::resolve_shape( 'my_custom_folder' ) );
	}

	/**
	 * A shape name resolves to itself.
	 *
	 * @return void
	 */
	public function test_a_shape_name_resolves_to_itself() {
		$this->assertSame( 'heart', WP_PostRatings_Template::resolve_shape( 'heart' ) );
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

		$this->assertContains( 'diamond', WP_PostRatings_Shapes::names() );
		$this->assertStringContainsString( 'data:image/svg+xml,', WP_PostRatings_Shapes::data_uri( 'diamond' ) );
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

		$this->assertSame( 'diamond', $clean['shape'] );
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

		$this->assertNotContains( 'broken', WP_PostRatings_Shapes::names() );
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

		$this->assertSame( array( '#e5484d' ), $clean['ratings']['color'] );
		$this->assertSame( array( '#eeeeee' ), $clean['ratings']['color_off'] );
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

		$clean = WP_PostRatings_Options::sanitize(
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
		$this->assertSame( 74.0, WP_PostRatings_Template::fill_percentage( 3.7, 5 ) );
		$this->assertSame( 80.0, WP_PostRatings_Template::fill_percentage( 4, 5 ) );
		$this->assertSame( 0.0, WP_PostRatings_Template::fill_percentage( 0, 5 ) );
		$this->assertSame( 100.0, WP_PostRatings_Template::fill_percentage( 5, 5 ) );
	}

	/**
	 * The fill is clamped and never divides by zero.
	 *
	 * @return void
	 */
	public function test_the_fill_is_clamped() {
		$this->assertSame( 100.0, WP_PostRatings_Template::fill_percentage( 99, 5 ) );
		$this->assertSame( 0.0, WP_PostRatings_Template::fill_percentage( -3, 5 ) );
		$this->assertSame( 0.0, WP_PostRatings_Template::fill_percentage( 3, 0 ) );
	}

	/**
	 * The rendered strip carries the fill as a custom property.
	 *
	 * @return void
	 */
	public function test_the_strip_carries_the_fill() {
		$html = WP_PostRatings_Template::ratings_images( 0, 5, 3.7, 'star', 'Alt' );

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
		$html  = WP_PostRatings_Template::ratings_images( 0, 5, 3, 'star', 'Alt' );
		$html .= WP_PostRatings_Template::ratings_images_vote( 1, 0, 5, 0, 'star', '', 0, array() );

		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( '.gif', $html );
		$this->assertStringNotContainsString( '/images/', $html );
	}
}
