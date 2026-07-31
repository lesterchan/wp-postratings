<?php
/**
 * Tests for the consolidated option row and its migration.
 *
 * @package WP-PostRatings
 */

/**
 * Reading, sanitizing and migrating the settings.
 *
 * @covers WP_PostRatings_Options
 */
class WP_PostRatings_Options_Test extends WP_PostRatings_TestCase {

	/**
	 * The settings row and the marker row both take the plugin prefix.
	 *
	 * @return void
	 */
	public function test_the_option_rows_are_prefixed_with_the_slug() {
		$this->assertSame( 'wp_postratings_options', WP_PostRatings_Options::OPTION );
		$this->assertSame( 'wp_postratings_version', WP_PostRatings_Options::VERSION );
	}

	/**
	 * A missing key falls back to the default rather than warning.
	 *
	 * @return void
	 */
	public function test_missing_keys_fall_back_to_defaults() {
		update_option( WP_PostRatings_Options::OPTION, array( 'max' => 7 ) );

		$options = WP_PostRatings_Options::get();

		$this->assertSame( 7, $options['max'] );
		$this->assertSame( 'star', $options['shape'] );
		$this->assertArrayHasKey( 'vote', $options['templates'] );
	}

	/**
	 * A stored group missing a key does not shadow the whole default group.
	 *
	 * @return void
	 */
	public function test_a_partial_group_merges_rather_than_replaces() {
		update_option(
			WP_PostRatings_Options::OPTION,
			array( 'templates' => array( 'vote' => 'CUSTOM' ) )
		);

		$options = WP_PostRatings_Options::get();

		$this->assertSame( 'CUSTOM', $options['templates']['vote'] );
		$this->assertStringContainsString( '%RATINGS_IMAGES%', $options['templates']['text'] );
	}

	// --- migration --------------------------------------------------------

	/**
	 * A customised 1.x install carries its values across.
	 *
	 * @return void
	 */
	public function test_the_migration_carries_customised_values() {
		$this->build_legacy_install();

		WP_PostRatings_Options::maybe_migrate();

		$options = WP_PostRatings_Options::get();

		$this->assertSame( 'thumb', $options['shape'], 'the up/down set did not map to its shape' );
		$this->assertSame( '2', $options['max'] );
		$this->assertSame( '4', $options['check_method'] );
		$this->assertSame( array( 'Vote Down', 'Vote Up' ), $options['ratings']['text'] );
		$this->assertSame( array( -1, 1 ), $options['ratings']['value'] );
	}

	/**
	 * The row the migration writes into is not one of the rows it deletes.
	 *
	 * Deleting it would throw away every setting just merged, which is the
	 * trap in reusing an existing option name.
	 *
	 * @return void
	 */
	public function test_the_reused_row_survives_the_migration() {
		$this->build_legacy_install();

		update_option(
			WP_PostRatings_Options::OPTION,
			array( 'ip_header' => 'HTTP_CF_CONNECTING_IP' )
		);

		WP_PostRatings_Options::maybe_migrate();

		$this->assertSame( 'HTTP_CF_CONNECTING_IP', WP_PostRatings_Options::get( 'ip_header' ) );
		$this->assertNotContains( WP_PostRatings_Options::OPTION, $this->legacy_names() );
	}

	/**
	 * Every legacy row is removed once merged.
	 *
	 * @return void
	 */
	public function test_the_legacy_rows_are_deleted() {
		$this->build_legacy_install();

		WP_PostRatings_Options::maybe_migrate();

		foreach ( $this->legacy_names() as $name ) {
			$this->assertFalse( get_option( $name, false ), $name . ' survived the migration' );
		}
	}

	/**
	 * Templates stored slashed are normalised exactly once.
	 *
	 * @return void
	 */
	public function test_slashed_templates_are_normalised() {
		$this->build_legacy_install();
		update_option( 'postratings_template_vote', "O\\'Brien\\'s %RATINGS_IMAGES_VOTE%" );

		WP_PostRatings_Options::maybe_migrate();

		$this->assertSame( "O'Brien's %RATINGS_IMAGES_VOTE%", WP_PostRatings_Options::template( 'vote' ) );
	}

	/**
	 * Running the migration twice is a no-op.
	 *
	 * Gated on the stored version, not on "do the old keys exist" -- an install
	 * that has already migrated has none, and treating that as fresh would
	 * write defaults straight over the merged row.
	 *
	 * @return void
	 */
	public function test_the_migration_is_idempotent() {
		$this->build_legacy_install();

		WP_PostRatings_Options::maybe_migrate();
		$first = WP_PostRatings_Options::get();

		WP_PostRatings_Options::maybe_migrate();
		$second = WP_PostRatings_Options::get();

		$this->assertSame( $first, $second );
		$this->assertSame( 'thumb', $second['shape'], 'the second run reverted to defaults' );
	}

	/**
	 * Both upgrade markers are recorded, in one row, once the upgrade finishes.
	 *
	 * @return void
	 */
	public function test_the_version_markers_are_recorded() {
		$this->build_legacy_install();
		delete_option( WP_PostRatings_Options::VERSION );

		WP_PostRatings_Install::maybe_upgrade();

		$this->assertSame(
			array(
				'plugin' => WP_POSTRATINGS_VERSION,
				'db'     => WP_POSTRATINGS_DB_VERSION,
			),
			get_option( WP_PostRatings_Options::VERSION ),
			'the marker row should hold exactly the two markers'
		);
	}

