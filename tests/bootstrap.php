<?php
/**
 * PHPUnit bootstrap for WP-PostRatings.
 *
 * Runs inside the wp-env "tests" container, where WP_TESTS_DIR is already
 * exported and the WordPress test library is present.
 *
 * @package WP-PostRatings
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = '/wordpress-phpunit';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find the WordPress test library at {$_tests_dir}." . PHP_EOL;
	echo 'Run the suite through wp-env: bash bin/test.sh' . PHP_EOL;
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Load the plugin under test before WordPress finishes booting.
 *
 * @return void
 */
function _wp_postratings_manually_load_plugin() {
	require dirname( __DIR__ ) . '/wp-postratings.php';

	// register_activation_hook() never fires in the test environment, so the
	// table and the capability have to be created by hand.
	WP_PostRatings_Install::install();
}
tests_add_filter( 'muplugins_loaded', '_wp_postratings_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

// After the WordPress bootstrap, which is what declares WP_UnitTestCase -- the
// fixture base class extends it, so it cannot be loaded any earlier.
require_once __DIR__ . '/helper-testcase.php';

// The WP-CLI stand-ins, in dependency order: the base class the command file
// extends, then the formatter it prints through, then the facade -- which ends
// by requiring the command itself, so nothing else has to know that the plugin
// only loads that file when WP_CLI is defined.
require_once __DIR__ . '/helper-wp-cli-command.php';
require_once __DIR__ . '/helper-wp-cli-utils.php';
require_once __DIR__ . '/helper-wp-cli.php';

// The shared metadata contract. helper-metadata-testcase.php is a byte-identical
// copy of _standards/templates/helper-metadata-testcase.php in all nineteen
// plugins, so it cannot name WP_PostRatings_TestCase; it extends
// Plugin_TestCase and this alias is the one per-plugin line the mechanism needs.
class_alias( 'WP_PostRatings_TestCase', 'Plugin_TestCase' );
require_once __DIR__ . '/helper-metadata-testcase.php';
