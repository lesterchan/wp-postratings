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
