<?php

// wp eval "include('$PWD/includes/postratings-tests.php'); get_post_rating_detail();"
// output results comparing the above two rating functions
function postratings_score_tests($confidence = NUM_VOTES_IMPACT_90, $arr = null) {
  $tests = array(
    [0,0,0,20,0], // 20x 4 stars
    [0,0,20,0,0],
    [10,0,0,0,10],
    [0,0,0,10,0],
    [0,0,25,15,4],
    [0,0,10,0,0], // 10x 3 start
    [0,0,0,0,1], // 5 stars
    [0,0,30,0,0],
    [0,0,0,0,10],
  );

  printf("## Confidence = %.3f\n", $confidence);
  printf("% 7sstars% 8s \t| weighted \t bayesian \t diff\n", "", "");
  print(str_repeat("-", 61) . "\n");
  foreach($arr ? : $tests as $ns) {
    $w = weighted_score($ns);
    $b = bayesian_score($ns, $confidence);
    // diff, floored at quarter
    $d = floor(($b - $w) * 4)/4;

    printf("%s \t| %.3f \t %.3f \t %s%s\n",
           implode(',', array_map(function($e){return sprintf("% 3d",$e);}, $ns)),
           $w, $b, /*$fw,*/
           ($d > 0 ? "\t " : ($d < 0 ? "\t" : '')),
           $d != 0 ? sprintf("%.2f", $d) : "");
  }
}

function get_post_rating_detail($stats) {
  global $wpdb;

  $post_stats = [];
  foreach($wpdb->get_results("SELECT distinct rating_postid as p FROM {$wpdb->ratings}") as $i) {
    $post_stats[] = get_post_score_data($i->p);
  }

  postratings_score_tests(NUM_VOTES_IMPACT_90, $post_stats);
}
