<?php
/**
 * Plugin Name: WP-PostRatings
 * Plugin URI: https://lesterchan.net/portfolio/programming/php/
 * Description: Adds an AJAX rating system for your WordPress site's content.
 * Version: 2.0.0
 * Requires at least: 6.8
 * Requires PHP: 8.2
 * Author: Lester 'GaMerZ' Chan
 * Author URI: https://lesterchan.net
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-postratings
 * Domain Path: /languages
 *
 * @package WP-PostRatings
 */

/*
	Copyright 2026  Lester Chan  (email : lesterchan@gmail.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP-PostRatings version. Compared against the 'plugin' marker in wp_postratings_version.
 */
define( 'WP_POSTRATINGS_VERSION', '2.0.0' );

/**
 * Schema counter. Compared against the 'db' marker in wp_postratings_version.
 *
 * Starts at 3 rather than 1 because it replaces two separate counters that
 * existed before 2.0.0 -- postratings_db_version, which had reached 2, and
 * postratings_options_version, which had reached 3. Taking the higher of the
 * two means no installed site is ever told its schema went backwards.
 */
define( 'WP_POSTRATINGS_DB_VERSION', '3' );

/**
 * Plugin slug, text domain and menu slug.
 */
define( 'WP_POSTRATINGS_SLUG', 'wp-postratings' );

/**
 * WP-PostRatings main file.
 */
define( 'WP_POSTRATINGS_MAIN_FILE', __FILE__ );

/**
 * Filesystem path of the plugin directory, with a trailing slash.
 */
define( 'WP_POSTRATINGS_DIR', plugin_dir_path( __FILE__ ) );

/**
 * URL of the plugin directory, with a trailing slash.
 */
define( 'WP_POSTRATINGS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Left over from the image sets, which 2.0.0 replaced with SVG shapes.
 *
 * Renamed from RATINGS_IMG_EXT, which carried no plugin prefix. There is no
 * file extension left to choose, so nothing in the plugin reads it; it is
 * defined only so a theme that still does gets an answer rather than a notice.
 */
if ( ! defined( 'WP_POSTRATINGS_IMG_EXT' ) ) {
	define( 'WP_POSTRATINGS_IMG_EXT', 'svg' );
}

require_once __DIR__ . '/includes/class-wp-postratings-shapes.php';
require_once __DIR__ . '/includes/class-wp-postratings-options.php';
require_once __DIR__ . '/includes/class-wp-postratings-install.php';
require_once __DIR__ . '/includes/class-wp-postratings-template.php';
require_once __DIR__ . '/includes/class-wp-postratings-rating.php';
require_once __DIR__ . '/includes/class-wp-postratings-comments.php';
require_once __DIR__ . '/includes/class-wp-postratings-stats.php';
require_once __DIR__ . '/includes/class-wp-postratings-widget.php';
require_once __DIR__ . '/includes/class-wp-postratings-wpstats.php';
require_once __DIR__ . '/includes/class-wp-postratings-settings.php';
require_once __DIR__ . '/includes/class-wp-postratings-admin.php';
require_once __DIR__ . '/includes/class-wp-postratings.php';
require_once __DIR__ . '/includes/template-tags.php';
require_once __DIR__ . '/includes/deprecated.php';

// class-wp-postratings-logs-table.php is loaded on demand by the log screen: it
// pulls in wp-admin/includes/class-wp-list-table.php, which has no business
// being loaded on a front end request. The two classes above only *register*
// hooks when is_admin(), so loading them costs a file read and nothing else --
// and it keeps them reachable from the test suite, which does not run as admin.

WP_PostRatings::get_instance();