	// --- sanitizing -------------------------------------------------------

	/**
	 * A tab that posts nothing for a setting leaves it alone.
	 *
	 * The settings screen is split in two, so a sanitizer that rebuilt the
	 * whole array from the submission would blank the other tab on every save.
	 *
	 * @return void
	 */
	public function test_absent_keys_keep_their_stored_value() {
		$this->set_option( 'max', 9 );

		$clean = WP_PostRatings_Options::sanitize( array( 'templates' => array( 'vote' => 'X' ) ) );

		$this->assertSame( 9, $clean['max'] );
		$this->assertSame( 'X', $clean['templates']['vote'] );
	}

	/**
	 * An image set the sanitizer would reject is never offered by the screen.
	 *
	 * The two used to be computed separately, so the UI could offer a style
	 * that saved, reported success and silently reverted.
	 *
	 * @return void
	 */
	public function test_the_image_allow_list_matches_what_the_screen_offers() {
		$shapes = WP_PostRatings_Shapes::names();

		$this->assertContains( 'star', $shapes );

		foreach ( $shapes as $shape ) {
			$clean = WP_PostRatings_Options::sanitize( array( 'shape' => $shape ) );
			$this->assertSame( $shape, $clean['shape'], $shape . ' is offered but rejected' );
		}

		$clean = WP_PostRatings_Options::sanitize( array( 'shape' => '../../evil' ) );
		$this->assertNotSame( '../../evil', $clean['shape'] );
	}

	/**
	 * A pre-2.0.0 image set name still saves, and lands on its shape.
	 *
	 * An install that has not migrated yet posts the old value back from its
	 * own settings screen.
	 *
	 * @return void
	 */
	public function test_a_legacy_set_name_is_accepted_and_mapped() {
		$clean = WP_PostRatings_Options::sanitize( array( 'shape' => 'stars_crystal' ) );

		$this->assertSame( 'star', $clean['shape'] );
	}

	/**
	 * Out of range choices fall back rather than being stored.
	 *
	 * @return void
	 */
	public function test_enumerated_settings_are_constrained() {
		$clean = WP_PostRatings_Options::sanitize(
			array(
				'allowtorate'  => 99,
				'check_method' => 99,
				'max'          => -5,
			)
		);

		$this->assertSame( 2, $clean['allowtorate'] );
		$this->assertSame( 3, $clean['check_method'] );
		$this->assertSame( WP_PostRatings_Options::MIN_SCALE, $clean['max'], 'a negative scale was not floored at the minimum' );
	}

	/**
	 * Markup in a template is filtered but not stripped wholesale.
	 *
	 * @return void
	 */
	public function test_templates_are_kses_filtered() {
		$clean = WP_PostRatings_Options::sanitize(
			array( 'templates' => array( 'vote' => '<strong>keep</strong><script>alert(1)</script>' ) )
		);

		$this->assertStringContainsString( '<strong>keep</strong>', $clean['templates']['vote'] );
		$this->assertStringNotContainsString( '<script>', $clean['templates']['vote'] );
	}

	/**
	 * The rows the 2.0.0 migration consolidates.
	 *
	 * @return array
	 */
	private function legacy_names() {
		return array(
			'postratings_image',
			'postratings_max',
			'postratings_customrating',
			'postratings_allowtorate',
			'postratings_logging_method',
			'postratings_ajax_style',
			'postratings_ratingstext',
			'postratings_ratingsvalue',
			'postratings_template_vote',
			'postratings_template_text',
			'postratings_template_permission',
			'postratings_template_none',
			'postratings_template_highestrated',
			'postratings_template_mostrated',
		);
	}

	/**
	 * Rebuild a customised pre-2.0.0 install.
	 *
	 * @return void
	 */
	private function build_legacy_install() {
		delete_option( WP_PostRatings_Options::OPTION );
		delete_option( WP_PostRatings_Options::VERSION );

		update_option( 'postratings_image', 'thumbs' );
		update_option( 'postratings_max', '2' );
		update_option( 'postratings_customrating', '1' );
		update_option( 'postratings_allowtorate', '1' );
		update_option( 'postratings_logging_method', '4' );
		update_option(
			'postratings_ajax_style',
			array(
				'loading' => 0,
				'fading'  => 0,
			)
		);
		update_option( 'postratings_ratingstext', array( 'Vote Down', 'Vote Up' ) );
		update_option( 'postratings_ratingsvalue', array( -1, 1 ) );
		update_option( 'postratings_template_vote', 'V %RATINGS_IMAGES_VOTE%' );
		update_option( 'postratings_template_text', 'T %RATINGS_SCORE%' );
		update_option( 'postratings_template_permission', 'P' );
		update_option( 'postratings_template_none', 'N' );
		update_option( 'postratings_template_highestrated', '<li>H</li>' );
		update_option( 'postratings_template_mostrated', '<li>M</li>' );
	}
	/**
	 * A scale larger than the cap is brought back to it, not stored as given.
	 *
	 * Every point on the scale is a glyph a visitor has to aim at and a column in
	 * the rating text table, so fifty is a misconfiguration rather than a
	 * preference. The only bound used to be a floor of one.
	 */
	public function test_the_scale_is_capped() {
		$clean = WP_PostRatings_Options::sanitize( array( 'max' => 50 ) );

		$this->assertSame( 10, $clean['max'], 'a scale of fifty was stored as given' );

		$clean = WP_PostRatings_Options::sanitize( array( 'max' => 0 ) );

		// Three, not one: one or two points is not a scale. Two opposing actions
		// is an up/down rating, which is a type of its own.
		$this->assertSame( WP_PostRatings_Options::MIN_SCALE, $clean['max'], 'a scale of zero was not floored at the minimum' );
	}

