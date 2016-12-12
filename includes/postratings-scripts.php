<?php
/**
 * WP-PostRatings Scripts.
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


### Function: Print Out jQuery Script At The Top
add_action('wp_head', 'ratings_javascripts_header');
function ratings_javascripts_header() {
    wp_print_scripts('jquery');
}


### Function: Enqueue Ratings JavaScripts/CSS
add_action('wp_enqueue_scripts', 'ratings_scripts');
function ratings_scripts() {
    if( @file_exists( get_stylesheet_directory() . '/postratings-css.css' ) ) {
        wp_enqueue_style( 'wp-postratings', get_stylesheet_directory_uri() . '/postratings-css.css', false, WP_POSTRATINGS_VERSION, 'all' );
    } elseif( @file_exists( get_stylesheet_directory() . '/css/postratings-css.css' ) ) {
        wp_enqueue_style( 'wp-postratings', get_stylesheet_directory_uri() . '/css/postratings-css.css', false, WP_POSTRATINGS_VERSION, 'all' );
    } else {
        wp_enqueue_style( 'wp-postratings', plugins_url( 'wp-postratings/css/postratings-css.css' ), false, WP_POSTRATINGS_VERSION, 'all' );
    }
    if( is_rtl() ) {
        if( @file_exists( get_stylesheet_directory() .'/postratings-css-rtl.css' ) ) {
            wp_enqueue_style( 'wp-postratings-rtl', get_stylesheet_directory_uri() . '/postratings-css-rtl.css', false, WP_POSTRATINGS_VERSION, 'all' );
        } elseif( @file_exists( get_stylesheet_directory() .'/css/postratings-css-rtl.css' ) ) {
            wp_enqueue_style( 'wp-postratings-rtl', get_stylesheet_directory_uri() . '/css/postratings-css-rtl.css', false, WP_POSTRATINGS_VERSION, 'all' );
        } else {
            wp_enqueue_style( 'wp-postratings-rtl', plugins_url( 'wp-postratings/css/postratings-css-rtl.css' ), false, WP_POSTRATINGS_VERSION, 'all' );
        }
    }
    $postratings_max = intval( get_option( 'postratings_max' ) );
    $postratings_custom = intval( get_option( 'postratings_customrating' ) );
    $postratings_ajax_style = get_option( 'postratings_ajax_style' );
    $postratings_image = get_option( 'postratings_image' );
    $postratings_plugins_url = plugins_url( 'wp-postratings' );
    $postratings_javascript = '';
    if($postratings_custom) {
        for($i = 1; $i <= $postratings_max; $i++) {
            $postratings_javascript .= 'var ratings_' . $i . '_mouseover_image=new Image();ratings_' . $i . '_mouseover_image.src="' . $postratings_plugins_url . '/images/' . $postratings_image . '/rating_' . $i . '_over.' . RATINGS_IMG_EXT . '";';
        }
    } else {
        $postratings_javascript = 'var ratings_mouseover_image=new Image();ratings_mouseover_image.src="' . $postratings_plugins_url . '/images/' . $postratings_image . '/rating_over.' . RATINGS_IMG_EXT . '";';
    }
    wp_enqueue_script('wp-postratings', plugins_url('wp-postratings/js/postratings-js.js'), array('jquery'), WP_POSTRATINGS_VERSION, true);
    wp_localize_script('wp-postratings', 'ratingsL10n', array(
        'plugin_url' => $postratings_plugins_url,
        'ajax_url' => admin_url('admin-ajax.php'),
        'text_wait' => __('Please rate only 1 item at a time.', 'wp-postratings'),
        'image' => $postratings_image,
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
        wp_enqueue_style('wp-postratings-admin', plugins_url('wp-postratings/css/postratings-admin-css.css'), false, WP_POSTRATINGS_VERSION, 'all');
        wp_enqueue_script('wp-postratings-admin', plugins_url('wp-postratings/js/postratings-admin-js.js'), array('jquery'), WP_POSTRATINGS_VERSION, true);
        wp_localize_script('wp-postratings-admin', 'ratingsAdminL10n', array(
            'admin_ajax_url' => admin_url('admin-ajax.php')
        ));
    }
}
