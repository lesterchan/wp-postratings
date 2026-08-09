/**
 * Who may rate, how a repeat vote is caught, and what a vote does to the post.
 *
 * The settings on the "Who May Rate" section only mean anything in a browser:
 * one of them is a cookie, one is an address, one is who you are logged in as,
 * and the control they gate is built by a script from markup the server sent.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const {
	ALLOW,
	CHECK,
	configure,
	createRatedPost,
	saveSettings,
	uniqueTitle,
	wpEval,
} = require( './helpers' );

/**
 * Click a point on the scale.
 *
 * The radio itself is visually hidden behind its label -- that is how the
 * shapes are drawn -- so a visitor clicks the label, and so does this.
 *
 * @param {import('@playwright/test').Page} page   Page showing the post.
 * @param {number}                          rating Which point to click.
 * @return {Promise<void>} Resolves once the click has been sent.
 */
async function rate( page, rating ) {
	const control = page.locator( '.wp-postratings-vote' ).first();
	const id = await control.evaluate( ( el ) => el.dataset.postId );

	await page.locator( `label[for="wp-postratings-${ id }-${ rating }"]` ).click();
}

/**
 * Wait for the rating to settle at a given average.
 *
 * Read from the rating image's accessible label, which the plugin builds
 * itself, rather than from the text beside it -- that is a template a site can
 * replace with anything, and a test that asserts on it is really asserting that
 * nobody has visited the Templates tab.
 *
 * @param {import('@playwright/test').Page} page    Page showing the post.
 * @param {string}                          average Average to wait for.
 * @return {Promise<void>} Resolves once the label says so.
 */
async function expectAverage( page, average ) {
	await expect(
		page.getByRole( 'img', { name: new RegExp( `average: ${ average.replace( '.', '\\.' ) }` ) } ),
	).toBeVisible( { timeout: 15_000 } );
}

/**
 * A logged-out page on the same site.
 *
 * @param {import('@playwright/test').Browser} browser Playwright browser.
 * @param {string}                             url     Where to go.
 * @return {Promise<Object>} The context and the page, for closing afterwards.
 */
async function asGuest( browser, url ) {
	const context = await browser.newContext( { storageState: undefined } );
	const page = await context.newPage();

	await page.goto( url );

	return { context, page };
}

/**
 * Which glyphs a rendered rating is actually drawing, in order.
 *
 * The one assertion no PHP test can make. A shape is a CSS mask built from a
 * chain -- the strip carries `--wp-postratings-shape-up` and `-down` as data
 * URIs, sets `--wp-postratings-shape` to a `var()` of one of them, and the
 * stylesheet feeds that to `mask-image` on the glyph inside. PHPUnit can only
 * assert the chain was written; whether the browser resolves it to the up shape
 * or the down one is a question about a page.
 *
 * Compared against the element's own two custom properties rather than against
 * hardcoded path data, so redrawing a shape cannot turn this red.
 *
 * @param {import('@playwright/test').Page} page Page showing the rating.
 * @return {Promise<string[]>} 'up' or 'down' per glyph, in document order.
 */