	/**
	 * A site that wants a longer scale says so through the filter.
	 */
	public function test_the_cap_is_filterable() {
		add_filter(
			'wp_postratings_max_scale',
			static function () {
				return 20;
			}
		);

		$this->assertSame( 20, WP_PostRatings_Options::max_scale(), 'the filter did not move the cap' );
		$this->assertSame( 15, WP_PostRatings_Options::sanitize( array( 'max' => 15 ) )['max'], 'the filtered cap was not honoured' );
	}
	/**
	 * Ratings in Google results are off until a site picks a type.
	 */
	public function test_the_schema_type_is_off_by_default() {
		$this->assertSame( '', WP_PostRatings_Options::defaults()['schema_type'], 'structured data was on by default' );
	}

	/**
	 * Only the types Google actually shows a rating for are accepted.
	 *
	 * Article, BlogPosting and NewsArticle are not among them, which is why the
	 * old markup produced no rich result on any post.
	 */
	public function test_only_supported_schema_types_are_accepted() {
		foreach ( array_keys( WP_PostRatings_Options::schema_types() ) as $type ) {
			$this->assertSame( $type, WP_PostRatings_Options::sanitize( array( 'schema_type' => $type ) )['schema_type'], $type . ' is offered but would not save' );
		}

		foreach ( array( 'Article', 'BlogPosting', 'NewsArticle', 'Nonsense' ) as $type ) {
			$this->assertSame( '', WP_PostRatings_Options::sanitize( array( 'schema_type' => $type ) )['schema_type'], $type . ' was accepted and Google shows no rating for it' );
		}
	}
	/**
	 * A rating can carry its own colour, and empty means "use the rated colour".
	 *
	 * A two step scale is the case this exists for: an up vote and a down vote
	 * are opposite actions and want opposite colours, which one shared setting
	 * cannot express.
	 */
	public function test_a_rating_can_carry_its_own_colour() {
		$clean = WP_PostRatings_Options::sanitize(
			array(
				'ratings' => array(
					'color' => array( '#00ff00', '#ff0000', 'not a colour', '' ),
				),
			)
		);

		$this->assertSame( array( '#00ff00', '#ff0000', '', '' ), $clean['ratings']['color'], 'the per-rating colours were not cleaned' );
	}

	/**
	 * An empty per-rating colour is accepted and means "use the built-in one".
	 *
	 * A colour input cannot be empty, so in the screen every swatch is filled --
	 * but a step that has never been rendered has no value at all, and that has
	 * to survive the sanitiser rather than becoming #000000.
	 */
	public function test_an_empty_rating_colour_survives() {
		$clean = WP_PostRatings_Options::sanitize(
			array(
				'ratings' => array(
					'color'     => array( '#00ff00', '' ),
					'color_off' => array( '', '#eeeeee' ),
				),
			)
		);

		$this->assertSame( array( '#00ff00', '' ), $clean['ratings']['color'], 'an empty rated colour was not kept empty' );
		$this->assertSame( array( '', '#eeeeee' ), $clean['ratings']['color_off'], 'an empty unrated colour was not kept empty' );
	}

	/**
	 * The strip carries the colour of the step being shown, and nothing when
	 * that step has none.
	 */
	public function test_the_strip_carries_the_step_colour() {
		$options                     = WP_PostRatings_Options::get();
		$options['ratings']['color'] = array( '#00ff00', '#ff0000', '', '', '' );
		WP_PostRatings_Options::update( $options );

		$this->assertSame( '--wp-postratings-color-on:#ff0000', WP_PostRatings_Template::rating_color_style( 2 ), 'step 2 did not get its own colour' );
		$this->assertSame( '--wp-postratings-color-on:#ff0000', WP_PostRatings_Template::rating_color_style( 1.8 ), 'a fractional rating did not round to its step' );
		$this->assertSame( '', WP_PostRatings_Template::rating_color_style( 3 ), 'a step with no colour of its own emitted one' );
		$this->assertSame( '', WP_PostRatings_Template::rating_color_style( 0 ), 'an unrated post emitted a colour' );
	}

