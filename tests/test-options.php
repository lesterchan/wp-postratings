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
class Test_Postratings_Options extends WP_PostRatings_TestCase {

	/**
	 * The row the plugin already owned is the one it consolidates into.
	 *
	 * @return void
	 */
	public function test_the_option_row_is_the_pre_existing_one() {
		$this->assertSame( 'postratings_options', WP_PostRatings_Options::OPTION );
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
		$this->assertSame( 'star', $options['image'] );
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

		$this->assertSame( 'thumb', $options['image'], 'the up/down set did not map to its shape' );
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
		$this->assertSame( 'thumb', $second['image'], 'the second run reverted to defaults' );
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
			$clean = WP_PostRatings_Options::sanitize( array( 'image' => $shape ) );
			$this->assertSame( $shape, $clean['image'], $shape . ' is offered but rejected' );
		}

		$clean = WP_PostRatings_Options::sanitize( array( 'image' => '../../evil' ) );
		$this->assertNotSame( '../../evil', $clean['image'] );
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
		$clean = WP_PostRatings_Options::sanitize( array( 'image' => 'stars_crystal' ) );

		$this->assertSame( 'star', $clean['image'] );
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
		$this->assertSame( 1, $clean['max'] );
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
}
