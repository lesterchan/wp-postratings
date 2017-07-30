<?php

function weighted_score($ns) {
  $x = 0;
  $i = 0; // start at 0: 1 star does not count anything in the mean
  foreach ($ns as $v) {
    $x += $i * $v;
    // increase the weight of the next star
    $i++;
  }
  return $x / array_sum($ns);
}

/*
  User votes impact (aka "confidence level")

  95%: 11(1.960)/0.5-5.5 = 38 votes
  90%: 11(1.645)/0.5-5.5 = 31 votes
  80%: 11(1.282)/0.5-5.5 = 23 votes
  70%: 11(1.036)/0.5-5.5 = 17 votes
  60%: 11(0.842)/0.5-5.5 = 13 votes
  50%: 11(0.674)/0.5-5.5 =  9 votes
*/
define('NUM_VOTES_IMPACT_60', 0.842);
define('NUM_VOTES_IMPACT_80', 1.282);
define('NUM_VOTES_IMPACT_90', 1.645);
define('NUM_VOTES_IMPACT_95', 1.960);

// https://stackoverflow.com/a/40958702
// http://www.evanmiller.org/ranking-items-with-star-ratings.html
function bayesian_score($ns, $confidence) {
  if (empty(array_filter($ns))) return NULL;

  $ns = array_reverse($ns);
  $N = array_sum($ns);
  $K = count($ns);
  $s = range($K,1,-1);
  $s2 = array_map(function($e) {return pow($e, 2);}, $s);
  $z = $confidence;
  if (! function_exists('f')) {
    function f($s, $ns) {
      $N = array_sum($ns);
      $K = count($ns);
      $asum = [];
      foreach(array_combine($s, $ns) as $sk => $nk) {
        $asum[] = $sk * ($nk+1);
      }
      return array_sum($asum) / ($N+$K);
    }
      }

  $fsns = f($s, $ns);
  return $fsns - $z * sqrt( ( f($s2, $ns) - pow($fsns, 2)) / ($N+$K+1) );
}

function get_post_score_data($post_id) {
  global $wpdb;

  $def = [];
  $f = $wpdb->get_results( $wpdb->prepare( "SELECT rating_rating as r, count(1) as c FROM {$wpdb->ratings} WHERE rating_postid = %d GROUP BY rating_rating", $post_id ));
  foreach($f as $data) $def[$data->r] = (int)$data->c;
  $def += array_fill( 1, intval( get_option( 'postratings_max', 5 ) ), 0 );
  ksort( $def );

  return $def;
}

function get_bayesian_score($post_id, $confidence) {
  return bayesian_score( get_post_score_data( $post_id ), $confidence );
}


/* ToDo: how to produce a dynamically generated field (with no stored data at all?)
   If it's not possible, we may consider (optionnally) storing the alternative average
   inside the DB too */
/*
function get_postrating_bayesian_score($metadata, $object_id, $meta_key, $single) {
  if ($meta_key && $meta_key == 'bayesian_score') {
    return get_bayesian_score($object_id, floatval(constant('NUM_VOTES_IMPACT_' . intval(get_option('bayesian_votes_impact', 90)))) ? : NUM_VOTES_IMPACT_90);
  }
}
add_filter('get_post_metadata', 'get_postrating_bayesian_score', 10, 4);
*/
