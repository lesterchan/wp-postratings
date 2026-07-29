<?php
/**
 * WP-PostRatings class-wp-postratings-options.php
 *
 * @package wp-postratings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads, writes, sanitizes and migrates the plugin's settings.
 *
 * Before 2.0.0 the plugin owned fifteen autoloaded rows, six of them templates.
 * They are consolidated into the single `postratings_options` row the plugin
 * already had, so no new option name is minted and the existing value merges
 * over the defaults for free.
 *
 * @since 2.0.0
 */
class WP_PostRatings_Options {

	/**
	 * Option holding the plugin settings.
	 */
	const OPTION = 'postratings_options';

	/**
	 * Bumped when the consolidated shape changes, so stored values migrate once.
	 *
	 * Lives in its own row rather than inside the settings: it is read to decide
	 * whether the settings need migrating, so it cannot live inside the thing
	 * being migrated.
	 */
	const VERSION_OPTION = 'postratings_options_version';

	/**
	 * Current settings shape.
	 */
	const VERSION = 3;

	/**
	 * The rows consolidated into self::OPTION by the 2.0.0 migration.
	 *
	 * Note that self::OPTION is deliberately absent: it is the row being
	 * written, and deleting it here would throw away every setting the
	 * migration has just merged.
	 *
	 * @var array
	 */
	private static $legacy_options = array(
		'postratings_image',
		'postratings_max',
		'postratings_customrating',
		'postratings_allowtorate',
		'postratings_logging_method',
		'postratings_ajax_style',
		'postratings_ratingstext',
		'postratings_ratingsvalue',
		'postratings_template_vote',
		'postratings_template_text',
		'postratings_template_permission',
		'postratings_template_none',
		'postratings_template_highestrated',
		'postratings_template_mostrated',
	);

	/**
	 * Maps a legacy row onto its key in the consolidated array.
	 *
	 * @return array
	 */
	private static function legacy_map() {
		return array(
			'postratings_image'                 => array( 'image' ),
			'postratings_max'                   => array( 'max' ),
			'postratings_customrating'          => array( 'customrating' ),
			'postratings_allowtorate'           => array( 'allowtorate' ),
			'postratings_logging_method'        => array( 'logging_method' ),
			'postratings_ajax_style'            => array( 'ajax_style' ),
			'postratings_ratingstext'           => array( 'ratings', 'text' ),
			'postratings_ratingsvalue'          => array( 'ratings', 'value' ),
			'postratings_template_vote'         => array( 'templates', 'vote' ),
			'postratings_template_text'         => array( 'templates', 'text' ),
			'postratings_template_permission'   => array( 'templates', 'permission' ),
			'postratings_template_none'         => array( 'templates', 'none' ),
			'postratings_template_highestrated' => array( 'templates', 'highestrated' ),
			'postratings_template_mostrated'    => array( 'templates', 'mostrated' ),
		);
	}

