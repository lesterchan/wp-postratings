<?php
/*
Plugin Name: WP-PostRatings
Plugin URI: https://lesterchan.net/portfolio/programming/php/
Description: Adds an AJAX rating system for your WordPress site's content.
Version: 1.86.2
Author: Lester 'GaMerZ' Chan
Author URI: https://lesterchan.net
Text Domain: wp-postratings
*/


/*
	Copyright 2019 Lester Chan (email: lesterchan@gmail.com)

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


/**
 * Security check
 * Prevent direct access to the file.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version
 * Set wp-postratings plugin version.
 */
define( 'WP_POSTRATINGS_VERSION', '1.86.2' );

/**
 * Rating logs table name
 */
global $wpdb;
$wpdb->ratings = $wpdb->prefix . 'ratings';

/**
 * Load plugin files
 * Require the plugin files in an alphabetical order.
 */
require_once 'includes/postratings-activation.php';
require_once 'includes/postratings-admin.php';
require_once 'includes/postratings-i18n.php';
require_once 'includes/postratings-scripts.php';
require_once 'includes/postratings-shortcodes.php';
require_once 'includes/postratings-stats.php';
require_once 'includes/postratings-widgets.php';

/**
 * Register plugin activation hook
 */
register_activation_hook( __FILE__, 'ratings_activation' );

### Define Image Extension
add_action( 'init', 'postratings_init' );
function postratings_init() {
	if ( ! defined( 'RATINGS_IMG_EXT' ) ) {
		define( 'RATINGS_IMG_EXT', apply_filters( 'wp_postratings_image_extension', 'gif' ) );
	}
}

### Function: Display The Rating For The Post
function the_ratings($start_tag = 'div', $custom_id = 0, $display = true) {
	global $id;
	// Allow Custom ID
	if ( (int) $custom_id > 0 ) {
		$ratings_id = $custom_id;
	} else {
		// If Global $id is 0, Get The Loop Post ID
		if ( $id === 0 ) {
			$ratings_id = get_the_ID();
		} elseif ( null === $id ) {
			global $post;
			$ratings_id = $post->ID;
		} else {
			$ratings_id = $id;
		}
	}

	$ratings_id = (int) $ratings_id;

	// Loading Style
	$postratings_ajax_style = get_option('postratings_ajax_style');
	if ( (int) $postratings_ajax_style['loading'] === 1 ) {
		$loading = '<' . $start_tag . ' id="post-ratings-' . $ratings_id . '-loading" class="post-ratings-loading">
			<img src="' . plugins_url('wp-postratings/images/loading.gif') . '" width="16" height="16" class="post-ratings-image" />' . esc_html__( 'Loading...', 'wp-postratings' ) . '</' . $start_tag . '>';
	} else {
		$loading = '';
	}
	// Check To See Whether User Has Voted
	$user_voted = check_rated($ratings_id);
	// HTML Attributes
	$ratings_options = get_option('postratings_options');
	$ratings_options['richsnippet'] = isset( $ratings_options['richsnippet'] ) ? $ratings_options['richsnippet'] : 1;
	if( is_singular() && $ratings_options['richsnippet'] ) {
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
		}
	// If User Is Not Allowed To Rate
	} else if(!check_allowtorate()) {
		if(!$display) {
			return "<$start_tag $attributes>".the_ratings_results($ratings_id, 0, 0, 0, 1).'</'.$start_tag.'>'.$loading;
		} else {
			echo "<$start_tag $attributes>".the_ratings_results($ratings_id, 0, 0, 0, 1).'</'.$start_tag.'>'.$loading;
		}
	// If User Has Not Voted
	} else {
		if(!$display) {
			return "<$start_tag $attributes data-nonce=\"".wp_create_nonce('postratings_'.$ratings_id.'-nonce')."\">".the_ratings_vote($ratings_id).'</'.$start_tag.'>'.$loading;
		} else {
			echo "<$start_tag $attributes data-nonce=\"".wp_create_nonce('postratings_'.$ratings_id.'-nonce')."\">".the_ratings_vote($ratings_id).'</'.$start_tag.'>'.$loading;
		}
	}
}


### Function: Display Ratings Results
function the_ratings_results( $post_id, $new_user = 0, $new_score = 0, $new_average = 0, $type = 0 ) {
	if( $new_user === 0 && $new_score === 0 && $new_average === 0 ) {
		$post_ratings_data = null;
	} else {
		$post_ratings_data = new stdClass();
		$post_ratings_data->ratings_users = $new_user;
		$post_ratings_data->ratings_score = $new_score;
		$post_ratings_data->ratings_average = $new_average;
	}
	// Display The Contents
	if( $type === 1 ) {
		$template_postratings_text = stripslashes( get_option( 'postratings_template_permission' ) );
	} else {
		$template_postratings_text = stripslashes( get_option( 'postratings_template_text' ) );
	}
	// Return Post Ratings Template
	return expand_ratings_template( $template_postratings_text, $post_id, $post_ratings_data );
}


### Function: Display Ratings Vote
function the_ratings_vote($post_id, $new_user = 0, $new_score = 0, $new_average = 0) {
  if($new_user === 0 && $new_score === 0 && $new_average === 0) {
	$post_ratings_data = null;
  } else {
	$post_ratings_data = new stdClass();
	$post_ratings_data->ratings_users = $new_user;
	$post_ratings_data->ratings_score = $new_score;
	$post_ratings_data->ratings_average = $new_average;
  }
	// If No Ratings, Return No Ratings template
	if( (int) get_post_meta( $post_id, 'ratings_users', true ) === 0 ) {
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
	$allow_to_vote = (int) get_option( 'postratings_allowtorate' );
	switch($allow_to_vote) {
		// Guests Only
		case 0:
			return ! is_user_logged_in();
			break;
		// Logged-in users only
		case 1:
			return is_user_logged_in();
			break;
		// Users registered on blog (for multisite)
		case 3:
			return is_user_member_of_blog();
			break;
		// Registered Users And Guests
		case 2:
		default:
			return true;
	}
}


### Function: Check Whether User Have Rated For The Post
function check_rated( $post_id ) {
	$postratings_logging_method = (int) get_option( 'postratings_logging_method' );
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

	$rated = apply_filters( 'wp_postratings_check_rated', $rated, $post_id );

	return $rated;
}


### Function: Check Rated By Cookie
function check_rated_cookie( $post_id ) {
	if ( isset( $_COOKIE["rated_$post_id"] ) ) {
		return true;
	}

	return false;
}


### Function: Check Rated By IP
function check_rated_ip($post_id) {
	global $wpdb;
	// Check IP From IP Logging Database
	$get_rated = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->ratings} WHERE rating_postid = %d AND (rating_ip = %s OR rating_ip = %s)", $post_id, ratings_get_ipaddress(), get_ipaddress()  ) );
	// 0: False | > 0: True
	return (int) $get_rated;
}


