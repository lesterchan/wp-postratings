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

jQuery(function($) {
	$('.post-ratings ol.vote li').click(function() {
		var $this = $(this)
			$postRatings = $this.closest('.post-ratings'),
			rating = $this.index() + 1,
			id = $postRatings.attr('id'),
			nonce = $postRatings.data('nonce'),
			postId = id.match(/\-([0-9]+)$/)[1],
			$postRatingsLoading = $('#post-ratings-' + postId + '-loading'),
			isBeingRated = $postRatings.data('is-being-rated');

		var rate = function() {
			if (ratingsL10n.show_loading) {
				$postRatingsLoading.show();
			}

			jQuery.ajax({
				// TODO: Even though a nonce is used, one should never do non-idempotent HTTP requests with GET, so this should rather be a POST. See http://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html for reference.
				type: 'GET',
				url: ratingsL10n.ajax_url,
				// TODO: Changing to POST gives us the added benefit of being able to send data in any form we'd like, like a much more convenient JSON or something similar.
				data: 'action=postratings&pid=' + postId + '&rate=' + rating + '&postratings_' + postId + '_nonce=' + nonce,
				cache: false,
				success: function(data) {
					$postRatings.html(data);

					if (ratingsL10n.show_loading) {
						$postRatingsLoading.hide();
					}

					if (ratingsL10n.show_fading) {
						$postRatings.fadeTo('def', 1, function () {
							$postRatings.data('is-being-rated', false);
						});
					} else {
						$postRatings.data('is-being-rated', false);
					}
				}
			});
		};

		if (!isBeingRated) {
			$postRatings.data('is-being-rated', true);

			if (ratingsL10n.show_fading) {
				$postRatings.fadeTo('def', 0, rate);
			} else {
				rate();
			}
		}
	}).hover(function() {
		$(this).addClass('o').prevAll().addClass('o');
	}, function() {
		$(this).removeClass('o').prevAll().removeClass('o');
	});
});