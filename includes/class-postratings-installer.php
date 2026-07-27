<?php
/**
 * WP-PostRatings class-postratings-installer.php
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
class Postratings_Installer {

	/**
	 * Option holding the installed schema version.
	 *
	 * Kept out of the settings row: it is read to decide whether the settings
	 * need migrating, so it cannot live inside the thing being migrated.
	 */
	const DB_VERSION_OPTION = 'postratings_db_version';

	/**
	 * Bumped when the table definition changes.
	 */
	const DB_VERSION = '2';

	/**
	 * Capability the admin screens require.
	 */
	const CAPABILITY = 'manage_ratings';

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

		Postratings_Options::maybe_migrate();
	}

	/**
	 * Run the install checks on a normal request when something is out of date.
	 *
	 * Activation does not fire on plugin *update*, which is the single most
	 * common reason a migration never runs.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( (string) get_option( self::DB_VERSION_OPTION, '' ) !== self::DB_VERSION ) {
			self::maybe_create_table();
			self::add_capability();
		}

		Postratings_Options::maybe_migrate();
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

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->ratings ) );

		if ( $exists === $wpdb->ratings ) {
			self::maybe_add_indexes();
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION );

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

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Add the indexes to a table that predates them.
	 *
	 * @return void
	 */
	private static function maybe_add_indexes() {
		global $wpdb;

		// $row->Key_name is MySQL's column name from SHOW INDEX, not ours.
		$indexes   = $wpdb->get_results( "SHOW INDEX FROM $wpdb->ratings" );
		$key_names = array();

		if ( is_array( $indexes ) ) {
			foreach ( $indexes as $index ) {
				$key_names[] = $index->Key_name;
			}
		}

		if ( ! in_array( 'rating_userid', $key_names, true ) ) {
			$wpdb->query( "ALTER TABLE $wpdb->ratings ADD INDEX rating_userid (rating_userid)" );
		}

		if ( ! in_array( 'rating_postid_ip', $key_names, true ) ) {
			$wpdb->query( "ALTER TABLE $wpdb->ratings ADD INDEX rating_postid_ip (rating_postid, rating_ip)" );
		}
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
			$role->add_cap( self::CAPABILITY );
		}
	}
}
