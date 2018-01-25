<?php

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

add_filter( 'wp_insert_comment', 'process_ratings_from_comment' );
function process_ratings_from_comment($comment_id) {
    if ( !isset($_POST['comment_post_ID']) || !isset($_POST['wp_postrating_form_value_' . $post_id]) ) {
        return;
    }

    $post_id = (int)$_POST['comment_post_ID'];
    $rate = (int)$_POST['wp_postrating_form_value_' . $post_id];
    if (! $post_id || ! $rate) {
        // ignored (could be simply a second comment while missing a second vote)
        return;
    }

    $allow_to_vote_with_comment = (int)get_option('postratings_onlyifcomment');
    if (! $allow_to_vote_with_comment) {
        return;
    }

    $rate_id = 0; $last_error = '';
    process_ratings($post_id, $rate, $rate_id, $last_error);
    if ($rate_id) {
        update_comment_meta( $comment_id, 'postratings_id', $rate_id );
    }
    // if $last_error: ToDo
}

add_filter( 'comments_array', 'comments_load_rate' );
function comments_load_rate($comments) {
		if( ! count( $comments ) ) return;

    global $wpdb;
    foreach( $comments as $comment ) {
        $rate_id = get_comment_meta($comment->comment_ID, 'postratings_id', true);
        if (intval($rate_id)) {
            $rate = $wpdb->get_var( $wpdb->prepare( "SELECT rating_rating FROM {$wpdb->ratings} WHERE rating_id = %d", intval($rate_id)) );
            if ($rate) {
                $comment->postrating_rate = $rate;
            }
        }
    }

    return $comments;
}

add_filter( 'manage_edit-comments_columns', 'comment_has_vote' );
function comment_has_vote( $columns ) {
  $columns['comment-vote'] = __( 'Vote', 'wp-postratings' );
  return $columns;
}


add_filter( 'manage_comments_custom_column', 'recent_comment_has_vote', 20, 2 );
function recent_comment_has_vote( $column_name, $comment_id ) {
  if( 'comment-vote' != strtolower( $column_name ) ) return;
  if ( ( $rate_id = get_comment_meta( $comment_id, 'postratings_id', true ) ) ) {
    if (intval($rate_id)) {
      global $wpdb;
      $rate = $wpdb->get_var( $wpdb->prepare( "SELECT rating_rating FROM {$wpdb->ratings} WHERE rating_id = %d", intval($rate_id)) );
      if ($rate) {
        printf(__('Rated at %d', 'wp-postratings'), $rate);
      }
    }
  }
}


// REST API specific
// to be called from the "update_callback" of register_rest_field()
function process_ratings_from_rest_API( WP_Comment $comment, $rate ) {
    // see update_additional_fields_for_object()
    if (! $comment->comment_post_ID || ! $rate) {
        return new WP_Error( 'rest_comment_vote_invalid', 'Voted content not found.', array( 'status' => 500 ) );
    }

    $allow_to_vote_with_comment = (int)get_option('postratings_onlyifcomment');
    if (! $allow_to_vote_with_comment) {
        return new WP_Error( 'rest_comment_vote_invalid', 'Vote bound to comment are not allowed.', array( 'status' => 400 ) );
    }

    $rate_id = 0; $last_error = '';
    process_ratings($comment->comment_post_ID, $rate, $rate_id, $last_error);
    if ( $rate_id ) {
        $updated = update_comment_meta( $comment->comment_ID, 'postratings_id', $rate_id );
        return $updated;
    }

    if ( $last_error ) {
        return new WP_Error( 'rest_comment_vote_invalid', $last_error, array( 'status' => 403 ) );
    }
    return new WP_Error( 'rest_comment_vote_invalid', 'Unknown error.', array( 'status' => 500 ) );
}