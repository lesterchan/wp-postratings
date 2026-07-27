<?php
/**
 * WP-PostRatings class-postratings-admin.php
 *
 * @package wp-postratings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The admin menus, the rating column on the post lists, and the log screen.
 *
 * @since 2.0.0
 */
class Postratings_Admin {

	/**
	 * Menu slug of the log screen.
	 */
	const PAGE_MANAGE = 'wp-postratings';

	/**
	 * Menu slug of the settings screen.
	 */
	const PAGE_OPTIONS = 'wp-postratings-options';

	/**
	 * The list table, built on load so Screen Options can register.
	 *
	 * @var Postratings_Logs_Table|null
	 */
	private static $table = null;

	/**
	 * Hook the admin.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( 'Postratings_Installer', 'maybe_upgrade' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'scripts' ) );

		add_filter( 'manage_posts_columns', array( __CLASS__, 'column_title' ) );
		add_filter( 'manage_pages_columns', array( __CLASS__, 'column_title' ) );
		add_action( 'manage_posts_custom_column', array( __CLASS__, 'column_content' ) );
		add_action( 'manage_pages_custom_column', array( __CLASS__, 'column_content' ) );
		add_filter( 'manage_edit-post_sortable_columns', array( __CLASS__, 'column_sortable' ) );
		add_filter( 'manage_edit-page_sortable_columns', array( __CLASS__, 'column_sortable' ) );

		add_action( 'pre_get_posts', array( __CLASS__, 'sort_by_rating' ) );
	}

	/**
	 * Register the menus.
	 *
	 * The slugs are plain names rather than the old "plugin file as menu slug"
	 * form, which put the plugin's directory name into every admin URL and into
	 * the hook suffix handed back to admin_enqueue_scripts.
	 *
	 * @return void
	 */
	public static function menu() {
		$hook = add_menu_page(
			__( 'Ratings', 'wp-postratings' ),
			__( 'Ratings', 'wp-postratings' ),
			Postratings_Installer::CAPABILITY,
			self::PAGE_MANAGE,
			array( __CLASS__, 'render_manage' ),
			'dashicons-star-filled'
		);

		add_submenu_page(
			self::PAGE_MANAGE,
			__( 'Manage Ratings', 'wp-postratings' ),
			__( 'Manage Ratings', 'wp-postratings' ),
			Postratings_Installer::CAPABILITY,
			self::PAGE_MANAGE,
			array( __CLASS__, 'render_manage' )
		);

		add_submenu_page(
			self::PAGE_MANAGE,
			__( 'Ratings Options', 'wp-postratings' ),
			__( 'Ratings Options', 'wp-postratings' ),
			Postratings_Installer::CAPABILITY,
			self::PAGE_OPTIONS,
			array( 'Postratings_Settings', 'render' )
		);

		add_action( 'load-' . $hook, array( __CLASS__, 'load_manage' ) );
	}

	/**
	 * The admin pages this plugin owns, by hook suffix.
	 *
	 * Extracted so the asset loading and anything else that needs the list
	 * cannot drift apart.
	 *
	 * @return array
	 */
	public static function screen_hooks() {
		return array(
			'toplevel_page_' . self::PAGE_MANAGE,
			'ratings_page_' . self::PAGE_MANAGE,
			'ratings_page_' . self::PAGE_OPTIONS,
		);
	}

	/**
	 * Load the list table class on demand.
	 *
	 * It pulls in wp-admin/includes/class-wp-list-table.php, so it is kept out
	 * of the main file's unconditional requires.
	 *
	 * @return void
	 */
	private static function require_list_table() {
		if ( ! class_exists( 'Postratings_Logs_Table' ) ) {
			require_once WP_POSTRATINGS_DIR . 'includes/class-postratings-logs-table.php';
		}
	}

