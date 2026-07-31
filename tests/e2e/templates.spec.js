/**
 * The Templates tab, and what a template does to a post.
 *
 * A template is markup a site owner types into a textarea and the plugin puts on
 * the front of every post. The reset buttons beside each one are the only place
 * the two rating types are offered side by side, which is why their naming kept
 * drifting away from the type chooser's.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const {
	ALLOW,
	CHECK,
	configure,
	createRatedPost,
	openSettings,
	resetTemplates,
	saveSettings,
} = require( './helpers' );

test.describe( 'Ratings templates', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	// These tests type into the markup every post is wrapped in, so they put it
	// back. Left as they found it, the next file's assertions about a score are
	// really assertions about a template nobody meant to change.
	test.afterEach( async ( { page } ) => {
		await resetTemplates( page );
	} );

	test( 'the reset buttons are named for the two rating types', async ( { page } ) => {
		await openSettings( page, 'templates' );

		const buttons = page.locator(
			'.wp-postratings-restore-template[data-template="vote"]',
		);

		await expect( buttons ).toHaveCount( 2 );

		// The chooser on the other tab says "Scale" and "Up or down". These said
		// "Normal Rating" and "Up/Down Rating", and the markup said 'default'.
		await expect( buttons.nth( 0 ) ).toHaveText( 'Reset to default (Scale)' );
		await expect( buttons.nth( 1 ) ).toHaveText( 'Reset to default (Up or down)' );

		await expect( buttons.nth( 0 ) ).toHaveAttribute( 'data-variant', 'scale' );
		await expect( buttons.nth( 1 ) ).toHaveAttribute( 'data-variant', 'updown' );
	} );

	test( 'each reset button fills the textarea with its own type\'s default', async ( {
		page,
	} ) => {
		await openSettings( page, 'templates' );

		const textarea = page.locator( '#wp_postratings_template_vote' );

		await textarea.fill( 'NONSENSE' );

		await page
			.locator( '.wp-postratings-restore-template[data-template="vote"][data-variant="updown"]' )
			.click();

		const updown = await textarea.inputValue();
		expect( updown ).toContain( '%RATINGS_IMAGES_VOTE%' );

		await page
			.locator( '.wp-postratings-restore-template[data-template="vote"][data-variant="scale"]' )
			.click();

		const scale = await textarea.inputValue();
		expect( scale ).toContain( '%RATINGS_IMAGES_VOTE%' );

		// The two differ: a scale counts out of a maximum, an up/down pair does
		// not. A single set for both is what the one "Restore Default" button
		// used to give.
		expect( scale ).not.toBe( updown );
	} );

	test( 'a saved template is what the post shows', async ( { page, requestUtils } ) => {
		await configure( page, { check: CHECK.never, allow: ALLOW.everyone } );

		await openSettings( page, 'templates' );

		await page
			.locator( '#wp_postratings_template_none' )
			.fill( 'NOBODY HAS RATED THIS YET %RATINGS_IMAGES_VOTE%' );

		await saveSettings( page );

		const post = await createRatedPost( requestUtils, 'Template on the front' );
		await page.goto( post.link );

		await expect( page.locator( '.wp-postratings' ).first() ).toContainText(
			'NOBODY HAS RATED THIS YET',
		);

		// The token still expanded, so the control is there to click.
		await expect( page.locator( '.wp-postratings-vote' ) ).toBeVisible();
	} );

	test( 'saving one tab does not blank the other', async ( { page } ) => {
		// One registered setting, two tabs. A sanitizer that rebuilt the whole
		// array from the submission would clear whichever tab was not posted.
		await openSettings( page, 'templates' );
		await page.locator( '#wp_postratings_template_text' ).fill( 'KEEP ME %RATINGS_IMAGES%' );
		await saveSettings( page );

		await openSettings( page );
		await page.locator( '#wp_postratings_check_method' ).selectOption( '2' );
		await saveSettings( page );

		await openSettings( page, 'templates' );
		await expect( page.locator( '#wp_postratings_template_text' ) ).toHaveValue(
			'KEEP ME %RATINGS_IMAGES%',
		);

		await openSettings( page );
		await expect( page.locator( '#wp_postratings_check_method' ) ).toHaveValue( '2' );
	} );

	test( 'the templates survive a reset back to their defaults', async ( { page } ) => {
		await resetTemplates( page );

		await openSettings( page, 'templates' );

		await expect( page.locator( '#wp_postratings_template_text' ) ).toHaveValue(
			/%RATINGS_IMAGES%/,
		);
	} );
} );

test.describe( 'Google rich snippets', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test( 'off by default, and nothing is emitted', async ( { page, requestUtils } ) => {
		await configure( page, { check: CHECK.never, allow: ALLOW.everyone, schema: '' } );

		const post = await createRatedPost( requestUtils, 'No snippet here' );
		await page.goto( post.link );

		await expect( page.locator( '[itemtype*="schema.org"]' ) ).toHaveCount( 0 );
	} );

	test( 'choosing a type marks the rating up as that type', async ( {
		page,
		requestUtils,
	} ) => {
		await configure( page, {
			check: CHECK.never,
			allow: ALLOW.everyone,
			schema: 'Product',
		} );

		const post = await createRatedPost( requestUtils, 'A product with a rating' );
		await page.goto( post.link );

		await rateFirst( page );

		await page.reload();

		const scope = page.locator( '[itemtype="https://schema.org/Product"]' );

		await expect( scope ).toHaveCount( 1 );

		// The aggregate is the only reason to emit any of this, so it has to be
		// inside the scope rather than beside it.
		await expect( scope.locator( '[itemprop="ratingValue"]' ) ).toHaveCount( 1 );
		await expect( scope.locator( '[itemprop="ratingCount"]' ) ).toHaveCount( 1 );
	} );

	test( 'the type list offers only what Google supports', async ( { page } ) => {
		await openSettings( page );

		const labels = await page
			.locator( '#wp_postratings_schema_type option' )
			.evaluateAll( ( options ) => options.map( ( option ) => option.textContent.trim() ) );

		// Article, BlogPosting and NewsArticle are absent on purpose: Google shows
		// no review snippet for them, which is why the old output produced nothing
		// on any post, ever.
		expect( labels ).toContain( 'Product' );
		expect( labels ).not.toContain( 'Article' );
		expect( labels ).not.toContain( 'BlogPosting' );
		expect( labels[ 0 ] ).toBe( 'No' );
	} );
} );

/**
 * Cast one vote, so there is an aggregate to mark up.
 *
 * @param {import('@playwright/test').Page} page Page showing the post.
 * @return {Promise<void>} Resolves once the vote has registered.
 */
async function rateFirst( page ) {
	const control = page.locator( '.wp-postratings-vote' ).first();
	const id = await control.evaluate( ( el ) => el.dataset.postId );

	await page.locator( `label[for="wp-postratings-${ id }-4"]` ).click();

	await expect( page.getByRole( 'img', { name: /average: 4\.00/ } ) ).toBeVisible( {
		timeout: 15_000,
	} );
}
