<?php
/**
 * WP-PostRatings Stats.
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


### Function: Display Most Rated Page/Post
if(!function_exists('get_most_rated')) {
	function get_most_rated($mode = '', $min_votes = 0, $limit = 10, $chars = 0, $display = true) {
		global $wpdb;
		$ratings_max = intval(get_option('postratings_max'));
		$ratings_custom = intval(get_option('postratings_customrating'));
		$output = '';
		if(!empty($mode) && $mode != 'both') {
			$where = $wpdb->prepare( "$wpdb->posts.post_type = %s", $mode );
		} else {
			$where = '1=1';
		}
		if($ratings_custom && $ratings_max == 2) {
			$order_by = 'ratings_score';
		} else {
			$order_by = 'ratings_average';
		}
		$temp = stripslashes(get_option('postratings_template_mostrated'));
		$sql = $wpdb->prepare(
			"SELECT DISTINCT $wpdb->posts.ID, (t1.meta_value+0.00) AS ratings_average, (t2.meta_value+0.00) AS ratings_users, (t3.meta_value+0.00) AS ratings_score FROM $wpdb->posts LEFT JOIN $wpdb->postmeta AS t1 ON t1.post_id = $wpdb->posts.ID LEFT JOIN $wpdb->postmeta As t2 ON t1.post_id = t2.post_id LEFT JOIN $wpdb->postmeta AS t3 ON t3.post_id = $wpdb->posts.ID WHERE t1.meta_key = 'ratings_average' AND t2.meta_key = 'ratings_users' AND t3.meta_key = 'ratings_score' AND $wpdb->posts.post_password = '' AND $wpdb->posts.post_date < NOW() AND $wpdb->posts.post_status = 'publish' AND t2.meta_value >= %d AND $where ORDER BY ratings_users DESC, $order_by DESC LIMIT %d",
			$min_votes,
			$limit
		);

		if ( false === ( $most_rated = wp_cache_get( $cache_key = 'get_most_rated_' . md5($sql), $cache_group = 'wp-postratings' ) ) ) {
			$most_rated = $wpdb->get_results( $sql, ARRAY_A );
			wp_cache_add( $cache_key, $most_rated, $cache_group, HOUR_IN_SECONDS );
		}

		// Prime the post caches if need be.
		_prime_post_caches( wp_list_pluck( $most_rated, 'ID' ) );

		// Add the post objects
		foreach ( $most_rated as $i => $post_rating ) {
			$most_rated[ $i ] = (object) array_merge( $post_rating, (array)get_post( $post_rating['ID'] ) );
		}

		if($most_rated) {
			foreach ($most_rated as $post) {
				$output .= expand_ratings_template($temp, $post, null, $chars, false)."\n";
			}
		} else {
			$output = '<li>'.esc_html__('N/A', 'wp-postratings').'</li>'."\n";
		}
		if($display) {
			echo $output;
		} else {
			return $output;
		}
	}
}


### Function: Display Most Rated Page/Post By Category ID
if(!function_exists('get_most_rated_category')) {
	function get_most_rated_category($category_id = 0, $mode = '', $min_votes = 0, $limit = 10, $chars = 0, $display = true) {
		global $wpdb;
		$ratings_max = intval(get_option('postratings_max'));
		$ratings_custom = intval(get_option('postratings_customrating'));
		$output = '';
		if(is_array($category_id)) {
			$category_id = array_map( 'intval', $category_id );
			$category_sql = "$wpdb->term_taxonomy.term_id IN (".join(',', $category_id).')';
		} else {
			$category_sql = $wpdb->prepare( "$wpdb->term_taxonomy.term_id = %d", $category_id );
		}
		if(!empty($mode) && $mode != 'both') {
			$where = $wpdb->prepare( "$wpdb->posts.post_type = %s", $mode );
		} else {
			$where = '1=1';
		}
		if($ratings_custom && $ratings_max == 2) {
			$order_by = 'ratings_score';
		} else {
			$order_by = 'ratings_average';
		}
		$temp = stripslashes(get_option('postratings_template_mostrated'));
		$sql = $wpdb->prepare(
			"SELECT DISTINCT $wpdb->posts.ID, (t1.meta_value+0.00) AS ratings_average, (t2.meta_value+0.00) AS ratings_users, (t3.meta_value+0.00) AS ratings_score FROM $wpdb->posts LEFT JOIN $wpdb->postmeta AS t1 ON t1.post_id = $wpdb->posts.ID LEFT JOIN $wpdb->postmeta As t2 ON t1.post_id = t2.post_id LEFT JOIN $wpdb->postmeta AS t3 ON t3.post_id = $wpdb->posts.ID INNER JOIN $wpdb->term_relationships ON ($wpdb->posts.ID = $wpdb->term_relationships.object_id) INNER JOIN $wpdb->term_taxonomy ON ($wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id) WHERE t1.meta_key = 'ratings_average' AND t2.meta_key = 'ratings_users' AND t3.meta_key = 'ratings_score' AND $wpdb->posts.post_password = '' AND $wpdb->posts.post_date < NOW() AND $wpdb->posts.post_status = 'publish' AND $wpdb->term_taxonomy.taxonomy = 'category' AND $category_sql AND t2.meta_value >= %d AND $where ORDER BY ratings_users DESC, $order_by DESC LIMIT %d",
			$min_votes,
			$limit
		);

		if ( false === ( $most_rated = wp_cache_get( $cache_key = 'get_most_rated_category_' . md5($sql), $cache_group = 'wp-postratings' ) ) ) {
			$most_rated = $wpdb->get_results( $sql, ARRAY_A );
			wp_cache_add( $cache_key, $most_rated, $cache_group, HOUR_IN_SECONDS );
		}

		// Prime the post caches if need be.
		_prime_post_caches( wp_list_pluck( $most_rated, 'ID' ) );

		// Add the post objects
		foreach ( $most_rated as $i => $post_rating ) {
			$most_rated[ $i ] = (object) array_merge( $post_rating, (array)get_post( $post_rating['ID'] ) );
		}

		if($most_rated) {
			foreach ($most_rated as $post) {
				$output .= expand_ratings_template($temp, $post, null, $chars, false)."\n";
			}
		} else {
			$output = '<li>'.esc_html__('N/A', 'wp-postratings').'</li>'."\n";
		}
		if($display) {
			echo $output;
		} else {
			return $output;
		}
	}
}


### Function: Display Most Rated Page/Post With Time Range
if(!function_exists('get_most_rated_range')) {
	function get_most_rated_range($time = '1 day', $mode = '', $limit = 10, $chars = 0, $display = true) {
		global $wpdb;
		$ratings_max = intval(get_option('postratings_max'));
		$ratings_custom = intval(get_option('postratings_customrating'));
		$min_time = strtotime('-'.$time, current_time('timestamp'));
		$output = '';
		if(!empty($mode) && $mode != 'both') {
			$where = $wpdb->prepare( "$wpdb->posts.post_type = %s", $mode );
		} else {
			$where = '1=1';
		}
		if($ratings_custom && $ratings_max == 2) {
			$order_by = 'ratings_score';
		} else {
			$order_by = 'ratings_average';
		}
		$temp = stripslashes(get_option('postratings_template_mostrated'));
		$sql = $wpdb->prepare(
			"SELECT COUNT($wpdb->ratings.rating_postid) AS ratings_users, SUM($wpdb->ratings.rating_rating) AS ratings_score, ROUND(((SUM($wpdb->ratings.rating_rating)/COUNT($wpdb->ratings.rating_postid))), 2) AS ratings_average, $wpdb->posts.ID FROM $wpdb->posts LEFT JOIN $wpdb->ratings ON $wpdb->ratings.rating_postid = $wpdb->posts.ID WHERE rating_timestamp >= $min_time AND $wpdb->posts.post_password = '' AND $wpdb->posts.post_date < NOW() AND $wpdb->posts.post_status = 'publish' AND $where GROUP BY $wpdb->ratings.rating_postid ORDER BY ratings_users DESC, $order_by DESC LIMIT %d",
			$limit
		);

		if ( false === ( $most_rated = wp_cache_get( $cache_key = 'get_most_rated_range_' . md5($sql), $cache_group = 'wp-postratings' ) ) ) {
			$most_rated = $wpdb->get_results( $sql, ARRAY_A );
			wp_cache_add( $cache_key, $most_rated, $cache_group, HOUR_IN_SECONDS );
		}

		// Prime the post caches if need be.
		_prime_post_caches( wp_list_pluck( $most_rated, 'ID' ) );

		// Add the post objects
		foreach ( $most_rated as $i => $post_rating ) {
			$most_rated[ $i ] = (object) array_merge( $post_rating, (array)get_post( $post_rating['ID'] ) );
		}

		if($most_rated) {
			foreach ($most_rated as $post) {
				$output .= expand_ratings_template($temp, $post, null, $chars, false)."\n";
			}
		} else {
			$output = '<li>'.esc_html__('N/A', 'wp-postratings').'</li>'."\n";
		}
		if($display) {
			echo $output;
		} else {
			return $output;
		}
	}
}


### Function: Display Most Rated Page/Post With Time Range By Category ID
if(!function_exists('get_most_rated_range_category')) {
	function get_most_rated_range_category($time = '1 day', $category_id = 0, $mode = '', $limit = 10, $chars = 0, $display = true) {
		global $wpdb;
		$ratings_max = intval(get_option('postratings_max'));
		$ratings_custom = intval(get_option('postratings_customrating'));
		$min_time = strtotime('-'.$time, current_time('timestamp'));
		$output = '';
		if(is_array($category_id)) {
			$category_id = array_map( 'intval', $category_id );
			// There is a bug with multiple categoies. The number of votes will be multiplied by the number of categories passed in.
			$category_sql = "$wpdb->term_taxonomy.term_id IN (".join(',', $category_id).')';
		} else {
			$category_sql = $wpdb->prepare( "$wpdb->term_taxonomy.term_id = %d", $category_id );
		}
		if(!empty($mode) && $mode != 'both') {
			$where = $wpdb->prepare( "$wpdb->posts.post_type = %s", $mode );
		} else {
			$where = '1=1';
		}
		if($ratings_custom && $ratings_max == 2) {
			$order_by = 'ratings_score';
		} else {
			$order_by = 'ratings_average';
		}
		$temp = stripslashes(get_option('postratings_template_mostrated'));
		$sql = $wpdb->prepare(
			"SELECT COUNT($wpdb->ratings.rating_postid) AS ratings_users, SUM($wpdb->ratings.rating_rating) AS ratings_score, ROUND(((SUM($wpdb->ratings.rating_rating)/COUNT($wpdb->ratings.rating_postid))), 2) AS ratings_average, $wpdb->posts.ID FROM $wpdb->posts LEFT JOIN $wpdb->ratings ON $wpdb->ratings.rating_postid = $wpdb->posts.ID INNER JOIN $wpdb->term_relationships ON ($wpdb->posts.ID = $wpdb->term_relationships.object_id) INNER JOIN $wpdb->term_taxonomy ON ($wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id) WHERE rating_timestamp >= $min_time AND $wpdb->posts.post_password = '' AND $wpdb->posts.post_date < NOW() AND $wpdb->posts.post_status = 'publish' AND $wpdb->term_taxonomy.taxonomy = 'category' AND $category_sql AND $where GROUP BY $wpdb->ratings.rating_postid ORDER BY ratings_users DESC, $order_by DESC LIMIT %d",
			$limit
		);

		if ( false === ( $most_rated = wp_cache_get( $cache_key = 'get_most_rated_range_category_' . md5($sql), $cache_group = 'wp-postratings' ) ) ) {
			$most_rated = $wpdb->get_results( $sql, ARRAY_A );
			wp_cache_add( $cache_key, $most_rated, $cache_group, HOUR_IN_SECONDS );
		}

		// Prime the post caches if need be.
		_prime_post_caches( wp_list_pluck( $most_rated, 'ID' ) );

		// Add the post objects
		foreach ( $most_rated as $i => $post_rating ) {
			$most_rated[ $i ] = (object) array_merge( $post_rating, (array)get_post( $post_rating['ID'] ) );
		}

		if($most_rated) {
			foreach ($most_rated as $post) {
				$output .= expand_ratings_template($temp, $post, null, $chars, false)."\n";
			}
		} else {
			$output = '<li>'.esc_html__('N/A', 'wp-postratings').'</li>'."\n";
		}
		if($display) {
			echo $output;
		} else {
			return $output;
		}
	}
}


### Function: Display Highest Rated Page/Post
if(!function_exists('get_highest_rated')) {
	function get_highest_rated($mode = '', $min_votes = 0, $limit = 10, $chars = 0, $display = true) {
		global $wpdb;
		$ratings_max = intval(get_option('postratings_max'));
		$ratings_custom = intval(get_option('postratings_customrating'));
		$output = '';
		if(!empty($mode) && $mode != 'both') {
			$where = $wpdb->prepare( "$wpdb->posts.post_type = %s", $mode );
		} else {
			$where = '1=1';
		}
		if($ratings_custom && $ratings_max == 2) {
			$order_by = 'ratings_score';
		} else {
			$order_by = 'ratings_average';
		}
		$temp = stripslashes(get_option('postratings_template_highestrated'));
		$sql = $wpdb->prepare(
			"SELECT DISTINCT $wpdb->posts.ID, (t1.meta_value+0.00) AS ratings_average, (t2.meta_value+0.00) AS ratings_users, (t3.meta_value+0.00) AS ratings_score FROM $wpdb->posts LEFT JOIN $wpdb->postmeta AS t1 ON t1.post_id = $wpdb->posts.ID LEFT JOIN $wpdb->postmeta As t2 ON t1.post_id = t2.post_id LEFT JOIN $wpdb->postmeta AS t3 ON t3.post_id = $wpdb->posts.ID WHERE t1.meta_key = 'ratings_average' AND t2.meta_key = 'ratings_users' AND t3.meta_key = 'ratings_score' AND $wpdb->posts.post_password = '' AND $wpdb->posts.post_date < NOW() AND $wpdb->posts.post_status = 'publish' AND t2.meta_value >= %d AND $where ORDER BY $order_by DESC, ratings_users DESC LIMIT %d",
			$min_votes,
			$limit
		);

		if ( false === ( $highest_rated = wp_cache_get( $cache_key = 'get_highest_rated_' . md5($sql), $cache_group = 'wp-postratings' ) ) ) {
			$highest_rated = $wpdb->get_results( $sql, ARRAY_A );
			wp_cache_add( $cache_key, $highest_rated, $cache_group, HOUR_IN_SECONDS );
		}

		// Prime the post caches if need be.
		_prime_post_caches( wp_list_pluck( $highest_rated, 'ID' ) );

		// Add the post objects
		foreach ( $highest_rated as $i => $post_rating ) {
			$highest_rated[ $i ] = (object) array_merge( $post_rating, (array)get_post( $post_rating['ID'] ) );
		}

		if($highest_rated) {
			foreach($highest_rated as $post) {
				$output .= expand_ratings_template($temp, $post, null, $chars, false)."\n";
			}
		} else {
			$output = '<li>'.esc_html__('N/A', 'wp-postratings').'</li>'."\n";
		}
		if($display) {
			echo $output;
		} else {
			return $output;
		}
	}
}


### Function: Display Highest Rated Page/Post By Category ID
if(!function_exists('get_highest_rated_category')) {
	function get_highest_rated_category($category_id = 0, $mode = '', $min_votes = 0, $limit = 10, $chars = 0, $display = true) {
		global $wpdb;
		$ratings_max = intval(get_option('postratings_max'));
		$ratings_custom = intval(get_option('postratings_customrating'));
		$output = '';
		// Code By: Dirceu P. Junior (http://pomoti.com)
		if(is_array($category_id)) {
			$category_id = array_map( 'intval', $category_id );
			$category_sql = "$wpdb->term_taxonomy.term_id IN (".join(',', $category_id).')';
		} else {
			$category_sql = $wpdb->prepare( "$wpdb->term_taxonomy.term_id = %d", $category_id );
		}
		if(!empty($mode) && $mode != 'both') {
			$where = $wpdb->prepare( "$wpdb->posts.post_type = %s", $mode );
		} else {
			$where = '1=1';
		}
		if($ratings_custom && $ratings_max == 2) {
			$order_by = 'ratings_score';
		} else {
			$order_by = 'ratings_average';
		}
		$temp = stripslashes(get_option('postratings_template_highestrated'));
		$sql = $wpdb->prepare(
			"SELECT DISTINCT $wpdb->posts.ID, (t1.meta_value+0.00) AS ratings_average, (t2.meta_value+0.00) AS ratings_users, (t3.meta_value+0.00) AS ratings_score FROM $wpdb->posts LEFT JOIN $wpdb->postmeta AS t1 ON t1.post_id = $wpdb->posts.ID LEFT JOIN $wpdb->postmeta AS t2 ON t1.post_id = t2.post_id LEFT JOIN $wpdb->postmeta AS t3 ON t3.post_id = $wpdb->posts.ID INNER JOIN $wpdb->term_relationships ON ($wpdb->posts.ID = $wpdb->term_relationships.object_id) INNER JOIN $wpdb->term_taxonomy ON ($wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id) WHERE t1.meta_key = 'ratings_average' AND t2.meta_key = 'ratings_users' AND t3.meta_key = 'ratings_score' AND $wpdb->posts.post_password = '' AND $wpdb->posts.post_date < NOW() AND $wpdb->posts.post_status = 'publish' AND $wpdb->term_taxonomy.taxonomy = 'category' AND $category_sql AND t2.meta_value >= %s AND $where ORDER BY $order_by DESC, ratings_users DESC LIMIT %d",
			$min_votes,
			$limit
		);

		if ( false === ( $highest_rated = wp_cache_get( $cache_key = 'get_highest_rated_category_' . md5($sql), $cache_group = 'wp-postratings' ) ) ) {
			$highest_rated = $wpdb->get_results( $sql, ARRAY_A );
			wp_cache_add( $cache_key, $highest_rated, $cache_group, HOUR_IN_SECONDS );
		}

		// Prime the post caches if need be.
		_prime_post_caches( wp_list_pluck( $highest_rated, 'ID' ) );

		// Add the post objects
		foreach ( $highest_rated as $i => $post_rating ) {
			$highest_rated[ $i ] = (object) array_merge( $post_rating, (array)get_post( $post_rating['ID'] ) );
		}

		if($highest_rated) {
			foreach($highest_rated as $post) {
				$output .= expand_ratings_template($temp, $post, null, $chars, false)."\n";
			}
		} else {
			$output = '<li>'.esc_html__('N/A', 'wp-postratings').'</li>'."\n";
		}
		if($display) {
			echo $output;
		} else {
			return $output;
		}
	}
}


### Function: Display Highest Rated Page/Post With Time Range
if(!function_exists('get_highest_rated_range')) {
	function get_highest_rated_range($time = '1 day', $mode = '', $limit = 10, $chars = 0, $display = true) {
		global $wpdb;
		$ratings_max = intval(get_option('postratings_max'));
		$ratings_custom = intval(get_option('postratings_customrating'));
		$min_time = strtotime('-'.$time, current_time('timestamp'));
		$output = '';
		if(!empty($mode) && $mode != 'both') {
			$where = $wpdb->prepare( "$wpdb->posts.post_type = %s", $mode );
		} else {
			$where = '1=1';
		}
		if($ratings_custom && $ratings_max == 2) {
			$order_by = 'ratings_score';
		} else {
			$order_by = 'ratings_average';
		}
		$temp = stripslashes(get_option('postratings_template_highestrated'));
		$sql = $wpdb->prepare(
			"SELECT COUNT($wpdb->ratings.rating_postid) AS ratings_users, SUM($wpdb->ratings.rating_rating) AS ratings_score, ROUND(((SUM($wpdb->ratings.rating_rating)/COUNT($wpdb->ratings.rating_postid))), 2) AS ratings_average, $wpdb->posts.ID FROM $wpdb->posts LEFT JOIN $wpdb->ratings ON $wpdb->ratings.rating_postid = $wpdb->posts.ID WHERE rating_timestamp >= $min_time AND $wpdb->posts.post_password = '' AND $wpdb->posts.post_date < NOW() AND $wpdb->posts.post_status = 'publish' AND $where GROUP BY $wpdb->ratings.rating_postid ORDER BY $order_by DESC, ratings_users DESC LIMIT %d",
			$limit
		);

		if ( false === ( $highest_rated = wp_cache_get( $cache_key = 'get_highest_rated_range_' . md5($sql), $cache_group = 'wp-postratings' ) ) ) {
			$highest_rated = $wpdb->get_results( $sql, ARRAY_A );
			wp_cache_add( $cache_key, $highest_rated, $cache_group, HOUR_IN_SECONDS );
		}

		// Prime the post caches if need be.
		_prime_post_caches( wp_list_pluck( $highest_rated, 'ID' ) );

		// Add the post objects
		foreach ( $highest_rated as $i => $post_rating ) {
			$highest_rated[ $i ] = (object) array_merge( $post_rating, (array)get_post( $post_rating['ID'] ) );
		}

		if($highest_rated) {
			foreach($highest_rated as $post) {
				$output .= expand_ratings_template($temp, $post, null, $chars, false)."\n";
			}
		} else {
			$output = '<li>'.esc_html__('N/A', 'wp-postratings').'</li>'."\n";
		}
		if($display) {
			echo $output;
		} else {
			return $output;
		}
	}
}


### Function: Display Highest Rated Page/Post With Time Range By Category ID
if(!function_exists('get_highest_rated_range_category')) {
	function get_highest_rated_range_category($time = '1 day', $category_id = 0, $mode = '', $limit = 10, $chars = 0, $display = true) {
		global $wpdb;
		$ratings_max = intval(get_option('postratings_max'));
		$ratings_custom = intval(get_option('postratings_customrating'));
		$min_time = strtotime('-'.$time, current_time('timestamp'));
		$output = '';
		// Code By: Dirceu P. Junior (http://pomoti.com)
		if(is_array($category_id)) {
			$category_id = array_map( 'intval', $category_id );
			$category_sql = "$wpdb->term_taxonomy.term_id IN (".join(',', $category_id).')';
		} else {
			$category_sql = $wpdb->prepare( "$wpdb->term_taxonomy.term_id = %d", $category_id );
		}
		if(!empty($mode) && $mode != 'both') {
			$where = $wpdb->prepare( "$wpdb->posts.post_type = %s", $mode );
		} else {
			$where = '1=1';
		}
		if($ratings_custom && $ratings_max == 2) {
			$order_by = 'ratings_score';
		} else {
			$order_by = 'ratings_average';
		}
		$temp = stripslashes(get_option('postratings_template_highestrated'));
		$sql = $wpdb->prepare(
			"SELECT COUNT($wpdb->ratings.rating_postid) AS ratings_users, SUM($wpdb->ratings.rating_rating) AS ratings_score, ROUND(((SUM($wpdb->ratings.rating_rating)/COUNT($wpdb->ratings.rating_postid))), 2) AS ratings_average, $wpdb->posts.ID FROM $wpdb->posts LEFT JOIN $wpdb->ratings ON $wpdb->ratings.rating_postid = $wpdb->posts.ID INNER JOIN $wpdb->term_relationships ON ($wpdb->posts.ID = $wpdb->term_relationships.object_id) INNER JOIN $wpdb->term_taxonomy ON ($wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id) WHERE rating_timestamp >= $min_time AND $wpdb->posts.post_password = '' AND $wpdb->posts.post_date < NOW() AND $wpdb->posts.post_status = 'publish' AND $wpdb->term_taxonomy.taxonomy = 'category' AND $category_sql AND $where GROUP BY $wpdb->ratings.rating_postid ORDER BY $order_by DESC, ratings_users DESC LIMIT %d",
			$limit
		);

		if ( false === ( $highest_rated = wp_cache_get( $cache_key = 'get_highest_rated_range_category_' . md5($sql), $cache_group = 'wp-postratings' ) ) ) {
			$highest_rated = $wpdb->get_results( $sql, ARRAY_A );
			wp_cache_add( $cache_key, $highest_rated, $cache_group, HOUR_IN_SECONDS );
		}

		// Prime the post caches if need be.
		_prime_post_caches( wp_list_pluck( $highest_rated, 'ID' ) );

		// Add the post objects
		foreach ( $highest_rated as $i => $post_rating ) {
			$highest_rated[ $i ] = (object) array_merge( $post_rating, (array)get_post( $post_rating['ID'] ) );
		}

		if($highest_rated) {
			foreach($highest_rated as $post) {
				$output .= expand_ratings_template($temp, $post, null, $chars, false)."\n";
			}
		} else {
			$output = '<li>'.esc_html__('N/A', 'wp-postratings').'</li>'."\n";
		}
		if($display) {
			echo $output;
		} else {
			return $output;
		}
	}
}


### Function: Display Lowest Rated Page/Post
if(!function_exists('get_lowest_rated')) {
	function get_lowest_rated($mode = '', $min_votes = 0, $limit = 10, $chars = 0, $display = true) {
		global $wpdb;
		$ratings_max = intval(get_option('postratings_max'));
		$ratings_custom = intval(get_option('postratings_customrating'));
		$output = '';
		if(!empty($mode) && $mode != 'both') {
			$where = $wpdb->prepare( "$wpdb->posts.post_type = %s", $mode );
		} else {
			$where = '1=1';
		}
		if($ratings_custom && $ratings_max == 2) {
			$order_by = 'ratings_score';
		} else {
			$order_by = 'ratings_average';
		}
		$temp = stripslashes(get_option('postratings_template_highestrated'));
		$sql = $wpdb->prepare(
			"SELECT DISTINCT $wpdb->posts.ID, (t1.meta_value+0.00) AS ratings_average, (t2.meta_value+0.00) AS ratings_users, (t3.meta_value+0.00) AS ratings_score FROM $wpdb->posts LEFT JOIN $wpdb->postmeta AS t1 ON t1.post_id = $wpdb->posts.ID LEFT JOIN $wpdb->postmeta As t2 ON t1.post_id = t2.post_id LEFT JOIN $wpdb->postmeta AS t3 ON t3.post_id = $wpdb->posts.ID WHERE t1.meta_key = 'ratings_average' AND t2.meta_key = 'ratings_users' AND t3.meta_key = 'ratings_score' AND $wpdb->posts.post_password = '' AND $wpdb->posts.post_date < NOW() AND $wpdb->posts.post_status = 'publish' AND t2.meta_value >= %d AND $where ORDER BY $order_by ASC, ratings_users DESC LIMIT %d",
			$min_votes,
			$limit
		);

		if ( false === ( $lowest_rated = wp_cache_get( $cache_key = 'get_lowest_rated_' . md5($sql), $cache_group = 'wp-postratings' ) ) ) {
			$lowest_rated = $wpdb->get_results( $sql, ARRAY_A );
			wp_cache_add( $cache_key, $lowest_rated, $cache_group, HOUR_IN_SECONDS );
		}

		// Prime the post caches if need be.
		_prime_post_caches( wp_list_pluck( $lowest_rated, 'ID' ) );

		// Add the post objects
		foreach ( $lowest_rated as $i => $post_rating ) {
			$lowest_rated[ $i ] = (object) array_merge( $post_rating, (array)get_post( $post_rating['ID'] ) );
		}

		if($lowest_rated) {
			foreach($lowest_rated as $post) {
				$output .= expand_ratings_template($temp, $post, null, $chars, false)."\n";
			}
		} else {
			$output = '<li>'.esc_html__('N/A', 'wp-postratings').'</li>'."\n";
		}
		if($display) {
			echo $output;
		} else {
			return $output;
		}
	}
}


### Function: Display Lowest Rated Page/Post By Category ID
if(!function_exists('get_lowest_rated_category')) {
	function get_lowest_rated_category($category_id = 0, $mode = '', $min_votes = 0, $limit = 10, $chars = 0, $display = true) {
		global $wpdb;
		$ratings_max = intval(get_option('postratings_max'));
		$ratings_custom = intval(get_option('postratings_customrating'));
		$output = '';
		// Code By: Dirceu P. Junior (http://pomoti.com)
		if(is_array($category_id)) {
			$category_id = array_map( 'intval', $category_id );
			$category_sql = "$wpdb->term_taxonomy.term_id IN (".join(',', $category_id).')';
		} else {
			$category_sql = $wpdb->prepare( "$wpdb->term_taxonomy.term_id = %d", $category_id );
		}
		if(!empty($mode) && $mode != 'both') {
			$where = $wpdb->prepare( "$wpdb->posts.post_type = %s", $mode );
		} else {
			$where = '1=1';
		}
		if($ratings_custom && $ratings_max == 2) {
			$order_by = 'ratings_score';
		} else {
			$order_by = 'ratings_average';
		}
		$temp = stripslashes(get_option('postratings_template_highestrated'));
		$sql = $wpdb->prepare(
			"SELECT DISTINCT $wpdb->posts.ID, (t1.meta_value+0.00) AS ratings_average, (t2.meta_value+0.00) AS ratings_users, (t3.meta_value+0.00) AS ratings_score FROM $wpdb->posts LEFT JOIN $wpdb->postmeta AS t1 ON t1.post_id = $wpdb->posts.ID LEFT JOIN $wpdb->postmeta AS t2 ON t1.post_id = t2.post_id LEFT JOIN $wpdb->postmeta AS t3 ON t3.post_id = $wpdb->posts.ID INNER JOIN $wpdb->term_relationships ON ($wpdb->posts.ID = $wpdb->term_relationships.object_id) INNER JOIN $wpdb->term_taxonomy ON ($wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id) WHERE t1.meta_key = 'ratings_average' AND t2.meta_key = 'ratings_users' AND t3.meta_key = 'ratings_score' AND $wpdb->posts.post_password = '' AND $wpdb->posts.post_date < NOW() AND $wpdb->posts.post_status = 'publish' AND $wpdb->term_taxonomy.taxonomy = 'category' AND $category_sql AND t2.meta_value >= %d AND $where ORDER BY $order_by ASC, ratings_users DESC LIMIT %d",
			$min_votes,
			$limit
		);

		if ( false === ( $lowest_rated = wp_cache_get( $cache_key = 'get_lowest_rated_category_' . md5($sql), $cache_group = 'wp-postratings' ) ) ) {
			$lowest_rated = $wpdb->get_results( $sql, ARRAY_A );
			wp_cache_add( $cache_key, $lowest_rated, $cache_group, HOUR_IN_SECONDS );
		}

		// Prime the post caches if need be.
		_prime_post_caches( wp_list_pluck( $lowest_rated, 'ID' ) );

		// Add the post objects
		foreach ( $lowest_rated as $i => $post_rating ) {
			$lowest_rated[ $i ] = (object) array_merge( $post_rating, (array)get_post( $post_rating['ID'] ) );
		}

		if($lowest_rated) {
			foreach($lowest_rated as $post) {
				$output .= expand_ratings_template($temp, $post, null, $chars, false)."\n";
			}
		} else {
			$output = '<li>'.esc_html__('N/A', 'wp-postratings').'</li>'."\n";
		}
		if($display) {
			echo $output;
		} else {
			return $output;
		}
	}
}


### Function: Display Lowest Rated Page/Post With Time Range
if(!function_exists('get_lowest_rated_range')) {
	function get_lowest_rated_range($time = '1 day', $mode = '', $limit = 10, $chars = 0, $display = true) {
		global $wpdb;
		$ratings_max = intval(get_option('postratings_max'));
		$ratings_custom = intval(get_option('postratings_customrating'));
		$min_time = strtotime('-'.$time, current_time('timestamp'));
		$output = '';
		if(!empty($mode) && $mode != 'both') {
			$where = $wpdb->prepare( "$wpdb->posts.post_type = %s", $mode );
		} else {
			$where = '1=1';
		}
		if($ratings_custom && $ratings_max == 2) {
			$order_by = 'ratings_score';
		} else {
			$order_by = 'ratings_average';
		}
		$temp = stripslashes(get_option('postratings_template_highestrated'));
		$sql = $wpdb->prepare(
			"SELECT COUNT($wpdb->ratings.rating_postid) AS ratings_users, SUM($wpdb->ratings.rating_rating) AS ratings_score, ROUND(((SUM($wpdb->ratings.rating_rating)/COUNT($wpdb->ratings.rating_postid))), 2) AS ratings_average, $wpdb->posts.ID FROM $wpdb->posts LEFT JOIN $wpdb->ratings ON $wpdb->ratings.rating_postid = $wpdb->posts.ID WHERE rating_timestamp >= $min_time AND $wpdb->posts.post_password = '' AND $wpdb->posts.post_date < NOW() AND $wpdb->posts.post_status = 'publish' AND $where GROUP BY $wpdb->ratings.rating_postid ORDER BY $order_by ASC, ratings_users DESC LIMIT %d",
			$limit
		);

		if ( false === ( $highest_rated = wp_cache_get( $cache_key = 'get_lowest_rated_range_' . md5($sql), $cache_group = 'wp-postratings' ) ) ) {
			$highest_rated = $wpdb->get_results( $sql, ARRAY_A );
			wp_cache_add( $cache_key, $highest_rated, $cache_group, HOUR_IN_SECONDS );
		}

		// Prime the post caches if need be.
		_prime_post_caches( wp_list_pluck( $highest_rated, 'ID' ) );

		// Add the post objects
		foreach ( $highest_rated as $i => $post_rating ) {
			$highest_rated[ $i ] = (object) array_merge( $post_rating, (array)get_post( $post_rating['ID'] ) );
		}

		if($highest_rated) {
			foreach($highest_rated as $post) {
				$output .= expand_ratings_template($temp, $post, null, $chars, false)."\n";
			}
		} else {
			$output = '<li>'.esc_html__('N/A', 'wp-postratings').'</li>'."\n";
		}
		if($display) {
			echo $output;
		} else {
			return $output;
		}
	}
}

### Function: Display Highest Score Page/Post
if(!function_exists('get_highest_score')) {
	function get_highest_score($mode = '', $min_votes = 0, $limit = 10, $chars = 0, $display = true) {
		global $wpdb;
		$output = '';
		if(!empty($mode) && $mode != 'both') {
			$where = $wpdb->prepare( "$wpdb->posts.post_type = %s", $mode );
		} else {
			$where = '1=1';
		}
		$temp = stripslashes(get_option('postratings_template_highestrated'));
		$sql = $wpdb->prepare(
			"SELECT DISTINCT $wpdb->posts.ID, (t1.meta_value+0.00) AS ratings_average, (t2.meta_value+0.00) AS ratings_users, (t3.meta_value+0.00) AS ratings_score FROM $wpdb->posts LEFT JOIN $wpdb->postmeta AS t1 ON t1.post_id = $wpdb->posts.ID LEFT JOIN $wpdb->postmeta As t2 ON t1.post_id = t2.post_id LEFT JOIN $wpdb->postmeta AS t3 ON t3.post_id = $wpdb->posts.ID WHERE t1.meta_key = 'ratings_average' AND t2.meta_key = 'ratings_users' AND t3.meta_key = 'ratings_score' AND $wpdb->posts.post_password = '' AND $wpdb->posts.post_date < NOW() AND $wpdb->posts.post_status = 'publish' AND t2.meta_value >= %d AND $where ORDER BY ratings_score DESC, ratings_average DESC LIMIT %d",
			$min_votes,
			$limit
		);

		if ( false === ( $highest_score = wp_cache_get( $cache_key = 'get_highest_score_' . md5($sql), $cache_group = 'wp-postratings' ) ) ) {
			$highest_score = $wpdb->get_results( $sql, ARRAY_A );
			wp_cache_add( $cache_key, $highest_score, $cache_group, HOUR_IN_SECONDS );
		}

		// Prime the post caches if need be.
		_prime_post_caches( wp_list_pluck( $highest_score, 'ID' ) );

		// Add the post objects
		foreach ( $highest_score as $i => $post_rating ) {
			$highest_score[ $i ] = (object) array_merge( $post_rating, (array)get_post( $post_rating['ID'] ) );
		}

		if($highest_score) {
			foreach ($highest_score as $post) {
				$output .= expand_ratings_template($temp, $post, null, $chars, false)."\n";
			}
		} else {
			$output = '<li>'.esc_html__('N/A', 'wp-postratings').'</li>'."\n";
		}
		if($display) {
			echo $output;
		} else {
			return $output;
		}
	}
}


### Function: Display Highest Score Page/Post By Category ID
if(!function_exists('get_highest_score_category')) {
	function get_highest_score_category($category_id = 0, $mode = '', $min_votes = 0, $limit = 10, $chars = 0, $display = true) {
		global $wpdb;
		$output = '';
		if(is_array($category_id)) {
			$category_id = array_map( 'intval', $category_id );
			$category_sql = "$wpdb->term_taxonomy.term_id IN (".join(',', $category_id).')';
		} else {
			$category_sql = $wpdb->prepare( "$wpdb->term_taxonomy.term_id = %d", $category_id );
		}
		if(!empty($mode) && $mode != 'both') {
			$where = $wpdb->prepare( "$wpdb->posts.post_type = %s", $mode );
		} else {
			$where = '1=1';
		}
		$temp = stripslashes(get_option('postratings_template_highestrated'));
		$sql = $wpdb->prepare(
			"SELECT DISTINCT $wpdb->posts.ID, (t1.meta_value+0.00) AS ratings_average, (t2.meta_value+0.00) AS ratings_users, (t3.meta_value+0.00) AS ratings_score FROM $wpdb->posts LEFT JOIN $wpdb->postmeta AS t1 ON t1.post_id = $wpdb->posts.ID LEFT JOIN $wpdb->postmeta As t2 ON t1.post_id = t2.post_id LEFT JOIN $wpdb->postmeta AS t3 ON t3.post_id = $wpdb->posts.ID INNER JOIN $wpdb->term_relationships ON ($wpdb->posts.ID = $wpdb->term_relationships.object_id) INNER JOIN $wpdb->term_taxonomy ON ($wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id) WHERE t1.meta_key = 'ratings_average' AND t2.meta_key = 'ratings_users' AND t3.meta_key = 'ratings_score' AND $wpdb->posts.post_password = '' AND $wpdb->posts.post_date < NOW() AND $wpdb->posts.post_status = 'publish' AND $wpdb->term_taxonomy.taxonomy = 'category' AND $category_sql AND t2.meta_value >= %d AND $where ORDER BY ratings_score DESC, ratings_average DESC LIMIT %d",
			$min_votes,
			$limit
		);

		if ( false === ( $highest_score = wp_cache_get( $cache_key = 'get_highest_score_category_' . md5($sql), $cache_group = 'wp-postratings' ) ) ) {
			$highest_score = $wpdb->get_results( $sql, ARRAY_A );
			wp_cache_add( $cache_key, $highest_score, $cache_group, HOUR_IN_SECONDS );
		}

		// Prime the post caches if need be.
		_prime_post_caches( wp_list_pluck( $highest_score, 'ID' ) );

		// Add the post objects
		foreach ( $highest_score as $i => $post_rating ) {
			$highest_score[ $i ] = (object) array_merge( $post_rating, (array)get_post( $post_rating['ID'] ) );
		}

		if($highest_score) {
			foreach ($highest_score as $post) {
				$output .= expand_ratings_template($temp, $post, null, $chars, false)."\n";
			}
		} else {
			$output = '<li>'.esc_html__('N/A', 'wp-postratings').'</li>'."\n";
		}
		if($display) {
			echo $output;
		} else {
			return $output;
		}
	}
}


### Function: Display Highest Score Page/Post With Time Range
if(!function_exists('get_highest_score_range')) {
	function get_highest_score_range($time = '1 day', $mode = '', $limit = 10, $chars = 0, $display = true) {
		global $wpdb;
		$min_time = strtotime('-'.$time, current_time('timestamp'));
		$output = '';
		if(!empty($mode) && $mode != 'both') {
			$where = $wpdb->prepare( "$wpdb->posts.post_type = %s", $mode );
		} else {
			$where = '1=1';
		}
		$temp = stripslashes(get_option('postratings_template_highestrated'));
		$sql = $wpdb->prepare(
			"SELECT COUNT($wpdb->ratings.rating_postid) AS ratings_users, SUM($wpdb->ratings.rating_rating) AS ratings_score, ROUND(((SUM($wpdb->ratings.rating_rating)/COUNT($wpdb->ratings.rating_postid))), 2) AS ratings_average, $wpdb->posts.ID FROM $wpdb->posts LEFT JOIN $wpdb->ratings ON $wpdb->ratings.rating_postid = $wpdb->posts.ID WHERE rating_timestamp >= $min_time AND $wpdb->posts.post_password = '' AND $wpdb->posts.post_date < NOW() AND $wpdb->posts.post_status = 'publish' AND $where GROUP BY $wpdb->ratings.rating_postid ORDER BY ratings_score DESC, ratings_average DESC LIMIT %d",
			$limit
		);

		if ( false === ( $highest_score = wp_cache_get( $cache_key = 'get_highest_score_range_' . md5($sql), $cache_group = 'wp-postratings' ) ) ) {
			$highest_score = $wpdb->get_results( $sql, ARRAY_A );
			wp_cache_add( $cache_key, $highest_score, $cache_group, HOUR_IN_SECONDS );
		}

		// Prime the post caches if need be.
		_prime_post_caches( wp_list_pluck( $highest_score, 'ID' ) );

		// Add the post objects
		foreach ( $highest_score as $i => $post_rating ) {
			$highest_score[ $i ] = (object) array_merge( $post_rating, (array)get_post( $post_rating['ID'] ) );
		}

		if($highest_score) {
			foreach ($highest_score as $post) {
				$output .= expand_ratings_template($temp, $post, null, $chars, false)."\n";
			}
		} else {
			$output = '<li>'.esc_html__('N/A', 'wp-postratings').'</li>'."\n";
		}
		if($display) {
			echo $output;
		} else {
			return $output;
		}
	}
}


### Function: Display Highest Score Page/Post With Time Range By Category ID
if(!function_exists('get_highest_score_range_category')) {
	function get_highest_score_range_category($time = '1 day', $category_id = 0, $mode = '', $limit = 10, $chars = 0, $display = true) {
		global $wpdb;
		$min_time = strtotime('-'.$time, current_time('timestamp'));
		$output = '';
		if(is_array($category_id)) {
			$category_id = array_map( 'intval', $category_id );
			// There is a bug with multiple categoies. The number of votes will be multiplied by the number of categories passed in.
			$category_sql = "$wpdb->term_taxonomy.term_id IN (".join(',', $category_id).')';
		} else {
			$category_sql = $wpdb->prepare( "$wpdb->term_taxonomy.term_id = %d", $category_id );
		}
		if(!empty($mode) && $mode != 'both') {
			$where = $wpdb->prepare( "$wpdb->posts.post_type = %s", $mode );
		} else {
			$where = '1=1';
		}
		$temp = stripslashes(get_option('postratings_template_highestrated'));
		$sql = $wpdb->prepare(
			"SELECT COUNT($wpdb->ratings.rating_postid) AS ratings_users, SUM($wpdb->ratings.rating_rating) AS ratings_score, ROUND(((SUM($wpdb->ratings.rating_rating)/COUNT($wpdb->ratings.rating_postid))), 2) AS ratings_average, $wpdb->posts.ID FROM $wpdb->posts LEFT JOIN $wpdb->ratings ON $wpdb->ratings.rating_postid = $wpdb->posts.ID INNER JOIN $wpdb->term_relationships ON ($wpdb->posts.ID = $wpdb->term_relationships.object_id) INNER JOIN $wpdb->term_taxonomy ON ($wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id) WHERE rating_timestamp >= $min_time AND $wpdb->posts.post_password = '' AND $wpdb->posts.post_date < NOW() AND $wpdb->posts.post_status = 'publish' AND $wpdb->term_taxonomy.taxonomy = 'category' AND $category_sql AND $where GROUP BY $wpdb->ratings.rating_postid ORDER BY ratings_score DESC, ratings_average DESC LIMIT %d",
			$limit
		);

		if ( false === ( $highest_score = wp_cache_get( $cache_key = 'get_highest_score_range_category_' . md5($sql), $cache_group = 'wp-postratings' ) ) ) {
			$highest_score = $wpdb->get_results( $sql, ARRAY_A );
			wp_cache_add( $cache_key, $highest_score, $cache_group, HOUR_IN_SECONDS );
		}

		// Prime the post caches if need be.
		_prime_post_caches( wp_list_pluck( $highest_score, 'ID' ) );

		// Add the post objects
		foreach ( $highest_score as $i => $post_rating ) {
			$highest_score[ $i ] = (object) array_merge( $post_rating, (array)get_post( $post_rating['ID'] ) );
		}

		if($highest_score) {
			foreach ($highest_score as $post) {
				$output .= expand_ratings_template($temp, $post, null, $chars, false)."\n";
			}
		} else {
			$output = '<li>'.esc_html__('N/A', 'wp-postratings').'</li>'."\n";
		}
		if($display) {
			echo $output;
		} else {
			return $output;
		}
	}
}


### Function: Display Highest Rated Page/Post By Tag ID
if(!function_exists('get_highest_rated_tag')) {
	function get_highest_rated_tag($tag_id = 0, $mode = '', $min_votes = 0, $limit = 10, $chars = 0, $display = true) {
		global $wpdb;
		$ratings_max = intval(get_option('postratings_max'));
		$ratings_custom = intval(get_option('postratings_customrating'));
		$output = '';
		if(is_array($tag_id)) {
			$tag_id = array_map( 'intval', $tag_id );
			$tag_sql = "$wpdb->term_taxonomy.term_id IN (".join(',', $tag_id).')';
		} else {
			$tag_sql = $wpdb->prepare( "$wpdb->term_taxonomy.term_id = %d", $tag_id );
		}
		if(!empty($mode) && $mode != 'both') {
			$where = $wpdb->prepare( "$wpdb->posts.post_type = %s", $mode );
		} else {
			$where = '1=1';
		}
		if($ratings_custom && $ratings_max == 2) {
			$order_by = 'ratings_score';
		} else {
			$order_by = 'ratings_average';
		}
		$temp = stripslashes(get_option('postratings_template_highestrated'));
		$sql = $wpdb->prepare(
			"SELECT DISTINCT $wpdb->posts.ID, (t1.meta_value+0.00) AS ratings_average, (t2.meta_value+0.00) AS ratings_users, (t3.meta_value+0.00) AS ratings_score FROM $wpdb->posts LEFT JOIN $wpdb->postmeta AS t1 ON t1.post_id = $wpdb->posts.ID LEFT JOIN $wpdb->postmeta AS t2 ON t1.post_id = t2.post_id LEFT JOIN $wpdb->postmeta AS t3 ON t3.post_id = $wpdb->posts.ID INNER JOIN $wpdb->term_relationships ON ($wpdb->posts.ID = $wpdb->term_relationships.object_id) INNER JOIN $wpdb->term_taxonomy ON ($wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id) WHERE t1.meta_key = 'ratings_average' AND t2.meta_key = 'ratings_users' AND t3.meta_key = 'ratings_score' AND $wpdb->posts.post_password = '' AND $wpdb->posts.post_date < NOW() AND $wpdb->posts.post_status = 'publish' AND $wpdb->term_taxonomy.taxonomy = 'post_tag' AND $tag_sql AND t2.meta_value >= %d AND $where ORDER BY $order_by DESC, ratings_users DESC LIMIT %d",
			$min_votes,
			$limit
		);

		if ( false === ( $highest_rated = wp_cache_get( $cache_key = 'get_highest_rated_tag_' . md5($sql), $cache_group = 'wp-postratings' ) ) ) {
			$highest_rated = $wpdb->get_results( $sql, ARRAY_A );
			wp_cache_add( $cache_key, $highest_rated, $cache_group, HOUR_IN_SECONDS );
		}

		// Prime the post caches if need be.
		_prime_post_caches( wp_list_pluck( $highest_rated, 'ID' ) );

		// Add the post objects
		foreach ( $highest_rated as $i => $post_rating ) {
			$highest_rated[ $i ] = (object) array_merge( $post_rating, (array)get_post( $post_rating['ID'] ) );
		}

		if($highest_rated) {
			foreach($highest_rated as $post) {
				$output .= expand_ratings_template($temp, $post, null, $chars, false)."\n";
			}
		} else {
			$output = '<li>'.esc_html__('N/A', 'wp-postratings').'</li>'."\n";
		}
		if($display) {
			echo $output;
		} else {
			return $output;
		}
	}
}


### Function: Display Lowest Rated Page/Post By Tag ID
if(!function_exists('get_lowest_rated_tag')) {
	function get_lowest_rated_tag($tag_id = 0, $mode = '', $min_votes = 0, $limit = 10, $chars = 0, $display = true) {
		global $wpdb;
		$ratings_max = intval(get_option('postratings_max'));
		$ratings_custom = intval(get_option('postratings_customrating'));
		$output = '';
		if(is_array($tag_id)) {
			$tag_id = array_map( 'intval', $tag_id );
			$tag_sql = "$wpdb->term_taxonomy.term_id IN (".join(',', $tag_id).')';
		} else {
			$tag_sql = $wpdb->prepare( "$wpdb->term_taxonomy.term_id = %d", $tag_id );
		}
		if(!empty($mode) && $mode != 'both') {
			$where = $wpdb->prepare( "$wpdb->posts.post_type = %s", $mode );
		} else {
			$where = '1=1';
		}
		if($ratings_custom && $ratings_max == 2) {
			$order_by = 'ratings_score';
		} else {
			$order_by = 'ratings_average';
		}
		$temp = stripslashes(get_option('postratings_template_highestrated'));
		$sql = $wpdb->prepare(
			"SELECT DISTINCT $wpdb->posts.ID, (t1.meta_value+0.00) AS ratings_average, (t2.meta_value+0.00) AS ratings_users, (t3.meta_value+0.00) AS ratings_score FROM $wpdb->posts LEFT JOIN $wpdb->postmeta AS t1 ON t1.post_id = $wpdb->posts.ID LEFT JOIN $wpdb->postmeta AS t2 ON t1.post_id = t2.post_id LEFT JOIN $wpdb->postmeta AS t3 ON t3.post_id = $wpdb->posts.ID INNER JOIN $wpdb->term_relationships ON ($wpdb->posts.ID = $wpdb->term_relationships.object_id) INNER JOIN $wpdb->term_taxonomy ON ($wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id) WHERE t1.meta_key = 'ratings_average' AND t2.meta_key = 'ratings_users' AND t3.meta_key = 'ratings_score' AND $wpdb->posts.post_password = '' AND $wpdb->posts.post_date < NOW() AND $wpdb->posts.post_status = 'publish' AND $wpdb->term_taxonomy.taxonomy = 'post_tag' AND $tag_sql AND t2.meta_value >= %d AND $where ORDER BY $order_by ASC, ratings_users DESC LIMIT %d",
			$min_votes,
			$limit
		);

		if ( false === ( $lowest_rated = wp_cache_get( $cache_key = 'get_lowest_rated_tag_' . md5($sql), $cache_group = 'wp-postratings' ) ) ) {
			$lowest_rated = $wpdb->get_results( $sql, ARRAY_A );
			wp_cache_add( $cache_key, $lowest_rated, $cache_group, HOUR_IN_SECONDS );
		}

		// Prime the post caches if need be.
		_prime_post_caches( wp_list_pluck( $lowest_rated, 'ID' ) );

		// Add the post objects
		foreach ( $lowest_rated as $i => $post_rating ) {
			$lowest_rated[ $i ] = (object) array_merge( $post_rating, (array)get_post( $post_rating['ID'] ) );
		}

		if($lowest_rated) {
			foreach($lowest_rated as $post) {
				$output .= expand_ratings_template($temp, $post, null, $chars, false)."\n";
			}
		} else {
			$output = '<li>'.esc_html__('N/A', 'wp-postratings').'</li>'."\n";
		}
		if($display) {
			echo $output;
		} else {
			return $output;
		}
	}
}


### Function: Display Total Rating Users
if(!function_exists('get_ratings_users')) {
	function get_ratings_users($display = true) {
		global $wpdb;

		if ( false === ( $ratings_users = wp_cache_get( $cache_key = 'get_ratings_users', $cache_group = 'wp-postratings' ) ) ) {
			$ratings_users = $wpdb->get_var("SELECT SUM((meta_value+0.00)) FROM $wpdb->postmeta WHERE meta_key = 'ratings_users'");
			wp_cache_add( $cache_key, $ratings_users, $cache_group, HOUR_IN_SECONDS );
		}

		if($display) {
			echo $ratings_users;
		} else {
			return $ratings_users;
		}
	}
}
