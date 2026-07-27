/**
 * WP-PostRatings front end.
 *
 * One delegated listener on document handles hover and click for every rating
 * image on the page. Before 2.0.0 this was inline onmouseover/onclick attributes
 * carrying per-rating text through esc_js( esc_attr( ... ) ), which is where the
 * plugin's escaping bugs lived.
 */
( function() {
	'use strict';

	const l10n = window.ratingsL10n || {};

	let isBeingRated = false;
	let currentPostId = 0;
	let currentRating = 0;

	/**
	 * URL of one rating image in the configured set.
	 *
	 * @param {string} file File name without the extension.
	 * @return {string} Absolute URL.
	 */
	function imageUrl( file ) {
		return l10n.pluginUrl + '/images/' + l10n.image + '/' + file + '.' + l10n.imageExt;
	}

	/**
	 * The base name for one position on the scale.
	 *
	 * A custom set has a distinct image per position; a normal set shares one.
	 *
	 * @param {number} position Position on the scale.
	 * @return {string} File name prefix.
	 */
	function prefixFor( position ) {
		return Number( l10n.custom ) ? 'rating_' + position : 'rating';
	}

	/**
	 * Every vote image belonging to one post.
	 *
	 * @param {number} postId Post id.
	 * @return {Array} Image elements.
	 */
	function imagesFor( postId ) {
		return Array.prototype.slice.call(
			document.querySelectorAll( '.post-ratings-vote[data-post-id="' + postId + '"]' ),
		);
	}

	/**
	 * Paint the strip up to the hovered position.
	 *
	 * @param {Element} image Image being hovered.
	 * @return {void}
	 */
	function highlight( image ) {
		if ( isBeingRated ) {
			return;
		}

		const postId = Number( image.dataset.postId );
		const rating = Number( image.dataset.rating );

		currentPostId = postId;
		currentRating = rating;

		const max = Number( l10n.max );

		imagesFor( postId ).forEach( function( candidate ) {
			const position = Number( candidate.dataset.rating );

			// An up/down set only ever lights the image under the cursor.
			if ( Number( l10n.custom ) && 2 === max ) {
				if ( position === rating ) {
					candidate.src = imageUrl( prefixFor( position ) + '_over' );
				}
				return;
			}

			if ( position <= rating ) {
				candidate.src = imageUrl( prefixFor( position ) + '_over' );
			}
		} );

		const text = document.getElementById( 'ratings_' + postId + '_text' );

		if ( text ) {
			text.style.display = '';
			text.textContent = image.dataset.ratingText || '';
		}
	}

	/**
	 * Put the strip back to the stored rating.
	 *
	 * @param {Element} image Image being left.
	 * @return {void}
	 */
	function restore( image ) {
		if ( isBeingRated ) {
			return;
		}

		const postId = Number( image.dataset.postId );
		const postRating = Number( image.dataset.postRating );
		const insertHalf = Number( image.dataset.insertHalf );
		const halfRtl = Number( image.dataset.halfRtl );

		imagesFor( postId ).forEach( function( candidate ) {
			const position = Number( candidate.dataset.rating );
			const prefix = prefixFor( position );

			if ( position <= postRating ) {
				candidate.src = imageUrl( prefix + '_on' );
			} else if ( position === insertHalf ) {
				candidate.src = imageUrl( prefix + '_half' + ( halfRtl ? '-rtl' : '' ) );
			} else {
				candidate.src = imageUrl( prefix + '_off' );
			}
		} );

		const text = document.getElementById( 'ratings_' + postId + '_text' );

		if ( text ) {
			text.style.display = 'none';
			text.textContent = '';
		}
	}

	/**
	 * Fade an element by stepping its opacity.
	 *
	 * @param {Element}  element Element to fade.
	 * @param {number}   to      Target opacity.
	 * @param {Function} done    Called when finished.
	 * @return {void}
	 */
	function fade( element, to, done ) {
		if ( ! element || ! Number( l10n.showFading ) ) {
			if ( element ) {
				element.style.opacity = to;
			}
			done();
			return;
		}

		const from = '' === element.style.opacity ? 1 : parseFloat( element.style.opacity );
		let startedAt = null;
		const duration = 400;

		function step( timestamp ) {
			if ( null === startedAt ) {
				startedAt = timestamp;
			}

			const progress = Math.min( ( timestamp - startedAt ) / duration, 1 );

			element.style.opacity = from + ( ( to - from ) * progress );

			if ( progress < 1 ) {
				window.requestAnimationFrame( step );
				return;
			}

			done();
		}

		window.requestAnimationFrame( step );
	}

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
	 * Post the vote and swap in the response.
	 *
	 * @return {void}
	 */
	function ratePost() {
		if ( isBeingRated ) {
			// eslint-disable-next-line no-alert -- Long-standing behaviour: warns rather than queueing a second vote.
			window.alert( l10n.textWait );
			return;
		}

		const postId = currentPostId;
		const container = document.getElementById( 'post-ratings-' + postId );

		if ( ! container ) {
			return;
		}

		const nonce = container.dataset.nonce || container.getAttribute( 'data-nonce' );

		isBeingRated = true;

		fade( container, 0, function() {
			toggleLoading( postId, true );

			const body = new URLSearchParams();

			body.append( 'action', 'postratings' );
			body.append( 'pid', postId );
			body.append( 'rate', currentRating );
			body.append( 'postratings_' + postId + '_nonce', nonce );

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
				.then( function( html ) {
					container.innerHTML = html;
					toggleLoading( postId, false );
					fade( container, 1, function() {
						isBeingRated = false;
					} );
				} )
				.catch( function() {
					toggleLoading( postId, false );
					fade( container, 1, function() {
						isBeingRated = false;
					} );
				} );
		} );
	}

	/**
	 * Attach the delegated listeners.
	 *
	 * @return {void}
	 */
	function start() {
		document.addEventListener(
			'mouseover',
			function( event ) {
				const image = event.target.closest( '.post-ratings-vote' );

				if ( image ) {
					highlight( image );
				}
			},
			true,
		);

		document.addEventListener(
			'mouseout',
			function( event ) {
				const image = event.target.closest( '.post-ratings-vote' );

				if ( image ) {
					restore( image );
				}
			},
			true,
		);

		document.addEventListener( 'click', function( event ) {
			const image = event.target.closest( '.post-ratings-vote' );

			if ( image ) {
				event.preventDefault();
				highlight( image );
				ratePost();
			}
		} );

		document.addEventListener( 'keydown', function( event ) {
			if ( 'Enter' !== event.key && ' ' !== event.key ) {
				return;
			}

			const image = event.target.closest( '.post-ratings-vote' );

			if ( image ) {
				event.preventDefault();
				highlight( image );
				ratePost();
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
