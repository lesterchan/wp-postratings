<?php
/**
 * Tests for uninstall and the installer.
 *
 * @package WP-PostRatings
 */

/**
 * Table installation, the capability, and the uninstall routine.
 *
 * @covers WP_PostRatings_Install
 */
class WP_PostRatings_Uninstall_Test extends WP_PostRatings_TestCase {

	/**
	 * The log table exists after installation.
	 *
	 * @return void
	 */
	public function test_the_table_is_installed() {
		global $wpdb;

		$this->assertSame( $wpdb->ratings, $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->ratings ) ), 'The table is installed.' );
	}

	/**
	 * The table name is registered with $wpdb, not merely assigned.
	 *
	 * The tables[] entry is what keeps it correct across switch_to_blog().
	 *
	 * @return void
	 */
	public function test_the_table_is_registered_with_wpdb() {
		global $wpdb;

		$this->assertContains( 'ratings', $wpdb->tables, 'The table is registered with wpdb.' );
		$this->assertSame( $wpdb->prefix . 'ratings', $wpdb->ratings, 'Under the table prefix.' );
	}

	/**
	 * Administrators get the capability.
	 *
	 * @return void
	 */
	public function test_the_capability_is_granted() {
		$this->assertTrue( get_role( 'administrator' )->has_cap( WP_PostRatings_Settings::capability() ), 'The fixture really does grant the capability, so the uninstall below has something to revoke.' );
	}

	/**
	 * Re-running the installer is a no-op rather than an ALTER on every load.
	 *
	 * DbDelta cannot round-trip this schema, so calling it unconditionally
	 * emits "ALTER TABLE ... SET DEFAULT ''" against the table every time.
	 *
	 * @return void
	 */
	public function test_reinstalling_does_not_alter_the_table() {
		global $wpdb;

		$before = $wpdb->get_var( "SHOW CREATE TABLE {$wpdb->ratings}", 1 );

		WP_PostRatings_Install::install();
		WP_PostRatings_Install::maybe_upgrade();

		$this->assertSame( $before, $wpdb->get_var( "SHOW CREATE TABLE {$wpdb->ratings}", 1 ), 'Reinstalling leaves the table as it was.' );
	}

	/**
	 * The schema version is recorded so the check can be skipped next load.
	 *
	 * @return void
	 */
	public function test_the_schema_version_is_recorded() {
		$this->assertSame(
			WP_POSTRATINGS_DB_VERSION,
			WP_PostRatings_Options::markers()['db'],
			'The schema version is recorded, so the next upgrade knows where it starts.'
		);
	}

	/**
	 * The root uninstall file delegates rather than doing the work itself.
	 *
	 * WordPress loads this one file and nothing else when a plugin is deleted,
	 * so whatever it contains is unreachable from every other test. Keeping it
	 * to a require and a call is what makes the uninstall path testable at all.
	 *
	 * @return void
	 */
	public function test_uninstall_php_delegates_to_the_installer() {
		$source = file_get_contents( dirname( __DIR__ ) . '/uninstall.php' );

		$this->assertStringContainsString( 'WP_PostRatings_Install::uninstall();', $source, 'uninstall.php delegates rather than repeating the work.' );
		$this->assertStringContainsString( "defined( 'WP_UNINSTALL_PLUGIN' )", $source, 'uninstall.php must refuse to run on its own' );
		$this->assertStringNotContainsString( '$wpdb', $source, 'the work belongs in the installer, beside the install it undoes' );
	}

	/**
	 * The uninstaller lifts WP_Site_Query's default cap of 100 sites.
	 *
	 * A single-site suite cannot build a 101-site network, so this is a
	 * source-level guard: without 'number' => 0 the loop silently stops at the
	 * hundredth site, leaving the options and tables behind on every one after
	 * it while still reporting success. wp_get_sites() -- deprecated in
	 * WordPress 4.6 and capped the same way -- is checked for at the same time.
	 *
	 * @return void
	 */
	public function test_uninstall_lifts_the_site_query_cap() {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-wp-postratings-install.php' );

		$this->assertMatchesRegularExpression( "/'number'\s*=>\s*0/", $source, 'uninstall.php lifts the site query cap, or a network past the default would be half-uninstalled.' );
		$this->assertMatchesRegularExpression( "/'fields'\s*=>\s*'ids'/", $source, 'uninstall.php asks for ids only, which is what makes the unlimited query affordable.' );
		$this->assertStringNotContainsString( 'wp_get_sites', $source, 'And does not call the function core removed.' );
	}

	/**
	 * The call to restore_current_blog() sits inside the site loop.
	 *
	 * Switching pushes onto a stack, so switching many times and restoring
	 * once leaves the stack unwound by exactly one.
	 *
	 * @return void
	 */
	public function test_uninstall_restores_inside_the_loop() {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-wp-postratings-install.php' );

		$this->assertMatchesRegularExpression(
			'/foreach\s*\(.*\)\s*\{\s*switch_to_blog\(.*\);\s*self::uninstall_site\(\);\s*restore_current_blog\(\);\s*\}/s',
			$source,
			'The restore sits inside the loop; once after it leaves the stack unwound by one.'
		);
	}

	/**
	 * Uninstalling one site clears its rows, its table and its capability.
	 *
	 * @return void
	 */
	public function test_uninstalling_a_site_clears_everything_it_owns() {
		global $wpdb;

		$post_id = $this->make_rated_post( 4, 18 );
		$this->log_rating( $post_id );

		// Watch for the DROP rather than looking for the table afterwards.
		//
		// WP_UnitTestCase filters every query through _create_temporary_tables()
		// and _drop_temporary_tables(), which rewrite CREATE/DROP TABLE into the
		// TEMPORARY forms so a test cannot alter real schema. SHOW TABLES never
		// lists temporary tables and a DROP TEMPORARY TABLE cannot remove a real
		// one, so "is the table gone" is a question this environment cannot
		// answer -- it reports whatever real table the bootstrap happened to
		// leave behind, no matter what uninstall did.
		$dropped = false;
		$watch   = static function ( $query ) use ( &$dropped ) {
			if ( false !== stripos( $query, 'DROP' ) && false !== stripos( $query, 'ratings' ) ) {
				$dropped = true;
			}

			return $query;
		};

		add_filter( 'query', $watch );
		WP_PostRatings_Install::uninstall_site();
		remove_filter( 'query', $watch );

		$this->assertFalse( get_option( WP_PostRatings_Options::OPTION ), 'the settings row survived' );
		$this->assertFalse( get_option( WP_PostRatings_Options::VERSION ), 'the marker row survived' );
		$this->assertTrue( $dropped, 'uninstall issued no DROP for the log table' );
		$this->assertSame( '', get_post_meta( $post_id, 'ratings_users', true ), 'the rating meta survived' );
		$this->assertFalse( get_role( 'administrator' )->has_cap( WP_PostRatings_Settings::capability() ), 'the capability survived' );

		// Put the site back for whatever runs next: neither the table nor the
		// capability is part of WP_UnitTestCase's rollback.
		WP_PostRatings_Install::install();
	}

	/**
	 * The uninstall list covers every row the plugin can leave behind.
	 *
	 * @return void
	 */
	public function test_the_uninstall_list_covers_every_option() {
		$names = WP_PostRatings_Options::all_option_names();

		$this->assertContains( WP_PostRatings_Options::OPTION, $names, 'The options row is on the uninstall list.' );
		$this->assertContains( WP_PostRatings_Options::VERSION, $names, 'The version row.' );
		$this->assertContains( 'postratings_options', $names, 'the renamed row would be orphaned' );
		$this->assertContains( 'postratings_db_version', $names, 'The legacy schema row.' );
		$this->assertContains( 'postratings_options_version', $names, 'The legacy options version row.' );
		$this->assertContains( 'postratings_template_vote', $names, 'a legacy row would be orphaned' );
		$this->assertContains( 'widget_ratings-widget', $names, 'And the widget row, which is the one easiest to forget.' );
	}
}
