<?php
/**
 * WP-PostRatings class-postratings.php
 *
 * @package wp-postratings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots the plugin and owns the front end hooks.
 *
 * @since 2.0.0
 */
class Postratings {

	/**
	 * Singleton instance.
	 *
	 * @var Postratings|null
	 */
	private static $instance = null;

	/**
	 * Get the instance, creating it on first call.
	 *
	 * @return Postratings
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register the table, the hooks and the activation hook.
	 */
	private function __construct() {
		$this->register_table();

		// register_activation_hook() has to run at file load time, which is
		// where WordPress requires it.
		register_activation_hook( WP_POSTRATINGS_MAIN_FILE, array( 'Postratings_Installer', 'activate' ) );

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );

		add_shortcode( 'ratings', array( $this, 'shortcode' ) );

		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'pre_get_posts', array( $this, 'sorting' ) );

		add_action( 'publish_post', array( $this, 'add_meta' ) );
		add_action( 'publish_page', array( $this, 'add_meta' ) );
		add_action( 'delete_post', array( $this, 'delete_meta' ) );

		add_action( 'widgets_init', array( $this, 'register_widget' ) );

		Postratings_Rating::init();
		Postratings_Comments::init();

		add_action( 'plugins_loaded', array( 'Postratings_WPStats', 'init' ) );

		if ( is_admin() ) {
			Postratings_Admin::init();
			Postratings_Settings::init();

			add_filter( 'set-screen-option', array( 'Postratings_Admin', 'set_screen_option' ), 10, 3 );
		}
	}

	/**
	 * Register the log table with $wpdb.
	 *
	 * The tables[] entry is what keeps the name correct across
	 * switch_to_blog(); assigning $wpdb->ratings alone is not multisite safe.
	 *
	 * @return void
	 */
	private function register_table() {
		global $wpdb;

		$wpdb->tables[] = 'ratings';
		$wpdb->ratings  = $wpdb->prefix . 'ratings';
	}

	/**
	 * Late setup that needs the rest of WordPress loaded.
	 *
	 * @return void
	 */
	public function init() {
		// RATINGS_IMG_EXT and wp_postratings_image_extension are gone with the
		// image sets: the shapes are SVG masks, so there is no file extension
		// left to choose. Defined for anything still reading it.
		if ( ! defined( 'RATINGS_IMG_EXT' ) ) {
			define( 'RATINGS_IMG_EXT', 'svg' );
		}
	}

	/**
	 * Register the widget.
	 *
	 * @return void
	 */
	public function register_widget() {
		register_widget( 'Postratings_Widget' );
	}

	/**
	 * Enqueue the front end assets.
	 *
	 * @return void
	 */
	public function scripts() {
		$options = Postratings_Options::get();

		// No separate RTL stylesheet since 2.0.0: the rules use logical
		// properties, so direction is handled by the browser.
		wp_enqueue_style( 'wp-postratings', $this->stylesheet_url( 'postratings.css' ), array(), WP_POSTRATINGS_VERSION );
		wp_add_inline_style( 'wp-postratings', self::color_css() );

		// No jQuery dependency since 2.0.0.
		wp_enqueue_script(
			'wp-postratings',
			WP_POSTRATINGS_URL . 'js/postratings.js',
			array(),
			WP_POSTRATINGS_VERSION,
			true
		);

		wp_localize_script(
			'wp-postratings',
			'ratingsL10n',
			array(
				// Much smaller than before 2.0.0: hovering is CSS now, so the
				// script no longer needs the image set, the extension, the
				// scale or the plugin URL to rewrite <img> sources with.
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'textWait'    => __( 'Please rate only 1 item at a time.', 'wp-postratings' ),
				'showLoading' => (int) $options['ajax_style']['loading'],
				'showFading'  => (int) $options['ajax_style']['fading'],
			)
		);
	}

	/**
	 * The chosen colours, as a custom property declaration.
	 *
	 * Emitted once as inline CSS rather than on every rating element, so a page
	 * with fifty ratings carries one declaration rather than fifty.
	 *
	 * @return string
	 */
	public static function color_css() {
		$colors = (array) Postratings_Options::get( 'colors' );

		$on  = isset( $colors['on'] ) ? $colors['on'] : '';
		$off = isset( $colors['off'] ) ? $colors['off'] : '';

		if ( '' === $on && '' === $off ) {
			return '';
		}

		$css  = ':root{';
		$css .= '' !== $on ? '--postratings-color-on:' . $on . ';' : '';
		$css .= '' !== $off ? '--postratings-color-off:' . $off . ';' : '';

		return $css . '}';
	}

	/**
	 * URL of a stylesheet, preferring a copy shipped by the active theme.
	 *
	 * The theme may place it at the root or under css/.
	 *
	 * @param string $file Stylesheet file name.
	 *
	 * @return string
	 */
	private function stylesheet_url( $file ) {
		foreach ( array( $file, 'css/' . $file ) as $candidate ) {
			if ( file_exists( get_stylesheet_directory() . '/' . $candidate ) ) {
				return get_stylesheet_directory_uri() . '/' . $candidate;
			}
		}

		return WP_POSTRATINGS_URL . 'css/' . $file;
	}

	/**
	 * The [ratings] shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public function shortcode( $atts ) {
		$attributes = shortcode_atts(
			array(
				'id'      => 0,
				'results' => false,
			),
			$atts
		);

		if ( is_feed() || ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) ) {
			return esc_html__( 'Note: There is a rating embedded within this post, please visit this post to rate it.', 'wp-postratings' );
		}

		$id = (int) $attributes['id'];

		if ( $attributes['results'] ) {
			return the_ratings_results( $id );
		}

		return the_ratings( 'span', $id, false );
	}

	/**
	 * Expose the rating sort query vars.
	 *
	 * @param array $vars Public query vars.
	 *
	 * @return array
	 */
	public function query_vars( $vars ) {
		$vars[] = 'r_sortby';
		$vars[] = 'r_orderby';

		return $vars;
	}

	/**
	 * Apply the rating sort filters when asked for.
	 *
	 * @param WP_Query $query Query being run.
	 *
	 * @return void
	 */
	public function sorting( $query ) {
		$sortby = $query->get( 'r_sortby' );

		remove_filter( 'posts_fields', array( $this, 'most_fields' ) );
		remove_filter( 'posts_join', array( $this, 'most_join' ) );
		remove_filter( 'posts_orderby', array( $this, 'most_orderby' ) );
		remove_filter( 'posts_fields', array( $this, 'highest_fields' ) );
		remove_filter( 'posts_join', array( $this, 'highest_join' ) );
		remove_filter( 'posts_orderby', array( $this, 'highest_orderby' ) );

		if ( 'most_rated' === $sortby ) {
			add_filter( 'posts_fields', array( $this, 'most_fields' ) );
			add_filter( 'posts_join', array( $this, 'most_join' ) );
			add_filter( 'posts_orderby', array( $this, 'most_orderby' ), 10, 2 );
		} elseif ( 'highest_rated' === $sortby ) {
			add_filter( 'posts_fields', array( $this, 'highest_fields' ) );
			add_filter( 'posts_join', array( $this, 'highest_join' ) );
			add_filter( 'posts_orderby', array( $this, 'highest_orderby' ), 10, 2 );
		}
	}

	/**
	 * The requested sort direction, restricted to asc or desc.
	 *
	 * @param WP_Query|null $query Query being filtered, when there is one.
	 *
	 * @return string
	 */
	private function order_direction( $query = null ) {
		// Taken from the query being filtered when there is one. get_query_var()
		// reads the *main* query, so a secondary WP_Query asking for a
		// direction had it silently ignored.
		$order = $query instanceof WP_Query
			? (string) $query->get( 'r_orderby' )
			: (string) get_query_var( 'r_orderby' );

		return 'asc' === strtolower( trim( $order ) ) ? 'asc' : 'desc';
	}

	/**
	 * Add the vote count to the selected fields.
	 *
	 * @param string $content Fields clause.
	 *
	 * @return string
	 */
	public function most_fields( $content ) {
		global $wpdb;

		return $content . ", ($wpdb->postmeta.meta_value+0) AS ratings_votes";
	}

	/**
	 * Join the vote count meta.
	 *
	 * @param string $content Join clause.
	 *
	 * @return string
	 */
	public function most_join( $content ) {
		global $wpdb;

		return $content . " LEFT JOIN $wpdb->postmeta ON $wpdb->postmeta.post_id = $wpdb->posts.ID AND $wpdb->postmeta.meta_key = 'ratings_users'";
	}

	/**
	 * Order by vote count.
	 *
	 * @param string   $orderby Order clause.
	 * @param WP_Query $query   Query being filtered.
	 *
	 * @return string
	 */
	public function most_orderby( $orderby, $query = null ) {
		$clause = ' ratings_votes ' . $this->order_direction( $query );

		return ! empty( $orderby ) ? $clause . ', ' . $orderby : $clause;
	}

	/**
	 * Add the average and vote count to the selected fields.
	 *
	 * @param string $content Fields clause.
	 *
	 * @return string
	 */
	public function highest_fields( $content ) {
		return $content . ', (t1.meta_value+0.00) AS ratings_average, (t2.meta_value+0.00) AS ratings_users';
	}

	/**
	 * Join the average and vote count meta.
	 *
	 * @param string $content Join clause.
	 *
	 * @return string
	 */
	public function highest_join( $content ) {
		global $wpdb;

		$options  = Postratings_Options::get();
		$meta_key = ( $options['customrating'] && 2 === (int) $options['max'] ) ? 'ratings_score' : 'ratings_average';

		$content .= " LEFT JOIN $wpdb->postmeta AS t1 ON t1.post_id = $wpdb->posts.ID";
		$content .= $wpdb->prepare( ' AND t1.meta_key = %s', $meta_key );
		$content .= " LEFT JOIN $wpdb->postmeta AS t2 ON t1.post_id = t2.post_id AND t2.meta_key = 'ratings_users'";

		return $content;
	}

	/**
	 * Order by average rating.
	 *
	 * @param string   $orderby Order clause.
	 * @param WP_Query $query   Query being filtered.
	 *
	 * @return string
	 */
	public function highest_orderby( $orderby, $query = null ) {
		$direction = $this->order_direction( $query );
		$clause    = ' ratings_average ' . $direction . ', ratings_users ' . $direction;

		return ! empty( $orderby ) ? $clause . ', ' . $orderby : $clause;
	}

	/**
	 * Seed the rating meta when a post is published.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return void
	 */
	public function add_meta( $post_id ) {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		add_post_meta( $post_id, 'ratings_users', 0, true );
		add_post_meta( $post_id, 'ratings_score', 0, true );
		add_post_meta( $post_id, 'ratings_average', 0, true );
	}

	/**
	 * Remove the rating meta when a post is deleted.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return void
	 */
	public function delete_meta( $post_id ) {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		delete_post_meta( $post_id, 'ratings_users' );
		delete_post_meta( $post_id, 'ratings_score' );
		delete_post_meta( $post_id, 'ratings_average' );
	}
}