### Function: Check Rated By Username
function check_rated_username($post_id) {
	global $wpdb, $user_ID;
	if ( !is_user_logged_in() ) {
		return 0;
	}
	// Check User ID From IP Logging Database
	$get_rated = $wpdb->get_var( $wpdb->prepare( "SELECT rating_userid FROM {$wpdb->ratings} WHERE rating_postid = %d AND rating_userid = %d", $post_id, $user_ID ) );
	// 0: False | > 0: True
	return (int) $get_rated;
}


### Function: Get Comment Authors Ratings
add_action ('loop_start', 'get_comment_authors_ratings' );
function get_comment_authors_ratings() {
	global $wpdb, $post, $comment_authors_ratings;
	$comment_authors_ratings_results = null;
	if ( !is_feed() && !is_admin() ) {
		$comment_authors_ratings = array();
		if ( $post && $post->ID ) {
			$comment_authors_ratings_results = $wpdb->get_results( $wpdb->prepare( "SELECT rating_username, rating_rating, rating_ip FROM {$wpdb->ratings} WHERE rating_postid = %d", $post->ID ) );
		}
		if ( $comment_authors_ratings_results ) {
			foreach ( $comment_authors_ratings_results as $comment_authors_ratings_result ) {
				$comment_author = stripslashes( $comment_authors_ratings_result->rating_username );
				$comment_authors_ratings[ $comment_author ] = $comment_authors_ratings_result->rating_rating;
				$comment_authors_ratings[ $comment_authors_ratings_result->rating_ip ] = $comment_authors_ratings_result->rating_rating;
			}
		}
	}
}


### Function: Comment Author Ratings
function comment_author_ratings( $comment_author_specific = '', $display = true ) {
	global $comment_authors_ratings;
	if ( get_comment_type() === 'comment' ) {
		$post_ratings_images = '';
		$ratings_image = get_option( 'postratings_image' );
		$ratings_max = (int) get_option( 'postratings_max' );
		$ratings_custom = (int) get_option( 'postratings_customrating' );
		$postratings_logging_method = (int) get_option( 'postratings_logging_method' );
		$comment_author = $comment_author_specific;
		if ( empty( $comment_author ) ) {
			$comment_author = get_comment_author();
		}

		$comment_author_rating = (int) $comment_authors_ratings[ $comment_author ];
		// If we logged by username, we don't want to use IP at all.
		if ( $postratings_logging_method !== 4 && $comment_author_rating === 0 ) {
			$comment_author_rating = (int) $comment_authors_ratings[ get_comment_author_IP() ];
		}
		if ( $comment_author_rating !== 0 ) {
			// Display Rated Images
			if ( $ratings_custom && $ratings_max === 2 ) {
				if ( $comment_author_rating > 0 ) {
					$comment_author_rating = '+' . $comment_author_rating;
				}
			}
			$image_alt = sprintf( __( '%s gives a rating of %s', 'wp-postratings' ), $comment_author, $comment_author_rating );
			$post_ratings_images = get_ratings_images_comment_author( $ratings_custom, $ratings_max, $comment_author_rating, $ratings_image, $image_alt );
		}
		if($display) {
			return $post_ratings_images;
		}

		return $post_ratings_images;
	}
}


### Function:  Display Comment Author Ratings
add_filter( 'comment_text', 'comment_author_ratings_filter' );
function comment_author_ratings_filter( $comment_text ) {
	global $comment, $comment_authors_ratings;

	$output = '';
	$display_comment_author_ratings = apply_filters( 'wp_postratings_display_comment_author_ratings', false );

	if ( $display_comment_author_ratings ) {
		if ( !is_feed() && !is_admin() ) {
			if ( ! empty( $comment ) && get_comment_type() === 'comment' ) {
				$post_ratings_images = '';
				$ratings_image = get_option( 'postratings_image' );
				$ratings_max = (int) get_option( 'postratings_max' );
				$ratings_custom = (int) get_option( 'postratings_customrating' );
				$postratings_logging_method = (int) get_option( 'postratings_logging_method' );
				$comment_author = get_comment_author();
				$comment_author_rating = (int) $comment_authors_ratings[ $comment_author ];
				// If we logged by username, we don't want to use IP at all.
				if ( $postratings_logging_method !== 4 && $comment_author_rating === 0 ) {
					$comment_author_rating = (int) $comment_authors_ratings[ get_comment_author_IP() ];
				}
				if ( $comment_author_rating !== 0 ) {
					// Display Rated Images
					if ( $ratings_custom && $ratings_max === 2 ) {
						if ( $comment_author_rating > 0 ) {
							$comment_author_rating = '+' . $comment_author_rating;
						}
					}
					$image_alt = sprintf( __( '%s gives a rating of %s', 'wp-postratings' ), $comment_author, $comment_author_rating );
					$post_ratings_images = get_ratings_images_comment_author( $ratings_custom, $ratings_max, $comment_author_rating, $ratings_image, $image_alt );
				}
				$output .= '<div class="post-ratings-comment-author">';
				if ( $post_ratings_images !== '' ) {
					$output .= get_comment_author() . ' ratings for this post: ' . $post_ratings_images;
				} else {
					$output .= get_comment_author() . ' did not rate this post.';
				}
				$output .= '</div>';
			}
		}
	}
	return $comment_text.$output;
}


