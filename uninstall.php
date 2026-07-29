<?php
/**
 * WP-PostRatings uninstall.php
 *
 * WordPress loads this file, and nothing else of the plugin, when the plugin is
 * deleted. Everything it does lives beside the installer it undoes, so the two
 * cannot drift apart and the work is reachable from the test suite.
 *
 * @package wp-postratings
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-wp-postratings-options.php';
require_once __DIR__ . '/includes/class-wp-postratings-settings.php';
require_once __DIR__ . '/includes/class-wp-postratings-install.php';

WP_PostRatings_Install::uninstall();
