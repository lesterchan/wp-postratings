<?php
/**
 * WP-PostRatings class-wp-postratings-shapes.php
 *
 * @package WP-PostRatings
 */

defined( 'ABSPATH' ) || exit;

/**
 * The rating shapes, as SVG.
 *
 * Before 2.0.0 the plugin shipped 121 GIF and PNG files across 16 folders. Ten
 * of those folders were the same handful of shapes in different colours and
 * finishes -- stars, stars_crystal, stars_dark, stars_png and stars_flat_png
 * are one star -- which is a job for a CSS custom property, not for four
 * directories of images.
 *
 * Every shape is delivered as a mask, so the markup carries no image at all and
 * the colour is whatever background-color the theme sets.
 *
 * @since 2.0.0
 */
class WP_PostRatings_Shapes {

	/**
	 * Scale shapes: repeated once per point on the scale.
	 */
	const SCALE = 'scale';

	/**
	 * Up/down shapes: exactly two opposing glyphs.
	 */
	const UPDOWN = 'updown';

	/**
	 * Numeric shapes: the position on the scale, drawn as its own number.
	 *
	 * The odd one out, and deliberately so. Every other shape is one path
	 * repeated once per point, which is why a set of digits could not be one:
	 * the glyph has to differ per position, and a mask by definition does not.
	 * So a numeric shape carries no path at all and the position supplies the
	 * glyph -- which also means it needs no artwork, and reads in whatever
	 * digits the site's locale uses.
	 */
	const NUMERIC = 'numeric';

	/**
	 * Every shape the plugin knows, including any a site has registered.
	 *
	 * The paths are all drawn in a 24x24 viewBox so one set of CSS sizing rules
	 * covers them.
	 *
	 * This is the single place shapes are enumerated: the settings picker, the
	 * sanitizer's allow list and the stylesheet all read from here, so a shape
	 * added through the filter is immediately selectable and savable. Deriving
	 * them separately is how a plugin ends up offering a choice its own
	 * sanitizer rejects.
	 *
	 * @return array
	 */
	public static function all() {
		$shapes = self::builtin();

		/**
		 * Filters the rating shapes.
		 *
		 * Replaces the pre-2.0.0 arrangement of dropping a folder of images
		 * into the plugin directory, which did not survive an update. A shape
		 * is an array of:
		 *
		 *     'type'  => WP_PostRatings_Shapes::SCALE, ::UPDOWN or ::NUMERIC
		 *     'label' => Name shown on the settings screen
		 *     'path'  => SVG path data in a 24x24 viewBox   (SCALE)
		 *     'up'    => SVG path data for the positive glyph (UPDOWN)
		 *     'down'  => SVG path data for the negative glyph (UPDOWN)
		 *
		 * A NUMERIC shape needs no artwork: the position is the glyph.
		 *
		 * Colour, size and spacing are CSS custom properties on
		 * .wp-postratings, so restyling an existing shape needs no PHP at all.
		 *
		 * @since 2.0.0
		 *
		 * @param array $shapes Shape definitions keyed by name.
		 */
		$shapes = apply_filters( 'wp_postratings_shapes', $shapes );

		return is_array( $shapes ) ? array_filter( $shapes, array( __CLASS__, 'is_valid' ) ) : self::builtin();
	}

	/**
	 * Whether a registered shape is usable.
	 *
	 * A malformed entry is dropped rather than allowed to render an empty mask
	 * or fatal on a missing key.
	 *
	 * @param mixed $shape Candidate shape.
	 *
	 * @return bool
	 */
	public static function is_valid( $shape ) {
		if ( ! is_array( $shape ) || empty( $shape['type'] ) ) {
			return false;
		}

		if ( self::UPDOWN === $shape['type'] ) {
			return ! empty( $shape['up'] ) && ! empty( $shape['down'] );
		}

		// A numeric shape is complete without a path, because the position it
		// is drawn at is the whole of its artwork.
		if ( self::NUMERIC === $shape['type'] ) {
			return true;
		}

		return self::SCALE === $shape['type'] && ! empty( $shape['path'] );
	}

