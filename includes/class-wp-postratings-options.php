<?php
/**
 * WP-PostRatings class-wp-postratings-options.php
 *
 * @package WP-PostRatings
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads, writes, sanitizes and migrates the plugin's settings.
 *
 * Before 2.0.0 the plugin owned seventeen autoloaded rows: fifteen of its own,
 * six of those templates, plus the two unprefixed rows it shared with WP-Stats.
 * They collapse into two -- wp_postratings_options for everything a site owner
 * can change, and wp_postratings_version for the two upgrade markers.
 *
 * The markers live in their own row rather than inside the settings because the
 * settings form never posts them: anything kept in the settings array has to be
 * rescued from the stored value on every save, and forgetting to do that is how
 * an upgrade marker gets silently wiped the first time somebody opens the
 * screen. With two rows the settings screen writes one and the upgrade routine
 * writes the other, and neither can corrupt the other.
 *
 * @since 2.0.0
 */
class WP_PostRatings_Options {

	/**
	 * Option holding the plugin settings.
	 */
	const OPTION = 'wp_postratings_options';

	/**
	 * Option holding the 'plugin' and 'db' upgrade markers, and nothing else.
	 */
	const VERSION = 'wp_postratings_version';

	/**
	 * The row this plugin's settings lived in before they were renamed.
	 *
	 * @var string
	 */
	private static $renamed_from = 'postratings_options';

