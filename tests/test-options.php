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
		$this->assertSame( '4', $options['logging_method'] );
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
				'allowtorate'    => 99,
				'logging_method' => 99,
				'max'            => -5,
			)
		);

		$this->assertSame( 2, $clean['allowtorate'] );
		$this->assertSame( 3, $clean['logging_method'] );
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
			'~<td class="wp-postratings wp-postratings-rating-preview">.*?--wp-postratings-color-on:(\#[0-9a-f]{6})~s',
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
			'all up'      => array( 2.0, 'up', WP_PostRatings_Options::COLOR_UP ),
			'mostly up'   => array( 1.6, 'up', WP_PostRatings_Options::COLOR_UP ),
			'mostly down' => array( 1.4, 'down', WP_PostRatings_Options::COLOR_DOWN ),
			'all down'    => array( 1.0, 'down', WP_PostRatings_Options::COLOR_DOWN ),
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
}
