/**
 * WP-PostRatings settings screen.
 *
 * Delegated listeners for the template reset buttons, the rating
 * text/value table refresh, and the rich snippet toggle.
 */
( function() {
	'use strict';

	const l10n = window.wpPostRatingsL10n || {};

	/**
	 * How many steps the table should have after a change.
	 *
	 * Counted from the rows on screen: the table is the scale, and a separate
	 * Max field would be a second place holding one fact.
	 *
	 * @param {number} delta Steps to add, or a negative number to remove.
	 * @return {number} The number of steps to render.
	 */
	function steps( delta ) {
		const chosen = document.querySelector( '.wp-postratings-shape-choice:checked' );

		// A type change restarts from that type's own default length; anything
		// else keeps what is on screen.
		if ( 'reset' === delta ) {
			return chosen ? Number( chosen.dataset.max ) : 0;
		}

		const target = document.getElementById( 'wp-postratings-rating-fields' );
		const rows = target ? target.querySelectorAll( 'tbody tr' ).length : 0;

		return rows + ( delta || 0 );
	}

	/**
	 * Rebuild the rating text/value table for the selected shape.
	 *
	 * @param {number|string} delta   Steps to add, a negative number to remove,
	 *                                or 'reset' to start the shape from its own
	 *                                defaults.
	 * @param {number}        removed Index of the row being removed, if any.
	 * @return {void}
	 */
	function refreshRatingFields( delta, removed ) {
		const target = document.getElementById( 'wp-postratings-rating-fields' );
		const shape = document.querySelector( '.wp-postratings-shape-choice:checked' );

		if ( ! target || ! shape ) {
			return;
		}

		const spinner = document.getElementById( 'wp-postratings-spinner' );

		if ( spinner ) {
			spinner.classList.add( 'is-active' );
		}

		// The shape is the whole request. Whether the rating is a scale or an
		// up/down pair follows from it, and it used to be sent alongside as a
		// separate "custom" flag read from a hidden field -- which was removed
		// with the Max field, leaving the flag permanently saying "scale".
		const query = new URLSearchParams( {
			action: 'wp_postratings_rating_fields',
			_ajax_nonce: target.dataset.nonce,
			max: String( steps( delta ) ),
			shape: shape.value,
		} );

		/* The typed table travels only when adding or removing a step. A change
		   of shape is a different rating -- and an up/down vote is signed, so
		   carrying its -1 into a scale left the first row at -1. */
		if ( 'reset' !== delta ) {
			[ 'text', 'value', 'color', 'color_off' ].forEach( function( field ) {
				Array.prototype.forEach.call(
					target.querySelectorAll( '[name$="[' + field + '][]"]' ),
					function( input, index ) {
						if ( 'number' !== typeof removed || index !== removed ) {
							// field[] rather than field: PHP builds an array
							// from repeated parameters only with brackets.
							query.append( field + '[]', input.value );
						}
					},
				);
			} );
		}

		window
			.fetch( l10n.ajaxUrl + '?' + query.toString(), {
				credentials: 'same-origin',
			} )
			.then( function( response ) {
				return response.text();
			} )
			.then( function( html ) {
				target.innerHTML = html;
			} )
			.catch( function() {
				// Leaves the existing rows in place. Without this the rejection
				// is unhandled and surfaces in the browser console.
			} )
			.finally( function() {
				if ( spinner ) {
					spinner.classList.remove( 'is-active' );
				}
			} );
	}

	/**
	 * Paint a swatch's colour onto the preview in its own row.
	 *
	 * The preview is the front end's own markup, so setting the two custom
	 * properties on the cell shows the real thing.
	 *
	 * @param {Element} input The colour input that changed.
	 * @return {void}
	 */
	function paintPreview( input ) {
		const row = input.closest( 'tr' );

		if ( ! row ) {
			return;
		}

		const preview = row.querySelector( '.wp-postratings-rating-preview' );

		if ( preview && input.dataset.property ) {
			preview.style.setProperty( input.dataset.property, input.value );
		}
	}

	// Colour inputs fire "input" continuously while the picker is open, so the
	// preview follows the colour as it is being chosen rather than only once the
	// picker closes.
	document.addEventListener( 'input', function( event ) {
		const swatch = event.target.closest( '.wp-postratings-color' );

		if ( swatch ) {
			paintPreview( swatch );
		}
	} );

	/**
	 * Show only the shapes belonging to the chosen rating type.
	 *
	 * The type is not stored: a shape knows whether it is a scale or a pair of
	 * opposing actions, so choosing a type is really choosing among that type's
	 * shapes. Picking one selects the first of them, which keeps the form
	 * consistent with itself without a second setting to disagree with.
	 *
	 * @param {string} type The chosen type.
	 * @return {void}
	 */
	function showShapesOfType( type ) {
		let first = null;

		Array.prototype.forEach.call(
			document.querySelectorAll( '.wp-postratings-shape-row' ),
			function( row ) {
				const matches = row.dataset.ratingType === type;

				row.hidden = ! matches;

				if ( matches && ! first ) {
					first = row.querySelector( '.wp-postratings-shape-choice' );
				}
			},
		);

		const checked = document.querySelector( '.wp-postratings-shape-choice:checked' );

		if ( first && ( ! checked || checked.closest( '.wp-postratings-shape-row' ).hidden ) ) {
			first.checked = true;
			refreshRatingFields( 'reset' );
		}
	}

	document.addEventListener( 'change', function( event ) {
		const type = event.target.closest( '.wp-postratings-rating-type' );

		if ( type ) {
			showShapesOfType( type.value );
		}
	} );

	document.addEventListener( 'click', function( event ) {
		const restore = event.target.closest( '.wp-postratings-restore-template' );

		if ( restore ) {
			event.preventDefault();

			const name = restore.dataset.template;
			const set = ( l10n.templates || {} )[ restore.dataset.variant ];
			const textarea = document.getElementById( 'wp_postratings_template_' + name );

			if ( textarea && set && set[ name ] ) {
				textarea.value = set[ name ];
			}

			return;
		}

		if ( event.target.closest( '.wp-postratings-shape-choice' ) ) {
			// Every shape change resets the table. A shape decides how many steps
			// there are and what they are called, so the labels, values and
			// colours belonging to the previous one have nothing to say about the
			// new one -- and carrying them is what put a scale's orange on a
			// thumbs pair and -1 in a scale's first row.
			refreshRatingFields( 'reset' );
			return;
		}

		if ( event.target.closest( '#wp-postratings-add-step' ) ) {
			event.preventDefault();
			refreshRatingFields( 1 );
			return;
		}

		const remove = event.target.closest( '.wp-postratings-remove-step' );

		if ( remove ) {
			event.preventDefault();

			const row = remove.closest( 'tr' );
			const rows = Array.prototype.slice.call( row.closest( 'tbody' ).children );

			refreshRatingFields( -1, rows.indexOf( row ) );
			return;
		}

		if ( event.target.closest( '#wp-postratings-reset-colors' ) ) {
			event.preventDefault();

			// Each swatch carries the built-in colour it resets to, so this needs
			// to know nothing about which column it is putting back.
			Array.prototype.forEach.call(
				document.querySelectorAll( '.wp-postratings-color' ),
				function( input ) {
					input.value = input.dataset.default;
					paintPreview( input );
				},
			);

			return;
		}

		const deleteButton = event.target.closest( '#wp-postratings-delete-data' );

		// eslint-disable-next-line no-alert -- Deleting rating data is irreversible.
		if ( deleteButton && ! window.confirm( deleteButton.dataset.confirm ) ) {
			event.preventDefault();
		}
	} );
}() );
