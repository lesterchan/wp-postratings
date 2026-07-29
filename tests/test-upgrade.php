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

		$this->assertSame( '5', $options['max'] );
		$this->assertSame( '1', $options['allowtorate'] );
		$this->assertSame( '2', $options['logging_method'] );
		$this->assertSame( 'HTTP_CF_CONNECTING_IP', $options['ip_header'] );
		$this->assertSame( 0, $options['richsnippet_ratings'] );
		$this->assertSame( array( 'Awful', 'Poor', 'OK', 'Good', 'Superb' ), $options['ratings']['text'] );
		$this->assertSame( array( 0, 1 ), array( (int) $options['ajax_style']['loading'], (int) $options['ajax_style']['fading'] ) );
	}

	/**
	 * The chosen image set becomes the equivalent shape.
	 *
	 * @return void
	 */
	public function test_the_chosen_style_carries_over() {
		WP_PostRatings_Install::maybe_upgrade();

		$this->assertSame( 'star', WP_PostRatings_Options::get( 'image' ) );
	}

	/**
	 * Customised templates survive, unslashed exactly once.
	 *
	 * @return void
	 */
	public function test_customised_templates_survive() {
		WP_PostRatings_Install::maybe_upgrade();

		$this->assertSame( "Rate: %RATINGS_IMAGES_VOTE% O'Brien", WP_PostRatings_Options::template( 'vote' ) );
		$this->assertSame( 'Voted: %RATINGS_IMAGES% %RATINGS_AVERAGE%', WP_PostRatings_Options::template( 'text' ) );
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

		$this->assertSame( 4, (int) get_post_meta( $this->post_id, 'ratings_users', true ) );
		$this->assertSame( 18, (int) get_post_meta( $this->post_id, 'ratings_score', true ) );
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

		$this->assertSame( wp_hash( '203.0.113.7' ), $row->rating_ip );
		$this->assertSame( 4, (int) $row->rating_rating );
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

		$this->assertTrue( WP_PostRatings_Rating::has_rated( $this->post_id ) );
	}

	// --- the front end keeps working ---------------------------------------

	/**
	 * The template tags still render, with the site's own templates.
	 *
	 * @return void
	 */
	public function test_the_front_end_uses_the_sites_own_templates() {
		WP_PostRatings_Install::maybe_upgrade();

		$this->assertStringContainsString( "O'Brien", the_ratings_vote( $this->post_id ) );
		$this->assertStringContainsString( 'Voted:', the_ratings_results( $this->post_id ) );
	}

	/**
	 * The per-value labels the site configured are used.
	 *
	 * @return void
	 */
	public function test_the_configured_labels_are_used() {
		WP_PostRatings_Install::maybe_upgrade();

		$this->assertStringContainsString( 'Superb', the_ratings_vote( $this->post_id ) );
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

		$this->assertStringContainsString( '<aside>', $html );
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
		do_action( 'init' );

		$this->assertSame(
			"Rate: %RATINGS_IMAGES_VOTE% O'Brien",
			WP_PostRatings_Options::template( 'vote' ),
			'the front end served default templates instead of the site\'s own'
		);

		$this->assertSame( 'star', WP_PostRatings_Options::get( 'image' ) );
		$this->assertSame( '1', WP_PostRatings_Options::get( 'allowtorate' ) );
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

		do_action( 'init' );
		WP_PostRatings_Install::maybe_upgrade();

		$this->assertSame( 0, $writes, 'the migration rewrote an already-migrated install' );
		$this->assertSame( $before, WP_PostRatings_Options::get() );
	}
}
