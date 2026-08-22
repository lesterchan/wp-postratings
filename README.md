# WP-PostRatings
Contributors: GamerZ  
Donate link: https://lesterchan.net/site/donation/  
Tags: ratings, rating, vote, ajax, post  
Requires at least: 6.8  
Tested up to: 7.1  
Stable tag: 2.0.1  
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

## Installation

1. Install and activate the plugin.
1. Go to `WP-Admin -> Ratings -> Settings` and choose the shape, the scale and who is allowed to rate.
1. Show the control: type `[ratings]` into a post, add the **Ratings** block, or call `the_ratings()` from your theme.

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

### Showing A Rating In A Block

A **Ratings** block is available in the editor, under **Widgets**. Two settings in the sidebar:

* **Post ID** — leave it at zero to rate the post the block sits in, which is what an empty `[ratings]` does, or give an id to show another post's rating wherever you put the block.
* **Show the result only** — displays the rating without offering to change it, for putting a post's score somewhere it should not be voted from. This is what `results="true"` does.

It renders on the server, so the preview in the editor is the real rating rather than an approximation, and changing your shape, colours or templates updates every post showing it without re-saving anything. The preview is deliberately not clickable: rating from the editor would record a real vote.

**The shortcode still works and is not going anywhere.** `[ratings]`, `[ratings id="1"]` and `[ratings id="1" results="true"]` behave exactly as they always have, and a post already containing one needs no change. The block calls the same code the shortcode calls, so the two render identically — use whichever suits the post.

### WP-CLI
```
wp postratings list
wp postratings list --limit=50 --format=json
wp postratings get 42
wp postratings delete 42 --yes
wp postratings delete --all --what=logs
```

A rating lives in two places — the running totals each post displays, and a row per vote in the log. `--what` chooses which to clear: `logs`, `data` or `both`, and `both` is the default.

### REST API
```
GET  /wp-json/postratings/v1/post/<id>
POST /wp-json/postratings/v1/post/<id>/rate
```

Reading is public, because a rating is public. Rating takes the same `wp_postratings_<id>-nonce` the rendered control already carries, passed as a `nonce` parameter, and is subject to the same eligibility and repeat-rating settings as rating through the page.

Each response carries the rendered markup as well as the numbers, because your templates and your chosen shape decide what a rating looks like.

**A refusal answers 403**, not 400 — a rating already cast, a rating off the end of the scale, a bad nonce. 400 is kept for a parameter this plugin never had a chance to look at. A post that does not exist answers 404, and so does one you are not allowed to rate — a draft belonging to somebody else answers exactly as a deleted post does, rather than confirming it is there.

**These routes are an addition.** The `admin-ajax.php` `wp_postratings` action is unchanged and still supported.

## Frequently Asked Questions

### How do I change the colour of the ratings?

Go to `WP-Admin -> Ratings -> Settings` and look under **Individual Rating Text and Value**. Each step on the scale has its own pair of colour swatches, in the **Rated** and **Not rated** columns, which is what the old `stars_crystal` and `stars_dark` image sets were for.

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

Register it with the `wp_postratings_shapes` filter. It then appears on the Ratings Settings screen like any built-in shape, and unlike the old approach of dropping a folder into `wp-content/plugins/wp-postratings/images/`, it survives an update.

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

This is about the **Header That Contains The IP** field on the Ratings Settings screen.

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

1. Ratings -> Settings: the shape, the scale, who may rate, and how a repeat is spotted
2. The Templates tab, holding the markup of the control and of every result
3. Ratings -> Logs, every vote with who cast it, filterable by user and by rating
4. The control in a post with a star under the pointer, and the same rating read-only below
5. The Ratings block in the editor, previewing the control for the post it is pointed at, with the sidebar choosing that post and whether the rating can be cast or only read

