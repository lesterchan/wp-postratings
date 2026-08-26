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

const {
	ALLOW,
	CHECK,
	COLORS,
	configure,
	createRatedPost,
	openSettings,
	rgb,
	saveSettings,
	stepFills,
	uniqueTitle,
	wpEval,
} = require( './helpers.js' );

/** The two scale families, which differ in how a point is drawn and in nothing else. */
const SCALES = [ 'star', 'number' ];

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

/**
 * The colour each step of a scale is currently drawn in.
 *
 * Read from the label rather than from the glyph inside it, because the label is
 * where the state lives for every scale shape: a mask takes its colour from
 * there through currentColor, and a numeric cell takes both its digit and its
 * tint from there. One reading therefore covers both families.
 *
 * @param {import('@playwright/test').Page} page Page showing the control.
 * @return {Promise<Object>} Step number to computed colour.
 */
function labelColors( page ) {
	return page
		.locator( '.wp-postratings-scale' )
		.first()
		.evaluate( ( el ) => {
			const colors = {};

			el.querySelectorAll( 'label[for]' ).forEach( ( label ) => {
				const step = Number( label.getAttribute( 'for' ).split( '-' ).pop() );

				colors[ step ] = getComputedStyle( label ).color;
			} );

			return colors;
		} );
}

/**
 * Put the pointer on one point of a scale and leave it there.
 *
 * @param {import('@playwright/test').Page} page   Page showing the control.
 * @param {number}                          postId Post being rated.
 * @param {number}                          step   Which point to hover.
 * @return {Promise<void>} Resolves once the pointer has landed.
 */
async function hoverStep( page, postId, step ) {
	await page.locator( `label[for="wp-postratings-${ postId }-${ step }"]` ).hover();
}

/**
 * Click a point on a scale, the way a visitor does.
 *
 * The radio itself is visually hidden behind its label -- that is how the shapes
 * are drawn -- so the label is what takes the click.
 *
 * @param {import('@playwright/test').Page} page   Page showing the control.
 * @param {number}                          postId Post being rated.
 * @param {number}                          step   Which point to click.
 * @return {Promise<void>} Resolves once the click has been sent.
 */
async function clickStep( page, postId, step ) {
	await page.locator( `label[for="wp-postratings-${ postId }-${ step }"]` ).click();
}

/**
 * Give a post a score without going through the control.
 *
 * @param {number} postId  Post to seed.
 * @param {number} users   Voter count.
 * @param {number} score   Total score.
 * @param {number} average Average.
 * @return {void}
 */
function seedRating( postId, users, score, average ) {
	wpEval(
		`update_post_meta( ${ postId }, 'ratings_users', ${ users } );
		update_post_meta( ${ postId }, 'ratings_score', ${ score } );
		update_post_meta( ${ postId }, 'ratings_average', ${ average } );
		echo '<<<seeded>>>';`,
	);
}

/**
 * How much of the read-only strip the browser actually painted, as a fraction.
 *
 * @param {import('@playwright/test').Page} page Page showing the strip.
 * @return {Promise<number>} The filled share of the bar, 0 to 1.
 */
