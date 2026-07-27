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
 * @covers Postratings_Settings::render
 * @covers Postratings_Admin::render_manage
 * @covers Postratings_Widget
 */
class Test_Postratings_Admin_Pages extends WP_PostRatings_TestCase {

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
		wp_get_current_user()->add_cap( Postratings_Installer::CAPABILITY );

		require_once ABSPATH . 'wp-admin/includes/admin.php';
		set_current_screen( 'toplevel_page_' . Postratings_Admin::PAGE_MANAGE );
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

		$html = $this->render_admin_screen( array( 'Postratings_Settings', 'render' ), $get );

		$this->assertAdminScreenClean( $html );
		$this->assertStringContainsString( 'Post Ratings Options', $html );
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

		$html = $this->render_admin_screen( array( 'Postratings_Admin', 'render_manage' ), $get );

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

		$html = $this->render_admin_screen( array( 'Postratings_Admin', 'render_manage' ) );

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
			array( 'Postratings_Admin', 'render_manage' ),
			array( 'orderby' => 'rating_id; DROP TABLE wp_ratings' )
		);

		$this->assertAdminScreenClean( $html );
		$this->assertStringContainsString( 'Manage Ratings', $html );
	}

	/**
	 * The settings screen never offers an image set the sanitizer rejects.
	 *
	 * @return void
	 */
	public function test_the_screen_only_offers_valid_image_sets() {
		$html = $this->render_admin_screen( array( 'Postratings_Settings', 'render' ) );

		preg_match_all( '/name="postratings_options\[image\]" value="([^"]+)"/', $html, $matches );

		$this->assertNotEmpty( $matches[1], 'the screen offered no image sets at all' );

		foreach ( $matches[1] as $offered ) {
			$clean = Postratings_Options::sanitize( array( 'image' => $offered ) );
			$this->assertSame( $offered, $clean['image'], $offered . ' is offered but would not save' );
		}
	}

	/**
	 * The widget form renders clean for an unconfigured widget.
	 *
	 * @return void
	 */
	public function test_the_widget_form_renders_clean() {
		$widget = new Postratings_Widget();

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

		$widget = new Postratings_Widget();

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
		$widget = new Postratings_Widget();

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
		$widget = new Postratings_Widget();

		$instance = $widget->update(
			array(
				'title'   => 'Top posts',
				'type'    => 'most_rated',
				'limit'   => '7',
				'cat_ids' => '3, 4, nonsense',
			),
			array()
		);

		$this->assertIsArray( $instance );
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
		$widget = new Postratings_Widget();

		$instance = $widget->update( array( 'type' => 'nonsense' ), array() );

		$this->assertSame( 'highest_rated', $instance['type'] );
	}
}
