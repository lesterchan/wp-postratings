/**
 * A cached page correcting its own ratings.
 *
 * The only place this can be shown is a browser, because the bug is not in
 * what the server renders -- that is right when it is rendered -- but in what
 * happens to the markup afterwards. A page cache keeps one visitor's copy and
 * hands it to the next, and no vote purges it.
 *
 * So the cache is played by Playwright: a page is loaded, its HTML kept, a vote
 * is cast from somewhere else, and the kept HTML is then served back the way a
 * cache would serve it. Whether the rating on it corrects itself is the whole
 * feature.
 *
 * What this cannot show is the nonce ageing out, because a nonce minted a
 * second ago still verifies. The unit suite pins the replacement nonce being
 * accepted; what is asserted here is the half a browser can see.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const {
	ALLOW,
	CHECK,
	configure,
	createRatedPost,
	uniqueTitle,
} = require( './helpers.js' );

/** The route the front end corrects itself from. */
const BATCH_ROUTE = /postratings\/v1\/posts/;

/**
 * A context belonging to nobody.
 *
 * `storageState: undefined` is the load-bearing part. browser.newContext()
 * inherits the config's `use` options, and this suite's are the saved admin
 * session -- so a context asked for with no options at all arrives logged in.
 * Which passes half the assertions here for the wrong reason and fails the
 * other half: a logged-in visitor bypasses every page cache, so the plugin
 * correctly declines to refresh anything for them.
 *
 * @param {import('@playwright/test').Browser} browser Playwright browser.
 * @return {Promise<import('@playwright/test').BrowserContext>} A logged-out context.
 */
function guestContext( browser ) {
	return browser.newContext( { storageState: undefined } );
}

/**
 * Load a post as a logged-out visitor and keep the HTML that came back.
 *
 * This is what a page cache stores.
 *
 * @param {import('@playwright/test').Browser} browser Playwright browser.
 * @param {string}                             url     Post to visit.
 * @return {Promise<string>} The page's HTML.
 */
async function cacheThePage( browser, url ) {
	const context = await guestContext( browser );
	const page = await context.newPage();

	await page.goto( url );
	await expect( page.locator( '.wp-postratings' ).first() ).toBeVisible();

	const html = await page.content();

	await context.close();

	return html;
}

/**
 * Rate a post as somebody else entirely.
 *
 * @param {import('@playwright/test').Browser} browser Playwright browser.
 * @param {string}                             url     Post to rate.
 * @param {number}                             rating  Point on the scale.
 * @return {Promise<void>} Resolves once the vote has registered.
 */
async function someoneElseRates( browser, url, rating ) {
	const context = await guestContext( browser );
	const page = await context.newPage();

	await page.goto( url );

	const control = page.locator( '.wp-postratings-vote' ).first();
	const id = await control.evaluate( ( el ) => el.dataset.postId );

	await page.locator( `label[for="wp-postratings-${ id }-${ rating }"]` ).click();

	// Wait for the reply to have landed, not merely for the click.
	await expect( page.locator( '.wp-postratings-vote' ) ).toHaveCount( 0, { timeout: 15_000 } );

	await context.close();
}

/**
 * Open a page that will be served the kept HTML for a URL, as a cache would.
 *
 * Only the document itself is intercepted. The script, the stylesheet and the
 * REST call all go to the real site, which is exactly the arrangement a cached
 * page is in.
 *
 * The navigation is left to the caller, because the refresh fires on
 * DOMContentLoaded: anything watching for its request has to be listening
 * before the page is asked for, not after goto() has returned.
 *
 * @param {import('@playwright/test').Browser} browser Playwright browser.
 * @param {string}                             url     Post being served.
 * @param {string}                             html    What the cache is holding.
 * @return {Promise<Object>} The context and the page, not yet navigated.
 */
async function openOnTheCachedCopy( browser, url, html ) {
	const context = await guestContext( browser );
	const page = await context.newPage();

	await page.route( ( candidate ) => candidate.href.split( '?' )[ 0 ] === url.split( '?' )[ 0 ], ( route ) => {
		if ( 'document' !== route.request().resourceType() ) {
			return route.continue();
		}

		return route.fulfill( { status: 200, contentType: 'text/html', body: html } );
	} );

	return { context, page };
}

/**
 * Record every request the page makes to the batch route.
 *
 * @param {import('@playwright/test').Page} page Page to watch.
 * @return {string[]} The urls, filled in as they are asked for.
 */
function recordBatchRequests( page ) {
	const asked = [];

	page.on( 'request', ( request ) => {
		if ( BATCH_ROUTE.test( request.url() ) ) {
			asked.push( request.url() );
		}
	} );

	return asked;
}

