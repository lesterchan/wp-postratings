/**
 * The rating a comment author gave, shown under their comment.
 *
 * Off unless a theme asks for it, which is why it had never been on a page: a
 * fixture has to turn the filter on before there is anything to look at. What
 * that turned up is the reason this file exists. The lookup ran off
 * `$GLOBALS['comment']`, which a classic theme's comment loop sets and a block
 * theme never does -- it renders each comment through the comment-template
 * block and passes the comment down as block context instead. So on every
 * default theme since 2022 the filter handed the comment body straight back and
 * the feature looked switched off.
 *
 * PHPUnit could not have caught that: its comment loop is one the test set up
 * itself, with the global filled in by hand. The question is what the theme
 * does, and that is a question about a page.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const {
	ALLOW,
	CHECK,
	configure,
	installFixtures,
	removeFixtures,
	showCommentAuthorRatings,
	uniqueTitle,
} = require( './helpers.js' );

/**
 * Publish a post that takes comments.
 *
 * @param {Object} requestUtils The e2e-test-utils request helper.
 * @param {string} title        Post title.
 * @return {Promise<Object>} The created post.
 */
function commentablePost( requestUtils, title ) {
	return requestUtils.createPost( {
		title,
		content: '[ratings]',
		status: 'publish',
		comment_status: 'open',
	} );
}

/**
 * Cast a vote and wait for the post to show it.
 *
 * @param {import('@playwright/test').Page} page    Page showing the post.
 * @param {number}                          rating  Which point to click.
 * @param {string}                          average Average to wait for.
 * @return {Promise<void>} Resolves once the rating has registered.
 */
async function rate( page, rating, average ) {
	const control = page.locator( '.wp-postratings-vote' ).first();
	const id = await control.evaluate( ( el ) => el.dataset.postId );

	await page.locator( `label[for="wp-postratings-${ id }-${ rating }"]` ).click();

	await expect(
		page.getByRole( 'img', { name: new RegExp( `average: ${ average.replace( '.', '\\.' ) }` ) } ),
	).toBeVisible( { timeout: 15_000 } );
}

/**
 * Leave an approved comment on a post, as whoever is signed in.
 *
 * Approved on purpose: a first comment from an address with no approved comment
 * behind it is held for moderation, and a held comment is not what a visitor
 * reading the post would see.
 *
 * @param {Object} requestUtils The e2e-test-utils request helper.
 * @param {number} postId       Post to comment on.
 * @param {string} content      What the comment says.
 * @return {Promise<Object>} The created comment.
 */
function comment( requestUtils, postId, content ) {
	return requestUtils.createComment( { post: postId, content, status: 'approved' } );
}

test.describe( 'A comment author’s rating', () => {
	/** The display name the rating and the comment are both recorded under. */
	let author;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllComments();
		await requestUtils.deleteAllPosts();

		author = ( await requestUtils.rest( { path: '/wp/v2/users/me' } ) ).name;

		installFixtures();
		showCommentAuthorRatings( true );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		showCommentAuthorRatings( false );
		removeFixtures();

		await requestUtils.deleteAllComments();
	} );

	test( 'is shown under the comment they left', async ( { page, requestUtils } ) => {
		await configure( page, { check: CHECK.never, allow: ALLOW.everyone } );

		const post = await commentablePost( requestUtils, uniqueTitle( 'Rated and commented' ) );

		await page.goto( post.link );
		await rate( page, 4, '4.00' );

		await comment( requestUtils, post.id, 'Four out of five from me.' );

		await page.goto( post.link );

		const note = page.locator( '.wp-postratings-comment-author' );

		await expect( note ).toBeVisible( { timeout: 15_000 } );
		await expect( note ).toContainText( `${ author } ratings for this post:` );

		// The strip itself, not just the sentence: what is appended is a rendered
		// rating, and it carries the score in its own accessible name.
		await expect(
			note.getByRole( 'img', { name: `${ author } gives a rating of 4` } ),
		).toBeVisible();
	} );

	test( 'says so when they did not rate', async ( { page, requestUtils } ) => {
		await configure( page, { check: CHECK.never, allow: ALLOW.everyone } );

		const post = await commentablePost( requestUtils, uniqueTitle( 'Commented only' ) );

		await comment( requestUtils, post.id, 'No opinion on the rating.' );

		await page.goto( post.link );

		const note = page.locator( '.wp-postratings-comment-author' );

		await expect( note ).toBeVisible( { timeout: 15_000 } );
		await expect( note ).toContainText( `${ author } did not rate this post.` );
		await expect( note.locator( '.wp-postratings-strip' ) ).toHaveCount( 0 );
	} );

	test( 'is drawn in the shape the site chose', async ( { page, requestUtils } ) => {
		await configure( page, {
			shape: 'number',
			check: CHECK.never,
			allow: ALLOW.everyone,
		} );

		const post = await commentablePost( requestUtils, uniqueTitle( 'Numbered comment' ) );

		await page.goto( post.link );
		await rate( page, 2, '2.00' );

		await comment( requestUtils, post.id, 'Two.' );

		await page.goto( post.link );

		const strip = page.locator( '.wp-postratings-comment-author .wp-postratings-strip' );

		await expect( strip ).toBeVisible( { timeout: 15_000 } );

		// The same renderer the post uses, so a shape that only works in one of
		// the two places is a shape drawn twice.
		expect(
			await strip.evaluate( ( el ) =>
				Array.from( el.querySelectorAll( '.wp-postratings-track .wp-postratings-item' ) ).map(
					( item ) => item.textContent.trim(),
				),
			),
		).toEqual( [ '1', '2', '3', '4', '5' ] );
	} );
} );
