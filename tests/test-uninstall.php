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
class Test_Postratings_Uninstall extends WP_PostRatings_TestCase {

	/**
	 * The log table exists after installation.
	 *
	 * @return void
	 */
	public function test_the_table_is_installed() {
		global $wpdb;

		$this->assertSame( $wpdb->ratings, $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->ratings ) ) );
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

		$this->assertContains( 'ratings', $wpdb->tables );
		$this->assertSame( $wpdb->prefix . 'ratings', $wpdb->ratings );
	}

	/**
	 * Administrators get the capability.
	 *
	 * @return void
	 */
	public function test_the_capability_is_granted() {
		$this->assertTrue( get_role( 'administrator' )->has_cap( WP_PostRatings_Settings::capability() ) );
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

		$this->assertSame( $before, $wpdb->get_var( "SHOW CREATE TABLE {$wpdb->ratings}", 1 ) );
	}

	/**
	 * The schema version is recorded so the check can be skipped next load.
	 *
	 * @return void
	 */
	public function test_the_schema_version_is_recorded() {
		$this->assertSame(
			WP_POSTRATINGS_DB_VERSION,
			(string) get_option( WP_PostRatings_Install::DB_VERSION_OPTION )
		);
	}

	/**
	 * The uninstaller lifts WP_Site_Query's default cap of 100 sites.
	 *
	 * A single-site suite cannot build a 101-site network, so this is a
	 * source-level guard: without 'number' => 0 the loop silently stops at the
	 * hundredth site, leaving the options and tables behind on every one after
	 * it while still reporting success.
	 *
	 * @return void
	 */
	public function test_uninstall_lifts_the_site_query_cap() {
		$source = file_get_contents( dirname( __DIR__ ) . '/uninstall.php' );

		$this->assertMatchesRegularExpression( "/'number'\s*=>\s*0/", $source );
		$this->assertMatchesRegularExpression( "/'fields'\s*=>\s*'ids'/", $source );
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
		$source = file_get_contents( dirname( __DIR__ ) . '/uninstall.php' );

		$this->assertMatchesRegularExpression(
			'/foreach\s*\(.*\)\s*\{\s*switch_to_blog\(.*\);\s*postratings_uninstall_site\(\);\s*restore_current_blog\(\);\s*\}/s',
			$source
		);
	}

	/**
	 * The removed wp_get_sites() is gone; it went in WP 5.1.
	 *
	 * @return void
	 */
	public function test_uninstall_does_not_call_a_removed_function() {
		$source = file_get_contents( dirname( __DIR__ ) . '/uninstall.php' );

		$this->assertStringNotContainsString( 'wp_get_sites', $source );
	}

	/**
	 * The uninstall list covers every row the plugin can leave behind.
	 *
	 * @return void
	 */
	public function test_the_uninstall_list_covers_every_option() {
		$names = WP_PostRatings_Options::all_option_names();

		$this->assertContains( WP_PostRatings_Options::OPTION, $names );
		$this->assertContains( WP_PostRatings_Options::VERSION_OPTION, $names );
		$this->assertContains( WP_PostRatings_Install::DB_VERSION_OPTION, $names );
		$this->assertContains( 'postratings_template_vote', $names, 'a legacy row would be orphaned' );
		$this->assertContains( 'widget_ratings-widget', $names );
	}
}
