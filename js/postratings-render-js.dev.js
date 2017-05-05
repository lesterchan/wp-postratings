/*
  Copyright (C) 2017, Raphaël . Droz + floss @ gmail DOT com

  This program is free software: you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  Basic implementation of the wp-postrating rendering mechanism but in Javascript.
  Use: in case raw data have to be transfered (eg: in JSON) without the overhead (and inflexibility)
       of transmiting the whole prederender HTML.
       In such cases expand_ratings_template can be used.
*/

/* 
   @param ratings_users an object of the form
   { 'usr': nb-users  // (int) usually the value of ratings_users
     'score': score   // (int) usually the value of ratings_score
     'avg': average   // (float) usually the value of ratings_average }
   @param max_rate the value corresponding to server-side option "postratings_max"
   @param images_dir the base URL stars images a fetched from, ex: /wp-content/plugins/wp-postratings/images/

   @return the HTML markup

   @use the global variable wp_postratings.post_rating_images_dir as a fallback for post_rating_images_dir
        Could be initialized by WP with wp_localize_script( 'wp-postratings', 'wp_postratings', ['images_dir' => FOO_PLUGIN_URL...] );
*/
function expand_ratings_template(rating, max_rate, images_dir) {
    var img_dir = images_dir || wp_postratings.images_dir;
    var img;
    var ratings_images = '';
    var vote_text = rating.usr > 1 ? 'votes' : 'vote';
    var alt_text = rating.usr + ' ' + vote_text + ', average: ' + rating.avg;
    for (var i=1; i<= max_rate; i++) {
	if (i <= Math.round(rating.avg, 1)) {
	    img = "on";
	} else if(i == _get_voting_half_star(rating.avg)) {
	    img = "half";
	} else {
	    img = "off";
	}
	ratings_images += '<img src="' + img_dir + '/stars/rating_' + img + '.gif" alt="' + alt_text + '" title="' + alt_text + '" class="post-ratings-image" />';
    }

    return ratings_images + ' (<strong>' + rating.usr + '</strong> ' + vote_text + ')';
}

// helper for expand_ratings_template()
function _get_voting_half_star(avg) {
    var post_ratings = Math.round(avg, 1);
    var post_ratings_average = Math.abs(Math.floor(avg));
    var average_diff = post_ratings_average - post_ratings;
    var insert_half = 0;
    if (average_diff >= 0.25 && average_diff <= 0.75) {
	insert_half = Math.ceil(post_ratings_average);
    }
    else if (average_diff > 0.75) {
	insert_half = Math.ceil(post_ratings);
    }
    return insert_half;
}
