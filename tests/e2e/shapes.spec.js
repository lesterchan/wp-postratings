/**
 * The three shape families, drawn by a real browser.
 *
 * A scale repeats one mask once per point, an up/down pair is two opposing
 * masks, and a numeric scale has no mask at all -- the position is the glyph.
 * PHPUnit can prove the plugin wrote the right custom property; only a browser
 * can say whether the property turned into a shape, and every failure this file
 * exists for looked correct in the markup:
 *
 * - a mask whose data URI carries a raw quote, which makes the browser discard
 *   the whole declaration silently and paint nothing;
 * - one glyph used for both directions of an up/down pair;
 * - a numeric digit inside the label, which joins the radio's accessible name
 *   unless it is hidden, so every value announces itself twice;
 * - padding on the bar, which slides the fill layer out from under the track.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const { ALLOW, CHECK, configure, createRatedPost, uniqueTitle } = require( './helpers.js' );

/**
 * Publish a post showing the rating read-only.
 *
 * A post nobody has rated shows the *control*, so the track-and-fill strip has
 * no way onto the page through [ratings] alone -- and the strip is what three
 * of the tests below are about. results="true" renders it whatever the score is.
 *
 * @param {Object} requestUtils The e2e-test-utils request helper.
 * @param {string} title        Post title.
 * @return {Promise<Object>} The created post.
 */
function createResultsPost( requestUtils, title ) {
	return requestUtils.createPost( {
		title,
		content: '[ratings results="true"]',
		status: 'publish',
	} );
}

/**
 * The mask each glyph is cut from, as the browser resolved it.
 *
 * Read from the computed style rather than from the attribute, because that is
 * the whole question: an inline custom property can be present and still not
 * become a mask.
 *
 * @param {import('@playwright/test').Page} page  Page showing the rating.
 * @param {string}                          scope Selector for the rating.
 * @return {Promise<string[]>} One entry per glyph, in document order.
 */
async function masks( page, scope ) {
	const rating = page.locator( scope ).first();

	await expect( rating ).toBeVisible( { timeout: 15_000 } );

	return rating.evaluate( ( el ) =>
		Array.from( el.querySelectorAll( '.wp-postratings-item' ) ).map( ( item ) => {
			const style = getComputedStyle( item );

			return ( style.maskImage || style.webkitMaskImage || 'none' ).replace( /\s/g, '' );
		} ),
	);
}

/**
 * What each glyph of a rating reads as, in document order.
 *
 * @param {import('@playwright/test').Page} page  Page showing the rating.
 * @param {string}                          scope Selector for the rating.
 * @return {Promise<string[]>} The glyphs' text.
 */
async function glyphText( page, scope ) {
	return page
		.locator( scope )
		.first()
		.evaluate( ( el ) =>
			Array.from( el.querySelectorAll( '.wp-postratings-item' ) ).map( ( item ) =>
				item.textContent.trim(),
			),
		);
}

