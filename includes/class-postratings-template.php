<?php
/**
 * WP-PostRatings class-postratings-template.php
 *
 * @package wp-postratings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the rating markup and expands the %TOKEN% templates.
 *
 * @since 2.0.0
 */
class Postratings_Template {

	/**
	 * Resolve a stored name to a shape.
	 *
	 * Installs that have not run the 2.0.0 migration yet still store an image
	 * folder name, so both are accepted and anything unrecognised falls back to
	 * stars. Rendering therefore stays correct at every step of the upgrade
	 * rather than only once the option has been rewritten.
	 *
	 * @param string $name Shape name, or a pre-2.0.0 image set name.
	 *
	 * @return string
	 */
	public static function resolve_shape( $name ) {
		$name = (string) $name;

		return Postratings_Shapes::get( $name ) ? $name : Postratings_Shapes::from_legacy( $name );
	}

	/**
	 * Resolve a name only if it is one the plugin recognises.
	 *
	 * The rendering fallback above is deliberately forgiving, because a page
	 * should still draw something. Anything that *accepts* a value needs the
	 * strict answer instead, and both the settings sanitizer and the rating
	 * fields endpoint use this one so they cannot disagree about what exists --
	 * which is precisely how the "numbers" set came to be offered by the screen
	 * and rejected on save.
	 *
	 * @param string $name Shape name, or a pre-2.0.0 image set name.
	 *
	 * @return string Shape name, or '' when unrecognised.
	 */
	public static function resolve_shape_strict( $name ) {
		$name = trim( (string) $name );

		if ( Postratings_Shapes::get( $name ) ) {
			return $name;
		}

		return array_key_exists( $name, Postratings_Shapes::legacy_map() )
			? Postratings_Shapes::from_legacy( $name )
			: '';
	}

	/**
	 * Inline custom properties carrying the shape's mask.
	 *
	 * The mask travels on the element rather than in the stylesheet so a site
	 * can register a shape through wp_postratings_shapes without also having to
	 * enqueue CSS for it.
	 *
	 * @param string $shape Shape name.
	 *
	 * @return string
	 */
	private static function shape_style( $shape ) {
		if ( Postratings_Shapes::is_updown( $shape ) ) {
			return '--wp-postratings-shape-up:url(' . Postratings_Shapes::data_uri( $shape, 'up' ) . ');' .
				'--wp-postratings-shape-down:url(' . Postratings_Shapes::data_uri( $shape, 'down' ) . ')';
		}

		return '--wp-postratings-shape:url(' . Postratings_Shapes::data_uri( $shape ) . ')';
	}

	/**
	 * How much of the strip reads as filled, as a percentage.
	 *
	 * Replaces the half-image rounding the GIF sets forced: an average of 3.7
	 * out of 5 renders as 74% rather than being snapped to 3.5.
	 *
	 * @param float $rating Rating to display.
	 * @param int   $max    Top of the scale.
	 *
	 * @return float
	 */
	public static function fill_percentage( $rating, $max ) {
		$max = (float) $max;

		if ( $max <= 0 ) {
			return 0.0;
		}

		return max( 0.0, min( 100.0, round( ( (float) $rating / $max ) * 100, 2 ) ) );
	}

	/**
	 * One row of shapes.
	 *
	 * @param int    $count Number of shapes.
	 * @param string $extra Extra style for each item.
	 *
	 * @return string
	 */
	private static function row( $count, $extra = '' ) {
		$style = '' !== $extra ? ' style="' . esc_attr( $extra ) . '"' : '';
		$item  = '<i class="post-ratings-item"' . $style . '></i>';

		return '<span class="post-ratings-row">' . str_repeat( $item, max( 0, (int) $count ) ) . '</span>';
	}