	/**
	 * Default settings.
	 *
	 * The template strings are byte-for-byte what the pre-2.0.0 activation
	 * routine wrote, including the double space in the "most rated" template --
	 * changing it would silently alter the markup of every install that never
	 * customised it.
	 *
	 * @return array
	 */
	public static function defaults() {
		$comma   = __( ',', 'wp-postratings' );
		$votes   = __( 'votes', 'wp-postratings' );
		$average = __( 'average', 'wp-postratings' );
		$out_of  = __( 'out of', 'wp-postratings' );
		$rated   = __( 'rated', 'wp-postratings' );

		return array(
			'image'               => 'star',
			'max'                 => 5,
			'customrating'        => 0,
			'allowtorate'         => 2,
			'logging_method'      => 3,
			'ip_header'           => '',
			'richsnippet'         => 1,
			'richsnippet_ratings' => 1,
			'ajax_style'          => array(
				'loading' => 1,
				'fading'  => 1,
			),
			// The colour used to be chosen by picking a whole image set:
			// stars_crystal and stars_dark were the same star in another
			// colour. Collapsing those into CSS would have left the choice
			// only to people who write CSS, so it is a setting instead.
			'colors'              => array(
				'on'  => '#f5a623',
				'off' => '#d4d4d8',
			),
			'ratings'             => array(
				'text'  => array(
					__( '1 Star', 'wp-postratings' ),
					__( '2 Stars', 'wp-postratings' ),
					__( '3 Stars', 'wp-postratings' ),
					__( '4 Stars', 'wp-postratings' ),
					__( '5 Stars', 'wp-postratings' ),
				),
				'value' => array( 1, 2, 3, 4, 5 ),
			),
			'templates'           => array(
				'vote'         => '%RATINGS_IMAGES_VOTE% (<strong>%RATINGS_USERS%</strong> ' . $votes . $comma . ' ' . $average . ': <strong>%RATINGS_AVERAGE%</strong> ' . $out_of . ' %RATINGS_MAX%)<br />%RATINGS_TEXT%',
				'text'         => '%RATINGS_IMAGES% (<em><strong>%RATINGS_USERS%</strong> ' . $votes . $comma . ' ' . $average . ': <strong>%RATINGS_AVERAGE%</strong> ' . $out_of . ' %RATINGS_MAX%' . $comma . ' <strong>' . $rated . '</strong></em>)',
				'permission'   => '%RATINGS_IMAGES% (<em><strong>%RATINGS_USERS%</strong> ' . $votes . $comma . ' ' . $average . ': <strong>%RATINGS_AVERAGE%</strong> ' . $out_of . ' %RATINGS_MAX%</em>)<br /><em>' . __( 'You need to be a registered member to rate this.', 'wp-postratings' ) . '</em>',
				'none'         => '%RATINGS_IMAGES_VOTE% (' . __( 'No Ratings Yet', 'wp-postratings' ) . ')<br />%RATINGS_TEXT%',
				'highestrated' => '<li><a href="%POST_URL%" title="%POST_TITLE%">%POST_TITLE%</a> %RATINGS_IMAGES% (%RATINGS_AVERAGE% ' . $out_of . ' %RATINGS_MAX%)</li>',
				'mostrated'    => '<li><a href="%POST_URL%"  title="%POST_TITLE%">%POST_TITLE%</a> - %RATINGS_USERS% ' . $votes . '</li>',
			),
		);
	}

	/**
	 * Get the settings, or a single top level setting.
	 *
	 * Merged over the defaults so a missing key never has to be guarded at the
	 * call site.
	 *
	 * @param string|null $key Setting to return, or null for all of them.
	 *
	 * @return mixed
	 */
	public static function get( $key = null ) {
		$stored = get_option( self::OPTION, array() );
		$data   = self::merge( self::defaults(), is_array( $stored ) ? $stored : array() );

		if ( null === $key ) {
			return $data;
		}

		return isset( $data[ $key ] ) ? $data[ $key ] : null;
	}

	/**
	 * Get a nested setting, e.g. get_nested( 'templates', 'vote' ).
	 *
	 * @param string $key    Top level key.
	 * @param string $subkey Nested key.
	 *
	 * @return mixed
	 */
	public static function get_nested( $key, $subkey ) {
		$value = self::get( $key );

		return is_array( $value ) && isset( $value[ $subkey ] ) ? $value[ $subkey ] : null;
	}

	/**
	 * Get one of the rating templates.
	 *
	 * @param string $name Template name.
	 *
	 * @return string
	 */
	public static function template( $name ) {
		$template = self::get_nested( 'templates', $name );

		return is_string( $template ) ? $template : '';
	}

	/**
	 * Store the settings.
	 *
	 * @param array $options Settings to store.
	 *
	 * @return void
	 */
	public static function update( array $options ) {
		update_option( self::OPTION, $options );
	}