test.describe( 'A page served from a cache', () => {
	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	/**
	 * A post nobody has rated yet, and its url.
	 *
	 * One per test rather than one for the file: the point of every case here
	 * is what a vote does to a page cached before it, so a post another test
	 * has already voted on cannot show it. Sharing one post is how the "no
	 * ratings yet" assertion passes without asserting anything.
	 *
	 * @param {Object} requestUtils The e2e-test-utils request helper.
	 * @param {string} title        What to call it.
	 * @return {Promise<string>} The post's url.
	 */
	async function unratedPost( requestUtils, title ) {
		const post = await createRatedPost( requestUtils, uniqueTitle( title ) );

		return post.link;
	}

	test( 'shows the vote count it was cached with, until the setting is on', async ( {
		admin,
		page,
		browser,
		requestUtils,
	} ) => {
		await admin.visitAdminPage( 'admin.php', 'page=wp-postratings-settings' );
		await configure( page, {
			allow: ALLOW.everyone,
			check: CHECK.never,
			pageCache: false,
		} );

		const postUrl = await unratedPost( requestUtils, 'Uncorrected cached rating' );
		const cached = await cacheThePage( browser, postUrl );

		expect( cached ).toContain( 'No Ratings Yet' );

		await someoneElseRates( browser, postUrl, 5 );

		const { context, page: visitor } = await openOnTheCachedCopy( browser, postUrl, cached );
		const asked = recordBatchRequests( visitor );

		await visitor.goto( postUrl );

		// Nothing asks the site anything, so the cached sentence stands and the
		// visitor is told there are no ratings on a post that has one. This is
		// the bug, reproduced.
		await expect( visitor.locator( '.wp-postratings' ).first() ).toContainText( 'No Ratings Yet' );
		expect( asked ).toHaveLength( 0 );

		await context.close();
	} );

	test( 'corrects the vote count once the site says its pages are cached', async ( {
		admin,
		page,
		browser,
		requestUtils,
	} ) => {
		await admin.visitAdminPage( 'admin.php', 'page=wp-postratings-settings' );
		await configure( page, {
			allow: ALLOW.everyone,
			check: CHECK.never,
			pageCache: true,
		} );

		const postUrl = await unratedPost( requestUtils, 'Corrected cached rating' );
		const cached = await cacheThePage( browser, postUrl );

		expect( cached ).toContain( 'No Ratings Yet' );

		await someoneElseRates( browser, postUrl, 5 );

		const { context, page: visitor } = await openOnTheCachedCopy( browser, postUrl, cached );
		const asked = recordBatchRequests( visitor );

		await visitor.goto( postUrl );

		// The vote the cached copy could not have known about, named rather
		// than inferred from the absence of the old sentence.
		await expect( visitor.locator( '.wp-postratings' ).first() ).toContainText(
			/1\s*votes/,
			{ timeout: 15_000 },
		);
		await expect( visitor.locator( '.wp-postratings' ).first() ).not.toContainText( 'No Ratings Yet' );

		expect( asked ).toHaveLength( 1 );
		expect( new URL( asked[ 0 ] ).searchParams.get( 'ids' ) ).not.toBe( null );

		await context.close();
	} );

	test( 'asks once for a page showing several ratings', async ( {
		admin,
		page,
		browser,
		requestUtils,
	} ) => {
		await admin.visitAdminPage( 'admin.php', 'page=wp-postratings-settings' );
		await configure( page, {
			allow: ALLOW.everyone,
			check: CHECK.never,
			pageCache: true,
		} );

		const first = await createRatedPost( requestUtils, uniqueTitle( 'First of three' ) );
		const second = await createRatedPost( requestUtils, uniqueTitle( 'Second of three' ) );

		// A page pointing the shortcode at three posts, rather than the blog
		// home: the theme's archive prints excerpts, and a shortcode in an
		// excerpt renders nothing.
		const roundup = await requestUtils.createPost( {
			title: uniqueTitle( 'Three ratings on one page' ),
			content: `[ratings]\n[ratings id="${ first.id }"]\n[ratings id="${ second.id }"]`,
			status: 'publish',
		} );

		const context = await guestContext( browser );
		const visitor = await context.newPage();
		const asked = recordBatchRequests( visitor );

		await visitor.goto( roundup.link );

		await expect( visitor.locator( '.wp-postratings' ).first() ).toBeVisible();

		const ratings = await visitor.locator( '.wp-postratings' ).count();

		expect( ratings ).toBe( 3 );
		// A round trip per rating is what the batch route exists to avoid.
		await expect.poll( () => asked.length, { timeout: 15_000 } ).toBe( 1 );
		expect( new URL( asked[ 0 ] ).searchParams.get( 'ids' ).split( ',' ) ).toHaveLength( 3 );

		await context.close();
	} );

	test( 'leaves a logged-in visitor alone, who never saw the cache', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		await admin.visitAdminPage( 'admin.php', 'page=wp-postratings-settings' );
		await configure( page, {
			allow: ALLOW.everyone,
			check: CHECK.never,
			pageCache: true,
		} );

		const postUrl = await unratedPost( requestUtils, 'Rating for somebody signed in' );
		const asked = recordBatchRequests( page );

		await page.goto( postUrl );
		await expect( page.locator( '.wp-postratings' ).first() ).toBeVisible();

		// Every page cache passes a logged-in request through to PHP, so this
		// markup was built for this visitor and there is nothing to correct.
		expect( asked ).toHaveLength( 0 );
	} );
} );
