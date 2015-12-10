/*
+----------------------------------------------------------------+
|																							|
|	WordPress Plugin: WP-PostRatings								|
|	Copyright (c) 2012 Lester "GaMerZ" Chan									|
|																							|
|	File Written By:																	|
|	- Lester "GaMerZ" Chan															|
|	- http://lesterchan.net															|
|																							|
|	File Information:																	|
|	- Post Ratings Javascript File													|
|	- wp-content/plugins/wp-postratings/postratings-js.php				|
|																							|
+----------------------------------------------------------------+
*/


// Variables
var post_id = 0;
var post_rating = 0;
var is_being_rated = false;
ratingsL10n.custom = parseInt(ratingsL10n.custom);
ratingsL10n.max = parseInt(ratingsL10n.max);
ratingsL10n.show_loading = parseInt(ratingsL10n.show_loading);
ratingsL10n.show_fading = parseInt(ratingsL10n.show_fading);

// When User Mouse Over Ratings
function current_rating(id, rating, rating_text) {
	if(!is_being_rated) {
		post_id = id;
		post_rating = rating;
		if(ratingsL10n.custom && ratingsL10n.max == 2) {
			jQuery('#rating_' + post_id + '_' + rating).attr('src', eval('ratings_' + rating + '_mouseover_image.src'));
		} else {
			for(i = 1; i <= rating; i++) {
				if(ratingsL10n.custom) {
					jQuery('#rating_' + post_id + '_' + i).attr('src', eval('ratings_' + i + '_mouseover_image.src'));
				} else {
					jQuery('#rating_' + post_id + '_' + i).attr('src', ratings_mouseover_image.src);
				}
			}
		}
		if(jQuery('#ratings_' + post_id + '_text').length) {
			jQuery('#ratings_' + post_id + '_text').show();
			jQuery('#ratings_' + post_id + '_text').html(rating_text);
		}
	}
}


// When User Mouse Out Ratings
function ratings_off(rating_score, insert_half, half_rtl) {
	if(!is_being_rated) {
		for(i = 1; i <= ratingsL10n.max; i++) {
			if(i <= rating_score) {
				if(ratingsL10n.custom) {
					jQuery('#rating_' + post_id + '_' + i).attr('src', ratingsL10n.plugin_url + '/images/' + ratingsL10n.image + '/rating_' + i + '_on.' + ratingsL10n.image_ext);
				} else {
					jQuery('#rating_' + post_id + '_' + i).attr('src', ratingsL10n.plugin_url + '/images/' + ratingsL10n.image + '/rating_on.' + ratingsL10n.image_ext);
				}
			} else if(i == insert_half) {
				if(ratingsL10n.custom) {
					jQuery('#rating_' + post_id + '_' + i).attr('src',  ratingsL10n.plugin_url + '/images/' + ratingsL10n.image + '/rating_' + i + '_half' + (half_rtl ? '-rtl' : '') + '.' + ratingsL10n.image_ext);
				} else {
					jQuery('#rating_' + post_id + '_' + i).attr('src', ratingsL10n.plugin_url + '/images/' + ratingsL10n.image + '/rating_half' + (half_rtl ? '-rtl' : '') + '.' + ratingsL10n.image_ext);
				}
			} else {
				if(ratingsL10n.custom) {
					jQuery('#rating_' + post_id + '_' + i).attr('src', ratingsL10n.plugin_url + '/images/' + ratingsL10n.image + '/rating_' + i + '_off.' + ratingsL10n.image_ext);
				} else {
					jQuery('#rating_' + post_id + '_' + i).attr('src', ratingsL10n.plugin_url + '/images/' + ratingsL10n.image + '/rating_off.' + ratingsL10n.image_ext);
				}
			}
		}
		if(jQuery('#ratings_' + post_id + '_text').length) {
			jQuery('#ratings_' + post_id + '_text').hide();
			jQuery('#ratings_' + post_id + '_text').empty();
		}
	}
}

// Set is_being_rated Status
function set_is_being_rated(rated_status) {
	is_being_rated = rated_status;
}

// Process Post Ratings Success
function rate_post_success(data) {
	jQuery('#post-ratings-' + post_id).html(data);
	if(ratingsL10n.show_loading) {
		jQuery('#post-ratings-' + post_id + '-loading').hide();
	}
	if(ratingsL10n.show_fading) {
		jQuery('#post-ratings-' + post_id).fadeTo('def', 1, function () {
			set_is_being_rated(false);
		});
	} else {
		set_is_being_rated(false);
	}
}

// Process Post Ratings
function rate_post() {
	post_ratings_el = jQuery('#post-ratings-' + post_id);
	if(!is_being_rated) {
		post_ratings_nonce = jQuery(post_ratings_el).data('nonce');
		if(typeof post_ratings_nonce == 'undefined' || post_ratings_nonce == null)
			post_ratings_nonce = jQuery(post_ratings_el).attr('data-nonce');
		set_is_being_rated(true);
		if(ratingsL10n.show_fading) {
			jQuery(post_ratings_el).fadeTo('def', 0, function () {
				if(ratingsL10n.show_loading) {
					jQuery('#post-ratings-' + post_id + '-loading').show();
				}
				jQuery.ajax({type: 'POST', xhrFields: {withCredentials: true}, dataType: 'html', url: ratingsL10n.ajax_url, data: 'action=postratings&pid=' + post_id + '&rate=' + post_rating + '&postratings_' + post_id + '_nonce=' + post_ratings_nonce, cache: false, success: rate_post_success});
			});
		} else {
			if(ratingsL10n.show_loading) {
				jQuery('#post-ratings-' + post_id + '-loading').show();
			}
			jQuery.ajax({type: 'POST', xhrFields: {withCredentials: true}, dataType: 'html', url: ratingsL10n.ajax_url, data: 'action=postratings&pid=' + post_id + '&rate=' + post_rating + '&postratings_' + post_id + '_nonce=' + post_ratings_nonce, cache: false, success: rate_post_success});
		}
	} else {
		alert(ratingsL10n.text_wait);
	}
}