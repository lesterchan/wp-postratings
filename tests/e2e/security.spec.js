/**
 * The stored XSS regression, in a real browser.
 *
 * A rating label is free text a site owner types on the settings screen, and it
 * is echoed into the rating markup the plugin builds. The value that matters
 * most is the one PHPUnit cannot reach: after a vote, that markup is assembled
 * in the admin-ajax reply and swapped straight into the page with innerHTML, so
 * a payload that survived the round trip runs there even when the same value is
 * safe everywhere PHP can render it. This test seeds the labels with the payload
 * a compromised or pre-fix install already holds -- written straight into the
 * option row, not through the form, because sanitising on the way in is the
 * assumption under test -- casts a vote, and reads the reply back.
 *
 * The assertion is the same on the ballot and in the reply: the sentinel the
 * payload would set must never become defined, the dangerous form -- a script
 * element, an onerror handler -- must be absent, and the payload's text must
 * survive on the page. Escaping that ate the payload entirely would pass the
 * first two and still be a bug, because a rating label that silently vanishes
 * is one.
 *
 * The label goes to the page through esc_html(), so a bare <img> arrives as
 * text rather than an element: the check is not "no image" but "nothing that
 * runs" -- no onerror, no script, and the sentinel still undefined.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const { createRatedPost, resetPlugin, uniqueTitle, wpEval } = require( './helpers.js' );

const SCRIPT_PAYLOAD = '<script>window.__pwned = 1;</script>';
const IMG_PAYLOAD = '<img src=x onerror="window.__pwned = 1">';
const ATTR_PAYLOAD = '" onmouseover="window.__pwned = 1';

/** The five scale labels, three of them attacks. */
const HOSTILE_LABELS = [
	`One ${ SCRIPT_PAYLOAD }`,
	`Two ${ IMG_PAYLOAD }`,
	`Three ${ ATTR_PAYLOAD }`,
	'Four',
	'Five',
];

/**
 * Whether any payload managed to run.
 *
 * @param {import('@playwright/test').Page} page Page to ask.
 * @return {Promise<boolean>} True if the sentinel was set.
 */
function pwned( page ) {
	return page.evaluate( () => window.__pwned === 1 );
}

/**
 * Put hostile labels into the option row and re-render the control after a vote.
 *
 * Written with update_option() rather than the settings form on purpose: the
 * form sanitises, and the row a pre-fix install carries did not go through it.
 * The text template is set to draw the vote control again -- with a marker to
 * wait on -- so the label comes back in the admin-ajax reply, which is the one
 * surface no PHP test renders.
 *
 * @param {string[]} labels       Scale labels, stored exactly as given.
 * @param {string}   textTemplate Template returned after a vote.
 * @return {void}
 */
function seedHostileRatings( labels, textTemplate ) {
	const seed = Buffer.from( JSON.stringify( { labels, text: textTemplate } ), 'utf8' ).toString(
		'base64',
	);

	wpEval(
		`$seed = json_decode( base64_decode( '${ seed }' ), true );
		$options = get_option( WP_PostRatings_Options::OPTION );
		$options['shape']            = 'star';
		$options['max']              = 5;
		$options['customrating']     = 0;
		$options['allowtorate']      = 2;
		$options['check_method']     = 0;
		$options['ratings']['text']  = $seed['labels'];
		$options['ratings']['value'] = array( 1, 2, 3, 4, 5 );
		$options['templates']['text'] = $seed['text'];
		update_option( WP_PostRatings_Options::OPTION, $options );
		echo '<<<seeded>>>';`,
	);
}

test.describe( 'Stored XSS stays stored', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	// The labels and the swapped text template outlive the row that holds them,
	// so put the shipped defaults back for whatever runs next.
	test.afterAll( () => {
		resetPlugin();
	} );

	test( 'the vote control renders hostile rating labels as text', async ( {
		page,
		requestUtils,
	} ) => {
		seedHostileRatings( HOSTILE_LABELS, '%RATINGS_IMAGES_VOTE% %RATINGS_TEXT%' );

		const post = await createRatedPost( requestUtils, uniqueTitle( 'Hostile labels' ) );

		await page.goto( post.link );

		const control = page.locator( `#wp-postratings-${ post.id }` );

		await expect( control.locator( '.wp-postratings-vote' ) ).toBeVisible();

		expect( await pwned( page ) ).toBe( false );

		// As text, not merely absent: the poisoned labels still say what the row
		// says, and neither payload became an element.
		await expect( control ).toContainText( 'window.__pwned' );
		await expect( control ).toContainText( 'onmouseover' );
		await expect( control.locator( 'img[onerror]' ) ).toHaveCount( 0 );
		await expect( control.locator( 'script' ) ).toHaveCount( 0 );
	} );

	test( 'the AJAX vote reply carries the hostile labels back escaped', async ( {
		page,
		requestUtils,
	} ) => {
		// A marker beside the control so the swap can be waited on: it is in the
		// text template, which only the admin-ajax reply renders, so its arrival
		// is the reply landing in the page rather than the original ballot.
		seedHostileRatings(
			HOSTILE_LABELS,
			'%RATINGS_IMAGES_VOTE% <span class="wp-postratings-reply-marker">voted</span> %RATINGS_TEXT%',
		);

		const post = await createRatedPost( requestUtils, uniqueTitle( 'Hostile reply' ) );

		await page.goto( post.link );

		const control = page.locator( `#wp-postratings-${ post.id }` );

		await expect( control.locator( '.wp-postratings-vote' ) ).toBeVisible();

		// The classic vector: the reply markup is built server-side and swapped
		// straight in with innerHTML, so an <img onerror> that survived the round
		// trip fires here even though the ballot above was clean.
		await page.locator( `label[for="wp-postratings-${ post.id }-4"]` ).click();

		await expect( control.locator( '.wp-postratings-reply-marker' ) ).toBeVisible( {
			timeout: 15_000,
		} );

		expect( await pwned( page ) ).toBe( false );

		await expect( control ).toContainText( 'window.__pwned' );
		await expect( control ).toContainText( 'onmouseover' );
		await expect( control.locator( 'img[onerror]' ) ).toHaveCount( 0 );
		await expect( control.locator( 'script' ) ).toHaveCount( 0 );
	} );
} );
