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
	 * Posts this visitor has voted on, and never emptied again.
	 *
	 * Separate from inFlight, which is emptied the moment the vote lands. A
	 * refresh resolving a heartbeat later would then find nothing in the way
	 * and paint the visitor's own result over with what the page should have
	 * shown before they clicked.
	 */
	const voted = new Set();

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
		voted.add( postId );

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
	 * Replace every rating on the page with one built for this visitor.
	 *
	 * Only runs where the site has said its pages are cached. The markup on a
	 * cached page was rendered for whoever filled the cache: its vote counts
	 * are that moment's, the choice between the control and the read-only
	 * result is that visitor's, and its nonce has been ageing ever since. One
	 * request answers for every rating on the page and puts all three right.
	 *
	 * A rating this visitor has voted on is skipped, whether that vote has
	 * landed yet or not: they got there first, and their result is newer than
	 * anything this was sent to say.
	 *
	 * @return {void}
	 */
	function refresh() {
		const containers = new Map();

		// The class, not an id prefix: every radio on a scale is
		// wp-postratings-<post>-<step>, so a prefix match would offer up
		// "4-2" as a post id and quietly ask about a post that is not there.
		document.querySelectorAll( '.wp-postratings' ).forEach( function( el ) {
			const postId = Number( el.id.slice( 'wp-postratings-'.length ) );

			if ( ! postId ) {
				return;
			}

			// A list rather than the element, because one post can be on the
			// page twice -- a [ratings] shortcode and the block both point at
			// whichever post they were given, and both are supported. Keeping
			// only the last would correct one of them and leave the other
			// showing what the cache had.
			containers.set( postId, ( containers.get( postId ) || [] ).concat( el ) );
		} );

		if ( ! containers.size ) {
			return;
		}

		const url = l10n.restUrl + ( l10n.restUrl.includes( '?' ) ? '&' : '?' ) +
			'ids=' + [ ...containers.keys() ].join( ',' );

		window
			.fetch( url, { credentials: 'same-origin' } )
			.then( function( response ) {
				return response.json();
			} )
			.then( function( data ) {
				( data.posts || [] ).forEach( function( post ) {
					if ( voted.has( post.post_id ) ) {
						return;
					}

					( containers.get( post.post_id ) || [] ).forEach( function( container ) {
						container.innerHTML = post.visitor_html;

						if ( post.nonce ) {
							container.dataset.nonce = post.nonce;
						} else {
							delete container.dataset.nonce;
						}
					} );
				} );
			} )
			.catch( function() {
				// The cached markup stays. Stale counts are worse than fresh
				// ones and better than an empty box.
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

		if ( l10n.refresh ) {
			refresh();
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
