<?php
/**
 * WP-PostRatings class-wp-postratings.php
 *
 * @package WP-PostRatings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots the plugin and owns the front end hooks.
 *
 * @since 2.0.0
 */
class WP_PostRatings {

	/**
	 * Singleton instance.
	 *
	 * @var WP_PostRatings|null
	 */
	private static $instance = null;

	/**
	 * Get the instance, creating it on first call.
	 *
	 * @return WP_PostRatings
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
		register_activation_hook( WP_POSTRATINGS_MAIN_FILE, array( 'WP_PostRatings_Install', 'activate' ) );

		// Deliberately on init rather than admin_init. Activation does not fire
		// on a plugin *update*, and an automatic background update runs on
		// cron, which is not an admin request: hooking this to the admin only
		// would leave such a site serving its front end with default settings
		// -- default templates, default scale -- while its real ones sat in the
		// old option rows, until somebody happened to log in. Gated on one
		// autoloaded option, so an already-migrated install pays a lookup.
		add_action( 'init', array( 'WP_PostRatings_Install', 'maybe_upgrade' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );
		add_action( 'enqueue_block_assets', array( $this, 'block_editor_styles' ) );

		add_shortcode( 'ratings', array( $this, 'shortcode' ) );

		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'pre_get_posts', array( $this, 'sorting' ) );

		add_action( 'publish_post', array( $this, 'add_meta' ) );
		add_action( 'publish_page', array( $this, 'add_meta' ) );
		add_action( 'delete_post', array( $this, 'delete_meta' ) );

		add_action( 'widgets_init', array( $this, 'register_widget' ) );

		WP_PostRatings_Rating::init();
		WP_PostRatings_Comments::init();
		WP_PostRatings_Blocks::init();

		add_action( 'plugins_loaded', array( 'WP_PostRatings_WPStats', 'init' ) );

		new WP_PostRatings_API();

		self::register_command();

		if ( is_admin() ) {
			WP_PostRatings_Admin::init();
			WP_PostRatings_Settings::init();

			add_filter( 'set-screen-option', array( 'WP_PostRatings_Admin', 'set_screen_option' ), 10, 3 );
		}
	}

	/**
	 * Register the WP-CLI command.
	 *
	 * The class file is required here rather than at plugin load because it
	 * extends WP_CLI_Command, which only exists when WP-CLI is the one running
	 * WordPress. Requiring it unconditionally is a fatal error on every web
	 * request.
	 *
	 * @return void
	 */
	public static function register_command() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		require_once WP_POSTRATINGS_DIR . 'includes/class-wp-postratings-command.php';

		WP_CLI::add_command( 'postratings', 'WP_PostRatings_Command' );
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
	 * Register the widget.
	 *
	 * @return void
	 */
	public function register_widget() {
		register_widget( 'WP_PostRatings_Widget' );
	}

	/**
	 * Enqueue the front end assets.
	 *
	 * @return void
	 */
	public function scripts() {
		$this->styles();

		// The empty dependency array is the point: the script is vanilla
		// ES2017 and asks for nothing since 2.0.0.
		wp_enqueue_script(
			'wp-postratings',
			WP_POSTRATINGS_URL . 'js/wp-postratings.js',
			array(),
			WP_POSTRATINGS_VERSION,
			true
		);

		wp_localize_script(
			'wp-postratings',
			'wpPostRatingsL10n',
			array(
				// Much smaller than before 2.0.0: hovering is CSS now, so the
				// script no longer needs the image set, the extension, the
				// scale or the plugin URL to rewrite <img> sources with.
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'textWait' => __( 'Please rate only 1 item at a time.', 'wp-postratings' ),
			)
		);
	}

	/**
	 * Enqueue the front end stylesheet.
	 *
	 * Split out of scripts() so the block editor can have the styles without
	 * the script.
	 *
	 * No separate RTL stylesheet since 2.0.0: the rules use logical
	 * properties, so direction is handled by the browser.
	 *
	 * @return void
	 */
	public function styles() {
		wp_enqueue_style( 'wp-postratings', $this->stylesheet_url( 'wp-postratings.css' ), array(), WP_POSTRATINGS_VERSION );
	}

	/**
	 * Enqueue the stylesheet for the block editor's preview.
	 *
	 * Every rating shape is a CSS mask declared in that stylesheet, so an
	 * editor without it previews the block as a bare row of radio buttons --
	 * technically the right markup and visibly not the rating the post will
	 * show.
	 *
	 * The styles only, never the script: the front end script casts the vote,
	 * and a preview that can be rated in records real ratings from the editor.
	 * The block wraps its preview in `inert` for the same reason.
	 *
	 * Guarded on is_admin() because `enqueue_block_assets` fires on the front
	 * end too, where scripts() has already done this on `wp_enqueue_scripts`.
	 *
	 * @return void
	 */
	public function block_editor_styles() {
		if ( ! is_admin() ) {
			return;
		}

		$this->styles();
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
	 * A thin wrapper over render_ratings(): everything but the attribute
	 * parsing is shared with the block, which calls the same method. The
	 * shortcode is not going anywhere -- it is what fifteen years of published
	 * posts contain -- and the block is an addition beside it.
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

		// Passed through as it arrived rather than normalised: the shortcode
		// has always treated any value at all as "yes", and tightening that
		// here would change what `results="0"` renders on pages nobody is
		// about to re-edit. The block's attribute is a real boolean and the
		// shared renderer only asks whether it is truthy, so the two agree
		// wherever a block can express the value at all.
		return self::render_ratings( $attributes['id'], $attributes['results'] );
	}

	/**
	 * Render a post's rating, for whichever entry point asked.
	 *
	 * The one renderer behind the `[ratings]` shortcode and the
	 * `wp-postratings/ratings` block. Neither of those calls the other: the
	 * block does not run do_shortcode() and the shortcode does not ask the
	 * block class for anything, so unregistering either leaves the other
	 * working, and the markup is decided in one place so they cannot drift.
	 *
	 * The feed and AMP guard lives here rather than in the shortcode precisely
	 * so the block inherits it: a dynamic block renders in a feed too, and a
	 * rating control in a feed reader votes nowhere.
	 *
	 * @param int         $post_id Post to rate, or 0 for the post being rendered.
	 * @param bool|string $results Whether to show the result instead of the
	 *                             control. A string where a shortcode supplied
	 *                             it, and only its truthiness is read.
	 *
	 * @return string
	 */
	public static function render_ratings( $post_id = 0, $results = false ) {
		if ( is_feed() || ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) ) {
			return esc_html__( 'Note: There is a rating embedded within this post, please visit this post to rate it.', 'wp-postratings' );
		}

		$post_id = (int) $post_id;

		if ( $results ) {
			return the_ratings_results( $post_id );
		}

		return the_ratings( 'span', $post_id, false );
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

		$options  = WP_PostRatings_Options::get();
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
