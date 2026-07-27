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
 * Values arrive from wp_localize_script() as strings, which is why the script
 * coerces them; the fixtures mirror that.
 *
 * @return {Object} l10n object.
 */
export function l10nFixture() {
	return {
		pluginUrl: 'https://example.com/wp-content/plugins/wp-postratings',
		ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
		textWait: 'Please rate only 1 item at a time.',
		image: 'stars',
		imageExt: 'gif',
		max: '5',
		showLoading: '1',
		showFading: '1',
		custom: '0',
	};
}

/**
 * Build the markup the PHP side emits for an unrated post.
 *
 * @param {number} postId Post id.
 * @return {string} Markup.
 */
export function voteMarkup( postId = 4 ) {
	let images = '';

	for ( let i = 1; i <= 5; i++ ) {
		images +=
			'<img src="https://example.com/wp-content/plugins/wp-postratings/images/stars/rating_off.gif"' +
			' alt="' + i + ' Stars" id="rating_' + postId + '_' + i + '"' +
			' class="post-ratings-image post-ratings-vote"' +
			' data-post-id="' + postId + '" data-rating="' + i + '"' +
			' data-rating-text="' + i + ' Stars" data-post-rating="0"' +
			' data-insert-half="0" data-half-rtl="0" role="button" tabindex="0" />';
	}

	return (
		'<div id="post-ratings-' + postId + '" class="post-ratings" data-nonce="abc123">' +
		images +
		'<span class="post-ratings-text" id="ratings_' + postId + '_text"></span>' +
		'</div>' +
		'<div id="post-ratings-' + postId + '-loading" class="post-ratings-loading">Loading...</div>'
	);
}
