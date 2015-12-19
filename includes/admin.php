<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;


### Function: Ratings Administration Menu
add_action('admin_menu', 'ratings_menu');
function ratings_menu() {
    add_menu_page(__('Ratings', 'wp-postratings'), __('Ratings', 'wp-postratings'), 'manage_ratings', 'wp-postratings/postratings-manager.php', '', 'dashicons-star-filled');

    add_submenu_page('wp-postratings/postratings-manager.php', __('Manage Ratings', 'wp-postratings'), __('Manage Ratings', 'wp-postratings'), 'manage_ratings', 'wp-postratings/postratings-manager.php');
    add_submenu_page('wp-postratings/postratings-manager.php', __('Ratings Options', 'wp-postratings'), __('Ratings Options', 'wp-postratings'),  'manage_ratings', 'wp-postratings/postratings-options.php');
    add_submenu_page('wp-postratings/postratings-manager.php', __('Ratings Templates', 'wp-postratings'), __('Ratings Templates', 'wp-postratings'),  'manage_ratings', 'wp-postratings/postratings-templates.php');
}


### Function: Show Rating Column in WP-Admin
add_filter('manage_posts_columns', 'add_postratings_column');
add_filter('manage_pages_columns', 'add_postratings_column');
function add_postratings_column($defaults) {
    $defaults['ratings'] = 'Ratings';
    return $defaults;
}


### Function: Fill In The Rating Column in WP-Admin
add_action('manage_posts_custom_column', 'add_postratings_column_content');
add_action('manage_pages_custom_column', 'add_postratings_column_content');
function add_postratings_column_content($column_name) {
    global $post;
    if($column_name == 'ratings') {
        if(function_exists('the_ratings')) {
            $template = str_replace('%RATINGS_IMAGES_VOTE%', '%RATINGS_IMAGES%<br />', stripslashes(get_option('postratings_template_vote')));
            echo expand_ratings_template($template, $post, null, 0, false);
        }
    }
}


### Function: Sort Rating Column in WP-Admin
add_filter('manage_edit-post_sortable_columns', 'sort_postratings_column');
add_filter('manage_edit-page_sortable_columns', 'sort_postratings_column');
function sort_postratings_column($defaults) {
    $defaults['ratings'] = 'ratings';
    return $defaults;
}
