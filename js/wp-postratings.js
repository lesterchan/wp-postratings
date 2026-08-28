/**
 * WP-PostRatings front end: posts the vote and swaps in the result.
 *
 * Hover and fill are CSS, so nothing here runs on mouse move. One delegated
 * listener on document covers ratings added after load.
 */
( function() {
	'use strict';

	const l10n = window.wpPostRatingsL10n || {};

	/** Posts currently mid-vote, so a second click cannot double-submit. */
	const inFlight = new Set();

	/**
	 * Post a vote and replace the control with whatever comes back.
	 *
	 * @param {Element} control The fieldset or button group that was used.
	 * @param {number}  rating  Value chosen.
	 * @return {void}
	 */
	function vote( control, rating ) {
		const postId = Number( control.dataset.postId );

		if ( ! postId || ! rating ) {
			return;
		}

		if ( inFlight.has( postId ) ) {
			// eslint-disable-next-line no-alert -- Long-standing behaviour: warns rather than queueing a second vote.
			window.alert( l10n.textWait );
			return;
		}

		const container = document.getElementById( 'wp-postratings-' + postId );

		if ( ! container ) {
			return;
		}

		const nonce = container.dataset.nonce || container.getAttribute( 'data-nonce' );

		inFlight.add( postId );

		// aria-busy, not an opacity tween: it dims through CSS and tells
		// assistive technology the region is updating.
		container.setAttribute( 'aria-busy', 'true' );

		const body = new URLSearchParams();

		body.append( 'action', 'wp_postratings' );
		body.append( 'pid', postId );
		body.append( 'rate', rating );
		body.append( 'wp_postratings_' + postId + '_nonce', nonce );

		/**
		 * Put the control back into a usable state.
		 *
		 * @param {string} [html] Replacement markup.
		 */
		function finish( html ) {
			if ( 'string' === typeof html ) {
				container.innerHTML = html;
			}

			container.removeAttribute( 'aria-busy' );
			inFlight.delete( postId );
		}

		window
			.fetch( l10n.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: body.toString(),
			} )
			.then( function( response ) {
				return response.text();
			} )
			.then( finish )
			.catch( function() {
				finish();
			} );
	}

	/**
	 * Attach the delegated listeners.
	 *
	 * @return {void}
	 */
	function start() {
		// "change" covers click and arrow-key alike, so the scale is keyboard
		// usable with no extra handling.
		document.addEventListener( 'change', function( event ) {
			const input = event.target.closest( '.wp-postratings-scale input[type="radio"]' );

			if ( ! input ) {
				return;
			}

			const control = input.closest( '.wp-postratings-vote' );

			if ( control ) {
				vote( control, Number( input.value ) );
			}
		} );

		// An up/down pair is two buttons, not a scale.
		document.addEventListener( 'click', function( event ) {
			const button = event.target.closest( '.wp-postratings-updown button' );

			if ( ! button ) {
				return;
			}

			event.preventDefault();

			const control = button.closest( '.wp-postratings-vote' );

			if ( control ) {
				vote( control, Number( button.dataset.rating ) );
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
