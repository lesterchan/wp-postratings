/**
 * Tests for the front end voting script.
 *
 * Much of what this file used to cover is gone: hovering and filling the scale
 * are CSS since 2.0.0, so there is no image swapping left to assert. What
 * remains is the part CSS cannot do -- posting the vote -- plus the field-name
 * contract the PHP handler depends on.
 */
import { beforeAll, beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import { l10nFixture, loadScript, updownMarkup, voteMarkup } from './helper-dom.js';

describe( 'wp-postratings front end', () => {
	beforeAll( () => {
		// Must exist before the IIFE evaluates: it reads l10n as it runs.
		window.wpPostRatingsL10n = l10nFixture();
		loadScript( 'js/wp-postratings.js' );
	} );

	beforeEach( () => {
		// The listeners live on document, so they survive this; re-evaluating
		// the script instead would double every handler.
		document.body.innerHTML = voteMarkup( 4 );

		window.fetch = vi.fn( () =>
			Promise.resolve( { text: () => Promise.resolve( '<em>Thanks!</em>' ) } ),
		);
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	/**
	 * Choose a value on the scale, the way a click or an arrow key does.
	 *
	 * @param {number} rating Value to pick.
	 * @return {Element} The input.
	 */
	function choose( rating ) {
		const input = document.querySelector( 'input[value="' + rating + '"]' );

		input.checked = true;
		input.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );

		return input;
	}

	// --- the request ------------------------------------------------------

	it( 'posts the vote when a value is chosen', async () => {
		choose( 4 );

		await vi.waitFor( () => expect( window.fetch ).toHaveBeenCalledTimes( 1 ) );

		const [ url, options ] = window.fetch.mock.calls[ 0 ];

		expect( url ).toBe( window.wpPostRatingsL10n.ajaxUrl );
		expect( options.method ).toBe( 'POST' );
	} );

	it( 'sends the field names the PHP side reads', async () => {
		choose( 4 );

		await vi.waitFor( () => expect( window.fetch ).toHaveBeenCalled() );

		const body = new URLSearchParams( window.fetch.mock.calls[ 0 ][ 1 ].body );

		// The contract WP_PostRatings_Rating::handle_vote() depends on; rename any
		// of these and votes are dropped silently.
		expect( body.get( 'action' ) ).toBe( 'wp_postratings' );
		expect( body.get( 'pid' ) ).toBe( '4' );
		expect( body.get( 'rate' ) ).toBe( '4' );
		expect( body.get( 'wp_postratings_4_nonce' ) ).toBe( 'abc123' );
	} );

	it( 'sends the value that was chosen', async () => {
		choose( 2 );

		await vi.waitFor( () => expect( window.fetch ).toHaveBeenCalled() );

		const body = new URLSearchParams( window.fetch.mock.calls[ 0 ][ 1 ].body );

		expect( body.get( 'rate' ) ).toBe( '2' );
	} );

	it( 'swaps the response into the container', async () => {
		choose( 4 );

		await vi.waitFor( () =>
			expect( document.getElementById( 'wp-postratings-4' ).innerHTML ).toBe( '<em>Thanks!</em>' ),
		);
	} );

	it( 'shows the loading indicator while voting and hides it once the response lands', async () => {
		const loading = document.getElementById( 'wp-postratings-4-loading' );

		choose( 4 );

		expect( loading.hidden ).toBe( false );

		await vi.waitFor( () => expect( loading.hidden ).toBe( true ) );
	} );

	// --- keyboard ---------------------------------------------------------

	/*
	 * Arrow keys move between radios natively and fire "change", which is why
	 * the script listens for that rather than for clicks: the control is
	 * keyboard operable without a single key handler. Before 2.0.0 each value
	 * was an <img role="button"> and this had to be hand-rolled.
	 */
	it( 'votes from a change event however it was triggered', async () => {
		const input = document.querySelector( 'input[value="3"]' );

		// What a browser does when the user arrows onto a radio.
		input.checked = true;
		input.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );

		await vi.waitFor( () => expect( window.fetch ).toHaveBeenCalledTimes( 1 ) );

		const body = new URLSearchParams( window.fetch.mock.calls[ 0 ][ 1 ].body );
		expect( body.get( 'rate' ) ).toBe( '3' );
	} );

	// --- guards -----------------------------------------------------------

	it( 'does not post twice while a vote is in flight', () => {
		let release;
		window.fetch = vi.fn(
			() =>
				new Promise( ( resolve ) => {
					release = () => resolve( { text: () => Promise.resolve( 'done' ) } );
				} ),
		);
		window.alert = vi.fn();

		choose( 4 );
		choose( 5 );

		expect( window.fetch ).toHaveBeenCalledTimes( 1 );
		expect( window.alert ).toHaveBeenCalledWith( window.wpPostRatingsL10n.textWait );

		release();
	} );

	it( 'marks the container busy while voting and clears it after', async () => {
		const container = document.getElementById( 'wp-postratings-4' );

		choose( 4 );

		expect( container.getAttribute( 'aria-busy' ) ).toBe( 'true' );

		await vi.waitFor( () => expect( container.hasAttribute( 'aria-busy' ) ).toBe( false ) );
	} );

	it( 'recovers from a failed request rather than locking up', async () => {
		window.fetch = vi.fn( () => Promise.reject( new Error( 'offline' ) ) );

		choose( 4 );

		await vi.waitFor( () =>
			expect(
				document.getElementById( 'wp-postratings-4' ).hasAttribute( 'aria-busy' ),
			).toBe( false ),
		);

		// A second attempt must still be possible.
		window.fetch = vi.fn( () =>
			Promise.resolve( { text: () => Promise.resolve( 'ok' ) } ),
		);
		choose( 3 );

		await vi.waitFor( () => expect( window.fetch ).toHaveBeenCalledTimes( 1 ) );
	} );

	it( 'ignores changes outside a rating control', () => {
		document.body.insertAdjacentHTML( 'beforeend', '<input type="checkbox" id="elsewhere" />' );

		document
			.getElementById( 'elsewhere' )
			.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );

		expect( window.fetch ).not.toHaveBeenCalled();
	} );

	// --- up / down --------------------------------------------------------

	it( 'posts an up vote from the up button', async () => {
		document.body.innerHTML = updownMarkup( 7 );

		document
			.querySelector( '.wp-postratings-up' )
			.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true, cancelable: true } ) );

		await vi.waitFor( () => expect( window.fetch ).toHaveBeenCalled() );

		const body = new URLSearchParams( window.fetch.mock.calls[ 0 ][ 1 ].body );

		expect( body.get( 'pid' ) ).toBe( '7' );
		expect( body.get( 'rate' ) ).toBe( '2' );
	} );

	it( 'posts a down vote from the down button', async () => {
		document.body.innerHTML = updownMarkup( 7 );

		document
			.querySelector( '.wp-postratings-down' )
			.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true, cancelable: true } ) );

		await vi.waitFor( () => expect( window.fetch ).toHaveBeenCalled() );

		const body = new URLSearchParams( window.fetch.mock.calls[ 0 ][ 1 ].body );

		expect( body.get( 'rate' ) ).toBe( '1' );
	} );

	it( 'does not submit a surrounding form when an up/down button is used', () => {
		document.body.innerHTML = updownMarkup( 7 );

		const button = document.querySelector( '.wp-postratings-up' );
		const event = new window.MouseEvent( 'click', { bubbles: true, cancelable: true } );

		button.dispatchEvent( event );

		expect( event.defaultPrevented ).toBe( true );
	} );

	it( 'ignores clicks that are not on a vote button', () => {
		document.body.innerHTML = updownMarkup( 7 );

		document
			.querySelector( '.wp-postratings' )
			.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );

		expect( window.fetch ).not.toHaveBeenCalled();
	} );

	// --- what CSS took over -----------------------------------------------

	it( 'never writes an image source', async () => {
		// The hover-and-fill behaviour is CSS now, and the shapes are masks, so
		// there is nothing left for the script to point at a file.
		choose( 3 );

		await vi.waitFor( () => expect( window.fetch ).toHaveBeenCalled() );

		const items = [ ...document.querySelectorAll( '.wp-postratings-item' ) ];

		expect( items.every( ( el ) => null === el.getAttribute( 'src' ) ) ).toBe( true );
	} );
} );
