/**
 * Shared helpers for the script tests.
 */
import { readFileSync } from 'node:fs';

/**
 * Evaluate one of the plugin's scripts in the current jsdom page.
 *
 * The script is an IIFE with no exports that attaches delegated listeners to
 * document, so it is loaded the way a browser would rather than imported.
 *
 * The l10n object has to exist on window *before* this runs: the IIFE reads it
 * as it evaluates. Evaluate once per test file -- a second evaluation adds a
 * second set of listeners and every handler then fires twice.
 *
 * @param {string} name Path relative to the plugin root.
 */
export function loadScript( name ) {
	const src = readFileSync( new URL( '../../' + name, import.meta.url ), 'utf8' );

	new Function( src )();
}

/**
 * The localisation object the front end script reads.
 *
 * Much smaller since 2.0.0: hovering is CSS, so the script no longer needs the
 * image set, the extension, the scale or the plugin URL. Values arrive from
 * wp_localize_script() as strings, which is why the script coerces them.
 *
 * @return {Object} l10n object.
 */
export function l10nFixture() {
	return {
		ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
		textWait: 'Please rate only 1 item at a time.',
		showLoading: '1',
		showFading: '1',
		restUrl: 'https://example.com/wp-json/postratings/v1/posts',
		// '' rather than false, because wp_localize_script() casts every
		// scalar it is given to a string. Off unless a test asks for it.
		refresh: '',
	};
}

/**
 * The markup the PHP side emits for a post this visitor has already rated.
 *
 * No control and no data-nonce: there is nothing left for them to vote with.
 *
 * @param {number} postId Post id.
 * @return {string} Markup.
 */
export function resultsMarkup( postId = 4 ) {
	return (
		'<span id="wp-postratings-' + postId + '" class="wp-postratings">' +
		'<span class="wp-postratings-strip"></span> (<em>4 votes</em>)' +
		'</span>'
	);
}

/**
 * The markup the PHP side emits for an unrated post on a scale.
 *
 * A span carrying role="radiogroup" rather than a fieldset: the [ratings]
 * shortcode wraps its output in a span, and the parser hoists any flow element
 * back out of one.
 *
 * @param {number} postId Post id.
 * @param {number} max    Points on the scale.
 * @return {string} Markup.
 */
export function voteMarkup( postId = 4, max = 5 ) {
	let values = '';

	// Emitted highest first, the way the CSS sibling combinator needs.
	for ( let i = max; i >= 1; i-- ) {
		const id = 'wp-postratings-' + postId + '-' + i;
		values +=
			'<input type="radio" id="' + id + '" name="wp-postratings-' + postId + '"' +
			' value="' + i + '" data-rating="' + i + '" />' +
			'<label for="' + id + '"><i class="wp-postratings-item"></i><span>' + i + ' Stars</span></label>';
	}

	return (
		'<span id="wp-postratings-' + postId + '" class="wp-postratings" data-nonce="abc123">' +
		'<span class="wp-postratings-vote wp-postratings-scale" data-post-id="' + postId + '"' +
		' role="radiogroup" aria-label="Rate this post">' + values + '</span>' +
		'</span>' +
		'<span id="wp-postratings-' + postId + '-loading" class="wp-postratings-loading" hidden>Loading…</span>'
	);
}

/**
 * The markup the PHP side emits for an up/down control.
 *
 * @param {number} postId Post id.
 * @return {string} Markup.
 */
export function updownMarkup( postId = 4 ) {
	return (
		'<span id="wp-postratings-' + postId + '" class="wp-postratings" data-nonce="abc123">' +
		'<span class="wp-postratings-vote wp-postratings-updown" data-post-id="' + postId + '"' +
		' role="group" aria-label="Vote on this post">' +
		'<button type="button" class="wp-postratings-up" data-rating="2">' +
		'<i class="wp-postratings-item"></i><span>Vote Up</span></button>' +
		'<button type="button" class="wp-postratings-down" data-rating="1">' +
		'<i class="wp-postratings-item"></i><span>Vote Down</span></button>' +
		'</span></span>'
	);
}