## Changelog
### 2.0.1
* FIXED: The comment author ratings display, which is off unless a theme opts in, still cost its query: every page with a loop fetched every rating the displayed post had ever received, then threw them away. Sites that have not opted in no longer pay it — one query fewer on every page, and on a heavily rated post it was not a small one.* FIXED: Nothing unpublished could be rated by anybody, including the people who wrote it. 2.0.0 required a post to be publicly viewable before it would accept a rating — which stops a stranger rating your drafts, and was the point — but it never asked who was rating, so a site whose editors rate their own drafts, pending or private posts got `Invalid Post ID` on every vote. A post you can already read is now ratable whether or not the public can see it, and the message no longer blames the post ID, which is rarely what is wrong with it.
* NEW: `wp_postratings_is_ratable` filters whether a given post may be rated at all, for sites whose answer is neither of the two above.
* CHANGED: The REST error code for a post that may not be rated is `wp_postratings_not_ratable` rather than `wp_postratings_no_such_post`. The status is still 404, deliberately: a 403 would tell a stranger that the draft exists.

### 2.0.0
* FIXED: Any post ID could be rated, not only a published one. A visitor who was never signed in could seed `ratings_users`, `ratings_score` and `ratings_average` onto a draft, a pending, a private or a trashed post — so it arrived already rated on the day it was published — and the unpublished title was copied into the log table, where the Logs screen then displayed it. On a site that had put `%POST_TITLE%` or `%POST_CONTENT%` into the text template, the reply returned unpublished content directly. Both the AJAX action and the REST route now require a post that is actually publicly viewable
* NEW: A `wp postratings` WP-CLI command — `list`, `get` and `delete`, with `--what=logs|data|both`.
* NEW: A `postratings/v1` REST API for reading a post's rating and casting one. The `admin-ajax.php` `wp_postratings` action is unchanged and still supported.
* NEW: A **Ratings** block for the editor, with the post id and a results-only toggle in the sidebar. It renders on the server through the same code the shortcode does, so the editor preview is the real rating. The `[ratings]` shortcode is unchanged and still supported — the block is an addition beside it, nothing needs migrating, and posts already holding a shortcode need no edit.
* BREAKING: Requires WordPress 6.8 and PHP 8.2.
* BREAKING: The rating images are gone. All 16 image sets and their 121 GIF and PNG files are replaced by 9 SVG shapes drawn with CSS, so ratings are sharp on every screen and cost no HTTP requests. Your chosen set is migrated automatically to the matching shape: stars, stars_crystal, stars_dark, stars_png and stars_flat_png all become `star`, thumbs becomes `thumb`, and so on. If you added your own folder to `images/` it will fall back to stars; see the FAQ for how to register a custom shape properly, which unlike the old folder survives an update.
* BREAKING: The rating markup has changed completely. A scale is now a group of radio buttons and an up/down is a pair of buttons, so the control announces itself correctly to screen readers and works from the keyboard. Every class and element id is now prefixed with the plugin slug: `.post-ratings` is `.wp-postratings`, `.post-ratings-image` no longer exists at all, and the wrapper id is `wp-postratings-123` rather than `post-ratings-123`. Colour, size and spacing are CSS custom properties, which is usually a one-line replacement; see the FAQ.
* BREAKING: The vote images no longer carry inline `onmouseover`/`onclick` attributes; hovering and clicking are handled by one delegated listener. Custom CSS or JavaScript that targeted those inline handlers, or that called `current_rating()` or `rate_post()` directly, needs updating.
* BREAKING: The `rate_post` action is renamed `wp_postratings_rate_post` and the old name is gone, with no deprecation shim. Its three arguments are unchanged.
* BREAKING: Every class is renamed to `WP_PostRatings_*`, and the plugin's own classes are no longer called `Postratings_*`. `RATINGS_IMG_EXT` is now `WP_POSTRATINGS_IMG_EXT`.
* BREAKING: The settings screen moved to `WP-Admin -> Ratings -> Settings`, at `admin.php?page=wp-postratings-settings`. Settings and Templates are the two tabs of that one page rather than two menu entries.
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
* NEW: When an up/down post has no verdict to show, it shows you your own vote instead. A tie points nowhere, so rating a post down and landing on one drew a blank pair that looked exactly like the control you had just clicked — as though the vote had not been recorded. Your vote is the only direction that can honestly be attributed at that point, so it takes the shape and the label reads "You rated this down". A post that *does* have a verdict keeps showing it: one that is 500 to 3 in favour must not read as a thumbs down to the single visitor who disagreed. Your vote comes from the "already rated" cookie, or from the rating log when the repeat-vote check is by username. It is deliberately never matched by IP address: behind a proxy or a mobile network many visitors share one address, so that would tell somebody who had never voted that they had
* FIXED: An up/down rating with no verdict was drawn as a vote down. Zero is not a direction: a post nobody has rated, and a post with as many down votes as up, both average zero, and both showed a lit thumbs down. They now show the pair unlit. This also fixes what a site sees after switching from a scale to an up/down control -- an up/down vote is worth -1 or +1, so a score off a scale of stars is far too large to be one, and reading it as a direction showed every previously rated post as a thumbs up that no amount of voting down could shift. Such a post now shows the unlit pair until its up/down votes outweigh its history, and the vote count and score are reported exactly as stored throughout
* FIXED: On a dark colour scheme the unrated shapes stayed the light grey chosen for a light page, and glared. The dark default is a custom property set on the container `the_ratings()` adds -- but the widget, and the shortcode and block when asked for results only, print the rating without that container, so nothing they drew ever inherited it. The same was true of the high-contrast default

