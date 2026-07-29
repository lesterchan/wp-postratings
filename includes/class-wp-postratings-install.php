<?php
/**
 * WP-PostRatings class-wp-postratings-install.php
 *
 * @package wp-postratings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates the log table and keeps the schema and capability in step.
 *
 * @since 2.0.0
 */
class WP_PostRatings_Install {

	/**
	 * Run on activation, for one site or every site on the network.
	 *
	 * @param bool $network_wide Whether the plugin is being network activated.
	 *
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			// 'number' => 0 lifts WP_Site_Query's default cap of 100, which
			// would otherwise skip the install on every site past the hundredth.
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::install();
				restore_current_blog();
			}

			return;
		}

		self::install();
	}

	/**
	 * Install or upgrade the current site.
	 *
	 * @return void
	 */
	public static function install() {
		self::maybe_create_table();
		self::add_capability();

		WP_PostRatings_Options::maybe_migrate();

		// Last, and in one write. A run that fails part way through leaves the
		// markers where they were, so the next request tries again rather than
		// believing the upgrade finished.
		WP_PostRatings_Options::update_markers();
	}

	/**
	 * Remove everything the plugin created, for one site or the whole network.
	 *
	 * Lives here rather than in uninstall.php so that install and uninstall sit
	 * side by side and stay in step, and so that a test can drive it directly
	 * instead of asserting on the shape of a root file with a regular
	 * expression. uninstall.php is four lines that call this.
	 *
	 * @return void
	 */
	public static function uninstall() {
		if ( ! is_multisite() ) {
			self::uninstall_site();

			return;
		}

		// 'number' => 0 lifts WP_Site_Query's default cap of 100, which would
		// otherwise leave the options and tables behind on every site past the
		// hundredth while uninstall still reported success.
		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		// restore_current_blog() sits inside the loop on purpose: switching
		// pushes onto a stack, so switching many times and restoring once
		// leaves the stack unwound by exactly one.
		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			self::uninstall_site();
			restore_current_blog();
		}
	}

	/**
	 * Remove the plugin's options, table, capability and post meta for one site.
	 *
	 * @return void
	 */
	public static function uninstall_site() {
		global $wpdb;

		foreach ( WP_PostRatings_Options::all_option_names() as $option_name ) {
			delete_option( $option_name );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Dropping the plugin's own table and clearing its meta key across every post; core has no API for either and there is nothing to cache.
		$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}ratings`" );

		foreach ( array( 'ratings_users', 'ratings_score', 'ratings_average' ) as $meta_key ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE meta_key = %s", $meta_key ) );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$role = get_role( 'administrator' );

		// The same expression add_capability() grants, filter and all: removing
		// the constant while granting the filtered value would leave a site that
		// uses wp_postratings_capability with a capability nothing takes back.
		if ( $role instanceof WP_Role ) {
			$role->remove_cap( WP_PostRatings_Settings::capability() );
		}
	}

	/**
	 * Run the install checks on a normal request when something is out of date.
	 *
	 * Activation does not fire on plugin *update*, which is the single most
	 * common reason a migration never runs. Gated on one autoloaded row, so an
	 * install that is already current pays a lookup and nothing else.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$markers = WP_PostRatings_Options::markers();

		if ( WP_POSTRATINGS_VERSION === $markers['plugin'] && WP_POSTRATINGS_DB_VERSION === $markers['db'] ) {
			return;
		}

		self::install();
	}

	/**
	 * Create the log table when it is missing.
	 *
	 * Deliberately not an unconditional dbDelta(): this schema does not round
	 * trip through it, so calling it on every load emits an
	 * "ALTER TABLE ... SET DEFAULT ''" against the table each time.
	 *
	 * @return void
	 */
	private static function maybe_create_table() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Creating and inspecting the plugin's own table is the whole job of this class; core has no API for it and there is nothing to cache.
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->ratings ) );

		if ( $exists === $wpdb->ratings ) {
			self::maybe_add_indexes();

			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $wpdb->ratings (
			rating_id INT(11) NOT NULL auto_increment,
			rating_postid INT(11) NOT NULL,
			rating_posttitle TEXT NOT NULL,
			rating_rating INT(2) NOT NULL,
			rating_timestamp VARCHAR(15) NOT NULL,
			rating_ip VARCHAR(40) NOT NULL,
			rating_host VARCHAR(200) NOT NULL,
			rating_username VARCHAR(50) NOT NULL,
			rating_userid INT(10) NOT NULL default '0',
			PRIMARY KEY (rating_id),
			KEY rating_userid (rating_userid),
			KEY rating_postid_ip (rating_postid, rating_ip)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Add the indexes to a table that predates them.
	 *
	 * @return void
	 */
	private static function maybe_add_indexes() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Adding an index to the plugin's own table; core has no API for it and there is nothing to cache.

		// Column 2 of SHOW INDEX is Key_name. Taken by position rather than by
		// name because the name is MySQL's, and reading $row->Key_name off the
		// object is a coding-standard violation this plugin would then have to
		// suppress in order to spell someone else's column correctly.
		$key_names = (array) $wpdb->get_col( "SHOW INDEX FROM $wpdb->ratings", 2 );

		if ( ! in_array( 'rating_userid', $key_names, true ) ) {
			$wpdb->query( "ALTER TABLE $wpdb->ratings ADD INDEX rating_userid (rating_userid)" );
		}

		if ( ! in_array( 'rating_postid_ip', $key_names, true ) ) {
			$wpdb->query( "ALTER TABLE $wpdb->ratings ADD INDEX rating_postid_ip (rating_postid, rating_ip)" );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Grant the administrator role the plugin's capability.
	 *
	 * @return void
	 */
	private static function add_capability() {
		$role = get_role( 'administrator' );

		// Null when the role has been removed, which is a fatal rather than a
		// missing capability if it is not checked.
		if ( $role instanceof WP_Role ) {
			$role->add_cap( WP_PostRatings_Settings::capability() );
		}
	}
}