### Function: Get IP Address
if ( ! function_exists( 'get_ipaddress' ) ) {
	function get_ipaddress() {
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' ) as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				foreach ( explode( ',', $_SERVER[$key] ) as $ip ) {
					$ip = trim( $ip );
					if ( filter_var( $ip, FILTER_VALIDATE_IP ) !== false ) {
						return esc_attr( $ip );
					}
				}
			}
		}
	}
}
function ratings_get_ipaddress() {
	return wp_hash( get_ipaddress() );
}
function ratings_get_hostname() {
	$hostname = gethostbyaddr( get_ipaddress() );
	if ( $hostname === get_ipaddress() ) {
		return wp_privacy_anonymize_ip( get_ipaddress() );
	}

	if ( false !== $hostname ) {
		return substr( $hostname, strpos( $hostname, '.' ) + 1 );
	}

	return false;
}


### Function: Return All Images From A Rating Image Folder
function ratings_images_folder($folder_name) {
	$normal_images = array('rating_over.'.RATINGS_IMG_EXT, 'rating_on.'.RATINGS_IMG_EXT, 'rating_half.'.RATINGS_IMG_EXT, 'rating_off.'.RATINGS_IMG_EXT);
	$postratings_path = WP_PLUGIN_DIR.'/wp-postratings/images/'.$folder_name;
	$count = 0;
	$rating['max'] = 0;
	$rating['custom'] = 0;
	$rating['images'] = array();
	if(is_dir($postratings_path)) {
		if($handle = @opendir($postratings_path)) {
			while (false !== ($filename = readdir($handle))) {
				if ($filename !== '.' && $filename !== '..' && substr($filename, -8) !== '-rtl.'.RATINGS_IMG_EXT && strpos($filename, '.') !== 0) {
					if ( in_array( $filename, $normal_images, true ) ) {
						$count++;
					} elseif( (int) substr( $filename,7, -7 ) > $rating['max'] ) {
						$rating['max'] = (int) substr( $filename,7, -7 );
					}
					$rating['images'][] = $filename;
				}
			}
			closedir($handle);
		}
	}
	if ( $count !== count($normal_images ) ) {
		$rating['custom'] = 1;
	}
	if ( $rating['max'] === 0 ) {
		$rating['max'] = (int) get_option( 'postratings_max' );
	}
	return $rating;
}