	/**
	 * The unrated colour reaches the page too.
	 *
	 * It was stored and offered on the screen but never rendered, so setting it
	 * did nothing and the unrated glyphs stayed the built-in grey.
	 */
	public function test_the_unrated_colour_reaches_the_strip() {
		$options                         = WP_PostRatings_Options::get();
		$options['ratings']['color']     = array( '', '#ff0000', '', '', '' );
		$options['ratings']['color_off'] = array( '', '#eeeeee', '', '', '' );
		WP_PostRatings_Options::update( $options );

		$this->assertSame(
			'--wp-postratings-color-on:#ff0000;--wp-postratings-color-off:#eeeeee',
			WP_PostRatings_Template::rating_color_style( 2 ),
			'the unrated colour did not reach the strip'
		);

		// A step that sets only one gets only that one.
		$options['ratings']['color']     = array( '', '', '', '', '' );
		$options['ratings']['color_off'] = array( '', '#eeeeee', '', '', '' );
		WP_PostRatings_Options::update( $options );

		$this->assertSame(
			'--wp-postratings-color-off:#eeeeee',
			WP_PostRatings_Template::rating_color_style( 2 ),
			'a step with only an unrated colour emitted the rated one too'
		);
	}
	/**
	 * A site-wide colour is carried onto every rating rather than dropped.
	 *
	 * This is the data-loss case. The one site-wide pair became a pair per
	 * rating, and a site that had chosen its own colours has to look identical
	 * afterwards -- leaving the steps empty would mean "use the built-in
	 * colour", silently discarding the choice.
	 */
	public function test_a_site_wide_colour_is_carried_onto_every_rating() {
		delete_option( WP_PostRatings_Options::OPTION );
		delete_option( WP_PostRatings_Options::VERSION );

		update_option(
			WP_PostRatings_Options::OPTION,
			array(
				'max'    => 3,
				'colors' => array(
					'on'  => '#e5484d',
					'off' => '#eeeeee',
				),
			)
		);

		WP_PostRatings_Options::maybe_migrate();

		$ratings = WP_PostRatings_Options::get( 'ratings' );

		$this->assertSame( array( '#e5484d', '#e5484d', '#e5484d' ), $ratings['color'], 'the chosen rated colour was lost' );
		$this->assertSame( array( '#eeeeee', '#eeeeee', '#eeeeee' ), $ratings['color_off'], 'the chosen unrated colour was lost' );
		$this->assertArrayNotHasKey( 'colors', WP_PostRatings_Options::get(), 'the retired site-wide row survived' );
	}
	/**
	 * An up/down pair can be two different colours at once.
	 *
	 * The case this whole column exists for. The read-only strip picks its
	 * colour from the post's current rating, which is right when one glyph is on
	 * screen -- but a vote control shows every step together, so colouring it
	 * that way could only ever paint both the same.
	 */
	public function test_an_up_down_control_carries_a_colour_per_step() {
		$options          = WP_PostRatings_Options::get();
		$options['shape'] = 'thumb';
		$options['max']   = 2;
		// Down is red and up is green, which is the convention the built-in
		// colours already follow.
		$options['ratings']['color']     = array( '#ff0000', '#00ff00' );
		$options['ratings']['color_off'] = array( '', '' );
		WP_PostRatings_Options::update( $options );

		$html = WP_PostRatings_Template::ratings_images_vote( 1, 0, 2, 0, 'thumb', '', 0, array( 'Down', 'Up' ) );

		// Step 1 is the down vote and step 2 the up vote.
		$this->assertMatchesRegularExpression(
			'/class="wp-postratings-down"[^>]*style="[^"]*--wp-postratings-color-on:#ff0000/',
			$html,
			'the down vote did not take step 1\'s colour'
		);
		$this->assertMatchesRegularExpression(
			'/class="wp-postratings-up"[^>]*style="[^"]*--wp-postratings-color-on:#00ff00/',
			$html,
			'the up vote did not take step 2\'s colour'
		);
	}
	/**
	 * The built-in up and down colours match the stylesheet, and go the right way.
	 */
	public function test_the_built_in_up_and_down_colours_match_the_stylesheet() {
		$css = file_get_contents( dirname( __DIR__ ) . '/css/wp-postratings.css' );

		$this->assertStringContainsString(
			'var( --wp-postratings-color-up, ' . WP_PostRatings_Options::COLOR_UP . ' )',
			$css,
			'the up fallback in the stylesheet does not match the constant'
		);
		$this->assertStringContainsString(
			'var( --wp-postratings-color-down, ' . WP_PostRatings_Options::COLOR_DOWN . ' )',
			$css,
			'the down fallback in the stylesheet does not match the constant'
		);
	}
	/**
	 * An up/down pair renders green and red without anything being stored.
	 *
	 * The bug this pins: the settings table showed those colours as the default
	 * while the preview beside it read the stored value, found nothing, and fell
	 * back to the stylesheet's orange. A default that only exists in the swatch
	 * is not a default.
	 */
	public function test_an_up_down_pair_defaults_to_green_and_red_when_rendered() {
		$options                         = WP_PostRatings_Options::get();
		$options['shape']                = 'thumb';
		$options['max']                  = 2;
		$options['ratings']['color']     = array( '', '' );
		$options['ratings']['color_off'] = array( '', '' );
		WP_PostRatings_Options::update( $options );

		$this->assertSame(
			'--wp-postratings-color-on:' . WP_PostRatings_Options::COLOR_DOWN,
			WP_PostRatings_Template::rating_color_style( 1 ),
			'the down vote did not default to red'
		);
		$this->assertSame(
			'--wp-postratings-color-on:' . WP_PostRatings_Options::COLOR_UP,
			WP_PostRatings_Template::rating_color_style( 2 ),
			'the up vote did not default to green'
		);
	}

