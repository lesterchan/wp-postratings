<?php
/**
 * WP-PostRatings class-postratings-logs-table.php
 *
 * @package wp-postratings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * The rating log, as a core list table.
 *
 * Replaces roughly five hundred lines of hand-rolled pagination, sort links and
 * filter form. Core handles the paging, the sortable columns and the bulk
 * actions; only the query and the cells are ours.
 *
 * @since 2.0.0
 */
class Postratings_Logs_Table extends WP_List_Table {

	/**
	 * Columns that may be sorted on, mapped to their database column.
	 *
	 * The values are interpolated into ORDER BY, so this allow list is what
	 * keeps that safe -- the request never reaches the query directly.
	 *
	 * @var array
	 */
	private static $sortable_map = array(
		'rating_id'        => 'rating_id',
		'rating_username'  => 'rating_username',
		'rating_rating'    => 'rating_rating',
		'rating_postid'    => 'rating_postid',
		'rating_posttitle' => 'rating_posttitle',
		'rating_timestamp' => 'rating_timestamp',
		'rating_ip'        => 'rating_ip',
		'rating_host'      => 'rating_host',
	);

	/**
	 * Total rows matching the current filters.
	 *
	 * @var int
	 */
	private $total_items = 0;

	/**
	 * Set up the table.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'rating',
				'plural'   => 'ratings',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns and their headings.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'               => '<input type="checkbox" />',
			'rating_id'        => __( 'ID', 'wp-postratings' ),
			'rating_username'  => __( 'Username', 'wp-postratings' ),
			'rating_rating'    => __( 'Rating', 'wp-postratings' ),
			'rating_postid'    => __( 'Post ID', 'wp-postratings' ),
			'rating_posttitle' => __( 'Post Title', 'wp-postratings' ),
			'rating_timestamp' => __( 'Date / Time', 'wp-postratings' ),
			'rating_ip'        => __( 'Hashed IP / Host', 'wp-postratings' ),
		);
	}

	/**
	 * Which columns are sortable.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'rating_id'        => array( 'rating_id', false ),
			'rating_username'  => array( 'rating_username', false ),
			'rating_rating'    => array( 'rating_rating', false ),
			'rating_postid'    => array( 'rating_postid', false ),
			'rating_posttitle' => array( 'rating_posttitle', false ),
			'rating_timestamp' => array( 'rating_timestamp', true ),
			'rating_ip'        => array( 'rating_ip', false ),
			'rating_host'      => array( 'rating_host', false ),
		);
	}

	/**
	 * Bulk actions offered above the table.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete Logs', 'wp-postratings' ),
		);
	}

	/**
	 * Build the query and fetch the current page.
	 *
	 * @return void
	 */
	public function prepare_items() {
		global $wpdb;

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$per_page = $this->get_items_per_page( 'postratings_logs_per_page', 20 );
		$paged    = $this->get_pagenum();

		$filters = self::current_filters();

		$where  = '1=1';
		$values = array();

		if ( $filters['post_id'] > 0 ) {
			$where   .= ' AND rating_postid = %d';
			$values[] = $filters['post_id'];
		}

		if ( '' !== $filters['user'] ) {
			$where   .= ' AND rating_username = %s';
			$values[] = $filters['user'];
		}

		if ( $filters['rating'] > 0 ) {
			$where   .= ' AND rating_rating = %d';
			$values[] = $filters['rating'];
		}

		if ( '' !== $filters['search'] ) {
			$where   .= ' AND (rating_posttitle LIKE %s OR rating_username LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$values[] = $like;
			$values[] = $like;
		}

		$count_sql = "SELECT COUNT(rating_id) FROM {$wpdb->ratings} WHERE $where";

		$this->total_items = (int) ( empty( $values )
			? $wpdb->get_var( $count_sql )
			: $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) ) );

		// Both halves come from the allow list above, never from the request.
		$orderby = self::current_orderby();
		$order   = self::current_order();

		$sql = "SELECT * FROM {$wpdb->ratings} WHERE $where ORDER BY $orderby $order LIMIT %d, %d";

		$this->items = $wpdb->get_results(
			$wpdb->prepare( $sql, array_merge( $values, array( ( $paged - 1 ) * $per_page, $per_page ) ) )
		);

		$this->set_pagination_args(
			array(
				'total_items' => $this->total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $this->total_items / $per_page ),
			)
		);
	}

	/**
	 * The filters currently applied, read from the request.
	 *
	 * @return array
	 */
	public static function current_filters() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading filters to decide what to list; nothing is written.
		return array(
			'post_id' => isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0,
			'rating'  => isset( $_REQUEST['rating'] ) ? absint( $_REQUEST['rating'] ) : 0,
			'user'    => isset( $_REQUEST['user'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['user'] ) ) : '',
			'search'  => isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * The column to sort on, resolved through the allow list.
	 *
	 * @return string
	 */
	private static function current_orderby() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : '';

		return isset( self::$sortable_map[ $orderby ] ) ? self::$sortable_map[ $orderby ] : 'rating_timestamp';
	}

	/**
	 * The sort direction.
	 *
	 * @return string
	 */
	private static function current_order() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order = isset( $_REQUEST['order'] ) ? strtoupper( sanitize_key( wp_unslash( $_REQUEST['order'] ) ) ) : '';

		return 'ASC' === $order ? 'ASC' : 'DESC';
	}

	/**
	 * The row checkbox.
	 *
	 * @param object $item Log row.
	 *
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="rating_id[]" value="%d" />', (int) $item->rating_id );
	}

	/**
	 * Fallback renderer for the plain columns.
	 *
	 * @param object $item        Log row.
	 * @param string $column_name Column being rendered.
	 *
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'rating_id':
				return (int) $item->rating_id;

			case 'rating_username':
				// Rows written before 2.0.0 stored the name slashed.
				return esc_html( stripslashes( $item->rating_username ) );

			case 'rating_postid':
				return esc_html( number_format_i18n( (int) $item->rating_postid ) );

			case 'rating_posttitle':
				$permalink = get_permalink( (int) $item->rating_postid );
				$title     = esc_html( stripslashes( $item->rating_posttitle ) );

				return $permalink
					? '<a href="' . esc_url( $permalink ) . '" target="_blank" rel="noopener">' . $title . '</a>'
					: $title;

			case 'rating_timestamp':
				return esc_html(
					mysql2date(
						/* translators: 1: date format, 2: time format. */
						sprintf( __( '%1$s @ %2$s', 'wp-postratings' ), get_option( 'date_format' ), get_option( 'time_format' ) ),
						gmdate( 'Y-m-d H:i:s', (int) $item->rating_timestamp )
					)
				);

			case 'rating_ip':
				return esc_html( $item->rating_ip ) . ' / ' . esc_html( $item->rating_host );
		}

		return '';
	}

	/**
	 * The rating column, drawn with the rating images.
	 *
	 * @param object $item Log row.
	 *
	 * @return string
	 */
	public function column_rating_rating( $item ) {
		$options = Postratings_Options::get();
		$rating  = (int) $item->rating_rating;

		if ( $options['customrating'] && 2 === (int) $options['max'] ) {
			return esc_html( $rating > 0 ? '+' . $rating : (string) $rating );
		}

		/* translators: 1: rating given, 2: top of the scale. */
		$alt = sprintf( __( 'User rated this %1$s out of %2$s', 'wp-postratings' ), $rating, $options['max'] );

		return Postratings_Template::ratings_images(
			(int) $options['customrating'],
			(int) $options['max'],
			$rating,
			$options['image'],
			$alt,
			0
		);
	}

	/**
	 * Message shown when nothing matches.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No Rating Logs Found', 'wp-postratings' );
	}

	/**
	 * The filter controls above the table.
	 *
	 * @param string $which Top or bottom.
	 *
	 * @return void
	 */
	protected function extra_tablenav( $which ) {
		global $wpdb;

		if ( 'top' !== $which ) {
			return;
		}

		$filters = self::current_filters();

		$users = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT rating_username, rating_userid FROM {$wpdb->ratings} WHERE rating_username != %s ORDER BY rating_userid ASC, rating_username ASC",
				__( 'Guest', 'wp-postratings' )
			)
		);

		$ratings = $wpdb->get_col( "SELECT DISTINCT rating_rating FROM {$wpdb->ratings} ORDER BY rating_rating ASC" );
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="postratings-filter-id"><?php esc_html_e( 'Post ID', 'wp-postratings' ); ?></label>
			<input type="number" min="0" id="postratings-filter-id" name="id" value="<?php echo esc_attr( $filters['post_id'] ? $filters['post_id'] : '' ); ?>"
				placeholder="<?php esc_attr_e( 'Post ID', 'wp-postratings' ); ?>" class="small-text" />

			<label class="screen-reader-text" for="postratings-filter-user"><?php esc_html_e( 'Username', 'wp-postratings' ); ?></label>
			<select id="postratings-filter-user" name="user">
				<option value=""><?php esc_html_e( 'All users', 'wp-postratings' ); ?></option>
				<?php foreach ( (array) $users as $user ) : ?>
					<?php $username = stripslashes( $user->rating_username ); ?>
					<option value="<?php echo esc_attr( $username ); ?>" <?php selected( $username, $filters['user'] ); ?>>
						<?php
						echo esc_html(
							( (int) $user->rating_userid > 0
								? __( 'Registered User:', 'wp-postratings' )
								: __( 'Comment Author:', 'wp-postratings' ) ) . ' ' . $username
						);
						?>
					</option>
				<?php endforeach; ?>
			</select>

			<label class="screen-reader-text" for="postratings-filter-rating"><?php esc_html_e( 'Rating', 'wp-postratings' ); ?></label>
			<select id="postratings-filter-rating" name="rating">
				<option value=""><?php esc_html_e( 'All ratings', 'wp-postratings' ); ?></option>
				<?php foreach ( (array) $ratings as $rating ) : ?>
					<option value="<?php echo esc_attr( $rating ); ?>" <?php selected( (int) $rating, $filters['rating'] ); ?>>
						<?php
						/* translators: %s: rating value. */
						printf( esc_html__( 'Rating: %s', 'wp-postratings' ), esc_html( number_format_i18n( $rating ) ) );
						?>
					</option>
				<?php endforeach; ?>
			</select>

			<?php submit_button( __( 'Filter', 'wp-postratings' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}
}