### Function: Add PostRatings To Content Automatically
//add_action('the_content', 'add_ratings_to_content');
function add_ratings_to_content($content) {
	if (!is_feed()) {
		$content .= the_ratings('div', 0, false);
	}
	return $content;
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
	if( post_password_required( $post_id ) ) {
		return esc_html__( 'There is no excerpt because this is a protected post.', 'wp-postratings' );
	}
	if(empty($post_excerpt)) {
		return snippet_text( strip_tags( strip_shortcodes( $post_content ) ), 200 );
	} else {
		return strip_shortcodes( $post_excerpt );
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
		$rate = isset( $_REQUEST['rate'] ) ? (int) $_REQUEST['rate'] : 0;
		$post_id = isset( $_REQUEST['pid'] ) ? (int) $_REQUEST['pid'] : 0;

		// Verify Referer
		if(!check_ajax_referer('postratings_'.$post_id.'-nonce', 'postratings_'.$post_id.'_nonce', false)) {
			esc_html_e('Failed To Verify Referrer', 'wp-postratings');
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
			header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
			$rated = check_rated($post_id);
			// Check Whether Post Has Been Rated By User
			if(!$rated) {
				// Check Whether Is There A Valid Post
				$post = get_post($post_id);
				// If Valid Post Then We Rate It
				if($post && !wp_is_post_revision($post)) {
					$ratings_max = (int) get_option( 'postratings_max' );
					$ratings_custom = (int) get_option( 'postratings_customrating' );
					$ratings_value = get_option('postratings_ratingsvalue');
					$post_title = addslashes($post->post_title);
					$post_ratings = get_post_custom($post_id);
					$post_ratings_users = ! empty( $post_ratings['ratings_users'] ) ? (int) $post_ratings['ratings_users'][0] : 0;
					$post_ratings_score = ! empty( $post_ratings['ratings_score'] ) ? (int) $post_ratings['ratings_score'][0] : 0;
					// Check For Ratings Lesser Than 1 And Greater Than $ratings_max
					if($rate < 1 || $rate > $ratings_max) {
						$rate = 0;
					}
					++$post_ratings_users;
					$post_ratings_score += (int) $ratings_value[ $rate - 1 ];
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
					$rate_userid = apply_filters( 'wp_postratings_process_ratings_userid', $user_ID );

					// Only Create Cookie If User Choose Logging Method 1 Or 3
					$postratings_logging_method = (int) get_option( 'postratings_logging_method' );
					if ( $postratings_logging_method === 1 || $postratings_logging_method === 3 ) {
						setcookie( 'rated_' . $post_id, $ratings_value[ $rate - 1 ], apply_filters( 'wp_postratings_cookie_expiration', time() + 30000000 ), apply_filters( 'wp_postratings_cookiepath', SITECOOKIEPATH ) );
					}
					// Log Ratings In DB If User Choose Logging Method 2, 3 or 4
					if ( $postratings_logging_method > 1 ) {
						$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->ratings} VALUES (%d, %d, %s, %d, %d, %s, %s, %s, %d )", 0, $post_id, $post_title, $ratings_value[$rate - 1], current_time('timestamp'), ratings_get_ipaddress(), ratings_get_hostname(), $rate_user, $rate_userid));
					}
					// Allow Other Plugins To Hook When A Post Is Rated
					do_action('rate_post', $rate_userid, $post_id, $ratings_value[$rate-1]);
					// Output AJAX Result
					echo the_ratings_results($post_id, $post_ratings_users, $post_ratings_score, $post_ratings_average);
					exit();
				} else {
					printf(esc_html__('Invalid Post ID (#%s).', 'wp-postratings'), $post_id);
					exit();
				} // End if($post)
			} else {
				printf(esc_html__('You Had Already Rated This Post. Post ID #%s.', 'wp-postratings'), $post_id);
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
	if(isset($_GET['action']) && $_GET['action'] === 'postratings-admin')
	{
		check_ajax_referer('wp-postratings_option_update_individual_rating');

		//Variables
		$postratings_url = plugins_url('wp-postratings/images');
		$postratings_path = WP_PLUGIN_DIR.'/wp-postratings/images';
		$postratings_ratingstext = get_option('postratings_ratingstext');
		$postratings_ratingsvalue = get_option('postratings_ratingsvalue');

		// Form Processing
		$postratings_customrating = isset( $_GET['custom'] ) ? (int) $_GET['custom'] : 0;
		$postratings_image = isset( $_GET['image'] ) ? esc_attr( trim( $_GET['image'] ) ) : '';
		$postratings_max = isset( $_GET['max'] ) ? (int) $_GET['max'] : 0;

		// If It Is A Up/Down Rating
		if($postratings_customrating && $postratings_max === 2) {
			$postratings_ratingsvalue[0] = -1;
			$postratings_ratingsvalue[1] = 1;
			$postratings_ratingstext[0] = __('Vote Down', 'wp-postratings');
			$postratings_ratingstext[1] = __('Vote Up', 'wp-postratings');
		} else {
			for($i = 0; $i < $postratings_max; $i++) {
				if($i > 0) {
					$postratings_ratingstext[$i] = sprintf(esc_html__('%s Stars', 'wp-postratings'), number_format_i18n($i+1));
				} else {
					$postratings_ratingstext[$i] = sprintf(esc_html__('%s Star', 'wp-postratings'), number_format_i18n($i+1));
				}
				$postratings_ratingsvalue[$i] = $i+1;
			}
		}
?>
		<table class="form-table">
			<thead>
				<tr>
					<th><?php esc_html_e('Rating Image', 'wp-postratings'); ?></th>
					<th><?php esc_html_e('Rating Text', 'wp-postratings'); ?></th>
					<th><?php esc_html_e('Rating Value', 'wp-postratings'); ?></th>
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
	$content .= " LEFT JOIN $wpdb->postmeta ON $wpdb->postmeta.post_id = $wpdb->posts.ID AND $wpdb->postmeta.meta_key = 'ratings_users'";
	return $content;
}
function ratings_most_orderby( $orderby ) {
	$r_orderby = trim( addslashes( get_query_var( 'r_orderby' ) )) ;
	if( empty( $r_orderby ) || ( $r_orderby !== 'asc' && $r_orderby !== 'desc' ) ) {
		$r_orderby = 'desc';
	}

	$rating_orderby = ' ratings_votes ' . $r_orderby;
	if ( ! empty ( $orderby ) ) {
		$rating_orderby .= ', ' . $orderby;
	}

	return $rating_orderby;
}


### Function: Modify Default WordPress Listing To Make It Sorted By Highest Rated
function ratings_highest_fields( $content ) {
	$content .= ', (t1.meta_value+0.00) AS ratings_average, (t2.meta_value+0.00) AS ratings_users';
	return $content;
}
function ratings_highest_join( $content ) {
	global $wpdb;
	$ratings_max = (int) get_option( 'postratings_max' );
	$ratings_custom = (int) get_option( 'postratings_customrating' );

	$content .= " LEFT JOIN $wpdb->postmeta AS t1 ON t1.post_id = $wpdb->posts.ID";
	if($ratings_custom && $ratings_max === 2) {
		$content .= " AND t1.meta_key = 'ratings_score'";
	} else {
		$content .= " AND t1.meta_key = 'ratings_average'";
	}
	$content .= " LEFT JOIN $wpdb->postmeta As t2 ON t1.post_id = t2.post_id AND t2.meta_key = 'ratings_users'";
	return $content;
}
function ratings_highest_orderby( $orderby ) {
	$r_orderby = trim( addslashes( get_query_var( 'r_orderby' ) ) );
	if ( empty( $r_orderby ) || ( $r_orderby !== 'asc' && $r_orderby !== 'desc' ) ) {
		$r_orderby = 'desc';
	}

	$rating_orderby = ' ratings_average ' . $r_orderby . ', ratings_users ' . $r_orderby;
	if ( ! empty ( $orderby ) ) {
		$rating_orderby .= ', ' . $orderby;
	}

	return $rating_orderby;
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
		add_filter('posts_orderby', 'ratings_most_orderby');
		remove_filter('posts_fields', 'ratings_highest_fields');
		remove_filter('posts_join', 'ratings_highest_join');
		remove_filter('posts_orderby', 'ratings_highest_orderby');
	} elseif($local_wp_query->get('r_sortby') == 'highest_rated') {
		add_filter('posts_fields', 'ratings_highest_fields');
		add_filter('posts_join', 'ratings_highest_join');
		add_filter('posts_orderby', 'ratings_highest_orderby');
		remove_filter('posts_fields', 'ratings_most_fields');
		remove_filter('posts_join', 'ratings_most_join');
		remove_filter('posts_orderby', 'ratings_most_orderby');
	} else {
		remove_filter('posts_fields', 'ratings_highest_fields');
		remove_filter('posts_join', 'ratings_highest_join');
		remove_filter('posts_orderby', 'ratings_highest_orderby');
		remove_filter('posts_fields', 'ratings_most_fields');
		remove_filter('posts_join', 'ratings_most_join');
		remove_filter('posts_orderby', 'ratings_most_orderby');
	}
}


add_action( 'pre_get_posts', 'sort_postratings' );
function sort_postratings( $query ) {
	if ( ! is_admin() ) {
		return;
	}
	$orderby = $query->get( 'orderby' );
	if ( 'ratings' === $orderby ) {
		$query->set( 'meta_key', 'ratings_average' );
		$query->set( 'orderby', 'meta_value_num' );
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
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_ratings" value="ratings" checked="checked" />&nbsp;&nbsp;<label for="wpstats_ratings">'.esc_html__('WP-PostRatings', 'wp-postratings').'</label><br />'."\n";
	} else {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_ratings" value="ratings" />&nbsp;&nbsp;<label for="wpstats_ratings">'.esc_html__('WP-PostRatings', 'wp-postratings').'</label><br />'."\n";
	}
	return $content;
}


### Function: Add WP-PostRatings Top Most/Highest Stats To WP-Stats Page Options
function postratings_page_admin_most_stats($content) {
	$stats_display = get_option('stats_display');
	$stats_mostlimit = (int) get_option( 'stats_mostlimit' );
	if($stats_display['rated_highest_post'] === 1) {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_rated_highest_post" value="rated_highest_post" checked="checked" />&nbsp;&nbsp;<label for="wpstats_rated_highest_post">'.esc_html(sprintf(_n('%s Highest Rated Post', '%s Highest Rated Posts', $stats_mostlimit, 'wp-postratings'), number_format_i18n($stats_mostlimit))).'</label><br />'."\n";
	} else {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_rated_highest_post" value="rated_highest_post" />&nbsp;&nbsp;<label for="wpstats_rated_highest_post">'.esc_html(sprintf(_n('%s Highest Rated Post', '%s Highest Rated Posts', $stats_mostlimit, 'wp-postratings'), number_format_i18n($stats_mostlimit))).'</label><br />'."\n";
	}
	if($stats_display['rated_highest_page'] === 1) {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_rated_highest_page" value="rated_highest_page" checked="checked" />&nbsp;&nbsp;<label for="wpstats_rated_highest_page">'.esc_html(sprintf(_n('%s Highest Rated Page', '%s Highest Rated Pages', $stats_mostlimit, 'wp-postratings'), number_format_i18n($stats_mostlimit))).'</label><br />'."\n";
	} else {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_rated_highest_page" value="rated_highest_page" />&nbsp;&nbsp;<label for="wpstats_rated_highest_page">'.esc_html(sprintf(_n('%s Highest Rated Page', '%s Highest Rated Pages', $stats_mostlimit, 'wp-postratings'), number_format_i18n($stats_mostlimit))).'</label><br />'."\n";
	}
	if($stats_display['rated_most_post'] === 1) {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_rated_most_post" value="rated_most_post" checked="checked" />&nbsp;&nbsp;<label for="wpstats_rated_most_post">'.esc_html(sprintf(_n('%s Most Rated Post', '%s Most Rated Posts', $stats_mostlimit, 'wp-postratings'), number_format_i18n($stats_mostlimit))).'</label><br />'."\n";
	} else {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_rated_most_post" value="rated_most_post" />&nbsp;&nbsp;<label for="wpstats_rated_most_post">'.esc_html(sprintf(_n('%s Most Rated Post', '%s Most Rated Posts', $stats_mostlimit, 'wp-postratings'), number_format_i18n($stats_mostlimit))).'</label><br />'."\n";
	}
	if($stats_display['rated_most_page'] === 1) {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_rated_most_page" value="rated_most_page" checked="checked" />&nbsp;&nbsp;<label for="wpstats_rated_most_page">'.esc_html(sprintf(_n('%s Most Rated Page', '%s Most Rated Pages', $stats_mostlimit, 'wp-postratings'), number_format_i18n($stats_mostlimit))).'</label><br />'."\n";
	} else {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_rated_most_page" value="rated_most_page" />&nbsp;&nbsp;<label for="wpstats_rated_most_page">'.esc_html(sprintf(_n('%s Most Rated Page', '%s Most Rated Pages', $stats_mostlimit, 'wp-postratings'), number_format_i18n($stats_mostlimit))).'</label><br />'."\n";
	}
	return $content;
}


### Function: Add WP-PostRatings General Stats To WP-Stats Page
function postratings_page_general_stats($content) {
	$stats_display = get_option('stats_display');
	if($stats_display['ratings'] === 1) {
		$content .= '<p><strong>'.esc_html__('WP-PostRatings', 'wp-postratings').'</strong></p>'."\n";
		$content .= '<ul>'."\n";
		$content .= '<li>'.esc_html(sprintf(_n('%s user casted his vote.', '%s users casted their vote.', get_ratings_users(false), 'wp-postratings'), '<strong>'.number_format_i18n(get_ratings_users(false)).'</strong>')).'</li>'."\n";
		$content .= '</ul>'."\n";
	}
	return $content;
}


### Function: Add WP-PostRatings Top Most/Highest Stats To WP-Stats Page
function postratings_page_most_stats($content) {
	$stats_display = get_option('stats_display');
	$stats_mostlimit = (int) get_option( 'stats_mostlimit' );
	if ( $stats_display['rated_highest_post'] === 1 ) {
		$content .= '<p><strong>'.sprintf(_n('%s Highest Rated Post', '%s Highest Rated Posts', $stats_mostlimit, 'wp-postratings'), number_format_i18n($stats_mostlimit)).'</strong></p>'."\n";
		$content .= '<ul>'."\n";
		$content .= get_highest_rated('post', 0, $stats_mostlimit, 0, false);
		$content .= '</ul>'."\n";
	}
	if ( $stats_display['rated_highest_page'] === 1 ) {
		$content .= '<p><strong>'.sprintf(_n('%s Highest Rated Page', '%s Highest Rated Pages', $stats_mostlimit, 'wp-postratings'), number_format_i18n($stats_mostlimit)).'</strong></p>'."\n";
		$content .= '<ul>'."\n";
		$content .= get_highest_rated('page', 0, $stats_mostlimit, 0, false);
		$content .= '</ul>'."\n";
	}
	if ( $stats_display['rated_most_post'] === 1 ) {
		$content .= '<p><strong>'.sprintf(_n('%s Most Rated Post', '%s Most Rated Posts', $stats_mostlimit, 'wp-postratings'), number_format_i18n($stats_mostlimit)).'</strong></p>'."\n";
		$content .= '<ul>'."\n";
		$content .= get_most_rated('post', 0, $stats_mostlimit, 0, false);
		$content .= '</ul>'."\n";
	}
	if ( $stats_display['rated_most_page'] === 1 ) {
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
	$image_alt = esc_attr( $image_alt );
	$ratings_image = esc_attr( $ratings_image );
	$image_alt = apply_filters( 'wp_postratings_ratings_image_alt', $image_alt );
	if(is_rtl() && file_exists(WP_PLUGIN_DIR.'/wp-postratings/images/'.$ratings_image.'/rating_start-rtl.'.RATINGS_IMG_EXT)) {
		$ratings_images .= '<img src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_start-rtl.'.RATINGS_IMG_EXT).'" alt="" class="post-ratings-image" />';
	} elseif(file_exists(WP_PLUGIN_DIR.'/wp-postratings/images/'.$ratings_image.'/rating_start.'.RATINGS_IMG_EXT)) {
		$ratings_images .= '<img src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_start.'.RATINGS_IMG_EXT).'" alt="" class="post-ratings-image" />';
	}
	if($ratings_custom) {
		for($i=1; $i <= $ratings_max; $i++) {
			if($i <= $post_rating) {
				$ratings_images .= '<img src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_'.$i.'_on.'.RATINGS_IMG_EXT).'" alt="'.$image_alt.'" title="'.$image_alt.'" class="post-ratings-image" />';
			} elseif($i === $insert_half) {
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
			} elseif($i === $insert_half) {
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
	$ratings_image = esc_attr( $ratings_image );
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
			$ratings_text = isset ( $ratings_texts[ $i - 1 ] ) ? esc_attr( stripslashes( $ratings_texts[ $i - 1] ) ) : '';
			$ratings_text_js = esc_js( $ratings_text );
			$image_alt = apply_filters( 'wp_postratings_ratings_image_alt', $ratings_text );
			if($i <= $post_rating) {
				$ratings_images .= '<img id="rating_'.$post_id.'_'.$i.'" src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_'.$i.'_on.'.RATINGS_IMG_EXT).'" alt="'.$image_alt.'" title="'.$image_alt.'" onmouseover="current_rating('.$post_id.', '.$i.', \''.$ratings_text_js.'\');" onmouseout="ratings_off('.$post_rating.', '.$insert_half.', '.$use_half_rtl.');" onclick="rate_post();" onkeypress="rate_post();" style="cursor: pointer; border: 0px;" />';
			} elseif($i == $insert_half) {
				if ($use_half_rtl) {
					$ratings_images .= '<img id="rating_'.$post_id.'_'.$i.'" src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_'.$i.'_half-rtl.'.RATINGS_IMG_EXT).'" alt="'.$image_alt.'" title="'.$image_alt.'" onmouseover="current_rating('.$post_id.', '.$i.', \''.$ratings_text_js.'\');" onmouseout="ratings_off('.$post_rating.', '.$insert_half.', '.$use_half_rtl.');" onclick="rate_post();" onkeypress="rate_post();" style="cursor: pointer; border: 0px;" />';
				} else {
					$ratings_images .= '<img id="rating_'.$post_id.'_'.$i.'" src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_'.$i.'_half.'.RATINGS_IMG_EXT).'" alt="'.$image_alt.'" title="'.$image_alt.'" onmouseover="current_rating('.$post_id.', '.$i.', \''.$ratings_text_js.'\');" onmouseout="ratings_off('.$post_rating.', '.$insert_half.', '.$use_half_rtl.');" onclick="rate_post();" onkeypress="rate_post();" style="cursor: pointer; border: 0px;" />';
				}
			} else {
				$ratings_images .= '<img id="rating_'.$post_id.'_'.$i.'" src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_'.$i.'_off.'.RATINGS_IMG_EXT).'" alt="'.$image_alt.'" title="'.$image_alt.'" onmouseover="current_rating('.$post_id.', '.$i.', \''.$ratings_text_js.'\');" onmouseout="ratings_off('.$post_rating.', '.$insert_half.', '.$use_half_rtl.');" onclick="rate_post();" onkeypress="rate_post();" style="cursor: pointer; border: 0px;" />';
			}
		}
	} else {
		if (is_rtl() && file_exists(WP_PLUGIN_DIR.'/wp-postratings/images/'.$ratings_image.'/rating_half-rtl.'.RATINGS_IMG_EXT)) {
			$use_half_rtl = 1;
		} else {
			$use_half_rtl = 0;
		}
		for($i=1; $i <= $ratings_max; $i++) {
			$ratings_text = isset( $ratings_texts[ $i - 1 ] ) ? esc_attr( stripslashes( $ratings_texts[ $i - 1 ] ) ) : '';
			$ratings_text_js = esc_js( $ratings_text );
			$image_alt = apply_filters( 'wp_postratings_ratings_image_alt', $ratings_text );
			if($i <= $post_rating) {
				$ratings_images .= '<img id="rating_'.$post_id.'_'.$i.'" src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_on.'.RATINGS_IMG_EXT).'" alt="'.$image_alt.'" title="'.$image_alt.'" onmouseover="current_rating('.$post_id.', '.$i.', \''.$ratings_text_js.'\');" onmouseout="ratings_off('.$post_rating.', '.$insert_half.', '.$use_half_rtl.');" onclick="rate_post();" onkeypress="rate_post();" style="cursor: pointer; border: 0px;" />';
			} elseif($i == $insert_half) {
				if ($use_half_rtl) {
					$ratings_images .= '<img id="rating_'.$post_id.'_'.$i.'" src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_half-rtl.'.RATINGS_IMG_EXT).'" alt="'.$image_alt.'" title="'.$image_alt.'" onmouseover="current_rating('.$post_id.', '.$i.', \''.$ratings_text_js.'\');" onmouseout="ratings_off('.$post_rating.', '.$insert_half.', '.$use_half_rtl.');" onclick="rate_post();" onkeypress="rate_post();" style="cursor: pointer; border: 0px;" />';
				} else {
					$ratings_images .= '<img id="rating_'.$post_id.'_'.$i.'" src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_half.'.RATINGS_IMG_EXT).'" alt="'.$image_alt.'" title="'.$image_alt.'" onmouseover="current_rating('.$post_id.', '.$i.', \''.$ratings_text_js.'\');" onmouseout="ratings_off('.$post_rating.', '.$insert_half.', '.$use_half_rtl.');" onclick="rate_post();" onkeypress="rate_post();" style="cursor: pointer; border: 0px;" />';
				}
			} else {
				$ratings_images .= '<img id="rating_'.$post_id.'_'.$i.'" src="'.plugins_url('/wp-postratings/images/'.$ratings_image.'/rating_off.'.RATINGS_IMG_EXT).'" alt="'.$image_alt.'" title="'.$image_alt.'" onmouseover="current_rating('.$post_id.', '.$i.', \''.$ratings_text_js.'\');" onmouseout="ratings_off('.$post_rating.', '.$insert_half.', '.$use_half_rtl.');" onclick="rate_post();" onkeypress="rate_post();" style="cursor: pointer; border: 0px;" />';
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
	$image_alt = esc_attr( $image_alt );
	$ratings_image = esc_attr( $ratings_image );
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
	$ratings_image = get_option( 'postratings_image' );
	$ratings_max = (int) get_option( 'postratings_max' );
	$ratings_custom = (int) get_option( 'postratings_customrating' );
	$ratings_options = get_option( 'postratings_options' );

	if ( is_object( $post_data ) ) {
		$post_id = (int) $post_data->ID;
	} else {
		$post_id = (int) $post_data;
	}

	// Most likely from coming from Widget
	if ( isset( $post_data->ratings_users ) ) {
		$post_ratings_users = (int) $post_data->ratings_users;
		$post_ratings_score = (int) $post_data->ratings_score;
		$post_ratings_average = (float) $post_data->ratings_average;
	// Most Likely coming from the_ratings_vote or the_ratings_rate
	} else if ( isset( $post_ratings_data->ratings_users ) ) {
		$post_ratings_users = (int) $post_ratings_data->ratings_users;
		$post_ratings_score = (int) $post_ratings_data->ratings_score;
		$post_ratings_average = (float) $post_ratings_data->ratings_average;
	} else {
		if ( get_the_ID() !== $post_id ) {
			$post_ratings_data = get_post_custom( $post_id );
		} else {
			$post_ratings_data = get_post_custom();
		}

		$post_ratings_users = is_array( $post_ratings_data ) && array_key_exists( 'ratings_users', $post_ratings_data ) ? (int) $post_ratings_data['ratings_users'][0] : 0;
		$post_ratings_score = is_array( $post_ratings_data ) && array_key_exists( 'ratings_score', $post_ratings_data ) ? (int) $post_ratings_data['ratings_score'][0] : 0;
		$post_ratings_average = is_array( $post_ratings_data ) && array_key_exists( 'ratings_average', $post_ratings_data ) ? (float) $post_ratings_data['ratings_average'][0] : 0;
	}

	if ( $post_ratings_score === 0 || $post_ratings_users === 0 ) {
		$post_ratings = 0;
		$post_ratings_average = 0;
		$post_ratings_percentage = 0;
	} else {
		$post_ratings = round( $post_ratings_average, 1 );
		$post_ratings_percentage = round( ( ( $post_ratings_score / $post_ratings_users ) / $ratings_max ) * 100, 2 );
	}
	$post_ratings_text = '<span class="post-ratings-text" id="ratings_' . $post_id . '_text"></span>';
	// Get the image's alt text
	if ( $ratings_custom && $ratings_max === 2 ) {
		if ( $post_ratings_score > 0 ) {
			$post_ratings_score = '+' . $post_ratings_score;
		}
		$post_ratings_alt_text = esc_html( sprintf( _n( '%s rating', '%s ratings', $post_ratings_score, 'wp-postratings' ), number_format_i18n( $post_ratings_score ) ) . __( ',', 'wp-postratings' ) . ' ' . sprintf( _n( '%s vote', '%s votes', $post_ratings_users, 'wp-postratings' ), number_format_i18n( $post_ratings_users ) ) );
	} else {
		$post_ratings_score = number_format_i18n( $post_ratings_score );
		$post_ratings_alt_text = esc_html( sprintf( _n( '%s vote', '%s votes', $post_ratings_users, 'wp-postratings' ), number_format_i18n( $post_ratings_users ) ) . __( ',', 'wp-postratings' ) . ' ' . __( 'average', 'wp-postratings' ) . ': ' . number_format_i18n( $post_ratings_average, 2 ) . ' ' . __( 'out of', 'wp-postratings' ) . ' ' . number_format_i18n( $ratings_max ) );
	}
	// Check for half star
	$insert_half = 0;
	$average_diff = abs( floor( $post_ratings_average ) - $post_ratings );
	if ( $average_diff >= 0.25 && $average_diff <= 0.75 ) {
		$insert_half = ceil( $post_ratings_average );
	} elseif ( $average_diff > 0.75 ) {
		$insert_half = ceil( $post_ratings );
	}
	$insert_half = (int) $insert_half;

	// Replace the variables
	$value = $template;
	if ( strpos( $template, '%RATINGS_IMAGES%') !== false ) {
		$get_ratings_images = get_ratings_images( $ratings_custom, $ratings_max, $post_ratings, $ratings_image, $post_ratings_alt_text, $insert_half );
		$post_ratings_images = apply_filters( 'wp_postratings_ratings_images', $get_ratings_images, $post_id, $post_ratings, $ratings_max );
		$value = str_replace( '%RATINGS_IMAGES%', $post_ratings_images, $value );
	}
	if ( strpos( $template, '%RATINGS_IMAGES_VOTE%' ) !== false ) {
		$ratings_texts = get_option( 'postratings_ratingstext' );
		$get_ratings_images_vote = get_ratings_images_vote( $post_id, $ratings_custom, $ratings_max, $post_ratings, $ratings_image, $post_ratings_alt_text, $insert_half, $ratings_texts );
		$post_ratings_images = apply_filters( 'wp_postratings_ratings_images_vote', $get_ratings_images_vote, $post_id, $post_ratings, $ratings_max );
		$value = str_replace( '%RATINGS_IMAGES_VOTE%', $post_ratings_images, $value );
	}

	$value = str_replace( array(
		'%RATINGS_ALT_TEXT%',
		'%RATINGS_TEXT%',
		'%RATINGS_MAX%',
		'%RATINGS_SCORE%',
		'%RATINGS_AVERAGE%',
		'%RATINGS_PERCENTAGE%',
		'%RATINGS_USERS%'
	), array(
		$post_ratings_alt_text,
		$post_ratings_text,
		number_format_i18n( $ratings_max ),
		$post_ratings_score,
		number_format_i18n( $post_ratings_average, 2 ),
		number_format_i18n( $post_ratings_percentage, 2 ),
		number_format_i18n( $post_ratings_users )
	), $value );

	// Post Template Variables
	$post_link = get_permalink( $post_data );
	$post_title = get_the_title( $post_data );
	if ( $max_post_title_chars > 0 ) {
		$post_title = snippet_text( $post_title, $max_post_title_chars );
	}
	$value = str_replace( array(
		'%POST_ID%',
		'%POST_TITLE%',
		'%POST_URL%'
	), array(
		$post_id,
		$post_title,
		$post_link
	), $value );

	if ( strpos( $template, '%POST_EXCERPT%') !== false ) {
		if ( get_the_ID() !== $post_id ) {
			$post = &get_post($post_id);
		}
		$post_excerpt = ratings_post_excerpt( $post_id, $post->post_excerpt, $post->post_content );
		$value = str_replace('%POST_EXCERPT%', $post_excerpt, $value );
	}
	if ( strpos( $template, '%POST_CONTENT%' ) !== false ) {
		if ( get_the_ID() !== $post_id ) {
			$post = &get_post( $post_id );
		}
		$value = str_replace('%POST_CONTENT%', get_the_content(), $value );
	}
	if ( strpos( $template, '%POST_THUMBNAIL%') !== false ) {
		if ( get_the_ID() !== $post_id ) {
			$post = &get_post( $post_id );
		}
		$value = str_replace( '%POST_THUMBNAIL%', get_the_post_thumbnail( $post, 'thumbnail' ), $value );
	}

	// Google Rich Snippet
	$google_structured_data = '';
	$ratings_options['richsnippet'] = isset( $ratings_options['richsnippet'] ) ? $ratings_options['richsnippet'] : 1;
	if ( $ratings_options['richsnippet'] && is_singular() && $is_main_loop ) {
		$itemtype = apply_filters( 'wp_postratings_schema_itemtype', 'itemscope itemtype="http://schema.org/Article"' );

		if ( empty( $post_excerpt ) ) {
			$post_excerpt = ratings_post_excerpt( $post_id, $post->post_excerpt, $post->post_content );
		}
		$post_meta = '<meta itemprop="headline" content="' . esc_attr( $post_title ) . '" />';
		$post_meta .= '<meta itemprop="description" content="' . wp_kses( $post_excerpt, array() ) . '" />';
		$post_meta .= '<meta itemprop="datePublished" content="' . mysql2date( 'c', $post->post_date, false ) . '" />';
		$post_meta .= '<meta itemprop="dateModified" content="' . mysql2date( 'c', $post->post_modified, false ) . '" />';
		$post_meta .= '<meta itemprop="url" content="' . $post_link . '" />';
		$post_meta .= '<meta itemprop="author" content="' . get_the_author() . '" />';
		$post_meta .= '<meta itemprop="mainEntityOfPage" content="' . get_permalink() . '" />';
		// Post Thumbnail
		$thumbnail = '';
		if ( has_post_thumbnail() ) {
			$thumbnail = wp_get_attachment_image_src( get_post_thumbnail_id( null ) );
		}
		$thumbnail = apply_filters( 'wp_postratings_post_thumbnail', $thumbnail, $post_id );
		if ( ! empty( $thumbnail ) ) {
			$post_meta .= '<div style="display: none;" itemprop="image" itemscope itemtype="https://schema.org/ImageObject">';
			$post_meta .= '<meta itemprop="url" content="' . $thumbnail[0] . '" />';
			$post_meta .= '<meta itemprop="width" content="' . $thumbnail[1] . '" />';
			$post_meta .= '<meta itemprop="height" content="' . $thumbnail[2] . '" />';
			$post_meta .= '</div>';
		}

		// Publisher
		$site_logo = '';
		if ( function_exists( 'the_custom_logo' ) ) {
			$custom_logo_id = get_theme_mod( 'custom_logo' );
			if ( $custom_logo_id ) {
				$custom_logo = wp_get_attachment_image_src( $custom_logo_id, 'full' );
				$site_logo = $custom_logo[0];
			}
		}
		if ( empty( $site_logo ) && has_header_image() ) {
			$header_image = get_header_image();
			if( ! empty( $header_image ) ) {
				$site_logo = $header_image;
			}
		}
		$site_logo = apply_filters( 'wp_postratings_site_logo', $site_logo );
		$post_meta .= '<div style="display: none;" itemprop="publisher" itemscope itemtype="https://schema.org/Organization">';
		$post_meta .= '<meta itemprop="name" content="' . get_bloginfo( 'name' ) . '" />';
		$post_meta .= '<div itemprop="logo" itemscope itemtype="https://schema.org/ImageObject">';
		$post_meta .= '<meta itemprop="url" content="' . $site_logo . '" />';
		$post_meta .= '</div>';
		$post_meta .= '</div>';

		$ratings_meta = '';
		if ( $post_ratings_average > 0 ) {
			$ratings_meta .= '<div style="display: none;" itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating">';
			$ratings_meta .= '<meta itemprop="bestRating" content="' . $ratings_max . '" />';
			$ratings_meta .= '<meta itemprop="worstRating" content="1" />';
			$ratings_meta .= '<meta itemprop="ratingValue" content="' . $post_ratings_average . '" />';
			$ratings_meta .= '<meta itemprop="ratingCount" content="' . $post_ratings_users . '" />';
			$ratings_meta .= '</div>';
		}

		$google_structured_data =  apply_filters( 'wp_postratings_google_structured_data', ( empty( $itemtype ) ? $ratings_meta : ( $post_meta . $ratings_meta ) ) );
	}

	return apply_filters( 'expand_ratings_template', ( $value . $google_structured_data ) );
}
