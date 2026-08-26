/**
 * The lists a rating feeds: the widget, and sorting a loop by score.
 *
 * Neither had ever been on a page. The widget offers fourteen kinds of list --
 * most rated, highest rated, lowest rated, each of those by category and by
 * time range -- and they are the only two things that render through the
 * `highestrated` and `mostrated` templates, so nothing had drawn those either.
 * Sorting is two public query variables hooked onto pre_get_posts, which is a
 * URL going in and an order coming out: readable only from a rendered page.
 *
 * The widget is reached through the plugin's own widget class rather than by
 * placing one in a sidebar, because placing one is a test of the widgets editor
 * and of whatever the active theme does with sidebars. What is under test here
 * is the list.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const {
	ALLOW,
	CHECK,
	configure,
	installFixtures,
	removeFixtures,
	uniqueTitle,
	wpEval,
} = require( './helpers.js' );

/**
 * Publish a post with a score already on it.
 *
 * Seeded rather than voted, because a list is about several posts holding
 * different scores and voting each one through the browser would be a test of
 * voting, which is covered elsewhere.
 *
 * @param {Object} requestUtils The e2e-test-utils request helper.
 * @param {string} title        Post title.
 * @param {number} users        Voter count.
 * @param {number} score        Total score.
 * @return {Promise<Object>} The created post.
 */
async function ratedPost( requestUtils, title, users, score ) {
	const post = await requestUtils.createPost( { title, content: title, status: 'publish' } );

	wpEval(
		`update_post_meta( ${ post.id }, 'ratings_users', ${ users } );
		update_post_meta( ${ post.id }, 'ratings_score', ${ score } );
		update_post_meta( ${ post.id }, 'ratings_average', ${ ( score / users ).toFixed( 2 ) } );
		echo '<<<seeded>>>';`,
	);

	return post;
}

/**
 * The posts a list names, in the order it names them.
 *
 * Matched by permalink rather than by title text, because a theme decides how a
 * title is marked up and this is not a test of the theme.
 *
 * @param {import('@playwright/test').Page} page  Page showing the list.
 * @param {Object[]}                        posts Posts to look for.
 * @param {string}                          scope Selector to look inside.
 * @return {Promise<string[]>} The titles found, in document order.
 */
function orderOf( page, posts, scope ) {
	return page.evaluate(
		( { links, within } ) => {
			const root = document.querySelector( within );

			if ( ! root ) {
				return [ 'no list' ];
			}

			return Array.from( root.querySelectorAll( 'a[href]' ) )
				.map( ( anchor ) => {
					const found = links.find(
						( link ) => anchor.href.replace( /\/$/, '' ) === link.url.replace( /\/$/, '' ),
					);

					return found ? found.title : null;
				} )
				.filter( ( title, index, all ) => title && all.indexOf( title ) === index );
		},
		{ links: posts.map( ( p ) => ( { url: p.link, title: p.title.raw } ) ), within: scope },
	);
}

test.describe( 'Lists a rating feeds', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		installFixtures();
	} );

	test.afterAll( async () => {
		removeFixtures();
	} );

	test( 'the highest rated list puts the best score first', async ( { page, requestUtils } ) => {
		await configure( page, { check: CHECK.never, allow: ALLOW.everyone } );

		const middling = await ratedPost( requestUtils, uniqueTitle( 'Middling' ), 4, 12 );
		const best = await ratedPost( requestUtils, uniqueTitle( 'Best' ), 4, 20 );
		const worst = await ratedPost( requestUtils, uniqueTitle( 'Worst' ), 4, 4 );

		const host = await requestUtils.createPost( {
			title: uniqueTitle( 'Highest rated list' ),
			content: '[ratings_widget type="highest_rated"]',
			status: 'publish',
		} );

		await page.goto( host.link );

		await expect( page.locator( '.e2e-widget' ) ).toBeVisible( { timeout: 15_000 } );

		expect( await orderOf( page, [ best, middling, worst ], '.e2e-widget' ) ).toEqual( [
			best.title.raw,
			middling.title.raw,
			worst.title.raw,
		] );
	} );

	test( 'the most rated list counts votes rather than score', async ( {
		page,
		requestUtils,
	} ) => {
		await configure( page, { check: CHECK.never, allow: ALLOW.everyone } );

		// The order is the reverse of the one above, which is the point: a post
		// can be rated often and rated badly, and the two lists are different
		// questions rather than one list sorted twice.
		const popular = await ratedPost( requestUtils, uniqueTitle( 'Popular' ), 9, 9 );
		const rare = await ratedPost( requestUtils, uniqueTitle( 'Rare' ), 2, 10 );

		const host = await requestUtils.createPost( {
			title: uniqueTitle( 'Most rated list' ),
			content: '[ratings_widget type="most_rated"]',
			status: 'publish',
		} );

		await page.goto( host.link );

		await expect( page.locator( '.e2e-widget' ) ).toBeVisible( { timeout: 15_000 } );

		expect( await orderOf( page, [ popular, rare ], '.e2e-widget' ) ).toEqual( [
			popular.title.raw,
			rare.title.raw,
		] );
	} );

	test( 'a list draws each rating in the shape the site chose', async ( {
		page,
		requestUtils,
	} ) => {
		await configure( page, { shape: 'number', check: CHECK.never, allow: ALLOW.everyone } );

		await ratedPost( requestUtils, uniqueTitle( 'Listed in numbers' ), 4, 16 );

		const host = await requestUtils.createPost( {
			title: uniqueTitle( 'Numeric list' ),
			content: '[ratings_widget type="highest_rated"]',
			status: 'publish',
		} );

		await page.goto( host.link );

		const strip = page.locator( '.e2e-widget .wp-postratings-strip' ).first();

		await expect( strip ).toBeVisible( { timeout: 15_000 } );

		// The list goes through the same renderer as a post does, so a shape that
		// works on one has to work on the other. This is where a list rendering
		// its own way would show.
		expect(
			await strip.evaluate( ( el ) =>
				Array.from( el.querySelectorAll( '.wp-postratings-track .wp-postratings-item' ) ).map(
					( item ) => item.textContent.trim(),
				),
			),
		).toEqual( [ '1', '2', '3', '4', '5' ] );
	} );

	test( 'a loop can be sorted by rating from the URL', async ( { page, requestUtils } ) => {
		await configure( page, { check: CHECK.never, allow: ALLOW.everyone } );

		const middling = await ratedPost( requestUtils, uniqueTitle( 'Sorted middling' ), 4, 12 );
		const best = await ratedPost( requestUtils, uniqueTitle( 'Sorted best' ), 4, 20 );
		const worst = await ratedPost( requestUtils, uniqueTitle( 'Sorted worst' ), 4, 4 );

		const all = [ best, middling, worst ];

		// r_sortby and r_orderby are public query variables the plugin registers
		// and answers on pre_get_posts, so the whole feature is a URL going in and
		// an order coming out.
		await page.goto( '/?r_sortby=highest_rated&r_orderby=desc' );

		expect( await orderOf( page, all, 'body' ) ).toEqual( [
			best.title.raw,
			middling.title.raw,
			worst.title.raw,
		] );

		// And the other way, because a direction that is ignored still looks
		// right in one of its two readings.
		await page.goto( '/?r_sortby=highest_rated&r_orderby=asc' );

		expect( await orderOf( page, all, 'body' ) ).toEqual( [
			worst.title.raw,
			middling.title.raw,
			best.title.raw,
		] );
	} );
} );
