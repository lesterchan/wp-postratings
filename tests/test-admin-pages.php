<?php
/**
 * Tests for the admin screens.
 *
 * These were the least covered part of the plugin and the place the rendering
 * bugs lived. Every *view* is covered, not merely every screen: one screen
 * carries several, each building markup the default view never touches.
 *
 * @package WP-PostRatings
 */

/**
 * The settings screen, the log screen and the widget form.
 *
 * @covers WP_PostRatings_Settings::render
 * @covers WP_PostRatings_Admin::render_manage
 * @covers WP_PostRatings_Widget
 */
class WP_PostRatings_Admin_Pages_Test extends WP_PostRatings_TestCase {

	/**
	 * Always be a user with the capability: these screens wp_die() otherwise,
	 * which takes the runner with it.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );
		wp_get_current_user()->add_cap( WP_PostRatings_Settings::capability() );

		require_once ABSPATH . 'wp-admin/includes/admin.php';
		set_current_screen( 'toplevel_page_' . WP_PostRatings_Admin::PAGE );

		// admin_init never runs in the suite, and do_settings_sections() draws
		// nothing at all without the sections and fields it registers.
		WP_PostRatings_Settings::register();
	}

	/**
	 * Every view of the settings screen renders clean.
	 *
	 * @dataProvider settings_views
	 *
	 * @param array $get Query arguments for the view.
	 *
	 * @return void
	 */
	public function test_the_settings_screen_renders_clean( $get ) {
		$this->make_rated_post();

		$html = $this->render_admin_screen( array( 'WP_PostRatings_Settings', 'render' ), $get );

		$this->assertAdminScreenClean( $html );
		$this->assertStringContainsString( 'Ratings Settings', $html );
	}

	/**
	 * The tabs on the settings screen.
	 *
	 * @return array
	 */
	public function settings_views() {
		return array(
			'default tab'   => array( array() ),
			'settings tab'  => array( array( 'tab' => 'settings' ) ),
			'templates tab' => array( array( 'tab' => 'templates' ) ),
			'unknown tab'   => array( array( 'tab' => 'nonsense' ) ),
		);
	}

	/**
	 * Every view of the log screen renders clean.
	 *
	 * @dataProvider log_views
	 *
	 * @param array $get Query arguments for the view.
	 *
	 * @return void
	 */
	public function test_the_log_screen_renders_clean( $get ) {
		$post_id = $this->make_rated_post();
		$this->log_rating( $post_id, 4, "O'Brien & <em>co</em>" );
		$this->log_rating( $post_id, 2, 'Guest' );

		$html = $this->render_admin_screen( array( 'WP_PostRatings_Admin', 'render_manage' ), $get );

		$this->assertAdminScreenClean( $html );
		$this->assertStringContainsString( 'Manage Ratings', $html );
	}

	/**
	 * The views of the log screen.
	 *
	 * @return array
	 */
	public function log_views() {
		return array(
			'default'             => array( array() ),
			'sorted ascending'    => array(
				array(
					'orderby' => 'rating_username',
					'order'   => 'asc',
				),
			),
			'filtered by rating'  => array( array( 'rating' => '4' ) ),
			'searched'            => array( array( 's' => 'Brien' ) ),
			'second page'         => array( array( 'paged' => '2' ) ),
			'unknown sort column' => array( array( 'orderby' => 'evil; DROP TABLE' ) ),
		);
	}