function filledShare( page ) {
	return page
		.locator( '.wp-postratings-strip' )
		.first()
		.evaluate( ( el ) => {
			const track = el.querySelector( '.wp-postratings-track .wp-postratings-row' );
			const fill = el.querySelector( '.wp-postratings-fill' );

			return fill.getBoundingClientRect().width / track.getBoundingClientRect().width;
		} );
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

test.describe( 'What a shape shows for a score', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	for ( const shape of SCALES ) {
		test( `nothing is lit on an unrated ${ shape } scale`, async ( { page, requestUtils } ) => {
			await configure( page, { shape, check: CHECK.never, allow: ALLOW.everyone } );

			const post = await createRatedPost( requestUtils, uniqueTitle( `Unrated ${ shape }` ) );
			await page.goto( post.link );

			// No score, so no point on the scale carries any of it.
			expect( await stepFills( page ) ).toEqual( { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 } );

			// Somewhere the pointer is not. configure() leaves it wherever it last
			// clicked on the settings screen, and that position survives the
			// navigation -- so without this the scale can be under it, and a test
			// about the resting state reads a hover.
			await page.mouse.move( 0, 0 );

			// And every point reads unrated. A scale that opened already coloured
			// would tell a visitor they had rated a post they had not.
			await expect.poll( () => labelColors( page ) ).toEqual( {
				1: rgb( COLORS.unrated ),
				2: rgb( COLORS.unrated ),
				3: rgb( COLORS.unrated ),
				4: rgb( COLORS.unrated ),
				5: rgb( COLORS.unrated ),
			} );
		} );

		test( `a rated ${ shape } scale opens showing the score so far`, async ( {
			page,
			requestUtils,
		} ) => {
			await configure( page, { shape, check: CHECK.never, allow: ALLOW.everyone } );

			const post = await createRatedPost( requestUtils, uniqueTitle( `Rated ${ shape }` ) );

			seedRating( post.id, 2, 7, 3.5 );

			await page.goto( post.link );

			// 3.5 of 5: three points full, half of the fourth, nothing on the fifth.
			// A numeric scale paints that share into the digit with background-clip
			// rather than through a mask, so the two families reach it differently
			// and both are asserted.
			expect( await stepFills( page ) ).toEqual( { 1: 100, 2: 100, 3: 100, 4: 50, 5: 0 } );
		} );

		test( `hovering a ${ shape } scale lights that point and everything below`, async ( {
			page,
			requestUtils,
		} ) => {
			await configure( page, { shape, check: CHECK.never, allow: ALLOW.everyone } );

			const post = await createRatedPost( requestUtils, uniqueTitle( `Hovered ${ shape }` ) );
			await page.goto( post.link );

			await expect( page.locator( '.wp-postratings-scale' ) ).toBeVisible( {
				timeout: 15_000,
			} );

			await hoverStep( page, post.id, 3 );

			/*
			 * Hovering is the one behaviour with no PHP behind it at all.
			 *
			 * Before 2.0.0 a script rewrote every image's src on mouseover; now the
			 * row is laid out in reverse so a plain sibling combinator can reach
			 * backwards from the hovered label. Nothing but a browser can say
			 * whether that works, and nothing in the suite asked until now.
			 */
			/*
			 * Polled, because the colour is transitioned and not switched.
			 *
			 * The stylesheet eases it over 120ms, so reading straight after the
			 * pointer lands catches a blend on its way -- and a blend is a real
			 * colour that equals neither end, so the assertion fails on a control
			 * that is working. What is being asserted is where it arrives.
			 */
			await expect.poll( () => labelColors( page ) ).toEqual( {
				1: rgb( COLORS.rated ),
				2: rgb( COLORS.rated ),
				3: rgb( COLORS.rated ),
				4: rgb( COLORS.unrated ),
				5: rgb( COLORS.unrated ),
			} );
		} );
	}

	test( 'a hovered numeric point tints its whole cell, not just the digit', async ( {
		page,
		requestUtils,
	} ) => {
		await configure( page, { shape: 'number', check: CHECK.never, allow: ALLOW.everyone } );

		const post = await createRatedPost( requestUtils, uniqueTitle( 'Hovered cell' ) );
		await page.goto( post.link );

		await expect( page.locator( '.wp-postratings-scale' ) ).toBeVisible( { timeout: 15_000 } );

		await hoverStep( page, post.id, 3 );

		// The cell is what carries a numeric point's state -- that is the whole
		// difference between this shape and a mask one -- so the lit side of the
		// boundary has to differ from the unlit side by more than the digit.
		const cells = await page
			.locator( '.wp-postratings-scale' )
			.first()
			.evaluate( ( el, id ) => {
				const read = ( step ) =>
					getComputedStyle(
						el.querySelector( `label[for="wp-postratings-${ id }-${ step }"]` ),
					).backgroundColor;

				return { lit: read( 3 ), unlit: read( 4 ) };
			}, post.id );

		expect( cells.lit ).not.toBe( cells.unlit );
		expect( cells.lit ).not.toBe( 'rgba(0, 0, 0, 0)' );
	} );

	for ( const shape of SCALES ) {
		test( `the ${ shape } result strip fills in proportion to the score`, async ( {
			page,
			requestUtils,
		} ) => {
			await configure( page, { shape, check: CHECK.never, allow: ALLOW.everyone } );

			const post = await createResultsPost( requestUtils, uniqueTitle( `Strip ${ shape }` ) );

			await page.goto( post.link );

			// Unrated: the fill layer is there and covers nothing.
			expect( await filledShare( page ) ).toBeCloseTo( 0, 2 );

			seedRating( post.id, 2, 7, 3.5 );
			await page.reload();

			// 3.5 of 5 is 70% of the bar, painted rather than rounded to the nearest
			// half glyph the way the pre-2.0.0 image sets had to be.
			expect( await filledShare( page ) ).toBeCloseTo( 0.7, 2 );
		} );
	}

	test( 'a numeric vote comes back as digits, in place', async ( { page, requestUtils } ) => {
		await configure( page, { shape: 'number', check: CHECK.never, allow: ALLOW.everyone } );

		const post = await createRatedPost( requestUtils, uniqueTitle( 'Voted in numbers' ) );
		await page.goto( post.link );

		await expect( page.locator( '.wp-postratings-scale' ) ).toBeVisible( { timeout: 15_000 } );

		await clickStep( page, post.id, 4 );

		/*
		 * The reply is rendered in the vote request, not the page request.
		 *
		 * Nothing reloads: the script posts the vote and puts whatever comes back
		 * into the container. So the shape has to survive a second trip through
		 * the renderer in a different request, and for a numeric scale that is the
		 * one path in the plugin where no page load has drawn the digits first.
		 */
		await expect(
			page.getByRole( 'img', { name: /average: 4\.00/ } ),
		).toBeVisible( { timeout: 15_000 } );

		// The control is gone, replaced rather than added to.
		await expect( page.locator( '.wp-postratings-vote' ) ).toHaveCount( 0 );

		// And what replaced it is still a numeric scale: digits, and no mask.
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

		( await masks( page, '.wp-postratings-strip' ) ).forEach( ( mask ) =>
			expect( mask ).toBe( 'none' ),
		);

		// Four of five, painted rather than rounded to the nearest whole digit.
		expect( await filledShare( page ) ).toBeCloseTo( 0.8, 2 );
	} );

	test( 'a ten point numeric scale keeps every cell the same width', async ( {
		page,
		requestUtils,
	} ) => {
		await configure( page, { shape: 'number', check: CHECK.never, allow: ALLOW.everyone } );

		// Five more steps, through the screen that adds them.
		await openSettings( page );

		for ( let i = 0; i < 5; i++ ) {
			await page.locator( '#wp-postratings-add-step' ).click();
		}

		await expect( page.locator( '#wp-postratings-rating-fields tbody tr' ) ).toHaveCount( 10 );
		await saveSettings( page );

		const post = await createResultsPost( requestUtils, uniqueTitle( 'Rated out of ten' ) );

		seedRating( post.id, 2, 10, 5 );
		await page.goto( post.link );

		expect( await glyphText( page, '.wp-postratings-strip' ) ).toEqual(
			[ ...Array( 10 ) ].map( ( _, i ) => String( i + 1 ) ).concat(
				[ ...Array( 10 ) ].map( ( _, i ) => String( i + 1 ) ),
			),
		);

		const geometry = await page
			.locator( '.wp-postratings-strip' )
			.first()
			.evaluate( ( el ) => {
				const items = Array.from(
					el.querySelectorAll( '.wp-postratings-track .wp-postratings-item' ),
				);
				const row = el
					.querySelector( '.wp-postratings-track .wp-postratings-row' )
					.getBoundingClientRect();
				const fill = el.querySelector( '.wp-postratings-fill' ).getBoundingClientRect();

				return {
					widths: items.map( ( i ) => i.getBoundingClientRect().width ),
					fifthEnds: items[ 4 ].getBoundingClientRect().right - row.left,
					fillEnds: fill.right - row.left,
				};
			} );

		/*
		 * Ten is two glyphs wide where every other point is one.
		 *
		 * The cell reserves the shape's size and the digits are tabular, so two
		 * of them come to about 95% of it and the last cell stays the width of
		 * the rest. That is a close enough thing to be worth holding: widen the
		 * font against the cell and the tenth cell grows, which is not just
		 * untidy -- the fill is a percentage of the whole bar, so a bar whose
		 * cells are not equal fills to the wrong place.
		 */
		geometry.widths.forEach( ( width ) =>
			expect( width ).toBeCloseTo( geometry.widths[ 0 ], 1 ),
		);

		// Five of ten, so the boundary is the edge of the fifth cell.
		expect( geometry.fillEnds ).toBeCloseTo( geometry.fifthEnds, 1 );
	} );

	test( 'an up or down button takes its own colour under the pointer', async ( {
		page,
		requestUtils,
	} ) => {
		await configure( page, {
			type: 'updown',
			shape: 'thumb',
			check: CHECK.never,
			allow: ALLOW.everyone,
		} );

		const post = await createRatedPost( requestUtils, uniqueTitle( 'Hovered thumb' ) );
		await page.goto( post.link );

		const up = page.getByRole( 'button', { name: 'Vote Up' } );
		const down = page.getByRole( 'button', { name: 'Vote Down' } );

		await expect( up ).toBeVisible( { timeout: 15_000 } );

		const colorOf = ( button ) => button.evaluate( ( el ) => getComputedStyle( el ).color );

		// The pointer is wherever the settings screen left it, which can be over
		// one of these buttons once the post has loaded.
		await page.mouse.move( 0, 0 );

		// Neither side is chosen until the pointer says so, so both rest unrated.
		// Polled throughout: these colours are eased over 120ms, so every reading
		// taken the instant the pointer moves is of a blend on its way somewhere.
		await expect.poll( () => colorOf( up ) ).toBe( rgb( COLORS.unrated ) );
		await expect.poll( () => colorOf( down ) ).toBe( rgb( COLORS.unrated ) );

		// And each takes its own colour rather than the pair taking one: two
		// opposing actions read green and red, which is the case per-step colours
		// exist for.
		await up.hover();
		await expect.poll( () => colorOf( up ) ).toBe( rgb( COLORS.up ) );
		await expect.poll( () => colorOf( down ) ).toBe( rgb( COLORS.unrated ) );

		await down.hover();
		await expect.poll( () => colorOf( down ) ).toBe( rgb( COLORS.down ) );
		await expect.poll( () => colorOf( up ) ).toBe( rgb( COLORS.unrated ) );
	} );
} );
