<?php
/**
 * Tests for upgrading an existing 1.x install.
 *
 * Everything here starts from a site as it actually exists before 2.0.0 --
 * fifteen option rows, log rows with hashed addresses, rating meta, a
 * configured image set and customised templates -- and asserts what the site
 * looks like afterwards. The migration is covered in test-options.php; this is
 * about what the *user* sees.
 *
 * @package WP-PostRatings
 */

/**
 * The upgrade path from 1.91.3.
 *
 * @covers WP_PostRatings_Options::maybe_migrate
 * @covers WP_PostRatings_Install::maybe_upgrade
 */
class WP_PostRatings_Upgrade_Test extends WP_PostRatings_TestCase {

	/**
	 * Post carrying ratings from before the upgrade.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Fire `init` again, the way a second request would.
	 *
	 * `init` has already fired once before any test runs - the bootstrap loads
	 * the plugin and then finishes booting WordPress. Firing it again here is
	 * the point of the two tests below, which watch a front end request migrate
	 * without wp-admin ever being asked; but it also re-runs the
	 * once-per-process work hooked to init, and block registration is exactly
	 * that. Registering a block type twice is a _doing_it_wrong() notice, which
	 * this suite turns into a failure, and the double registration is an
	 * artefact of one process standing in for two requests rather than
	 * something a site can do. A real second request starts with an empty block
	 * registry, so empty it of this plugin's blocks first and the second init
	 * then does the same work the first one did.
	 *
	 * @return void
	 */
	private function fire_init() {
		$registry = WP_Block_Type_Registry::get_instance();

		foreach ( array_keys( $registry->get_all_registered() ) as $name ) {
			if ( 0 === strpos( $name, 'wp-postratings/' ) ) {
				$registry->unregister( $name );
			}
		}

		do_action( 'init' );
	}

	/**
	 * Build a site as 1.91.3 left it.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->post_id = $this->make_rated_post( 4, 18 );

		// Rating history, written the way 1.x wrote it: the address hashed, the
		// username and title slashed.
		$this->log_rating( $this->post_id, 4, "O\\'Brien", '203.0.113.7' );

		// The consolidated row as 1.x knew it: three keys, nothing else.
		delete_option( WP_PostRatings_Options::OPTION );
		delete_option( WP_PostRatings_Options::VERSION );
		update_option(
			'postratings_options',
			array(
				'ip_header'           => 'HTTP_CF_CONNECTING_IP',
				'richsnippet'         => 1,
				'richsnippet_ratings' => 0,
			)
		);

		// The fifteen separate rows.
		update_option( 'postratings_image', 'stars_crystal' );
		update_option( 'postratings_max', '5' );
		update_option( 'postratings_customrating', '0' );
		update_option( 'postratings_allowtorate', '1' );
		update_option( 'postratings_logging_method', '2' );
		update_option(
			'postratings_ajax_style',
			array(
				'loading' => 0,
				'fading'  => 1,
			)
		);
		update_option( 'postratings_ratingstext', array( 'Awful', 'Poor', 'OK', 'Good', 'Superb' ) );
		update_option( 'postratings_ratingsvalue', array( 1, 2, 3, 4, 5 ) );
		update_option( 'postratings_template_vote', 'Rate: %RATINGS_IMAGES_VOTE% O\\\'Brien' );
		update_option( 'postratings_template_text', 'Voted: %RATINGS_IMAGES% %RATINGS_AVERAGE%' );
		update_option( 'postratings_template_permission', 'No permission' );
		update_option( 'postratings_template_none', 'Nothing yet %RATINGS_IMAGES_VOTE%' );
		update_option( 'postratings_template_highestrated', '<li>H %POST_TITLE%</li>' );
		update_option( 'postratings_template_mostrated', '<li>M %POST_TITLE%</li>' );
	}

	// --- the settings survive ---------------------------------------------

	/**
	 * Every configured setting is still in force afterwards.
	 *
	 * @return void
	 */
	public function test_the_settings_survive() {
		WP_PostRatings_Install::maybe_upgrade();

		$options = WP_PostRatings_Options::get();

		$this->assertSame( '5', $options['max'], 'The scale survives the upgrade.' );
		$this->assertSame( '1', $options['allowtorate'], 'The permission setting.' );
		$this->assertSame( '2', $options['check_method'], 'The check method.' );
		$this->assertSame( 'HTTP_CF_CONNECTING_IP', $options['ip_header'], 'And the header setting.' );
		// The two rich snippet toggles are gone, and an install that had them on
		// lands on "No" rather than being given a type it never chose. The old
		// output declared schema.org/Article, which Google shows no rating for,
		// so nothing that worked is being taken away.
		$this->assertSame( '', $options['schema_type'], 'a legacy install was given a schema type it never picked' );
		$this->assertArrayNotHasKey( 'richsnippet', $options, 'the retired toggle survived the migration' );
		$this->assertArrayNotHasKey( 'richsnippet_ratings', $options, 'the retired toggle survived the migration' );
		$this->assertSame( array( 'Awful', 'Poor', 'OK', 'Good', 'Superb' ), $options['ratings']['text'], 'The rating labels survive whole.' );
		// The two "while a vote is in flight" settings are retired with the two
		// rich snippet ones. The loading text is gone and the rating always dims,
		// because dimming it is aria-busy -- which announces the update to
		// assistive technology as well as greying it, and that is not a
		// preference.
		$this->assertArrayNotHasKey( 'ajax_style', $options, 'the retired AJAX style settings survived the migration' );
	}

