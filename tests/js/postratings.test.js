/**
 * Tests for the front end voting script.
 *
 * The script has no exports; it attaches delegated listeners to document and is
 * driven here the way a visitor drives it.
 */
import { beforeAll, beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import { l10nFixture, loadScript, voteMarkup } from './helpers.js';

describe( 'wp-postratings front end', () => {
	beforeAll( () => {
		// Must exist before the IIFE evaluates: it reads l10n as it runs.
		window.ratingsL10n = l10nFixture();
		loadScript( 'js/postratings.js' );
	} );

	beforeEach( () => {
		// The listeners live on document, so they survive this; re-evaluating
		// the script instead would double every handler.
		document.body.innerHTML = voteMarkup( 4 );

		window.fetch = vi.fn( () =>
			Promise.resolve( { text: () => Promise.resolve( '<em>Thanks!</em>' ) } ),
		);

		hide( false );
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	/**
	 * Control what document.hidden reports.
	 *
	 * @param {boolean} hidden Whether the tab is backgrounded.
	 */
	function hide( hidden ) {
		Object.defineProperty( document, 'hidden', {
			configurable: true,
			get: () => hidden,
		} );
	}

	/**
	 * The vote image for one position on the scale.
	 *
	 * @param {number} rating Position.
	 * @return {Element} Image element.
	 */
	function star( rating ) {
		return document.querySelector( '.post-ratings-vote[data-rating="' + rating + '"]' );
	}

	/**
	 * File names of the rating images currently displayed.
	 *
	 * @return {Array} File names.
	 */
	function shownImages() {
		return [ ...document.querySelectorAll( '.post-ratings-vote' ) ].map( ( img ) =>
			img.src.split( '/' ).pop(),
		);
	}

	/**
	 * Click one star and let the promise chain settle.
	 *
	 * @param {number} rating Position to click.
	 */
	async function vote( rating ) {
		star( rating ).dispatchEvent( new window.MouseEvent( 'mouseover', { bubbles: true } ) );
		star( rating ).dispatchEvent(
			new window.MouseEvent( 'click', { bubbles: true, cancelable: true } ),
		);
		await vi.waitFor( () => expect( window.fetch ).toHaveBeenCalled() );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	}

	// --- hover ------------------------------------------------------------

	it( 'lights the strip up to the hovered star', () => {
		star( 3 ).dispatchEvent( new window.MouseEvent( 'mouseover', { bubbles: true } ) );

		expect( shownImages() ).toEqual( [
			'rating_over.gif',
			'rating_over.gif',
			'rating_over.gif',
			'rating_off.gif',
			'rating_off.gif',
		] );
	} );

	it( 'shows the rating text while hovering and clears it after', () => {
		const text = document.getElementById( 'ratings_4_text' );

		star( 2 ).dispatchEvent( new window.MouseEvent( 'mouseover', { bubbles: true } ) );
		expect( text.textContent ).toBe( '2 Stars' );

		star( 2 ).dispatchEvent( new window.MouseEvent( 'mouseout', { bubbles: true } ) );
		expect( text.textContent ).toBe( '' );
	} );

	it( 'restores the stored rating on mouseout', () => {
		star( 4 ).dispatchEvent( new window.MouseEvent( 'mouseover', { bubbles: true } ) );
		star( 4 ).dispatchEvent( new window.MouseEvent( 'mouseout', { bubbles: true } ) );

		expect( shownImages().every( ( name ) => 'rating_off.gif' === name ) ).toBe( true );
	} );

	// --- the request ------------------------------------------------------

	it( 'posts the vote to admin-ajax', async () => {
		await vote( 4 );

		expect( window.fetch ).toHaveBeenCalledTimes( 1 );

		const [ url, options ] = window.fetch.mock.calls[ 0 ];

		expect( url ).toBe( window.ratingsL10n.ajaxUrl );
		expect( options.method ).toBe( 'POST' );
	} );

	it( 'sends the field names the PHP side reads', async () => {
		await vote( 4 );

		const body = new URLSearchParams( window.fetch.mock.calls[ 0 ][ 1 ].body );

		// This is the contract Postratings_Rating::handle_vote() depends on;
		// rename any of these and votes are silently dropped.
		expect( body.get( 'action' ) ).toBe( 'postratings' );
		expect( body.get( 'pid' ) ).toBe( '4' );
		expect( body.get( 'rate' ) ).toBe( '4' );
		expect( body.get( 'postratings_4_nonce' ) ).toBe( 'abc123' );
	} );

	it( 'sends the rating that was clicked, not the last hovered', async () => {
		star( 2 ).dispatchEvent( new window.MouseEvent( 'mouseover', { bubbles: true } ) );
		await vote( 5 );

		const body = new URLSearchParams( window.fetch.mock.calls[ 0 ][ 1 ].body );

		expect( body.get( 'rate' ) ).toBe( '5' );
	} );

	it( 'swaps the response into the container', async () => {
		await vote( 4 );

		expect( document.getElementById( 'post-ratings-4' ).innerHTML ).toBe( '<em>Thanks!</em>' );
	} );

	it( 'hides the loading indicator once the response lands', async () => {
		await vote( 4 );

		expect( document.getElementById( 'post-ratings-4-loading' ).style.display ).toBe( 'none' );
	} );

	// --- the regression that shipped past a headless run -------------------

	it( 'still sends the vote when the tab is in the background', async () => {
		// A hidden document does not merely throttle requestAnimationFrame, it
		// stops delivering the callbacks altogether, so this stubs rAF to never
		// fire rather than trusting jsdom -- which happily runs rAF regardless
		// of document.hidden, and would let this pass for the wrong reason.
		//
		// The fade used to drive itself with rAF and the fetch was nested in its
		// completion callback, so a backgrounded tab dropped the vote entirely.
		hide( true );
		const raf = vi
			.spyOn( window, 'requestAnimationFrame' )
			.mockImplementation( () => 0 );

		star( 4 ).dispatchEvent( new window.MouseEvent( 'mouseover', { bubbles: true } ) );
		star( 4 ).dispatchEvent(
			new window.MouseEvent( 'click', { bubbles: true, cancelable: true } ),
		);

		await vi.waitFor( () => expect( window.fetch ).toHaveBeenCalledTimes( 1 ) );

		raf.mockRestore();
	} );

	it( 'leaves the container visible when the tab is hidden', async () => {
		hide( true );
		await vote( 4 );

		expect( document.getElementById( 'post-ratings-4' ).style.opacity ).toBe( '1' );
	} );

	// --- guards -----------------------------------------------------------

	it( 'does not post twice while a vote is in flight', async () => {
		let release;
		window.fetch = vi.fn(
			() =>
				new Promise( ( resolve ) => {
					release = () => resolve( { text: () => Promise.resolve( 'done' ) } );
				} ),
		);
		window.alert = vi.fn();

		star( 4 ).dispatchEvent( new window.MouseEvent( 'mouseover', { bubbles: true } ) );
		star( 4 ).dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );
		star( 5 ).dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );

		expect( window.fetch ).toHaveBeenCalledTimes( 1 );
		expect( window.alert ).toHaveBeenCalledWith( window.ratingsL10n.textWait );

		release();
	} );

	it( 'accepts another vote once the first has finished', async () => {
		await vote( 4 );

		document.body.innerHTML = voteMarkup( 7 );
		await vote( 3 );

		expect( window.fetch ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'recovers from a failed request rather than locking up', async () => {
		window.fetch = vi.fn( () => Promise.reject( new Error( 'offline' ) ) );

		star( 4 ).dispatchEvent( new window.MouseEvent( 'mouseover', { bubbles: true } ) );
		star( 4 ).dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );

		await vi.waitFor( () =>
			expect( document.getElementById( 'post-ratings-4' ).style.opacity ).toBe( '1' ),
		);

		// A second attempt must still be possible.
		window.fetch = vi.fn( () =>
			Promise.resolve( { text: () => Promise.resolve( 'ok' ) } ),
		);
		await vote( 4 );

		expect( window.fetch ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'ignores clicks outside a rating image', () => {
		document.body.insertAdjacentHTML( 'beforeend', '<a href="#" id="elsewhere">Link</a>' );

		document
			.getElementById( 'elsewhere' )
			.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );

		expect( window.fetch ).not.toHaveBeenCalled();
	} );

	// --- keyboard ---------------------------------------------------------

	it( 'votes on Enter, for keyboard users', async () => {
		star( 3 ).dispatchEvent( new window.MouseEvent( 'mouseover', { bubbles: true } ) );
		star( 3 ).dispatchEvent(
			new window.KeyboardEvent( 'keydown', { key: 'Enter', bubbles: true } ),
		);

		await vi.waitFor( () => expect( window.fetch ).toHaveBeenCalledTimes( 1 ) );

		const body = new URLSearchParams( window.fetch.mock.calls[ 0 ][ 1 ].body );
		expect( body.get( 'rate' ) ).toBe( '3' );
	} );

	it( 'ignores other keys', () => {
		star( 3 ).dispatchEvent(
			new window.KeyboardEvent( 'keydown', { key: 'a', bubbles: true } ),
		);

		expect( window.fetch ).not.toHaveBeenCalled();
	} );

	// --- image sets -------------------------------------------------------

	it( 'uses per-step images for a custom set', () => {
		window.ratingsL10n.custom = '1';

		star( 2 ).dispatchEvent( new window.MouseEvent( 'mouseover', { bubbles: true } ) );

		expect( shownImages()[ 0 ] ).toBe( 'rating_1_over.gif' );
		expect( shownImages()[ 1 ] ).toBe( 'rating_2_over.gif' );

		window.ratingsL10n.custom = '0';
	} );

	it( 'lights only the hovered image on an up/down set', () => {
		window.ratingsL10n.custom = '1';
		window.ratingsL10n.max = '2';

		star( 2 ).dispatchEvent( new window.MouseEvent( 'mouseover', { bubbles: true } ) );

		expect( shownImages()[ 0 ] ).toBe( 'rating_off.gif' );
		expect( shownImages()[ 1 ] ).toBe( 'rating_2_over.gif' );

		window.ratingsL10n.custom = '0';
		window.ratingsL10n.max = '5';
	} );
} );
