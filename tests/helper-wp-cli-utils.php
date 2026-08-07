<?php
/**
 * Stand-in for the WP-CLI formatter the list and get subcommands print through.
 *
 * `WP_CLI\Utils\format_items()` is a namespaced function rather than a class,
 * so it needs a file that declares that namespace and nothing else -- a braced
 * namespace block sharing a file with global code would work in PHP and read
 * as a puzzle.
 *
 * Recording the rows rather than printing them is what makes the table
 * assertable: a test can ask which polls the command listed and in what order
 * without parsing column-aligned text back into data.
 *
 * @package WP-PostRatings
 */

namespace WP_CLI\Utils;

if ( ! function_exists( __NAMESPACE__ . '\format_items' ) ) {
	/**
	 * Records the rows a command asked to be formatted.
	 *
	 * @param string $format Requested output format.
	 * @param array  $items  Rows to render.
	 * @param array  $fields Columns to render, in order.
	 * @return void
	 */
	function format_items( $format, $items, $fields ) {
		\WP_CLI::$items[] = array(
			'format' => $format,
			'items'  => $items,
			'fields' => $fields,
		);
	}
}