	/**
	 * The chosen image set becomes the equivalent shape.
	 *
	 * @return void
	 */
	public function test_the_chosen_style_carries_over() {
		WP_PostRatings_Install::maybe_upgrade();

		$this->assertSame( 'star', WP_PostRatings_Options::get( 'shape' ), 'The chosen style carries over as the shape it became.' );
	}

	/**
	 * Customised templates survive, unslashed exactly once.
	 *
	 * @return void
	 */
	public function test_customised_templates_survive() {
		WP_PostRatings_Install::maybe_upgrade();

		$this->assertSame( "Rate: %RATINGS_IMAGES_VOTE% O'Brien", WP_PostRatings_Options::template( 'vote' ), 'A customised vote template survives, apostrophe and all.' );
		$this->assertSame( 'Voted: %RATINGS_IMAGES% %RATINGS_AVERAGE%', WP_PostRatings_Options::template( 'text' ), 'And so does the results template.' );
	}

	// --- the data survives -------------------------------------------------

	/**
	 * Existing ratings are untouched.
	 *
	 * The post meta is the rating; losing it would reset every score on the
	 * site.
	 *
	 * @return void
	 */
	public function test_existing_ratings_are_untouched() {
		WP_PostRatings_Install::maybe_upgrade();

		$this->assertSame( 4, (int) get_post_meta( $this->post_id, 'ratings_users', true ), 'The vote count on an existing post is untouched.' );
		$this->assertSame( 18, (int) get_post_meta( $this->post_id, 'ratings_score', true ), 'And the score.' );
	}

	/**
	 * The rating log is untouched, hashes and all.
	 *
	 * @return void
	 */
	public function test_the_rating_log_is_untouched() {
		global $wpdb;

		WP_PostRatings_Install::maybe_upgrade();

		$row = $wpdb->get_row( "SELECT * FROM {$wpdb->ratings}" );

		$this->assertSame( wp_hash( '203.0.113.7' ), $row->rating_ip, 'The logged address is untouched.' );
		$this->assertSame( 4, (int) $row->rating_rating, 'And the rating.' );
	}

	/**
	 * A visitor who already voted is still recognised.
	 *
	 * The stored value keys de-duplication, so a changed hash would silently
	 * let everyone vote again.
	 *
	 * @return void
	 */
	public function test_a_previous_voter_is_still_recognised() {
		WP_PostRatings_Install::maybe_upgrade();

		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		unset( $_SERVER['HTTP_CF_CONNECTING_IP'] );

		$this->assertTrue( WP_PostRatings_Rating::has_rated( $this->post_id ), 'A voter recorded before the upgrade is still recognised after it.' );
	}

	// --- the front end keeps working ---------------------------------------

	/**
	 * The template tags still render, with the site's own templates.
	 *
	 * @return void
	 */
	public function test_the_front_end_uses_the_sites_own_templates() {
		WP_PostRatings_Install::maybe_upgrade();

		$this->assertStringContainsString( "O'Brien", the_ratings_vote( $this->post_id ), 'The front end renders the template the site configured.' );
		$this->assertStringContainsString( 'Voted:', the_ratings_results( $this->post_id ), 'For the results as well as the vote form.' );
	}

	/**
	 * The per-value labels the site configured are used.
	 *
	 * @return void
	 */
	public function test_the_configured_labels_are_used() {
		WP_PostRatings_Install::maybe_upgrade();

		$this->assertStringContainsString( 'Superb', the_ratings_vote( $this->post_id ), 'And uses the labels the site configured.' );
	}

	/**
	 * A widget configured before the upgrade still works.
	 *
	 * The class was renamed, but the id_base is unchanged, so stored instances
	 * in widget_ratings-widget still belong to it.
	 *
	 * @return void
	 */
	public function test_a_configured_widget_still_works() {
		WP_PostRatings_Install::maybe_upgrade();

		$widget = new WP_PostRatings_Widget();

		$this->assertSame( 'ratings-widget', $widget->id_base, 'stored widget instances would be orphaned' );

		ob_start();
		$widget->widget(
			array(
				'before_widget' => '<aside>',
				'after_widget'  => '</aside>',
				'before_title'  => '<h2>',
				'after_title'   => '</h2>',
			),
			array(
				'title' => 'Top',
				'type'  => 'highest_rated',
			)
		);
		$html = ob_get_clean();

		$this->assertStringContainsString( '<aside>', $html, 'A widget configured before the upgrade still renders.' );
	}

