<?php
/**
 * The WP-CLI command.
 *
 * @package WP-PostRatings
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and clears post ratings from the command line.
 *
 * Registered as `wp postratings` rather than `wp wp-postratings`. A `wp-`
 * prefix is a wordpress.org directory convention for keeping one plugin's
 * download page apart from another's, not a command-naming one, and after the
 * `wp` binary it only stutters; WP-CLI's own ecosystem names commands after the
 * brand -- `wp wc`, `wp yoast`, `wp jetpack`. WP-CLI gives a collision to
 * whichever plugin registered last and a bare noun is claimable by anyone; that
 * is the accepted trade for a word as distinctive as `postratings`.
 *
 * Every subcommand goes through WP_PostRatings_Data, the same class the admin
 * screen clears through, so a rating cleared here loses the same two things it
 * loses through the browser: the running totals in post meta and the rows in
 * the log.
 */
class WP_PostRatings_Command extends WP_CLI_Command {

	/**
	 * List the best rated posts.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : How many posts to list.
	 * ---
	 * default: 10
	 * ---
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - count
	 *   - ids
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # The ten best rated posts.
	 *     $ wp postratings list
	 *
	 *     # The best fifty, as JSON.
	 *     $ wp postratings list --limit=50 --format=json
	 *
	 * @subcommand list
	 *
	 * @param array $args       Positional arguments. Unused.
	 * @param array $assoc_args Flags passed to the command.
	 * @return void
	 */
	public function list_( $args, $assoc_args ) {
		unset( $args );

		$limit  = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 10;
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		$rated  = WP_PostRatings_Data::top( $limit );

		if ( empty( $rated ) ) {
			// Nothing rated yet is an answer, not a failure.
			WP_CLI::success( __( 'No posts have been rated yet.', 'wp-postratings' ) );

			return;
		}

		$rows = array();

		foreach ( $rated as $entry ) {
			$rows[] = array(
				'id'      => $entry['post_id'],
				'title'   => $entry['title'],
				'users'   => $entry['users'],
				'score'   => $entry['score'],
				'average' => $entry['average'],
			);
		}

		$this->print_rows( $format, $rows, array( 'id', 'title', 'users', 'score', 'average' ) );
	}

	/**
	 * Show one post's rating.
	 *
	 * ## OPTIONS
	 *
	 * <post-id>
	 * : The post to read.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp postratings get 42
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flags passed to the command.
	 * @return void
	 */
	public function get( $args, $assoc_args ) {
		$post_id = $this->post_or_die( $args[0] );

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		$rating = WP_PostRatings_Data::get( $post_id );

		$rows = array(
			array(
				'field' => 'users',
				'value' => $rating['users'],
			),
			array(
				'field' => 'score',
				'value' => $rating['score'],
			),
			array(
				'field' => 'average',
				'value' => $rating['average'],
			),
		);

		$this->print_rows( $format, $rows, array( 'field', 'value' ) );
	}

	/**
	 * Delete the rating log, the running totals, or both.
	 *
	 * ## OPTIONS
	 *
	 * [<post-id>...]
	 * : Posts to clear. Ignored when --all is given.
	 *
	 * [--all]
	 * : Clear every post on the site.
	 *
	 * [--what=<what>]
	 * : Which half to clear.
	 * ---
	 * default: both
	 * options:
	 *   - logs
	 *   - data
	 *   - both
	 * ---
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     # Clear one post's rating entirely.
	 *     $ wp postratings delete 42
	 *
	 *     # Drop the log but keep the totals every post displays.
	 *     $ wp postratings delete --all --what=logs
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flags passed to the command.
	 * @return void
	 */
	public function delete( $args, $assoc_args ) {
		$all  = ! empty( $assoc_args['all'] );
		$what = isset( $assoc_args['what'] ) ? $assoc_args['what'] : 'both';

		if ( ! $all && empty( $args ) ) {
			// An empty id list would otherwise read as "delete nothing" and
			// report success, which is the wrong answer to a mistyped command.
			WP_CLI::error( __( 'Name at least one post id, or pass --all.', 'wp-postratings' ) );
		}

		$post_ids = $all ? array() : array_map( 'intval', $args );

		WP_CLI::confirm(
			$all
				? __( 'Clear ratings for every post on this site?', 'wp-postratings' )
				/* translators: %s: list of post ids. */
				: sprintf( __( 'Clear ratings for post(s) %s?', 'wp-postratings' ), implode( ', ', $post_ids ) ),
			$assoc_args
		);

		if ( 'logs' === $what || 'both' === $what ) {
			$deleted = WP_PostRatings_Data::delete_logs( $post_ids, $all );

			WP_CLI::success(
				sprintf(
					/* translators: %s: number of log entries deleted. */
					_n( '%s rating log entry deleted.', '%s rating log entries deleted.', $deleted, 'wp-postratings' ),
					number_format_i18n( $deleted )
				)
			);
		}

		if ( 'data' === $what || 'both' === $what ) {
			WP_PostRatings_Data::delete_ratings( $post_ids, $all );

			WP_CLI::success(
				$all
					? __( 'All rating data deleted.', 'wp-postratings' )
					/* translators: %s: list of post ids. */
					: sprintf( __( 'Rating data deleted for Post ID(s) %s.', 'wp-postratings' ), implode( ', ', $post_ids ) )
			);
		}
	}

	/**
	 * Resolve an id to a post, or stop the command.
	 *
	 * @param string $post_id Post id as it arrived on the command line.
	 * @return int The post id.
	 */
	private function post_or_die( $post_id ) {
		$post = get_post( (int) $post_id );

		if ( ! $post ) {
			/* translators: %d: the post id that was asked for. */
			WP_CLI::error( sprintf( __( 'No post with id %d.', 'wp-postratings' ), (int) $post_id ) );
		}

		return (int) $post->ID;
	}

	/**
	 * Print rows, reducing to the first column when ids were asked for.
	 *
	 * WP_CLI\Utils\format_items() hands `ids` straight to implode(), so a row
	 * that is an associative array comes out as the word `Array` and a PHP
	 * warning -- which is what `--format=ids` did here until it was run against
	 * a real WP-CLI rather than reasoned about. Reducing to the first column is
	 * what makes the documented `| xargs` pipes work.
	 *
	 * @param string $format Requested output format.
	 * @param array  $rows   Rows to print.
	 * @param array  $fields Columns, in order. The first is the id.
	 * @return void
	 */
	private function print_rows( $format, array $rows, array $fields ) {
		if ( 'ids' === $format ) {
			WP_CLI\Utils\format_items( $format, wp_list_pluck( $rows, $fields[0] ), $fields );

			return;
		}

		WP_CLI\Utils\format_items( $format, $rows, $fields );
	}
}
