# WP-PostRatings
Contributors: GamerZ  
Donate link: https://lesterchan.net/site/donation/  
Tags: ratings, rating, vote, ajax, post  
Requires at least: 6.8  
Tested up to: 7.0  
Stable tag: 2.0.0  
Requires PHP: 8.2  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds an AJAX rating system for your WordPress site's content.

## Description

WP-PostRatings adds a rating control to any post, page or custom post type. Visitors pick a value, the vote is posted in the background, and the result appears in its place without a page load.

### Features

* Nine rating shapes, drawn with CSS rather than shipped as images, so they stay sharp at any size and cost no HTTP requests.
* A scale, or a two-way up/down control, with the rated and unrated colours as settings.
* Template tags and a shortcode for the highest rated, most rated, lowest rated and highest scoring posts, optionally within a category, a tag or a time range.
* A rating log with sortable columns, filters, bulk delete and Screen Options.
* Google rich snippet markup for the post and its aggregate rating.
* A sidebar widget, and a section on the WP-Stats page when that plugin is installed.

### Donations
I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.

## Usage

The simplest way, and the only one that works in a block theme without editing template files, is the shortcode. Put it in the post or page you want rated:

* `[ratings]` rates the post it appears in.
* `[ratings id="1"]` shows the rating for post 1, wherever you put it.
* `[ratings id="1" results="true"]` shows post 1's result without letting anyone vote from there.

To put ratings on every post automatically, a classic theme can call the template tag from `single.php`, `archive.php` or `index.php`, anywhere inside the loop:

```php
<?php the_ratings(); ?>
```

The settings live at **WP-Admin -> Ratings -> Settings**, which has a Settings tab and a Templates tab.

## Frequently Asked Questions

### How do I change the colour of the ratings?

Set them on the Ratings Options screen, under **Ratings Colour**. There is one colour for a rated shape and one for an unrated one, which is what the old `stars_crystal` and `stars_dark` image sets were for.

Hovering uses the rated colour. If you would rather tell "about to pick" apart from "already recorded", set `--wp-postratings-color-hover` in your theme.

### How do I change the size, spacing or hover colour?

Anything beyond the two colours is a CSS custom property on `.wp-postratings`, so this is all it takes in your theme:

```css
.wp-postratings {
	--wp-postratings-size: 24px;
	--wp-postratings-gap: 4px;
	--wp-postratings-color-hover: #f7c56b;
}
```

Before 2.0.0 a different colour meant a whole extra folder of images, which is why the plugin shipped `stars`, `stars_crystal` and `stars_dark` separately. They are all the same shape now.

### How do I use my own rating shape?

Register it with the `wp_postratings_shapes` filter. It then appears on the Ratings Options screen like any built-in shape, and unlike the old approach of dropping a folder into `wp-content/plugins/wp-postratings/images/`, it survives an update.

```php
add_filter( 'wp_postratings_shapes', function ( $shapes ) {
	$shapes['diamond'] = array(
		'type'  => 'scale',
		'label' => 'Diamonds',
		'path'  => 'M12 2l10 10-10 10L2 12z',
	);

	return $shapes;
} );
```

The path is SVG path data drawn in a 24x24 box. For an up/down control use `'type' => 'updown'` and supply `'up'` and `'down'` paths instead of `'path'`.


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

### How To Change The Text Screen Readers Announce?

```php
add_filter( 'wp_postratings_ratings_image_alt', function ( $label ) {
	return $label;
} );
```

This is the description a screen reader reads out for a displayed rating, such as "4 votes, average: 3.70 out of 5". Since 2.0.0 the ratings are drawn with CSS rather than images, so there is no `alt` or `title` attribute involved; the value becomes the `aria-label` on the rating.

Returning an empty string removes it, which is what this filter was usually used for. Be aware that it leaves the rating with no accessible name at all, so anyone using a screen reader is told nothing about it. Prefer rewording it over removing it.

### How To Display Comment Author Ratings?

```php
add_filter( 'wp_postratings_display_comment_author_ratings', '__return_true' );
```

By default, the comment author ratings are not displayed. If you want to display the ratings, you need to make use of the `wp_postratings_display_comment_author_ratings` filter as shown in the sample code above.

### How To change the cookie expiration time?

```php
function custom_rating_cookie_expiration() {
	return strtotime( 'tomorrow' ) ;
}
add_filter( 'wp_postratings_cookie_expiration', 'custom_rating_cookie_expiration', 10, 0 );
```

