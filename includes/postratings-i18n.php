<?php
/**
 * WP-PostRatings i18n.
 *
 * @package WordPress
 * @subpackage WP-PostRatings Plugin
 */


/**
 * Security check
 * Prevent direct access to the file.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/*
 * Internationalization
 * Load plugin translation files.
 *
 * @since 1.84
 */
class WPPostRatingsI18N {

	/*
	 * Constructor
	 */
	public function __construct() {

		// Load textdomain
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

	}

	/*
	 * Load the text domain for translation
	 */
	public function load_textdomain() {

		load_plugin_textdomain( 'wp-postratings' );

	}

}
new WPPostRatingsI18N();
