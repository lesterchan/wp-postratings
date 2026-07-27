# WP-PostRatings
Contributors: GamerZ  
Donate link: https://lesterchan.net/site/donation/  
Tags: ratings, rating, postratings, postrating, vote, digg, ajax, post  
Requires at least: 6.0  
Tested up to: 7.0  
Stable tag: 2.0.0  
Requires PHP: 7.4  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds an AJAX rating system for your WordPress site's content.

## Description

### Usage
1. Open `wp-content/themes/<YOUR THEME NAME>/index.php`
2. You may place it in archive.php, single.php, post.php or page.php also.
3. Find: `<?php while (have_posts()) : the_post(); ?>`
4. Add Anywhere Below It (The Place You Want The Ratings To Show): `<?php if(function_exists('the_ratings')) { the_ratings(); } ?>`

* If you DO NOT want the ratings to appear in every post/page, DO NOT use the code above. Just type in `[ratings]` into the selected post/page content and it will embed ratings into that post/page only.
* If you want to embed other post ratings use `[ratings id="1"]`, where 1 is the ID of the post/page ratings that you want to display.
* If you want to embed other post ratings results, use `[ratings id="1" results="true"]`, where 1 is the ID of the post/page ratings results that you want to display.

### Development
[https://github.com/lesterchan/wp-postratings](https://github.com/lesterchan/wp-postratings "https://github.com/lesterchan/wp-postratings")

### Credits
* Plugin icon by [Freepik](https://www.freepik.com) from [Flaticon](https://www.flaticon.com)

### Donations
I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.

## Changelog
### 2.0.0
* IMPORTANT: If you set "Header That Contains The IP" (for Cloudflare, a load balancer or any reverse proxy), that header is now parsed as the forwarded-for chain it is, and only the first valid address in it is used. Previously the whole header value was stored, so a visitor could rate repeatedly just by appending another address to it. Existing rating logs recorded through such a header will no longer match, so some visitors may be able to rate once more. Leave the field blank unless you are actually behind a proxy. See the FAQ.
* IMPORTANT: The vote images no longer carry inline `onmouseover`/`onclick` attributes; hovering and clicking are handled by one delegated listener. Custom CSS or JavaScript that targeted those inline handlers, or that called `current_rating()` or `rate_post()` directly, needs updating.
* NEW: Requires WordPress 6.0 and PHP 7.4.
* NEW: Rewritten as classes under `includes/`, so the plugin now works installed under any directory name.
* NEW: Settings moved to the WordPress Settings API, with the options and templates screens merged into one tabbed page.
* NEW: The rating log is now a standard WordPress list table, with sortable columns, bulk delete, a search box and Screen Options.
* NEW: The scripts are vanilla JavaScript; the jQuery dependency is gone.
* NEW: The fifteen `postratings_*` option rows are consolidated into a single `postratings_options` row. Your settings are migrated automatically.
* FIXED: The per-post lock file was never released, so one was left behind in the server's temporary directory on every single rating.
* FIXED: Uninstalling on a multisite network stopped after the first 100 sites, leaving the options and the rating table behind on every site after that.
* FIXED: Activating network-wide also stopped after 100 sites.
* FIXED: A rating outside the configured scale was recorded as a vote worth nothing, and warned on PHP 8.
* FIXED: Comment author ratings never matched by IP, so they had been silently broken since ratings began being stored hashed.
* FIXED: The "numbers" rating image set could not be saved; it reported success and reverted to stars.
* FIXED: Post titles containing an apostrophe were stored in the log with a stray backslash.
* FIXED: The rating log always claimed to be sorted "Descending" whichever order was chosen.
* FIXED: Deleting all rating data left every post's cache holding the values that had just been removed.
* FIXED: Numerous PHP 8 warnings on the admin screens and in an unconfigured widget.

### 1.91.3
* NEW: WordPress 7.0
* FIXED: Escape individual rating text in the admin AJAX handler to prevent XSS

### 1.91.2
* FIXED: XSS in Google Rich Text Snippets

### 1.91.1
* FIXED: Read from default REMOTE_ADDR unless specified in options

### 1.91
* NEW: Supports specifying which header to read the user's IP from

### 1.90.1
* FIXED: Support mutex lock for multi-site.

### 1.90
* FIXED: Use mutex lock to prevent race condition

### 1.89.1
* FIXED: Change all http://schema.org to https://schema.org

### 1.89
* NEW: Added `post_id` to second argument of `wp_postratings_expand_ratings_template`.
* NEW Removed passed by reference for `get_post()` 

### 1.88
* NEW: Added filter `wp_postratings_disable_richsnippet` to disable richsnippet on the fly.
* NEW: Added a setting in `WP-Admin -> Ratings -> Rating Options` to disable the ratings component of the Rich Snippet. Props @8ctopus

### 1.87
* FIXED: Rename filter `expand_ratings_template` to `wp_postratings_expand_ratings_template` for consistency.
* FIXED: Remove wp_print_scripts
* FIXED: Added additional to Google Structured Data despite it is no longer working. Will consider removing it next time
* NEW: Added `wp_postratings_ipaddress` and `wp_postratings_hostname` to allow user to overwrite it.
* NEW: Add loading alt text filer
* NEW: Add wp_postratings_always_log filter to allow user to always log no matter what

### 1.86.2
* FIXED: Wrong type check for inser_half which affects half rating image.

### 1.86.1
* FIXED: Sanitize file name for images folder in WP-Admin

### 1.86
* NEW: Hashed IP and Anonymize Hostname to make it GDPR compliance
* NEW: If Do Not Log is set in Rating Options, do not log to DB

### 1.85
* NEW: wp_postratings_post_thumbnail filter
* FIXED: Take into consideration logging method when dealing with ratings in comments
* FIXED: Compressed Images

### 1.84.1
* NEW: New wp_postratings_google_structured_data filter to filter Google Structured Data.
* FIXED: unnamed-file.numbers due to sanitize_file_name().
* FIXED: Generate the full path to image to prevent Googlebot from 404.

### 1.84
* NEW: Added '%POST_THUMBNAIL%' Template variable.
* NEW: Added 'wp_postratings_cookie_expiration' filter. Props @ramiy.
* NEW: Added 'wp_postratings_ratings_image_alt' filter
* NEW: Added more meta itemprops to pass Structured Data Testing Tool test
* NEW: Remove po/mo files from the plugin. Props @ramiy.
* NEW: Use translate.wordpress.org to translate the plugin. Props @ramiy.
* NEW: Add phpDocs and update file headers. Props @ramiy.
* NEW: Adds the ability to restrict voting rights to members of the blog. Props @stephenharris.
* FIXED: Use the new admin headings hierarchy with H1, H2, H3 tags. Props @ramiy.
* FIXED: Move *.js files to /js/ sub-folder. Props @ramiy.
* FIXED: Move *.css files to /css/ sub-folder. Props @ramiy.
* FIXED: Move the scripts to a separate file in /includes/ sub-folder. Props @ramiy.
* FIXED: Move the widget to a separate file in /includes/ sub-folder. Props @ramiy.
* FIXED: Move the shortcode to a separate file in /includes/ sub-folder. Props @ramiy.
* FIXED: Move activation hooks to a separate file in /includes/ sub-folder. Props @ramiy.
* FIXED: Move admin functions and hooks to a separate file in /includes/ sub-folder. Props @ramiy.
* FIXED: Move the i18n load to a separate file in /includes/ sub-folder. Props @ramiy.
* FIXED: Replace die() with wp_die() and add i18n to the strings. Props @ramiy.
* FIXED: Update translation strings to avoid using 'post' as the post type. Props @ramiy.
* FIXED: Minor translation string fix. Props @ramiy.
* FIXED: Update rating widget. Props @ramiy.
* FIXED: Security hardening. Props @stephenharris.

### 1.83.2
* FIXED: Unauthenticated blind SQL injection in ratings_most_orderby(). Props @Ben Bidner from Automattic.

### 1.83.1
* FIXED: Remove No Results template from the_ratings_results()

### 1.83
* NEW: Added 'wp_postratings_display_comment_author_ratings' filter. Props @ramiy.
* FIXED: Removing Loading ... because SERP will index the text if the ratings is at the top of the article
* FIXED: Move 'wp_postratings_image_extension' filter to init()
* FIXED: Show headline, datePublished and image despite there is no ratings
* FIXED: Show post without ratings as well when sorting is done in URL. Props @talljosh.

### 1.82
* NEW: Added 'wp_postratings_image_extension' filter. Props @ramiy.
* FIXED: Added headline, datePublished, image to Article Schema type
* FIXED: Deprecated PHP4 constructor in WordPress 4.3
* FIXED: Remove schema code when Rich Snippets is off

### 1.81
* NEW: Added worstRating of 1. Props @rafaellop
* NEW: Checked for defined() for RATINGS_IMG_EXT to allow overwrite
* FIXED: Integration with WP-Stats

### 1.80
* NEW: Suppor Custom Post Types in Widgets
* NEW: Added 'wp_postratings_process_ratings_user', 'wp_postratings_process_ratings_userid' & 'wp_postratings_check_rated' filters
* NEW: Supports WordPress Multisite Network Activate
* NEW: Uses WordPress native uninstall.php

### 1.79
* NEW: Use POST for ratings instead
* NEW: Add 'wp_postratings_schema_itemtype' filter so that you can change the Schema Type. See the FAQ for sample.
* FIXED: Use 'is_rtl()' instead of $text_direction

### 1.78
* NEW: Uses Dash Icons
* NEW: Option to turn off Google Rich Snippets
* FIXED: Use SITECOOKIEPATH instead of COOKIEPATH. Props jbrule.
* FIXED: If global $id is 0, use get_the_ID(). Props instruite.
* FIXED: use esc_attr() and esc_js() to escape characters

### 1.77
* NEW: Add in %POST_ID% template variables
* FIXED: Ensure Google Rich Snippet only displays in main loop and not in the widget
* FIXED: Removed reviewCount from Google Rich Snippet
* FIXED: Make the ratings widget more optimized
* FIXED: Some widget templates are using postratings_template_mostrated instead of postratings_template_highestrated

### 1.76
* FIXED: No longer needing add_post_meta() if update_post_meta() fails
* FIXED: Update 'Individual Rating Text/Value' Display no working due to missing nonce
* FIXED: Added stripslashes() to remove slashes in the templates
* FIXED: Check whether it is an array to prevent array_key_exists() from throwing a warning.

### 1.75
* Change htmlspecialchars to esc_attr(). Props Ryan Satterfield.
* Change esc_attr() to wp_kses() For itemprop. Props oneTarek.

### 1.74
*  check_rated_username() should be using $user_ID. Props Artem Gordinsky.

### 1.73
* Add Stars Flat (PNG) Icons. Props hebaf.
* Change Schema From http://schema.org/Product To http://schema.org/Article

## Upgrade Notice

### 2.0.0
Major release. Requires WordPress 6.0 and PHP 7.4. Your settings are migrated automatically. If you have set "Header That Contains The IP", read the changelog before upgrading. Custom CSS or JavaScript targeting the vote images' inline `onmouseover`/`onclick` attributes needs updating.

## Screenshots

1. Admin - Ratings Log Bottom
2. Admin - Ratings Log Top
3. Admin - Ratings Options
4. Admin - Ratings Templates
5. Ratings
6. Ratings Hover

## Frequently Asked Questions

### Every visitor can rate over and over, or every rating log entry shows the same IP

This is about the **Header That Contains The IP** field on the Ratings Options screen.

Leave it blank unless your site is genuinely behind a reverse proxy or CDN such as Cloudflare. Blank means the plugin uses the address your web server actually saw, which is what you want on a normal host.

If you are behind a proxy, name the exact header your proxy sets, for example `HTTP_CF_CONNECTING_IP` for Cloudflare or `HTTP_X_FORWARDED_FOR` for most load balancers. Two things are worth knowing:

* A visitor can send any header they like. If you name a header your own stack does not set and overwrite, anyone can put whatever they want in it and rate as many times as they please.
* `X-Forwarded-For` is a list, `client, proxy1, proxy2`. As of 2.0.0 the plugin reads the first valid address in that list rather than storing the whole string. Before 2.0.0 it stored the whole string, which meant a visitor could append one more address and appear to be somebody new on every request.

Because the stored value has changed shape, rating logs recorded through such a header before 2.0.0 will not match any more, and some visitors may be able to rate one more time. Logs recorded without the field set are unaffected.


### How To Change Schema Type?

```php
<?php  
add_filter( 'wp_postratings_schema_itemtype', 'wp_postratings_schema_itemtype' );  
function wp_postratings_schema_itemtype( $itemtype ) {  
	return 'itemscope itemtype="https://schema.org/Recipe"';  
}  
?>
```

The default schema type is 'Article', if you want to change it to 'Recipe', you need to make use of the `wp_postratings_schema_itemtype` filter as shown in the sample code above.

### How To Add Your Site Logo For Google Rich Snippets

```php
<?php  
add_filter( 'wp_postratings_site_logo', 'wp_postratings_site_logo' );  
function wp_postratings_site_logo( $url ) {  
	return 'https://example.com/logo.png';  
}  
?>
```

By default, the plugin will use your site header image URL as your site logo. If you want to change it, you need to make use of the `wp_postratings_site_logo` filter as shown in the sample code above.

### How To Remove Ratings Image alt and title Text?

```php
<?php  
add_filter( 'wp_postratings_ratings_image_alt', 'wp_postratings_ratings_image_alt' );  
function wp_postratings_ratings_image_alt( $alt_title_text ) {  
	return '';  
}  
?>
```

### How To Display Comment Author Ratings?

```php
add_filter( 'wp_postratings_display_comment_author_ratings', '__return_true' );
```

By default, the comment author ratings are not displayed. If you want to display the ratings, you need to make use of the `wp_postratings_display_comment_author_ratings` filter as shown in the sample code above.

### How To use PNG images instead of GIF images?

```php
function custom_rating_image_extension() {
    return 'png';
}
add_filter( 'wp_postratings_image_extension', 'custom_rating_image_extension' );
```

The default image extension if 'gif', if you want to change it to 'png', you need to make use of the `wp_postratings_image_extension` filter as shown in the sample code above.

### How To change the cookie expiration time?

```php
function custom_rating_cookie_expiration() {
	return strtotime( 'tomorrow' ) ;
}
add_filter( 'wp_postratings_cookie_expiration', 'custom_rating_cookie_expiration', 10, 0 );
```

The default cookie expiration if 'time() + 30000000', if you want to change the lenght of the experation, you need to make use of the `wp_postratings_cookie_expiration` filter as shown in the sample code above.

### How Does WP-PostRatings Load CSS?
* WP-PostRatings will load `postratings-css.css` from your theme's CSS directory if it exists.
* If it doesn't exists, it will just load the default 'postratings-css.css' that comes with WP-PostRatings.
* This will allow you to upgrade WP-PostRatings without worrying about overwriting your ratings styles that you have created.

### How To Use Ratings Stats With Widgets?
1. Go to `WP-Admin -> Appearance -> Widgets`
2. The widget name is Ratings.

### To Display Lowest Rated Post

```php
<?php if (function_exists('get_lowest_rated')): ?>
	<ul>
		<?php get_lowest_rated(); ?>
	</ul>
<?php endif; ?>
```
 
* Default: get_lowest_rated('both', 0, 10)
* The value 'both' will display both the lowest rated posts and pages.
* If you want to display the lowest rated posts only, replace 'both' with 'post'.
* If you want to display the lowest rated pages only, replace 'both' with 'page'.
* The value 0 refers to the minimum votes required before the post get shown.
* The value 10 will display only the top 10 lowest rated posts/pages.

### To Display Lowest Rated Post By Tag

```php
<?php if (function_exists('get_lowest_rated_tag')): ?>
	<ul>
		<?php get_lowest_rated_tag(TAG_ID); ?>
	</ul>
<?php endif; ?>
```
 
* Default: get_lowest_rated_tag(TAG_ID, 'both', 0, 10)
* Replace TAG_ID will your tag ID. If you want it to span several categories, replace TAG_ID with array(1, 2) where 1 and 2 are your categories ID.
* The value 'both' will display both the lowest rated posts and pages.
* If you want to display the lowest rated posts only, replace 'both' with 'post'.
* If you want to display the lowest rated pages only, replace 'both' with 'page'.
* The value 0 refers to the minimum votes required before the post get shown.
* The value 10 will display only the top 10 lowest rated posts/pages.

### To Display Lowest Rated Post In A Category

```php
<?php if (function_exists('get_lowest_rated_category')): ?>
	<ul>
		<?php get_lowest_rated_category(CATEGORY_ID); ?>
	</ul>
<?php endif; ?>
```
 
* Default: get_lowest_rated_category(CATEGORY_ID, 'both', 0, 10)
* Replace CATEGORY_ID will your category ID. If you want it to span several categories, replace CATEGORY_ID with array(1, 2) where 1 and 2 are your categories ID.
* The value 'both' will display both the lowest rated posts and pages.
* If you want to display the lowest rated posts only, replace 'both' with 'post'.
* If you want to display the lowest rated pages only, replace 'both' with 'page'.
* The value 0 refers to the minimum votes required before the post get shown.
* The value 10 will display only the top 10 lowest rated posts/pages.

### To Display Highest Rated Post

```php
<?php if (function_exists('get_highest_rated')): ?>
	<ul>
		<?php get_highest_rated(); ?>
	</ul>
<?php endif; ?>
```
 
* Default: get_highest_rated('both', 0, 10)
* The value 'both' will display both the highest rated posts and pages.
* If you want to display the highest rated posts only, replace 'both' with 'post'.
* If you want to display the highest rated pages only, replace 'both' with 'page'.
* The value 0 refers to the minimum votes required before the post get shown.
* The value 10 will display only the top 10 highest rated posts/pages.

### To Display Highest Rated Post By Tag

```php
<?php if (function_exists('get_highest_rated_tag')): ?>
	<ul>
		<?php get_highest_rated_tag(TAG_ID); ?>
	</ul>
<?php endif; ?>
```
 
* Default: get_highest_rated_tag(TAG_ID, 'both', 0, 10)
* Replace TAG_ID will your tag ID. If you want it to span several categories, replace TAG_ID with array(1, 2) where 1 and 2 are your categories ID.
* The value 'both' will display both the highest rated posts and pages.
* If you want to display the highest rated posts only, replace 'both' with 'post'.
* If you want to display the highest rated pages only, replace 'both' with 'page'.
* The value 0 refers to the minimum votes required before the post get shown.
* The value 10 will display only the top 10 highest rated posts/pages.

### To Display Highest Rated Post In A Category

```php
<?php if (function_exists('get_highest_rated_category')): ?>
	<ul>
		<?php get_highest_rated_category(CATEGORY_ID); ?>
	</ul>
<?php endif; ?>
```
 
* Default: get_highest_rated_category(CATEGORY_ID, 'both', 0, 10)
* Replace CATEGORY_ID will your category ID. If you want it to span several categories, replace CATEGORY_ID with array(1, 2) where 1 and 2 are your categories ID.
* The value 'both' will display both the highest rated posts and pages.
* If you want to display the highest rated posts only, replace 'both' with 'post'.
* If you want to display the highest rated pages only, replace 'both' with 'page'.
* The value 0 refers to the minimum votes required before the post get shown.
* The value 10 will display only the top 10 highest rated posts/pages.

### To Display Highest Rated Post Within A Given Period

```php
<?php if (function_exists('get_highest_rated_range')): ?>
	<ul>
		<?php get_highest_rated_range('1 day'); ?>
	</ul>
<?php endif; ?>
```
 
* Default: get_highest_rated_range('1 day', 'both', 10)
* The value '1 day' will be the range that you want. You can use '2 days', '1 month', etc.
* The value 'both' will display both the most rated posts and pages.
* If you want to display the most rated posts only, replace 'both' with 'post'.
* If you want to display the most rated pages only, replace 'both' with 'page'.
* The value 10 will display only the top 10 most rated posts/pages.

### To Display Most Rated Post

```php
<?php if (function_exists('get_most_rated')): ?>
	<ul>
		<?php get_most_rated(); ?>
	</ul>
<?php endif; ?>
```
 
* Default: get_most_rated('both', 0, 10)
* The value 'both' will display both the most rated posts and pages.
* If you want to display the most rated posts only, replace 'both' with 'post'.
* If you want to display the most rated pages only, replace 'both' with 'page'.
* The value 0 refers to the minimum votes required before the post get shown.
* The value 10 will display only the top 10 most rated posts/pages.

### To Display Most Rated Post In A Category

```php
<?php if (function_exists('get_most_rated_category')): ?>
	<ul>
		<?php get_most_rated_category(CATEGORY_ID); ?>
	</ul>
<?php endif; ?>
```
 
* Default: get_most_rated_category(CATEGORY_ID, 'both', 0, 10)
* Replace CATEGORY_ID will your category ID. If you want it to span several categories, replace CATEGORY_ID with array(1, 2) where 1 and 2 are your categories ID.
* The value 'both' will display both the most rated posts and pages.
* If you want to display the most rated posts only, replace 'both' with 'post'.
* If you want to display the most rated pages only, replace 'both' with 'page'.
* The value 0 refers to the minimum votes required before the post get shown.
* The value 10 will display only the top 10 most rated posts/pages.

### To Display Most Rated Post Within A Given Period

```php
<?php if (function_exists('get_most_rated_range')): ?>
	<ul>
		<?php get_most_rated_range('1 day'); ?>
	</ul>
<?php endif; ?>
```
 
* Default: get_most_rated_range('1 day', 'both', 10)
* The value '1 day' will be the range that you want. You can use '2 days', '1 month', etc.
* The value 'both' will display both the most rated posts and pages.
* If you want to display the most rated posts only, replace 'both' with 'post'.
* If you want to display the most rated pages only, replace 'both' with 'page'.
* The value 10 will display only the top 10 most rated posts/pages.

### To Display Highest Score Post

```php
<?php if (function_exists('get_highest_score')): ?>
	<ul>
		<?php get_highest_score(); ?>
	</ul>
<?php endif; ?>
```
 
* Default: get_highest_score('both', 0, 10)
* The value 'both' will display both the most rated posts and pages.
* If you want to display the most rated posts only, replace 'both' with 'post'.
* If you want to display the most rated pages only, replace 'both' with 'page'.
* The value 0 refers to the minimum votes required before the post get shown.
* The value 10 will display only the top 10 most rated posts/pages.

### To Display Highest Score Post In A Category

```php
<?php if (function_exists('get_highest_score_category')): ?>
	<ul>
		<?php get_highest_score_category(CATEGORY_ID); ?>
	</ul>
<?php endif; ?>
```
 
* Default: get_highest_score_category(CATEGORY_ID, 'both', 0, 10)
* Replace CATEGORY_ID will your category ID. If you want it to span several categories, replace CATEGORY_ID with array(1, 2) where 1 and 2 are your categories ID.
* The value 'both' will display both the most rated posts and pages.
* If you want to display the most rated posts only, replace 'both' with 'post'.
* If you want to display the most rated pages only, replace 'both' with 'page'.
* The value 0 refers to the minimum votes required before the post get shown.
* The value 10 will display only the top 10 most rated posts/pages.


### To Display Highest Score Post Within A Given Period

```php
<?php if (function_exists('get_highest_score_range')): ?>
	<ul>
		<?php get_highest_score_range('1 day'); ?>
	</ul>
<?php endif; ?>
```
 
* Default: get_highest_score_range('1 day', 'both', 10)
* The value '1 day' will be the range that you want. You can use '2 days', '1 month', etc.
* The value 'both' will display both the most rated posts and pages.
* If you want to display the most rated posts only, replace 'both' with 'post'.
* If you want to display the most rated pages only, replace 'both' with 'page'.
* The value 10 will display only the top 10 most rated posts/pages.

### To Sort Highest/Lowest Rated Posts
* You can use: `<?php query_posts( array( 'meta_key' => 'ratings_average', 'orderby' => 'meta_value_num', 'order' => 'DESC' ) ); ?>`
* Or pass in the variables to the URL: `https://yoursite.com/?r_sortby=highest_rated&amp;r_orderby=desc`
* You can replace desc with asc if you want the lowest rated posts.

### To Sort Most/Least Rated Posts
* You can use: `<?php query_posts( array( 'meta_key' => 'ratings_users', 'orderby' => 'meta_value_num', 'order' => 'DESC' ) ); ?>`
* Or pass in the variables to the URL: `https://yoursite.com/?r_sortby=most_rated&amp;r_orderby=desc`
* You can replace desc with asc if you want the least rated posts.
