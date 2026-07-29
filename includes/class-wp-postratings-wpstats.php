<?php
/**
 * WP-PostRatings class-wp-postratings-wpstats.php
 *
 * @package wp-postratings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contributes this plugin's section to the WP-Stats page.
 *
 * Before 2.0.0 the two plugins met in a bare `stats_display` option row that six
 * plugins wrote to at once, and WP-Stats had to know the names of every panel
 * its siblings owned. Now WP-Stats asks -- it fires wp_stats_sections and each
 * plugin answers for itself -- so WP-Stats cannot render a section for a plugin
 * that is not installed, and this class decides whether to appear at all by
 * reading nothing but its own settings.
 *
 * Loaded unconditionally and inert without WP-Stats: nothing fires the filter,
 * so nothing here runs. There is no class_exists() probing between plugins.
 *
 * @since 2.0.0
 */
class WP_PostRatings_WPStats {

	/**
	 * Offer the section to WP-Stats.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'wp_stats_sections', array( __CLASS__, 'register_section' ) );
	}

	/**
	 * Add this plugin's entry to the section list.
	 *
	 * @param array $sections Sections keyed by plugin slug with underscores.
	 *
	 * @return array
	 */
	public static function register_section( $sections ) {
		$sections = (array) $sections;

		if ( ! WP_PostRatings_Options::get( 'stats_display' ) ) {
			// Opted out: contribute nothing, rather than an entry with an empty
			// body that WP-Stats would still draw a heading for.
			return $sections;
		}

		$sections['wp_postratings'] = array(
			'title'    => __( 'Ratings', 'wp-postratings' ),
			'priority' => 10,
			'render'   => array( __CLASS__, 'render' ),
		);

		return $sections;
	}

	/**
	 * Echo the section body.
	 *
	 * Takes no arguments and returns nothing, per the contract: WP-Stats has
	 * echoed the heading by the time this runs.
	 *
	 * @return void
	 */
	public static function render() {
		$limit = self::most_limit();
		$users = (int) get_ratings_users( false );

		echo '<ul>' . "\n";
		printf(
			'<li><strong>%1$s</strong> %2$s</li>' . "\n",
			esc_html( number_format_i18n( $users ) ),
			esc_html( _n( 'user casted his vote.', 'users casted their vote.', $users, 'wp-postratings' ) )
		);
		echo '</ul>' . "\n";

		self::render_list(
			/* translators: %s: number of posts. */
			sprintf( _n( '%s Highest Rated Post', '%s Highest Rated Posts', $limit, 'wp-postratings' ), number_format_i18n( $limit ) ),
			get_highest_rated( 'post', 0, $limit, 0, false )
		);

		self::render_list(
			/* translators: %s: number of posts. */
			sprintf( _n( '%s Most Rated Post', '%s Most Rated Posts', $limit, 'wp-postratings' ), number_format_i18n( $limit ) ),
			get_most_rated( 'post', 0, $limit, 0, false )
		);
	}

	/**
	 * One titled list inside the section.
	 *
	 * @param string $heading Heading for the list.
	 * @param string $items   Rendered <li> markup.
	 *
	 * @return void
	 */
	private static function render_list( $heading, $items ) {
		printf( '<p><strong>%s</strong></p>' . "\n", esc_html( $heading ) );
		echo '<ul>' . "\n";
		echo wp_kses_post( $items );
		echo '</ul>' . "\n";
	}

	/**
	 * How many entries the "highest" and "most" lists show.
	 *
	 * @return int
	 */
	public static function most_limit() {
		return max( 1, (int) WP_PostRatings_Options::get( 'stats_most_limit' ) );
	}
}
