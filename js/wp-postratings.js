/**
 * WP-PostRatings front end.
 *
 * Considerably smaller since 2.0.0. Hovering and filling the scale are CSS --
 * `label:hover ~ label` on a reversed row -- so this no longer rewrites image
 * sources on every mouse move. All it does is post the vote and swap in the
 * result.
 *
 * One delegated listener on document covers every rating on the page, whether
 * it was in the original markup or arrived later.
 */
( function() {
	'use strict';

	const l10n = window.ratingsL10n || {};

	/** Posts currently mid-vote, so a second click cannot double-submit. */
	const inFlight = new Set();

	/**
	 * Show or hide the loading indicator for a post.
	 *
	 * @param {number}  postId  Post id.
	 * @param {boolean} visible Whether to show it.
	 * @return {void}
	 */
	function toggleLoading( postId, visible ) {
		if ( ! Number( l10n.showLoading ) ) {
			return;
		}

		const loading = document.getElementById( 'post-ratings-' + postId + '-loading' );

		if ( loading ) {
			loading.style.display = visible ? '' : 'none';
		}
	}

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

		const container = document.getElementById( 'post-ratings-' + postId );

		if ( ! container ) {
			return;
		}

		const nonce = container.dataset.nonce || container.getAttribute( 'data-nonce' );

		inFlight.add( postId );

		// aria-busy rather than an opacity tween: it dims the control through
		// CSS *and* tells assistive technology the region is updating.
		if ( Number( l10n.showFading ) ) {
			container.setAttribute( 'aria-busy', 'true' );
		}

		toggleLoading( postId, true );

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

			toggleLoading( postId, false );
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
		// A scale is radio inputs, so "change" is the right signal: it fires
		// for a click and for arrow-key navigation alike, which is what makes
		// the control usable from the keyboard without any extra handling.
		document.addEventListener( 'change', function( event ) {
			const input = event.target.closest( '.post-ratings-scale input[type="radio"]' );

			if ( ! input ) {
				return;
			}

			const control = input.closest( '.post-ratings-vote' );

			if ( control ) {
				vote( control, Number( input.value ) );
			}
		} );

		// An up/down pair is two buttons, not a scale.
		document.addEventListener( 'click', function( event ) {
			const button = event.target.closest( '.post-ratings-updown button' );

			if ( ! button ) {
				return;
			}

			event.preventDefault();

			const control = button.closest( '.post-ratings-vote' );

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
