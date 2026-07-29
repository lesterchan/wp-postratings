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
 * Contributes panels to WP-Stats when that plugin is installed.
 *
 * @since 2.0.0
 */
class WP_PostRatings_WPStats {

	/**
	 * Hook into WP-Stats.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'wp_stats_display_defaults', array( __CLASS__, 'display_defaults' ) );
		add_filter( 'wp_stats_page_admin_plugins', array( __CLASS__, 'admin_general' ) );
		add_filter( 'wp_stats_page_admin_most', array( __CLASS__, 'admin_most' ) );
		add_filter( 'wp_stats_page_plugins', array( __CLASS__, 'page_general' ) );
		add_filter( 'wp_stats_page_most', array( __CLASS__, 'page_most' ) );
	}

	/**
	 * The "most" panels this plugin owns.
	 *
	 * @return array
	 */
	private static function panels() {
		$limit = self::most_limit();

		return array(
			/* translators: %s: number of posts. */
			'rated_highest_post' => array( 'get_highest_rated', 'post', _n( '%s Highest Rated Post', '%s Highest Rated Posts', $limit, 'wp-postratings' ) ),
			/* translators: %s: number of pages. */
			'rated_highest_page' => array( 'get_highest_rated', 'page', _n( '%s Highest Rated Page', '%s Highest Rated Pages', $limit, 'wp-postratings' ) ),
			/* translators: %s: number of posts. */
			'rated_most_post'    => array( 'get_most_rated', 'post', _n( '%s Most Rated Post', '%s Most Rated Posts', $limit, 'wp-postratings' ) ),
			/* translators: %s: number of pages. */
			'rated_most_page'    => array( 'get_most_rated', 'page', _n( '%s Most Rated Page', '%s Most Rated Pages', $limit, 'wp-postratings' ) ),
		);
	}

	/**
	 * Tell WP-Stats about the toggles this plugin owns.
	 *
	 * Without this WP-Stats only learns a key exists once its checkbox has been
	 * submitted, so the panels would start out off on a fresh install.
	 *
	 * @param array $defaults WP-Stats defaults.
	 *
	 * @return array
	 */
	public static function display_defaults( $defaults ) {
		// WP-Stats' own defaults win, so this only ever adds.
		return array_merge(
			array(
				'ratings'            => 1,
				'rated_highest_post' => 1,
				'rated_highest_page' => 0,
				'rated_most_post'    => 1,
				'rated_most_page'    => 0,
			),
			(array) $defaults
		);
	}

	/**
	 * Whether a WP-Stats display toggle is on.
	 *
	 * @param string $key Toggle name.
	 *
	 * @return bool
	 */
	public static function enabled( $key ) {
		if ( function_exists( 'wp_stats_display_enabled' ) ) {
			return wp_stats_display_enabled( $key );
		}

		// WP-Stats before 3.0.0 kept the toggles in their own option row.
		$stats_display = get_option( 'stats_display' );

		return is_array( $stats_display ) && 1 === (int) ( isset( $stats_display[ $key ] ) ? $stats_display[ $key ] : 0 );
	}

	/**
	 * How many "most" entries WP-Stats shows.
	 *
	 * @return int
	 */
	public static function most_limit() {
		if ( function_exists( 'wp_stats_most_limit' ) ) {
			return wp_stats_most_limit();
		}

		return (int) get_option( 'stats_mostlimit' );
	}

	/**
	 * One checkbox on the WP-Stats options screen.
	 *
	 * @param string $value Toggle name.
	 * @param string $label Label.
	 *
	 * @return string
	 */
	private static function checkbox( $value, $label ) {
		// WP-Stats 3.0.0 owns the field name, which changed when it
		// consolidated its option rows.
		if ( function_exists( 'wp_stats_checkbox' ) ) {
			return wp_stats_checkbox( $value, $label );
		}

		return '<input type="checkbox" name="stats_display[]" id="wpstats_' . esc_attr( $value ) . '" value="' . esc_attr( $value ) . '"' .
			checked( self::enabled( $value ), true, false ) .
			' />&nbsp;&nbsp;<label for="wpstats_' . esc_attr( $value ) . '">' . esc_html( $label ) . '</label><br />' . "\n";
	}

	/**
	 * Add the general toggle to the WP-Stats options screen.
	 *
	 * @param string $content Existing markup.
	 *
	 * @return string
	 */
	public static function admin_general( $content ) {
		return $content . self::checkbox( 'ratings', __( 'WP-PostRatings', 'wp-postratings' ) );
	}

	/**
	 * Add the "most" toggles to the WP-Stats options screen.
	 *
	 * @param string $content Existing markup.
	 *
	 * @return string
	 */
	public static function admin_most( $content ) {
		$limit = self::most_limit();

		foreach ( self::panels() as $key => $panel ) {
			$content .= self::checkbox( $key, sprintf( $panel[2], number_format_i18n( $limit ) ) );
		}

		return $content;
	}

	/**
	 * Add the vote total to the WP-Stats page.
	 *
	 * @param string $content Existing markup.
	 *
	 * @return string
	 */
	public static function page_general( $content ) {
		if ( ! self::enabled( 'ratings' ) ) {
			return $content;
		}

		$users = get_ratings_users( false );

		$content .= '<p><strong>' . esc_html__( 'WP-PostRatings', 'wp-postratings' ) . '</strong></p>' . "\n";
		$content .= '<ul>' . "\n";
		$content .= '<li><strong>' . esc_html( number_format_i18n( $users ) ) . '</strong> ' .
			esc_html( _n( 'user casted his vote.', 'users casted their vote.', $users, 'wp-postratings' ) ) . '</li>' . "\n";
		$content .= '</ul>' . "\n";

		return $content;
	}

	/**
	 * Add the "most" panels to the WP-Stats page.
	 *
	 * @param string $content Existing markup.
	 *
	 * @return string
	 */
	public static function page_most( $content ) {
		$limit = self::most_limit();

		foreach ( self::panels() as $key => $panel ) {
			if ( ! self::enabled( $key ) ) {
				continue;
			}

			list( $callback, $mode, $heading ) = $panel;

			$content .= '<p><strong>' . esc_html( sprintf( $heading, number_format_i18n( $limit ) ) ) . '</strong></p>' . "\n";
			$content .= '<ul>' . "\n";
			$content .= $callback( $mode, 0, $limit, 0, false );
			$content .= '</ul>' . "\n";
		}

		return $content;
	}
}