The default cookie expiration if 'time() + 30000000', if you want to change the lenght of the experation, you need to make use of the `wp_postratings_cookie_expiration` filter as shown in the sample code above.

### How Does WP-PostRatings Load CSS?

Most restyling needs no CSS file at all any more: the colours are a setting, and the size, spacing and hover colour are custom properties. See the two questions above first.

If you do want to replace the stylesheet wholesale, WP-PostRatings loads `wp-postratings.css` from your theme's directory (or its `css/` subdirectory) when that file exists, and its own copy otherwise, so an upgrade will not overwrite your styles.

Note the filename changed in 2.0.0: it was `postratings-css.css` before. A theme still shipping the old name is simply ignored, and the plugin's own stylesheet loads instead. There is no separate RTL stylesheet any more either, because the rules use logical properties.

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

```php
$query = new WP_Query( array(
	'meta_key' => 'ratings_average',
	'orderby'  => 'meta_value_num',
	'order'    => 'DESC',
) );
```

Or pass the variables in the URL: `https://yoursite.com/?r_sortby=highest_rated&r_orderby=desc`

Replace `desc` with `asc` for the lowest rated posts.

### To Sort Most/Least Rated Posts

```php
$query = new WP_Query( array(
	'meta_key' => 'ratings_users',
	'orderby'  => 'meta_value_num',
	'order'    => 'DESC',
) );
```

Or pass the variables in the URL: `https://yoursite.com/?r_sortby=most_rated&r_orderby=desc`

Replace `desc` with `asc` for the least rated posts.

These examples use `WP_Query` rather than `query_posts()`, which the old ones called: `query_posts()` replaces the main query and WordPress has discouraged it for years. To sort the main query instead, use `pre_get_posts`.

## Screenshots

1. Admin - Manage Ratings
2. Admin - Ratings Settings
3. Admin - Ratings Templates
4. Ratings
5. Ratings Hover