	/**
	 * The read-only rating strip.
	 *
	 * @param int    $ratings_custom Unused since 2.0.0; kept for the signature.
	 * @param int    $ratings_max    Number of points on the scale.
	 * @param float  $post_rating    Rating to display.
	 * @param string $shape          Shape name, or a pre-2.0.0 image set name.
	 * @param string $image_alt      Text describing the rating.
	 * @param int    $insert_half    Unused since 2.0.0; the fill is a percentage.
	 *
	 * @return string
	 */
	public static function ratings_images( $ratings_custom, $ratings_max, $post_rating, $shape, $image_alt, $insert_half = 0 ) {
		unset( $ratings_custom, $insert_half );

		$shape       = self::resolve_shape( $shape );
		$ratings_max = max( 1, (int) $ratings_max );
		$image_alt   = apply_filters( 'wp_postratings_ratings_image_alt', $image_alt );
		$fill        = self::fill_percentage( $post_rating, $ratings_max );

		$style = self::shape_style( $shape ) . ';--wp-postratings-fill:' . $fill . '%';

		$item_style = Postratings_Shapes::is_updown( $shape )
			? '--wp-postratings-shape:var(--wp-postratings-shape-up)'
			: '';

		$html  = '<span class="post-ratings-strip post-ratings-shape-' . esc_attr( $shape ) . '"';
		$html .= ' style="' . esc_attr( $style ) . '"';
		$html .= '' !== $image_alt ? ' role="img" aria-label="' . esc_attr( $image_alt ) . '"' : ' aria-hidden="true"';
		$html .= '>';
		$html .= '<span class="post-ratings-track" aria-hidden="true">' . self::row( $ratings_max, $item_style ) . '</span>';
		$html .= '<span class="post-ratings-fill" aria-hidden="true">' . self::row( $ratings_max, $item_style ) . '</span>';
		$html .= '</span>';

		return $html;
	}

	/**
	 * The clickable rating control.
	 *
	 * A scale is a radio group inside a fieldset, so it announces as one
	 * control and arrow keys move between the values. Before 2.0.0 each point
	 * was a separate image carrying role="button", which a screen reader read
	 * as five unrelated buttons.
	 *
	 * An up/down set is a pair of buttons instead, because it is two opposing
	 * actions rather than one value out of several.
	 *
	 * @param int    $post_id        Post being rated.
	 * @param int    $ratings_custom Unused since 2.0.0; kept for the signature.
	 * @param int    $ratings_max    Number of points on the scale.
	 * @param float  $post_rating    Unused since 2.0.0; the control is unset.
	 * @param string $shape          Shape name, or a pre-2.0.0 image set name.
	 * @param string $image_alt      Unused since 2.0.0; each value labels itself.
	 * @param int    $insert_half    Unused since 2.0.0.
	 * @param array  $ratings_texts  Per-value labels.
	 *
	 * @return string
	 */
	public static function ratings_images_vote( $post_id, $ratings_custom, $ratings_max, $post_rating, $shape, $image_alt, $insert_half, $ratings_texts ) {
		unset( $ratings_custom, $post_rating, $image_alt, $insert_half );

		$shape         = self::resolve_shape( $shape );
		$post_id       = (int) $post_id;
		$ratings_max   = max( 1, (int) $ratings_max );
		$ratings_texts = (array) $ratings_texts;
		$style         = self::shape_style( $shape );

		if ( Postratings_Shapes::is_updown( $shape ) ) {
			return self::updown_control( $post_id, $shape, $style, $ratings_texts );
		}

		return self::scale_control( $post_id, $shape, $style, $ratings_max, $ratings_texts );
	}