	/**
	 * Set up the log screen: handle actions, then build the table.
	 *
	 * @return void
	 */
	public static function load_manage() {
		self::require_list_table();
		self::handle_actions();

		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Ratings per page', 'wp-postratings' ),
				'default' => 20,
				'option'  => 'postratings_logs_per_page',
			)
		);

		self::$table = new Postratings_Logs_Table();
	}

	/**
	 * Keep the Screen Options per-page value.
	 *
	 * @param mixed  $status Default.
	 * @param string $option Option name.
	 * @param mixed  $value  Submitted value.
	 *
	 * @return mixed
	 */
	public static function set_screen_option( $status, $option, $value ) {
		return 'postratings_logs_per_page' === $option ? (int) $value : $status;
	}

	/**
	 * Handle the bulk delete and the delete-data form.
	 *
	 * @return void
	 */
	private static function handle_actions() {
		global $wpdb;

		if ( ! current_user_can( Postratings_Installer::CAPABILITY ) ) {
			return;
		}

		$notices = array();

		// Bulk "delete selected" from the list table.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

		if ( 'delete' === $action && ! empty( $_REQUEST['rating_id'] ) ) {
			check_admin_referer( 'bulk-ratings' );

			$ids = wp_parse_id_list( wp_unslash( $_REQUEST['rating_id'] ) );

			if ( ! empty( $ids ) ) {
				$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

				$deleted = $wpdb->query(
					// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders is a generated run of %d, one per id.
					$wpdb->prepare( "DELETE FROM {$wpdb->ratings} WHERE rating_id IN ( $placeholders )", $ids )
				);

				/* translators: %s: number of log entries deleted. */
				$notices[] = sprintf( _n( '%s rating log entry deleted.', '%s rating log entries deleted.', $deleted, 'wp-postratings' ), number_format_i18n( $deleted ) );
			}
		}

		// The "Delete Ratings Data/Logs" form.
		if ( ! empty( $_POST['postratings_delete'] ) ) {
			check_admin_referer( 'wp-postratings_logs' );

			$mode     = isset( $_POST['delete_datalog'] ) ? (int) $_POST['delete_datalog'] : 1;
			$raw_ids  = isset( $_POST['delete_postid'] ) ? sanitize_text_field( wp_unslash( $_POST['delete_postid'] ) ) : '';
			$is_all   = 'all' === strtolower( trim( $raw_ids ) );
			$post_ids = $is_all ? array() : wp_parse_id_list( $raw_ids );

			// A list that parsed to nothing would build "IN ()", a syntax error
			// rather than a no-op.
			if ( ! $is_all && empty( $post_ids ) ) {
				$notices[] = __( 'No valid Post ID(s) given.', 'wp-postratings' );
			} else {
				$notices = array_merge( $notices, self::delete_data( $mode, $is_all, $post_ids ) );
			}
		}

		if ( ! empty( $notices ) ) {
			set_transient( 'postratings_admin_notices', $notices, 60 );

			wp_safe_redirect( add_query_arg( 'page', self::PAGE_MANAGE, admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	/**
	 * Delete logs, rating data, or both.
	 *
	 * @param int   $mode     1 logs, 2 data, 3 both.
	 * @param bool  $is_all   Whether to clear everything.
	 * @param array $post_ids Posts to clear.
	 *
	 * @return array Notices.
	 */
	private static function delete_data( $mode, $is_all, $post_ids ) {
		global $wpdb;

		$notices   = array();
		$meta_keys = array( 'ratings_users', 'ratings_score', 'ratings_average' );

		if ( 1 === $mode || 3 === $mode ) {
			if ( $is_all ) {
				$deleted = $wpdb->query( "DELETE FROM {$wpdb->ratings}" );
			} else {
				$placeholders = implode( ', ', array_fill( 0, count( $post_ids ), '%d' ) );

				$deleted = $wpdb->query(
					// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders is a generated run of %d, one per id.
					$wpdb->prepare( "DELETE FROM {$wpdb->ratings} WHERE rating_postid IN ( $placeholders )", $post_ids )
				);
			}

			/* translators: %s: number of log entries deleted. */
			$notices[] = sprintf( _n( '%s rating log entry deleted.', '%s rating log entries deleted.', $deleted, 'wp-postratings' ), number_format_i18n( $deleted ) );
		}

		if ( 2 === $mode || 3 === $mode ) {
			if ( $is_all ) {
				foreach ( $meta_keys as $meta_key ) {
					$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", $meta_key ) );
				}

				// Deleting meta rows directly leaves every post's meta cache
				// holding the values that were just removed.
				wp_cache_flush();

				$notices[] = __( 'All rating data deleted.', 'wp-postratings' );
			} else {
				foreach ( $post_ids as $post_id ) {
					foreach ( $meta_keys as $meta_key ) {
						delete_post_meta( $post_id, $meta_key );
					}
				}

				/* translators: %s: list of post ids. */
				$notices[] = sprintf( __( 'Rating data deleted for Post ID(s) %s.', 'wp-postratings' ), implode( ', ', $post_ids ) );
			}
		}

		return $notices;
	}

	/**
	 * Render the log screen.
	 *
	 * @return void
	 */
	public static function render_manage() {
		global $wpdb;

		if ( ! current_user_can( Postratings_Installer::CAPABILITY ) ) {
			wp_die( esc_html__( 'Access Denied', 'wp-postratings' ) );
		}

		self::require_list_table();

		if ( ! self::$table instanceof Postratings_Logs_Table ) {
			self::$table = new Postratings_Logs_Table();
		}

		self::$table->prepare_items();

		$notices = get_transient( 'postratings_admin_notices' );

		if ( ! empty( $notices ) ) {
			delete_transient( 'postratings_admin_notices' );

			foreach ( (array) $notices as $notice ) {
				printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $notice ) );
			}
		}

		$total_users = (int) $wpdb->get_var( "SELECT SUM(meta_value) FROM {$wpdb->postmeta} WHERE meta_key = 'ratings_users'" );
		$total_score = (int) $wpdb->get_var( "SELECT SUM((meta_value+0.00)) FROM {$wpdb->postmeta} WHERE meta_key = 'ratings_score'" );
		$total_avg   = $total_users > 0 ? $total_score / $total_users : 0;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Manage Ratings', 'wp-postratings' ); ?></h1>

			<h2><?php esc_html_e( 'Rating Logs', 'wp-postratings' ); ?></h2>
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_MANAGE ); ?>" />
				<?php
				self::$table->search_box( __( 'Search Logs', 'wp-postratings' ), 'postratings' );
				self::$table->display();
				?>
			</form>

			<h2><?php esc_html_e( 'Rating Stats', 'wp-postratings' ); ?></h2>
			<table class="widefat striped">
				<tr>
					<th><?php esc_html_e( 'Total Users Voted:', 'wp-postratings' ); ?></th>
					<td><?php echo esc_html( number_format_i18n( $total_users ) ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Total Score:', 'wp-postratings' ); ?></th>
					<td><?php echo esc_html( number_format_i18n( $total_score ) ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Total Average:', 'wp-postratings' ); ?></th>
					<td><?php echo esc_html( number_format_i18n( $total_avg, 2 ) ); ?></td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Delete Ratings Data/Logs', 'wp-postratings' ); ?></h2>
			<form method="post" action="<?php echo esc_url( add_query_arg( 'page', self::PAGE_MANAGE, admin_url( 'admin.php' ) ) ); ?>">
				<?php wp_nonce_field( 'wp-postratings_logs' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="postratings_delete_datalog"><?php esc_html_e( 'Delete Type:', 'wp-postratings' ); ?></label>
						</th>
						<td>
							<select id="postratings_delete_datalog" name="delete_datalog">
								<option value="1"><?php esc_html_e( 'Logs Only', 'wp-postratings' ); ?></option>
								<option value="2"><?php esc_html_e( 'Data Only', 'wp-postratings' ); ?></option>
								<option value="3"><?php esc_html_e( 'Logs And Data', 'wp-postratings' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="postratings_delete_postid"><?php esc_html_e( 'Post ID(s):', 'wp-postratings' ); ?></label>
						</th>
						<td>
							<input type="text" id="postratings_delete_postid" name="delete_postid" size="20" dir="ltr" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Separate each Post ID with a comma, for example 2,3,4. Type "all" to clear every post.', 'wp-postratings' ); ?></p>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" name="postratings_delete" value="1" class="button button-secondary"
						data-confirm="<?php esc_attr_e( 'You are about to delete post ratings data/logs. This action is not reversible.', 'wp-postratings' ); ?>"
						id="postratings-delete-data">
						<?php esc_html_e( 'Delete Data/Logs', 'wp-postratings' ); ?>
					</button>
				</p>
			</form>

			<h2><?php esc_html_e( 'Note:', 'wp-postratings' ); ?></h2>
			<ul class="ul-disc">
				<li><?php esc_html_e( '"Logs Only" means the logs generated when a user rates a post.', 'wp-postratings' ); ?></li>
				<li><?php esc_html_e( '"Data Only" means the rating data for the post.', 'wp-postratings' ); ?></li>
				<li><?php esc_html_e( '"Logs And Data" means both the logs generated and the rating data for the post.', 'wp-postratings' ); ?></li>
				<li><?php esc_html_e( 'If your logging method is by IP and Cookie or by Cookie, users may still be unable to rate if they have voted before, as the cookie is still stored on their computer.', 'wp-postratings' ); ?></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Enqueue the admin assets on this plugin's screens.
	 *
	 * @param string $hook_suffix Current screen hook.
	 *
	 * @return void
	 */
	public static function scripts( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, self::screen_hooks(), true ) ) {
			return;
		}

		// The shape previews on the settings screen are the front end control,
		// so they need the front end stylesheet that draws the masks -- without
		// it the picker renders as a list of labels with nothing beside them.
		wp_enqueue_style(
			'wp-postratings',
			WP_POSTRATINGS_URL . 'css/postratings.css',
			array(),
			WP_POSTRATINGS_VERSION
		);

		wp_enqueue_style(
			'wp-postratings-admin',
			WP_POSTRATINGS_URL . 'css/postratings-admin-css.css',
			array( 'wp-postratings' ),
			WP_POSTRATINGS_VERSION
		);

		wp_enqueue_script(
			'wp-postratings-admin',
			WP_POSTRATINGS_URL . 'js/postratings-admin.js',
			array(),
			WP_POSTRATINGS_VERSION,
			true
		);

		wp_localize_script(
			'wp-postratings-admin',
			'postratingsAdminL10n',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'defaultTemplates' => Postratings_Options::defaults()['templates'],
				'updownTemplates'  => Postratings_Settings::updown_templates(),
			)
		);
	}

	/**
	 * Add the Ratings column to the post and page lists.
	 *
	 * @param array $columns Existing columns.
	 *
	 * @return array
	 */
	public static function column_title( $columns ) {
		$columns['ratings'] = esc_html__( 'Ratings', 'wp-postratings' );

		return $columns;
	}

	/**
	 * Fill the Ratings column.
	 *
	 * @param string $column_name Column being rendered.
	 *
	 * @return void
	 */
	public static function column_content( $column_name ) {
		global $post;

		if ( 'ratings' !== $column_name ) {
			return;
		}

		$template = str_replace(
			'%RATINGS_IMAGES_VOTE%',
			'%RATINGS_IMAGES%<br />',
			Postratings_Options::template( 'vote' )
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered template markup, escaped as it is built.
		echo Postratings_Template::expand( $template, $post, null, 0, false );
	}

	/**
	 * Make the Ratings column sortable.
	 *
	 * @param array $columns Sortable columns.
	 *
	 * @return array
	 */
	public static function column_sortable( $columns ) {
		$columns['ratings'] = 'ratings';

		return $columns;
	}

	/**
	 * Sort the post list by rating when asked.
	 *
	 * @param WP_Query $query Query being run.
	 *
	 * @return void
	 */
	public static function sort_by_rating( $query ) {
		if ( ! is_admin() || 'ratings' !== $query->get( 'orderby' ) ) {
			return;
		}

		$query->set( 'meta_key', 'ratings_average' );
		$query->set( 'orderby', 'meta_value_num' );
	}
}