	/**
	 * A username carrying markup is escaped in the log.
	 *
	 * @return void
	 */
	public function test_a_markup_username_is_escaped() {
		$post_id = $this->make_rated_post();
		$this->log_rating( $post_id, 4, '<script>alert(1)</script>' );

		$html = $this->render_admin_screen( array( 'WP_PostRatings_Admin', 'render_manage' ) );

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	/**
	 * An unknown sort column falls back rather than reaching the query.
	 *
	 * @return void
	 */
	public function test_an_unknown_sort_column_is_ignored() {
		$post_id = $this->make_rated_post();
		$this->log_rating( $post_id );

		$html = $this->render_admin_screen(
			array( 'WP_PostRatings_Admin', 'render_manage' ),
			array( 'orderby' => 'rating_id; DROP TABLE wp_ratings' )
		);

		$this->assertAdminScreenClean( $html );
		$this->assertStringContainsString( 'Manage Ratings', $html );
	}

	/**
	 * The settings screen never offers a shape the sanitizer rejects.
	 *
	 * @return void
	 */
	public function test_the_screen_only_offers_valid_shapes() {
		$html = $this->render_admin_screen( array( 'WP_PostRatings_Settings', 'render' ) );

		preg_match_all( '/name="wp_postratings_options\[shape\]" value="([^"]+)"/', $html, $matches );

		$this->assertNotEmpty( $matches[1], 'the screen offered no shapes at all' );

		foreach ( $matches[1] as $offered ) {
			$clean = WP_PostRatings_Options::sanitize( array( 'shape' => $offered ) );
			$this->assertSame( $offered, $clean['shape'], $offered . ' is offered but would not save' );
		}
	}

	/**
	 * The widget form renders clean for an unconfigured widget.
	 *
	 * @return void
	 */
	public function test_the_widget_form_renders_clean() {
		$widget = new WP_PostRatings_Widget();

		$html = $this->render_admin_screen(
			static function () use ( $widget ) {
				$widget->form( array() );
			}
		);

		$this->assertSame( array(), $this->admin_page_notices );
		$this->assertStringContainsString( 'Statistics Type:', $html );
	}

	/**
	 * The widget renders every list type without a diagnostic.
	 *
	 * @return void
	 */
	public function test_the_widget_renders_every_type() {
		$this->make_rated_post( 4, 18 );

		$widget = new WP_PostRatings_Widget();

		$args = array(
			'before_widget' => '<aside>',
			'after_widget'  => '</aside>',
			'before_title'  => '<h2>',
			'after_title'   => '</h2>',
		);

		$types = array(
			'most_rated',
			'most_rated_category',
			'most_rated_range',
			'most_rated_range_category',
			'highest_rated',
			'highest_rated_category',
			'highest_rated_range',
			'highest_rated_range_category',
			'lowest_rated',
			'lowest_rated_category',
			'lowest_rated_range',
			'highest_score',
			'highest_score_category',
			'highest_score_range',
			'highest_score_range_category',
		);

		foreach ( $types as $type ) {
			$html = $this->render_admin_screen(
				static function () use ( $widget, $args, $type ) {
					$widget->widget(
						$args,
						array(
							'title'      => 'Ratings',
							'type'       => $type,
							'cat_ids'    => (string) get_option( 'default_category' ),
							'time_range' => '10 years',
						)
					);
				}
			);

			$this->assertSame( array(), $this->admin_page_notices, $type . ' raised a diagnostic' );
			$this->assertStringContainsString( '<aside>', $html, $type . ' rendered nothing' );
		}
	}

	/**
	 * An unconfigured widget does not warn per missing key.
	 *
	 * @return void
	 */
	public function test_an_unconfigured_widget_does_not_warn() {
		$widget = new WP_PostRatings_Widget();

		$this->render_admin_screen(
			static function () use ( $widget ) {
				$widget->widget(
					array(
						'before_widget' => '',
						'after_widget'  => '',
						'before_title'  => '',
						'after_title'   => '',
					),
					array()
				);
			}
		);

		$this->assertSame( array(), $this->admin_page_notices );
	}

	/**
	 * Saving works without the old hidden "submit" field.
	 *
	 * The old form carried a hidden "submit" field and returned false without
	 * it, which blocked Customizer saves.
	 *
	 * @return void
	 */
	public function test_the_widget_saves_without_a_submit_field() {
		$widget = new WP_PostRatings_Widget();

		$instance = $widget->update(
			array(
				'title'   => 'Top posts',
				'type'    => 'most_rated',
				'limit'   => '7',
				'cat_ids' => '3, 4, nonsense',
			),
			array()
		);

		$this->assertIsArray( $instance, 'A save without the submit marker still returns an instance rather than false.' );
		$this->assertSame( 'Top posts', $instance['title'] );
		$this->assertSame( 'most_rated', $instance['type'] );
		$this->assertSame( 7, $instance['limit'] );
		// wp_parse_id_list() maps 'nonsense' to 0, which would filter on a
		// category that cannot exist.
		$this->assertSame( '3,4', $instance['cat_ids'] );
	}

	/**
	 * An unknown list type falls back rather than fataling on a missing key.
	 *
	 * @return void
	 */
	public function test_an_unknown_widget_type_falls_back() {
		$widget = new WP_PostRatings_Widget();

		$instance = $widget->update( array( 'type' => 'nonsense' ), array() );

		$this->assertSame( 'highest_rated', $instance['type'] );
	}
	/**
	 * Settings comes first and the log second, both under one menu.
	 *
	 * The reverse of the arrangement everywhere else in this collection, and
	 * deliberately so: the log records who rated what, which is for spotting
	 * abuse, while the settings are what a site owner actually opens. Asserted
	 * because menu order is registration order, and registration order is the
	 * kind of thing an edit reshuffles without anyone noticing.
	 */
	public function test_settings_comes_before_the_log() {
		global $menu, $submenu;

		$menu    = array();
		$submenu = array();

		// add_submenu_page() returns false and registers nothing when the current
		// user lacks the capability, so the entries this asserts on only exist for
		// somebody who may see them. The capability is the plugin's own.
		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		get_role( 'administrator' )->add_cap( WP_PostRatings_Settings::CAPABILITY );
		wp_set_current_user( $user );

		// menu() is called directly, not through do_action( 'admin_menu' ):
		// WP_PostRatings_Admin::init() is behind is_admin(), which is false under
		// PHPUnit, so the action has nothing hooked to it here.
		WP_PostRatings_Admin::menu();

		$parent = WP_PostRatings_Settings::page();

		$this->assertArrayHasKey( $parent, $submenu, 'the plugin registered no submenu under its settings page' );

		$slugs = wp_list_pluck( $submenu[ $parent ], 2 );

		$this->assertSame(
			array( $parent, WP_PostRatings_Admin::PAGE ),
			array_values( $slugs ),
			'Settings must come first and the log second'
		);

		$labels = wp_list_pluck( $submenu[ $parent ], 0 );

		$this->assertSame( 'Settings', $labels[0], 'the first entry is not Settings' );
		$this->assertSame( 'Logs', $labels[1], 'the log is not called Logs' );
	}
	/**
	 * The assets load on the screens the menu actually registered.
	 *
	 * This has silently broken twice: once when the menu was renamed, and once
	 * when Settings became the top-level page. Both times screen_hooks() went on
	 * describing a menu that no longer existed, the stylesheet stopped being
	 * enqueued, and -- because every rating shape is a CSS mask from that
	 * stylesheet -- the shape picker rendered as a list of labels. Nothing
	 * errored either time.
	 *
	 * So this does not assert the strings. It registers the menu, takes the
	 * hooks WordPress reports for the plugin's own pages, and requires
	 * screen_hooks() to be exactly those.
	 */
	public function test_the_assets_load_on_the_screens_the_menu_registered() {
		global $menu, $submenu;

		$menu    = array();
		$submenu = array();

		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		get_role( 'administrator' )->add_cap( WP_PostRatings_Settings::CAPABILITY );
		wp_set_current_user( $user );

		WP_PostRatings_Admin::menu();

		$parent   = WP_PostRatings_Settings::page();
		$expected = array( get_plugin_page_hookname( $parent, '' ) );

		foreach ( wp_list_pluck( $submenu[ $parent ], 2 ) as $slug ) {
			$expected[] = get_plugin_page_hookname( $slug, $parent );
		}

		$expected = array_values( array_unique( $expected ) );
		$actual   = WP_PostRatings_Admin::screen_hooks();

		sort( $expected );
		sort( $actual );

		$this->assertSame( $expected, $actual, 'screen_hooks() describes a menu that is not the one registered' );
	}
}
