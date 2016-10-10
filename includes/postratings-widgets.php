<?php
/**
 * WP-PostRatings Widgets.
 *
 * @package WordPress
 * @subpackage WP-PostRatings Plugin
 */


/**
 * Security check
 * Prevent direct access to the file.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


### Class: WP-PostRatings Widget
class WP_Widget_PostRatings extends WP_Widget {

    // Constructor
    function __construct() {
        parent::__construct(
			'ratings-widget',
			esc_html__('Ratings', 'wp-postratings'),
			array(
				'description' => esc_html__('WP-PostRatings ratings statistics', 'wp-postratings')
			)
		);
    }

    // Display Widget
    function widget($args, $instance) {
        $title = apply_filters('widget_title', esc_attr($instance['title']));
        $type = esc_attr($instance['type']);
        $mode = esc_attr($instance['mode']);
        $limit = intval($instance['limit']);
        $min_votes = intval($instance['min_votes']);
        $chars = intval($instance['chars']);
        $cat_ids = array_map( 'intval', explode(',', esc_attr($instance['cat_ids'])) );
        $time_range = esc_attr($instance['time_range']);
        echo $args['before_widget'].$args['before_title'].$title.$args['after_title'];
        echo '<ul>'."\n";
        switch($type) {
            case 'most_rated':
                get_most_rated($mode, $min_votes, $limit, $chars);
                break;
            case 'most_rated_category':
                get_most_rated($cat_ids, $mode, $min_votes, $limit, $chars);
                break;
            case 'most_rated_range':
                get_most_rated_range($time_range, $mode, $limit, $chars);
                break;
            case 'most_rated_range_category':
                get_most_rated_range_category($time_range, $cat_ids, $mode, $limit, $chars);
                break;
            case 'highest_rated':
                get_highest_rated($mode, $min_votes, $limit, $chars);
                break;
            case 'highest_rated_category':
                get_highest_rated_category($cat_ids, $mode, $min_votes, $limit, $chars);
                break;
            case 'highest_rated_range':
                get_highest_rated_range($time_range, $mode, $limit, $chars);
                break;
            case 'highest_rated_range_category':
                get_highest_rated_range_category($time_range, $cat_ids, $mode, $limit, $chars);
                break;
            case 'lowest_rated':
                get_lowest_rated($mode, $min_votes, $limit, $chars);
                break;
            case 'lowest_rated_category':
                get_lowest_rated_category($cat_ids, $mode, $min_votes, $limit, $chars);
                break;
            case 'lowest_rated_range':
                get_lowest_rated_range($time_range, $mode, $limit, $chars);
                break;
            case 'highest_score':
                get_highest_score($mode, $min_votes, $limit, $chars);
                break;
            case 'highest_score_category':
                get_highest_score_category($cat_ids, $mode, $min_votes, $limit, $chars);
                break;
            case 'highest_score_range':
                get_highest_score_range($time_range, $mode, $limit, $chars);
                break;
            case 'highest_score_range_category':
                get_highest_score_range_category($time_range, $cat_ids, $mode, $limit, $chars);
                break;
        }
        echo '</ul>'."\n";
        echo $args['after_widget'];
    }

    // When Widget Control Form Is Posted
    function update($new_instance, $old_instance) {
        if (!isset($new_instance['submit'])) {
            return false;
        }
        $instance = $old_instance;
        $instance['title'] = strip_tags($new_instance['title']);
        $instance['type'] = strip_tags($new_instance['type']);
        $instance['mode'] = strip_tags($new_instance['mode']);
        $instance['limit'] = intval($new_instance['limit']);
        $instance['min_votes'] = intval($new_instance['min_votes']);
        $instance['chars'] = intval($new_instance['chars']);
        $instance['cat_ids'] = strip_tags($new_instance['cat_ids']);
        $instance['time_range'] = strip_tags($new_instance['time_range']);
        return $instance;
    }

    // Display Widget Control Form
    function form($instance) {
        global $wpdb;
        $instance = wp_parse_args((array) $instance, array('title' => esc_html__('Ratings', 'wp-postratings'), 'type' => 'highest_rated', 'mode' => '', 'limit' => 10, 'min_votes' => 0, 'chars' => 200, 'cat_ids' => '0', 'time_range' => '1 day'));
        $title = esc_attr($instance['title']);
        $type = esc_attr($instance['type']);
        $mode = trim( esc_attr( $instance['mode'] ) );
        $limit = intval($instance['limit']);
        $min_votes = intval($instance['min_votes']);
        $chars = intval($instance['chars']);
        $cat_ids = esc_attr($instance['cat_ids']);
        $time_range = esc_attr($instance['time_range']);
        $post_types = get_post_types( array(
            'public' => true
        ) );
?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php esc_html_e('Title:', 'wp-postratings'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('type'); ?>"><?php esc_html_e('Statistics Type:', 'wp-postratings'); ?></label>
			<select name="<?php echo $this->get_field_name('type'); ?>" id="<?php echo $this->get_field_id('type'); ?>" class="widefat">
				<option value="most_rated"<?php selected('most_rated', $type); ?>><?php esc_html_e('Most Rated', 'wp-postratings'); ?></option>
				<option value="most_rated_category"<?php selected('most_rated_category', $type); ?>><?php esc_html_e('Most Rated By Category', 'wp-postratings'); ?></option>
				<option value="most_rated_range"<?php selected('most_rated_range', $type); ?>><?php esc_html_e('Most Rated By Time Range', 'wp-postratings'); ?></option>
				<option value="most_rated_range_category"<?php selected('most_rated_range_category', $type); ?>><?php esc_html_e('Most Rated By Time Range And Category', 'wp-postratings'); ?></option>
				<optgroup>&nbsp;</optgroup>
				<option value="highest_rated"<?php selected('highest_rated', $type); ?>><?php esc_html_e('Highest Rated', 'wp-postratings'); ?></option>
				<option value="highest_rated_category"<?php selected('highest_rated_category', $type); ?>><?php esc_html_e('Highest Rated By Category', 'wp-postratings'); ?></option>
				<option value="highest_rated_range"<?php selected('highest_rated_range', $type); ?>><?php esc_html_e('Highest Rated By Time Range', 'wp-postratings'); ?></option>
				<option value="highest_rated_range_category"<?php selected('highest_rated_range_category', $type); ?>><?php esc_html_e('Highest Rated By Time Range And Category', 'wp-postratings'); ?></option>
				<optgroup>&nbsp;</optgroup>
				<option value="lowest_rated"<?php selected('lowest_rated', $type); ?>><?php esc_html_e('Lowest Rated', 'wp-postratings'); ?></option>
				<option value="lowest_rated_category"<?php selected('lowest_rated_category', $type); ?>><?php esc_html_e('Lowest Rated By Category', 'wp-postratings'); ?></option>
				<option value="lowest_rated_range"<?php selected('lowest_rated_range', $type); ?>><?php esc_html_e('Lowest Rated By Time Range', 'wp-postratings'); ?></option>
				<optgroup>&nbsp;</optgroup>
				<option value="highest_score"<?php selected('highest_score', $type); ?>><?php esc_html_e('Highest Score', 'wp-postratings'); ?></option>
				<option value="highest_score_category"<?php selected('highest_score_category', $type); ?>><?php esc_html_e('Highest Score By Category', 'wp-postratings'); ?></option>
				<option value="highest_score_range"<?php selected('highest_score_range', $type); ?>><?php esc_html_e('Highest Score By Time Range', 'wp-postratings'); ?></option>
				<option value="highest_score_range_category"<?php selected('highest_score_range_category', $type); ?>><?php esc_html_e('Highest Score By Time Range And Category', 'wp-postratings'); ?></option>
			</select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('mode'); ?>"><?php esc_html_e('Include Ratings From:', 'wp-postratings'); ?></label>
			<select name="<?php echo $this->get_field_name('mode'); ?>" id="<?php echo $this->get_field_id('mode'); ?>" class="widefat">
				<option value=""<?php selected( '', $mode ); ?>><?php esc_html_e( 'All', 'wp-postratings' ); ?></option>
					<?php if( $post_types > 0 ): ?>
						<?php foreach( $post_types as $post_type ): ?>
							<option value="<?php echo $post_type; ?>"<?php selected( $post_type, $mode ); ?>><?php printf( esc_html__( '%s Only', 'wp-postratings' ), ucfirst( $post_type ) ); ?></option>
						<?php endforeach; ?>
					<?php endif; ?>
			</select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('limit'); ?>"><?php esc_html_e('No. Of Records To Show:', 'wp-postratings'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('limit'); ?>" name="<?php echo $this->get_field_name('limit'); ?>" type="text" value="<?php echo $limit; ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('min_votes'); ?>"><?php esc_html_e('Minimum Votes:', 'wp-postratings'); ?> <span style="color: red;">*</span></label>
			<input class="widefat" id="<?php echo $this->get_field_id('min_votes'); ?>" name="<?php echo $this->get_field_name('min_votes'); ?>" type="text" value="<?php echo $min_votes; ?>" size="4" />
            <span class="description"><?php esc_html_e('The minimum number of votes, before the rating displayed.', 'wp-postratings'); ?></span>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('chars'); ?>"><?php esc_html_e('Maximum Title Length (Characters):', 'wp-postratings'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('chars'); ?>" name="<?php echo $this->get_field_name('chars'); ?>" type="text" value="<?php echo $chars; ?>" />
            <span class="description"><?php esc_html_e('<strong>0</strong> to disable.', 'wp-postratings'); ?></span>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('cat_ids'); ?>"><?php esc_html_e('Category IDs:', 'wp-postratings'); ?> <span style="color: red;">**</span></label>
			<input class="widefat" id="<?php echo $this->get_field_id('cat_ids'); ?>" name="<?php echo $this->get_field_name('cat_ids'); ?>" type="text" value="<?php echo $cat_ids; ?>" />
            <span class="description"><?php esc_html_e('Seperate mutiple categories with commas.', 'wp-postratings'); ?></span>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('time_range'); ?>"><?php esc_html_e('Time Range:', 'wp-postratings'); ?> <span style="color: red;">**</span></label>
			<input class="widefat" id="<?php echo $this->get_field_id('time_range'); ?>" name="<?php echo $this->get_field_name('time_range'); ?>" type="text" value="<?php echo $time_range; ?>" />
            <span class="description"><?php esc_html_e('Use values like <strong>1 day</strong>, <strong>2 weeks</strong>, <strong>1 month</strong>.', 'wp-postratings'); ?></span>
        </p>
        <p style="color: red;">
            <span class="description"><?php esc_html_e('* Time range statistics does not support Minimum Votes field, you can ignore that it.', 'wp-postratings'); ?></span><br />
            <span class="description"><?php esc_html_e('** If you are not using any category or time range statistics, you can ignore it.', 'wp-postratings'); ?></span>
        <p>
        <input type="hidden" id="<?php echo $this->get_field_id('submit'); ?>" name="<?php echo $this->get_field_name('submit'); ?>" value="1" />
<?php
    }
}

### Function: Init WP-PostRatings Widget
function widget_ratings_init() {
    register_widget('WP_Widget_PostRatings');
}
add_action('widgets_init', 'widget_ratings_init');