	/**
	 * A scale, as a radio group.
	 *
	 * The values are emitted highest first so a plain sibling combinator can
	 * light everything below the hovered one, which is what lets the hover
	 * behaviour be CSS rather than JavaScript rewriting image sources.
	 *
	 * @param int    $post_id       Post being rated.
	 * @param string $shape         Shape name.
	 * @param string $style         Inline custom properties.
	 * @param int    $ratings_max   Number of points.
	 * @param array  $ratings_texts Per-value labels.
	 *
	 * @return string
	 */
	private static function scale_control( $post_id, $shape, $style, $ratings_max, $ratings_texts ) {
		// A <span>, not a <fieldset>: the [ratings] shortcode wraps its output in
		// a <span>, which is phrasing content, and the parser hoists any flow
		// element straight back out of it -- leaving the control outside its own
		// container. role="radiogroup" with a label is equivalent for assistive
		// technology and nests legally.
		$html  = '<span class="post-ratings-vote post-ratings-scale post-ratings-shape-' . esc_attr( $shape ) . '"';
		$html .= ' style="' . esc_attr( $style ) . '" data-post-id="' . esc_attr( $post_id ) . '"';
		$html .= ' role="radiogroup" aria-label="' . esc_attr__( 'Rate this post', 'wp-postratings' ) . '">';

		for ( $i = $ratings_max; $i >= 1; $i-- ) {
			if ( isset( $ratings_texts[ $i - 1 ] ) && '' !== $ratings_texts[ $i - 1 ] ) {
				$label = (string) $ratings_texts[ $i - 1 ];
			} else {
				/* translators: %s: position on the rating scale. */
				$label = sprintf( _n( '%s Star', '%s Stars', $i, 'wp-postratings' ), number_format_i18n( $i ) );
			}

			$id = 'postratings-' . $post_id . '-' . $i;

			$html .= '<input type="radio" id="' . esc_attr( $id ) . '"';
			$html .= ' name="postratings-' . esc_attr( $post_id ) . '"';
			$html .= ' value="' . esc_attr( $i ) . '" data-rating="' . esc_attr( $i ) . '" />';
			$html .= '<label for="' . esc_attr( $id ) . '">';
			$html .= '<i class="post-ratings-item"></i><span>' . esc_html( $label ) . '</span>';
			$html .= '</label>';
		}

		return $html . '</span>';
	}

	/**
	 * An up/down pair, as two buttons.
	 *
	 * @param int    $post_id       Post being rated.
	 * @param string $shape         Shape name.
	 * @param string $style         Inline custom properties.
	 * @param array  $ratings_texts Per-value labels.
	 *
	 * @return string
	 */
	private static function updown_control( $post_id, $shape, $style, $ratings_texts ) {
		$down = isset( $ratings_texts[0] ) && '' !== $ratings_texts[0]
			? (string) $ratings_texts[0]
			: __( 'Vote Down', 'wp-postratings' );

		$up = isset( $ratings_texts[1] ) && '' !== $ratings_texts[1]
			? (string) $ratings_texts[1]
			: __( 'Vote Up', 'wp-postratings' );

		$html  = '<span class="post-ratings-vote post-ratings-updown post-ratings-shape-' . esc_attr( $shape ) . '"';
		$html .= ' style="' . esc_attr( $style ) . '" data-post-id="' . esc_attr( $post_id ) . '"';
		$html .= ' role="group" aria-label="' . esc_attr__( 'Vote on this post', 'wp-postratings' ) . '">';

		$buttons = array(
			array( 'up', 2, $up, '--wp-postratings-shape:var(--wp-postratings-shape-up)' ),
			array( 'down', 1, $down, '--wp-postratings-shape:var(--wp-postratings-shape-down)' ),
		);

		foreach ( $buttons as $button ) {
			list( $direction, $value, $label, $item_style ) = $button;

			$html .= '<button type="button" class="post-ratings-' . esc_attr( $direction ) . '"';
			$html .= ' data-rating="' . esc_attr( $value ) . '">';
			$html .= '<i class="post-ratings-item" style="' . esc_attr( $item_style ) . '"></i>';
			$html .= '<span>' . esc_html( $label ) . '</span>';
			$html .= '</button>';
		}

		return $html . '</span>';
	}

