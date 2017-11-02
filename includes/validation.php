<?php

/*
  Copyright 2017 Raphaël Droz

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.
*/

function wpr_setup_validation() {
    $logmode = intval( get_option( 'postratings_logging_method' ) );

    add_filter('wp_postratings_can_rate', 'wpr_common_validator',         10, 3);
    add_filter('wp_postratings_can_rate', 'wpr_check_allowtorate',        15, 3);
    if ( $logmode == 1 || $logmode == 3 ) {
        add_filter('wp_postratings_can_rate', 'wpr_check_rated_cookie',   20, 3);
    }
    if ( $logmode == 2 || $logmode == 3 ) {
        add_filter('wp_postratings_can_rate', 'wpr_check_rated_ip',       21, 3);
    }
    if ( $logmode == 4 ) {
        add_filter('wp_postratings_can_rate', 'wpr_check_rated_username', 22, 3);
    }
    add_filter('wp_postratings_can_rate', 'wpr_recaptcha_validator',      25, 3);
}


function wpr_recaptcha_validator( $err, $post_id, $rate ) {
    if (recaptcha_is_enabled() && recaptcha_is_op() && ! is_human()) {
        $err[] = esc_html__('invalid captcha.', 'wp-postratings');
    }
    return $err;
}

function wpr_common_validator( $err, $post_id, $rate ) {
    if($rate <= 0) {
        $err[] = esc_html__('Invalid rate value.', 'wp-postratings');
    }

    if (! $post_id) {
        $err[] = sprintf(esc_html__('Invalid Post ID #%d.', 'wp-postratings'), $post_id);
    }

    // Check Whether Is There A Valid Post
    $post = get_post($post_id);
    if (! $post || wp_is_post_revision($post)) {
        $err[] = sprintf(esc_html__('Invalid post #%d.', 'wp-postratings'), $post_id);
    }
    return $err;
}

//Check Who Is Allow To Rate
function wpr_is_allowed_to_rate() {
    return count(wpr_check_allowtorate([])) == 0;
}

function wpr_check_allowtorate( $err ) {
    $allow_to_vote = intval(get_option('postratings_allowtorate'));
    $allowed = FALSE;
    switch($allow_to_vote) {
        // Guests Only
    case 0:
        $allowed = ! is_user_logged_in();
        // Logged-in users only
    case 1:
        $allowed = is_user_logged_in();
        // Users registered on blog (for multisite)
    case 3:
        $allowed = is_user_member_of_blog();
        // Registered Users And Guests
    case 2:
    default:
        $allowed = true;
    }

    if (! $allowed ) {
        $err[] = esc_html__('Voting forbidden, check policy.', 'wp-postratings');
    }
    return $err;
}


// helper for the following
function wpr_has_already_rated( $post_id ) {
    $logmode = intval( get_option( 'postratings_logging_method' ) );
    $err = [];
    if ( $logmode == 1 || $logmode == 3 ) {
        $err = wpr_check_rated_cookie( $err, $post_id );
    }
    if ( $logmode == 2 || $logmode == 3 ) {
        $err = wpr_check_rated_ip( $err, $post_id );
    }
    if ( $logmode == 4 ) {
        $err = wpr_check_rated_username( $err, $post_id );
    }
    return count($err) > 0;
}

// Check Rated By Cookie
function wpr_check_rated_cookie( $err, $post_id ) {
    $rated = isset($_COOKIE["rated_$post_id"]);
    if ( $rated ) {
        $err[] = sprintf(esc_html__('You already rated post #%d (cookie).', 'wp-postratings'), $post_id);
    }
    return $err;
}


// Check Rated By IP
function wpr_check_rated_ip( $err, $post_id ) {
    global $wpdb;
    // Check IP From IP Logging Database
    if ($wpdb->get_var( $wpdb->prepare( "SELECT rating_ip FROM {$wpdb->ratings} WHERE rating_postid = %d AND rating_ip = %s", $post_id, get_ipaddress() ) ) ) {
        $err[] = sprintf(esc_html__('You already rated post #%d (ip).', 'wp-postratings'), $post_id);
    }
    return $err;
}


// Check Rated By Username
function wpr_check_rated_username( $err, $post_id ) {
    global $wpdb, $user_ID;
    if( !is_user_logged_in() ) {
        $err[] = sprintf(esc_html__('You already rated post #%d (anonymous userid).', 'wp-postratings'), $post_id);
        return $err;
    }

    // Check User ID From IP Logging Database
    if ( $wpdb->get_var( $wpdb->prepare( "SELECT rating_userid FROM {$wpdb->ratings} WHERE rating_postid = %d AND rating_userid = %d", $post_id, $user_ID ) ) ) {
        $err[] = sprintf(esc_html__('You already rated post #%d (userid).', 'wp-postratings'), $post_id);
    }
    return $err;
}