	/**
	 * A scale has no such convention, so it emits nothing and the stylesheet
	 * decides. Otherwise every star would be given a colour nobody chose.
	 */
	public function test_a_scale_emits_no_colour_of_its_own_by_default() {
		$options                         = WP_PostRatings_Options::get();
		$options['shape']                = 'star';
		$options['max']                  = 5;
		$options['ratings']['color']     = array( '', '', '', '', '' );
		$options['ratings']['color_off'] = array( '', '', '', '', '' );
		WP_PostRatings_Options::update( $options );

		$this->assertSame( '', WP_PostRatings_Template::rating_color_style( 3 ), 'a star step invented a colour' );
	}

	/**
	 * A stored colour still wins over the built-in one.
	 */
	public function test_a_stored_colour_wins_over_the_built_in_one() {
		$options                     = WP_PostRatings_Options::get();
		$options['shape']            = 'thumb';
		$options['max']              = 2;
		$options['ratings']['color'] = array( '#123456', '' );
		WP_PostRatings_Options::update( $options );

		$this->assertSame(
			'--wp-postratings-color-on:#123456',
			WP_PostRatings_Template::rating_color_style( 1 ),
			'the stored colour lost to the built-in one'
		);
	}
	/**
	 * The settings table's preview shows the colour the swatch beside it shows.
	 *
	 * Asserted against the rendered table, not against rating_color_style().
	 * That function returned the right answer throughout while nothing called it
	 * from the single-glyph renderer, so the screen stayed orange and every test
	 * passed.
	 */
	public function test_the_table_preview_matches_its_swatch() {
		$options                         = WP_PostRatings_Options::get();
		$options['shape']                = 'thumb';
		$options['max']                  = 2;
		$options['ratings']['color']     = array( '', '' );
		$options['ratings']['color_off'] = array( '', '' );
		WP_PostRatings_Options::update( $options );

		ob_start();
		WP_PostRatings_Settings::field_ratings();
		$html = ob_get_clean();

		preg_match_all(
			'~wp-postratings-rating-preview" style="--wp-postratings-color-on:(\#[0-9a-f]{6})~',
			$html,
			$previews
		);

		$this->assertSame(
			array( WP_PostRatings_Options::COLOR_DOWN, WP_PostRatings_Options::COLOR_UP ),
			$previews[1],
			'the preview glyphs do not carry the colours the swatches offer'
		);
	}
	/**
	 * An up/down rating shows one glyph, pointing whichever way won.
	 *
	 * The bug: a two step shape was drawn as a strip of $max glyphs, and the
	 * strip repeats one shape -- the up one -- so rating a post produced two
	 * thumbs up. An up/down set is a pair of opposing actions, not a
	 * one-out-of-two scale, so a rating on it is a direction.
	 *
	 * @dataProvider data_updown_averages
	 *
	 * @param float  $average   Stored average.
	 * @param string $direction Glyph that should be shown.
	 * @param string $colour    Colour it should carry.
	 */
	public function test_an_up_down_rating_shows_one_glyph( $average, $direction, $colour ) {
		$options                         = WP_PostRatings_Options::get();
		$options['shape']                = 'thumb';
		$options['max']                  = 2;
		$options['ratings']['color']     = array( '', '' );
		$options['ratings']['color_off'] = array( '', '' );
		WP_PostRatings_Options::update( $options );

		$html = WP_PostRatings_Template::ratings_images( 0, 2, $average, 'thumb', 'x' );

		$this->assertSame( 1, substr_count( $html, 'wp-postratings-item' ), 'an up/down rating drew more than one glyph' );
		$this->assertStringContainsString( 'shape-' . $direction . ')', $html, 'the glyph points the wrong way' );
		$this->assertStringContainsString( '--wp-postratings-color-on:' . $colour, $html, 'the glyph is the wrong colour' );
	}

	/**
	 * Averages either side of the midpoint.
	 *
	 * @return array
	 */
	public function data_updown_averages() {
		return array(
			// Signed: down is -1 and up is +1, so zero is the dividing line.
			'all up'      => array( 1.0, 'up', WP_PostRatings_Options::COLOR_UP ),
			'mostly up'   => array( 0.4, 'up', WP_PostRatings_Options::COLOR_UP ),
			'mostly down' => array( -0.4, 'down', WP_PostRatings_Options::COLOR_DOWN ),
			'all down'    => array( -1.0, 'down', WP_PostRatings_Options::COLOR_DOWN ),
		);
	}