	/**
	 * The rating strip beside a comment author.
	 *
	 * @param int    $ratings_custom        Whether the set is an up/down one.
	 * @param int    $ratings_max           Number of points on the scale.
	 * @param int    $comment_author_rating Rating the author gave.
	 * @param string $shape                 Shape name, or a legacy image set.
	 * @param string $image_alt             Text describing the rating.
	 *
	 * @return string
	 */
	public static function ratings_images_comment_author( $ratings_custom, $ratings_max, $comment_author_rating, $shape, $image_alt ) {
		$shape = self::resolve_shape( $shape );

		// An up/down vote is one glyph, not a strip: the author voted one way
		// or the other.
		if ( Postratings_Shapes::is_updown( $shape ) || ( $ratings_custom && 2 === (int) $ratings_max ) ) {
			$direction = $comment_author_rating > 0 ? 'up' : 'down';
			$style     = self::shape_style( $shape ) . ';--wp-postratings-shape:var(--wp-postratings-shape-' . $direction . ')';

			// A single glyph in normal flow, not the track-and-fill overlay: the
			// fill layer is absolutely positioned, so on its own it leaves the
			// strip with no intrinsic size and nothing renders.
			return '<span class="post-ratings-strip post-ratings-single post-ratings-shape-' . esc_attr( $shape ) . '"' .
				' style="' . esc_attr( $style ) . '" role="img" aria-label="' . esc_attr( $image_alt ) . '">' .
				self::row( 1 ) .
				'</span>';
		}

		return self::ratings_images( 0, $ratings_max, $comment_author_rating, $shape, $image_alt );
	}