	/**
	 * The shapes the plugin ships.
	 *
	 * @return array
	 */
	private static function builtin() {
		return array(
			// --- scale shapes -------------------------------------------------
			'star'      => array(
				'type'  => self::SCALE,
				'label' => __( 'Stars', 'wp-postratings' ),
				'path'  => 'M12 2.4l2.95 5.98 6.6.96-4.77 4.65 1.12 6.57L12 17.46l-5.9 3.1 1.12-6.57L2.45 9.34l6.6-.96z',
			),
			'heart'     => array(
				'type'  => self::SCALE,
				'label' => __( 'Hearts', 'wp-postratings' ),
				'path'  => 'M12 21l-1.45-1.32C5.4 15.01 2 11.93 2 8.15 2 5.07 4.42 2.65 7.5 2.65c1.74 0 3.41.81 4.5 2.09 1.09-1.28 2.76-2.09 4.5-2.09 3.08 0 5.5 2.42 5.5 5.5 0 3.78-3.4 6.86-8.55 11.53z',
			),
			'square'    => array(
				'type'  => self::SCALE,
				'label' => __( 'Squares', 'wp-postratings' ),
				'path'  => 'M3.5 3.5h17v17h-17z',
			),
			'bar'       => array(
				'type'  => self::SCALE,
				'label' => __( 'Bars', 'wp-postratings' ),
				'path'  => 'M2 8h20v8H2z',
			),
			'circle'    => array(
				'type'  => self::SCALE,
				'label' => __( 'Circles', 'wp-postratings' ),
				'path'  => 'M12 2.5A9.5 9.5 0 1 1 12 21.5 9.5 9.5 0 0 1 12 2.5z',
			),

			// --- numeric shapes -----------------------------------------------
			'number'    => array(
				'type'  => self::NUMERIC,
				'label' => __( 'Numbers', 'wp-postratings' ),
			),

			// --- up/down shapes -----------------------------------------------
			'thumb'     => array(
				'type'  => self::UPDOWN,
				'label' => __( 'Thumbs up / down', 'wp-postratings' ),
				'up'    => 'M2 9.5h4v11H2zm6 0L12.6 2c.9 0 1.6.8 1.4 1.7L13.3 8H20a2 2 0 0 1 2 2.3l-1.2 7.4a2.6 2.6 0 0 1-2.6 2.2H8z',
				'down'  => 'M2 3.5h4v11H2zm6 11L12.6 22c.9 0 1.6-.8 1.4-1.7L13.3 16H20a2 2 0 0 0 2-2.3l-1.2-7.4A2.6 2.6 0 0 0 18.2 4H8z',
			),
			'plusminus' => array(
				'type'  => self::UPDOWN,
				'label' => __( 'Plus / minus', 'wp-postratings' ),
				'up'    => 'M12 2.5a9.5 9.5 0 1 0 0 19 9.5 9.5 0 0 0 0-19zm1.2 8.3h4v2.4h-4v4h-2.4v-4h-4v-2.4h4v-4h2.4z',
				'down'  => 'M12 2.5a9.5 9.5 0 1 0 0 19 9.5 9.5 0 0 0 0-19zm5.2 8.3v2.4H6.8v-2.4z',
			),
			'tickcross' => array(
				'type'  => self::UPDOWN,
				'label' => __( 'Tick / cross', 'wp-postratings' ),
				'up'    => 'M12 2.5a9.5 9.5 0 1 0 0 19 9.5 9.5 0 0 0 0-19zm-1.4 13.9l-4-4 1.7-1.7 2.3 2.3 5.1-5.1 1.7 1.7z',
				'down'  => 'M12 2.5a9.5 9.5 0 1 0 0 19 9.5 9.5 0 0 0 0-19zm4.2 12.1l-1.6 1.6L12 13.6l-2.6 2.6-1.6-1.6L10.4 12 7.8 9.4l1.6-1.6L12 10.4l2.6-2.6 1.6 1.6L13.6 12z',
			),
			'updown'    => array(
				'type'  => self::UPDOWN,
				'label' => __( 'Arrows up / down', 'wp-postratings' ),
				'up'    => 'M12 2.5l9.5 9.5h-5.7v9.5H8.2V12H2.5z',
				'down'  => 'M12 21.5L2.5 12h5.7V2.5h7.6V12h5.7z',
			),
		);
	}