	/**
	 * A scale still draws one glyph per step.
	 */
	public function test_a_scale_still_draws_a_glyph_per_step() {
		$options          = WP_PostRatings_Options::get();
		$options['shape'] = 'star';
		$options['max']   = 5;
		WP_PostRatings_Options::update( $options );

		$html = WP_PostRatings_Template::ratings_images( 0, 5, 3, 'star', 'x' );

		// Five in the track and five in the fill.
		$this->assertSame( 10, substr_count( $html, 'wp-postratings-item' ), 'the scale no longer draws a glyph per step' );
	}
	/**
	 * An up/down rating is always two, whatever the form posts.
	 *
	 * It is a pair of opposing actions, so there is nothing to choose. The Max
	 * field is readonly for it on the screen, and this is the half that holds
	 * when the form is posted by something other than the screen.
	 */
	public function test_an_up_down_rating_is_always_two_steps() {
		$clean = WP_PostRatings_Options::sanitize(
			array(
				'shape' => 'thumb',
				'max'   => 7,
			)
		);

		$this->assertSame( 2, $clean['max'], 'an up/down rating accepted a scale' );
	}

	/**
	 * Every shape belongs to exactly one of the two rating types.
	 *
	 * The type is derived from the shape and never stored beside it, so this is
	 * what makes that derivation total: a shape registered through
	 * wp_postratings_shapes without a type would otherwise silently become a
	 * scale.
	 */
	public function test_every_shape_has_a_rating_type() {
		foreach ( WP_PostRatings_Shapes::all() as $name => $shape ) {
			$this->assertContains(
				$shape['type'],
				array( WP_PostRatings_Shapes::SCALE, WP_PostRatings_Shapes::UPDOWN ),
				$name . ' has no rating type'
			);
		}
	}
	/**
	 * The scale is however many steps the table has.
	 *
	 * There is no Max field any more. The table is the scale, so a number beside
	 * it telling it how long to be was a second place holding one fact -- and the
	 * one that lost, because raising it left the text and value arrays shorter
	 * than the loop that reads them.
	 */
	public function test_the_scale_is_the_number_of_steps_posted() {
		$clean = WP_PostRatings_Options::sanitize(
			array(
				'shape'   => 'star',
				'ratings' => array(
					'text'  => array( 'a', 'b', 'c', 'd' ),
					'value' => array( 1, 2, 3, 4 ),
				),
			)
		);

		$this->assertSame( 4, $clean['max'], 'the scale did not follow the table' );
	}

	/**
	 * Adding beyond the cap, or removing below the floor, is refused.
	 */
	public function test_the_number_of_steps_is_bounded() {
		$clean = WP_PostRatings_Options::sanitize(
			array(
				'shape'   => 'star',
				'ratings' => array( 'text' => array_fill( 0, 50, 'x' ) ),
			)
		);

		$this->assertSame( 10, $clean['max'], 'a fifty step table was accepted' );

		$clean = WP_PostRatings_Options::sanitize(
			array(
				'shape'   => 'star',
				'ratings' => array( 'text' => array( 'only one' ) ),
			)
		);

		$this->assertSame( WP_PostRatings_Options::MIN_SCALE, $clean['max'], 'a one step table was accepted as a scale' );
	}

	/**
	 * An up/down rating stays two however many rows arrive.
	 */
	public function test_an_up_down_rating_ignores_the_number_of_rows() {
		$clean = WP_PostRatings_Options::sanitize(
			array(
				'shape'   => 'thumb',
				'ratings' => array( 'text' => array( 'a', 'b', 'c', 'd', 'e' ) ),
			)
		);

		$this->assertSame( 2, $clean['max'], 'an up/down rating grew a scale' );
	}
	/**
	 * An up/down vote is stored signed, and read that way.
	 *
	 * Down is -1 and up is +1, so a post's average runs from -1 to +1 and zero
	 * is the dividing line. It is not a scale of two: reading it against a
	 * scale's midpoint called every average below 1.5 a down vote, including a
	 * clear win for up.
	 *
	 * @dataProvider data_signed_averages
	 *
	 * @param float  $average   Stored average.
	 * @param string $direction Glyph that should be shown.
	 */
	public function test_a_signed_up_down_average_reads_the_right_way( $average, $direction ) {
		$options          = WP_PostRatings_Options::get();
		$options['shape'] = 'thumb';
		$options['max']   = 2;
		WP_PostRatings_Options::update( $options );

		$html = WP_PostRatings_Template::ratings_images( 0, 2, $average, 'thumb', 'x' );

		$this->assertStringContainsString( 'shape-' . $direction . ')', $html, 'an average of ' . $average . ' pointed the wrong way' );
	}

