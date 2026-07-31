/**
 * Tests for the settings screen script.
 *
 * Like the front end script this is an IIFE with no exports that attaches
 * delegated listeners to document, so it is loaded into a page and driven.
 */
import { beforeAll, beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import { loadScript } from './helper-dom.js';

describe( 'wp-postratings settings screen', () => {
	beforeAll( () => {
		window.wpPostRatingsL10n = {
			ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
			defaultTemplates: {
				vote: 'DEFAULT VOTE %RATINGS_IMAGES_VOTE%',
				text: 'DEFAULT TEXT %RATINGS_IMAGES%',
			},
			updownTemplates: {
				vote: 'UPDOWN VOTE %RATINGS_IMAGES_VOTE%',
				text: 'UPDOWN TEXT %RATINGS_IMAGES%',
			},
		};

		loadScript( 'js/wp-postratings-admin.js' );
	} );

	beforeEach( () => {
		document.body.innerHTML = `
			<form>
				<input type="radio" name="postratings_options[shape]" value="stars"
					class="wp-postratings-shape-choice" data-custom="0" data-max="5" checked />
				<input type="radio" name="postratings_options[shape]" value="thumbs"
					class="wp-postratings-shape-choice" data-custom="1" data-max="2" />

				<input type="number" id="wp_postratings_max" value="5" />
				<input type="hidden" id="wp_postratings_customrating" value="0" />


				<span class="spinner" id="wp-postratings-spinner"></span>
				<div id="wp-postratings-rating-fields" data-nonce="nonce123">old rows</div>

				<textarea id="wp_postratings_template_vote">CUSTOM VOTE</textarea>
				<textarea id="wp_postratings_template_text">CUSTOM TEXT</textarea>
				<button type="button" class="wp-postratings-restore-template"
					data-template="vote" data-variant="default">Default</button>
				<button type="button" class="wp-postratings-restore-template"
					data-template="vote" data-variant="updown">Up/Down</button>
				<button type="button" class="wp-postratings-restore-template"
					data-template="text" data-variant="default">Default text</button>

				<button type="submit" id="wp-postratings-delete-data"
					data-confirm="Are you sure?">Delete</button>
			</form>
		`;

		window.fetch = vi.fn( () =>
			Promise.resolve( { text: () => Promise.resolve( '<p>new rows</p>' ) } ),
		);
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	/**
	 * Click an element by selector.
	 *
	 * @param {string} selector CSS selector.
	 * @return {Element} The element clicked.
	 */
	function click( selector ) {
		const el = document.querySelector( selector );
		el.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true, cancelable: true } ) );
		return el;
	}

	// --- restoring templates ----------------------------------------------

	it( 'restores the normal default template', () => {
		click( '.wp-postratings-restore-template[data-template="vote"][data-variant="default"]' );

		expect( document.getElementById( 'wp_postratings_template_vote' ).value ).toBe(
			'DEFAULT VOTE %RATINGS_IMAGES_VOTE%',
		);
	} );

	it( 'restores the up/down default template', () => {
		click( '.wp-postratings-restore-template[data-template="vote"][data-variant="updown"]' );

		expect( document.getElementById( 'wp_postratings_template_vote' ).value ).toBe(
			'UPDOWN VOTE %RATINGS_IMAGES_VOTE%',
		);
	} );

	it( 'restores only the template the button names', () => {
		click( '.wp-postratings-restore-template[data-template="vote"][data-variant="default"]' );

		expect( document.getElementById( 'wp_postratings_template_text' ).value ).toBe( 'CUSTOM TEXT' );
	} );

	it( 'does not submit the form when restoring', () => {
		const button = document.querySelector(
			'.wp-postratings-restore-template[data-template="vote"][data-variant="default"]',
		);
		const event = new window.MouseEvent( 'click', { bubbles: true, cancelable: true } );

		button.dispatchEvent( event );

		expect( event.defaultPrevented ).toBe( true );
	} );

	it( 'leaves the textarea alone for an unknown template name', () => {
		const button = document.querySelector( '.wp-postratings-restore-template' );
		button.dataset.template = 'nonexistent';

		click( '.wp-postratings-restore-template' );

		expect( document.getElementById( 'wp_postratings_template_vote' ).value ).toBe( 'CUSTOM VOTE' );
	} );

	// --- the rating fields follow the shape and the scale ------------------

	it( 'requests rebuilt rating rows', async () => {
		click( '.wp-postratings-shape-choice' );

		await vi.waitFor( () => expect( window.fetch ).toHaveBeenCalled() );

		const url = new URL( window.fetch.mock.calls[ 0 ][ 0 ] );

		// The names the PHP endpoint reads; rename one and the table stops
		// rebuilding, silently.
		expect( url.searchParams.get( 'action' ) ).toBe( 'wp_postratings_rating_fields' );
		expect( url.searchParams.get( '_ajax_nonce' ) ).toBe( 'nonce123' );
		expect( url.searchParams.get( 'shape' ) ).toBe( 'stars' );
		expect( url.searchParams.get( 'max' ) ).toBe( '5' );
		expect( url.searchParams.get( 'custom' ) ).toBe( '0' );
	} );

	it( 'swaps the response into the table', async () => {
		click( '.wp-postratings-shape-choice' );

		await vi.waitFor( () =>
			expect( document.getElementById( 'wp-postratings-rating-fields' ).innerHTML ).toBe(
				'<p>new rows</p>',
			),
		);
	} );

	it( 'sends the shape that is actually selected', async () => {
		// Click the thumbs radio itself: clicking a radio is what selects it, so
		// choosing a shape and rebuilding for it are one gesture now.
		click( 'input[value="thumbs"]' );

		await vi.waitFor( () => expect( window.fetch ).toHaveBeenCalled() );

		const url = new URL( window.fetch.mock.calls[ 0 ][ 0 ] );

		expect( url.searchParams.get( 'shape' ) ).toBe( 'thumbs' );
	} );

	it( 'shows the spinner while the request is in flight and hides it after', async () => {
		const spinner = document.getElementById( 'wp-postratings-spinner' );

		click( '.wp-postratings-shape-choice' );

		expect( spinner.classList.contains( 'is-active' ) ).toBe( true );

		await vi.waitFor( () => expect( spinner.classList.contains( 'is-active' ) ).toBe( false ) );
	} );

	it( 'hides the spinner even when the request fails', async () => {
		window.fetch = vi.fn( () => Promise.reject( new Error( 'offline' ) ) );

		const spinner = document.getElementById( 'wp-postratings-spinner' );

		click( '.wp-postratings-shape-choice' );

		await vi.waitFor( () => expect( spinner.classList.contains( 'is-active' ) ).toBe( false ) );
	} );

	it( 'does nothing when no shape is selected', () => {
		// Driven through the scale, not through a shape: clicking a shape is what
		// selects it, so that path can never find nothing checked. Changing the
		// scale can.
		document.querySelectorAll( 'input[name="postratings_options[shape]"]' ).forEach( ( input ) => {
			input.checked = false;
		} );

		const max = document.getElementById( 'wp_postratings_max' );
		max.value = '7';
		max.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );

		expect( window.fetch ).not.toHaveBeenCalled();
	} );

	it( 'rebuilds when the scale changes', async () => {
		const max = document.getElementById( 'wp_postratings_max' );
		max.value = '7';
		max.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );

		await vi.waitFor( () => expect( window.fetch ).toHaveBeenCalled() );

		const url = new URL( window.fetch.mock.calls[ 0 ][ 0 ] );

		expect( url.searchParams.get( 'max' ) ).toBe( '7' );
	} );

	// --- choosing a shape --------------------------------------------

	it( 'fixes the scale when a custom set is chosen', () => {
		click( 'input[value="thumbs"]' );

		expect( document.getElementById( 'wp_postratings_customrating' ).value ).toBe( '1' );
		expect( document.getElementById( 'wp_postratings_max' ).value ).toBe( '2' );
		// A custom set defines its own number of steps, so the field is locked.
		expect( document.getElementById( 'wp_postratings_max' ).readOnly ).toBe( true );
	} );

	it( 'unlocks the scale again for a normal set', () => {
		click( 'input[value="thumbs"]' );
		click( 'input[value="stars"]' );

		expect( document.getElementById( 'wp_postratings_customrating' ).value ).toBe( '0' );
		expect( document.getElementById( 'wp_postratings_max' ).readOnly ).toBe( false );
	} );

	// --- the destructive button -------------------------------------------

	it( 'blocks the delete when the confirmation is declined', () => {
		window.confirm = vi.fn( () => false );

		const button = document.getElementById( 'wp-postratings-delete-data' );
		const event = new window.MouseEvent( 'click', { bubbles: true, cancelable: true } );

		button.dispatchEvent( event );

		expect( window.confirm ).toHaveBeenCalledWith( 'Are you sure?' );
		expect( event.defaultPrevented ).toBe( true );
	} );

	it( 'allows the delete when the confirmation is accepted', () => {
		window.confirm = vi.fn( () => true );

		const button = document.getElementById( 'wp-postratings-delete-data' );
		const event = new window.MouseEvent( 'click', { bubbles: true, cancelable: true } );

		button.dispatchEvent( event );

		expect( event.defaultPrevented ).toBe( false );
	} );
} );
