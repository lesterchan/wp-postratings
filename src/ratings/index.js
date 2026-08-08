/**
 * The `wp-postratings/ratings` block.
 *
 * A dynamic block: `save` returns null, so nothing but the block comment is
 * written into post_content and every view re-renders from PHP. That is what
 * makes the block and the `[ratings]` shortcode able to share one renderer --
 * the markup is decided in exactly one place, at render time, for both of
 * them, and a site that changes its shape or its templates sees the change in
 * posts it never re-saved.
 *
 * The preview is core's ServerSideRender, which posts the attributes to
 * /wp/v2/block-renderer/wp-postratings/ratings and draws what the front end
 * would draw. That is also why this block registers no REST route of its own.
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';

import metadata from './block.json';

/**
 * The editor view.
 *
 * A named component with a capitalised name rather than an `edit()` shorthand
 * on the settings object: useBlockProps is a React hook, and the hook rules can
 * only tell a component from a plain function by that capital.
 *
 * The post is chosen by id rather than from a dropdown of posts. Any site big
 * enough to want the control is too big for a select, and the plugin's REST
 * namespace carries only what its AJAX endpoint already carried -- reading a
 * post's rating and casting one. A picker would be a route invented for the
 * convenience of one control.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @return {Element} The editor view.
 */
function Edit( { attributes, setAttributes } ) {
	const { id, results } = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Ratings', 'wp-postratings' ) }>
					<TextControl
						__nextHasNoMarginBottom
						label={ __( 'Post ID', 'wp-postratings' ) }
						help={ __(
							'Zero rates the post this block sits in, which is what an empty [ratings] shortcode does.',
							'wp-postratings',
						) }
						type="number"
						min={ 0 }
						value={ id }
						onChange={ ( value ) =>
							setAttributes( { id: parseInt( value, 10 ) || 0 } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show the result only', 'wp-postratings' ) }
						help={ __(
							'Displays the rating without offering to change it, for putting a post’s score somewhere it should not be voted from.',
							'wp-postratings',
						) }
						checked={ results }
						onChange={ ( value ) =>
							setAttributes( { results: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
				{ /* Rating from inside the editor would cast a real vote, so
				     the preview is deliberately not interactive. */ }
				<div inert="">
					<ServerSideRender
						block={ metadata.name }
						attributes={ attributes }
					/>
				</div>
			</div>
		</>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,

	save() {
		return null;
	},
} );
