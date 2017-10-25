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
    var vote_text = rating.usr > 1 ? 'votes' : 'vote';
    var alt_text = rating.usr + ' ' + vote_text + ', average: ' + rating.avg;
    var tpl = `<img src="${img_dir}/stars/rating_%s.gif" alt="${alt_text}" title="${alt_text}" class="post-ratings-image" />\n`;
    // templating
    var ratings_images = '', i = 1;
    var has_half = Math.abs(rating.avg - Math.floor(rating.avg)).toFixed(3) >= 0.25;

    for (i=1; i<= Math.floor(rating.avg); i++) ratings_images += tpl.replace(/%s/g,"on");
    if (i < max_rate) ratings_images += tpl.replace(/%s/g, has_half ? 'half' : 'off');
    for (++i; i <= max_rate; i++) ratings_images += tpl.replace(/%s/g,"off");
    // return
    return ratings_images + ' (<strong>' + rating.usr + '</strong> ' + vote_text + ')';
}