	/**
	 * Replace the %TOKEN% variables in a template.
	 *
	 * @param string      $template            Template with %TOKEN% placeholders.
	 * @param int|WP_Post $post_data           Post id or object.
	 * @param object|null $post_ratings_data   Pre-computed rating figures.
	 * @param int         $max_post_title_chars Truncate the title to this many characters.
	 * @param bool        $is_main_loop        Whether this is the main loop, for rich snippets.
	 *
	 * @return string
	 */
	public static function expand( $template, $post_data, $post_ratings_data = null, $max_post_title_chars = 0, $is_main_loop = true ) {
		global $post;

		// expand() reassigns the global $post to read another post's excerpt or
		// content. Without restoring it the rest of the loop renders against
		// whichever post this template happened to name.
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Reassigned to read another post's excerpt/content, and restored below.
		$original_post = $post;

		$options        = Postratings_Options::get();
		$ratings_image  = $options['image'];
		$ratings_max    = (int) $options['max'];
		$ratings_custom = (int) $options['customrating'];

		$post_id = is_object( $post_data ) ? (int) $post_data->ID : (int) $post_data;

		if ( isset( $post_data->ratings_users ) ) {
			// Most likely coming from a widget.
			$post_ratings_users   = (int) $post_data->ratings_users;
			$post_ratings_score   = (int) $post_data->ratings_score;
			$post_ratings_average = (float) $post_data->ratings_average;
		} elseif ( isset( $post_ratings_data->ratings_users ) ) {
			// Most likely coming from the_ratings_vote() or a fresh vote.
			$post_ratings_users   = (int) $post_ratings_data->ratings_users;
			$post_ratings_score   = (int) $post_ratings_data->ratings_score;
			$post_ratings_average = (float) $post_ratings_data->ratings_average;
		} else {
			$meta = get_the_ID() !== $post_id ? get_post_custom( $post_id ) : get_post_custom();

			$post_ratings_users   = is_array( $meta ) && isset( $meta['ratings_users'][0] ) ? (int) $meta['ratings_users'][0] : 0;
			$post_ratings_score   = is_array( $meta ) && isset( $meta['ratings_score'][0] ) ? (int) $meta['ratings_score'][0] : 0;
			$post_ratings_average = is_array( $meta ) && isset( $meta['ratings_average'][0] ) ? (float) $meta['ratings_average'][0] : 0;
		}

		if ( 0 === $post_ratings_score || 0 === $post_ratings_users ) {
			$post_ratings            = 0;
			$post_ratings_average    = 0;
			$post_ratings_percentage = 0;
		} else {
			$post_ratings            = round( $post_ratings_average, 1 );
			$post_ratings_percentage = round( ( ( $post_ratings_score / $post_ratings_users ) / $ratings_max ) * 100, 2 );
		}

		$post_ratings_text = '<span class="post-ratings-text" id="ratings_' . $post_id . '_text"></span>';

		if ( $ratings_custom && 2 === $ratings_max ) {
			if ( $post_ratings_score > 0 ) {
				$post_ratings_score = '+' . $post_ratings_score;
			}

			/* translators: %s: number of ratings. */
			$score_text = sprintf( _n( '%s rating', '%s ratings', $post_ratings_score, 'wp-postratings' ), number_format_i18n( $post_ratings_score ) );
			/* translators: %s: number of votes. */
			$votes_text = sprintf( _n( '%s vote', '%s votes', $post_ratings_users, 'wp-postratings' ), number_format_i18n( $post_ratings_users ) );

			$post_ratings_alt_text = esc_html( $score_text . __( ',', 'wp-postratings' ) . ' ' . $votes_text );
		} else {
			$post_ratings_score = number_format_i18n( $post_ratings_score );

			/* translators: %s: number of votes. */
			$votes_text = sprintf( _n( '%s vote', '%s votes', $post_ratings_users, 'wp-postratings' ), number_format_i18n( $post_ratings_users ) );

			$post_ratings_alt_text = esc_html(
				$votes_text . __( ',', 'wp-postratings' ) . ' ' . __( 'average', 'wp-postratings' ) . ': ' .
				number_format_i18n( $post_ratings_average, 2 ) . ' ' . __( 'out of', 'wp-postratings' ) . ' ' .
				number_format_i18n( $ratings_max )
			);
		}

		// Retained only to feed the two rendering calls' signatures; the fill
		// is derived from the rating itself now.
		$insert_half = 0;

		$value = $template;

		if ( false !== strpos( $template, '%RATINGS_IMAGES%' ) ) {
			$images = self::ratings_images( $ratings_custom, $ratings_max, $post_ratings, $ratings_image, $post_ratings_alt_text, $insert_half );
			$images = apply_filters( 'wp_postratings_ratings_images', $images, $post_id, $post_ratings, $ratings_max );
			$value  = str_replace( '%RATINGS_IMAGES%', $images, $value );
		}

		if ( false !== strpos( $template, '%RATINGS_IMAGES_VOTE%' ) ) {
			$ratings_texts = Postratings_Options::get_nested( 'ratings', 'text' );
			$images        = self::ratings_images_vote( $post_id, $ratings_custom, $ratings_max, $post_ratings, $ratings_image, $post_ratings_alt_text, $insert_half, (array) $ratings_texts );
			$images        = apply_filters( 'wp_postratings_ratings_images_vote', $images, $post_id, $post_ratings, $ratings_max );
			$value         = str_replace( '%RATINGS_IMAGES_VOTE%', $images, $value );
		}

		$value = str_replace(
			array(
				'%RATINGS_ALT_TEXT%',
				'%RATINGS_TEXT%',
				'%RATINGS_MAX%',
				'%RATINGS_SCORE%',
				'%RATINGS_AVERAGE%',
				'%RATINGS_PERCENTAGE%',
				'%RATINGS_USERS%',
			),
			array(
				$post_ratings_alt_text,
				$post_ratings_text,
				number_format_i18n( $ratings_max ),
				$post_ratings_score,
				number_format_i18n( $post_ratings_average, 2 ),
				number_format_i18n( $post_ratings_percentage, 2 ),
				number_format_i18n( $post_ratings_users ),
			),
			$value
		);

		$post_link  = get_permalink( $post_data );
		$post_title = get_the_title( $post_data );

		if ( $max_post_title_chars > 0 ) {
			$post_title = self::snippet( $post_title, $max_post_title_chars );
		}

		$value = str_replace(
			array( '%POST_ID%', '%POST_TITLE%', '%POST_URL%' ),
			array( $post_id, $post_title, $post_link ),
			$value
		);

		$post_excerpt = '';

		if ( false !== strpos( $template, '%POST_EXCERPT%' ) ) {
			if ( get_the_ID() !== $post_id ) {
				$post = get_post( $post_id );
			}

			$post_excerpt = self::post_excerpt( $post_id, $post->post_excerpt, $post->post_content );
			$value        = str_replace( '%POST_EXCERPT%', $post_excerpt, $value );
		}

		if ( false !== strpos( $template, '%POST_CONTENT%' ) ) {
			if ( get_the_ID() !== $post_id ) {
				$post = get_post( $post_id );
			}

			$value = str_replace( '%POST_CONTENT%', get_the_content(), $value );
		}

		if ( false !== strpos( $template, '%POST_THUMBNAIL%' ) ) {
			if ( get_the_ID() !== $post_id ) {
				$post = get_post( $post_id );
			}

			$value = str_replace( '%POST_THUMBNAIL%', get_the_post_thumbnail( $post, 'thumbnail' ), $value );
		}

		$structured_data = self::structured_data(
			$options,
			$post_id,
			$post_title,
			$post_link,
			$post_excerpt,
			$post_ratings_average,
			$post_ratings_users,
			$ratings_max,
			$is_main_loop
		);

		$post = $original_post;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		return apply_filters( 'wp_postratings_expand_ratings_template', $value . $structured_data, $post_id );
	}