## Changelog
### 2.0.0
* BREAKING: Requires WordPress 6.8 and PHP 8.2, up from 6.0 and 7.4. A site on an older stack simply will not be offered the update.
* BREAKING: The rating images are gone. All 16 image sets and their 121 GIF and PNG files are replaced by 9 SVG shapes drawn with CSS, so ratings are sharp on every screen and cost no HTTP requests. Your chosen set is migrated automatically to the matching shape: stars, stars_crystal, stars_dark, stars_png and stars_flat_png all become `star`, thumbs becomes `thumb`, and so on. If you added your own folder to `images/` it will fall back to stars; see the FAQ for how to register a custom shape properly, which unlike the old folder survives an update.
* BREAKING: The rating markup has changed completely. A scale is now a group of radio buttons and an up/down is a pair of buttons, so the control announces itself correctly to screen readers and works from the keyboard. Every class and element id is now prefixed with the plugin slug: `.post-ratings` is `.wp-postratings`, `.post-ratings-image` no longer exists at all, and the wrapper id is `wp-postratings-123` rather than `post-ratings-123`. Colour, size and spacing are CSS custom properties, which is usually a one-line replacement; see the FAQ.
* BREAKING: The vote images no longer carry inline `onmouseover`/`onclick` attributes; hovering and clicking are handled by one delegated listener. Custom CSS or JavaScript that targeted those inline handlers, or that called `current_rating()` or `rate_post()` directly, needs updating.
* BREAKING: The `rate_post` action is renamed `wp_postratings_rate_post` and the old name is gone, with no deprecation shim. Its three arguments are unchanged.
* BREAKING: Every class is renamed to `WP_PostRatings_*`, and the plugin's own classes are no longer called `Postratings_*`. `RATINGS_IMG_EXT` is now `WP_POSTRATINGS_IMG_EXT`.
* BREAKING: The settings screen moved to `WP-Admin -> Ratings -> Settings`, at `admin.php?page=wp-postratings-settings`. Ratings Options and Ratings Templates are the two tabs of that one page rather than two menu entries.
* BREAKING: The option rows are renamed. The fifteen `postratings_*` rows become one `wp_postratings_options`, and the two separate version markers become one `wp_postratings_version`. Your settings are migrated automatically on the first load after the update.
* BREAKING: The shared, unprefixed `stats_display` and `stats_mostlimit` option rows are no longer read. WP-Stats integration is now two settings of this plugin's own, on the Settings tab, and WP-Stats asks each plugin for its section through the `wp_stats_sections` filter rather than reading anybody's options. Update all seven WP-Stats-aware plugins together; see the Upgrade Notice.
* BREAKING: If you set "Header That Contains The IP" (for Cloudflare, a load balancer or any reverse proxy), that header is now parsed as the forwarded-for chain it is, and only the first valid address in it is used. Previously the whole header value was stored, so a visitor could rate repeatedly just by appending another address to it. Existing rating logs recorded through such a header will no longer match, so some visitors may be able to rate once more. Leave the field blank unless you are actually behind a proxy. See the FAQ.
* BREAKING: "Ratings Logging Method" is now "Check For Repeat Votes", and it no longer decides whether anything is logged. It never did anything but choose what a returning visitor is matched against, and the two readings pointed opposite ways: a site that picked "Do Not Log" or "Logged By Cookie" got no ratings log, no rows on the logs screen and no WP-Stats figures, which is not what either choice sounds like. **Every vote is now recorded whatever this is set to.** The choices read "Do Not Check", "Check By Cookie", "Check By IP Address", "Check By Cookie And IP Address" and "Check By Username"; the stored numbers are unchanged, so your setting means what it always meant. The option key inside `wp_postratings_options` is `check_method` rather than `logging_method`, migrated automatically.
* BREAKING: The `wp_postratings_always_log` filter is gone. It asked for exactly the behaviour above, which is now the default, and a filter that forces the default does nothing. Sites that would rather *not* keep the record have the opposite filter instead: return false from `wp_postratings_log_rating` and no row is written. The post's own score and vote count are post meta and are updated either way.
* BREAKING: The stylesheet is now `wp-postratings.css`, not `postratings-css.css`, and there is no separate RTL stylesheet. If your theme ships its own `postratings-css.css` override it will no longer be picked up; rename it, or better, use the colour setting and the CSS custom properties instead.
* NEW: Rewritten as classes under `includes/`, so the plugin now works installed under any directory name.
* NEW: Settings moved to the WordPress Settings API, with the options and templates screens merged into one tabbed page.
* NEW: The rating log is now a standard WordPress list table, with sortable columns, bulk delete, a search box and Screen Options.
* NEW: The scripts are vanilla JavaScript; the jQuery dependency is gone, and hovering the stars is pure CSS, so nothing runs on mouse move at all.
* NEW: Rating colours are a setting, per rating rather than per site: each row of the rating table has its own rated and not-rated colour, with a Reset to default beside them. This replaces the old colour variants of the image sets, where changing colour meant choosing a different set of files.
* NEW: `wp_postratings_shapes` lets you register your own rating shape.
* NEW: `WP_POSTRATINGS_TRUST_PROXY` and the `wp_postratings_trust_proxy` filter opt into the usual reverse-proxy headers without naming one. "Header That Contains The IP" stays the narrow answer -- exactly the one header your proxy sets -- and still wins when both are given; the constant is the broad one, meaning "try the seven headers proxies commonly use". WP-Email, WP-Polls, WP-Ban and WP-UserOnline already worked this way; WP-PostRatings was the one that did not.
* NEW: `wp_postratings_capability` filters the capability the plugin's screens require, which is still `manage_ratings`.
* NEW: The stylesheet honours `prefers-color-scheme: dark`, `prefers-contrast: more` and `prefers-reduced-motion: reduce`.
* CHANGED: Ratings display a true percentage instead of being rounded to the nearest half image, so an average of 3.7 out of 5 now fills 74% of the stars rather than showing three and a half.
* CHANGED: `RATINGS_IMG_EXT` and `wp_postratings_image_extension` no longer choose anything; there are no image files left to have an extension. The `wp_postratings_ratings_image_alt` filter stays, but it now sets the rating's accessible label rather than an image's alt and title text.
* FIXED: The plugin requested an admin stylesheet that had been an empty file since 2020.
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
* NEW: Removed passed by reference for `get_post()`

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
* FIXED: Change htmlspecialchars to esc_attr(). Props Ryan Satterfield.
* FIXED: Change esc_attr() to wp_kses() For itemprop. Props oneTarek.

### 1.74
* FIXED: check_rated_username() should be using $user_ID. Props Artem Gordinsky.

### 1.73
* NEW: Add Stars Flat (PNG) Icons. Props hebaf.
* CHANGED: Change Schema From http://schema.org/Product To http://schema.org/Article

## Upgrade Notice

### 2.0.0. This is a major release and it changes things you may have customised. Read this before updating from 1.91.3.