	/**
	 * Averages either side of zero.
	 *
	 * @return array
	 */
	public function data_signed_averages() {
		return array(
			'unanimously up'   => array( 1.0, 'up' ),
			'mostly up'        => array( 0.5, 'up' ),
			'barely up'        => array( 0.1, 'up' ),
			'even'             => array( 0.0, 'down' ),
			'mostly down'      => array( -0.5, 'down' ),
			'unanimously down' => array( -1.0, 'down' ),
		);
	}
	/**
	 * Changing the rating type starts the new one from its own defaults.
	 *
	 * Everything the table holds belongs to one type or the other, so this is a
	 * reset rather than a merge: labels, values and colours all come from the
	 * type being switched to. Carrying any of it across is what left a scale's
	 * first row at -1, and left an up/down pair wearing its stars' colours.
	 *
	 * @dataProvider data_type_switches
	 *
	 * @param string $from  Shape switched away from.
	 * @param string $to    Shape switched to.
	 * @param int    $max   Steps expected afterwards.
	 * @param string $text  First label expected.
	 * @param int    $value First value expected.
	 */
	public function test_changing_the_rating_type_resets_to_its_defaults( $from, $to, $max, $text, $value ) {
		$options                         = WP_PostRatings_Options::get();
		$options['shape']                = $from;
		$options['ratings']['color']     = array_fill( 0, 5, '#e32400' );
		$options['ratings']['color_off'] = array_fill( 0, 5, '#cccccc' );
		WP_PostRatings_Options::update( $options );

		$clean = WP_PostRatings_Options::sanitize( array( 'shape' => $to ) );

		$this->assertSame( $max, $clean['max'], 'the scale did not reset' );
		$this->assertSame( $text, $clean['ratings']['text'][0], 'the labels did not reset' );
		$this->assertSame( $value, $clean['ratings']['value'][0], 'the values did not reset' );
		$this->assertSame( '', $clean['ratings']['color'][0], 'a colour survived the type change' );
		$this->assertSame( '', $clean['ratings']['color_off'][0], 'a colour survived the type change' );
		$this->assertCount( $max, $clean['ratings']['color'], 'the colours are not one per step' );
	}

	/**
	 * Both directions.
	 *
	 * @return array
	 */
	public function data_type_switches() {
		return array(
			'scale to up/down' => array( 'star', 'thumb', 2, 'Vote Down', -1 ),
			'up/down to scale' => array( 'thumb', 'star', 5, '1 Star', 1 ),
		);
	}

	/**
	 * Staying within a type keeps them.
	 */
	public function test_changing_shape_within_a_type_keeps_the_colours() {
		$options                     = WP_PostRatings_Options::get();
		$options['shape']            = 'star';
		$options['max']              = 5;
		$options['ratings']['color'] = array_fill( 0, 5, '#e32400' );
		WP_PostRatings_Options::update( $options );

		$clean = WP_PostRatings_Options::sanitize(
			array(
				'shape'   => 'heart',
				'ratings' => array( 'color' => array_fill( 0, 5, '#e32400' ) ),
			)
		);

		$this->assertSame( array_fill( 0, 5, '#e32400' ), $clean['ratings']['color'], 'a colour was lost moving between two scales' );
	}
	/**
	 * Every preview in the table carries the colours its swatches show.
	 *
	 * A row with nothing stored used to emit no colour at all and fall through
	 * to the stylesheet -- which is not the built-in colour under a dark colour
	 * scheme or increased contrast, both of which move
	 * --wp-postratings-color-off. So a newly added step rendered darker than the
	 * swatch beside it promised.
	 */
	public function test_every_preview_carries_its_own_colours() {
		$options                         = WP_PostRatings_Options::get();
		$options['shape']                = 'star';
		$options['max']                  = 5;
		$options['ratings']['color']     = array( '#e32400', '', '', '', '' );
		$options['ratings']['color_off'] = array( '', '', '', '', '' );
		WP_PostRatings_Options::update( $options );

		ob_start();
		WP_PostRatings_Settings::field_ratings();
		$html = ob_get_clean();

		preg_match_all( '~wp-postratings-rating-preview" style="([^"]+)"~', $html, $previews );

		$this->assertCount( 5, $previews[1], 'not every step has a preview' );

		foreach ( $previews[1] as $step => $style ) {
			$style = html_entity_decode( $style );

			$this->assertStringContainsString( '--wp-postratings-color-off:' . WP_PostRatings_Options::COLOR_UNRATED, $style, 'step ' . ( $step + 1 ) . ' left its unrated colour to the stylesheet' );
			$this->assertStringContainsString( '--wp-postratings-color-on:', $style, 'step ' . ( $step + 1 ) . ' left its rated colour to the stylesheet' );
		}

		$this->assertStringContainsString( '--wp-postratings-color-on:#e32400', html_entity_decode( $previews[1][0] ), 'the stored colour did not reach the first preview' );
	}
	/**
	 * Switching type shows the new type's colours before anything is saved.
	 *
	 * The table is drawn for a shape the site has not saved yet, so taking the
	 * defaults from stored state showed a five point rating's orange in an
	 * up/down pair that had never had one. They come from the shape being
	 * rendered instead.
	 */
	public function test_switching_type_uses_the_new_types_colours_unsaved() {
		$options                         = WP_PostRatings_Options::get();
		$options['shape']                = 'star';
		$options['max']                  = 5;
		$options['ratings']['color']     = array_fill( 0, 5, '#e32400' );
		$options['ratings']['color_off'] = array_fill( 0, 5, '#cccccc' );
		WP_PostRatings_Options::update( $options );

		ob_start();
		WP_PostRatings_Settings::render_rating_fields( 2, 'thumb', array( 'Down', 'Up' ), array( -1, 1 ), array(), array() );
		$html = ob_get_clean();

		preg_match_all( '~data-default="(\#[0-9a-f]{6})"[^>]*data-property="--wp-postratings-color-on"~', $html, $swatches );
		preg_match_all( '~wp-postratings-rating-preview" style="--wp-postratings-color-on:(\#[0-9a-f]{6})~', $html, $previews );

		$expected = array( WP_PostRatings_Options::COLOR_DOWN, WP_PostRatings_Options::COLOR_UP );

		$this->assertSame( $expected, $swatches[1], 'the swatches kept the old type\'s colours' );
		$this->assertSame( $expected, $previews[1], 'the previews kept the old type\'s colours' );
	}
	/**
	 * The customrating flag is derived from the shape, never posted.
	 *
	 * It records whether the rating is an up/down pair, which the shape already
	 * says. It used to arrive as a hidden field beside Max Ratings, and when
	 * that field was removed the value silently stopped being submitted -- so
	 * the screen went on treating a thumbs set as a scale and carried its
	 * colours across.
	 */
	public function test_customrating_follows_the_shape() {
		$this->assertSame( 1, WP_PostRatings_Options::sanitize( array( 'shape' => 'thumb' ) )['customrating'], 'an up/down shape did not set customrating' );
		$this->assertSame( 0, WP_PostRatings_Options::sanitize( array( 'shape' => 'star' ) )['customrating'], 'a scale did not clear customrating' );
	}