test.describe( 'The three shape families', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test( 'a mask shape repeats one glyph once per point', async ( { page, requestUtils } ) => {
		await configure( page, { shape: 'star', check: CHECK.never, allow: ALLOW.everyone } );

		const post = await createResultsPost( requestUtils, uniqueTitle( 'Rated in stars' ) );
		await page.goto( post.link );

		const drawn = await masks( page, '.wp-postratings-strip' );

		// Five points in the track and five in the fill laid over it.
		expect( drawn ).toHaveLength( 10 );

		// Every one of them is a mask the browser accepted. "none" is what a data
		// URI carrying a raw quote resolves to, and it is invisible rather than
		// broken-looking, so nothing else in the suite would notice.
		drawn.forEach( ( mask ) => expect( mask ).toContain( 'data:image/svg+xml' ) );

		// And they are all the same one: that is what makes a scale a scale.
		expect( new Set( drawn ).size ).toBe( 1 );
	} );

	test( 'an up or down pair draws two glyphs that differ', async ( { page, requestUtils } ) => {
		await configure( page, {
			type: 'updown',
			shape: 'thumb',
			check: CHECK.never,
			allow: ALLOW.everyone,
		} );

		const post = await createRatedPost( requestUtils, uniqueTitle( 'Rated up or down' ) );
		await page.goto( post.link );

		const drawn = await masks( page, '.wp-postratings-vote' );

		expect( drawn ).toHaveLength( 2 );
		drawn.forEach( ( mask ) => expect( mask ).toContain( 'data:image/svg+xml' ) );

		// The pair is the point. A set of one here is the bug where both buttons
		// took the up glyph and a down vote drew a thumbs up.
		expect( new Set( drawn ).size ).toBe( 2 );
	} );

	test( 'a numeric scale draws its positions and carries no mask', async ( {
		page,
		requestUtils,
	} ) => {
		await configure( page, { shape: 'number', check: CHECK.never, allow: ALLOW.everyone } );

		const post = await createResultsPost( requestUtils, uniqueTitle( 'Rated in numbers' ) );
		await page.goto( post.link );

		// No mask, and no empty url() either: url() wrapped around an empty data
		// URI is a parse error, and the browser drops the declaration it is in.
		const drawn = await masks( page, '.wp-postratings-strip' );

		expect( drawn ).toHaveLength( 10 );
		drawn.forEach( ( mask ) => expect( mask ).toBe( 'none' ) );

		// The glyph differs per position, which is the reason a numeric shape
		// could not be registered as an ordinary one.
		expect( await glyphText( page, '.wp-postratings-strip' ) ).toEqual( [
			'1',
			'2',
			'3',
			'4',
			'5',
			'1',
			'2',
			'3',
			'4',
			'5',
		] );
	} );

	test( 'a numeric digit is drawn upright', async ( { page, requestUtils } ) => {
		await configure( page, { shape: 'number', check: CHECK.never, allow: ALLOW.everyone } );

		const post = await createResultsPost( requestUtils, uniqueTitle( 'Rated upright' ) );
		await page.goto( post.link );

		// The glyph is an <i>, which every other shape leaves empty and the
		// browser's own stylesheet italicises. Nothing in the markup says slanted,
		// which is why this is asserted here and not in PHPUnit.
		const style = await page
			.locator( '.wp-postratings-strip .wp-postratings-item' )
			.first()
			.evaluate( ( el ) => getComputedStyle( el ).fontStyle );

		expect( style ).toBe( 'normal' );
	} );

	test( 'a numeric value announces itself once', async ( { page, requestUtils } ) => {
		await configure( page, { shape: 'number', check: CHECK.never, allow: ALLOW.everyone } );

		const post = await createRatedPost( requestUtils, uniqueTitle( 'Announced once' ) );
		await page.goto( post.link );

		await expect( page.locator( '.wp-postratings-scale' ) ).toBeVisible( { timeout: 15_000 } );

		// A label's text is the radio's accessible name, and a numeric glyph is
		// real text inside it. Left exposed it is read as part of the name and
		// every value says itself twice -- "3 3 Stars". Only the browser computes
		// this name, so only the browser can catch it.
		await expect( page.getByRole( 'radio', { name: '3 Stars', exact: true } ) ).toHaveCount( 1 );
	} );

	test( 'a numeric bar is no taller than its glyphs, and its layers line up', async ( {
		page,
		requestUtils,
	} ) => {
		await configure( page, { shape: 'number', check: CHECK.never, allow: ALLOW.everyone } );

		const post = await createResultsPost( requestUtils, uniqueTitle( 'Rated in a bar' ) );
		await page.goto( post.link );

		const strip = page.locator( '.wp-postratings-strip' ).first();

		await expect( strip ).toBeVisible( { timeout: 15_000 } );

		const geometry = await strip.evaluate( ( el ) => {
			const track = el.querySelector( '.wp-postratings-track .wp-postratings-row' );
			const fill = el.querySelector( '.wp-postratings-fill .wp-postratings-row' );

			return {
				barHeight: el.getBoundingClientRect().height,
				glyphHeight: el.querySelector( '.wp-postratings-item' ).getBoundingClientRect()
					.height,
				trackStart: track.getBoundingClientRect().left,
				fillStart: fill.getBoundingClientRect().left,
			};
		} );

		// The rules along the top and bottom are inset shadows rather than a
		// border, so the bar is exactly as tall as the glyphs in it. A border all
		// round makes it two pixels taller and pushes every digit down by one.
		expect( geometry.barHeight ).toBeCloseTo( geometry.glyphHeight, 1 );

		// And the two layers start at the same place. They come apart the moment
		// anything puts padding on the bar, because the fill is positioned against
		// the padding box while the track sits in the content box -- which draws
		// one row of digits over another, offset, and nothing else would see it.
		expect( geometry.fillStart ).toBeCloseTo( geometry.trackStart, 1 );
	} );
} );