**Your server has to be new enough.** WP-PostRatings now needs WordPress 6.8 and PHP 8.2. If your site is older than that, WordPress will not offer you the update at all. Ask your host to move you to a current PHP before updating.

**Your settings move themselves.** The first page load after the update folds the fifteen `postratings_*` option rows, plus the two shared `stats_display` and `stats_mostlimit` rows, into one `wp_postratings_options` row, and replaces `postratings_db_version` and `postratings_options_version` with a single `wp_postratings_version`. You do not have to do anything, but if you have code that reads those old rows directly, it will find them gone.

**If you use WP-Stats, update all seven plugins together.** WP-Stats, WP-PostRatings, WP-Polls, WP-EMail, WP-PostViews, WP-DownloadManager and WP-DraftsForFriends all used to share two unprefixed option rows, `stats_display` and `stats_mostlimit`. Each of them now keeps its own copy and deletes the shared rows once it has read them, so whichever you update first takes them away from the rest. Every plugin treats a missing row as "show my block" rather than "hide it", so nothing disappears — but a block you had deliberately switched off may come back. Switch it off again under **Ratings -> Settings -> WP-Stats**, where "Show a ratings section on the stats page" and the entries-per-list figure now live.

**The settings screen moved.** It is at **Ratings -> Settings** now, and what used to be two menu entries — Ratings Options and Ratings Templates — are two tabs of that one page. Any bookmark to `admin.php?page=wp-postratings-options` should become `admin.php?page=wp-postratings-settings`. The capability is unchanged: it is still `manage_ratings`.

**Google rich snippets are off, and now have a type to choose.** The two settings — "Enable Google Rich Snippets?" and "Enable Ratings In Rich Snippets?" — are one setting, **Show ratings in Google results?**, and it starts at **No**.

They had become the same decision, and the markup they produced could not do what it promised. It declared `schema.org/Article`, and Google shows a rating only for Book, Course, Event, Local Business, Movie, Organization, Product, Recipe, Software App and a few subtypes. Article, BlogPosting and NewsArticle are not on that list, so on an ordinary post it never produced a rich result at all. Nothing that worked is being taken away.

**Unless you filtered `wp_postratings_schema_itemtype`.** If you used that filter to declare a supported type — a recipe blog pointing it at `Recipe`, a review site at `Product` — you *were* getting a real rich result, and it stops until you pick that same type in the new setting. The filter still runs and still has the last word, so an existing filter keeps working as long as the setting is not left at No.

Pick a type only if your posts genuinely are that thing. Structured data has to describe the page, and marking a blog post as a `Product` to collect stars is what Google calls spammy structured markup — it costs a manual action, not a ranking.

**Your rating images are replaced by shapes.** The 16 image sets are now 9 shapes drawn with CSS. Whichever set you had chosen is mapped to the matching shape automatically. If you had added your own folder of images, it falls back to stars — the FAQ shows how to register a shape instead, which survives updates.

**Custom CSS almost certainly needs updating.** Every class and id now starts with the plugin slug:

* `.post-ratings` is now `.wp-postratings`
* `.post-ratings-image` is gone entirely; there is no `<img>` any more
* the wrapper id is `wp-postratings-123`, not `post-ratings-123`
* the stylesheet is `css/wp-postratings.css`, not `postratings-css.css`, and there is no `-rtl` variant

Colour, size, spacing and hover colour are CSS custom properties now, so most old overrides become a one-line change. See the FAQ.

**Custom PHP may need updating.** Two things were renamed with no shim left behind:

* the `rate_post` action is now `wp_postratings_rate_post`, with the same three arguments
* the `RATINGS_IMG_EXT` constant is now `WP_POSTRATINGS_IMG_EXT`

Every class was also renamed from `Postratings_*` to `WP_PostRatings_*`. The template tags — `the_ratings()`, `get_highest_rated()`, `get_most_rated()` and the rest — are unchanged, and so are the twenty-two filters that were already prefixed `wp_postratings_`.

**Check the IP header setting.** If "Header That Contains The IP" is filled in, the plugin now reads only the first valid address in it rather than storing the whole chain. Rating logs recorded through that header before the update will no longer match, so a few visitors may be able to rate once more. If you are not behind Cloudflare, a load balancer or another reverse proxy, clear the field.

**Custom JavaScript.** The rating markup carries no inline `onmouseover` or `onclick` handlers any more, and there is no jQuery. Anything calling `current_rating()` or `rate_post()` in the browser needs rewriting against the delegated listener in `js/wp-postratings.js`.