	// --- the window before an admin page is loaded -------------------------

	/**
	 * The front end shows the site's settings without waiting for wp-admin.
	 *
	 * Plugin updates usually happen in wp-admin, so admin_init fires moments
	 * later and the migration runs. An automatic background update does not:
	 * it runs on cron, which is not an admin request. If migrating only ever
	 * happened on admin_init, such a site would serve its front end with the
	 * default five stars and the default templates -- its real settings still
	 * sitting in the old rows -- until somebody happened to log in.
	 *
	 * @return void
	 */
	public function test_the_front_end_migrates_without_an_admin_request() {
		// Precisely the state after a background update: new code, old rows,
		// and no admin request has happened.
		$this->assertFalse( is_admin(), 'this test has to run as a front end request' );

		// What the plugin does on a normal front end page load.
		$this->fire_init();

		$this->assertSame(
			"Rate: %RATINGS_IMAGES_VOTE% O'Brien",
			WP_PostRatings_Options::template( 'vote' ),
			'the front end served default templates instead of the site\'s own'
		);

		$this->assertSame( 'star', WP_PostRatings_Options::get( 'shape' ), 'A front end request migrates the shape.' );
		$this->assertSame( '1', WP_PostRatings_Options::get( 'allowtorate' ), 'And the rest of the settings, with no admin request needed.' );
	}

	/**
	 * Migrating is cheap enough to attempt on every request.
	 *
	 * It is gated on one autoloaded option, so an already-migrated install
	 * pays a single array lookup and writes nothing.
	 *
	 * @return void
	 */
	public function test_an_already_migrated_install_writes_nothing() {
		WP_PostRatings_Install::maybe_upgrade();

		$before = WP_PostRatings_Options::get();
		$writes = 0;

		add_filter(
			'pre_update_option_' . WP_PostRatings_Options::OPTION,
			static function ( $value ) use ( &$writes ) {
				++$writes;
				return $value;
			}
		);

		$this->fire_init();
		WP_PostRatings_Install::maybe_upgrade();

		$this->assertSame( 0, $writes, 'the migration rewrote an already-migrated install' );
		$this->assertSame( $before, WP_PostRatings_Options::get(), 'An already migrated install writes nothing.' );
	}

	/**
	 * A second request does not upgrade while another one holds the lock.
	 *
	 * Everything install() does is a check on the state it is about to change,
	 * and it runs on every front-end request until the markers move:
	 * maybe_add_indexes() reads SHOW INDEX and then ALTERs. Two requests
	 * between the read and the ALTER both issue it.
	 */
	public function test_a_request_does_not_upgrade_while_another_holds_the_lock() {
		add_option( WP_PostRatings_Install::UPGRADE_LOCK, time(), '', false );

		WP_PostRatings_Install::maybe_upgrade();

		$this->assertNotSame(
			WP_POSTRATINGS_VERSION,
			WP_PostRatings_Options::markers()['plugin'],
			'the upgrade ran while another request held the lock'
		);
	}

	/**
	 * A lock left behind by a request that died is taken back.
	 *
	 * Otherwise one fatal during the upgrade would stop every later request
	 * from ever finishing it, and the site would stay behind for good.
	 */
	public function test_a_lock_left_by_a_dead_request_is_taken_back() {
		add_option( WP_PostRatings_Install::UPGRADE_LOCK, time() - ( WP_PostRatings_Install::UPGRADE_LOCK_TIMEOUT + 1 ), '', false );

		WP_PostRatings_Install::maybe_upgrade();

		$this->assertSame(
			WP_POSTRATINGS_VERSION,
			WP_PostRatings_Options::markers()['plugin'],
			'an abandoned lock is believed rather than stolen'
		);
		$this->assertFalse( get_option( WP_PostRatings_Install::UPGRADE_LOCK, false ), 'and the lock is released afterwards' );
	}

	/**
	 * The lock is released whichever way the upgrade leaves.
	 *
	 * A lock held past the end of the request that took it is a lock nothing
	 * releases for five minutes, so every request in that window skips an
	 * upgrade it should have run.
	 */
	public function test_the_upgrade_lock_is_never_left_held() {
		WP_PostRatings_Install::maybe_upgrade();

		$this->assertFalse( get_option( WP_PostRatings_Install::UPGRADE_LOCK, false ), 'the lock survived a completed upgrade' );

		// And again on the path where nothing is owed at all.
		WP_PostRatings_Install::maybe_upgrade();

		$this->assertFalse( get_option( WP_PostRatings_Install::UPGRADE_LOCK, false ), 'the lock survived a no-op run' );
	}

	/**
	 * Uninstall takes the lock row with it.
	 */
	public function test_uninstall_removes_the_upgrade_lock() {
		$this->assertContains(
			WP_PostRatings_Install::UPGRADE_LOCK,
			WP_PostRatings_Options::all_option_names(),
			'the lock is not on the list uninstall deletes'
		);
	}
}
