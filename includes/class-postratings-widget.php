<?php
/**
 * WP-PostRatings class-postratings-widget.php
 *
 * @package wp-postratings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sidebar widget listing the top rated posts.
 *
 * @since 2.0.0
 */
class Postratings_Widget extends WP_Widget {

	/**
	 * Register the widget.
	 */
	public function __construct() {
		parent::__construct(
			'ratings-widget',
			esc_html__( 'Ratings', 'wp-postratings' ),
			array(
				'description'                 => esc_html__( 'WP-PostRatings ratings statistics', 'wp-postratings' ),
				'customize_selective_refresh' => true,
			)
		);
	}

	/**
	 * Default instance settings.
	 *
	 * @return array
	 */
	private function defaults() {
		return array(
			'title'      => esc_html__( 'Ratings', 'wp-postratings' ),
			'type'       => 'highest_rated',
			'mode'       => '',
			'limit'      => 10,
			'min_votes'  => 0,
			'chars'      => 200,
			'cat_ids'    => '0',
			'time_range' => '1 day',
		);
	}

	/**
	 * Every list the widget can show, and how to build it.
	 *
	 * @return array
	 */
	private function types() {
		return array(
			'most_rated'                   => array( __( 'Most Rated', 'wp-postratings' ), 'most', 'meta', '', 'mostrated' ),
			'most_rated_category'          => array( __( 'Most Rated By Category', 'wp-postratings' ), 'most', 'meta', 'category', 'mostrated' ),
			'most_rated_range'             => array( __( 'Most Rated By Time Range', 'wp-postratings' ), 'most', 'range', '', 'mostrated' ),
			'most_rated_range_category'    => array( __( 'Most Rated By Time Range And Category', 'wp-postratings' ), 'most', 'range', 'category', 'mostrated' ),
			'highest_rated'                => array( __( 'Highest Rated', 'wp-postratings' ), 'highest', 'meta', '', 'highestrated' ),
			'highest_rated_category'       => array( __( 'Highest Rated By Category', 'wp-postratings' ), 'highest', 'meta', 'category', 'highestrated' ),
			'highest_rated_range'          => array( __( 'Highest Rated By Time Range', 'wp-postratings' ), 'highest', 'range', '', 'highestrated' ),
			'highest_rated_range_category' => array( __( 'Highest Rated By Time Range And Category', 'wp-postratings' ), 'highest', 'range', 'category', 'highestrated' ),
			'lowest_rated'                 => array( __( 'Lowest Rated', 'wp-postratings' ), 'lowest', 'meta', '', 'highestrated' ),
			'lowest_rated_category'        => array( __( 'Lowest Rated By Category', 'wp-postratings' ), 'lowest', 'meta', 'category', 'highestrated' ),
			'lowest_rated_range'           => array( __( 'Lowest Rated By Time Range', 'wp-postratings' ), 'lowest', 'range', '', 'highestrated' ),
			'highest_score'                => array( __( 'Highest Score', 'wp-postratings' ), 'score', 'meta', '', 'highestrated' ),
			'highest_score_category'       => array( __( 'Highest Score By Category', 'wp-postratings' ), 'score', 'meta', 'category', 'highestrated' ),
			'highest_score_range'          => array( __( 'Highest Score By Time Range', 'wp-postratings' ), 'score', 'range', '', 'highestrated' ),
			'highest_score_range_category' => array( __( 'Highest Score By Time Range And Category', 'wp-postratings' ), 'score', 'range', 'category', 'highestrated' ),
		);
	}

	/**
	 * Render the widget.
	 *
	 * @param array $args     Sidebar arguments.
	 * @param array $instance Widget settings.
	 *
	 * @return void
	 */
	public function widget( $args, $instance ) {
		// An unconfigured widget has none of these keys, which is a warning per
		// key on PHP 8 rather than an empty widget.
		$instance = wp_parse_args( (array) $instance, $this->defaults() );

		$types = $this->types();
		$type  = isset( $types[ $instance['type'] ] ) ? $instance['type'] : 'highest_rated';

		list( , $order, $source, $taxonomy, $template ) = $types[ $type ];

		$title = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sidebar chrome supplied by the theme; the title is escaped.
		echo $args['before_widget'] . $args['before_title'] . esc_html( $title ) . $args['after_title'];
		echo '<ul>' . "\n";

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered list markup, escaped as it is built.
		echo Postratings_Stats::output(
			array(
				'source'    => $source,
				'order'     => $order,
				'mode'      => $instance['mode'],
				'min_votes' => (int) $instance['min_votes'],
				'limit'     => (int) $instance['limit'],
				'time'      => $instance['time_range'],
				'taxonomy'  => $taxonomy,
				'terms'     => array_map( 'intval', explode( ',', (string) $instance['cat_ids'] ) ),
			),
			$template,
			(int) $instance['chars'],
			false
		);

		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

		echo '</ul>' . "\n";
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sidebar chrome supplied by the theme.
		echo $args['after_widget'];
	}

