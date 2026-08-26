# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What it is

Lets visitors rate a post — a scale of stars (or hearts, thumbs, …) or an
up/down pair — and renders the result. Template tags, a widget, an admin Ratings
column, comment author ratings, "highest rated" / "most rated" listings, Google
rich-snippet markup, and a WP-Stats section. Menu: **Settings** first, then
**Logs**.

At ~7,300 lines of `includes/` it is a large plugin, and most of that is the
shape and template machinery.

## Data

* Ratings live in **post meta**: `ratings_users`, `ratings_score`,
  `ratings_average`, `ratings_max`, `ratings_users_ip`. Unprefixed, and they stay
  that way — every existing site's data is under those keys.
* A **vote log table**, for spotting abuse. That is what the Logs screen is.
* `wp_postratings_options` folds in fifteen `postratings_*` rows;
  `wp_postratings_version` replaces **two** markers, `postratings_db_version` and
  `postratings_options_version`, and holds nothing but the `plugin` and `db`
  values. Keep them out of the settings array: a marker in there has to be
  rescued from the stored value on every save, because the settings form never
  posts one.
* It contributes a section to **WP-Stats**, a separate plugin, by answering the
  `wp_stats_sections` filter.

**Settings comes before Logs in the menu, deliberately.** The log is a record
for spotting abuse, not a workspace, so the menu opens on Settings and the
second entry is called *Logs* rather than "Manage" anything.

## The shared-row arrangement

`WP_PostRatings_Options::$shared_options` carries the docblock that explains it:

> the migration folds these in and deletes them — and, unlike the rows above,
> they are deliberately absent from `all_option_names()`, because uninstalling
> one plugin must not clear a setting that a sibling which has not upgraded yet
> is still reading.

`stats_display` and `stats_mostlimit` were unprefixed rows that WP-Stats and
several companion plugins all wrote into; none of them owned the rows. So the
migration deletes them once it has folded them in, and **uninstall must not**.
Keeping the two lists apart is the whole mechanism — do not merge them for
single-source-of-truth tidiness.

## Traps

* **Record the admin hook suffix; never spell it out and never derive it.**
  This plugin lost its shape picker to that **twice in one day** — every shape is
  a CSS mask from the admin stylesheet, so the screen
  became a list of labels. First `'ratings_page_'` was hardcoded and renaming the
  menu moved the real hook; then it was *derived from the menu title*, which
  survived the rename and broke when Settings became the top-level entry, because
  a top-level page's hook is `toplevel_page_<slug>` and owes nothing to the
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
* **The numeric shape is the exception, and had to be.** Every mask shape is one
  path repeated once per point, so a set of digits could not be one: the glyph
  differs per position. `WP_PostRatings_Shapes::NUMERIC` carries no path at all
  and the position supplies the glyph, which is why `row()` is the one row that
  is not a `str_repeat()`. Two things bite. The glyph is an `<i>`, which every
  other shape leaves empty and the browser italicises, so the stylesheet resets
  `font-style`. And a numeric glyph is real text inside the `<label>`, so it
  joins the radio's accessible name unless it is `aria-hidden` — leave it
  exposed and every value announces itself twice, "3 3 Stars".
* **Never put padding on a rating bar.** The numeric shape draws cells, so the
  obvious way to space them off the ends is `padding-inline` on
  `.wp-postratings-strip` — and that silently breaks the result display, because
  `.wp-postratings-fill` is absolutely positioned and resolves against the
  padding box while the track sits in the content box. The two rows come apart
  by exactly the padding. The end caps are a border, which widens the bar the
  way the caps are meant to; the top and bottom rules are inset shadows, which
  do not, so the bar stays exactly `--wp-postratings-size` tall.
* **The shape picker groups its rows by family, not by type.** The radios above
  it offer two choices, because there are two controls: one value out of N, or a
  pair of opposing actions. A shape's type is finer than that, so grouping the
  rows by it puts a numeric shape in a group no radio names and hides it for
  good.
* **Rich snippets default to No, and that is a correctness fix.** The old markup
  declared `schema.org/Article`, and Google shows ratings only for Book, Course,
  Event, Local Business, Movie, Organization, Product, Recipe, Software App and a
  few subtypes — so on an ordinary post it never produced a rich result.
  `wp_postratings_schema_itemtype` still has the last word. Marking a blog post
  as a `Product` to collect stars is spammy structured markup and costs a manual
  action.
* **A comment reaches `comment_text` as an argument, not as a global.** Comment
  author ratings looked switched off on every block theme, for the whole of the
  time block themes have been the default: the filter read
  `$GLOBALS['comment']`, which a classic theme's comment loop sets and the
  comment template block does not — it carries the comment as block context and
  leaves the global empty. Take the comment from the filter's second argument
  (so the hook has to be registered with `10, 2`) and pass it down to
  `get_comment_type()`, `get_comment_author()` and `get_comment_author_IP()`,
  each of which otherwise falls back to that same global. A unit test cannot see
  this, because its comment loop is one the test set up by hand.
