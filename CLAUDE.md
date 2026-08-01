# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

WP-PostRatings follows `_standards/STANDARDS.md` in the parent folder, which is
the contract for all nineteen plugins in the collection. Where this file and
that one disagree, that one wins.

## What it is

Lets visitors rate a post — a scale of stars (or hearts, thumbs, …) or an
up/down pair — and renders the result. Template tags, a widget, an admin Ratings
column, comment author ratings, "highest rated" / "most rated" listings, Google
rich-snippet markup, and a WP-Stats section. Menu: **Settings** first, then
**Logs**.

At ~7,300 lines of `includes/` it is the largest plugin in the collection.

## Data

* Ratings live in **post meta**: `ratings_users`, `ratings_score`,
  `ratings_average`, `ratings_max`, `ratings_users_ip`. Unprefixed, and they stay
  that way — every existing site's data is under those keys.
* A **vote log table**, for spotting abuse. That is what the Logs screen is.
* `wp_postratings_options` folds in fifteen `postratings_*` rows;
  `wp_postratings_version` replaces **two** markers, `postratings_db_version` and
  `postratings_options_version`.
* One of the seven WP-Stats plugins (§13).

**Settings comes before Logs in the menu, and §4.1 uses this plugin as the
example.** The log is a record for spotting abuse, not a workspace, so the menu
opens on Settings and the second entry is called *Logs* rather than "Manage"
anything. wp-email's log is the same shape and follows the same rule.

## The shared-row arrangement — this plugin is the reference

`WP_PostRatings_Options::$shared_options`
(`includes/class-wp-postratings-options.php:73-89`) carries the docblock that
explains it, and `_standards/RESUME.md` points three other plugins here:

> the migration folds these in and deletes them — and, unlike the rows above,
> they are deliberately absent from `all_option_names()`, because uninstalling
> one plugin must not clear a setting that a sibling which has not upgraded yet
> is still reading.

wp-polls and wp-downloadmanager currently delete the shared rows on uninstall and
wp-useronline has a related issue. **Do not copy their arrangement into this
one.**

## Traps

* **Record the admin hook suffix; never spell it out and never derive it.**
  §4.1 records that this plugin lost its shape picker to that **twice in one
  day** — every shape is a CSS mask from the admin stylesheet, so the screen
  became a list of labels. First `'ratings_page_'` was hardcoded and renaming the
  menu moved the real hook; then it was *derived from the menu title*, which
  survived the rename and broke when Settings became the top-level entry, because
  a top-level page's hook is `toplevel_page_{{SLUG}}` and owes nothing to the
  title. `WP_PostRatings_Admin::$screen_hooks[]` collects what
  `add_menu_page()` / `add_submenu_page()` hand back. Assert against
  `get_plugin_page_hookname()` for the slugs actually in `$submenu`, never
  against a hand-built string.
* **`settings_errors()` is called in two different ways and both are correct.**
  The Settings screen (under a top-level menu) must call it itself — core only
  calls it for `options-general.php` children. The Logs screen calls
  `settings_errors( self::PAGE )`, and a transient carries the queue across the
  redirect. Getting this wrong prints every notice twice or none at all; carry a
  test asserting "the notice appears exactly once".
* **`WP_PostRatings_Shapes::all()` is the single enumeration of shapes.** The
  settings picker, the sanitizer's allow-list and the stylesheet all read from
  it, so a shape registered through the filter is immediately selectable *and*
  savable. Deriving them separately is how a plugin ends up offering a choice its
  own sanitizer rejects.
* **Shapes are CSS masks, not images.** Before 2.0.0 the plugin shipped 121 GIF
  and PNG files across 16 folders, ten of which were the same handful of shapes
  in different colours — a job for a custom property. The markup carries no
  `<img>` at all and the colour is whatever `background-color` the theme sets.
  All paths are drawn in a 24×24 viewBox so one set of sizing rules covers them.
  A custom image folder falls back to stars; registering a shape through the
  filter is the replacement, and it survives an update.
* **Rich snippets default to No, and that is a correctness fix.** The old markup
  declared `schema.org/Article`, and Google shows ratings only for Book, Course,
  Event, Local Business, Movie, Organization, Product, Recipe, Software App and a
  few subtypes — so on an ordinary post it never produced a rich result.
  `wp_postratings_schema_itemtype` still has the last word. Marking a blog post
  as a `Product` to collect stars is spammy structured markup and costs a manual
  action.
* **`setcookie()` is guarded by `headers_sent()`** — wp-polls copied this.
* **Only the first valid address in the trusted proxy header is read.** Logs
  recorded through the old whole-chain behaviour no longer match, so a few
  visitors can rate once more; the Upgrade Notice says to clear the field unless
  genuinely behind a proxy. wp-polls and wp-postratings carry the canonical
  "Header That Contains The IP" label and description that the other three
  proxy-header plugins are being brought to (task #20).
* **Every class and id is slug-prefixed now** — `.post-ratings` →
  `.wp-postratings`, `post-ratings-123` → `wp-postratings-123`,
  `postratings-css.css` → `css/wp-postratings.css`, no `-rtl` variant,
  `.post-ratings-image` gone entirely. Custom CSS breaks; the FAQ documents the
  custom properties that replace most overrides.
* **`rate_post` → `wp_postratings_rate_post`, `RATINGS_IMG_EXT` →
  `WP_POSTRATINGS_IMG_EXT`**, no shims. The twenty-two `wp_postratings_*` filters
  and every template tag are unchanged.
* **No inline `onmouseover`/`onclick` and no jQuery.** Browser code calling
  `current_rating()` or `rate_post()` must be rewritten against the delegated
  listener in `js/wp-postratings.js`.
* Repeat-vote checking is separate from whether votes are logged (commit
  `4c52e74`), matching wp-polls.
* **The sanitiser merges the submitted subset over the stored value.** §4.2.2
  names wp-postratings and wp-polls as the two to copy rather than re-deriving:
  `register_setting()`'s callback is handed only the fields the submitting form
  posted, so a sanitiser that returns just what it was given wipes the other
  tab's values silently.

## Tests

The e2e suite is the largest and the only one of the six early ones green from
the start (52 tests). It found the missing `settings_errors()` call on the
settings screen — the same bug that later turned up in wp-useronline.

`test-shapes.php` covers the enumeration/allow-list agreement; `test-upgrade.php`
the two-marker fold-in; `test-lock.php` and `test-vote.php` the concurrency and
eligibility paths.