async function glyphDirections( page ) {
	const strip = page.locator( '.wp-postratings-strip' ).first();

	await expect( strip ).toBeVisible( { timeout: 15_000 } );

	return strip.evaluate( ( el ) => {
		const own = getComputedStyle( el );
		const normalise = ( value ) => ( value || '' ).replace( /["'\s]/g, '' );

		const up = normalise( own.getPropertyValue( '--wp-postratings-shape-up' ) );
		const down = normalise( own.getPropertyValue( '--wp-postratings-shape-down' ) );

		return Array.from( el.querySelectorAll( '.wp-postratings-item' ) ).map( ( item ) => {
			const style = getComputedStyle( item );
			const mask = normalise( style.maskImage || style.webkitMaskImage );

			if ( up && mask === up ) {
				return 'up';
			}

			if ( down && mask === down ) {
				return 'down';
			}

			return `unresolved:${ mask.slice( 0, 40 ) }`;
		} );
	} );
}

/**
 * How much of each step of the vote control the browser has actually filled.
 *
 * The other assertion PHP cannot make, and the one that matters most here: the
 * score is painted into each glyph by a gradient whose stop is a custom
 * property, so PHPUnit can only prove the property was written. Whether it turns
 * into paint on the right glyph depends on the stylesheet and on the theme around
 * it -- and the first version of this feature laid one strip over the whole row
 * instead, which passed every unit test and was visibly wrong on this very theme,
 * because a theme putting padding on a label moves that label's glyph without
 * moving anything the strip could see.
 *
 * Keyed by step rather than taken in document order: the row is laid out
 * reversed so a sibling combinator can reach backwards, so the fifth step comes
 * first in the markup.
 *
 * @param {import('@playwright/test').Page} page Page showing the control.
 * @return {Promise<Object>} Step number to filled percentage.
 */
async function stepFills( page ) {
	const scale = page.locator( '.wp-postratings-scale' ).first();

	await expect( scale ).toBeVisible( { timeout: 15_000 } );

	return scale.evaluate( ( el ) => {
		const fills = {};

		el.querySelectorAll( 'label[for]' ).forEach( ( label ) => {
			const step = Number( label.getAttribute( 'for' ).split( '-' ).pop() );
			const item = label.querySelector( '.wp-postratings-item' );

			if ( ! item ) {
				fills[ step ] = 'no glyph';
				return;
			}

			// The gradient's two stops sit at the same percentage -- one colour up
			// to it, the other from it -- so the first is the fill.
			const painted = getComputedStyle( item ).backgroundImage;
			const stop = painted.match( /([0-9.]+)%/ );

			fills[ step ] = stop ? Number( stop[ 1 ] ) : `unpainted:${ painted }`;
		} );

		return fills;
	} );
}

test.describe( 'Casting a vote', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test( 'a scale records the vote and the score appears without a reload', async ( {
		page,
		requestUtils,
	} ) => {
		await configure( page, { check: CHECK.never, allow: ALLOW.everyone } );

		const post = await createRatedPost( requestUtils, 'A post worth rating' );
		await page.goto( post.link );

		await expect( page.locator( '.wp-postratings-vote' ) ).toBeVisible();

		await rate( page, 4 );

		// The rated markup replaces the control in place, without a reload.
		await expectAverage( page, '4.00' );
		await expect( page.locator( '.wp-postratings-vote' ) ).toHaveCount( 0 );
	} );

	test( 'the vote control opens showing the score so far', async ( { page, requestUtils } ) => {
		// "Do Not Check" so a post that already has votes still offers the control
		// to this visitor -- which is the whole situation: a rated post, and a
		// reader who has not rated it, looking at a control that has to show the
		// score without pretending the reader cast it.
		await configure( page, { check: CHECK.never, allow: ALLOW.everyone } );

		const post = await createRatedPost( requestUtils, uniqueTitle( 'A post already at three and a half' ) );

		wpEval(
			`update_post_meta( ${ post.id }, 'ratings_users', 2 );
			update_post_meta( ${ post.id }, 'ratings_score', 7 );
			update_post_meta( ${ post.id }, 'ratings_average', 3.5 );
			echo '<<<seeded>>>';`,
		);

		await page.goto( post.link );

		await expect( page.locator( '.wp-postratings-vote' ) ).toBeVisible();

		// 3.5 of 5: three glyphs full, half of the fourth, nothing on the fifth.
		// It rendered five empty stars beside its own text saying "average: 3.50",
		// and then, on the second attempt, filled the right width in the wrong
		// place. Both are failures a browser sees and an array does not.
		expect( await stepFills( page ) ).toEqual( { 1: 100, 2: 100, 3: 100, 4: 50, 5: 0 } );
	} );

	test( 'a post nobody has rated opens the control empty', async ( { page, requestUtils } ) => {
		await configure( page, { check: CHECK.never, allow: ALLOW.everyone } );

		const post = await createRatedPost( requestUtils, uniqueTitle( 'A post nobody has rated' ) );

		await page.goto( post.link );

		expect( await stepFills( page ) ).toEqual( { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 } );
	} );

	test( 'an up or down rating is two buttons, and a down vote is negative', async ( {
		page,
		requestUtils,
	} ) => {
		await configure( page, {
			type: 'updown',
			shape: 'thumb',
			check: CHECK.never,
			allow: ALLOW.everyone,
		} );

		const post = await createRatedPost( requestUtils, 'A post worth a thumb' );
		await page.goto( post.link );

		// Two opposing actions, so two buttons -- not a scale of two, which is
		// what drew a strip of identical thumbs.
		const buttons = page.locator( '.wp-postratings-vote button' );
		await expect( buttons ).toHaveCount( 2 );

		await page.getByRole( 'button', { name: 'Vote Down' } ).click();

		// An up/down vote is signed, so one down vote scores -1 rather than 1. The
		// label is worded for a pair rather than a scale, because there is no
		// "out of" when the two sides are opposing actions.
		await expect( page.getByRole( 'img', { name: /-1 ratings/ } ) ).toBeVisible( {
			timeout: 15_000,
		} );

		// And it is drawn pointing down. Asserting the totals alone is what let a
		// down vote render as a thumbs up for as long as it did: the score was
		// right in the label the whole time, so every assertion here passed while
		// the glyph beside it contradicted them.
		expect( await glyphDirections( page ) ).toEqual( [ 'down' ] );
	} );

	test( 'a down vote that ties the post still shows the voter their own vote', async ( {
		page,
		requestUtils,
	} ) => {
		// The cookie is what remembers a personal vote, so the check has to be one
		// of the two settings that writes one.
		await configure( page, {
			type: 'updown',
			shape: 'thumb',
			check: CHECK.cookie,
			allow: ALLOW.everyone,
		} );

		const post = await createRatedPost( requestUtils, uniqueTitle( 'A post about to be tied' ) );

		// One up vote already, so the down vote below lands the post on zero --
		// which points nowhere, and used to draw a blank pair identical to the
		// control that had just been clicked.
		wpEval(
			`update_post_meta( ${ post.id }, 'ratings_users', 1 );
			update_post_meta( ${ post.id }, 'ratings_score', 1 );
			update_post_meta( ${ post.id }, 'ratings_average', 1 );
			echo '<<<seeded>>>';`,
		);

		await page.goto( post.link );
		await page.getByRole( 'button', { name: 'Vote Down' } ).click();

		// The post is tied, and says so.
		await expect( page.getByRole( 'img', { name: /You rated this down/ } ) ).toBeVisible( {
			timeout: 15_000,
		} );

		// One glyph, pointing down: the vote just cast, not the blank pair. This is
		// the round trip PHPUnit cannot reach -- the reply is rendered in the same
		// request that sets the cookie, so it has to read a cookie the browser has
		// not sent back yet.
		expect( await glyphDirections( page ) ).toEqual( [ 'down' ] );

		// The totals are still the post's own, unchanged by whose vote is drawn.
		await expect( page.locator( '.wp-postratings' ) ).toContainText( '2' );
	} );

	test( 'Do Not Check lets the same visitor rate twice', async ( { page, requestUtils } ) => {
		await configure( page, { check: CHECK.never, allow: ALLOW.everyone } );

		const post = await createRatedPost( requestUtils, 'Rate me as often as you like' );
		await page.goto( post.link );

		await rate( page, 5 );
		await expectAverage( page, '5.00' );

		await page.reload();

		// The control is back, because nothing was checked.
		await expect( page.locator( '.wp-postratings-vote' ) ).toBeVisible();

		await rate( page, 1 );

		// Two votes, averaging three.
		await expectAverage( page, '3.00' );
	} );

	test( 'Check By Cookie refuses a second vote from the same browser', async ( {
		page,
		requestUtils,
	} ) => {
		await configure( page, { check: CHECK.cookie, allow: ALLOW.everyone } );

		const post = await createRatedPost( requestUtils, 'One vote per browser' );
		await page.goto( post.link );

		await rate( page, 4 );
		await expectAverage( page, '4.00' );

		await page.reload();

		// No control this time: the cookie says this browser has voted.
		await expect( page.locator( '.wp-postratings-vote' ) ).toHaveCount( 0 );
	} );

	test( 'Check By Username refuses a second vote from the same account', async ( {
		page,
		requestUtils,
	} ) => {
		await configure( page, { check: CHECK.username, allow: ALLOW.loggedInOnly } );

		const post = await createRatedPost( requestUtils, 'One vote per account' );
		await page.goto( post.link );

		await rate( page, 3 );
		await expectAverage( page, '3.00' );

		await page.reload();
		await expect( page.locator( '.wp-postratings-vote' ) ).toHaveCount( 0 );
	} );

	test( 'Logged-in Users Only shows a guest the permission template', async ( {
		page,
		browser,
		requestUtils,
	} ) => {
		await configure( page, { check: CHECK.never, allow: ALLOW.loggedInOnly } );

		const post = await createRatedPost( requestUtils, 'Members only' );

		const guest = await asGuest( browser, post.link );

		await expect( guest.page.locator( '.wp-postratings' ).first() ).toContainText(
			'You need to be a registered member to rate this.',
		);
		await expect( guest.page.locator( '.wp-postratings-vote' ) ).toHaveCount( 0 );

		await guest.context.close();
	} );

	test( 'Guests Only shows a logged-in user the permission template', async ( {
		page,
		requestUtils,
	} ) => {
		await configure( page, { check: CHECK.never, allow: ALLOW.guestsOnly } );

		const post = await createRatedPost( requestUtils, 'Guests only' );
		await page.goto( post.link );

		await expect( page.locator( '.wp-postratings-vote' ) ).toHaveCount( 0 );
	} );

	test( 'a guest may rate when everyone is allowed', async ( { page, browser, requestUtils } ) => {
		await configure( page, { check: CHECK.never, allow: ALLOW.everyone } );

		const post = await createRatedPost( requestUtils, 'Everyone welcome' );

		const guest = await asGuest( browser, post.link );

		await expect( guest.page.locator( '.wp-postratings-vote' ) ).toBeVisible();

		await rate( guest.page, 2 );

		await expectAverage( guest.page, '2.00' );

		await guest.context.close();
	} );

	test( 'every vote reaches the log, whatever the check is set to', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Until 2.0.0 the lighter checks wrote no row at all, so a site that only
		// wanted repeat voting allowed lost its log with it.
		await configure( page, { check: CHECK.never, allow: ALLOW.everyone } );

		const title = uniqueTitle( 'Logged whatever the check' );
		const post = await createRatedPost( requestUtils, title );
		await page.goto( post.link );

		await rate( page, 5 );
		await expectAverage( page, '5.00' );

		await admin.visitAdminPage( 'admin.php', 'page=wp-postratings' );

		await expect( page.locator( '.wp-list-table tbody tr', { hasText: title } ) ).toHaveCount(
			1,
		);
	} );

	test( 'Check By IP refuses a second vote from another browser', async ( {
		page,
		browser,
		requestUtils,
	} ) => {
		await configure( page, { check: CHECK.ip, allow: ALLOW.everyone } );

		const post = await createRatedPost( requestUtils, uniqueTitle( 'One vote per address' ) );

		// A fresh context has no cookie, so only the address can catch it -- which
		// is the whole difference between this setting and the cookie one.
		const first = await asGuest( browser, post.link );
		await rate( first.page, 4 );
		await expectAverage( first.page, '4.00' );
		await first.context.close();

		const second = await asGuest( browser, post.link );
		await expect( second.page.locator( '.wp-postratings-vote' ) ).toHaveCount( 0 );
		await second.context.close();
	} );

	test( 'the rating text typed into the settings is what a visitor reads', async ( {
		page,
		requestUtils,
	} ) => {
		await configure( page, { check: CHECK.never, allow: ALLOW.everyone } );

		// Rename the first step, which is the label on the control and the text
		// the plugin shows once a post has been rated.
		await page
			.locator( '#wp-postratings-rating-fields input[name$="[text][]"]' )
			.first()
			.fill( 'Dreadful' );
		await saveSettings( page );

		const post = await createRatedPost( requestUtils, uniqueTitle( 'Reads Dreadful' ) );
		await page.goto( post.link );

		await expect( page.locator( '.wp-postratings-vote' ) ).toContainText( 'Dreadful' );

		await rate( page, 1 );
		await expectAverage( page, '1.00' );

		// Put the default back for whatever runs next.
		await configure( page, { check: CHECK.never, allow: ALLOW.everyone } );
	} );

	test( 'the rating value typed into the settings is what gets scored', async ( {
		page,
		requestUtils,
	} ) => {
		await configure( page, { check: CHECK.never, allow: ALLOW.everyone } );

		// The value is what the vote is worth, and it does not have to be the
		// position: a five point scale can score its top step at ten.
		await page
			.locator( '#wp-postratings-rating-fields input[name$="[value][]"]' )
			.last()
			.fill( '10' );
		await saveSettings( page );

		const post = await createRatedPost( requestUtils, uniqueTitle( 'Worth ten' ) );
		await page.goto( post.link );

		await rate( page, 5 );

		await expectAverage( page, '10.00' );

		await configure( page, { check: CHECK.never, allow: ALLOW.everyone } );
	} );

	test( 'the results shortcode shows a score without offering a vote', async ( {
		page,
		requestUtils,
	} ) => {
		await configure( page, { check: CHECK.never, allow: ALLOW.everyone } );

		const post = await requestUtils.createPost( {
			title: uniqueTitle( 'Results only' ),
			content: '[ratings]<hr />[ratings results="true"]',
			status: 'publish',
		} );

		await page.goto( post.link );

		await rate( page, 3 );
		await expectAverage( page, '3.00' );

		await page.reload();

		// One control, two renderings of the same rating: the results one is
		// read-only however the post is configured.
		await expect( page.locator( '.wp-postratings-strip' ).first() ).toBeVisible();
	} );

	test( 'the unrated shapes darken on a dark scheme, wrapper or no wrapper', async ( {
		browser,
		requestUtils,
	} ) => {
		// The dark-scheme default is a custom property, and a custom property is
		// only worth anything to an element that inherits it. `.wp-postratings`
		// is added by the_ratings() and nothing else, so the results shortcode --
		// like the widget and the block's results mode, which reach the same
		// renderer -- prints a bare `.wp-postratings-strip` with no such
		// ancestor. The track then fell back to #d4d4d8, a grey chosen against a
		// white page, and glared on a dark one.
		//
		// Read as a computed colour rather than asserted against the stylesheet:
		// the bug was never in the declaration, it was in which elements the
		// declaration could reach.
		const post = await requestUtils.createPost( {
			title: uniqueTitle( 'Dark scheme' ),
			content: '[ratings results="true"]',
			status: 'publish',
		} );

		const context = await browser.newContext( { colorScheme: 'dark', storageState: undefined } );

		try {
			const guest = await context.newPage();
			await guest.goto( post.link );

			const track = guest.locator( '.wp-postratings-strip .wp-postratings-track' ).first();
			await expect( track ).toBeAttached();

			// The wrapper really is absent, or the assertion below would hold
			// for a reason that has nothing to do with the fix.
			await expect( guest.locator( '.wp-postratings .wp-postratings-track' ) ).toHaveCount( 0 );

			const colour = await track.evaluate( ( el ) => getComputedStyle( el ).color );

			expect( colour ).toBe( 'rgb(82, 82, 91)' );
		} finally {
			await context.close();
		}
	} );

	test( 'the rating appears in the posts list column', async ( { page, admin, requestUtils } ) => {
		await configure( page, { check: CHECK.never, allow: ALLOW.everyone } );

		const post = await createRatedPost( requestUtils, 'Shown in the column' );
		await page.goto( post.link );

		await rate( page, 4 );
		await expectAverage( page, '4.00' );

		await admin.visitAdminPage( 'edit.php' );

		const row = page.locator( '#the-list tr', { hasText: 'Shown in the column' } );

		await expect( row.locator( 'td.ratings' ) ).toContainText( '4.00' );
	} );
} );
