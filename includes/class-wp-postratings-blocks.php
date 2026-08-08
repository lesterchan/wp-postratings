<?php
/**
 * WP-PostRatings class-wp-postratings-blocks.php
 *
 * @package WP-PostRatings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin's blocks and renders them.
 *
 * **The block is added beside the shortcode, never in place of it.**
 * `[ratings]` stays registered, documented and supported, and nothing here
 * deprecates it. The shortcode sits in an unknowable number of published posts
 * on sites nobody can survey, so a block that replaced it would break every one
 * of those pages on update.
 *
 * The block is dynamic. Its `save` returns null in JavaScript, so what lands in
 * post_content is the block comment and its attributes and no markup at all --
 * every view re-renders through the callback below. That is what lets a block
 * and a shortcode share one renderer: the markup is decided once, at render
 * time, for both of them, so a site that changes its shape or its templates
 * sees the change in posts it never re-saved.
 *
 * **Neither entry point calls the other.** The block does not run
 * `do_shortcode()` and the shortcode does not ask this class for anything. They
 * are siblings over `WP_PostRatings::render_ratings()`, which is where the feed
 * guard and the results-or-control choice live. Routing the block through
 * `do_shortcode()` would make it inherit the shortcode's attribute parsing, and
 * would break the block outright the day anybody unregistered the shortcode.
 *
 * @since 2.0.0
 */
class WP_PostRatings_Blocks {

	/**
	 * Blocks this plugin registers, as build directory => render callback.
	 *
	 * One entry today, and a list anyway: the name, title, attributes and
	 * script handle all come out of the `block.json` the build copies into
	 * `build/<directory>/`, which is what
	 * `register_block_type_from_metadata()` reads, so this class never restates
	 * any of them and a second block is a line here.
	 *
	 * @return array<string, callable>
	 */
	private static function blocks() {
		return array(
			'ratings' => array( __CLASS__, 'render_ratings' ),
		);
	}

	/**
	 * Hooks block registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Registers every block against its built metadata.
	 *
	 * A missing `build/` directory means the plugin was installed without its
	 * build step having run -- a git checkout rather than a release zip, since
	 * `bin/build` runs before the deploy copies anything. Registering a block
	 * whose script cannot be enqueued gives an editor that silently fails to
	 * load it, so this returns instead and leaves the shortcode working.
	 *
	 * @return void
	 */
	public static function register() {
		foreach ( self::blocks() as $directory => $callback ) {
			$metadata = WP_POSTRATINGS_DIR . 'build/' . $directory;

			if ( ! file_exists( $metadata . '/block.json' ) ) {
				continue;
			}

			register_block_type_from_metadata(
				$metadata,
				array( 'render_callback' => $callback )
			);
		}
	}

	/**
	 * Renders the `wp-postratings/ratings` block.
	 *
	 * The attributes arrive typed, because block.json declares them: `id` is
	 * already an integer and `results` a real boolean, where a shortcode's are
	 * whatever the author typed. The shared renderer casts the one and reads
	 * the truthiness of the other, so the two entry points cannot disagree
	 * about what `id="0"` means.
	 *
	 * @param array $attributes Block attributes.
	 *
	 * @return string
	 */
	public static function render_ratings( $attributes ) {
		$attributes = wp_parse_args(
			$attributes,
			array(
				'id'      => 0,
				'results' => false,
			)
		);

		return WP_PostRatings::render_ratings( $attributes['id'], $attributes['results'] );
	}
}