	/**
	 * Shape names, for an allow list.
	 *
	 * @return array
	 */
	public static function names() {
		return array_keys( self::all() );
	}

	/**
	 * One shape's definition, or null.
	 *
	 * @param string $name Shape name.
	 *
	 * @return array|null
	 */
	public static function get( $name ) {
		$shapes = self::all();

		return isset( $shapes[ $name ] ) ? $shapes[ $name ] : null;
	}

	/**
	 * Whether a shape is the two-point up/down kind.
	 *
	 * The two families are different controls, not two skins: a scale is one
	 * value out of N, an up/down is a pair of opposing actions.
	 *
	 * @param string $name Shape name.
	 *
	 * @return bool
	 */
	public static function is_updown( $name ) {
		$shape = self::get( $name );

		return $shape && self::UPDOWN === $shape['type'];
	}

	/**
	 * Whether a shape draws the position rather than a mask.
	 *
	 * It is still a scale -- one value out of N -- so everything that asks
	 * whether this is an up/down pair goes on getting no for an answer. What
	 * changes is only what a point on it is drawn with, which is why this is a
	 * separate question rather than a third branch of is_updown().
	 *
	 * @param string $name Shape name.
	 *
	 * @return bool
	 */
	public static function is_numeric_shape( $name ) {
		$shape = self::get( $name );

		return $shape && self::NUMERIC === $shape['type'];
	}

	/**
	 * A shape's SVG as a data URI, for use as a CSS mask.
	 *
	 * Encoded only where it must be, which is smaller and more legible than
	 * base64. The single quotes matter: an unquoted CSS url() token may not
	 * contain a quote character, so leaving them raw makes the browser discard
	 * the whole declaration -- silently, with no mask and no error.
	 *
	 * @param string $name    Shape name.
	 * @param string $variant '', 'up' or 'down'.
	 *
	 * @return string
	 */
	public static function data_uri( $name, $variant = '' ) {
		$shape = self::get( $name );

		if ( ! $shape || self::NUMERIC === $shape['type'] ) {
			return '';
		}

		if ( self::UPDOWN === $shape['type'] ) {
			$path = 'down' === $variant ? $shape['down'] : $shape['up'];
		} else {
			$path = $shape['path'];
		}

		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="' . $path . '"/></svg>';

		$svg = str_replace( '"', "'", $svg );

		return 'data:image/svg+xml,' . str_replace(
			array( '%', '#', '<', '>', "'", ' ' ),
			array( '%25', '%23', '%3C', '%3E', '%27', '%20' ),
			$svg
		);
	}

	/**
	 * Map a pre-2.0.0 image folder name onto a shape.
	 *
	 * The colour and finish variants collapse: stars, stars_crystal,
	 * stars_dark, stars_png and stars_flat_png were one shape all along.
	 *
	 * @return array
	 */
	public static function legacy_map() {
		return array(
			'stars'             => 'star',
			'stars_crystal'     => 'star',
			'stars_dark'        => 'star',
			'stars_png'         => 'star',
			'stars_flat_png'    => 'star',
			'heart'             => 'heart',
			'heart_crystal'     => 'heart',
			'squares'           => 'square',
			'bars'              => 'bar',
			'numbers'           => 'number',
			'thumbs'            => 'thumb',
			'plusminus'         => 'plusminus',
			'plusminus_crystal' => 'plusminus',
			'tickcross'         => 'tickcross',
			'tickcross_crystal' => 'tickcross',
			'updown_crystal'    => 'updown',
		);
	}

	/**
	 * The shape a stored image-set name becomes.
	 *
	 * Anything unrecognised -- a folder the site added itself -- falls back to
	 * stars rather than rendering nothing.
	 *
	 * @param string $legacy Stored image set name.
	 *
	 * @return string
	 */
	public static function from_legacy( $legacy ) {
		$map = self::legacy_map();

		return isset( $map[ $legacy ] ) ? $map[ $legacy ] : 'star';
	}
}
