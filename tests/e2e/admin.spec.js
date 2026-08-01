/**
 * The rest of what the plugin puts in wp-admin.
 *
 * The capability gate, the column it adds to the posts list, and Screen Options
 * on the logs table -- all things a person meets before they ever open the
 * settings screen, and none of which a unit test looks at.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const { ALLOW, CHECK, configure, createRatedPost, uniqueTitle } = require( './helpers' );

const LOGS_URL = '/wp-admin/admin.php?page=wp-postratings';
const SETTINGS_URL = '/wp-admin/admin.php?page=wp-postratings-settings';

/**
 * Create a user with a role, or return the existing one.
 *
 * There is no user helper in the e2e utilities, so this goes through the REST
 * API the admin session is already authenticated for.
 *
 * @param {Object} requestUtils The e2e request helper.
 * @param {string} username     Login name.
 * @param {string} role         Role slug.
 * @return {Promise<Object>} The user record.
 */
async function ensureUser( requestUtils, username, role ) {
	const existing = await requestUtils.rest( {
		path: '/wp/v2/users',
		params: { search: username, context: 'edit' },
	} );

	if ( existing.length ) {
		return existing[ 0 ];
	}

	return requestUtils.rest( {
		method: 'POST',
		path: '/wp/v2/users',
		data: {
			username,
			email: `${ username }@example.com`,
			password: 'correct-horse-battery-staple',
			roles: [ role ],
		},
	} );
}

/**
 * A browser context logged in as somebody else.
 *
 * @param {import('@playwright/test').Browser} browser  Playwright browser.
 * @param {string}                             baseURL  Site root.
 * @param {string}                             username Login name.
 * @return {Promise<Object>} The context and its page.
 */
async function loginAs( browser, baseURL, username ) {
	const context = await browser.newContext( { storageState: undefined } );
	const page = await context.newPage();

	await page.goto( `${ baseURL }/wp-login.php` );

	// wp-login.php focuses and selects #user_login on a 200ms timer, so that a
	// visitor can start typing. Filling across that moment puts the password
	// into the username box: Playwright focuses #user_pass, the timer takes
	// focus back and selects what is there, and the typed text replaces the
	// selection. Waiting for the timer's own effect is the signal that it has
	// already fired -- a sleep would only make the race less likely.
	await expect( page.locator( '#user_login' ) ).toBeFocused();

	await page.locator( '#user_login' ).fill( username );
	await page.locator( '#user_pass' ).fill( 'correct-horse-battery-staple' );
	await page.locator( '#wp-submit' ).click();

	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();

	return { context, page };
}

test.describe( 'The plugin in wp-admin', () => {
	test( 'a user without the capability gets no menu and no screen', async ( {
		browser,
		baseURL,
		requestUtils,
	} ) => {
		await ensureUser( requestUtils, 'ratings_subscriber', 'subscriber' );

		const other = await loginAs( browser, baseURL, 'ratings_subscriber' );

		// The plugin ships its own capability rather than using manage_options,
		// so this is the gate every one of its screens is behind.
		await expect( other.page.locator( '#adminmenu' ).getByText( 'Ratings' ) ).toHaveCount( 0 );

		await other.page.goto( SETTINGS_URL );
		await expect( other.page.locator( 'body' ) ).toContainText(
			/Access Denied|not allowed to access this page/,
		);

		await other.page.goto( LOGS_URL );
		await expect( other.page.locator( 'body' ) ).toContainText(
			/Access Denied|not allowed to access this page/,
		);

		await other.context.close();
	} );

	test( 'the administrator reaches both screens from the menu', async ( { page, admin } ) => {
		await admin.visitAdminPage( 'index.php' );

		const menu = page.locator( '#adminmenu' );

		// Settings above Logs: the screen a site owner opens to change something
		// comes before the record of what has happened.
		const items = await menu
			.locator( 'a[href*="page=wp-postratings"]' )
			.evaluateAll( ( links ) => links.map( ( link ) => link.textContent.trim() ) );

		expect( items ).toContain( 'Settings' );
		expect( items ).toContain( 'Logs' );
		expect( items.indexOf( 'Settings' ) ).toBeLessThan( items.indexOf( 'Logs' ) );
	} );

	test( 'the posts list column is sortable by rating', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		await configure( page, { check: CHECK.never, allow: ALLOW.everyone } );
		await requestUtils.deleteAllPosts();

		const low = uniqueTitle( 'Column low' );
		const high = uniqueTitle( 'Column high' );

		for ( const [ title, rating ] of [
			[ low, 1 ],
			[ high, 5 ],
		] ) {
			const post = await createRatedPost( requestUtils, title );
			await page.goto( post.link );

			const id = await page
				.locator( '.wp-postratings-vote' )
				.first()
				.evaluate( ( el ) => el.dataset.postId );

			await page.locator( `label[for="wp-postratings-${ id }-${ rating }"]` ).click();
			await expect( page.getByRole( 'img', { name: /vote/ } ) ).toBeVisible( {
				timeout: 15_000,
			} );
		}

		await admin.visitAdminPage( 'edit.php' );

		await page.locator( 'th#ratings a' ).click();

		await expect( page.locator( '#the-list tr' ).first() ).toContainText( low );

		await page.locator( 'th#ratings a' ).click();

		await expect( page.locator( '#the-list tr' ).first() ).toContainText( high );
	} );

	test( 'Screen Options changes how many log rows are shown', async ( {
		page,
		requestUtils,
	} ) => {
		await configure( page, { check: CHECK.never, allow: ALLOW.everyone } );
		await requestUtils.deleteAllPosts();

		// Three votes, then ask for two per page.
		for ( let i = 0; i < 3; i++ ) {
			const post = await createRatedPost( requestUtils, uniqueTitle( `Row ${ i }` ) );
			await page.goto( post.link );

			const id = await page
				.locator( '.wp-postratings-vote' )
				.first()
				.evaluate( ( el ) => el.dataset.postId );

			await page.locator( `label[for="wp-postratings-${ id }-4"]` ).click();
			await expect( page.getByRole( 'img', { name: /vote/ } ) ).toBeVisible( {
				timeout: 15_000,
			} );
		}

		await page.goto( LOGS_URL );

		await page.locator( '#show-settings-link' ).click();
		await page.locator( '#wp_postratings_logs_per_page' ).fill( '2' );
		await page.locator( '#screen-options-apply' ).click();

		await expect( page.locator( '.wp-list-table tbody tr' ) ).toHaveCount( 2 );

		// Put it back, so the next test sees a normal screen.
		await page.locator( '#show-settings-link' ).click();
		await page.locator( '#wp_postratings_logs_per_page' ).fill( '20' );
		await page.locator( '#screen-options-apply' ).click();
	} );
} );
