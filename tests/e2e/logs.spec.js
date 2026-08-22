/**
 * The Logs screen: the list table, its bulk action, and the delete form.
 *
 * The bulk action is here because of a bug no unit test could see. The screen
 * printed a nonce of its own beside the one WP_List_Table already prints, both
 * named _wpnonce, and PHP keeps the last field of a repeated name -- so the
 * check read the wrong value and every bulk delete failed with "The link you
 * followed has expired". The PHPUnit test passed throughout, because it built
 * the nonce itself instead of reading the one on the page.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const { ALLOW, CHECK, configure, createRatedPost, uniqueTitle } = require( './helpers.js' );

const LOGS_URL = '/wp-admin/admin.php?page=wp-postratings';

/**
 * Publish a post and rate it, so there is a log row to work with.
 *
 * @param {import('@playwright/test').Page} page         Page under test.
 * @param {Object}                          requestUtils The e2e request helper.
 * @param {string}                          title        Post title.
 * @param {number}                          rating       Which point to click.
 * @return {Promise<Object>} The created post.
 */
async function ratedPost( page, requestUtils, title, rating = 4 ) {
	const post = await createRatedPost( requestUtils, title );

	await page.goto( post.link );

	const id = await page
		.locator( '.wp-postratings-vote' )
		.first()
		.evaluate( ( el ) => el.dataset.postId );

	await page.locator( `label[for="wp-postratings-${ id }-${ rating }"]` ).click();

	// The rating image's own label, not the template beside it: a test that reads
	// the template is really testing whether anyone has edited the Templates tab.
	await expect( page.getByRole( 'img', { name: /rating|vote/ } ) ).toBeVisible( {
		timeout: 15_000,
	} );

	return post;
}

/**
 * Empty the log through the screen's own delete form.
 *
 * @param {import('@playwright/test').Page} page Page under test.
 * @return {Promise<void>} Resolves once the log is empty.
 */
async function clearLog( page ) {
	await page.goto( LOGS_URL );

	await page.locator( '#wp-postratings-delete-datalog' ).selectOption( '3' );
	await page.locator( '#wp-postratings-delete-postid' ).fill( 'all' );

	page.once( 'dialog', ( dialog ) => dialog.accept() );
	await page.locator( '#wp-postratings-delete-data' ).click();

	await expect( page.locator( '.wp-list-table tbody' ) ).toContainText( 'No Rating Logs Found' );
}

test.describe( 'The logs screen', () => {
	test.beforeEach( async ( { page, requestUtils } ) => {
		await requestUtils.deleteAllPosts();

		// Do Not Check, so the same browser can rate several posts in one run.
		await configure( page, { check: CHECK.never, allow: ALLOW.everyone } );

		// A log row outlives the post it was cast on, which is the point of a log
		// and a nuisance for a test counting rows. Start from an empty one.
		await clearLog( page );
	} );

	test( 'the bulk form carries exactly one nonce', async ( { page, requestUtils } ) => {
		// With a row in it: WP_List_Table draws the bulk controls around the rows
		// it has, so an empty table is not the screen this is about.
		await ratedPost( page, requestUtils, uniqueTitle( 'Something to select' ) );

		await page.goto( LOGS_URL );

		const bulkForm = page.locator( 'form' ).filter( {
			has: page.locator( '#bulk-action-selector-top' ),
		} );

		await expect( bulkForm.locator( 'input[name="_wpnonce"]' ) ).toHaveCount( 1 );
	} );

	test( 'a bulk delete removes the selected row', async ( { page, requestUtils } ) => {
		const title = uniqueTitle( 'Doomed log entry' );

		await ratedPost( page, requestUtils, title );

		await page.goto( LOGS_URL );

		const row = page.locator( '.wp-list-table tbody tr', { hasText: title } );
		await expect( row ).toHaveCount( 1 );

		await row.locator( 'input[type="checkbox"]' ).check();
		await page.locator( '#bulk-action-selector-top' ).selectOption( 'delete' );
		await page.locator( '#doaction' ).click();

		// The whole point: no "The link you followed has expired".
		await expect( page.locator( 'body' ) ).not.toContainText( 'link you followed has expired' );

		await expect( page.locator( '.wp-list-table tbody tr', { hasText: title } ) ).toHaveCount(
			0,
		);
	} );

	test( 'the search box filters the log', async ( { page, requestUtils } ) => {
		const wanted = uniqueTitle( 'Findable post' );
		const other = uniqueTitle( 'Unrelated post' );

		await ratedPost( page, requestUtils, wanted );
		await ratedPost( page, requestUtils, other );

		await page.goto( LOGS_URL );

		await page.locator( '#wp-postratings-search-input' ).fill( wanted );
		await page.getByRole( 'button', { name: 'Search Logs' } ).click();

		await expect( page.locator( '.wp-list-table tbody tr', { hasText: wanted } ) ).toHaveCount(
			1,
		);
		await expect( page.locator( '.wp-list-table tbody tr', { hasText: other } ) ).toHaveCount(
			0,
		);
	} );

	test( 'a sortable column reorders the log', async ( { page, requestUtils } ) => {
		const low = uniqueTitle( 'Rated low' );
		const high = uniqueTitle( 'Rated high' );

		await ratedPost( page, requestUtils, low, 1 );
		await ratedPost( page, requestUtils, high, 5 );

		await page.goto( LOGS_URL );

		await page.locator( 'th#rating_rating a' ).click();

		// Ascending on the first click, so the low rating leads.
		await expect( page.locator( '.wp-list-table tbody tr' ).first() ).toContainText( low );

		await page.locator( 'th#rating_rating a' ).click();

		await expect( page.locator( '.wp-list-table tbody tr' ).first() ).toContainText( high );
	} );

	test( 'Delete Data/Logs asks before it does anything', async ( { page, requestUtils } ) => {
		const title = uniqueTitle( 'Survives a declined confirm' );

		await ratedPost( page, requestUtils, title );

		await page.goto( LOGS_URL );

		await page.locator( '#wp-postratings-delete-datalog' ).selectOption( '3' );
		await page.locator( '#wp-postratings-delete-postid' ).fill( 'all' );

		// Declined: nothing is deleted. This is irreversible, so the confirmation
		// has to be a real gate rather than decoration.
		page.once( 'dialog', ( dialog ) => dialog.dismiss() );
		await page.locator( '#wp-postratings-delete-data' ).click();

		await expect( page.locator( '.wp-list-table tbody tr', { hasText: title } ) ).toHaveCount(
			1,
		);
	} );

	test( 'Delete Data/Logs clears the log when confirmed', async ( { page, requestUtils } ) => {
		await ratedPost( page, requestUtils, uniqueTitle( 'Cleared by the delete form' ) );

		await page.goto( LOGS_URL );

		await expect( page.locator( '.wp-list-table tbody tr' ) ).toHaveCount( 1 );

		await clearLog( page );
	} );

	test( 'clearing the data resets the post itself', async ( { page, requestUtils } ) => {
		const post = await ratedPost( page, requestUtils, uniqueTitle( 'Back to unrated' ) );

		await clearLog( page );

		await page.goto( post.link );

		// "Logs And Data" means the post meta too, so the control comes back.
		await expect( page.locator( '.wp-postratings-vote' ) ).toBeVisible();
	} );
} );
