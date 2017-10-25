/*
 +----------------------------------------------------------------+
 |                                                                |
 | WordPress Plugin: WP-PostRatings                               |
 | Copyright (c) 2012 Lester "GaMerZ" Chan                        |
 | Copyright (c) 2017 Raphaël Droz <raphael.droz@gmail.com>       |
 |                                                                |
 | File Information:                                              |
 | - Post Ratings Javascript File                                 |
 | - wp-content/plugins/wp-postratings/postratings-js.php         |
 |                                                                |
 +----------------------------------------------------------------+
 */


// Variables
var $j = jQuery.noConflict();
var is_being_rated = false;
var postratings_captcha = null;
var ratings_mouseover_image;

jQuery(function($) {
	ratingsL10n.custom = parseInt(ratingsL10n.custom);
	ratingsL10n.max = parseInt(ratingsL10n.max);
	ratingsL10n.show_loading = parseInt(ratingsL10n.show_loading);
	ratingsL10n.show_fading = parseInt(ratingsL10n.show_fading);
	ratingsL10n.baseimg = ratingsL10n.plugin_url + '/images/' + ratingsL10n.image + '/rating' ;
	ratingsL10n.rtl = parseInt(ratingsL10n.rtl);

	if(ratingsL10n.custom) {
		ratings_mouseover_image = [];
		for(var i = 1; i <= ratingsL10n.max; i++) {
			ratings_mouseover_image[i] = new Image();
			ratings_mouseover_image[i].src = ratingsL10n.baseimg + "_" + i + "_over." + ratingsL10n.image_ext ;
		}
	} else {
		ratings_mouseover_image = new Image();
		ratings_mouseover_image.src = ratingsL10n.baseimg + "_over."  + ratingsL10n.image_ext ;
	}

	$('img[data-votes]')
		.on('mouseover mouseout', current_rating)
		.on('click keypress',     rate_post);

});

// intermediary functions: wrap RTL complexity (invert on/off)
function getRtlI(i) { return (!ratingsL10n.rtl) ? i : (ratingsL10n.max - i + 1); }
function getOver(i) { return ratingsL10n.custom ? ratings_mouseover_image[ getRtlI(i) ].src : ratings_mouseover_image.src; }
function getOn(i)   { return ratingsL10n.baseimg + (ratingsL10n.custom ? '_' + getRtlI(i) : '') + '_' + getRtlDir('on') + '.' + ratingsL10n.image_ext; }
function getOff(i)  { return ratingsL10n.baseimg + (ratingsL10n.custom ? '_' + getRtlI(i) : '') + '_' + getRtlDir('off') + '.' + ratingsL10n.image_ext; }
function getHalf(i) { return ratingsL10n.baseimg + (ratingsL10n.custom ? '_' + getRtlI(i) : '') + '_' + getRtlDir('half') + '.' + ratingsL10n.image_ext; }

function getRtlDir(name) {
	if (!ratingsL10n.rtl) return name;
	switch(name) {
	case "on": return "off";
	case "off": return "on";
	case "half": return "half-rtl";
	default: return "";
	}
}

/* DOM function: let help knowing whether we are in Ajax mode or not
 * Ajax mode: submit async on click
 * Non-ajax mode: click stores in an hidden field (value can be changed with further click) and
   and it's not wp-postraings responsibility to post the value */
function is_using_ajax(post_id) {
	return Boolean( $j('#post-ratings-' + post_id).data('ajax') );
}

function non_ajax_hidden_parent(post_id) {
	var parent = $j('input[name="wp_postrating_form_value_' + post_id + '"]');
	if (parent.length == 1) return parent;
	return false;
}

