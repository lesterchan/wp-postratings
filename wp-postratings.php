<?php
/*
Plugin Name: WP-PostRatings
Plugin URI: http://lesterchan.net/portfolio/programming/php/
Description: Adds an AJAX rating system for your WordPress blog's post/page.
Version: 1.82
Author: Lester 'GaMerZ' Chan
Author URI: http://lesterchan.net
Text Domain: wp-postratings
*/


/*
	Copyright 2015 Lester Chan  (email : lesterchan@gmail.com)

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

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

### Version
define( 'WP_POSTRATINGS_VERSION', 1.82 );

### Define Image Extension
if( ! defined( 'RATINGS_IMG_EXT' ) ) {
	define( 'RATINGS_IMG_EXT', apply_filters( 'wp_postratings_image_extension', 'gif' ) );
}

### Create Text Domain For Translations
add_action( 'plugins_loaded', 'postratings_textdomain' );
function postratings_textdomain() {
	load_plugin_textdomain( 'wp-postratings', false, dirname( plugin_basename( __FILE__ ) ) );
}


### Rating Logs Table Name
global $wpdb;
$wpdb->ratings = $wpdb->prefix.'ratings';


### Function: Ratings Administration Menu
add_action('admin_menu', 'ratings_menu');
function ratings_menu() {
	add_menu_page(__('Ratings', 'wp-postratings'), __('Ratings', 'wp-postratings'), 'manage_ratings', 'wp-postratings/postratings-manager.php', '', 'dashicons-star-filled');

	add_submenu_page('wp-postratings/postratings-manager.php', __('Manage Ratings', 'wp-postratings'), __('Manage Ratings', 'wp-postratings'), 'manage_ratings', 'wp-postratings/postratings-manager.php');
	add_submenu_page('wp-postratings/postratings-manager.php', __('Ratings Options', 'wp-postratings'), __('Ratings Options', 'wp-postratings'),  'manage_ratings', 'wp-postratings/postratings-options.php');
	add_submenu_page('wp-postratings/postratings-manager.php', __('Ratings Templates', 'wp-postratings'), __('Ratings Templates', 'wp-postratings'),  'manage_ratings', 'wp-postratings/postratings-templates.php');
}


### Function: Display The Rating For The Post
function the_ratings($start_tag = 'div', $custom_id = 0, $display = true) {
	global $id;
	// Allow Custom ID
	if(intval($custom_id) > 0) {
		$ratings_id = $custom_id;
	} else {
		// If Global $id is 0, Get The Loop Post ID
		if($id === 0) {
			$ratings_id = get_the_ID();
		} elseif (is_null($id)) {
			global $post;
			$ratings_id = $post->ID;
		} else {
			$ratings_id = $id;
		}
	}

	// Loading Style
	$postratings_ajax_style = get_option('postratings_ajax_style');
	if(intval($postratings_ajax_style['loading']) == 1) {
		$loading = '<' . $start_tag . ' id="post-ratings-' . $ratings_id . '-loading" class="post-ratings-loading">
			<img src="' . plugins_url('wp-postratings/images/loading.gif') . '" width="16" height="16" alt="' . __( 'Loading...', 'wp-postratings' ) . '" title="' . __( 'Loading...', 'wp-postratings' ) . '" class="post-ratings-image" />' . __( 'Loading...', 'wp-postratings' ) . '</' . $start_tag . '>';
	} else {
		$loading = '';
	}
	// Check To See Whether User Has Voted
	$user_voted = check_rated($ratings_id);
	// HTML Attributes
	$ratings_options = get_option('postratings_options');
	$ratings_options['richsnippet'] = isset( $ratings_options['richsnippet'] ) ? $ratings_options['richsnippet'] : 1;
	if( (is_single() || is_page() ) && $ratings_options['richsnippet'] ) {
		$itemtype = apply_filters('wp_postratings_schema_itemtype', 'itemscope itemtype="http://schema.org/Article"');
		$attributes = 'id="post-ratings-'.$ratings_id.'" class="post-ratings" '.$itemtype;
	} else {
		$attributes = 'id="post-ratings-'.$ratings_id.'" class="post-ratings"';
	}
	// If User Voted Or Is Not Allowed To Rate
	if($user_voted) {
		if(!$display) {
			return "<$start_tag $attributes>".the_ratings_results($ratings_id).'</'.$start_tag.'>'.$loading;
		} else {
			echo "<$start_tag $attributes>".the_ratings_results($ratings_id).'</'.$start_tag.'>'.$loading;
			return;
		}
	// If User Is Not Allowed To Rate
	} else if(!check_allowtorate()) {
		if(!$display) {
			return "<$start_tag $attributes>".the_ratings_results($ratings_id, 0, 0, 0, 1).'</'.$start_tag.'>'.$loading;
		} else {
			echo "<$start_tag $attributes>".the_ratings_results($ratings_id, 0, 0, 0, 1).'</'.$start_tag.'>'.$loading;
			return;
		}
	// If User Has Not Voted
	} else {
		if(!$display) {
			return "<$start_tag $attributes data-nonce=\"".wp_create_nonce('postratings_'.$ratings_id.'-nonce')."\">".the_ratings_vote($ratings_id).'</'.$start_tag.'>'.$loading;
		} else {
			echo "<$start_tag $attributes data-nonce=\"".wp_create_nonce('postratings_'.$ratings_id.'-nonce')."\">".the_ratings_vote($ratings_id).'</'.$start_tag.'>'.$loading;
			return;
		}
	}
}


### Function: Print Out jQuery Script At The Top
add_action('wp_head', 'ratings_javascripts_header');
function ratings_javascripts_header() {
	wp_print_scripts('jquery');
}


### Function: Enqueue Ratings JavaScripts/CSS
add_action('wp_enqueue_scripts', 'ratings_scripts');
function ratings_scripts() {
	if(@file_exists(TEMPLATEPATH.'/postratings-css.css')) {
		wp_enqueue_style('wp-postratings', get_stylesheet_directory_uri().'/postratings-css.css', false, WP_POSTRATINGS_VERSION, 'all');
	} else {
		wp_enqueue_style('wp-postratings', plugins_url('wp-postratings/postratings-css.css'), false, WP_POSTRATINGS_VERSION, 'all');
	}
	if(is_rtl()) {
		if(@file_exists(TEMPLATEPATH.'/postratings-css-rtl.css')) {
			wp_enqueue_style('wp-postratings-rtl', get_stylesheet_directory_uri().'/postratings-css-rtl.css', false, WP_POSTRATINGS_VERSION, 'all');
		} else {
			wp_enqueue_style('wp-postratings-rtl', plugins_url('wp-postratings/postratings-css-rtl.css'), false, WP_POSTRATINGS_VERSION, 'all');
		}
	}
	$postratings_max = intval(get_option('postratings_max'));
	$postratings_custom = intval(get_option('postratings_customrating'));
	$postratings_ajax_style = get_option('postratings_ajax_style');
	$postratings_javascript = '';
	if($postratings_custom) {
		for($i = 1; $i <= $postratings_max; $i++) {
			$postratings_javascript .= 'var ratings_'.$i.'_mouseover_image=new Image();ratings_'.$i.'_mouseover_image.src=ratingsL10n.plugin_url+"/images/"+ratingsL10n.image+"/rating_'.$i.'_over."+ratingsL10n.image_ext;';
		}
	} else {
		$postratings_javascript = 'var ratings_mouseover_image=new Image();ratings_mouseover_image.src=ratingsL10n.plugin_url+"/images/"+ratingsL10n.image+"/rating_over."+ratingsL10n.image_ext;';
	}
	wp_enqueue_script('wp-postratings', plugins_url('wp-postratings/postratings-js.js'), array('jquery'), WP_POSTRATINGS_VERSION, true);
	wp_localize_script('wp-postratings', 'ratingsL10n', array(
		'plugin_url' => plugins_url('wp-postratings'),
		'ajax_url' => admin_url('admin-ajax.php'),
		'text_wait' => __('Please rate only 1 post at a time.', 'wp-postratings'),
		'image' => get_option('postratings_image'),
		'image_ext' => RATINGS_IMG_EXT,
		'max' => $postratings_max,
		'show_loading' => intval($postratings_ajax_style['loading']),
		'show_fading' => intval($postratings_ajax_style['fading']),
		'custom' => $postratings_custom,
		'l10n_print_after' => $postratings_javascript
	));
}


### Function: Enqueue Ratings Stylesheets/JavaScripts In WP-Admin
add_action('admin_enqueue_scripts', 'ratings_scripts_admin');
function ratings_scripts_admin($hook_suffix) {
	$postratings_admin_pages = array('wp-postratings/postratings-manager.php', 'wp-postratings/postratings-options.php', 'wp-postratings/postratings-templates.php', 'wp-postratings/postratings-uninstall.php');
	if(in_array($hook_suffix, $postratings_admin_pages)) {
		wp_enqueue_style('wp-postratings-admin', plugins_url('wp-postratings/postratings-admin-css.css'), false, WP_POSTRATINGS_VERSION, 'all');
		wp_enqueue_script('wp-postratings-admin', plugins_url('wp-postratings/postratings-admin-js.js'), array('jquery'), WP_POSTRATINGS_VERSION, true);
		wp_localize_script('wp-postratings-admin', 'ratingsAdminL10n', array(
			'admin_ajax_url' => admin_url('admin-ajax.php')
		));
	}
}


### Function: Display Ratings Results
function the_ratings_results($post_id, $new_user = 0, $new_score = 0, $new_average = 0, $type = 0) {
	if($new_user == 0 && $new_score == 0 && $new_average == 0) {
		$post_ratings_data = null;
	} else {
		$post_ratings_data = new stdClass();
		$post_ratings_data->ratings_users = $new_user;
		$post_ratings_data->ratings_score = $new_score;
		$post_ratings_data->ratings_average = $new_average;
	}
	// Display The Contents
	if($type == 1) {
		$template_postratings_text = stripslashes(get_option('postratings_template_permission'));
	} else {
		$template_postratings_text = stripslashes(get_option('postratings_template_text'));
	}
	// Return Post Ratings Template
	return expand_ratings_template($template_postratings_text, $post_id, $post_ratings_data);
}


### Function: Display Ratings Vote
function the_ratings_vote($post_id, $new_user = 0, $new_score = 0, $new_average = 0) {
  if($new_user == 0 && $new_score == 0 && $new_average == 0) {
    $post_ratings_data = null;
  } else {
	$post_ratings_data = new stdClass();
    $post_ratings_data->ratings_users = $new_user;
    $post_ratings_data->ratings_score = $new_score;
    $post_ratings_data->ratings_average = $new_average;
  }
	// If No Ratings, Return No Ratings templae
	if(get_post_meta($post_id, 'ratings_users', true) == 0) {
		$template_postratings_none = stripslashes(get_option('postratings_template_none'));
		// Return Post Ratings Template
		return expand_ratings_template($template_postratings_none, $post_id, $post_ratings_data);
	} else {
		// Display The Contents
		$template_postratings_vote = stripslashes(get_option('postratings_template_vote'));
		// Return Post Ratings Voting Template
		return expand_ratings_template($template_postratings_vote, $post_id, $post_ratings_data);
	}
}


### Function: Check Who Is Allow To Rate
function check_allowtorate() {
	global $user_ID;
	$user_ID = intval($user_ID);
	$allow_to_vote = intval(get_option('postratings_allowtorate'));
	switch($allow_to_vote) {
		// Guests Only
		case 0:
			if($user_ID > 0) {
				return false;
			}
			return true;
			break;
		// Registered Users Only
		case 1:
			if($user_ID == 0) {
				return false;
			}
			return true;
			break;
		// Registered Users And Guests
		case 2:
		default:
			return true;
	}
}


### Function: Check Whether User Have Rated For The Post
function check_rated( $post_id ) {
	$postratings_logging_method = intval( get_option( 'postratings_logging_method' ) );
	$rated = false;
	switch( $postratings_logging_method ) {
		// Do Not Log
		case 0:
			$rated = false;
			break;
		// Logged By Cookie
		case 1:
			$rated = check_rated_cookie( $post_id );
			break;
		// Logged By IP
		case 2:
			$rated = check_rated_ip( $post_id );
			break;
		// Logged By Cookie And IP
		case 3:
			$rated_cookie = check_rated_cookie( $post_id );
			if( $rated_cookie > 0 ) {
				$rated = true;
			} else {
				$rated = check_rated_ip( $post_id );
			}
			break;
		// Logged By Username
		case 4:
			$rated = check_rated_username( $post_id );
			break;
	}

	$rated = apply_filters( 'wp_postratings_check_rated', $rated );

	return $rated;
}


### Function: Check Rated By Cookie
function check_rated_cookie($post_id) {
	if(isset($_COOKIE["rated_$post_id"])) {
		return true;
	} else {
		return false;
	}
}


### Function: Check Rated By IP
function check_rated_ip($post_id) {
	global $wpdb;
	// Check IP From IP Logging Database
	$get_rated = $wpdb->get_var( $wpdb->prepare( "SELECT rating_ip FROM {$wpdb->ratings} WHERE rating_postid = %d AND rating_ip = %s", $post_id, get_ipaddress() ) );
	// 0: False | > 0: True
	return intval($get_rated);
}


### Function: Check Rated By Username
function check_rated_username($post_id) {
	global $wpdb, $user_ID;
	if(!is_user_logged_in()) {
		return 0;
	}
	// Check User ID From IP Logging Database
	$get_rated = $wpdb->get_var( $wpdb->prepare( "SELECT rating_userid FROM {$wpdb->ratings} WHERE rating_postid = %d AND rating_userid = %d", $post_id, $user_ID ) );
	// 0: False | > 0: True
	return intval( $get_rated);
}


### Function: Get Comment Authors Ratings
add_action('loop_start', 'get_comment_authors_ratings');
function get_comment_authors_ratings() {
	global $wpdb, $post, $comment_authors_ratings;
	$comment_authors_ratings_results = null;
	if(!is_feed() && !is_admin()) {
		$comment_authors_ratings = array();
		if($post && $post->ID) {
			$comment_authors_ratings_results = $wpdb->get_results( $wpdb->prepare( "SELECT rating_username, rating_rating, rating_ip FROM {$wpdb->ratings} WHERE rating_postid = %d", $post->ID ) );
		}
		if($comment_authors_ratings_results) {
			foreach($comment_authors_ratings_results as $comment_authors_ratings_result) {
				$comment_author = stripslashes($comment_authors_ratings_result->rating_username);
				$comment_authors_ratings[$comment_author] = $comment_authors_ratings_result->rating_rating;
				$comment_authors_ratings[$comment_authors_ratings_result->rating_ip] = $comment_authors_ratings_result->rating_rating;
			}
		}
	}
}


### Function: Comment Author Ratings
function comment_author_ratings($comment_author_specific = '', $display = true) {
	global $comment_authors_ratings;
	if(get_comment_type() == 'comment') {
		$post_ratings_images = '';
		$ratings_image = get_option('postratings_image');
		$ratings_max = intval(get_option('postratings_max'));
		$ratings_custom = intval(get_option('postratings_customrating'));
		if(empty($comment_author_specific)) {
			$comment_author = get_comment_author();
		} else {
			$comment_author = $comment_author_specific;
		}
		$comment_author_rating = intval($comment_authors_ratings[$comment_author]);
		if($comment_author_rating == 0) {
			$comment_author_rating = intval($comment_authors_ratings[get_comment_author_IP()]);
		}
		if($comment_author_rating != 0) {
			// Display Rated Images
			if($ratings_custom && $ratings_max == 2) {
				if($comment_author_rating > 0) {
					$comment_author_rating = '+'.$comment_author_rating;
				}
			}
			$image_alt = sprintf(__('%s gives a rating of %s', 'wp-postratings'), $comment_author, $comment_author_rating);
			$post_ratings_images = get_ratings_images_comment_author($ratings_custom, $ratings_max, $comment_author_rating, $ratings_image, $image_alt);
		}
		if($display) {
			return $post_ratings_images;
		} else {
			return $post_ratings_images;
		}
	}
}


### Function:  Display Comment Author Ratings
//add_filter('comment_text', 'comment_author_ratings_filter');
function comment_author_ratings_filter($comment_text) {
	global $comment, $comment_authors_ratings;
	$output = '';
	if(!is_feed() && !is_admin()) {
		if(get_comment_type() == 'comment') {
			$post_ratings_images = '';
			$ratings_image = get_option('postratings_image');
			$ratings_max = intval(get_option('postratings_max'));
			$ratings_custom = intval(get_option('postratings_customrating'));
			$comment_author = get_comment_author();
			$comment_author_rating = intval($comment_authors_ratings[$comment_author]);
			if($comment_author_rating == 0) {
				$comment_author_rating = intval($comment_authors_ratings[get_comment_author_IP()]);
			}
			if($comment_author_rating != 0) {
				// Display Rated Images
				if($ratings_custom && $ratings_max == 2) {
					if($comment_author_rating > 0) {
						$comment_author_rating = '+'.$comment_author_rating;
					}
				}
				$image_alt = sprintf(__('%s gives a rating of %s', 'wp-postratings'), $comment_author, $comment_author_rating);
				$post_ratings_images = get_ratings_images_comment_author($ratings_custom, $ratings_max, $comment_author_rating, $ratings_image, $image_alt);
			}
			$output .= '<div class="post-ratings-comment-author">';
			if($post_ratings_images != '') {
				$output .= get_comment_author().' ratings for this post: '.$post_ratings_images;
			} else {
				$output .= get_comment_author().' did not rate this post.';
			}
			$output .= '</div>';
		}
	}
	return $comment_text.$output;
}


### Function: Get IP Address
if(!function_exists('get_ipaddress')) {
	function get_ipaddress() {
		if (empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
			$ip_address = $_SERVER["REMOTE_ADDR"];
		} else {
			$ip_address = $_SERVER["HTTP_X_FORWARDED_FOR"];
		}
		if(strpos($ip_address, ',') !== false) {
			$ip_address = explode(',', $ip_address);
			$ip_address = $ip_address[0];
		}
		return esc_attr($ip_address);
	}
}


### Function: Return All Images From A Rating Image Folder
function ratings_images_folder($folder_name) {
	$normal_images = array('rating_over.'.RATINGS_IMG_EXT, 'rating_on.'.RATINGS_IMG_EXT, 'rating_half.'.RATINGS_IMG_EXT, 'rating_off.'.RATINGS_IMG_EXT);
	$postratings_path = WP_PLUGIN_DIR.'/wp-postratings/images/'.$folder_name;
	$images_count_temp = 1;
	$images_count = 1;
	$count = 0;
	$rating['max'] = 0;
	$rating['custom'] = 0;
	$rating['images'] = array();
	if(is_dir($postratings_path)) {
		if($handle = @opendir($postratings_path)) {
			while (false !== ($filename = readdir($handle))) {
				if ($filename != '.' && $filename != '..' && substr($filename, -8) != '-rtl.'.RATINGS_IMG_EXT && strpos($filename, '.') !== 0) {
					if(in_array($filename, $normal_images)) {
						$count++;
					} elseif(intval(substr($filename,7, -7)) > $rating['max']) {
						$rating['max'] = intval(substr($filename,7, -7));
					}
					$rating['images'][] = $filename;
					$images_count++;
				}
			}
			closedir($handle);
		}
	}
	if($count != sizeof($normal_images)) {
		$rating['custom'] = 1;
	}
	if($rating['max'] == 0) {
		$rating['max'] = intval(get_option('postratings_max'));
	}
	return $rating;
}


### Function: Add PostRatings To Post/Page Automatically
//add_action('the_content', 'add_ratings_to_content');
function add_ratings_to_content($content) {
	if (!is_feed()) {
		$content .= the_ratings('div', 0, false);
	}
	return $content;
}


### Function: Short Code For Inserting Ratings Into Posts
add_shortcode( 'ratings', 'ratings_shortcode' );
function ratings_shortcode( $atts ) {
	$attributes = shortcode_atts( array( 'id' => 0, 'results' => false ), $atts );
	if( ! is_feed() ) {
		$id = intval( $attributes['id'] );
		if( $attributes['results'] ) {
			return the_ratings_results( $id );
		} else {
			return the_ratings( 'span', $id, false );
		}
	} else {
		return __( 'Note: There is a rating embedded within this post, please visit this post to rate it.', 'wp-postratings' );
	}
}


### Function: Snippet Text
if(!function_exists('snippet_text')) {
	function snippet_text($text, $length = 0) {
		if (defined('MB_OVERLOAD_STRING')) {
		  $text = @html_entity_decode($text, ENT_QUOTES, get_option('blog_charset'));
		 	if (mb_strlen($text) > $length) {
				return htmlentities(mb_substr($text,0,$length), ENT_COMPAT, get_option('blog_charset')).'...';
		 	} else {
				return htmlentities($text, ENT_COMPAT, get_option('blog_charset'));
		 	}
		} else {
			$text = @html_entity_decode($text, ENT_QUOTES, get_option('blog_charset'));
		 	if (strlen($text) > $length) {
				return htmlentities(substr($text,0,$length), ENT_COMPAT, get_option('blog_charset')).'...';
		 	} else {
				return htmlentities($text, ENT_COMPAT, get_option('blog_charset'));
		 	}
		}
	}
}


### Function: Process Post Excerpt, For Some Reasons, The Default get_post_excerpt() Does Not Work As Expected
function ratings_post_excerpt($post_id, $post_excerpt, $post_content) {
	if(post_password_required($post_id)) {
		return __('There is no excerpt because this is a protected post.', 'wp-postratings');
	}
	if(empty($post_excerpt)) {
		return snippet_text(strip_tags($post_content), 200);
	} else {
		return $post_excerpt;
	}
}


### Function: Add Rating Custom Fields
add_action('publish_post', 'add_ratings_fields');
add_action('publish_page', 'add_ratings_fields');
function add_ratings_fields($post_ID) {
	global $wpdb;
	if(!wp_is_post_revision($post_ID)) {
		add_post_meta($post_ID, 'ratings_users', 0, true);
		add_post_meta($post_ID, 'ratings_score', 0, true);
		add_post_meta($post_ID, 'ratings_average', 0, true);
	}
}


### Function:Delete Rating Custom Fields
add_action('delete_post', 'delete_ratings_fields');
function delete_ratings_fields($post_ID) {
	global $wpdb;
	if(!wp_is_post_revision($post_ID)) {
		delete_post_meta($post_ID, 'ratings_users');
		delete_post_meta($post_ID, 'ratings_score');
		delete_post_meta($post_ID, 'ratings_average');
	}
}


### Function: Process Ratings
add_action('wp_ajax_postratings', 'process_ratings');
add_action('wp_ajax_nopriv_postratings', 'process_ratings');
function process_ratings() {
	global $wpdb, $user_identity, $user_ID;

	if(isset($_REQUEST['action']) && $_REQUEST['action'] == 'postratings')
	{
		$rate = intval($_REQUEST['rate']);
		$post_id = intval($_REQUEST['pid']);

		// Verify Referer
		if(!check_ajax_referer('postratings_'.$post_id.'-nonce', 'postratings_'.$post_id.'_nonce', false))
		{
			_e('Failed To Verify Referrer', 'wp-postratings');
			exit();
		}

		if($rate > 0 && $post_id > 0 && check_allowtorate()) {
			// Check For Bot
			$bots_useragent = array('googlebot', 'google', 'msnbot', 'ia_archiver', 'lycos', 'jeeves', 'scooter', 'fast-webcrawler', 'slurp@inktomi', 'turnitinbot', 'technorati', 'yahoo', 'findexa', 'findlinks', 'gaisbo', 'zyborg', 'surveybot', 'bloglines', 'blogsearch', 'ubsub', 'syndic8', 'userland', 'gigabot', 'become.com');
			$useragent = $_SERVER['HTTP_USER_AGENT'];
			foreach ($bots_useragent as $bot) {
				if (stristr($useragent, $bot) !== false) {
					return;
				}
			}
			header('Content-Type: text/html; charset='.get_option('blog_charset').'');
			postratings_textdomain();
			$rated = check_rated($post_id);
			// Check Whether Post Has Been Rated By User
			if(!$rated) {
				// Check Whether Is There A Valid Post
				$post = get_post($post_id);
				// If Valid Post Then We Rate It
				if($post && !wp_is_post_revision($post)) {
					$ratings_max = intval(get_option('postratings_max'));
					$ratings_custom = intval(get_option('postratings_customrating'));
					$ratings_value = get_option('postratings_ratingsvalue');
					$post_title = addslashes($post->post_title);
					$post_ratings = get_post_custom($post_id);
					$post_ratings_users = ! empty( $post_ratings['ratings_users'] ) ? intval($post_ratings['ratings_users'][0]) : 0;
					$post_ratings_score = ! empty( $post_ratings['ratings_score'] ) ? intval($post_ratings['ratings_score'][0]) : 0;
					// Check For Ratings Lesser Than 1 And Greater Than $ratings_max
					if($rate < 1 || $rate > $ratings_max) {
						$rate = 0;
					}
					$post_ratings_users = ($post_ratings_users+1);
					$post_ratings_score = ($post_ratings_score+intval($ratings_value[$rate-1]));
					$post_ratings_average = round($post_ratings_score/$post_ratings_users, 2);
					update_post_meta($post_id, 'ratings_users', $post_ratings_users);
					update_post_meta($post_id, 'ratings_score', $post_ratings_score);
					update_post_meta($post_id, 'ratings_average', $post_ratings_average);

					// Add Log
					if(!empty($user_identity)) {
						$rate_user = addslashes($user_identity);
					} elseif(!empty($_COOKIE['comment_author_'.COOKIEHASH])) {
						$rate_user = addslashes($_COOKIE['comment_author_'.COOKIEHASH]);
					} else {
						$rate_user = __('Guest', 'wp-postratings');
					}
					$rate_user = apply_filters( 'wp_postratings_process_ratings_user', $rate_user );
					$rate_userid = apply_filters( 'wp_postratings_process_ratings_userid', intval( $user_ID ) );

					// Only Create Cookie If User Choose Logging Method 1 Or 3
					$postratings_logging_method = intval(get_option('postratings_logging_method'));
					if($postratings_logging_method == 1 || $postratings_logging_method == 3) {
						$rate_cookie = setcookie("rated_".$post_id, $ratings_value[$rate-1], time() + 30000000, apply_filters('wp_postratings_cookiepath', SITECOOKIEPATH));
					}
					// Log Ratings No Matter What
					$rate_log = $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->ratings} VALUES (%d, %d, %s, %d, %d, %s, %s, %s, %d )", 0, $post_id, $post_title, $ratings_value[$rate-1], current_time('timestamp'), get_ipaddress(), @gethostbyaddr( get_ipaddress() ), $rate_user, $rate_userid ) );
					// Allow Other Plugins To Hook When A Post Is Rated
					do_action('rate_post', $rate_userid, $post_id, $ratings_value[$rate-1]);
					// Output AJAX Result
					echo the_ratings_results($post_id, $post_ratings_users, $post_ratings_score, $post_ratings_average);
					exit();
				} else {
					printf(__('Invalid Post ID. Post ID #%s.', 'wp-postratings'), $post_id);
					exit();
				} // End if($post)
			} else {
				printf(__('You Had Already Rated This Post. Post ID #%s.', 'wp-postratings'), $post_id);
				exit();
			}// End if(!$rated)
		} // End if($rate && $post_id && check_allowtorate())
	} // End if(isset($_REQUEST['action']) && $_REQUEST['action'] == 'postratings')
}


### Function: Process Ratings
add_action('wp_ajax_postratings-admin', 'manage_ratings');
function manage_ratings()
{
	### Form Processing
	if(isset($_GET['action']) && $_GET['action'] == 'postratings-admin')
	{
		check_ajax_referer('wp-postratings_option_update_individual_rating');

		//Variables
		$postratings_url = plugins_url('wp-postratings/images');
		$postratings_path = WP_PLUGIN_DIR.'/wp-postratings/images';
		$postratings_ratingstext = get_option('postratings_ratingstext');
		$postratings_ratingsvalue = get_option('postratings_ratingsvalue');

		// Form Processing
		$postratings_customrating = intval($_GET['custom']);
		$postratings_image = trim($_GET['image']);
		$postratings_max = intval($_GET['max']);

		// If It Is A Up/Down Rating
		if($postratings_customrating && $postratings_max == 2) {
			$postratings_ratingsvalue[0] = -1;
			$postratings_ratingsvalue[1] = 1;
			$postratings_ratingstext[0] = __('Vote This Post Down', 'wp-postratings');
			$postratings_ratingstext[1] = __('Vote This Post Up', 'wp-postratings');
		} else {
			for($i = 0; $i < $postratings_max; $i++) {
				if($i > 0) {
					$postratings_ratingstext[$i] = sprintf(__('%s Stars', 'wp-postratings'), number_format_i18n($i+1));
				} else {
					$postratings_ratingstext[$i] = sprintf(__('%s Star', 'wp-postratings'), number_format_i18n($i+1));
				}
				$postratings_ratingsvalue[$i] = $i+1;
			}
		}
?>
		<table class="form-table">
			<thead>
				<tr>
					<th><?php _e('Rating Image', 'wp-postratings'); ?></th>
					<th><?php _e('Rating Text', 'wp-postratings'); ?></th>
					<th><?php _e('Rating Value', 'wp-postratings'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
					for($i = 1; $i <= $postratings_max; $i++) {
						$postratings_text = stripslashes($postratings_ratingstext[$i-1]);
						$postratings_value = $postratings_ratingsvalue[$i-1];
						if($postratings_value > 0) {
							$postratings_value = '+'.$postratings_value;
						}
						echo '<tr>'."\n";
						echo '<td>'."\n";
						if(is_rtl() && file_exists($postratings_path.'/'.$postratings_image.'/rating_start-rtl.'.RATINGS_IMG_EXT)) {
							echo '<img src="'.$postratings_url.'/'.$postratings_image.'/rating_start-rtl.'.RATINGS_IMG_EXT.'" alt="rating_start-rtl.'.RATINGS_IMG_EXT.'" class="post-ratings-image" />';
						} elseif(file_exists($postratings_path.'/'.$postratings_image.'/rating_start.'.RATINGS_IMG_EXT)) {
							echo '<img src="'.$postratings_url.'/'.$postratings_image.'/rating_start.'.RATINGS_IMG_EXT.'" alt="rating_start.'.RATINGS_IMG_EXT.'" class="post-ratings-image" />';
						}
						if($postratings_customrating) {
							if($postratings_max == 2) {
								echo '<img src="'.$postratings_url.'/'.$postratings_image.'/rating_'.$i.'_on.'.RATINGS_IMG_EXT.'" alt="rating_'.$i.'_on.'.RATINGS_IMG_EXT.'" class="post-ratings-image" />';
							} else {
								for($j = 1; $j < ($i+1); $j++) {
									echo '<img src="'.$postratings_url.'/'.$postratings_image.'/rating_'.$j.'_on.'.RATINGS_IMG_EXT.'" alt="rating_on.'.RATINGS_IMG_EXT.'" class="post-ratings-image" />';
								}
							}
						} else {
							for($j = 1; $j < ($i+1); $j++) {
								echo '<img src="'.$postratings_url.'/'.$postratings_image.'/rating_on.'.RATINGS_IMG_EXT.'" alt="rating_on.'.RATINGS_IMG_EXT.'" class="post-ratings-image" />';
							}
						}
		        		if(is_rtl() && file_exists($postratings_path.'/'.$postratings_image.'/rating_end-rtl.'.RATINGS_IMG_EXT)) {
							echo '<img src="'.$postratings_url.'/'.$postratings_image.'/rating_end-rtl.'.RATINGS_IMG_EXT.'" alt="rating_end-rtl.'.RATINGS_IMG_EXT.'" class="post-ratings-image" />';
						} elseif(file_exists($postratings_path.'/'.$postratings_image.'/rating_end.'.RATINGS_IMG_EXT)) {
							echo '<img src="'.$postratings_url.'/'.$postratings_image.'/rating_end.'.RATINGS_IMG_EXT.'" alt="rating_end.'.RATINGS_IMG_EXT.'" class="post-ratings-image" />';
						}
						echo '</td>'."\n";
						echo '<td>'."\n";
						echo '<input type="text" id="postratings_ratingstext_'.$i.'" name="postratings_ratingstext[]" value="'.$postratings_text.'" size="20" maxlength="50" />'."\n";
						echo '</td>'."\n";
						echo '<td>'."\n";
						echo '<input type="text" id="postratings_ratingsvalue_'.$i.'" name="postratings_ratingsvalue[]" value="'.$postratings_value.'" size="2" maxlength="2" />'."\n";
						echo '</td>'."\n";
						echo '</tr>'."\n";
					}
				?>
			</tbody>
		</table>
<?php
	}
	exit();
}


### Function: Modify Default WordPress Listing To Make It Sorted By Most Rated
function ratings_most_fields($content) {
	global $wpdb;
	$content .= ", ($wpdb->postmeta.meta_value+0) AS ratings_votes";
	return $content;
}
function ratings_most_join($content) {
	global $wpdb;
	$content .= " LEFT JOIN $wpdb->postmeta ON $wpdb->postmeta.post_id = $wpdb->posts.ID";
	return $content;
}
function ratings_most_where($content) {
	global $wpdb;
	$content .= " AND $wpdb->postmeta.meta_key = 'ratings_users'";
	return $content;
}
function ratings_most_orderby($content) {
	$orderby = trim(addslashes(get_query_var('r_orderby')));
	if(empty($orderby) && ($orderby != 'asc' || $orderby != 'desc')) {
		$orderby = 'desc';
	}
	$content = " ratings_votes $orderby";
	return $content;
}


### Function: Modify Default WordPress Listing To Make It Sorted By Highest Rated
function ratings_highest_fields($content) {
	$content .= ", (t1.meta_value+0.00) AS ratings_average, (t2.meta_value+0.00) AS ratings_users";
	return $content;
}
function ratings_highest_join($content) {
	global $wpdb;
	$content .= " LEFT JOIN $wpdb->postmeta AS t1 ON t1.post_id = $wpdb->posts.ID LEFT JOIN $wpdb->postmeta As t2 ON t1.post_id = t2.post_id";
	return $content;
}
function ratings_highest_where($content) {
	$ratings_max = intval(get_option('postratings_max'));
	$ratings_custom = intval(get_option('postratings_customrating'));
	if($ratings_custom && $ratings_max == 2) {
		$content .= " AND t1.meta_key = 'ratings_score' AND t2.meta_key = 'ratings_users'";
	} else {
		$content .= " AND t1.meta_key = 'ratings_average' AND t2.meta_key = 'ratings_users'";
	}
	return $content;
}
function ratings_highest_orderby($content) {
	$orderby = trim(addslashes(get_query_var('r_orderby')));
	if(empty($orderby) || ($orderby != 'asc' && $orderby != 'desc')) {
		$orderby = 'desc';
	}
	$content = " ratings_average $orderby, ratings_users $orderby";
	return $content;
}


### Function: Ratings Public Variables
add_filter('query_vars', 'ratings_variables');
function ratings_variables($public_query_vars) {
	$public_query_vars[] = 'r_sortby';
	$public_query_vars[] = 'r_orderby';
	return $public_query_vars;
}


### Function: Sort Ratings Posts
add_action('pre_get_posts', 'ratings_sorting');
function ratings_sorting($local_wp_query) {
	if($local_wp_query->get('r_sortby') == 'most_rated') {
		add_filter('posts_fields', 'ratings_most_fields');
		add_filter('posts_join', 'ratings_most_join');
		add_filter('posts_where', 'ratings_most_where');
		add_filter('posts_orderby', 'ratings_most_orderby');
		remove_filter('posts_fields', 'ratings_highest_fields');
		remove_filter('posts_join', 'ratings_highest_join');
		remove_filter('posts_where', 'ratings_highest_where');
		remove_filter('posts_orderby', 'ratings_highest_orderby');
	} elseif($local_wp_query->get('r_sortby') == 'highest_rated') {
		add_filter('posts_fields', 'ratings_highest_fields');
		add_filter('posts_join', 'ratings_highest_join');
		add_filter('posts_where', 'ratings_highest_where');
		add_filter('posts_orderby', 'ratings_highest_orderby');
		remove_filter('posts_fields', 'ratings_most_fields');
		remove_filter('posts_join', 'ratings_most_join');
		remove_filter('posts_where', 'ratings_most_where');
		remove_filter('posts_orderby', 'ratings_most_orderby');
	} else {
		remove_filter('posts_fields', 'ratings_highest_fields');
		remove_filter('posts_join', 'ratings_highest_join');
		remove_filter('posts_where', 'ratings_highest_where');
		remove_filter('posts_orderby', 'ratings_highest_orderby');
		remove_filter('posts_fields', 'ratings_most_fields');
		remove_filter('posts_join', 'ratings_most_join');
		remove_filter('posts_where', 'ratings_most_where');
		remove_filter('posts_orderby', 'ratings_most_orderby');
	}
}


### Function Show Ratings Column in WP-Admin
add_action('manage_posts_custom_column', 'add_postratings_column_content');
add_filter('manage_posts_columns', 'add_postratings_column');
add_action('manage_pages_custom_column', 'add_postratings_column_content');
add_filter('manage_pages_columns', 'add_postratings_column');
function add_postratings_column($defaults) {
    $defaults['ratings'] = 'Ratings';
    return $defaults;
}


### Functions Fill In The Ratings
function add_postratings_column_content($column_name) {
	global $post;
    if($column_name == 'ratings') {
        if(function_exists('the_ratings')) {
        	$template = str_replace('%RATINGS_IMAGES_VOTE%', '%RATINGS_IMAGES%<br />', stripslashes(get_option('postratings_template_vote')));
			echo expand_ratings_template($template, $post, null, 0, false);
        }
    }
}


### Function Sort Columns
add_filter('manage_edit-post_sortable_columns', 'sort_postratings_column');
add_filter('manage_edit-page_sortable_columns', 'sort_postratings_column');
function sort_postratings_column($defaults)
{
    $defaults['ratings'] = 'ratings';
    return $defaults;
}
add_action('pre_get_posts', 'sort_postratings');
function sort_postratings($query) {
	if(!is_admin())
		return;
	$orderby = $query->get('orderby');
	if('ratings' == $orderby) {
		$query->set('meta_key', 'ratings_average');
		$query->set('orderby', 'meta_value_num');
	}
}


### Function: Plug Into WP-Stats
add_action( 'plugins_loaded','postratings_wp_stats' );
function postratings_wp_stats() {
	add_filter( 'wp_stats_page_admin_plugins', 'postratings_page_admin_general_stats' );
	add_filter( 'wp_stats_page_admin_most', 'postratings_page_admin_most_stats' );
	add_filter( 'wp_stats_page_plugins', 'postratings_page_general_stats' );
	add_filter( 'wp_stats_page_most', 'postratings_page_most_stats' );
}


### Function: Add WP-PostRatings General Stats To WP-Stats Page Options
function postratings_page_admin_general_stats($content) {
	$stats_display = get_option('stats_display');
	if($stats_display['ratings'] == 1) {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_ratings" value="ratings" checked="checked" />&nbsp;&nbsp;<label for="wpstats_ratings">'.__('WP-PostRatings', 'wp-postratings').'</label><br />'."\n";
	} else {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_ratings" value="ratings" />&nbsp;&nbsp;<label for="wpstats_ratings">'.__('WP-PostRatings', 'wp-postratings').'</label><br />'."\n";
	}
	return $content;
}


### Function: Add WP-PostRatings Top Most/Highest Stats To WP-Stats Page Options
function postratings_page_admin_most_stats($content) {
	$stats_display = get_option('stats_display');
	$stats_mostlimit = intval(get_option('stats_mostlimit'));
	if($stats_display['rated_highest_post'] == 1) {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_rated_highest_post" value="rated_highest_post" checked="checked" />&nbsp;&nbsp;<label for="wpstats_rated_highest_post">'.sprintf(_n('%s Highest Rated Post', '%s Highest Rated Posts', $stats_mostlimit, 'wp-postratings'), number_format_i18n($stats_mostlimit)).'</label><br />'."\n";
	} else {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_rated_highest_post" value="rated_highest_post" />&nbsp;&nbsp;<label for="wpstats_rated_highest_post">'.sprintf(_n('%s Highest Rated Post', '%s Highest Rated Posts', $stats_mostlimit, 'wp-postratings'), number_format_i18n($stats_mostlimit)).'</label><br />'."\n";
	}
	if($stats_display['rated_highest_page'] == 1) {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_rated_highest_page" value="rated_highest_page" checked="checked" />&nbsp;&nbsp;<label for="wpstats_rated_highest_page">'.sprintf(_n('%s Highest Rated Page', '%s Highest Rated Pages', $stats_mostlimit, 'wp-postratings'), number_format_i18n($stats_mostlimit)).'</label><br />'."\n";
	} else {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_rated_highest_page" value="rated_highest_page" />&nbsp;&nbsp;<label for="wpstats_rated_highest_page">'.sprintf(_n('%s Highest Rated Page', '%s Highest Rated Pages', $stats_mostlimit, 'wp-postratings'), number_format_i18n($stats_mostlimit)).'</label><br />'."\n";
	}
	if($stats_display['rated_most_post'] == 1) {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_rated_most_post" value="rated_most_post" checked="checked" />&nbsp;&nbsp;<label for="wpstats_rated_most_post">'.sprintf(_n('%s Most Rated Post', '%s Most Rated Posts', $stats_mostlimit, 'wp-postratings'), number_format_i18n($stats_mostlimit)).'</label><br />'."\n";
	} else {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_rated_most_post" value="rated_most_post" />&nbsp;&nbsp;<label for="wpstats_rated_most_post">'.sprintf(_n('%s Most Rated Post', '%s Most Rated Posts', $stats_mostlimit, 'wp-postratings'), number_format_i18n($stats_mostlimit)).'</label><br />'."\n";
	}
	if($stats_display['rated_most_page'] == 1) {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_rated_most_page" value="rated_most_page" checked="checked" />&nbsp;&nbsp;<label for="wpstats_rated_most_page">'.sprintf(_n('%s Most Rated Page', '%s Most Rated Pages', $stats_mostlimit, 'wp-postratings'), number_format_i18n($stats_mostlimit)).'</label><br />'."\n";
	} else {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_rated_most_page" value="rated_most_page" />&nbsp;&nbsp;<label for="wpstats_rated_most_page">'.sprintf(_n('%s Most Rated Page', '%s Most Rated Pages', $stats_mostlimit, 'wp-postratings'), number_format_i18n($stats_mostlimit)).'</label><br />'."\n";
	}
	return $content;
}


### Function: Add WP-PostRatings General Stats To WP-Stats Page
function postratings_page_general_stats($content) {
	$stats_display = get_option('stats_display');
	if($stats_display['ratings'] == 1) {
		$content .= '<p><strong>'.__('WP-PostRatings', 'wp-postratings').'</strong></p>'."\n";
		$content .= '<ul>'."\n";
		$content .= '<li>'.sprintf(_n('<strong>%s</strong> user casted his vote.', '<strong>%s</strong> users casted their vote.', get_ratings_users(false), 'wp-postratings'), number_format_i18n(get_ratings_users(false))).'</li>'."\n";
		$content .= '</ul>'."\n";
	}
	return $content;
}


### Function: Add WP-PostRatings Top Most/Highest Stats To WP-Stats Page
function postratings_page_most_stats($content) {
	$stats_display = get_option('stats_display');
	$stats_mostlimit = intval(get_option('stats_mostlimit'));
	if($stats_display['rated_highest_post'] == 1) {
		$content .= '<p><strong>'.sprintf(_n('%s Highest Rated Post', '%s Highest Rated Posts', $stats_mostlimit, 'wp-postratings'), number_format_i18n($stats_mostlimit)).'</strong></p>'."\n";
		$content .= '<ul>'."\n";
		$content .= get_highest_rated('post', 0, $stats_mostlimit, 0, false);
		$content .= '</ul>'."\n";
	}
	if($stats_display['rated_highest_page'] == 1) {
		$content .= '<p><strong>'.sprintf(_n('%s Highest Rated Page', '%s Highest Rated Pages', $stats_mostlimit, 'wp-postratings'), number_format_i18n($stats_mostlimit)).'</strong></p>'."\n";
		$content .= '<ul>'."\n";
		$content .= get_highest_rated('page', 0, $stats_mostlimit, 0, false);
		$content .= '</ul>'."\n";
	}
	if($stats_display['rated_most_post'] == 1) {
		$content .= '<p><strong>'.sprintf(_n('%s Most Rated Post', '%s Most Rated Posts', $stats_mostlimit, 'wp-postratings'), number_format_i18n($stats_mostlimit)).'</strong></p>'."\n";
		$content .= '<ul>'."\n";
		$content .= get_most_rated('post', 0, $stats_mostlimit, 0, false);
		$content .= '</ul>'."\n";
	}
	if($stats_display['rated_most_page'] == 1) {
		$content .= '<p><strong>'.sprintf(_n('%s Most Rated Page', '%s Most Rated Pages', $stats_mostlimit, 'wp-postratings'), number_format_i18n($stats_mostlimit)).'</strong></p>'."\n";
		$content .= '<ul>'."\n";
		$content .= get_most_rated('page', 0, $stats_mostlimit, 0, false);
		$content .= '</ul>'."\n";
	}
	return $content;
}


### Function: Gets HTML of rating images
function get_ratings_images($ratings_custom, $ratings_max, $post_rating, $ratings_image, $image_alt, $insert_half) {
	$ratings_images = '';
	if(is_rtl() && file_exists(WP_PLUGIN_DIR.'/wp-postratings/images/'.$ratings_image.'/rating_start-rtl.'.RATINGS_IMG_EXT)) {
		$ratings_images .= '<img src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_start-rtl.'.RATINGS_IMG_EXT).'" alt="" class="post-ratings-image" />';
	} elseif(file_exists(WP_PLUGIN_DIR.'/wp-postratings/images/'.$ratings_image.'/rating_start.'.RATINGS_IMG_EXT)) {
		$ratings_images .= '<img src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_start.'.RATINGS_IMG_EXT).'" alt="" class="post-ratings-image" />';
	}
	if($ratings_custom) {
		for($i=1; $i <= $ratings_max; $i++) {
			if($i <= $post_rating) {
				$ratings_images .= '<img src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_'.$i.'_on.'.RATINGS_IMG_EXT).'" alt="'.$image_alt.'" title="'.$image_alt.'" class="post-ratings-image" />';
			} elseif($i == $insert_half) {
				if (is_rtl() && file_exists(WP_PLUGIN_DIR.'/wp-postratings/images/'.$ratings_image.'/rating_'.$i.'_half-rtl.'.RATINGS_IMG_EXT)) {
					$ratings_images .= '<img src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_'.$i.'_half-rtl.'.RATINGS_IMG_EXT).'" alt="'.$image_alt.'" title="'.$image_alt.'" class="post-ratings-image" />';
				} else {
					$ratings_images .= '<img src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_'.$i.'_half.'.RATINGS_IMG_EXT).'" alt="'.$image_alt.'" title="'.$image_alt.'" class="post-ratings-image" />';
				}
			} else {
				$ratings_images .= '<img src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_'.$i.'_off.'.RATINGS_IMG_EXT).'" alt="'.$image_alt.'" title="'.$image_alt.'" class="post-ratings-image" />';
			}
		}
	} else {
		for($i=1; $i <= $ratings_max; $i++) {
			if($i <= $post_rating) {
				$ratings_images .= '<img src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_on.'.RATINGS_IMG_EXT).'" alt="'.$image_alt.'" title="'.$image_alt.'" class="post-ratings-image" />';
			} elseif($i == $insert_half) {
				if (is_rtl() && file_exists(WP_PLUGIN_DIR.'/wp-postratings/images/'.$ratings_image.'/rating_half-rtl.'.RATINGS_IMG_EXT)) {
					$ratings_images .= '<img src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_half-rtl.'.RATINGS_IMG_EXT).'" alt="'.$image_alt.'" title="'.$image_alt.'" class="post-ratings-image" />';
				} else {
					$ratings_images .= '<img src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_half.'.RATINGS_IMG_EXT).'" alt="'.$image_alt.'" title="'.$image_alt.'" class="post-ratings-image" />';
				}
			} else {
				$ratings_images .= '<img src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_off.'.RATINGS_IMG_EXT).'" alt="'.$image_alt.'" title="'.$image_alt.'" class="post-ratings-image" />';
			}
		}
	}
	if(is_rtl() && file_exists(WP_PLUGIN_DIR.'/wp-postratings/images/'.$ratings_image.'/rating_end-rtl.'.RATINGS_IMG_EXT)) {
		$ratings_images .= '<img src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_end-rtl.'.RATINGS_IMG_EXT).'" alt="" class="post-ratings-image" />';
	} elseif(file_exists(WP_PLUGIN_DIR.'/wp-postratings/images/'.$ratings_image.'/rating_end.'.RATINGS_IMG_EXT)) {
		$ratings_images .= '<img src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_end.'.RATINGS_IMG_EXT).'" alt="" class="post-ratings-image" />';
	}
	return $ratings_images;
}


### Function: Gets HTML of rating images for voting
function get_ratings_images_vote($post_id, $ratings_custom, $ratings_max, $post_rating, $ratings_image, $image_alt, $insert_half, $ratings_texts) {
	$ratings_images = '';
	if(is_rtl() && file_exists(WP_PLUGIN_DIR.'/wp-postratings/images/'.$ratings_image.'/rating_start-rtl.'.RATINGS_IMG_EXT)) {
		$ratings_images .= '<img src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_start-rtl.'.RATINGS_IMG_EXT).'" alt="" class="post-ratings-image" />';
	} elseif(file_exists(WP_PLUGIN_DIR.'/wp-postratings/images/'.$ratings_image.'/rating_start.'.RATINGS_IMG_EXT)) {
		$ratings_images .= '<img src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_start.'.RATINGS_IMG_EXT).'" alt="" class="post-ratings-image" />';
	}
	if($ratings_custom) {
		for($i=1; $i <= $ratings_max; $i++) {
			if (is_rtl() && file_exists(WP_PLUGIN_DIR.'/wp-postratings/images/'.$ratings_image.'/rating_'.$i.'half-rtl.'.RATINGS_IMG_EXT)) {
				$use_half_rtl = 1;
			} else {
				$use_half_rtl = 0;
			}
			$ratings_text = esc_attr( stripslashes( $ratings_texts[$i-1] ) );
			$ratings_text_js = esc_js( $ratings_text );
			if($i <= $post_rating) {
				$ratings_images .= '<img id="rating_'.$post_id.'_'.$i.'" src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_'.$i.'_on.'.RATINGS_IMG_EXT).'" alt="'.$ratings_text.'" title="'.$ratings_text.'" onmouseover="current_rating('.$post_id.', '.$i.', \''.$ratings_text_js.'\');" onmouseout="ratings_off('.$post_rating.', '.$insert_half.', '.$use_half_rtl.');" onclick="rate_post();" onkeypress="rate_post();" style="cursor: pointer; border: 0px;" />';
			} elseif($i == $insert_half) {
				if ($use_half_rtl) {
					$ratings_images .= '<img id="rating_'.$post_id.'_'.$i.'" src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_'.$i.'_half-rtl.'.RATINGS_IMG_EXT).'" alt="'.$ratings_text.'" title="'.$ratings_text.'" onmouseover="current_rating('.$post_id.', '.$i.', \''.$ratings_text_js.'\');" onmouseout="ratings_off('.$post_rating.', '.$insert_half.', '.$use_half_rtl.');" onclick="rate_post();" onkeypress="rate_post();" style="cursor: pointer; border: 0px;" />';
				} else {
					$ratings_images .= '<img id="rating_'.$post_id.'_'.$i.'" src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_'.$i.'_half.'.RATINGS_IMG_EXT).'" alt="'.$ratings_text.'" title="'.$ratings_text.'" onmouseover="current_rating('.$post_id.', '.$i.', \''.$ratings_text_js.'\');" onmouseout="ratings_off('.$post_rating.', '.$insert_half.', '.$use_half_rtl.');" onclick="rate_post();" onkeypress="rate_post();" style="cursor: pointer; border: 0px;" />';
				}
			} else {
				$ratings_images .= '<img id="rating_'.$post_id.'_'.$i.'" src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_'.$i.'_off.'.RATINGS_IMG_EXT).'" alt="'.$ratings_text.'" title="'.$ratings_text.'" onmouseover="current_rating('.$post_id.', '.$i.', \''.$ratings_text_js.'\');" onmouseout="ratings_off('.$post_rating.', '.$insert_half.', '.$use_half_rtl.');" onclick="rate_post();" onkeypress="rate_post();" style="cursor: pointer; border: 0px;" />';
			}
		}
	} else {
		if (is_rtl() && file_exists(WP_PLUGIN_DIR.'/wp-postratings/images/'.$ratings_image.'/rating_half-rtl.'.RATINGS_IMG_EXT)) {
			$use_half_rtl = 1;
		} else {
			$use_half_rtl = 0;
		}
		for($i=1; $i <= $ratings_max; $i++) {
			$ratings_text = esc_attr( stripslashes( $ratings_texts[$i-1] ) );
			$ratings_text_js = esc_js( $ratings_text );
			if($i <= $post_rating) {
				$ratings_images .= '<img id="rating_'.$post_id.'_'.$i.'" src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_on.'.RATINGS_IMG_EXT).'" alt="'.$ratings_text.'" title="'.$ratings_text.'" onmouseover="current_rating('.$post_id.', '.$i.', \''.$ratings_text_js.'\');" onmouseout="ratings_off('.$post_rating.', '.$insert_half.', '.$use_half_rtl.');" onclick="rate_post();" onkeypress="rate_post();" style="cursor: pointer; border: 0px;" />';
			} elseif($i == $insert_half) {
				if ($use_half_rtl) {
					$ratings_images .= '<img id="rating_'.$post_id.'_'.$i.'" src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_half-rtl.'.RATINGS_IMG_EXT).'" alt="'.$ratings_text.'" title="'.$ratings_text.'" onmouseover="current_rating('.$post_id.', '.$i.', \''.$ratings_text_js.'\');" onmouseout="ratings_off('.$post_rating.', '.$insert_half.', '.$use_half_rtl.');" onclick="rate_post();" onkeypress="rate_post();" style="cursor: pointer; border: 0px;" />';
				} else {
					$ratings_images .= '<img id="rating_'.$post_id.'_'.$i.'" src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_half.'.RATINGS_IMG_EXT).'" alt="'.$ratings_text.'" title="'.$ratings_text.'" onmouseover="current_rating('.$post_id.', '.$i.', \''.$ratings_text_js.'\');" onmouseout="ratings_off('.$post_rating.', '.$insert_half.', '.$use_half_rtl.');" onclick="rate_post();" onkeypress="rate_post();" style="cursor: pointer; border: 0px;" />';
				}
			} else {
				$ratings_images .= '<img id="rating_'.$post_id.'_'.$i.'" src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_off.'.RATINGS_IMG_EXT).'" alt="'.$ratings_text.'" title="'.$ratings_text.'" onmouseover="current_rating('.$post_id.', '.$i.', \''.$ratings_text_js.'\');" onmouseout="ratings_off('.$post_rating.', '.$insert_half.', '.$use_half_rtl.');" onclick="rate_post();" onkeypress="rate_post();" style="cursor: pointer; border: 0px;" />';
			}
		}
	}
	if(is_rtl() && file_exists(WP_PLUGIN_DIR.'/wp-postratings/images/'.$ratings_image.'/rating_end-rtl.'.RATINGS_IMG_EXT)) {
		$ratings_images .= '<img src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_end-rtl.'.RATINGS_IMG_EXT).'" alt="" class="post-ratings-image" />';
	} elseif(file_exists(WP_PLUGIN_DIR.'/wp-postratings/images/'.$ratings_image.'/rating_end.'.RATINGS_IMG_EXT)) {
		$ratings_images .= '<img src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_end.'.RATINGS_IMG_EXT).'" alt="" class="post-ratings-image" />';
	}
	return $ratings_images;
}


### Function: Gets HTML of rating images for comment author
function get_ratings_images_comment_author($ratings_custom, $ratings_max, $comment_author_rating, $ratings_image, $image_alt) {
	$ratings_images = '';
	if(is_rtl() && file_exists(WP_PLUGIN_DIR.'/wp-postratings/images/'.$ratings_image.'/rating_start-rtl.'.RATINGS_IMG_EXT)) {
		$ratings_images .= '<img src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_start-rtl.'.RATINGS_IMG_EXT).'" alt="" class="post-ratings-image" />';
	} elseif(file_exists(WP_PLUGIN_DIR.'/wp-postratings/images/'.$ratings_image.'/rating_start.'.RATINGS_IMG_EXT)) {
		$ratings_images .= '<img src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_start.'.RATINGS_IMG_EXT).'" alt="" class="post-ratings-image" />';
	}
	if($ratings_custom && $ratings_max == 2) {
		if($comment_author_rating > 0) {
			$ratings_images .= '<img src="'.plugins_url('wp-postratings/images/'.$ratings_image.'/rating_2_on.'.RATINGS_IMG_EXT).'" alt="'.$image_alt.'" title="'.$image_alt.'" class="post-ratings-image" />';
		} else {
			$ratings_images .= '<img src="'.plugins_url('wp-postratings/images/'.$ratings_image.'/rating_1_on.'.RATINGS_IMG_EXT).'" alt="'.$image_alt.'" title="'.$image_alt.'" class="post-ratings-image" />';
		}
	} elseif($ratings_custom) {
		for($i=1; $i <= $ratings_max; $i++) {
			if($i <= $comment_author_rating) {
				$ratings_images .= '<img src="'.plugins_url('wp-postratings/images/'.$ratings_image.'/rating_'.$i.'_on.'.RATINGS_IMG_EXT).'" alt="'.$image_alt.'" title="'.$image_alt.'" class="post-ratings-image" />';
			} else {
				$ratings_images .= '<img src="'.plugins_url('wp-postratings/images/'.$ratings_image.'/rating_'.$i.'_off.'.RATINGS_IMG_EXT).'" alt="'.$image_alt.'" title="'.$image_alt.'" class="post-ratings-image" />';
			}
		}
	} else {
		for($i=1; $i <= $ratings_max; $i++) {
			if($i <= $comment_author_rating) {
				$ratings_images .= '<img src="'.plugins_url('wp-postratings/images/'.$ratings_image.'/rating_on.'.RATINGS_IMG_EXT).'" alt="'.$image_alt.'" title="'.$image_alt.'" class="post-ratings-image" />';
			} else {
				$ratings_images .= '<img src="'.plugins_url('wp-postratings/images/'.$ratings_image.'/rating_off.'.RATINGS_IMG_EXT).'" alt="'.$image_alt.'" title="'.$image_alt.'" class="post-ratings-image" />';
			}
		}
	}
	if(is_rtl() && file_exists(WP_PLUGIN_DIR.'/wp-postratings/images/'.$ratings_image.'/rating_end-rtl.'.RATINGS_IMG_EXT)) {
		$ratings_images .= '<img src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_end-rtl.'.RATINGS_IMG_EXT).'" alt="" class="post-ratings-image" />';
	} elseif(file_exists(WP_PLUGIN_DIR.'/wp-postratings/images/'.$ratings_image.'/rating_end.'.RATINGS_IMG_EXT)) {
		$ratings_images .= '<img src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_end.'.RATINGS_IMG_EXT).'" alt="" class="post-ratings-image" />';
	}
	return $ratings_images;
}

### Function: Replaces the template's variables with appropriate values
function expand_ratings_template($template, $post_data, $post_ratings_data = null, $max_post_title_chars = 0, $is_main_loop = true) {
	global $post;

	// Get global variables
	$ratings_image = get_option('postratings_image');
	$ratings_max = intval(get_option('postratings_max'));
	$ratings_custom = intval(get_option('postratings_customrating'));
	$ratings_options = get_option('postratings_options');

	if(is_object($post_data)) {
		$post_id = $post_data->ID;
	} else {
		$post_id = $post_data;
	}

	// Most likely from coming from Widget
	if(isset($post_data->ratings_users)) {
		$post_ratings_users = intval($post_data->ratings_users);
		$post_ratings_score = intval($post_data->ratings_score);
		$post_ratings_average = floatval($post_data->ratings_average);
	// Most Likely coming from the_ratings_vote or the_ratings_rate
	} else if(isset($post_ratings_data->ratings_users)) {
		$post_ratings_users = intval($post_ratings_data->ratings_users);
		$post_ratings_score = intval($post_ratings_data->ratings_score);
		$post_ratings_average = floatval($post_ratings_data->ratings_average);
	} else {
		if(get_the_ID() != $post_id) {
			$post_ratings_data = get_post_custom($post_id);
		} else {
			$post_ratings_data = get_post_custom();
		}

		$post_ratings_users = is_array($post_ratings_data) && array_key_exists('ratings_users', $post_ratings_data) ? intval($post_ratings_data['ratings_users'][0]) : 0;
		$post_ratings_score = is_array($post_ratings_data) && array_key_exists('ratings_score', $post_ratings_data) ? intval($post_ratings_data['ratings_score'][0]) : 0;
		$post_ratings_average = is_array($post_ratings_data) && array_key_exists('ratings_average', $post_ratings_data) ? floatval($post_ratings_data['ratings_average'][0]) : 0;
	}

	if($post_ratings_score == 0 || $post_ratings_users == 0) {
		$post_ratings = 0;
		$post_ratings_average = 0;
		$post_ratings_percentage = 0;
	} else {
		$post_ratings = round($post_ratings_average, 1);
		$post_ratings_percentage = round((($post_ratings_score/$post_ratings_users)/$ratings_max) * 100, 2);
	}
	$post_ratings_text = '<span class="post-ratings-text" id="ratings_'.$post_id.'_text"></span>';
	// Get the image's alt text
	if($ratings_custom && $ratings_max == 2) {
		if($post_ratings_score > 0) {
			$post_ratings_score = '+'.$post_ratings_score;
		}
		$post_ratings_alt_text = sprintf(_n('%s rating', '%s rating', $post_ratings_score, 'wp-postratings'), number_format_i18n($post_ratings_score)).__(',', 'wp-postratings').' '.sprintf(_n('%s vote', '%s votes', $post_ratings_users, 'wp-postratings'), number_format_i18n($post_ratings_users));
	} else {
		$post_ratings_score = number_format_i18n($post_ratings_score);
		$post_ratings_alt_text = sprintf(_n('%s vote', '%s votes', $post_ratings_users, 'wp-postratings'), number_format_i18n($post_ratings_users)).__(',', 'wp-postratings').' '.__('average', 'wp-postratings').': '.number_format_i18n($post_ratings_average, 2).' '.__('out of', 'wp-postratings').' '.number_format_i18n($ratings_max);
	}
	// Check for half star
	$insert_half = 0;
	$average_diff = abs(floor($post_ratings_average)-$post_ratings);
	if($average_diff >= 0.25 && $average_diff <= 0.75) {
		$insert_half = ceil($post_ratings_average);
	} elseif($average_diff > 0.75) {
		$insert_half = ceil($post_ratings);
	}
	// Replace the variables
	$value = $template;
	if (strpos($template, '%RATINGS_IMAGES%') !== false) {
		$post_ratings_images = get_ratings_images($ratings_custom, $ratings_max, $post_ratings, $ratings_image, $post_ratings_alt_text, $insert_half);
		$value = str_replace("%RATINGS_IMAGES%", $post_ratings_images, $value);
	}
	if (strpos($template, '%RATINGS_IMAGES_VOTE%') !== false) {
		$ratings_texts = get_option('postratings_ratingstext');
		$post_ratings_images = get_ratings_images_vote($post_id, $ratings_custom, $ratings_max, $post_ratings, $ratings_image, $post_ratings_alt_text, $insert_half, $ratings_texts);
		$value = str_replace("%RATINGS_IMAGES_VOTE%", $post_ratings_images, $value);
	}
	$value = str_replace("%RATINGS_ALT_TEXT%", $post_ratings_alt_text, $value);
	$value = str_replace("%RATINGS_TEXT%", $post_ratings_text, $value);
	$value = str_replace("%RATINGS_MAX%", number_format_i18n($ratings_max), $value);
	$value = str_replace("%RATINGS_SCORE%", $post_ratings_score, $value);
	$value = str_replace("%RATINGS_AVERAGE%", number_format_i18n($post_ratings_average, 2), $value);
	$value = str_replace("%RATINGS_PERCENTAGE%", number_format_i18n($post_ratings_percentage, 2), $value);
	$value = str_replace("%RATINGS_USERS%", number_format_i18n($post_ratings_users), $value);

	// Post Template Variables
	$post_link = get_permalink($post_data);
	$post_title = get_the_title($post_data);
	if ($max_post_title_chars > 0) {
		$post_title = snippet_text($post_title, $max_post_title_chars);
	}
	$value = str_replace("%POST_ID%", $post_id, $value);
	$value = str_replace("%POST_TITLE%", $post_title, $value);
	$value = str_replace("%POST_URL%", $post_link, $value);

	if (strpos($template, '%POST_EXCERPT%') !== false) {
		if (get_the_ID() != $post_id) {
			$post = &get_post($post_id);
		}
		$post_excerpt = ratings_post_excerpt($post_id, $post->post_excerpt, $post->post_content, $post->post_password);
		$value = str_replace("%POST_EXCERPT%", $post_excerpt, $value);
	}
	if (strpos($template, '%POST_CONTENT%') !== false) {
		if (get_the_ID() != $post_id) {
			$post = &get_post($post_id);
		}
		$value = str_replace("%POST_CONTENT%", get_the_content(), $value);
	}

	// Google Rich Snippet
	$ratings_options['richsnippet'] = isset( $ratings_options['richsnippet'] ) ? $ratings_options['richsnippet'] : 1;
	if( $ratings_options['richsnippet'] && ( is_single() || is_page() ) && $is_main_loop && $post_ratings_average > 0 ) {
		$itemtype = apply_filters( 'wp_postratings_schema_itemtype', 'itemscope itemtype="http://schema.org/Article"' );

		if( empty( $post_excerpt ) ) {
			$post_excerpt = ratings_post_excerpt( $post_id, $post->post_excerpt, $post->post_content, $post->post_password );
		}
		$post_meta = '<meta itemprop="headline" content="' . esc_attr( $post_title ) . '" />';
		$post_meta .= '<meta itemprop="description" content="' . wp_kses( $post_excerpt, array() ) . '" />';
		$post_meta .= '<meta itemprop="datePublished" content="' . get_the_time( 'c' ) . '" />';
		$post_meta .= '<meta itemprop="url" content="' . $post_link . '" />';
		if( has_post_thumbnail() ) {
			$thumbnail = wp_get_attachment_image_src( get_post_thumbnail_id( null ) );
			if( ! empty( $thumbnail ) ) {
				$post_meta .= '<meta itemprop="image" content="' . $thumbnail[0] . '" />';
			}
		}
		$ratings_meta = '<div style="display: none;" itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating">';
		$ratings_meta .= '<meta itemprop="bestRating" content="' . $ratings_max . '" />';
		$ratings_meta .= '<meta itemprop="worstRating" content="1" />';
		$ratings_meta .= '<meta itemprop="ratingValue" content="' . $post_ratings_average . '" />';
		$ratings_meta .= '<meta itemprop="ratingCount" content="' . $post_ratings_users . '" />';
		$ratings_meta .= '</div>';

		$value = empty( $itemtype ) ? $value . $ratings_meta : $value . $post_meta . $ratings_meta;
	}

	return apply_filters( 'expand_ratings_template', $value );
}


### Class: WP-PostRatings Widget
 class WP_Widget_PostRatings extends WP_Widget {
	// Constructor
	function __construct() {
		$widget_ops = array('description' => __('WP-PostRatings ratings statistics', 'wp-postratings'));
		parent::__construct('ratings-widget', __('Ratings', 'wp-postratings'), $widget_ops);
	}

	// Display Widget
	function widget($args, $instance) {
		extract($args);
		$title = apply_filters('widget_title', esc_attr($instance['title']));
		$type = esc_attr($instance['type']);
		$mode = esc_attr($instance['mode']);
		$limit = intval($instance['limit']);
		$min_votes = intval($instance['min_votes']);
		$chars = intval($instance['chars']);
		$cat_ids = explode(',', esc_attr($instance['cat_ids']));
		$time_range = esc_attr($instance['time_range']);
		echo $before_widget.$before_title.$title.$after_title;
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
		echo $after_widget;
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

	// DIsplay Widget Control Form
	function form($instance) {
		global $wpdb;
		$instance = wp_parse_args((array) $instance, array('title' => __('Ratings', 'wp-postratings'), 'type' => 'highest_rated', 'mode' => '', 'limit' => 10, 'min_votes' => 0, 'chars' => 200, 'cat_ids' => '0', 'time_range' => '1 day'));
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
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'wp-postratings'); ?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('type'); ?>"><?php _e('Statistics Type:', 'wp-postratings'); ?>
				<select name="<?php echo $this->get_field_name('type'); ?>" id="<?php echo $this->get_field_id('type'); ?>" class="widefat">
					<option value="most_rated"<?php selected('most_rated', $type); ?>><?php _e('Most Rated', 'wp-postratings'); ?></option>
					<option value="most_rated_category"<?php selected('most_rated_category', $type); ?>><?php _e('Most Rated By Category', 'wp-postratings'); ?></option>
					<option value="most_rated_range"<?php selected('most_rated_range', $type); ?>><?php _e('Most Rated By Time Range', 'wp-postratings'); ?></option>
					<option value="most_rated_range_category"<?php selected('most_rated_range_category', $type); ?>><?php _e('Most Rated By Time Range And Category', 'wp-postratings'); ?></option>
					<optgroup>&nbsp;</optgroup>
					<option value="highest_rated"<?php selected('highest_rated', $type); ?>><?php _e('Highest Rated', 'wp-postratings'); ?></option>
					<option value="highest_rated_category"<?php selected('highest_rated_category', $type); ?>><?php _e('Highest Rated By Category', 'wp-postratings'); ?></option>
					<option value="highest_rated_range"<?php selected('highest_rated_range', $type); ?>><?php _e('Highest Rated By Time Range', 'wp-postratings'); ?></option>
					<option value="highest_rated_range_category"<?php selected('highest_rated_range_category', $type); ?>><?php _e('Highest Rated By Time Range And Category', 'wp-postratings'); ?></option>
					<optgroup>&nbsp;</optgroup>
					<option value="lowest_rated"<?php selected('lowest_rated', $type); ?>><?php _e('Lowest Rated', 'wp-postratings'); ?></option>
					<option value="lowest_rated_category"<?php selected('lowest_rated_category', $type); ?>><?php _e('Lowest Rated By Category', 'wp-postratings'); ?></option>
					<option value="lowest_rated_range"<?php selected('lowest_rated_range', $type); ?>><?php _e('Lowest Rated By Time Range', 'wp-postratings'); ?></option>
					<optgroup>&nbsp;</optgroup>
					<option value="highest_score"<?php selected('highest_score', $type); ?>><?php _e('Highest Score', 'wp-postratings'); ?></option>
					<option value="highest_score_category"<?php selected('highest_score_category', $type); ?>><?php _e('Highest Score By Category', 'wp-postratings'); ?></option>
					<option value="highest_score_range"<?php selected('highest_score_range', $type); ?>><?php _e('Highest Score By Time Range', 'wp-postratings'); ?></option>
					<option value="highest_score_range_category"<?php selected('highest_score_range_category', $type); ?>><?php _e('Highest Score By Time Range And Category', 'wp-postratings'); ?></option>
				</select>
			</label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('mode'); ?>"><?php _e('Include Ratings From:', 'wp-postratings'); ?>
				<select name="<?php echo $this->get_field_name('mode'); ?>" id="<?php echo $this->get_field_id('mode'); ?>" class="widefat">
					<option value=""<?php selected( '', $mode ); ?>><?php _e( 'All', 'wp-postratings' ); ?></option>
						<?php if( $post_types > 0 ): ?>
							<?php foreach( $post_types as $post_type ): ?>
								<option value="<?php echo $post_type; ?>"<?php selected( $post_type, $mode ); ?>><?php printf( __( '%s Only', 'wp-postratings' ), ucfirst( $post_type ) ); ?></option>
							<?php endforeach; ?>
						<?php endif; ?>
				</select>
			</label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('limit'); ?>"><?php _e('No. Of Records To Show:', 'wp-postratings'); ?> <input class="widefat" id="<?php echo $this->get_field_id('limit'); ?>" name="<?php echo $this->get_field_name('limit'); ?>" type="text" value="<?php echo $limit; ?>" /></label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('min_votes'); ?>"><?php _e('Minimum Votes:', 'wp-postratings'); ?> <span style="color: red;">*</span> <input class="widefat" id="<?php echo $this->get_field_id('min_votes'); ?>" name="<?php echo $this->get_field_name('min_votes'); ?>" type="text" value="<?php echo $min_votes; ?>" size="4" /></label><br />
			<small><?php _e('You can set the minimum votes that a post or page must have before it gets displayed.', 'wp-postratings'); ?></small>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('chars'); ?>"><?php _e('Maximum Post Title Length (Characters):', 'wp-postratings'); ?> <input class="widefat" id="<?php echo $this->get_field_id('chars'); ?>" name="<?php echo $this->get_field_name('chars'); ?>" type="text" value="<?php echo $chars; ?>" /></label><br />
			<small><?php _e('<strong>0</strong> to disable.', 'wp-postratings'); ?></small>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('cat_ids'); ?>"><?php _e('Category IDs:', 'wp-postratings'); ?> <span style="color: red;">**</span> <input class="widefat" id="<?php echo $this->get_field_id('cat_ids'); ?>" name="<?php echo $this->get_field_name('cat_ids'); ?>" type="text" value="<?php echo $cat_ids; ?>" /></label><br />
			<small><?php _e('Seperate mutiple categories with commas.', 'wp-postratings'); ?></small>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('time_range'); ?>"><?php _e('Time Range:', 'wp-postratings'); ?> <span style="color: red;">**</span> <input class="widefat" id="<?php echo $this->get_field_id('time_range'); ?>" name="<?php echo $this->get_field_name('time_range'); ?>" type="text" value="<?php echo $time_range; ?>" /></label><br />
			<small><?php _e('Use values like <strong>1 day</strong>, <strong>2 weeks</strong>, <strong>1 month</strong>.', 'wp-postratings'); ?></small>
		</p>
		<p style="color: red;">
			<small><?php _e('* Time range statistics does not support Minimum Votes field, you can ignore that it.', 'wp-postratings'); ?></small><br />
			<small><?php _e('** If you are not using any category or time range statistics, you can ignore it.', 'wp-postratings'); ?></small>
		<p>
		<input type="hidden" id="<?php echo $this->get_field_id('submit'); ?>" name="<?php echo $this->get_field_name('submit'); ?>" value="1" />
<?php
	}
}


### Function: Init WP-PostRatings Widget
add_action('widgets_init', 'widget_ratings_init');
function widget_ratings_init() {
	postratings_textdomain();
	register_widget('WP_Widget_PostRatings');
}


### Function: Activate Plugin
register_activation_hook( __FILE__, 'ratings_activation' );
function ratings_activation( $network_wide )
{
	if ( is_multisite() && $network_wide )
	{
		$ms_sites = wp_get_sites();

		if( 0 < sizeof( $ms_sites ) )
		{
			foreach ( $ms_sites as $ms_site )
			{
				switch_to_blog( $ms_site['blog_id'] );
				ratings_activate();
			}
		}

		restore_current_blog();
	}
	else
	{
		ratings_activate();
	}
}

function ratings_activate() {
	global $wpdb;

	// Create Post Ratings Table
	$create_sql = "CREATE TABLE $wpdb->ratings (".
			"rating_id INT(11) NOT NULL auto_increment,".
			"rating_postid INT(11) NOT NULL ,".
			"rating_posttitle TEXT NOT NULL,".
			"rating_rating INT(2) NOT NULL ,".
			"rating_timestamp VARCHAR(15) NOT NULL ,".
			"rating_ip VARCHAR(40) NOT NULL ,".
			"rating_host VARCHAR(200) NOT NULL,".
			"rating_username VARCHAR(50) NOT NULL,".
			"rating_userid int(10) NOT NULL default '0',".
			"PRIMARY KEY (rating_id));";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $create_sql );

    // Add In Options (4 Records)
	add_option('postratings_image', 'stars' );
	add_option('postratings_max', '5' );
	add_option('postratings_template_vote', '%RATINGS_IMAGES_VOTE% (<strong>%RATINGS_USERS%</strong> '.__('votes', 'wp-postratings').__(',', 'wp-postratings').' '.__('average', 'wp-postratings').': <strong>%RATINGS_AVERAGE%</strong> '.__('out of', 'wp-postratings').' %RATINGS_MAX%)<br />%RATINGS_TEXT%' );
	add_option('postratings_template_text', '%RATINGS_IMAGES% (<em><strong>%RATINGS_USERS%</strong> '.__('votes', 'wp-postratings').__(',', 'wp-postratings').' '.__('average', 'wp-postratings').': <strong>%RATINGS_AVERAGE%</strong> '.__('out of', 'wp-postratings').' %RATINGS_MAX%'.__(',', 'wp-postratings').' <strong>'.__('rated', 'wp-postratings').'</strong></em>)' );
	add_option('postratings_template_none', '%RATINGS_IMAGES_VOTE% ('.__('No Ratings Yet', 'wp-postratings').')<br />%RATINGS_TEXT%' );
	// Database Upgrade For WP-PostRatings 1.02
	add_option('postratings_logging_method', '3' );
	add_option('postratings_allowtorate', '2' );
	// Database Uprade For WP-PostRatings 1.04
	maybe_add_column($wpdb->ratings, 'rating_userid', "ALTER TABLE $wpdb->ratings ADD rating_userid INT( 10 ) NOT NULL DEFAULT '0';");
	// Database Uprade For WP-PostRatings 1.05
	add_option('postratings_ratingstext', array(__('1 Star', 'wp-postratings'), __('2 Stars', 'wp-postratings'), __('3 Stars', 'wp-postratings'), __('4 Stars', 'wp-postratings'), __('5 Stars', 'wp-postratings')) );
	add_option('postratings_template_highestrated', '<li><a href="%POST_URL%" title="%POST_TITLE%">%POST_TITLE%</a> %RATINGS_IMAGES% (%RATINGS_AVERAGE% '.__('out of', 'wp-postratings').' %RATINGS_MAX%)</li>' );
	// Database Upgrade For WP-PostRatings 1.11
	add_option('postratings_ajax_style', array('loading' => 1, 'fading' => 1) );
	// Database Upgrade For WP-PostRatings 1.20
	add_option('postratings_ratingsvalue', array(1,2,3,4,5) );
	add_option('postratings_customrating', 0 );
	add_option('postratings_template_permission', '%RATINGS_IMAGES% (<em><strong>%RATINGS_USERS%</strong> '.__('votes', 'wp-postratings').__(',', 'wp-postratings').' '.__('average', 'wp-postratings').': <strong>%RATINGS_AVERAGE%</strong> '.__('out of', 'wp-postratings').' %RATINGS_MAX%</em>)<br /><em>'.__('You need to be a registered member to rate this post.', 'wp-postratings').'</em>' );
	// Database Upgrade For WP-PostRatings 1.30
	add_option('postratings_template_mostrated', '<li><a href="%POST_URL%"  title="%POST_TITLE%">%POST_TITLE%</a> - %RATINGS_USERS% '.__('votes', 'wp-postratings').'</li>' );
	// Database Upgrade For WP-PostRatings 1.50
	delete_option('widget_ratings_highest_rated');
	delete_option('widget_ratings_most_rated');

	// Set 'manage_ratings' Capabilities To Administrator
	$role = get_role( 'administrator' );
	$role->add_cap( 'manage_ratings' );
}


### Seperate PostRatings Stats For Readability
require_once('postratings-stats.php');
