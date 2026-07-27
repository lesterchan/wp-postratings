/**
 * WP-PostRatings settings screen.
 *
 * Delegated listeners for the "Restore Default" template buttons, the rating
 * text/value table refresh, and the rich snippet toggle.
 */
( function() {
	'use strict';

	const l10n = window.postratingsAdminL10n || {};

	/**
	 * Enable or disable the "ratings in rich snippets" radios to match the
	 * parent toggle.
	 *
	 * @return {void}
	 */
	function syncRichSnippetRatings() {
		const parent = document.getElementById( 'postratings_richsnippet_on' );

		if ( ! parent ) {
			return;
		}

		const disabled = ! parent.checked;

		Array.prototype.forEach.call(
			document.querySelectorAll( '.postratings-richsnippet-ratings' ),
			function( input ) {
				input.disabled = disabled;
			},
		);
	}

	/**
	 * Rebuild the rating text/value table for the selected image set.
	 *
	 * @return {void}
	 */
	function refreshRatingFields() {
		const button = document.getElementById( 'postratings-refresh-ratings' );
		const target = document.getElementById( 'postratings-rating-fields' );
		const image = document.querySelector( 'input[name$="[image]"]:checked' );

		if ( ! button || ! target || ! image ) {
			return;
		}

		const spinner = document.getElementById( 'postratings-spinner' );
		const custom = document.getElementById( 'postratings_customrating' );
		const max = document.getElementById( 'postratings_max' );

		if ( spinner ) {
			spinner.classList.add( 'is-active' );
		}

		const query = new URLSearchParams( {
			action: 'postratings_rating_fields',
			_ajax_nonce: button.dataset.nonce,
			custom: custom ? custom.value : '0',
			max: max ? max.value : '0',
			image: image.value,
		} );

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
			.finally( function() {
				if ( spinner ) {
					spinner.classList.remove( 'is-active' );
				}
			} );
	}

	/**
	 * Apply the chosen image set's shape to the Max Ratings field.
	 *
	 * A custom set fixes the number of steps, so the field is made read only.
	 *
	 * @param {Element} input The radio that was chosen.
	 * @return {void}
	 */
	function applyImageChoice( input ) {
		const custom = document.getElementById( 'postratings_customrating' );
		const max = document.getElementById( 'postratings_max' );

		if ( custom ) {
			custom.value = input.dataset.custom;
		}

		if ( max ) {
			max.value = input.dataset.max;
			max.readOnly = '1' === input.dataset.custom;
		}
	}

	document.addEventListener( 'click', function( event ) {
		const restore = event.target.closest( '.postratings-restore-template' );

		if ( restore ) {
			event.preventDefault();

			const name = restore.dataset.template;
			const set = 'updown' === restore.dataset.variant ? l10n.updownTemplates : l10n.defaultTemplates;
			const textarea = document.getElementById( 'postratings_template_' + name );

			if ( textarea && set && set[ name ] ) {
				textarea.value = set[ name ];
			}

			return;
		}

		if ( event.target.closest( '#postratings-refresh-ratings' ) ) {
			event.preventDefault();
			refreshRatingFields();
			return;
		}

		const imageChoice = event.target.closest( '.postratings-image-choice' );

		if ( imageChoice ) {
			applyImageChoice( imageChoice );
			return;
		}

		const deleteButton = event.target.closest( '#postratings-delete-data' );

		// eslint-disable-next-line no-alert -- Deleting rating data is irreversible.
		if ( deleteButton && ! window.confirm( deleteButton.dataset.confirm ) ) {
			event.preventDefault();
		}
	} );

	document.addEventListener( 'change', function( event ) {
		if ( event.target.closest( 'input[name$="[richsnippet]"]' ) ) {
			syncRichSnippetRatings();
		}
	} );
}() );
