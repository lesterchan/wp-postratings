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
var $j = jQuery.noConflict();
var is_being_rated = false;
ratingsL10n.custom = parseInt(ratingsL10n.custom);
ratingsL10n.max = parseInt(ratingsL10n.max);
ratingsL10n.show_loading = parseInt(ratingsL10n.show_loading);
ratingsL10n.show_fading = parseInt(ratingsL10n.show_fading);


var ratings_mouseover_image;
if(ratingsL10n.custom) {
	ratings_mouseover_image = [];
        for(var i = 1; i <= ratingsL10n.max; i++) {
		ratings_mouseover_image[i] = new Image();
		ratings_mouseover_image[i].src = ratingsL10n.plugin_url + "/images/" + ratingsL10n.image + "/rating_" + i + "_over." + ratingsL10n.image_ext ;
        }
} else {
	ratings_mouseover_image = new Image();
	ratings_mouseover_image.src = ratingsL10n.plugin_url + "/images/" + ratingsL10n.image + "/rating_over."  + ratingsL10n.image_ext ;
}

// When User Mouse Over Ratings
function current_rating(post_id, post_rating, rating_text) {
	if(!is_being_rated) {
		if(ratingsL10n.custom && ratingsL10n.max == 2) {
			document.getElementById('rating_' + post_id + '_' + post_rating).src = ratings_mouseover_image[i].src;
		} else {
			for(var i = 1; i <= post_rating; i++) {
				console.log("#" + 'rating_' + post_id + '_' + i + ' = ' + ratings_mouseover_image.src);
				document.getElementById('rating_' + post_id + '_' + i).src = ratingsL10n.custom ? ratings_mouseover_image[i].src : ratings_mouseover_image.src;
			}
		}
		if(jQuery('#ratings_' + post_id + '_text').length) {
			jQuery('#ratings_' + post_id + '_text').html(rating_text).show();
		}
	}
}

// When User Mouse Out Ratings
function ratings_off(post_id, rating_score, insert_half, half_rtl) {
	if(!is_being_rated) {
		var baseimg = ratingsL10n.plugin_url + '/images/' + ratingsL10n.image + '/rating' ;
		for(var i = 1; i <= ratingsL10n.max; i++) {
			var element = document.getElementById('rating_' + post_id + '_' + i);
			if(i <= rating_score) {
				element.src = baseimg + (ratingsL10n.custom ? '_' + i : '') + '_on.' + ratingsL10n.image_ext;
			} else if(i == insert_half) {
				element.src = baseimg + (ratingsL10n.custom ? '_' + i : '') + '_half' + (half_rtl ? '-rtl' : '') + '.' + ratingsL10n.image_ext;
			} else {
				element.src = baseimg + (ratingsL10n.custom ? '_' + i : '') + '_off.' + ratingsL10n.image_ext;
			}
		}
		if(jQuery('#ratings_' + post_id + '_text').length) {
			jQuery('#ratings_' + post_id + '_text').hide().empty();
		}
	}
}

// Set is_being_rated Status
function set_is_being_rated(rated_status) {
	is_being_rated = rated_status;
}

// Process Post Ratings Success
function rate_post_success(post_id, data) {
	$j('#post-ratings-' + post_id).html(data);
	if(ratingsL10n.show_loading) {
		$j('#post-ratings-' + post_id + '-loading').hide();
	}
	if(ratingsL10n.show_fading) {
		$j('#post-ratings-' + post_id).fadeTo('def', 1, function () {
			set_is_being_rated(false);
		});
	} else {
		set_is_being_rated(false);
	}
}

// Process Post Ratings
function rate_post(post_id, post_rating) {
	var post_ratings_el = $j('#post-ratings-' + post_id);
	if(!is_being_rated) {
		var post_ratings_nonce = $j(post_ratings_el).data('nonce');
		if(typeof post_ratings_nonce == 'undefined' || post_ratings_nonce == null)
			post_ratings_nonce = $j(post_ratings_el).attr('data-nonce');
		set_is_being_rated(true);
		if(ratingsL10n.show_fading) {
			$j(post_ratings_el).fadeTo('def', 0, function () {
				if(ratingsL10n.show_loading) {
					$j('#post-ratings-' + post_id + '-loading').show();
				}
			});
		} else {
			if(ratingsL10n.show_loading) {
				$j('#post-ratings-' + post_id + '-loading').show();
			}
		}
		$j.post({xhrFields: {withCredentials: true},
			 dataType: 'html',
			 url: ratingsL10n.ajax_url,
			 data: 'action=postratings&pid=' + post_id + '&rate=' + post_rating + '&postratings_' + post_id + '_nonce=' + post_ratings_nonce,
			 cache: false})
			.done(function(data) { rate_post_success(post_id, data); });
	}

	else {
		alert(ratingsL10n.text_wait);
	}
}
