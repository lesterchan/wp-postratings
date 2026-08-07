/**
 * The REST routes.
 *
 * Two routes -- read a post's rating, cast one -- under `postratings/v1`, a bare
 * noun rather than the plugin slug, because a `wp-` prefix is a wordpress.org
 * directory convention and not a naming rule for what a plugin registers.
 *
 * The PHPUnit suite already dispatches these through WP_REST_Server, so what is
 * worth testing here is only what the HTTP layer decides: that the namespace is
 * really the one that got registered, that a visitor who is not logged in at all
 * can rate -- which is who ratings are for, and who a dispatcher test cannot
 * impersonate -- and that the AJAX endpoint these sit beside still answers,
 * because it was kept on purpose.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	createRatedPost,
	resetPlugin,
	uniqueTitle,
	wpEval,
	wpEvalJson,
} = require( './helpers.js' );

/** Every route lives under this namespace. */
const NAMESPACE = '/postratings/v1';

/**
 * How many people have rated a post, straight out of the meta.
 *
 * @param {number} postId Post to read.
 * @return {number} Raters recorded.
 */
function raters( postId ) {
	return wpEvalJson( `(int) get_post_meta( ${ postId }, 'ratings_users', true )` );
}

test.describe( 'The REST routes', () => {
	let postId;

	test.beforeEach( async ( { requestUtils } ) => {
		resetPlugin();

		const post = await createRatedPost( requestUtils, uniqueTitle( 'REST rating' ) );

		postId = post.id;
	} );

	test( 'the fixture really is the namespace this plugin registered', async ( {
		requestUtils,
	} ) => {
		// Every call below is under one namespace. If it were ever renamed, all
		// of them would 404 and the "refused" tests would pass for the wrong
		// reason.
		const index = await requestUtils.rest( { path: '/' } );

		expect( index.namespaces ).toContain( 'postratings/v1' );
		expect( index.namespaces ).not.toContain( 'wp-postratings/v1' );
	} );

	test( 'reading a post returns its totals and its rendered markup', async ( {
		requestUtils,
	} ) => {
		const rating = await requestUtils.rest( {
			path: `${ NAMESPACE }/post/${ postId }`,
		} );

		expect( rating.users ).toBe( 0 );
		// The markup matters as much as the numbers: the templates and the shape
		// are the site's to change, so a client drawing its own stars would
		// ignore both.
		expect( rating.html ).not.toBe( '' );
	} );

	test( 'a post that does not exist is a 404, not an unrated post', async ( {
		request,
	} ) => {
		const response = await request.get(
			`/index.php?rest_route=${ NAMESPACE }/post/123456`,
		);

		expect( response.status() ).toBe( 404 );
	} );

	// Everything below runs without the administrator's cookies, because
	// playwright.config.js sets `use.storageState` for the whole suite and the
	// `request` fixture inherits it like any other. Rating is the one thing a
	// site's visitors do, and a nonce is tied to the user it was made for -- so
	// a "logged out" test carrying an admin cookie fails on the nonce and reads
	// as a broken endpoint rather than a broken fixture.
	test.describe( 'as a visitor who is not logged in', () => {
		test.use( { storageState: { cookies: [], origins: [] } } );

		test( 'the fixture really is logged out', async ( { request } ) => {
			// Without this the tests below prove nothing on the day somebody
			// changes the storage state: they would pass as the administrator
			// too, and a rating is not the thing that would tell you.
			const me = await request.get( '/index.php?rest_route=/wp/v2/users/me' );

			expect( me.status() ).toBe( 401 );
		} );

		test( 'a logged-out visitor can rate, which is who ratings are for', async ( {
			request,
		} ) => {
			// Minted through WP-CLI, which runs with nobody logged in, for the
			// same user the request below is: both are user 0.
			const nonce = wpEval(
				`echo '<<<' . wp_create_nonce( 'wp_postratings_${ postId }-nonce' ) . '>>>';`,
			);

			expect( nonce ).not.toBe( '' );

			const response = await request.post(
				`/index.php?rest_route=${ NAMESPACE }/post/${ postId }/rate`,
				{ form: { rate: 5, nonce } },
			);

			expect( response.status() ).toBe( 200 );
			expect( raters( postId ) ).toBe( 1 );
		} );

		test( 'a rating without the post nonce is refused and records nothing', async ( {
			request,
		} ) => {
			const response = await request.post(
				`/index.php?rest_route=${ NAMESPACE }/post/${ postId }/rate`,
				{ form: { rate: 5, nonce: 'not-the-nonce' } },
			);

			expect( response.status() ).toBe( 403 );
			expect( raters( postId ) ).toBe( 0 );
		} );
	} );

	test( 'the AJAX endpoint these sit beside still answers', async ( { request } ) => {
		// Kept on purpose: a theme or a cached script may still be calling it.
		// If this ever 404s, the routes above stopped being an addition and
		// became a replacement.
		const response = await request.get(
			`/wp-admin/admin-ajax.php?action=wp_postratings&pid=${ postId }&rate=1`,
		);

		expect( response.status() ).toBe( 200 );
	} );
} );
