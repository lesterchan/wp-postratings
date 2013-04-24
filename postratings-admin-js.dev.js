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
|	- Post Ratings Admin Javascript File											|
|	- wp-content/plugins/wp-postratings/postratings-admin-js.php		|
|																							|
+----------------------------------------------------------------+
*/


// Function: Update Rating Text, Rating Value
function update_rating_text_value() {
	jQuery('#postratings_loading').show();
	jQuery.ajax({type: 'GET', url: ratingsAdminL10n.admin_ajax_url, data: 'custom=' + jQuery('#postratings_customrating').val() + '&image=' + jQuery("input[@name=postratings_image]:checked").val() + '&max=' + jQuery('#postratings_max').val(), cache: false, success: function (data) { jQuery('#rating_text_value').html(data); jQuery('#postratings_loading').hide(); }});
}