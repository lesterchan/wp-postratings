<?php

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

add_filter( 'wp_insert_comment', 'process_ratings_from_comment' );
function process_ratings_from_comment($comment_id) {
		$post_id = intval($_POST['comment_post_ID']);
    $rate = intval($_POST["wp_postrating_form_value_$post_id"]);
    if (! $post_id || ! $rate) {
        // ignored (could be simply a second comment while missing a second vote)
        return;
    }

    $allow_to_vote_with_comment = intval(get_option('postratings_onlyifcomment'));
    if (! $allow_to_vote_with_comment) {
        return;
    }

    $rate_id = 0; $last_error = '';
    process_ratings($post_id, $rate, $rate_id, $last_error);
    if ($rate_id) {
        add_comment_meta( $comment_id, 'postratings_id', $rate_id );
    }
    // if $last_error: ToDo
}

add_filter( 'comments_array', 'show_rating_in_comment' );
function show_rating_in_comment($comments) {
		if( ! count( $comments ) ) return;
    global $wpdb;

    foreach( $comments as $comment ) {
        $rate_id = get_comment_meta($comment->comment_ID, 'postratings_id', true);
        if (intval($rate_id)) {
            $rate = $wpdb->get_var( $wpdb->prepare( "SELECT rating_rating FROM {$wpdb->ratings} WHERE rating_id = %d", intval($rate_id)) );
            if ($rate) {
                $comment->comment_content .= '<p class="vote-value">'
                                          . esc_html(sprintf(__('Rated %d', 'wp-postratings'), $rate))
                                          . '</p>';
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

  return $options;
}


// REST API specific
// to be called from the "update_callback" of register_rest_field()
function process_ratings_from_rest_API( WP_Comment $comment, $rate ) {
    if (! $comment->comment_post_ID || ! $rate) {
        // ToDo
        return;
    }

    $allow_to_vote_with_comment = intval(get_option('postratings_onlyifcomment'));
    if (! $allow_to_vote_with_comment) {
        return;
    }

    $rate_id = 0; $last_error = '';
    process_ratings($comment->comment_post_ID, $rate, $rate_id, $last_error);
    if ($rate_id) {
        add_comment_meta( $comment_id, 'postratings_id', $rate_id );
        return true;
    }
    elseif ($last_error) {
        var_dump($last_error);
        return new WP_Error( 'rest_comment_vote_invalid', $last_error, array( 'status' => 403 ) );
    }
    return false;
}