	/**
	 * Save the widget settings.
	 *
	 * @param array $new_instance Submitted settings.
	 * @param array $old_instance Stored settings.
	 *
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = wp_parse_args( (array) $old_instance, $this->defaults() );
		$types    = $this->types();

		$instance['title']      = isset( $new_instance['title'] ) ? wp_strip_all_tags( $new_instance['title'] ) : '';
		$instance['mode']       = isset( $new_instance['mode'] ) ? sanitize_key( $new_instance['mode'] ) : '';
		$instance['limit']      = isset( $new_instance['limit'] ) ? absint( $new_instance['limit'] ) : 10;
		$instance['min_votes']  = isset( $new_instance['min_votes'] ) ? absint( $new_instance['min_votes'] ) : 0;
		$instance['chars']      = isset( $new_instance['chars'] ) ? absint( $new_instance['chars'] ) : 200;
		$instance['time_range'] = isset( $new_instance['time_range'] ) ? sanitize_text_field( $new_instance['time_range'] ) : '1 day';

		$type             = isset( $new_instance['type'] ) ? sanitize_key( $new_instance['type'] ) : '';
		$instance['type'] = isset( $types[ $type ] ) ? $type : $instance['type'];

		$cat_ids             = isset( $new_instance['cat_ids'] ) ? wp_parse_id_list( $new_instance['cat_ids'] ) : array();
		$instance['cat_ids'] = implode( ',', $cat_ids );

		return $instance;
	}

	/**
	 * The widget settings form.
	 *
	 * @param array $instance Stored settings.
	 *
	 * @return void
	 */
	public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, $this->defaults() );

		$post_types = get_post_types( array( 'public' => true ) );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'wp-postratings' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>"><?php esc_html_e( 'Statistics Type:', 'wp-postratings' ); ?></label>
			<select name="<?php echo esc_attr( $this->get_field_name( 'type' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>" class="widefat">
				<?php foreach ( $this->types() as $key => $type ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $key, $instance['type'] ); ?>><?php echo esc_html( $type[0] ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'mode' ) ); ?>"><?php esc_html_e( 'Include Ratings From:', 'wp-postratings' ); ?></label>
			<select name="<?php echo esc_attr( $this->get_field_name( 'mode' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'mode' ) ); ?>" class="widefat">
				<option value=""<?php selected( '', $instance['mode'] ); ?>><?php esc_html_e( 'All', 'wp-postratings' ); ?></option>
				<?php foreach ( $post_types as $post_type ) : ?>
					<option value="<?php echo esc_attr( $post_type ); ?>"<?php selected( $post_type, $instance['mode'] ); ?>>
						<?php
						/* translators: %s: post type name. */
						printf( esc_html__( '%s Only', 'wp-postratings' ), esc_html( ucfirst( $post_type ) ) );
						?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"><?php esc_html_e( 'No. Of Records To Show:', 'wp-postratings' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'limit' ) ); ?>" type="number" min="1" value="<?php echo esc_attr( $instance['limit'] ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'min_votes' ) ); ?>"><?php esc_html_e( 'Minimum Votes:', 'wp-postratings' ); ?> <span style="color: red;">*</span></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'min_votes' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'min_votes' ) ); ?>" type="number" min="0" value="<?php echo esc_attr( $instance['min_votes'] ); ?>" />
			<span class="description"><?php esc_html_e( 'The minimum number of votes, before the rating displayed.', 'wp-postratings' ); ?></span>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'chars' ) ); ?>"><?php esc_html_e( 'Maximum Title Length (Characters):', 'wp-postratings' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'chars' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'chars' ) ); ?>" type="number" min="0" value="<?php echo esc_attr( $instance['chars'] ); ?>" />
			<span class="description">
				<?php
				printf(
					/* translators: %s: the number zero, wrapped in <strong>. */
					esc_html__( '%s to disable.', 'wp-postratings' ),
					'<strong>0</strong>'
				);
				?>
			</span>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'cat_ids' ) ); ?>"><?php esc_html_e( 'Category IDs:', 'wp-postratings' ); ?> <span style="color: red;">**</span></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'cat_ids' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'cat_ids' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['cat_ids'] ); ?>" />
			<span class="description"><?php esc_html_e( 'Separate multiple categories with commas.', 'wp-postratings' ); ?></span>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'time_range' ) ); ?>"><?php esc_html_e( 'Time Range:', 'wp-postratings' ); ?> <span style="color: red;">**</span></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'time_range' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'time_range' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['time_range'] ); ?>" />
			<span class="description">
				<?php
				printf(
					/* translators: 1, 2, 3: example time ranges, wrapped in <strong>. */
					esc_html__( 'Use values like %1$s, %2$s, %3$s.', 'wp-postratings' ),
					'<strong>1 day</strong>',
					'<strong>2 weeks</strong>',
					'<strong>1 month</strong>'
				);
				?>
			</span>
		</p>
		<p style="color: red;">
			<span class="description"><?php esc_html_e( '* Time range statistics do not support the Minimum Votes field, you can ignore it.', 'wp-postratings' ); ?></span><br />
			<span class="description"><?php esc_html_e( '** If you are not using any category or time range statistics, you can ignore it.', 'wp-postratings' ); ?></span>
		</p>
		<?php
	}
}