* **`setcookie()` is guarded by `headers_sent()`.**
* **Only the first valid address in the trusted proxy header is read.** Logs
  recorded through the old whole-chain behaviour no longer match, so a few
  visitors can rate once more; the Upgrade Notice says to clear the field unless
  genuinely behind a proxy.
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
  `4c52e74`).
* **The sanitiser merges the submitted subset over the stored value.**
  `register_setting()`'s callback is handed only the fields the submitting form
  posted, so a sanitiser that returns just what it was given wipes the other
  tab's values silently. This screen has two tabs; that is not hypothetical.

## The block

`wp-postratings/ratings`, registered by `WP_PostRatings_Blocks` from the metadata
`bin/build` compiles out of `src/` into `build/`. **`build/` is generated and
gitignored**, so a checkout that has never been built registers no block;
`bin/test.sh` and `bin/test-e2e.sh` build first.

**The block wraps `[ratings]` and never replaces it.** The shortcode stays
registered and supported. Both entry points meet at
`WP_PostRatings::render_ratings( $post_id, $results )` and **neither calls the
other**; the block does not run `do_shortcode()`.

**`results` is deliberately not normalised.** The shortcode has always treated
any value as "yes", so `results="0"` renders the result on live pages today.
Running it through a boolean filter would silently change them. The shared
renderer reads truthiness only, so a real boolean from the block and a string
from the shortcode agree wherever a block can express the value at all.

**The renderer is on `WP_PostRatings`, not on `WP_PostRatings_Template`.** The
composition step sits on top of the `the_ratings()` template tags, which
themselves sit on the template class -- putting it there would make the markup
builder call back up into its own public API.

**`block_editor_styles()` is load-bearing.** Every rating shape is a CSS mask on
an empty `<i class="wp-postratings-item">` sized entirely from the stylesheet,
so an editor without it previews labels with zero-width nothing beside them.
Guarded on `is_admin()` because `enqueue_block_assets` fires on the front end
too, where `styles()` has already run.

## WP-CLI and REST

`wp postratings list|get|delete`, and `postratings/v1` with two routes: read a
post's rating, cast one.

**A rating lives in two places** -- the running totals in post meta and a row per
vote in the log -- so "clear this post's ratings" is ambiguous until you say
which. `delete --what=logs|data|both` is that choice, and `WP_PostRatings_Data`
is where both halves are done, so the admin screen and the command cannot
disagree about what clearing means.

**`process_vote()` returns a string whether the rating landed or not** -- rendered
markup on success, a plain sentence on refusal, an empty string for the refusals
that say nothing. Nothing in that return distinguishes them, so the REST route
decides by watching `ratings_users` instead: a rating that counted always moves
it by one. Reading the return instead makes "outside the scale" and "already
rated" both answer 200. Do not simplify it.

**The `admin-ajax.php` `wp_postratings` action is still registered and still
supported**, with a test asserting so.

## Migrations, and why they are tested through a browser

`maybe_upgrade()` hangs off `init`, so every request reaches it — activation
hooks do not fire on a plugin update, which is the usual reason a migration
never runs at all. Fifteen unprefixed rows fold into one, two markers collapse
into one row, and two settings come out of the shared WP-Stats rows.

What a browser adds over `test-upgrade.php` is the shape question. The shape a
site chose years ago is a *folder name* that no longer exists — the sixteen
image folders became ten shapes — and whether the migration turned it into
one the settings screen can actually **draw** is a question about a page, not
about an array. `tests/e2e/upgrade.spec.js` asserts the resolved shape comes back
checked in the picker, and that an unrecognised folder lands on stars rather
than on nothing.

Three things its fixtures rely on:

* **A `wp eval` call is itself an upgrade request**, because the migration runs
  on `init` and WP-CLI boots the plugin before running anything. Seed the
  fixture and read it back inside *one* call; a second call finds the rows
  already migrated and the browser request then has nothing left to do.
* **Read rows raw** — the options accessor merges the defaults, so it cannot
  tell a written row from an absent one.
* **A scalar legacy row reads back as a string.** `postratings_max` comes out of
  `wp_options` as `"5"` whatever was written, while the two parallel ratings
  arrays keep their types.

## Tests

`bin/test.sh` runs PHPUnit, `bin/test-multisite.sh` the network pass, and
`bin/test-e2e.sh` the Playwright suite. **Run them rather than trusting a note
about their last result** — CI is the authority, and this file cannot be.

`test-shapes.php` covers the enumeration/allow-list agreement; `test-upgrade.php`
the two-marker fold-in; `test-lock.php` and `test-vote.php` the concurrency and
eligibility paths.

The e2e helpers deliberately drive the *screen* rather than the database — a
helper that wrote the option row directly would skip the sanitizer and the form,
which are two of the three things these tests exist to watch. The migration
helpers are the one exception, and they say so: fifteen rows in the shape a
release left them have no screen to go in through, because the screen that wrote
them has not existed for years.
