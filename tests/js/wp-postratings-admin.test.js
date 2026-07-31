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
			templates: {
				scale: {
					vote: 'SCALE VOTE %RATINGS_IMAGES_VOTE%',
					text: 'SCALE TEXT %RATINGS_IMAGES%',
				},
				updown: {
					vote: 'UPDOWN VOTE %RATINGS_IMAGES_VOTE%',
					text: 'UPDOWN TEXT %RATINGS_IMAGES%',
				},
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

				<input type="hidden" id="wp_postratings_customrating" value="0" />


				<span class="spinner" id="wp-postratings-spinner"></span>
				<div id="wp-postratings-rating-fields" data-nonce="nonce123">
					<table><tbody>
						<tr><td><input name="postratings_options[ratings][text][]" value="One" /></td>
							<td><button type="button" class="wp-postratings-remove-step"></button></td></tr>
						<tr><td><input name="postratings_options[ratings][text][]" value="Two" /></td>
							<td><button type="button" class="wp-postratings-remove-step"></button></td></tr>
						<tr><td><input name="postratings_options[ratings][text][]" value="Three" /></td>
							<td><button type="button" class="wp-postratings-remove-step"></button></td></tr>
					</tbody></table>
				</div>
				<button type="button" id="wp-postratings-add-step"></button>
				<input type="color" class="wp-postratings-color" data-default="#f5a623" value="#000000" />
				<input type="color" class="wp-postratings-color" data-default="#d4d4d8" value="#ffffff" />
				<button type="button" id="wp-postratings-reset-colors"></button>

				<textarea id="wp_postratings_template_vote">CUSTOM VOTE</textarea>
				<textarea id="wp_postratings_template_text">CUSTOM TEXT</textarea>
				<button type="button" class="wp-postratings-restore-template"
					data-template="vote" data-variant="scale">Scale</button>
				<button type="button" class="wp-postratings-restore-template"
					data-template="vote" data-variant="updown">Up/Down</button>
				<button type="button" class="wp-postratings-restore-template"
					data-template="text" data-variant="scale">Scale text</button>

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
		click( '.wp-postratings-restore-template[data-template="vote"][data-variant="scale"]' );

		expect( document.getElementById( 'wp_postratings_template_vote' ).value ).toBe(
			'SCALE VOTE %RATINGS_IMAGES_VOTE%',
		);
	} );

	it( 'restores the up/down default template', () => {
		click( '.wp-postratings-restore-template[data-template="vote"][data-variant="updown"]' );

		expect( document.getElementById( 'wp_postratings_template_vote' ).value ).toBe(
			'UPDOWN VOTE %RATINGS_IMAGES_VOTE%',
		);
	} );

	it( 'restores only the template the button names', () => {
		click( '.wp-postratings-restore-template[data-template="vote"][data-variant="scale"]' );

		expect( document.getElementById( 'wp_postratings_template_text' ).value ).toBe( 'CUSTOM TEXT' );
	} );

	it( 'does not submit the form when restoring', () => {
		const button = document.querySelector(
			'.wp-postratings-restore-template[data-template="vote"][data-variant="scale"]',
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
		// The chosen shape's own default length: a shape change is a reset, so
		// the rows on screen do not carry over.
		expect( url.searchParams.get( 'max' ) ).toBe( '5' );
		// Nothing else. The rating type used to travel beside the shape as a
		// "custom" flag, read from a hidden field that no longer exists, so it
		// said "scale" whatever was chosen. The shape already answers it.
		expect( url.searchParams.get( 'custom' ) ).toBeNull();
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

		click( '#wp-postratings-add-step' );

		expect( window.fetch ).not.toHaveBeenCalled();
	} );

	it( 'asks for one more step than the table has, carrying what is typed', async () => {
		click( '#wp-postratings-add-step' );

		await vi.waitFor( () => expect( window.fetch ).toHaveBeenCalled() );

		const url = new URL( window.fetch.mock.calls[ 0 ][ 0 ] );

		// Three rows on screen, so adding asks for four -- counted from the
		// table rather than read from a Max field, because the table is the
		// scale.
		expect( url.searchParams.get( 'max' ) ).toBe( '4' );

		// And every label travels with it, so a rebuild does not throw away what
		// the site has typed into the rows that are not changing.
		// text[] rather than text: PHP builds an array from repeated parameters
		// only when they carry brackets, and without them it keeps the last
		// value alone -- which is how the first row came back holding the last
		// row's label.
		expect( url.searchParams.getAll( 'text[]' ) ).toEqual( [ 'One', 'Two', 'Three' ] );
	} );

	it( 'leaves the removed row out of the rebuild', async () => {
		click( '.wp-postratings-remove-step' );

		await vi.waitFor( () => expect( window.fetch ).toHaveBeenCalled() );

		const url = new URL( window.fetch.mock.calls[ 0 ][ 0 ] );

		expect( url.searchParams.get( 'max' ) ).toBe( '2' );
		expect( url.searchParams.getAll( 'text[]' ) ).toEqual( [ 'Two', 'Three' ] );
	} );

	// --- choosing a shape --------------------------------------------

	it( 'resets the table on any shape change, to that shape\'s own length', async () => {
		click( 'input[value="thumbs"]' );

		await vi.waitFor( () => expect( window.fetch ).toHaveBeenCalled() );

		let url = new URL( window.fetch.mock.calls[ 0 ][ 0 ] );

		// The shape's own default length, and nothing carried: a shape decides
		// how many steps there are and what they are called.
		expect( url.searchParams.get( 'max' ) ).toBe( '2' );
		expect( url.searchParams.getAll( 'text[]' ) ).toEqual( [] );

		window.fetch.mockClear();

		click( 'input[value="stars"]' );

		await vi.waitFor( () => expect( window.fetch ).toHaveBeenCalled() );

		url = new URL( window.fetch.mock.calls[ 0 ][ 0 ] );

		expect( url.searchParams.get( 'max' ) ).toBe( '5' );
		expect( url.searchParams.getAll( 'text[]' ) ).toEqual( [] );
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

	it( 'resets every colour to the built-in one', () => {
		const swatches = document.querySelectorAll( '.wp-postratings-color' );

		expect( swatches[ 0 ].value ).toBe( '#000000' );

		click( '#wp-postratings-reset-colors' );

		// Each swatch carries its own default, so one button resets both columns
		// without knowing which is which.
		expect( swatches[ 0 ].value ).toBe( '#f5a623' );
		expect( swatches[ 1 ].value ).toBe( '#d4d4d8' );
	} );

	it( 'carries nothing when the shape changes, only when a step does', async () => {
		click( 'input[value="thumbs"]' );

		await vi.waitFor( () => expect( window.fetch ).toHaveBeenCalled() );

		const url = new URL( window.fetch.mock.calls[ 0 ][ 0 ] );

		// A different shape is a different rating: its labels and, across the two
		// types, its values are not the old ones. Carrying them is what left the
		// first row of a scale at -1 after switching away from thumbs.
		expect( url.searchParams.getAll( 'text[]' ) ).toEqual( [] );
		expect( url.searchParams.getAll( 'value[]' ) ).toEqual( [] );
	} );
} );
