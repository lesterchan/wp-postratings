/**
 * Tests for the front end correcting a cached page's ratings.
 *
 * A rating is rendered for the visitor who asked for the page: their vote
 * count, their branch of the three templates, their nonce. A page cache then
 * serves that one copy to everybody. This is the script that asks the site what
 * the markup should have been and repaints it.
 *
 * Unlike the voting tests, the script is evaluated inside each case rather than
 * once for the file: the refresh runs as the script starts, so a case that
 * wants a different page or a different answer has to be set up before it does.
 * That leaves duplicate delegated listeners behind, which is harmless here
 * because nothing in this file dispatches a vote -- except the one case that
 * does, which loads the script exactly once itself.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { l10nFixture, loadScript, resultsMarkup, voteMarkup } from './helpers.js';

describe( 'wp-postratings cached page refresh', () => {
	beforeEach( () => {
		window.wpPostRatingsL10n = l10nFixture();
		window.wpPostRatingsL10n.refresh = '1';
	} );

	afterEach( () => {
		document.body.innerHTML = '';
		vi.restoreAllMocks();
	} );

	/**
	 * Answer the refresh request with these posts.
	 *
	 * @param {Array} posts Entries as the REST route returns them.
	 * @return {Function} The fetch mock.
	 */
	function respondWith( posts ) {
		window.fetch = vi.fn( () =>
			Promise.resolve( { json: () => Promise.resolve( { posts } ) } ),
		);

		return window.fetch;
	}

	/**
	 * The query string the refresh asked with.
	 *
	 * @return {URLSearchParams} Parameters.
	 */
	function requestedParams() {
		return new URL( window.fetch.mock.calls[ 0 ][ 0 ] ).searchParams;
	}

	// --- when it runs at all ---------------------------------------------

	it( 'asks for nothing when the site does not cache its pages', async () => {
		window.wpPostRatingsL10n.refresh = '';
		document.body.innerHTML = voteMarkup( 4 );
		respondWith( [] );

		loadScript( 'js/wp-postratings.js' );

		// A request per page on every site that does not need one is the cost
		// this default exists to avoid.
		await Promise.resolve();
		expect( window.fetch ).not.toHaveBeenCalled();
	} );

	it( 'asks for nothing on a page with no ratings on it', async () => {
		document.body.innerHTML = '<p>No ratings here.</p>';
		respondWith( [] );

		loadScript( 'js/wp-postratings.js' );

		await Promise.resolve();
		expect( window.fetch ).not.toHaveBeenCalled();
	} );

	// --- the request -----------------------------------------------------

	it( 'asks the batch route for the rating on the page', async () => {
		document.body.innerHTML = voteMarkup( 4 );
		respondWith( [] );

		loadScript( 'js/wp-postratings.js' );

		await vi.waitFor( () => expect( window.fetch ).toHaveBeenCalledTimes( 1 ) );

		const [ url, options ] = window.fetch.mock.calls[ 0 ];

		expect( url ).toContain( window.wpPostRatingsL10n.restUrl );
		expect( requestedParams().get( 'ids' ) ).toBe( '4' );
		// The rated cookie is what decides which branch comes back.
		expect( options.credentials ).toBe( 'same-origin' );
	} );

	it( 'asks about every rating on the page in one request', async () => {
		document.body.innerHTML = voteMarkup( 4 ) + voteMarkup( 9 ) + resultsMarkup( 12 );
		respondWith( [] );

		loadScript( 'js/wp-postratings.js' );

		await vi.waitFor( () => expect( window.fetch ).toHaveBeenCalled() );

		// One request, not three. An archive is exactly where a page cache
		// pays off, and a round trip per rating would take that back.
		expect( window.fetch ).toHaveBeenCalledTimes( 1 );
		expect( requestedParams().get( 'ids' ) ).toBe( '4,9,12' );
	} );

	it( 'does not mistake a radio button for a rating', async () => {
		// Every step of a scale is wp-postratings-<post>-<step>, so matching
		// on the id prefix would ask about post "4-2" five times over.
		document.body.innerHTML = voteMarkup( 4 );
		respondWith( [] );

		loadScript( 'js/wp-postratings.js' );

		await vi.waitFor( () => expect( window.fetch ).toHaveBeenCalled() );

		expect( requestedParams().get( 'ids' ) ).toBe( '4' );
	} );

	it( 'keeps a query string the site already put on the REST url', async () => {
		// A site with plain permalinks gets /?rest_route=/postratings/v1/posts.
		window.wpPostRatingsL10n.restUrl = 'https://example.com/?rest_route=/postratings/v1/posts';
		document.body.innerHTML = voteMarkup( 4 );
		respondWith( [] );

		loadScript( 'js/wp-postratings.js' );

		await vi.waitFor( () => expect( window.fetch ).toHaveBeenCalled() );

		const params = requestedParams();

		expect( params.get( 'rest_route' ) ).toBe( '/postratings/v1/posts' );
		expect( params.get( 'ids' ) ).toBe( '4' );
	} );

	// --- what it paints --------------------------------------------------

	it( 'replaces the cached markup with the visitor markup', async () => {
		document.body.innerHTML = voteMarkup( 4 );
		respondWith( [ { post_id: 4, visitor_html: '<em>7 votes</em>', nonce: 'fresh' } ] );

		loadScript( 'js/wp-postratings.js' );

		await vi.waitFor( () =>
			expect( document.getElementById( 'wp-postratings-4' ).innerHTML ).toBe( '<em>7 votes</em>' ),
		);
	} );

	it( 'replaces the nonce the cached page was carrying', async () => {
		document.body.innerHTML = voteMarkup( 4 );
		respondWith( [ { post_id: 4, visitor_html: '<em>vote</em>', nonce: 'fresh' } ] );

		loadScript( 'js/wp-postratings.js' );

		// The cached one has been ageing since the page was cached, and stops
		// verifying within a day of that. This is the half of the fix that
		// makes voting work again rather than just look right.
		await vi.waitFor( () =>
			expect( document.getElementById( 'wp-postratings-4' ).dataset.nonce ).toBe( 'fresh' ),
		);
	} );

	it( 'takes the nonce away when the visitor has nothing left to vote with', async () => {
		document.body.innerHTML = voteMarkup( 4 );
		respondWith( [ { post_id: 4, visitor_html: '<em>4 votes, rated</em>', nonce: '' } ] );

		loadScript( 'js/wp-postratings.js' );

		await vi.waitFor( () =>
			expect( document.getElementById( 'wp-postratings-4' ).innerHTML ).toContain( 'rated' ),
		);

		expect( document.getElementById( 'wp-postratings-4' ).dataset.nonce ).toBeUndefined();
	} );

	it( 'paints each rating with its own answer', async () => {
		document.body.innerHTML = voteMarkup( 4 ) + voteMarkup( 9 );
		respondWith( [
			{ post_id: 9, visitor_html: '<em>nine</em>', nonce: 'n9' },
			{ post_id: 4, visitor_html: '<em>four</em>', nonce: 'n4' },
		] );

		loadScript( 'js/wp-postratings.js' );

		// Answered out of order on purpose: the response is matched by post_id,
		// not by position.
		await vi.waitFor( () =>
			expect( document.getElementById( 'wp-postratings-9' ).innerHTML ).toBe( '<em>nine</em>' ),
		);

		expect( document.getElementById( 'wp-postratings-4' ).innerHTML ).toBe( '<em>four</em>' );
		expect( document.getElementById( 'wp-postratings-4' ).dataset.nonce ).toBe( 'n4' );
	} );

	it( 'paints both copies when one post is on the page twice', async () => {
		// A [ratings] shortcode and the block can both point at the same post,
		// and both are supported. Correcting one and leaving the other is
		// worse than correcting neither: the page then shows two different
		// counts for one post.
		document.body.innerHTML = voteMarkup( 4 ) + voteMarkup( 4 );
		respondWith( [ { post_id: 4, visitor_html: '<em>7 votes</em>', nonce: 'fresh' } ] );

		loadScript( 'js/wp-postratings.js' );

		await vi.waitFor( () =>
			expect( document.querySelectorAll( '.wp-postratings' )[ 1 ].innerHTML ).toBe( '<em>7 votes</em>' ),
		);

		const copies = document.querySelectorAll( '.wp-postratings' );

		expect( copies[ 0 ].innerHTML ).toBe( '<em>7 votes</em>' );
		expect( copies[ 0 ].dataset.nonce ).toBe( 'fresh' );
		expect( copies[ 1 ].dataset.nonce ).toBe( 'fresh' );
		// And it was still one question, asked once.
		expect( requestedParams().get( 'ids' ) ).toBe( '4' );
	} );

	it( 'ignores an answer for a rating that is not on the page', async () => {
		document.body.innerHTML = voteMarkup( 4 );
		respondWith( [ { post_id: 99, visitor_html: '<em>elsewhere</em>', nonce: '' } ] );

		loadScript( 'js/wp-postratings.js' );

		await vi.waitFor( () => expect( window.fetch ).toHaveBeenCalled() );
		await Promise.resolve();

		expect( document.body.innerHTML ).not.toContain( 'elsewhere' );
	} );

	// --- when it cannot ---------------------------------------------------

	it( 'leaves the cached markup alone when the request fails', async () => {
		document.body.innerHTML = voteMarkup( 4 );

		window.fetch = vi.fn( () => Promise.reject( new Error( 'offline' ) ) );

		loadScript( 'js/wp-postratings.js' );

		await vi.waitFor( () => expect( window.fetch ).toHaveBeenCalled() );
		await Promise.resolve();

		// Stale counts are worse than fresh ones and better than an empty box,
		// and the cached control still carries a nonce that may well work.
		expect( document.querySelector( '.wp-postratings-vote' ) ).not.toBeNull();
		expect( document.getElementById( 'wp-postratings-4' ).dataset.nonce ).toBe( 'abc123' );
	} );

	it( 'survives a response carrying no posts at all', async () => {
		document.body.innerHTML = voteMarkup( 4 );

		window.fetch = vi.fn( () => Promise.resolve( { json: () => Promise.resolve( {} ) } ) );

		loadScript( 'js/wp-postratings.js' );

		await vi.waitFor( () => expect( window.fetch ).toHaveBeenCalled() );
		await Promise.resolve();

		expect( document.querySelector( '.wp-postratings-vote' ) ).not.toBeNull();
	} );

	it( 'does not paint over a vote the visitor got in first', async () => {
		document.body.innerHTML = voteMarkup( 4 );

		let settleRefresh;

		// The refresh is left hanging so the vote can overtake it, which is
		// the race this guard exists for: the visitor's own result is newer
		// than anything the refresh was sent to say.
		window.fetch = vi.fn( ( url, options ) => {
			if ( options && 'POST' === options.method ) {
				return Promise.resolve( { text: () => Promise.resolve( '<em>Thanks!</em>' ) } );
			}

			return new Promise( ( resolve ) => {
				settleRefresh = () =>
					resolve( {
						json: () =>
							Promise.resolve( {
								posts: [ { post_id: 4, visitor_html: '<em>stale</em>', nonce: 'x' } ],
							} ),
					} );
			} );
		} );

		loadScript( 'js/wp-postratings.js' );

		await vi.waitFor( () => expect( settleRefresh ).toBeTypeOf( 'function' ) );

		const input = document.querySelector( 'input[value="4"]' );

		input.checked = true;
		input.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );

		settleRefresh();

		await vi.waitFor( () =>
			expect( document.getElementById( 'wp-postratings-4' ).innerHTML ).toBe( '<em>Thanks!</em>' ),
		);

		expect( document.getElementById( 'wp-postratings-4' ).innerHTML ).not.toContain( 'stale' );
	} );
} );