## Upgrade Notice

### 2.0.0

Requires WordPress 6.8 and PHP 8.2.

**Settings migrate on the first admin page load.** The fifteen `postratings_*` rows, plus the shared `stats_display` and `stats_mostlimit` rows, are folded into one `wp_postratings_options` row, and `postratings_db_version` and `postratings_options_version` become a single `wp_postratings_version`. Code reading the old rows directly will find them gone.

**Update all seven WP-Stats plugins together.** WP-Stats, WP-PostRatings, WP-Polls, WP-EMail, WP-PostViews, WP-DownloadManager and WP-DraftsForFriends shared two unprefixed rows, `stats_display` and `stats_mostlimit`. Each now keeps its own copy and deletes the shared rows once it has read them, so whichever you update first takes them from the rest. A missing row means "show", so a block you had switched off may reappear — switch it off again under **Ratings -> Settings -> WP-Stats**, where the setting and the entries-per-list figure now live.

**The settings screen is at Ratings -> Settings**, with Settings and Templates as two tabs of it. `admin.php?page=wp-postratings-options` is now `admin.php?page=wp-postratings-settings`. The capability is still `manage_ratings`.

**Two Google rich snippet settings became one, defaulting to No.** "Enable Google Rich Snippets?" and "Enable Ratings In Rich Snippets?" are now **Show ratings in Google results?**. The old markup declared `schema.org/Article`, and Google shows ratings only for Book, Course, Event, Local Business, Movie, Organization, Product, Recipe, Software App and a few subtypes — so on an ordinary post it never produced a rich result.

If you filtered `wp_postratings_schema_itemtype` to a supported type, you *were* getting a real rich result, and it stops until you select that same type in the new setting. The filter still runs and still has the last word. Pick a type only if the content genuinely is that thing: marking a blog post as a `Product` to collect stars is spammy structured markup, and it costs a manual action.

**Rating images are now CSS shapes.** The 16 image sets are 9 shapes; whichever set you had is mapped to the matching shape automatically. A custom image folder falls back to stars — the FAQ shows how to register a shape instead, which survives updates.

**Custom CSS needs updating.** Every class and id is slug-prefixed now:

* `.post-ratings` is now `.wp-postratings`
* `.post-ratings-image` is gone; there is no `<img>` any more
* the wrapper id is `wp-postratings-123`, not `post-ratings-123`
* the stylesheet is `css/wp-postratings.css`, not `postratings-css.css`, and there is no `-rtl` variant

Colour, size, spacing and hover colour are CSS custom properties, so most overrides become a one-line change. See the FAQ.

**Renamed with no shim left behind:**

* the `rate_post` action is now `wp_postratings_rate_post`, with the same three arguments
* `RATINGS_IMG_EXT` is now `WP_POSTRATINGS_IMG_EXT`
* every class is `WP_PostRatings_*` rather than `Postratings_*`

The template tags — `the_ratings()`, `get_highest_rated()`, `get_most_rated()` and the rest — and the twenty-two `wp_postratings_*` filters are unchanged.

**"Header That Contains The IP" now reads only the first valid address** rather than storing the whole chain. Rating logs recorded through it before the update no longer match, so a few visitors may be able to rate once more. Clear the field unless you are behind a reverse proxy.

**Custom JavaScript.** The markup carries no inline `onmouseover` or `onclick` handlers and there is no jQuery. Code calling `current_rating()` or `rate_post()` in the browser must be rewritten against the delegated listener in `js/wp-postratings.js`.
