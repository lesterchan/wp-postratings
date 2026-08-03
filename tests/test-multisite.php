<?php
/**
 * Tests that only mean anything on a network.
 *
 * They skip themselves on a single site run, so bin/test-multisite.sh is the
 * only way they execute.
 *
 * @package WP-PostRatings
 */

/**
 * Table registration, per-site isolation and network activation.
 *
 * @group ms-required
 *
 * @covers WP_PostRatings_Install::activate
 * @covers WP_PostRatings::register_table
 */
class WP_PostRatings_Multisite_Test extends WP_PostRatings_TestCase {

	/**
	 * Skip the whole class unless this is a network.
	 *
	 * @return void
	 */
	public function set_up() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Runs only against a multisite network.' );
		}

		parent::set_up();
	}

	/**
	 * Create a second site on the network.
	 *
	 * @return int Blog id.
	 */
	private function make_site() {
		return self::factory()->blog->create();
	}

	// --- table registration -----------------------------------------------

	/**
	 * The table name follows switch_to_blog().
	 *
	 * This is what $wpdb->tables[] buys. Assigning $wpdb->ratings alone leaves
	 * it pointing at whichever site happened to load the plugin, so every
	 * switched read and write would hit the wrong site's table.
	 *
	 * @return void
	 */
	public function test_the_table_name_follows_the_current_site() {
		global $wpdb;

		$other = $this->make_site();

		$before = $wpdb->ratings;

		switch_to_blog( $other );
		$switched = $wpdb->ratings;
		restore_current_blog();

		$this->assertNotSame( $before, $switched, 'the table name did not change with the site' );
		$this->assertSame( $before, $wpdb->ratings, 'the table name did not come back' );
		$this->assertStringContainsString( (string) $other, $switched );
	}

	/**
	 * The table is registered as a blog-scoped table, not merely assigned.
	 *
	 * @return void
	 */
	public function test_the_table_is_registered_as_blog_scoped() {
		global $wpdb;

		$this->assertContains( 'ratings', $wpdb->tables );
		$this->assertArrayHasKey( 'ratings', $wpdb->tables( 'blog' ), 'The ratings table is registered blog-scoped, so each site gets its own.' );
	}

	// --- isolation --------------------------------------------------------

	/**
	 * Each site keeps its own rating log.
	 *
	 * @return void
	 */
	public function test_each_site_has_its_own_log() {
		global $wpdb;

		$other = $this->make_site();

		switch_to_blog( $other );
		WP_PostRatings_Install::install();
		$other_post = $this->make_rated_post( 0, 0 );
		$this->log_rating( $other_post, 5, 'Elsewhere' );
		$other_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->ratings}" );
		restore_current_blog();

		$this->assertSame( 1, $other_count );
		$this->assertSame( 0, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->ratings}" ) );
	}

	/**
	 * A vote recorded on one site does not appear on another.
	 *
	 * @return void
	 */
	public function test_a_vote_is_confined_to_its_site() {
		global $wpdb;

		$this->set_option( 'allowtorate', 2 );
		$this->set_option( 'check_method', 2 );

		$other = $this->make_site();

		switch_to_blog( $other );
		WP_PostRatings_Install::install();
		$options                 = WP_PostRatings_Options::get();
		$options['allowtorate']  = 2;
		$options['check_method'] = 2;
		WP_PostRatings_Options::update( $options );

		$post_id = $this->make_rated_post( 0, 0 );
		WP_PostRatings_Rating::process_vote( $post_id, 4 );

		$this->assertSame( 1, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->ratings}" ) );
		restore_current_blog();

		$this->assertSame( 0, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->ratings}" ) );
	}

	/**
	 * Settings are per site.
	 *
	 * @return void
	 */
	public function test_settings_are_per_site() {
		$other = $this->make_site();

		$this->set_option( 'shape', 'stars' );

		switch_to_blog( $other );
		WP_PostRatings_Install::install();
		$options          = WP_PostRatings_Options::get();
		$options['shape'] = 'thumbs';
		WP_PostRatings_Options::update( $options );
		restore_current_blog();

		$this->assertSame( 'stars', WP_PostRatings_Options::get( 'shape' ) );

		switch_to_blog( $other );
		$this->assertSame( 'thumbs', WP_PostRatings_Options::get( 'shape' ) );
		restore_current_blog();
	}

	/**
	 * The lock file is named per site.
	 *
	 * Two sites sharing one lock would serialise unrelated votes across the
	 * whole network.
	 *
	 * @return void
	 */
	public function test_the_lock_file_is_named_per_site() {
		$other = $this->make_site();

		$here = WP_PostRatings_Rating::lock_file( 1 );

		switch_to_blog( $other );
		$there = WP_PostRatings_Rating::lock_file( 1 );
		restore_current_blog();

		$this->assertNotSame( $here, $there );
		$this->assertStringContainsString( 'wp-blog-' . $other . '-', $there );
	}

	// --- network activation -----------------------------------------------

	/**
	 * Network activation installs on every site, not just the current one.
	 *
	 * @return void
	 */
	public function test_network_activation_installs_everywhere() {
		global $wpdb;

		$first  = $this->make_site();
		$second = $this->make_site();

		// Tear the table down on both so activation has something to do.
		foreach ( array( $first, $second ) as $blog_id ) {
			switch_to_blog( $blog_id );
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->ratings}" );
			delete_option( WP_PostRatings_Options::VERSION );
			restore_current_blog();
		}

		WP_PostRatings_Install::activate( true );

		foreach ( array( $first, $second ) as $blog_id ) {
			switch_to_blog( $blog_id );
			// Both sides have to be read inside the switch: $wpdb->ratings is
			// recomputed on the way back out.
			$expected = $wpdb->ratings;
			$exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $expected ) );
			restore_current_blog();

			$this->assertSame( $expected, $exists, 'site ' . $blog_id . ' did not get its table' );
		}
	}

	/**
	 * Activating for one site only touches that site.
	 *
	 * @return void
	 */
	public function test_single_site_activation_does_not_touch_the_network() {
		global $wpdb;

		$other = $this->make_site();

		switch_to_blog( $other );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->ratings}" );
		delete_option( WP_PostRatings_Options::VERSION );
		restore_current_blog();

		WP_PostRatings_Install::activate( false );

		switch_to_blog( $other );
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->ratings ) );
		restore_current_blog();

		$this->assertNull( $exists, 'a single site activation installed across the network' );
	}

	/**
	 * The switch stack is left balanced.
	 *
	 * Switching pushes onto a stack; restoring once after many switches leaves
	 * it unwound by exactly one, and every later switch is then off by one
	 * site.
	 *
	 * @return void
	 */
	public function test_network_activation_leaves_the_switch_stack_balanced() {
		global $wpdb;

		$this->make_site();
		$this->make_site();

		$before = get_current_blog_id();
		$depth  = count( $GLOBALS['_wp_switched_stack'] );

		WP_PostRatings_Install::activate( true );

		$this->assertSame( $before, get_current_blog_id(), 'activation left the wrong site current' );
		$this->assertCount( $depth, $GLOBALS['_wp_switched_stack'], 'the switch stack was left unbalanced' );
		$this->assertSame( $wpdb->prefix . 'ratings', $wpdb->ratings );
	}

	/**
	 * The capability is granted on each site of the network.
	 *
	 * Roles are per site, so granting it once would leave every other site's
	 * administrators locked out of the rating screens.
	 *
	 * @return void
	 */
	public function test_the_capability_is_granted_on_each_site() {
		$other = $this->make_site();

		switch_to_blog( $other );
		get_role( 'administrator' )->remove_cap( WP_PostRatings_Settings::capability() );
		restore_current_blog();

		WP_PostRatings_Install::activate( true );

		switch_to_blog( $other );
		$granted = get_role( 'administrator' )->has_cap( WP_PostRatings_Settings::capability() );
		restore_current_blog();

		$this->assertTrue( $granted, 'Network activation grants the capability on the other site too, not only the current one.' );
	}

	/**
	 * "Users registered on this site" is a network-only rule.
	 *
	 * @return void
	 */
	public function test_the_network_only_permission_rule_works() {
		$this->set_option( 'allowtorate', 3 );

		$member = self::factory()->user->create();
		$other  = $this->make_site();

		wp_set_current_user( $member );
		$this->assertTrue( WP_PostRatings_Rating::can_rate(), 'a member of this site was refused' );

		switch_to_blog( $other );
		$options                = WP_PostRatings_Options::get();
		$options['allowtorate'] = 3;
		WP_PostRatings_Options::update( $options );

		$this->assertFalse( WP_PostRatings_Rating::can_rate(), 'a non-member of the switched site was allowed' );
		restore_current_blog();
	}
}