	/**
	 * Merge stored settings over defaults, one level into the nested groups.
	 *
	 * Plain array_merge() would let a stored 'templates' array missing a key
	 * shadow the whole default group; array_merge_recursive() would concatenate
	 * the rating text lists instead of replacing them.
	 *
	 * @param array $defaults Defaults.
	 * @param array $stored   Stored settings.
	 *
	 * @return array
	 */
	private static function merge( array $defaults, array $stored ) {
		$merged = array_merge( $defaults, $stored );

		foreach ( array( 'ajax_style', 'colors', 'ratings', 'templates' ) as $group ) {
			if ( isset( $stored[ $group ] ) && is_array( $stored[ $group ] ) ) {
				$merged[ $group ] = array_merge( $defaults[ $group ], $stored[ $group ] );
			} else {
				$merged[ $group ] = $defaults[ $group ];
			}
		}

		return $merged;
	}

	/**
	 * Sanitize a set of settings.
	 *
	 * Registered as the sanitize_callback for the setting, so the Settings API
	 * runs it on every save. Templates are echoed verbatim by the template tags,
	 * so they are sanitized on the way in rather than on the way out.
	 *
	 * @param mixed $options Submitted settings.
	 *
	 * @return array
	 */
	public static function sanitize( $options ) {
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$defaults = self::defaults();
		$current  = self::get();

		// The settings screen is split across two tabs that post disjoint sets
		// of fields, so anything absent from this submission keeps its stored
		// value rather than reverting to the default.
		$clean = $current;

		if ( isset( $options['image'] ) ) {
			// The allow list is the shape registry the settings picker also reads
			// from, so the screen cannot offer a shape the sanitizer rejects.
			// A pre-2.0.0 image set name is accepted and mapped, so an install
			// that has not migrated yet still saves correctly.
			$image = WP_PostRatings_Template::resolve_shape_strict( $options['image'] );

			$clean['image'] = '' !== $image ? $image : $current['image'];
		}

		if ( isset( $options['customrating'] ) ) {
			$clean['customrating'] = empty( $options['customrating'] ) ? 0 : 1;
		}

		if ( isset( $options['max'] ) ) {
			$clean['max'] = max( 1, (int) $options['max'] );
		}

		if ( isset( $options['allowtorate'] ) ) {
			$allowtorate          = (int) $options['allowtorate'];
			$clean['allowtorate'] = in_array( $allowtorate, array( 0, 1, 2, 3 ), true ) ? $allowtorate : $defaults['allowtorate'];
		}

		if ( isset( $options['logging_method'] ) ) {
			$logging_method          = (int) $options['logging_method'];
			$clean['logging_method'] = in_array( $logging_method, array( 0, 1, 2, 3, 4 ), true ) ? $logging_method : $defaults['logging_method'];
		}

		if ( isset( $options['ip_header'] ) ) {
			// A header name, not a value: anything outside the shape PHP uses
			// for $_SERVER keys cannot match and is dropped.
			$ip_header          = strtoupper( sanitize_text_field( trim( (string) $options['ip_header'] ) ) );
			$clean['ip_header'] = preg_match( '/^[A-Z0-9_]*$/', $ip_header ) ? $ip_header : '';
		}

		foreach ( array( 'richsnippet', 'richsnippet_ratings' ) as $key ) {
			if ( isset( $options[ $key ] ) ) {
				$clean[ $key ] = empty( $options[ $key ] ) ? 0 : 1;
			}
		}

		if ( isset( $options['ajax_style'] ) && is_array( $options['ajax_style'] ) ) {
			foreach ( array( 'loading', 'fading' ) as $key ) {
				if ( isset( $options['ajax_style'][ $key ] ) ) {
					$clean['ajax_style'][ $key ] = empty( $options['ajax_style'][ $key ] ) ? 0 : 1;
				}
			}
		}

		if ( isset( $options['colors'] ) && is_array( $options['colors'] ) ) {
			foreach ( array( 'on', 'off' ) as $key ) {
				if ( ! isset( $options['colors'][ $key ] ) ) {
					continue;
				}

				// sanitize_hex_color() returns null for anything that is not a
				// hex colour, which would blank the property and render the
				// shapes invisible; the stored value stands instead.
				$color = sanitize_hex_color( trim( (string) $options['colors'][ $key ] ) );

				if ( null !== $color && '' !== $color ) {
					$clean['colors'][ $key ] = $color;
				}
			}
		}

		if ( isset( $options['ratings'] ) && is_array( $options['ratings'] ) ) {
			if ( isset( $options['ratings']['text'] ) && is_array( $options['ratings']['text'] ) ) {
				$clean['ratings']['text'] = array_values(
					array_map(
						static function ( $text ) {
							return wp_kses_post( trim( (string) $text ) );
						},
						$options['ratings']['text']
					)
				);
			}

			if ( isset( $options['ratings']['value'] ) && is_array( $options['ratings']['value'] ) ) {
				$clean['ratings']['value'] = array_values( array_map( 'intval', $options['ratings']['value'] ) );
			}
		}

		if ( isset( $options['templates'] ) && is_array( $options['templates'] ) ) {
			foreach ( $defaults['templates'] as $name => $default ) {
				if ( isset( $options['templates'][ $name ] ) && ! is_array( $options['templates'][ $name ] ) ) {
					$clean['templates'][ $name ] = wp_kses_post( trim( (string) $options['templates'][ $name ] ) );
				}
			}
		}

		return $clean;
	}

