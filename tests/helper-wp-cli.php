<?php
/**
 * Stand-in for the WP_CLI facade, recording what the command reports.
 *
 * Kept out of the test files themselves so each of those declares exactly one
 * class, and loaded from bootstrap.php rather than from a test, so the order
 * does not depend on which test runs first. The base class it extends lives in
 * helper-wp-cli-command.php and the formatter in helper-wp-cli-utils.php,
 * because the coding standard allows one class per file and that rule is not
 * relaxed for the suite.
 *
 * @package WP-PostRatings
 */

if ( ! class_exists( 'WP_CLI' ) ) {
	/**
	 * Stand-in for the WP_CLI facade, recording what the command reports.
	 */
	class WP_CLI {
		/**
		 * Messages passed to WP_CLI::success().
		 *
		 * @var array
		 */
		public static $successes = array();

		/**
		 * Messages passed to WP_CLI::warning().
		 *
		 * @var array
		 */
		public static $warnings = array();

		/**
		 * Messages passed to WP_CLI::log().
		 *
		 * @var array
		 */
		public static $logs = array();

		/**
		 * Questions passed to WP_CLI::confirm().
		 *
		 * @var array
		 */
		public static $confirmations = array();

		/**
		 * Commands registered with WP_CLI::add_command().
		 *
		 * @var array
		 */
		public static $commands = array();

		/**
		 * Row sets passed to WP_CLI\Utils\format_items(), in call order.
		 *
		 * @var array
		 */
		public static $items = array();

		/**
		 * Records a success message.
		 *
		 * @param string $message Message.
		 * @return void
		 */
		public static function success( $message ) {
			self::$successes[] = $message;
		}

		/**
		 * Records a warning.
		 *
		 * @param string $message Message.
		 * @return void
		 */
		public static function warning( $message ) {
			self::$warnings[] = $message;
		}

		/**
		 * Records a log line.
		 *
		 * @param string $message Message.
		 * @return void
		 */
		public static function log( $message ) {
			self::$logs[] = $message;
		}

		/**
		 * Stops the command, the way the real facade does.
		 *
		 * WP_CLI::error() prints and then exits, so every line after a call to
		 * it is unreachable. A stub that returned would let a test pass while
		 * the command carried on operating on a poll it had just said did not
		 * exist -- so this throws, and RuntimeException is used rather than a
		 * fourth helper file declaring an exception class nothing else needs.
		 *
		 * @param string $message Message.
		 * @return void
		 * @throws RuntimeException Always, standing in for WP-CLI's exit.
		 */
		public static function error( $message ) {
			throw new RuntimeException( $message );
		}

		/**
		 * Records a confirmation prompt, and stops unless --yes was passed.
		 *
		 * This models the non-interactive case, which is the one worth pinning:
		 * WP-CLI asks, there is nobody to answer, and the command does not go
		 * ahead. A test that wants the delete to happen passes --yes, exactly as
		 * a script would.
		 *
		 * @param string $question   Question.
		 * @param array  $assoc_args Flags the command was called with.
		 * @return void
		 * @throws RuntimeException When --yes was not passed.
		 */
		public static function confirm( $question, $assoc_args = array() ) {
			self::$confirmations[] = $question;

			if ( empty( $assoc_args['yes'] ) ) {
				throw new RuntimeException( $question );
			}
		}

		/**
		 * Records a command registration.
		 *
		 * @param string $name    Command name.
		 * @param string $handler Handler class name.
		 * @return void
		 */
		public static function add_command( $name, $handler ) {
			self::$commands[ $name ] = $handler;
		}
	}
}

require_once dirname( __DIR__ ) . '/includes/class-wp-postratings-command.php';