	/**
	 * Each rating type starts from one definition, and they differ where it
	 * matters: length, labels, values and colours.
	 */
	public function test_each_type_has_its_own_defaults() {
		$scale  = WP_PostRatings_Options::defaults_for_type( 'star' );
		$updown = WP_PostRatings_Options::defaults_for_type( 'thumb' );

		$this->assertSame( 5, $scale['max'], 'a scale does not default to five steps' );
		$this->assertSame( 2, $updown['max'], 'an up/down pair is not two steps' );

		$this->assertSame( '1 Star', $scale['ratings']['text'][0], 'a scale does not start at 1 Star' );
		$this->assertSame( 'Vote Down', $updown['ratings']['text'][0], 'an up/down pair does not start at Vote Down' );

		$this->assertSame( array( 1, 2, 3, 4, 5 ), $scale['ratings']['value'], 'a scale is not 1 to 5' );
		$this->assertSame( array( -1, 1 ), $updown['ratings']['value'], 'an up/down pair is not signed' );

		// Both leave the colours empty, so the built-ins apply -- orange for a
		// scale, green and red for the pair.
		$this->assertSame( array_fill( 0, 5, '' ), $scale['ratings']['color'], 'a scale stores a colour it was never given' );
		$this->assertSame( array( '', '' ), $updown['ratings']['color'], 'an up/down pair stores a colour it was never given' );
	}

	/**
	 * The first save after a type change keeps what was typed into it.
	 *
	 * The screen rebuilds the table as soon as the type changes, so the rows
	 * that arrive with the new shape are already the new type's -- including any
	 * colour chosen before pressing Save. Resetting them because the type had
	 * changed discarded exactly one submission's worth of edits, every time, and
	 * it was the submission where the site had just picked its colours.
	 */
	public function test_the_first_save_after_a_type_change_keeps_its_colours() {
		WP_PostRatings_Options::update(
			array_merge(
				WP_PostRatings_Options::defaults(),
				array( 'shape' => 'star' )
			)
		);

		$clean = WP_PostRatings_Options::sanitize(
			array(
				'shape'   => 'thumb',
				'ratings' => array(
					'text'      => array( 'Nope', 'Yep' ),
					'value'     => array( -1, 1 ),
					'color'     => array( '#111111', '#222222' ),
					'color_off' => array( '#333333', '#444444' ),
				),
			)
		);

		$this->assertSame( 2, $clean['max'], 'switching to an up/down shape did not give two steps' );
		$this->assertSame( array( 'Nope', 'Yep' ), $clean['ratings']['text'], 'the labels typed alongside the switch were discarded' );
		$this->assertSame( array( '#111111', '#222222' ), $clean['ratings']['color'], 'the rated colours chosen alongside the switch were discarded' );
		$this->assertSame( array( '#333333', '#444444' ), $clean['ratings']['color_off'], 'the unrated colours chosen alongside the switch were discarded' );
	}

	/**
	 * A form that never saw the change still resets.
	 *
	 * Without the script -- or from a page opened before the shape changed --
	 * the table posted is the old type's, and a five row scale is not a rating
	 * an up/down shape can describe.
	 */
	public function test_a_table_from_the_old_type_is_still_reset() {
		WP_PostRatings_Options::update(
			array_merge(
				WP_PostRatings_Options::defaults(),
				array( 'shape' => 'star' )
			)
		);

		$clean = WP_PostRatings_Options::sanitize(
			array(
				'shape'   => 'thumb',
				'ratings' => array(
					'text'  => array( '1 Star', '2 Stars', '3 Stars', '4 Stars', '5 Stars' ),
					'value' => array( 1, 2, 3, 4, 5 ),
					'color' => array_fill( 0, 5, '#f5a623' ),
				),
			)
		);

		$this->assertSame( 2, $clean['max'], 'a scale posted against an up/down shape was not reset' );
		$this->assertSame( 'Vote Down', $clean['ratings']['text'][0], 'the reset did not use the up/down labels' );
		$this->assertSame( array( -1, 1 ), $clean['ratings']['value'], 'the reset did not use signed values' );
		$this->assertSame( array( '', '' ), $clean['ratings']['color'], 'the scale colours survived a reset' );
	}
}