	/**
	 * Consolidate the pre-2.0.0 option rows into one.
	 *
	 * Gated on the stored version rather than on "do the old keys exist": an
	 * install that has already migrated has no old keys, and treating that as a
	 * fresh install would write defaults straight over the merged row.
	 *
	 * Idempotent, and driven from admin_init as well as activation, because
	 * activation does not fire on plugin update.
	 *
	 * @return void
	 */
	public static function maybe_migrate() {
		if ( (int) get_option( self::VERSION_OPTION, 0 ) >= self::VERSION ) {
			return;
		}

		$stored = get_option( self::OPTION, array() );
		$merged = is_array( $stored ) ? $stored : array();

		foreach ( self::legacy_map() as $legacy_name => $path ) {
			$value = get_option( $legacy_name, null );

			if ( null === $value ) {
				continue;
			}

			// Templates were stored slashed and stripslashes()'d on every read.
			// Normalising once here means the new code can read them straight.
			if ( 'templates' === $path[0] ) {
				$value = stripslashes( (string) $value );
			}

			if ( 'ratings' === $path[0] && is_array( $value ) ) {
				$value = array_map(
					static function ( $item ) {
						return is_string( $item ) ? stripslashes( $item ) : $item;
					},
					$value
				);
			}

			if ( 1 === count( $path ) ) {
				$merged[ $path[0] ] = $value;
				continue;
			}

			if ( ! isset( $merged[ $path[0] ] ) || ! is_array( $merged[ $path[0] ] ) ) {
				$merged[ $path[0] ] = array();
			}

			$merged[ $path[0] ][ $path[1] ] = $value;
		}

		// The 16 image folders became 9 SVG shapes in 2.0.0, and the colour and
		// finish variants collapsed into CSS custom properties: stars,
		// stars_crystal, stars_dark, stars_png and stars_flat_png were all one
		// star. Anything unrecognised -- a folder the site added itself -- lands
		// on stars rather than rendering nothing.
		if ( isset( $merged['image'] ) ) {
			$merged['image'] = WP_PostRatings_Template::resolve_shape( $merged['image'] );
		}

		self::update( self::merge( self::defaults(), $merged ) );

		foreach ( self::$legacy_options as $legacy_name ) {
			delete_option( $legacy_name );
		}

		update_option( self::VERSION_OPTION, self::VERSION );
	}

	/**
	 * Every option row the plugin owns, for uninstall.
	 *
	 * @return array
	 */
	public static function all_option_names() {
		return array_merge(
			self::$legacy_options,
			array(
				self::OPTION,
				self::VERSION_OPTION,
				'postratings_db_version',
				'widget_ratings',
				'widget_ratings-widget',
				'widget_ratings_highest_rated',
				'widget_ratings_most_rated',
			)
		);
	}
}