	/**
	 * The rows consolidated into self::OPTION by the 2.0.0 migration.
	 *
	 * @var array
	 */
	private static $legacy_options = array(
		'postratings_options',
		'postratings_db_version',
		'postratings_options_version',
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
	 * The unprefixed rows this plugin shared with WP-Stats and five others.
	 *
	 * They were never any one plugin's to own: whichever of the six saved the
	 * WP-Stats screen last wrote the whole row, and whichever was uninstalled
	 * first took the other five's settings with it. Each plugin keeps its own
	 * copy now, so the migration folds these in and deletes them -- and, unlike
	 * the rows above, they are deliberately absent from all_option_names(),
	 * because uninstalling one plugin must not clear a setting that a sibling
	 * which has not upgraded yet is still reading.
	 *
	 * @var array
	 */
	private static $shared_options = array(
		'stats_display'   => 'stats_display',
		'stats_mostlimit' => 'stats_most_limit',
	);

	/**
	 * Maps a legacy row onto its key in the consolidated array.
	 *
	 * @return array
	 */
	private static function legacy_map() {
		return array(
			'postratings_image'                 => array( 'shape' ),
			'postratings_max'                   => array( 'max' ),
			'postratings_customrating'          => array( 'customrating' ),
			'postratings_allowtorate'           => array( 'allowtorate' ),
			'postratings_logging_method'        => array( 'check_method' ),
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
	 * The colour a rated glyph uses unless a rating overrides it.
	 *
	 * Matches the fallback in the stylesheet's var(), so the two cannot drift.
	 *
	 * @var string
	 */
	const COLOR_RATED = '#f5a623';

	/**
	 * The colour an up vote uses unless the rating overrides it.
	 *
	 * Matches the stylesheet's own fallback, as COLOR_RATED does.
	 *
	 * @var string
	 */
	const COLOR_UP = '#2e9e4f';

	/**
	 * The colour a down vote uses unless the rating overrides it.
	 *
	 * @var string
	 */
	const COLOR_DOWN = '#e5484d';

	/**
	 * The colour an unrated glyph uses unless a rating overrides it.
	 *
	 * @var string
	 */
	const COLOR_UNRATED = '#d4d4d8';

	/**
	 * The schema.org types Google will show a rating for.
	 *
	 * Not an arbitrary list: these are the types Google documents as eligible
	 * for a review snippet. Article, BlogPosting and NewsArticle are absent
	 * because Google does not support them, which is why the old output --
	 * schema.org/Article carrying an aggregateRating -- produced no rich result
	 * on any post, ever.
	 *
	 * Structured data has to describe what the page actually is. Marking a blog
	 * post as a Product to collect stars is what Google calls spammy structured
	 * markup, and it is a manual action rather than a ranking nudge, which is
	 * why this is off unless a site deliberately turns it on.
	 *
	 * @return array Label keyed by schema.org type.
	 */
	public static function schema_types() {
		return array(
			'Book'                => __( 'Book', 'wp-postratings' ),
			'Course'              => __( 'Course', 'wp-postratings' ),
			'Event'               => __( 'Event', 'wp-postratings' ),
			'Game'                => __( 'Game', 'wp-postratings' ),
			'HowTo'               => __( 'How-to', 'wp-postratings' ),
			'LocalBusiness'       => __( 'Local business', 'wp-postratings' ),
			'Movie'               => __( 'Movie', 'wp-postratings' ),
			'Organization'        => __( 'Organization', 'wp-postratings' ),
			'Product'             => __( 'Product', 'wp-postratings' ),
			'Recipe'              => __( 'Recipe', 'wp-postratings' ),
			'SoftwareApplication' => __( 'Software application', 'wp-postratings' ),
		);
	}

	/**
	 * Everything a rating type starts with.
	 *
	 * One definition, used when a type is chosen and when the table is rebuilt,
	 * so the screen and the saved settings cannot start from different places.
	 *
	 * @param string $shape Shape name, already resolved.
	 *
	 * @return array The 'max' and the whole 'ratings' array.
	 */
	public static function defaults_for_type( $shape ) {
		if ( WP_PostRatings_Shapes::is_updown( $shape ) ) {
			return array(
				'max'     => 2,
				'ratings' => array(
					'text'      => array( __( 'Vote Down', 'wp-postratings' ), __( 'Vote Up', 'wp-postratings' ) ),
					// Signed: a direction, not a position, so the average of a
					// post's votes runs from -1 to +1 with zero in the middle.
					'value'     => array( -1, 1 ),
					// Empty, so the built-in green and red apply.
					'color'     => array( '', '' ),
					'color_off' => array( '', '' ),
				),
			);
		}

		$defaults = self::defaults();

		return array(
			'max'     => $defaults['max'],
			'ratings' => $defaults['ratings'],
		);
	}

	/**
	 * The colour a step uses, stored or built in.
	 *
	 * One place answers this, because the settings screen and the renderer both
	 * need it and they were disagreeing: the screen showed an up/down pair the
	 * green and red it defaults to, while the preview beside it read the stored
	 * value, found nothing, and fell back to the stylesheet's orange. A default
	 * that only exists in the swatch is not a default.
	 *
	 * @param int    $step  Position on the scale, from 1.
	 * @param string $key   Either 'color' or 'color_off'.
	 * @param string $shape Shape name, already resolved.
	 *
	 * @return string A hex colour, or '' to leave the stylesheet's own to it.
	 */
	public static function rating_color( $step, $key, $shape = '' ) {
		$colors = (array) ( self::get( 'ratings' )[ $key ] ?? array() );
		$stored = isset( $colors[ $step - 1 ] ) ? (string) $colors[ $step - 1 ] : '';

		if ( '' !== $stored ) {
			return $stored;
		}

		return self::builtin_color( $step, $key, $shape );
	}

	/**
	 * Whether a submitted rating table is the right length for a rating type.
	 *
	 * The length is the only thing that can be checked: a two row table is an
	 * up/down pair and nothing else, and a table of three to ten rows is a
	 * scale. What the rows say is the site's business.
	 *
	 * @param array  $options Raw submitted options.
	 * @param string $shape   Shape the submission is for, already resolved.
	 *
	 * @return bool
	 */
	private static function table_fits_type( $options, $shape ) {
		if ( ! isset( $options['ratings']['text'] ) || ! is_array( $options['ratings']['text'] ) ) {
			return false;
		}

		$rows = count( $options['ratings']['text'] );

		if ( WP_PostRatings_Shapes::is_updown( $shape ) ) {
			return 2 === $rows;
		}

		return $rows >= self::MIN_SCALE && $rows <= self::max_scale();
	}

	/**
	 * The colour a step of a shape has before anybody chooses one.
	 *
	 * Separate from rating_color() because there is one caller that must not
	 * consult the stored colours at all: the shape picker, which compares shapes
	 * against each other and so has to draw every one of them the same way. It
	 * asked rating_color() once, and a site with an orange five star scale saved
	 * got an orange pair of thumbs -- the stored colour for steps one and two,
	 * which belong to a rating that is not the one being previewed.
	 *
	 * @param int    $step  Position on the scale, counting from one.
	 * @param string $key   Either 'color' or 'color_off'.
	 * @param string $shape Shape being drawn.
	 *
	 * @return string A colour, or '' to leave it to the stylesheet.
	 */
	public static function builtin_color( $step, $key, $shape = '' ) {
		// Only the rated colour has a built-in worth writing out, and only for an
		// up/down pair: two opposing actions conventionally read green and red,
		// where no convention says the third star differs from the fourth. The
		// unrated colour and a scale's rated colour are left to the stylesheet.
		if ( 'color' !== $key || ! WP_PostRatings_Shapes::is_updown( $shape ) ) {
			return '';
		}

		return 2 === (int) $step ? self::COLOR_UP : self::COLOR_DOWN;
	}

	/**
	 * The smallest scale a rating may use.
	 *
	 * Three. One or two points is not a scale -- two opposing actions is an
	 * up/down set, which is a different rating type with its own shapes and its
	 * own renderer, and offering it here as "a scale of 2" is the misreading
	 * that had a two step rating drawn as a strip of identical thumbs.
	 *
	 * @var int
	 */
	const MIN_SCALE = 3;

	/**
	 * The largest scale a rating may use.
	 *
	 * Ten by default. Every point on the scale is a glyph the visitor has to
	 * aim at and a column in the rating text table, so a scale of fifty is a
	 * misconfiguration rather than a preference -- and it used to be accepted
	 * silently, because the only bound was a floor of one.
	 *
	 * @return int
	 */
	public static function max_scale() {
		/**
		 * Filters the largest scale a rating may use.
		 *
		 * @since 2.0.0
		 *
		 * @param int $max Maximum number of points on the scale.
		 */
		return max( 1, (int) apply_filters( 'wp_postratings_max_scale', 10 ) );
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
			'shape'            => 'star',
			'max'              => 5,
			'customrating'     => 0,
			'allowtorate'      => 2,
			'check_method'     => 3,
			'ip_header'        => '',
			'schema_type'      => '',
			// The plugin's half of the WP-Stats contract. Its own setting now,
			// not a slice of a row six plugins wrote to at once.
			'stats_display'    => 1,
			'stats_most_limit' => 10,
			'ratings'          => array(
				'text'      => array(
					__( '1 Star', 'wp-postratings' ),
					__( '2 Stars', 'wp-postratings' ),
					__( '3 Stars', 'wp-postratings' ),
					__( '4 Stars', 'wp-postratings' ),
					__( '5 Stars', 'wp-postratings' ),
				),
				'value'     => array( 1, 2, 3, 4, 5 ),
				// One per step, empty meaning "use the rated colour above". A
				// two step scale is the case this exists for: an up vote and a
				// down vote are opposite actions and want opposite colours,
				// which one shared colour cannot express.
				'color'     => array( '', '', '', '', '' ),
				'color_off' => array( '', '', '', '', '' ),
			),
			'templates'        => array(
				'vote'         => '%RATINGS_IMAGES_VOTE% (<strong>%RATINGS_USERS%</strong> ' . $votes . $comma . ' ' . $average . ': <strong>%RATINGS_AVERAGE%</strong> ' . $out_of . ' %RATINGS_MAX%)<br />%RATINGS_TEXT%',
				'text'         => '%RATINGS_IMAGES% (<em><strong>%RATINGS_USERS%</strong> ' . $votes . $comma . ' ' . $average . ': <strong>%RATINGS_AVERAGE%</strong> ' . $out_of . ' %RATINGS_MAX%' . $comma . ' <strong>' . $rated . '</strong></em>)',
				'permission'   => '%RATINGS_IMAGES% (<em><strong>%RATINGS_USERS%</strong> ' . $votes . $comma . ' ' . $average . ': <strong>%RATINGS_AVERAGE%</strong> ' . $out_of . ' %RATINGS_MAX%</em>)<br /><em>' . __( 'You need to be a registered member to rate this.', 'wp-postratings' ) . '</em>',
				'none'         => '%RATINGS_IMAGES_VOTE% (' . __( 'No Ratings Yet', 'wp-postratings' ) . ')<br />%RATINGS_TEXT%',
				'highestrated' => '<li><a href="%POST_URL%" title="%POST_TITLE%">%POST_TITLE%</a> %RATINGS_IMAGES% (%RATINGS_AVERAGE% ' . $out_of . ' %RATINGS_MAX%)</li>',
				'mostrated'    => '<li><a href="%POST_URL%" title="%POST_TITLE%">%POST_TITLE%</a> - %RATINGS_USERS% ' . $votes . '</li>',
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
	 * `update_option()` declines to write a value equal to the one `get_option()`
	 * would return, and `register_setting()` is passed a `default`, which installs
	 * a `default_option_wp_postratings_options` filter answering with the shipped
	 * defaults for a row that does not exist. Core's `add_option()` fallback sits
	 * immediately below that comparison and is unreachable once the two compare
	 * equal. So the migration -- the only caller that reaches this with a value
	 * equal to the defaults, which is the commonest install there is -- wrote
	 * nothing at all, and the markers were stamped complete either way, so the
	 * upgrade could never run again.
	 *
	 * Held off by hook order alone: `maybe_migrate()` runs on `init` and the
	 * filter is registered on `admin_init`. One `show_in_rest`, one registration
	 * moved earlier, or any third-party `default_option_*` filter reaches it,
	 * silently, after the legacy rows have already been deleted.
	 *
	 * And hidden by an accident. The sanitiser runs kses over the templates, which
	 * collapsed a doubled space the "most rated" default carried before 2.0.0, so
	 * the value written differed from the defaults by that one character and the
	 * comparison found a difference. The guarantee rested on a typo in a template.
	 *
	 * Passing an explicit default to `get_option()` defeats the registered one --
	 * `filter_default_option()` returns early when a default was passed -- which is
	 * what lets an absent row be told apart from a defaulted one and added
	 * outright. `add_option()` runs the sanitize callback exactly as
	 * `update_option()` does, so nothing else about the stored value changes.
	 *
	 * @param array $options Settings to store.
	 *
	 * @return void
	 */
	public static function update( array $options ) {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $options );

			return;
		}

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

		foreach ( array( 'ratings', 'templates' ) as $group ) {
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
	 * @param mixed $input Submitted settings.
	 *
	 * @return array
	 */
	public static function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$defaults = self::defaults();
		$current  = self::get();

		// The settings screen is split across two tabs that post disjoint sets
		// of fields, so anything absent from this submission keeps its stored
		// value rather than reverting to the default.
		$clean = $current;

		if ( isset( $input['shape'] ) ) {
			// The allow list is the shape registry the settings picker also reads
			// from, so the screen cannot offer a shape the sanitizer rejects.
			// A pre-2.0.0 image set name is accepted and mapped, so an install
			// that has not migrated yet still saves correctly.
			$shape = WP_PostRatings_Template::resolve_shape_strict( $input['shape'] );

			$clean['shape'] = '' !== $shape ? $shape : $current['shape'];
		}

		/*
		 * Derived from the shape, never posted.
		 *
		 * It records whether the rating is an up/down pair, which the shape
		 * already says. It used to arrive as a hidden field, and when the field
		 * it lived beside was removed the value silently stopped being submitted
		 * -- so the screen went on thinking a thumbs set was a scale.
		 */
		$clean['customrating'] = WP_PostRatings_Shapes::is_updown(
			WP_PostRatings_Template::resolve_shape(
				isset( $clean['shape'] ) ? $clean['shape'] : (string) self::get( 'shape' )
			)
		) ? 1 : 0;

		if ( isset( $input['schema_type'] ) ) {
			$type                 = (string) $input['schema_type'];
			$clean['schema_type'] = array_key_exists( $type, self::schema_types() ) ? $type : '';
		}

		/*
		 * Changing the rating type starts the new one from its own defaults.
		 *
		 * Not a merge, and not a partial clear: everything the table holds
		 * belongs to one type or the other. "1 Star" means nothing on a thumbs
		 * set, an up/down vote is signed where a scale is not, and green and red
		 * are the built-ins for one while orange is the built-in for the other.
		 * Carrying any of it across is what left a scale's first row at -1 after
		 * switching away from thumbs, and left an up/down pair wearing the
		 * colours its stars had.
		 *
		 * So both directions reset: to Vote Down and Vote Up at -1 and +1 with
		 * green and red, or to a five point scale with orange.
		 *
		 * But only when the table posted with it still belongs to the old type.
		 * The screen rebuilds the table the moment the shape changes, so a normal
		 * submit arrives carrying rows that already belong to the new type -- and
		 * resetting those threw away the first edit made after every switch,
		 * which is where the colours a site had just chosen went. A table the
		 * right length for the type it arrived with is the new type's table, and
		 * is sanitized like any other. One that is not came from a form that
		 * never saw the change: no script, or a page opened beforehand.
		 */
		$was = WP_PostRatings_Template::resolve_shape( (string) self::get( 'shape' ) );
		$now = isset( $clean['shape'] ) ? $clean['shape'] : $was;

		if ( WP_PostRatings_Shapes::is_updown( $was ) !== WP_PostRatings_Shapes::is_updown( $now )
			&& ! self::table_fits_type( $input, $now ) ) {
			$fresh = self::defaults_for_type( $now );

			$clean['ratings'] = $fresh['ratings'];
			$clean['max']     = $fresh['max'];

			return $clean;
		}

		/*
		 * The scale is however many steps the table has, not a number posted
		 * beside it. The table is the scale, so a Max field was a second place
		 * holding one fact -- and the one that lost, since raising it left the
		 * text and value arrays shorter than the loop that reads them.
		 *
		 * An up/down set is always two: a pair of opposing actions, with nothing
		 * to choose. A scale starts at three, because one or two points is not a
		 * scale.
		 */
		$shape = isset( $clean['shape'] ) ? (string) $clean['shape'] : (string) self::get( 'shape' );

		if ( WP_PostRatings_Shapes::is_updown( WP_PostRatings_Template::resolve_shape( $shape ) ) ) {
			$clean['max'] = 2;
		} elseif ( isset( $input['ratings']['text'] ) && is_array( $input['ratings']['text'] ) ) {
			$clean['max'] = max( self::MIN_SCALE, min( self::max_scale(), count( $input['ratings']['text'] ) ) );
		} elseif ( isset( $input['max'] ) ) {
			$clean['max'] = max( self::MIN_SCALE, min( self::max_scale(), (int) $input['max'] ) );
		}

		if ( isset( $input['allowtorate'] ) ) {
			$allowtorate          = (int) $input['allowtorate'];
			$clean['allowtorate'] = in_array( $allowtorate, array( 0, 1, 2, 3 ), true ) ? $allowtorate : $defaults['allowtorate'];
		}

		if ( isset( $input['check_method'] ) ) {
			$check_method          = (int) $input['check_method'];
			$clean['check_method'] = in_array( $check_method, array( 0, 1, 2, 3, 4 ), true ) ? $check_method : $defaults['check_method'];
		}

		if ( isset( $input['ip_header'] ) ) {
			// A header name, not a value: anything outside the shape PHP uses
			// for $_SERVER keys cannot match and is dropped.
			$ip_header          = strtoupper( sanitize_text_field( trim( (string) $input['ip_header'] ) ) );
			$clean['ip_header'] = preg_match( '/^[A-Z0-9_]*$/', $ip_header ) ? $ip_header : '';
		}

		foreach ( array( 'stats_display' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$clean[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
			}
		}

		if ( isset( $input['stats_most_limit'] ) ) {
			$clean['stats_most_limit'] = max( 1, (int) $input['stats_most_limit'] );
		}

		if ( isset( $input['ratings'] ) && is_array( $input['ratings'] ) ) {
			if ( isset( $input['ratings']['text'] ) && is_array( $input['ratings']['text'] ) ) {
				$clean['ratings']['text'] = array_values(
					array_map(
						static function ( $text ) {
							return wp_kses_post( trim( (string) $text ) );
						},
						$input['ratings']['text']
					)
				);
			}

			if ( isset( $input['ratings']['value'] ) && is_array( $input['ratings']['value'] ) ) {
				$clean['ratings']['value'] = array_values( array_map( 'intval', $input['ratings']['value'] ) );
			}

			/*
			 * Both swatches are always enabled and pre-filled with the site-wide
			 * colour, so an untouched row saves that colour rather than staying
			 * empty. Empty is still accepted -- a row that has never been
			 * rendered has no value -- and still means "use the site-wide one".
			 */
			foreach ( array( 'color', 'color_off' ) as $key ) {
				if ( ! isset( $input['ratings'][ $key ] ) || ! is_array( $input['ratings'][ $key ] ) ) {
					continue;
				}

				$clean['ratings'][ $key ] = array_values(
					array_map(
						static function ( $color ) {
							// sanitize_hex_color() returns null for anything that
							// is not a hex colour, and '' is legitimate here: it
							// means "use the site-wide colour".
							$color = sanitize_hex_color( trim( (string) $color ) );

							return null === $color ? '' : $color;
						},
						$input['ratings'][ $key ]
					)
				);
			}
		}

		if ( isset( $input['templates'] ) && is_array( $input['templates'] ) ) {
			foreach ( $defaults['templates'] as $name => $default ) {
				if ( isset( $input['templates'][ $name ] ) && ! is_array( $input['templates'][ $name ] ) ) {
					$clean['templates'][ $name ] = wp_kses_post( trim( (string) $input['templates'][ $name ] ) );
				}
			}
		}

		return $clean;
	}

	/**
	 * The two upgrade markers, normalised.
	 *
	 * Always exactly 'plugin' and 'db', whatever is actually in the row, so a
	 * caller never has to guard either key.
	 *
	 * @return array
	 */
	public static function markers() {
		$stored = get_option( self::VERSION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		return array(
			'plugin' => isset( $stored['plugin'] ) ? (string) $stored['plugin'] : '',
			'db'     => isset( $stored['db'] ) ? (string) $stored['db'] : '',
		);
	}

	/**
	 * Record that this version's upgrade has finished.
	 *
	 * One write, both markers, at the end of the upgrade routine: a half-run
	 * upgrade never gets to record itself as complete.
	 *
	 * @return void
	 */
	public static function update_markers() {
		update_option(
			self::VERSION,
			array(
				'plugin' => WP_POSTRATINGS_VERSION,
				'db'     => WP_POSTRATINGS_DB_VERSION,
			)
		);
	}

	/**
	 * Fold every pre-2.0.0 option row into wp_postratings_options.
	 *
	 * Seventeen rows go in: postratings_options itself, the fourteen loose
	 * settings rows beside it, and the two unprefixed rows this plugin shared
	 * with WP-Stats. All seventeen are deleted afterwards, including the old
	 * postratings_options, because the row has been renamed and leaving it
	 * behind would mean a later downgrade silently resurrected stale settings.
	 *
	 * Idempotent: after the first run there are no legacy rows left to read, and
	 * re-merging the stored value over the defaults changes nothing.
	 *
	 * @return void
	 */
	public static function maybe_migrate() {
		$stored = get_option( self::OPTION, null );

		// The old row is the starting point when the new one does not exist yet,
		// so an install upgrading from 1.91.3 keeps every setting it had.
		if ( null === $stored ) {
			$stored = get_option( self::$renamed_from, array() );
		}

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

		/*
		 * The two rich snippet toggles are retired.
		 *
		 * They had become one decision -- the aggregate rating is the only
		 * reason to emit that markup -- and the type they declared,
		 * schema.org/Article, is one Google shows no rating for. So an install
		 * that had them on was getting nothing, and lands on "No" rather than
		 * being handed a type it never chose.
		 */
		unset( $merged['richsnippet'], $merged['richsnippet_ratings'] );

		/*
		 * The two "while a vote is in flight" settings are retired.
		 *
		 * The loading text is gone entirely and the rating always dims, because
		 * dimming it is aria-busy, which tells assistive technology the region
		 * is updating as well as greying it -- that is not a preference. A site
		 * that wants it back styles .wp-postratings[aria-busy="true"].
		 */
		unset( $merged['ajax_style'] );

		/*
		 * The one site-wide pair of colours becomes a pair per rating.
		 *
		 * A site that chose its own colours keeps them: they are copied onto
		 * every step before the row is dropped, so nothing looks different
		 * afterwards. Copying rather than leaving the steps empty is the point --
		 * empty means "use the built-in colour", which would silently discard the
		 * choice.
		 */
		if ( isset( $merged['colors'] ) && is_array( $merged['colors'] ) ) {
			$steps = max( 1, (int) ( $merged['max'] ?? 5 ) );

			foreach ( array(
				'on'  => 'color',
				'off' => 'color_off',
			) as $from => $to ) {
				$color = isset( $merged['colors'][ $from ] ) ? (string) $merged['colors'][ $from ] : '';

				if ( '' === $color || ! empty( $merged['ratings'][ $to ] ) ) {
					continue;
				}

				$merged['ratings'][ $to ] = array_fill( 0, $steps, $color );
			}

			unset( $merged['colors'] );
		}

		/*
		 * The key is 'shape' now, not 'image'.
		 *
		 * Nothing released ever wrote 'image' -- the released option is
		 * postratings_image, folded in by legacy_map() above -- so this only
		 * catches an install that ran a 2.0.0 beta, where leaving it would drop
		 * the chosen shape back to the default.
		 */
		if ( isset( $merged['image'] ) ) {
			if ( ! isset( $merged['shape'] ) ) {
				$merged['shape'] = $merged['image'];
			}

			unset( $merged['image'] );
		}

		/*
		 * And 'check_method' now, not 'logging_method'.
		 *
		 * Same case as 'image' above: the released row is
		 * postratings_logging_method, which legacy_map() folds straight into the
		 * new key, so this catches only a 2.0.0 beta. The numbers are unchanged
		 * -- what moved is the name, because the setting never chose whether to
		 * log anything. Left behind, the site would silently fall back to
		 * checking by cookie and IP.
		 */
		if ( isset( $merged['logging_method'] ) ) {
			if ( ! isset( $merged['check_method'] ) ) {
				$merged['check_method'] = $merged['logging_method'];
			}

			unset( $merged['logging_method'] );
		}

		// The 16 image folders became 9 SVG shapes in 2.0.0, and the colour and
		// finish variants collapsed into CSS custom properties: stars,
		// stars_crystal, stars_dark, stars_png and stars_flat_png were all one
		// star. Anything unrecognised -- a folder the site added itself -- lands
		// on stars rather than rendering nothing.
		if ( isset( $merged['shape'] ) ) {
			$merged['shape'] = WP_PostRatings_Template::resolve_shape( $merged['shape'] );
		}

		$merged = self::migrate_stats_settings( $merged );

		self::update( self::merge( self::defaults(), $merged ) );

		foreach ( array_merge( self::$legacy_options, array_keys( self::$shared_options ) ) as $legacy_name ) {
			delete_option( $legacy_name );
		}
	}

	/**
	 * Take this plugin's share of the two rows it used to hold jointly.
	 *
	 * The old stats_display row was an array of checkbox keys written by
	 * whichever of the seven contributing plugins saved the WP-Stats screen
	 * last, and this plugin owned four of those keys. Only one question
	 * survives -- does WP-Stats show a ratings section at all -- because
	 * WP-Stats collects whole sections now rather than individual panels.
	 *
	 * An absent row means "on", never "off". All seven plugins fold these two
	 * rows in and each deletes them afterwards, so whichever one the site
	 * updates first takes them away and the other six find nothing left to
	 * read. Treating that as a deliberate opt-out would make the ratings block
	 * disappear from the stats page of any site that happened to update
	 * WP-Stats first, silently and with nothing in any log to explain it. The
	 * worst case the other way round is a block the owner switches off again.
	 *
	 * @param array $merged Settings assembled so far.
	 *
	 * @return array
	 */
	private static function migrate_stats_settings( $merged ) {
		foreach ( self::$shared_options as $legacy_name => $key ) {
			if ( isset( $merged[ $key ] ) ) {
				continue;
			}

			$legacy = get_option( $legacy_name, null );

			if ( 'stats_display' === $key ) {
				// null is "a sibling has already migrated it away", not "off".
				$merged[ $key ] = null === $legacy
					? 1
					: (int) ( is_array( $legacy ) ? ! empty( $legacy['ratings'] ) : (bool) $legacy );

				continue;
			}

			// The limit is only a number, and its default is a sensible one, so
			// an absent row simply leaves the default in place.
			if ( null !== $legacy && '' !== $legacy ) {
				$merged[ $key ] = max( 1, (int) $legacy );
			}
		}

		return $merged;
	}

	/**
	 * Every option row the plugin owns, for uninstall.
	 *
	 * The legacy names are included so uninstalling after an upgrade that never
	 * ran still clears them.
	 *
	 * @return array
	 */
	public static function all_option_names() {
		return array_merge(
			self::$legacy_options,
			array(
				self::OPTION,
				self::VERSION,
				'widget_ratings',
				'widget_ratings-widget',
				'widget_ratings_highest_rated',
				'widget_ratings_most_rated',
			)
		);
	}
}
