<?php
/**
 * Plugin Name: WP-PostRatings
 * Plugin URI: https://lesterchan.net/portfolio/programming/php/
 * Description: Adds an AJAX rating system for your WordPress site's content.
 * Version: 2.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
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
	Copyright 2026 Lester Chan  (email : lesterchan@gmail.com)

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
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP-PostRatings version.
 */
define( 'WP_POSTRATINGS_VERSION', '2.0.0' );

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

require_once __DIR__ . '/includes/class-postratings-options.php';
require_once __DIR__ . '/includes/class-postratings-installer.php';
require_once __DIR__ . '/includes/class-postratings-template.php';
require_once __DIR__ . '/includes/class-postratings-rating.php';
require_once __DIR__ . '/includes/class-postratings-comments.php';
require_once __DIR__ . '/includes/class-postratings-stats.php';
require_once __DIR__ . '/includes/class-postratings-widget.php';
require_once __DIR__ . '/includes/class-postratings-wpstats.php';
require_once __DIR__ . '/includes/class-postratings-settings.php';
require_once __DIR__ . '/includes/class-postratings-admin.php';
require_once __DIR__ . '/includes/class-postratings.php';
require_once __DIR__ . '/includes/template-tags.php';
require_once __DIR__ . '/includes/deprecated.php';

// class-postratings-logs-table.php is loaded on demand by the log screen: it
// pulls in wp-admin/includes/class-wp-list-table.php, which has no business
// being loaded on a front end request. The two classes above only *register*
// hooks when is_admin(), so loading them costs a file read and nothing else --
// and it keeps them reachable from the test suite, which does not run as admin.

Postratings::get_instance();