// mouseover/out handler
function current_rating(event) {
	var post_ratings_el = $j(event.target);
	var post_id = $j(event.target).data('id');
	var current_rating = $j(event.target).data('currentRating');
	var rating_score = $j(event.target).data('votes');
	var insert_half = $j(event.target).data('half');

	if (is_being_rated) return;

	var curval = NaN; // possible stored value: disabled
	if (! is_using_ajax(post_id)) {
		var $parent = non_ajax_hidden_parent(post_id);
		curval = parseInt($parent.val());
	}

	/* This could be:
	 1) first set all OFF
	 2) then set to ON those that need
	 3) halve it if necessary
	 4) then highlight if it's a non-mouseover selection (non-ajax mode)
	    (currently stored value)... but prioritize mouseover => Math.min(mouseover,selected value)
	 5) then highlight up to the score of the current star (if mouseover)

	 Make all these passes filling up the array of images URL */

	var next_images = [];
	function setOn(i)   { next_images[i] = getOn(i); }
	function setOff(i)  { next_images[i] = getOff(i); } 
	function setHalf(i) { next_images[i] = getHalf(i); }
	function setOver(i) { next_images[i] = getOver(i); }
	var max = ratingsL10n.max;

	var i;
	// 1) off them all
	for(i = 1; i <= max; i++) setOff(i);
	// 2) on up to current score (always applies except for unvoted items)
	for(i = 1; i <= current_rating; i++) setOn(i);
	// 3) set the half-star (if it applies)
	if (insert_half > rating_score) setHalf(insert_half);
	// 4) on up to currently voted score (if non-ajax, non-default mode)
	if (! isNaN(curval)) {
		// ToDo: find another color
		if (event.type == "mouseover") for(i = 1; i <= Math.min(curval, rating_score); i++) setOver(i);
		else for(i = 1; i <= curval; i++) setOver(i);
	}
	// 5) mouseover
	if (event.type == "mouseover") for(i = 1; i <= rating_score; i++) setOver(i);

	// Now apply all these images.
	// NB: reversing the array, may be an even simpler way to do RTL
	for(i = 1; i <= max; i++) $j('#rating_' + post_id + '_' + i).attr('src', next_images[i]);

	updateText($j('#ratings_' + post_id + '_text'), post_ratings_el, event.type == "mouseout");
}

function updateText($text_el, $element, mouseout) {
	if ($text_el.length) {
		if (mouseout) $text_el.hide().empty();
		else $text_el.html($element.data('ratingsText')).show();
	}
}


// Process Post Ratings Success
function rate_post_success(post_id, data) {
	$j('#post-ratings-' + post_id).html(data);
	if(ratingsL10n.show_loading) {
		$j('#post-ratings-' + post_id + '-loading').hide();
	}
	if(ratingsL10n.show_fading) {
		$j('#post-ratings-' + post_id).fadeTo('def', 1);
	}
}

// Process Post Ratings
function rate_post(event) {
	var post_ratings_el = $j(event.target);
	var post_id = $j(event.target).data('id');
	var post_rating = $j(event.target).data('votes');

	var captcha_response = '';

	if (! is_using_ajax(post_id)) {
		var value_holder = non_ajax_hidden_parent(post_id);
		var curval = $j(value_holder).val();
		$j(value_holder).val(null);
		$j('#rating_' + post_id + '_' + curval).trigger('mouseout');
		if (curval != post_rating) {
			$j('#rating_' + post_id + '_' + post_rating).trigger('mouseover');
			$j(value_holder).val(post_rating);
		}
		return;
	}

	if(ratingsL10n.captcha_sitekey && ratingsL10n.captcha_sitekey.length) {
		if (postratings_captcha === null) {
			postratings_captcha = grecaptcha.render("g-recaptcha-response", {"sitekey":ratingsL10n.captcha_sitekey});
			return;
		} else {
			captcha_response = grecaptcha.getResponse(postratings_captcha);
			if (! grecaptcha.getResponse(postratings_captcha)) {
				// grecaptcha.reset(postratings_captcha);
				return;
			}
			else {
				// ok, let's submit
				$j('#g-recaptcha-response').remove();
			}
		}
	}

	if(! is_being_rated) {
		var post_ratings_nonce = $j(post_ratings_el).parent('.post-ratings').data('nonce');
		is_being_rated = true;
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
			 data: 'action=postratings&pid=' + post_id + '&rate=' + post_rating + '&postratings_' + post_id + '_nonce=' + post_ratings_nonce + '&g-recaptcha-response=' + captcha_response,
			 cache: false})
			.done(function(data) { rate_post_success(post_id, data); })
			.always(function() { is_being_rated = false; });
	}

	else {
		alert(ratingsL10n.text_wait);
	}
}
