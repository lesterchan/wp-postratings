# WP-PostRatings
Contributors: GamerZ  
Donate link: http://lesterchan.net/site/donation/  
Tags: ratings, rating, postratings, postrating, vote, digg, ajax, post  
Requires at least: 2.8  
Tested up to: 4.4  
Stable tag: 1.83  

Adds an AJAX rating system for your WordPress blog's post/page.

## Description

### Build Status
[![Build Status](https://travis-ci.org/lesterchan/wp-postratings.svg?branch=master)](https://travis-ci.org/lesterchan/wp-postratings)

### Development
[https://github.com/lesterchan/wp-postratings](https://github.com/lesterchan/wp-postratings "https://github.com/lesterchan/wp-postratings")

### Translations
[http://dev.wp-plugins.org/browser/wp-postratings/i18n/](http://dev.wp-plugins.org/browser/wp-postratings/i18n/ "http://dev.wp-plugins.org/browser/wp-postratings/i18n/")

### Credits
* Plugin icon by [Freepik](http://www.freepik.com) from [Flaticon](http://www.flaticon.com)
* Icons courtesy of [FamFamFam](http://www.famfamfam.com/ "FamFamFam") and [Everaldo](http://www.everaldo.com "Everaldo")

### Donations
I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.

## Changelog
### Version 1.83
* NEW: Added 'wp_postratings_display_comment_author_ratings' filter
* FIXED: Move wp_postratings_image_extension filter to init()
* FIXED: Show headline, datePublished and image despite there is no ratings
* FIXED: Show post without ratings as well when sorting is done in URL. Props @talljosh.

### Version 1.82
* NEW: Added 'wp_postratings_image_extension' filter
* FIXED: Added headline, datePublished, image to Article Schema type
* FIXED: Deprecated PHP4 constructor in WordPress 4.3
* FIXED: Remove schema code when Rich Snippets is off

### Version 1.81
* NEW: Added worstRating of 1. Props @rafaellop
* NEW: Checked for defined() for RATINGS_IMG_EXT to allow overwrite
* FIXED: Integration with WP-Stats

### Version 1.80
* NEW: Suppor Custom Post Types in Widgets
* NEW: Added 'wp_postratings_process_ratings_user', 'wp_postratings_process_ratings_userid' & 'wp_postratings_check_rated' filters
* NEW: Supports WordPress Multisite Network Activate
* NEW: Uses WordPress native uninstall.php

### Version 1.79
* NEW: Use POST for ratings instead
* NEW: Add 'wp_postratings_schema_itemtype' filter so that you can change the Schema Type. See the FAQ for sample.
* FIXED: Use 'is_rtl()' instead of $text_direction

### Version 1.78
* NEW: Uses Dash Icons
* NEW: Option to turn off Google Rich Snippets
* FIXED: Use SITECOOKIEPATH instead of COOKIEPATH. Props jbrule.
* FIXED: If global $id is 0, use get_the_ID(). Props instruite.
* FIXED: use esc_attr() and esc_js() to escape characters

### Version 1.77
* NEW: Add in %POST_ID% template variables
* FIXED: Ensure Google Rich Snippet only displays in main loop and not in the widget
* FIXED: Removed reviewCount from Google Rich Snippet
* FIXED: Make the ratings widget more optimized
* FIXED: Some widget templates are using postratings_template_mostrated instead of postratings_template_highestrated

### Version 1.76
* FIXED: No longer needing add_post_meta() if update_post_meta() fails
* FIXED: Update 'Individual Rating Text/Value' Display no working due to missing nonce
* FIXED: Added stripslashes() to remove slashes in the templates
* FIXED: Check whether it is an array to prevent array_key_exists() from throwing a warning.

### Version 1.75
* Change htmlspecialchars to esc_attr(). Props Ryan Satterfield.
* Change esc_attr() to wp_kses() For itemprop. Props oneTarek.

### Version 1.74
*  check_rated_username() should be using $user_ID. Props Artem Gordinsky.

### Version 1.73
* Add Stars Flat (PNG) Icons. Props hebaf.
* Change Schema From http://schema.org/Product To http://schema.org/Article

### Version 1.72 (11-07-2013)
* Fixed not logging ratings
* Fixed sorting of ratings logs

### Version 1.71 (10-07-2013)
* Fixed "unable to delete logs/data"

### Version 1.70 (01-07-2013)
* Add rate_post action for other plugins to use. Props paulgibbs.
* Prevent direct access to PHP files to avoid PHP errors. Props paulgibbs.
* Fixes for PHP Notices. Props paulgibbs.
* Improvements. Props paulgibbs.
  * Better and safer handling of input variables
  * Removed some manual SQL in favour of WP's API.
  * Audited the rest of the SQL to make sure it was safe.
  * Removed unneeded switch() block, and decreased the line indentation for better readability :)
  * Use $wpdb->prepare() for SQL statements in wp-postratings.php
* esc_attr(). Props felipedjinn.

### Version 1.65 (19-04-2013)
* Fixed "Creating default object from empty value"
* Added Text Domain To Plugin
* Added Tested To 3.5

### Version 1.64 (17-12-2012)
* Add "Ratings" Column To Manage Pages In WP-Admin
* Add Sortable "Ratings" Column To Manage Posts/Pages In WP-Admin

### Version 1.63 (21-05-2012)
* NEW: Move AJAX Request to wp-admin/admin-ajax.php
* NEW: Added nonce To AJAX Calls And Admin Pages
* NEW: Added Support For Google Rich Snippet

### Version 1.62 (31-09-2011)
* FIXED: Escaped Hostname
* FIXED: Ensure Ratings Post ID In Shortcode Is An Integer

### Version 1.61 (17-02-2011)
* FIXED: XSS Vulnerability. Thanks Dion Hulse aka dd32
* FIXED: Removed Global $post

### Version 1.50 (15-06-2009)
* NEW: Works For WordPress 2.8 Only
* NEW: Javascript Now Placed At The Footer
* NEW: Uses jQuery Instead Of tw-sack
* NEW: Minified Javascript Instead Of Packed Javascript
* NEW: Renamed postratings-admin-js-packed.js To postratings-admin-js.js
* NEW: Renamed postratings-admin-js.js To postratings-admin-js.dev.js
* NEW: Renamed postratings-js-packed.js To postratings-js.js
* NEW: Renamed postratings-js.js To postratings-js.dev.js
* NEW: Translate Javascript Variables Using wp_localize_script()
* NEW: Added In Most Rated & Highest Rated Pages To WP-Stats
* NEW: Added get_highest_score(), get_highest_score_category(), get_highest_score_range(), get_highest_score_range_category()
* NEW: Use _n() Instead Of __ngettext() And _n_noop() Instead Of __ngettext_noop()
* NEW: Uses New Widget Class From WordPress
* NEW: Merge Widget Code To wp-postratings.php And Remove wp-postratings-widget.php
* NEW: get_highest_rated_tag() And get_lowest_rated_tag()
* FIXED: Uses $_SERVER['PHP_SELF'] With plugin_basename(__FILE__) Instead Of Just $_SERVER['REQUEST_URI']
* FIXED: Ensure That Post Is Not A Revision
* FIXED: Missing + Sign For Thumbs Up/Down Ratings If Score Is Positive
* FIXED: Logged By Username Now Shows Ratings Results To Users Who Did Not Login
* FIXED: Multiple Loops Filtered Not Cleared

### Version 1.40 (12-12-2008)
* NEW: Works For WordPress 2.7 Only
* NEW: Load Admin JS And CSS Only In WP-PostRatings Admin Pages
* NEW: Added postratings-admin-css.css For WP-PostRatings Admin CSS Styles
* NEW: Allow The Usage Of PNG Icons Or GIF Icons. See Usage Tab.*
* NEW: Added get_lowest_rated_range() Function
* NEW: Right To Left Language Support by <a href="http://persian-programming.com/" title="http://persian-programming.com/">Kambiz R. Khojasteh</a>
* NEW: Added "postratings-css-rtl.css" by <a href="http://persian-programming.com/" title="http://persian-programming.com/">Kambiz R. Khojasteh</a>
* NEW: Added 3 Functions For Creating HTML Code Of Rating Images by <a href="http://persian-programming.com/" title="http://persian-programming.com/">Kambiz R. Khojasteh</a>
* NEW: Call postratings_textdomain() In create_ratinglogs_table() and process_ratings() by <a href="http://persian-programming.com/" title="http://persian-programming.com/">Kambiz R. Khojasteh</a>
* NEW: Replaced Template Variable Calculations With expand_ratings_template() by <a href="http://persian-programming.com/" title="http://persian-programming.com/">Kambiz R. Khojasteh</a>
* NEW: Added Filter expand_ratings_template For Localizing Digits by <a href="http://persian-programming.com/" title="http://persian-programming.com/">Kambiz R. Khojasteh</a>
* NEW: Uses wp_register_style(), wp_print_styles(), plugins_url() And site_url()
* FIXED: SSL Support

### Version 1.31 (16-07-2008)
* NEW: Works For WordPress 2.6
* NEW: Renamed GET Variables sortby To r_sortby And orderby To r_orderby
* NEW: Renamed postratings-admin-js.php To postratings-admin-js.js and Move The Dynamic Javascript Variables To The PHP Pages
* NEW: Renamed postratings-js.php To postratings-js.js and Move The Dynamic Javascript Variables To The PHP Pages
* NEW: Uses postratings-js-packed.js And postratings-admin-js-packed.js
* NEW: When Displaying The Ratings Given By Comment Author, It Check Against Cookie As Well As IP
* NEW: Better Translation Using __ngetext() by <a href="http://hweia.ru/" title="http://hweia.ru/">Anna Ozeritskaya</a>
* FIXED: MYSQL Charset Issue Should Be Solved
* FIXED: Removed WP-Cache Compatibility As It Is Not Tested
* FIXED: Able To Use r_sortby And r_orderby in query_posts()

### Version 1.30 (01-06-2008)
* NEW: Works For WordPress 2.5 Only
* NEW: Removed 'postratings-usage.php'
* NEW: Uses ShortCode API
* NEW: Splitted Templates From PostRating Options Into Its Own File, 'postratings-templates.php'
* NEW: Able To Display The Ratings Given By Comment Author When Displaying Comments
* NEW: WP-PostRatings Will Load 'postratings-css.css' Inside Your Theme Directory If It Exists. If Not, It Will Just Load The Default 'postratings-css.css' By WP-PostRatings
* NEW: Use number_format_i18n() Instead Of number_format()
* NEW: Added Get Most/Highest Rated Post Within A Given Time Range Function
* NEW: Added Get Most/Highest Rated Post By Category ID Within A Given Time Range Function
* NEW: Added get_lowest_rated() Function
* NEW: Added get_lowest_rated_category() Function
* NEW: Added get_most_rated_category() Function
* NEW: Get Most Rated Is Now Under The Templates
* NEW: Minimum Votes Options/Parameters Added To get_lowest_rated(), get_lowest_rated_category(), get_highest_rated(), get_highest_rated_category(), get_most_rated() and get_most_rated_category() Functions
* NEW: Uses /wp-postratings/ Folder Instead Of /postratings/
* NEW: Uses wp-postratings.php Instead Of postratings.php
* NEW: Move WP-PostRatings Stats Out Of postratings.php Into postrating-stats.php
* FIXED: Thumbs Up/Down Post Should Get Sorted By Score Instead Of Average
* FIXED: Increased The Length Of The Input Box For Individual Rating Value
* FIXED: Manage Ratings Does Not Display "Numbers" Style Rating
* FIXED: %POST_EXCERPT% Variable Is Sometimes Empty

### Version 1.20 (01-10-2007)
* NEW: Works For WordPress 2.3 Only
* NEW: Ability To Embed [ratings=1] Into Post/Excerpt, Where 1 Is The ID Of The Post/Page Ratings You Want To Display
* NEW: Ability To Embed [ratings_results=1] Into Post/Excerpt, Where 1 Is The ID Of The Post/Page Ratings Results You Want To Display
* NEW: Ability To Support Mutiple Categories For get_highest_rated_category(). By: <a href="http://pomoti.com">Dirceu P. Junior</a>
* NEW: Ability To Embed [ratings] Into Excerpt
* NEW: Added Template For No Permission To Rate
* NEW: Ability To Filter Logs By Post ID, User and Rating
* NEW: Added heart, heart_crystal, plusminus, plusminus_crystal, stars_crystal, thumbs, tickcross, tickcross_crystal and updown_crystal Rating Styles
* NEW: Supports Up/Down Or Thumbs Up/Thumbs Down Rating
* NEW: Supports Custom Image For Individual Rating Scale
* NEW: WP-Cache Compatible By <a href="http://forums.lesterchan.net/index.php/topic,227.0.html">Nir Aides</a>
* NEW: Highest Rated Widge And Most Rated Widget Added
* NEW: Ability To Uninstall WP-PostRatings
* NEW: Uses WP-Stats Filter To Add Stats Into WP-Stats Page
* FIXED: Some Translation Bug In postrating-usage.php

### Version 1.11 (01-06-2007)
* NEW: Ratings Custom Fields Will Automatically Be Created With The Creation Of Each New Post/Page
* NEW: Added AJAX Style Option: "Show Loading Image With Text"
* NEW: Added AJAX Style Option: "Show Fading In And Fading Out Of Ratings"
* NEW: Removed Ratings From Feed If Ratings Is Embedded Into The Post Using [ratings]
* FIXED: Wrong URL For Page Under Top Rated/Highest Posts Listing
* FIXED: Next/Previous Paging Bug In WP-Admin -> Manage Ratings
* FIXED: Sort Most Rated Posts By Number Of Voters Followed By Post Average Ratings

### Version 1.10 (01-02-2007)
* NEW: Works For WordPress 2.1 Only
* NEW: Renamed postratings-js.js To postratings-js.php To Enable PHP Parsing
* NEW: Added Function To Get Highest Rated Post By Category ID

### Version 1.05 (02-01-2007)
* NEW: Added The Ability For Each Rating Star To Have Its Own Text
* NEW: Highest Rated Post Is Now In The Templates For Easy Modification
* NEW: Usage Instructions Is Also Included Within The Plugin Itself
* NEW: Able To Delete Ratings Logs And Data By Post IDs
* NEW: Able To Uninstall WP-PostRatings
* NEW: Localization WP-PostRatings
* FIXED: snippet_text() Function Missing
* FIXED: AJAX Not Working On Servers Running On PHP CGI
* FIXED: Highest Rated Post Is Now Based On The Ratings Followed By The Number Of Votes
* FIXED: Added Some Default Styles To postratings-css.css To Ensure That WP-PostRatings Does Not Break

### Version 1.04 (01-10-2006)
* NEW: Ability To Logged By UserName
* NEW: get_highest_rated_sidebar(); To Display The Highest Rated Post On The Sidebar
* NEW: Added CSS Class 'post-ratings-image' To All IMG Tags
* FIXED: If Site URL Doesn't Match WP Option's Site URL, WP-PostRatings Will Not Work

### Version 1.03 (01-07-2006)
* NEW: Total Rating Votes Stats And Total Rating Users Stats Function Added
* FIXED: Ratings Not Working On Physical Pages That Is Integrated Into WordPress
* FIXED: Modified Get Most/Highest Rated Post Function
* FIXED: Search Bots Unable To Index Site

### Version 1.02a (07-06-2006)
* FIXED: AJAX Not Working In Opera Browser

### Version 1.02 (01-06-2006)
* NEW: Fading In/Put Effect After You Rate A Post
* NEW: Rating Voting And Rating Results Are On The Same Image
* NEW: Added Rating Option For Logging Method
* NEW: Added Rating Option For Who Can Rate
* NEW: Added Rating Results Image To Get Highest Rated Stats
* NEW: Rating Administration Panel And The Code That WP-PostRatings Generated Is XHTML 1.0 Transitional

### Version 1.01 (01-04-2006)
* NEW: AJAX Voting
* FIXED: Block Search Bots From Voting
* FIXED: Hard Coded Table Name In Ratings Stats

### Version 1.00 (01-03-2006)
* NEW: Initial Release

## Installation

1. Open `wp-content/plugins` Folder
2. Put: `Folder: wp-PostRatings`
3. Activate `WP-PostRatings` Plugin
4. Go to `WP-Admin -> Ratings`

### Usage
1. Open `wp-content/themes/<YOUR THEME NAME>/index.php`
2. You may place it in archive.php, single.php, post.php or page.php also.
3. Find: `<?php while (have_posts()) : the_post(); ?>`
4. Add Anywhere Below It (The Place You Want The Ratings To Show): `<?php if(function_exists('the_ratings')) { the_ratings(); } ?>`

* If you DO NOT want the ratings to appear in every post/page, DO NOT use the code above. Just type in `[ratings]` into the selected post/page content and it will embed ratings into that post/page only.
* If you want to embed other post ratings use `[ratings id="1"]`, where 1 is the ID of the post/page ratings that you want to display.
* If you want to embed other post ratings results, use `[ratings id="1" results="true"]`, where 1 is the ID of the post/page ratings results that you want to display.


## Upgrading

1. Deactivate `WP-PostRatings` Plugin
2. Open `wp-content/plugins` Folder
3. Put/Overwrite: `Folder: wp-postratings`
4. Activate `WP-PostRatings` Plugin
5. Go to `WP-Admin -> Ratings -> Ratings Templates` and restore all the template variables to `Default`
6. Go to `WP-Admin -> Appearance -> Widgets` and re-add the Ratings Widget

## Upgrade Notice

N/A

## Screenshots

1. Admin - Ratings Log Bottom
2. Admin - Ratings Log Top
3. Admin - Ratings Options
4. Admin - Ratings Templates
5. Ratings
6. Ratings Hover

## Frequently Asked Questions

### How To Change Schema Type?
* The default schema type is 'Article', if you want to change it to 'Recipe', you need to make use of the `wp_postratings_schema_itemtype` filter as shown in the sample code below:
<code>
<?php  
add_filter('wp_postratings_schema_itemtype', 'wp_postratings_schema_itemtype');  
function wp_postratings_schema_itemtype($itemtype) {  
	return 'itemscope itemtype="http://schema.org/Recipe"';  
}  
?>
</code>

### How To Display Comment Author Ratings?
* By default, the comment author ratings are not displayed. If you want to display the ratings, you need to make use of the `wp_postratings_display_comment_author_ratings` filter as shown in the sample code below:
<code>
function custom_display_comment_author_ratings() {
    return true;
}
add_filter( 'wp_postratings_display_comment_author_ratings', 'custom_display_comment_author_ratings' );
</code>

### How To use PNG images instead of GIF images?
* The default image extension if 'gif', if you want to change it to 'png', you need to make use of the `wp_postratings_image_extension` filter as shown in the sample code below:
<code>
function custom_rating_image_extension() {
    return 'png';
}
add_filter( 'wp_postratings_image_extension', 'custom_rating_image_extension' );
</code>

### How Does WP-PostRatings Load CSS?
* WP-PostRatings will load `postratings-css.css` from your theme's directory if it exists.
* If it doesn't exists, it will just load the default 'postratings-css.css' that comes with WP-PostRatings.
* This will allow you to upgrade WP-PostRatings without worrying about overwriting your ratings styles that you have created.

### How To Use Ratings Stats With Widgets?
1. Go to `WP-Admin -> Appearance -> Widgets`
2. The widget name is Ratings.

### How To Use Ratings Stats Outside WP Loop?

### To Display Lowest Rated Post
* Use:
<code>
<?php if (function_exists('get_lowest_rated')): ?>
	<ul>
		<?php get_lowest_rated(); ?>
	</ul>
<?php endif; ?>
</code>
* Default: get_lowest_rated('both', 0, 10)
* The value 'both' will display both the lowest rated posts and pages.
* If you want to display the lowest rated posts only, replace 'both' with 'post'.
* If you want to display the lowest rated pages only, replace 'both' with 'page'.
* The value 0 refers to the minimum votes required before the post get shown.
* The value 10 will display only the top 10 lowest rated posts/pages.

### To Display Lowest Rated Post By Tag
* Use:
<code>
<?php if (function_exists('get_lowest_rated_tag')): ?>
	<ul>
		<?php get_lowest_rated_tag(TAG_ID); ?>
	</ul>
<?php endif; ?>
</code>
* Default: get_lowest_rated_tag(TAG_ID, 'both', 0, 10)
* Replace TAG_ID will your tag ID. If you want it to span several categories, replace TAG_ID with array(1, 2) where 1 and 2 are your categories ID.
* The value 'both' will display both the lowest rated posts and pages.
* If you want to display the lowest rated posts only, replace 'both' with 'post'.
* If you want to display the lowest rated pages only, replace 'both' with 'page'.
* The value 0 refers to the minimum votes required before the post get shown.
* The value 10 will display only the top 10 lowest rated posts/pages.

### To Display Lowest Rated Post In A Category
* Use:
<code>
<?php if (function_exists('get_lowest_rated_category')): ?>
	<ul>
		<?php get_lowest_rated_category(CATEGORY_ID); ?>
	</ul>
<?php endif; ?>
</code>
* Default: get_lowest_rated_category(CATEGORY_ID, 'both', 0, 10)
* Replace CATEGORY_ID will your category ID. If you want it to span several categories, replace CATEGORY_ID with array(1, 2) where 1 and 2 are your categories ID.
* The value 'both' will display both the lowest rated posts and pages.
* If you want to display the lowest rated posts only, replace 'both' with 'post'.
* If you want to display the lowest rated pages only, replace 'both' with 'page'.
* The value 0 refers to the minimum votes required before the post get shown.
* The value 10 will display only the top 10 lowest rated posts/pages.

### To Display Highest Rated Post
* Use:
<code>
<?php if (function_exists('get_highest_rated')): ?>
	<ul>
		<?php get_highest_rated(); ?>
	</ul>
<?php endif; ?>
</code>
* Default: get_highest_rated('both', 0, 10)
* The value 'both' will display both the highest rated posts and pages.
* If you want to display the highest rated posts only, replace 'both' with 'post'.
* If you want to display the highest rated pages only, replace 'both' with 'page'.
* The value 0 refers to the minimum votes required before the post get shown.
* The value 10 will display only the top 10 highest rated posts/pages.

### To Display Highest Rated Post By Tag
* Use:
<code>
<?php if (function_exists('get_highest_rated_tag')): ?>
	<ul>
		<?php get_highest_rated_tag(TAG_ID); ?>
	</ul>
<?php endif; ?>
</code>
* Default: get_highest_rated_tag(TAG_ID, 'both', 0, 10)
* Replace TAG_ID will your tag ID. If you want it to span several categories, replace TAG_ID with array(1, 2) where 1 and 2 are your categories ID.
* The value 'both' will display both the highest rated posts and pages.
* If you want to display the highest rated posts only, replace 'both' with 'post'.
* If you want to display the highest rated pages only, replace 'both' with 'page'.
* The value 0 refers to the minimum votes required before the post get shown.
* The value 10 will display only the top 10 highest rated posts/pages.

### To Display Highest Rated Post In A Category
* Use:
<code>
<?php if (function_exists('get_highest_rated_category')): ?>
	<ul>
		<?php get_highest_rated_category(CATEGORY_ID); ?>
	</ul>
<?php endif; ?>
</code>
* Default: get_highest_rated_category(CATEGORY_ID, 'both', 0, 10)
* Replace CATEGORY_ID will your category ID. If you want it to span several categories, replace CATEGORY_ID with array(1, 2) where 1 and 2 are your categories ID.
* The value 'both' will display both the highest rated posts and pages.
* If you want to display the highest rated posts only, replace 'both' with 'post'.
* If you want to display the highest rated pages only, replace 'both' with 'page'.
* The value 0 refers to the minimum votes required before the post get shown.
* The value 10 will display only the top 10 highest rated posts/pages.

### To Display Highest Rated Post Within A Given Period
* Use:
<code>
<?php if (function_exists('get_highest_rated_range')): ?>
	<ul>
		<?php get_highest_rated_range('1 day'); ?>
	</ul>
<?php endif; ?>
</code>
* Default: get_highest_rated_range('1 day', 'both', 10)
* The value '1 day' will be the range that you want. You can use '2 days', '1 month', etc.
* The value 'both' will display both the most rated posts and pages.
* If you want to display the most rated posts only, replace 'both' with 'post'.
* If you want to display the most rated pages only, replace 'both' with 'page'.
* The value 10 will display only the top 10 most rated posts/pages.

### To Display Most Rated Post
* Use:
<code>
<?php if (function_exists('get_most_rated')): ?>
	<ul>
		<?php get_most_rated(); ?>
	</ul>
<?php endif; ?>
</code>
* Default: get_most_rated('both', 0, 10)
* The value 'both' will display both the most rated posts and pages.
* If you want to display the most rated posts only, replace 'both' with 'post'.
* If you want to display the most rated pages only, replace 'both' with 'page'.
* The value 0 refers to the minimum votes required before the post get shown.
* The value 10 will display only the top 10 most rated posts/pages.

### To Display Most Rated Post In A Category
* Use:
<code>
<?php if (function_exists('get_most_rated_category')): ?>
	<ul>
		<?php get_most_rated_category(CATEGORY_ID); ?>
	</ul>
<?php endif; ?>
</code>
* Default: get_most_rated_category(CATEGORY_ID, 'both', 0, 10)
* Replace CATEGORY_ID will your category ID. If you want it to span several categories, replace CATEGORY_ID with array(1, 2) where 1 and 2 are your categories ID.
* The value 'both' will display both the most rated posts and pages.
* If you want to display the most rated posts only, replace 'both' with 'post'.
* If you want to display the most rated pages only, replace 'both' with 'page'.
* The value 0 refers to the minimum votes required before the post get shown.
* The value 10 will display only the top 10 most rated posts/pages.

### To Display Most Rated Post Within A Given Period
* Use:
<code>
<?php if (function_exists('get_most_rated_range')): ?>
	<ul>
		<?php get_most_rated_range('1 day'); ?>
	</ul>
<?php endif; ?>
</code>
* Default: get_most_rated_range('1 day', 'both', 10)
* The value '1 day' will be the range that you want. You can use '2 days', '1 month', etc.
* The value 'both' will display both the most rated posts and pages.
* If you want to display the most rated posts only, replace 'both' with 'post'.
* If you want to display the most rated pages only, replace 'both' with 'page'.
* The value 10 will display only the top 10 most rated posts/pages.

### To Display Highest Score Post
* Use:
<code>
<?php if (function_exists('get_highest_score')): ?>
	<ul>
		<?php get_highest_score(); ?>
	</ul>
<?php endif; ?>
</code>
* Default: get_highest_score('both', 0, 10)
* The value 'both' will display both the most rated posts and pages.
* If you want to display the most rated posts only, replace 'both' with 'post'.
* If you want to display the most rated pages only, replace 'both' with 'page'.
* The value 0 refers to the minimum votes required before the post get shown.
* The value 10 will display only the top 10 most rated posts/pages.

### To Display Highest Score Post In A Category
* Use:
<code>
<?php if (function_exists('get_highest_score_category')): ?>
	<ul>
		<?php get_highest_score_category(CATEGORY_ID); ?>
	</ul>
<?php endif; ?>
</code>
* Default: get_highest_score_category(CATEGORY_ID, 'both', 0, 10)
* Replace CATEGORY_ID will your category ID. If you want it to span several categories, replace CATEGORY_ID with array(1, 2) where 1 and 2 are your categories ID.
* The value 'both' will display both the most rated posts and pages.
* If you want to display the most rated posts only, replace 'both' with 'post'.
* If you want to display the most rated pages only, replace 'both' with 'page'.
* The value 0 refers to the minimum votes required before the post get shown.
* The value 10 will display only the top 10 most rated posts/pages.


### To Display Highest Score Post Within A Given Period
* Use:
<code>
<?php if (function_exists('get_highest_score_range')): ?>
	<ul>
		<?php get_highest_score_range('1 day'); ?>
	</ul>
<?php endif; ?>
</code>
* Default: get_highest_score_range('1 day', 'both', 10)
* The value '1 day' will be the range that you want. You can use '2 days', '1 month', etc.
* The value 'both' will display both the most rated posts and pages.
* If you want to display the most rated posts only, replace 'both' with 'post'.
* If you want to display the most rated pages only, replace 'both' with 'page'.
* The value 10 will display only the top 10 most rated posts/pages.

### To Sort Highest/Lowest Rated Posts
* You can use: ``<?php query_posts( array( 'meta_key' => 'ratings_average', 'orderby' => 'meta_value_num', 'order' => 'DESC' ) ); ?>``
* Or pass in the variables to the URL: `http://yoursite.com/?r_sortby=highest_rated&amp;r_orderby=desc`
* You can replace desc with asc if you want the lowest rated posts.

### To Sort Most/Least Rated Posts
* You can use: ``<?php query_posts( array( 'meta_key' => 'ratings_users', 'orderby' => 'meta_value_num', 'order' => 'DESC' ) ); ?>``
* Or pass in the variables to the URL: `http://yoursite.com/?r_sortby=most_rated&amp;r_orderby=desc`
* You can replace desc with asc if you want the least rated posts.