	/**
	 * Build the Google rich snippet markup.
	 *
	 * @param array  $options              Plugin settings.
	 * @param int    $post_id              Post id.
	 * @param string $post_title           Post title.
	 * @param string $post_link            Post permalink.
	 * @param string $post_excerpt         Post excerpt.
	 * @param float  $post_ratings_average Average rating.
	 * @param int    $post_ratings_users   Number of voters.
	 * @param int    $ratings_max          Top of the scale.
	 * @param bool   $is_main_loop         Whether this is the main loop.
	 *
	 * @return string
	 */
	private static function structured_data( $options, $post_id, $post_title, $post_link, $post_excerpt, $post_ratings_average, $post_ratings_users, $ratings_max, $is_main_loop ) {
		global $post;

		if ( apply_filters( 'wp_postratings_disable_richsnippet', false ) ) {
			return '';
		}

		if ( empty( $options['richsnippet'] ) || ! is_singular() || ! $is_main_loop ) {
			return '';
		}

		$itemtype = apply_filters( 'wp_postratings_schema_itemtype', 'itemscope itemtype="https://schema.org/Article"' );

		if ( empty( $post_excerpt ) && ! empty( $post ) ) {
			$post_excerpt = self::post_excerpt( $post_id, $post->post_excerpt, $post->post_content );
		}

		$post_meta  = '<meta itemprop="name" content="' . esc_attr( $post_title ) . '" />';
		$post_meta .= '<meta itemprop="headline" content="' . esc_attr( $post_title ) . '" />';
		$post_meta .= '<meta itemprop="description" content="' . esc_attr( wp_kses( $post_excerpt, array() ) ) . '" />';
		$post_meta .= '<meta itemprop="datePublished" content="' . esc_attr( mysql2date( 'c', $post->post_date, false ) ) . '" />';
		$post_meta .= '<meta itemprop="dateModified" content="' . esc_attr( mysql2date( 'c', $post->post_modified, false ) ) . '" />';
		$post_meta .= '<meta itemprop="url" content="' . esc_url( $post_link ) . '" />';
		$post_meta .= '<meta itemprop="author" content="' . esc_attr( get_the_author() ) . '" />';
		$post_meta .= '<meta itemprop="mainEntityOfPage" content="' . esc_url( get_permalink() ) . '" />';

		$thumbnail = has_post_thumbnail() ? wp_get_attachment_image_src( get_post_thumbnail_id( null ) ) : '';
		$thumbnail = apply_filters( 'wp_postratings_post_thumbnail', $thumbnail, $post_id );

		if ( ! empty( $thumbnail ) ) {
			$post_meta .= '<div style="display: none;" itemprop="image" itemscope itemtype="https://schema.org/ImageObject">';
			$post_meta .= '<meta itemprop="url" content="' . esc_url( $thumbnail[0] ) . '" />';
			$post_meta .= '<meta itemprop="width" content="' . esc_attr( $thumbnail[1] ) . '" />';
			$post_meta .= '<meta itemprop="height" content="' . esc_attr( $thumbnail[2] ) . '" />';
			$post_meta .= '</div>';
		}

		$site_logo      = '';
		$custom_logo_id = get_theme_mod( 'custom_logo' );

		if ( $custom_logo_id ) {
			$custom_logo = wp_get_attachment_image_src( $custom_logo_id, 'full' );
			$site_logo   = ! empty( $custom_logo[0] ) ? $custom_logo[0] : '';
		}

		if ( empty( $site_logo ) && has_header_image() ) {
			$site_logo = get_header_image();
		}

		$site_logo = apply_filters( 'wp_postratings_site_logo', $site_logo );

		$post_meta .= '<div style="display: none;" itemprop="publisher" itemscope itemtype="https://schema.org/Organization">';
		$post_meta .= '<meta itemprop="name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />';
		$post_meta .= '<meta itemprop="url" content="' . esc_url( home_url() ) . '" />';
		$post_meta .= '<div itemprop="logo" itemscope itemtype="https://schema.org/ImageObject">';
		$post_meta .= '<meta itemprop="url" content="' . esc_url( $site_logo ) . '" />';
		$post_meta .= '</div>';
		$post_meta .= '</div>';

		$ratings_meta = '';

		if ( ! empty( $options['richsnippet_ratings'] ) && $post_ratings_average > 0 ) {
			$ratings_meta .= '<div style="display: none;" itemprop="aggregateRating" itemscope itemtype="https://schema.org/AggregateRating">';
			$ratings_meta .= '<meta itemprop="bestRating" content="' . esc_attr( $ratings_max ) . '" />';
			$ratings_meta .= '<meta itemprop="worstRating" content="1" />';
			$ratings_meta .= '<meta itemprop="ratingValue" content="' . esc_attr( $post_ratings_average ) . '" />';
			$ratings_meta .= '<meta itemprop="ratingCount" content="' . esc_attr( $post_ratings_users ) . '" />';
			$ratings_meta .= '</div>';
		}

		return apply_filters(
			'wp_postratings_google_structured_data',
			empty( $itemtype ) ? $ratings_meta : $post_meta . $ratings_meta
		);
	}

