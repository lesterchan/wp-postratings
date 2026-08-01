<?php
/**
 * WP-PostRatings' half of the metadata contract.
 *
 * The contract itself is Plugin_Metadata_TestCase, a byte-identical copy of
 * _standards/templates/helper-metadata-testcase.php that every one of the
 * nineteen plugins carries. Everything shared lives there. What is left here is
 * what a machine cannot derive from the directory: the version being shipped,
 * the class prefix, the breaks the Upgrade Notice has to name, and the hooks
 * that have to reach into WP-PostRatings' own classes.
 *
 * @package WP-PostRatings
 */

/**
 * The shared contract, plus what only WP-PostRatings can answer.
 */
class WP_PostRatings_Metadata_Test extends Plugin_Metadata_TestCase {

	/**
	 * The version this release ships.
	 *
	 * Written out rather than read from WP_POSTRATINGS_VERSION, so a bump has to
	 * be made here as well and cannot happen by accident.
	 *
	 * @return string
	 */
	protected function expected_version() {
		return '2.0.0';
	}

	/**
	 * The prefix every class the plugin declares carries.
	 *
	 * @return string
	 */
	protected function class_prefix() {
		return 'WP_PostRatings';
	}

	/**
	 * Everything a site owner updating from the released version would notice.
	 *
	 * The fifteen option rows that were folded up, the two shared WP-Stats rows,
	 * the settings screen that moved, the rich snippet settings that became one,
	 * the images that became CSS shapes, every renamed class, hook, constant and
	 * selector, and the browser functions custom JavaScript used to call.
	 *
	 * @return string[]
	 */
	protected function upgrade_notice_subjects() {
		return array(
			'6.8',
			'8.2',
			'stats_display',
			'stats_mostlimit',
			'wp_postratings_options',
			'wp_postratings_version',
			'postratings_db_version',
			'wp-postratings-options',
			'wp-postratings-settings',
			'manage_ratings',
			'wp_postratings_schema_itemtype',
			'.post-ratings',
			'.wp-postratings',
			'wp-postratings-123',
			'postratings-css.css',
			'css/wp-postratings.css',
			'wp_postratings_rate_post',
			'RATINGS_IMG_EXT',
			'WP_POSTRATINGS_IMG_EXT',
			'Postratings_',
			'WP_PostRatings_',
			'current_rating()',
			'js/wp-postratings.js',
		);
	}

	/**
	 * WP-PostRatings is one of the seven sharing the WP-Stats surface.
	 *
	 * @return bool
	 */
	protected function wp_stats_family() {
		return true;
	}

	/**
	 * The unprefixed WP-Stats rows WP-PostRatings reads but does not own.
	 *
	 * @return string[]
	 */
	protected function shared_wp_stats_rows() {
		return array( 'stats_display', 'stats_mostlimit' );
	}

	/**
	 * Write the rows uninstall is expected to remove.
	 *
	 * @return void
	 */
	protected function seed_option_rows() {
		WP_PostRatings_Options::update( WP_PostRatings_Options::defaults() );
		WP_PostRatings_Options::update_markers();
	}

	/**
	 * Write the wp_postratings_version marker row.
	 *
	 * @return void
	 */
	protected function write_version_row() {
		WP_PostRatings_Options::update_markers();
	}

	/**
	 * Round-trip the settings sanitiser.
	 *
	 * @param array $input What the settings form is pretending to have posted.
	 * @return array
	 */
	protected function sanitize_settings( array $input ) {
		return (array) WP_PostRatings_Options::sanitize( $input );
	}

	/**
	 * Real settings keys to send through the sanitiser beside the poison.
	 *
	 * @return array
	 */
	protected function settings_fixture() {
		return array(
			'max'       => 5,
			'ip_header' => 'HTTP_X_FORWARDED_FOR',
		);
	}

	/**
	 * Register the front-end and admin assets.
	 *
	 * The admin half is driven with a hook suffix the menu actually reported
	 * rather than a hand-built string. That distinction is not pedantry: this
	 * test went on passing with a hand-built one while the assets had stopped
	 * loading on the real screen.
	 *
	 * @return void
	 */
	protected function register_plugin_assets() {
		WP_PostRatings::get_instance()->scripts();

		get_role( 'administrator' )->add_cap( WP_PostRatings_Settings::CAPABILITY );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		WP_PostRatings_Admin::menu();

		$hooks = WP_PostRatings_Admin::screen_hooks();

		$this->assertNotEmpty( $hooks, 'The menu registered no screens to enqueue on.' );

		WP_PostRatings_Admin::scripts( $hooks[0] );
	}

	/**
	 * The one stylesheet uses logical properties throughout.
	 *
	 * §5.1 drops the mirrored sheet, and the shared test proves no second sheet
	 * ships. That is only safe if the sheet that remains works in both
	 * directions, which is what this asserts - a physical margin-left reads
	 * correctly in English and wrongly in Arabic, and nothing else would catch
	 * it.
	 *
	 * @return void
	 */
	public function test_the_stylesheet_uses_logical_properties_only() {
		$css = (string) file_get_contents( $this->metadata_root() . '/css/wp-postratings.css' );

		$physical = array(
			'margin-left',
			'margin-right',
			'padding-left',
			'padding-right',
			'text-align: left',
			'text-align: right',
			'border-left',
			'border-right',
		);

		foreach ( $physical as $property ) {
			$this->assertStringNotContainsString(
				$property,
				$css,
				$property . ' is a physical property; use its logical equivalent (§5.1).'
			);
		}
	}
}
