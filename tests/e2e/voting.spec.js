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
	stepFills,
	uniqueTitle,
	wpEval,
} = require( './helpers.js' );

/**
 * The $_SERVER key the proxy tests ask the settings screen to trust.
 *
 * Playwright sets the header, PHP exposes it under this name, and the plugin
 * reads whichever key the site named -- so the three have to agree.
 *
 * @type {string}
 */
const PROXY_HEADER = 'HTTP_X_FORWARDED_FOR';

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
async function asGuest( browser, url, options = {} ) {
	const { address, storageState } = options;

	const context = await browser.newContext( {
		storageState,
		// The header the settings screen has been told to trust. Two contexts
		// otherwise arrive from one address -- the container's -- so this is the
		// only way to ask what the plugin does with two different visitors.
		...( address ? { extraHTTPHeaders: { 'X-Forwarded-For': address } } : {} ),
	} );

	const page = await context.newPage();

	await page.goto( url );

	return { context, page };
}

/**
 * Whether the visitor looking at this page is being offered the control.
 *
 * @param {import('@playwright/test').Page} page Page showing the post.
 * @return {Promise<boolean>} Whether a vote is on offer.
 */
async function canRate( page ) {
	return ( await page.locator( '.wp-postratings-vote' ).count() ) > 0;
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

	test( 'Check By Cookie And IP catches a repeat by the address alone', async ( {
		browser,
		page,
		requestUtils,
	} ) => {
		// The default, and until now the one setting with no test of its own.
		await configure( page, {
			check: CHECK.cookieAndIp,
			allow: ALLOW.everyone,
			ipHeader: PROXY_HEADER,
		} );

		const post = await createRatedPost( requestUtils, uniqueTitle( 'One vote per either' ) );

		const first = await asGuest( browser, post.link, { address: '203.0.113.10' } );
		await rate( first.page, 4 );
		await expectAverage( first.page, '4.00' );
		await first.context.close();

		// A browser with no cookie at all, arriving from the address that voted.
		// Only the address half can catch this one.
		const second = await asGuest( browser, post.link, { address: '203.0.113.10' } );
		expect( await canRate( second.page ) ).toBe( false );
		await second.context.close();
	} );

	test( 'Check By Cookie And IP catches a repeat by the cookie alone', async ( {
		browser,
		page,
		requestUtils,
	} ) => {
		await configure( page, {
			check: CHECK.cookieAndIp,
			allow: ALLOW.everyone,
			ipHeader: PROXY_HEADER,
		} );

		const post = await createRatedPost( requestUtils, uniqueTitle( 'One vote per cookie' ) );

		const first = await asGuest( browser, post.link, { address: '203.0.113.10' } );
		await rate( first.page, 4 );
		await expectAverage( first.page, '4.00' );

		const voted = await first.context.storageState();
		await first.context.close();

		// The same visitor's cookie carried to a different address. The address
		// half cannot catch this, so a refusal here is the cookie's doing.
		const moved = await asGuest( browser, post.link, {
			address: '203.0.113.20',
			storageState: voted,
		} );
		expect( await canRate( moved.page ) ).toBe( false );
		await moved.context.close();

		// And the control that makes that reading honest: the same new address
		// without the cookie is still offered a vote, so it was the cookie that
		// refused the one above and not the address.
		const stranger = await asGuest( browser, post.link, { address: '203.0.113.20' } );
		expect( await canRate( stranger.page ) ).toBe( true );
		await stranger.context.close();
	} );

	test( 'the trusted proxy header decides which address a vote is matched against', async ( {
		browser,
		page,
		requestUtils,
	} ) => {
		// Checking by address alone, so nothing but the address can refuse.
		await configure( page, {
			check: CHECK.ip,
			allow: ALLOW.everyone,
			ipHeader: PROXY_HEADER,
		} );

		const post = await createRatedPost( requestUtils, uniqueTitle( 'One vote per real address' ) );

		const first = await asGuest( browser, post.link, { address: '203.0.113.10' } );
		await rate( first.page, 4 );
		await expectAverage( first.page, '4.00' );
		await first.context.close();

		/*
		 * Two visitors the container cannot tell apart.
		 *
		 * Every context here arrives from one address, so without the header this
		 * second visitor is the first one and gets refused -- which is exactly
		 * what the test above this asserts when no header is trusted. Being
		 * offered a vote is therefore proof the header was read and believed.
		 */
		const elsewhere = await asGuest( browser, post.link, { address: '203.0.113.20' } );
		expect( await canRate( elsewhere.page ) ).toBe( true );
		await elsewhere.context.close();

		// And the address it names is matched, not merely read: the first
		// visitor's address is refused a second time.
		const again = await asGuest( browser, post.link, { address: '203.0.113.10' } );
		expect( await canRate( again.page ) ).toBe( false );
		await again.context.close();
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

	test( 'a post the public cannot see is still ratable by somebody who can', async ( {
		browser,
		page,
		requestUtils,
	} ) => {
		// The reported bug, from the browser rather than from PHP: every vote on
		// a private post came back "Invalid Post ID", naming an id that was
		// perfectly valid. What a browser adds over the unit tests is the wiring
		// around the check -- the control is rendered by the page and carries a
		// per-post nonce, and process_vote() is reached through the AJAX action
		// rather than called directly, so this is the only place both halves are
		// exercised on a post the public cannot see.
		await configure( page, { check: CHECK.never, allow: ALLOW.everyone } );

		const post = await requestUtils.createPost( {
			title: uniqueTitle( 'Private but ratable' ),
			content: '[ratings]',
			status: 'private',
		} );

		await page.goto( post.link );

		await rate( page, 4 );
		await expectAverage( page, '4.00' );

		// And the guard it was added for still holds: the post is not public,
		// and a visitor who is not signed in reaches no control at all.
		const { context, page: guest } = await asGuest( browser, post.link );

		try {
			await expect( guest.locator( '.wp-postratings-vote' ) ).toHaveCount( 0 );
		} finally {
			await context.close();
		}
	} );
} );