	/**
	 * Truncate text to a character count, entity-safely.
	 *
	 * @param string $text   Text to shorten.
	 * @param int    $length Maximum length.
	 *
	 * @return string
	 */
	public static function snippet( $text, $length = 0 ) {
		$charset = get_option( 'blog_charset' );
		$text    = html_entity_decode( $text, ENT_QUOTES, $charset );

		if ( mb_strlen( $text ) > $length ) {
			return htmlentities( mb_substr( $text, 0, $length ), ENT_COMPAT, $charset ) . '...';
		}

		return htmlentities( $text, ENT_COMPAT, $charset );
	}

	/**
	 * The excerpt used by %POST_EXCERPT% and the rich snippet description.
	 *
	 * @param int    $post_id      Post id.
	 * @param string $post_excerpt Stored excerpt.
	 * @param string $post_content Post content.
	 *
	 * @return string
	 */
	public static function post_excerpt( $post_id, $post_excerpt, $post_content ) {
		if ( post_password_required( $post_id ) ) {
			return esc_html__( 'There is no excerpt because this is a protected post.', 'wp-postratings' );
		}

		if ( empty( $post_excerpt ) ) {
			return self::snippet( wp_strip_all_tags( strip_shortcodes( $post_content ) ), 200 );
		}

		return strip_shortcodes( $post_excerpt );
	}
}
