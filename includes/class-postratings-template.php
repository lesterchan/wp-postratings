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
	 * Cached list of image folder names.
	 *
	 * @var array|null
	 */
	private static $folders = null;

	/**
	 * URL of one rating image.
	 *
	 * @param string $set  Image set folder name.
	 * @param string $file File name without the extension.
	 *
	 * @return string
	 */
	public static function image_url( $set, $file ) {
		return WP_POSTRATINGS_URL . 'images/' . $set . '/' . $file . '.' . RATINGS_IMG_EXT;
	}

	/**
	 * Whether one rating image exists on disk.
	 *
	 * @param string $set  Image set folder name.
	 * @param string $file File name without the extension.
	 *
	 * @return bool
	 */
	public static function image_exists( $set, $file ) {
		return file_exists( WP_POSTRATINGS_DIR . 'images/' . $set . '/' . $file . '.' . RATINGS_IMG_EXT );
	}

	/**
	 * One <img> tag.
	 *
	 * @param string $set        Image set folder name.
	 * @param string $file       File name without the extension.
	 * @param string $alt        Alt and title text, unescaped.
	 * @param array  $attributes Extra attributes, unescaped.
	 *
	 * @return string
	 */
	private static function image_tag( $set, $file, $alt = '', $attributes = array() ) {
		$html = '<img src="' . esc_url( self::image_url( $set, $file ) ) . '" alt="' . esc_attr( $alt ) . '"';

		if ( '' !== $alt ) {
			$html .= ' title="' . esc_attr( $alt ) . '"';
		}

		foreach ( $attributes as $name => $value ) {
			$html .= ' ' . $name . '="' . esc_attr( $value ) . '"';
		}

		return $html . ' />';
	}

	/**
	 * The decorative image that opens a rating strip, if the set has one.
	 *
	 * @param string $set Image set folder name.
	 *
	 * @return string
	 */
	private static function start_image( $set ) {
		if ( is_rtl() && self::image_exists( $set, 'rating_start-rtl' ) ) {
			return self::image_tag( $set, 'rating_start-rtl', '', array( 'class' => 'post-ratings-image' ) );
		}

		if ( self::image_exists( $set, 'rating_start' ) ) {
			return self::image_tag( $set, 'rating_start', '', array( 'class' => 'post-ratings-image' ) );
		}

		return '';
	}

	/**
	 * The decorative image that closes a rating strip, if the set has one.
	 *
	 * @param string $set Image set folder name.
	 *
	 * @return string
	 */
	private static function end_image( $set ) {
		if ( is_rtl() && self::image_exists( $set, 'rating_end-rtl' ) ) {
			return self::image_tag( $set, 'rating_end-rtl', '', array( 'class' => 'post-ratings-image' ) );
		}

		if ( self::image_exists( $set, 'rating_end' ) ) {
			return self::image_tag( $set, 'rating_end', '', array( 'class' => 'post-ratings-image' ) );
		}

		return '';
	}

	/**
	 * Every image set shipped in images/.
	 *
	 * The settings sanitizer and the radio buttons on the settings screen are
	 * both derived from this, so the screen cannot offer a set the sanitizer
	 * would reject -- which would save, report success and silently revert.
	 *
	 * @return array
	 */
	public static function image_folders() {
		if ( null !== self::$folders ) {
			return self::$folders;
		}

		self::$folders = array();

		$entries = glob( WP_POSTRATINGS_DIR . 'images/*', GLOB_ONLYDIR );

		if ( is_array( $entries ) ) {
			foreach ( $entries as $entry ) {
				self::$folders[] = basename( $entry );
			}
		}

		sort( self::$folders );

		return self::$folders;
	}

	/**
	 * Describe an image set: how many images, whether it is a custom set.
	 *
	 * @param string $folder_name Image set folder name.
	 *
	 * @return array
	 */
	public static function folder_info( $folder_name ) {
		$normal_images = array(
			'rating_over.' . RATINGS_IMG_EXT,
			'rating_on.' . RATINGS_IMG_EXT,
			'rating_half.' . RATINGS_IMG_EXT,
			'rating_off.' . RATINGS_IMG_EXT,
		);

		$rating = array(
			'max'    => 0,
			'custom' => 0,
			'images' => array(),
		);

		$count = 0;
		$path  = WP_POSTRATINGS_DIR . 'images/' . $folder_name;

		if ( is_dir( $path ) ) {
			$handle = opendir( $path );

			if ( false !== $handle ) {
				while ( false !== ( $filename = readdir( $handle ) ) ) {
					if ( '.' === $filename || '..' === $filename || 0 === strpos( $filename, '.' ) ) {
						continue;
					}

					if ( substr( $filename, -8 ) === '-rtl.' . RATINGS_IMG_EXT ) {
						continue;
					}

					if ( in_array( $filename, $normal_images, true ) ) {
						++$count;
					} elseif ( (int) substr( $filename, 7, -7 ) > $rating['max'] ) {
						$rating['max'] = (int) substr( $filename, 7, -7 );
					}

					$rating['images'][] = $filename;
				}

				closedir( $handle );
			}
		}

		if ( count( $normal_images ) !== $count ) {
			$rating['custom'] = 1;
		}

		if ( 0 === $rating['max'] ) {
			$rating['max'] = (int) Postratings_Options::get( 'max' );
		}

		return $rating;
	}

	/**
	 * The read-only rating strip.
	 *
	 * @param int    $ratings_custom Whether the set is a custom one.
	 * @param int    $ratings_max    Number of images in the scale.
	 * @param float  $post_rating    Rating to display.
	 * @param string $ratings_image  Image set folder name.
	 * @param string $image_alt      Alt text.
	 * @param int    $insert_half    Position of the half image, or 0.
	 *
	 * @return string
	 */
	public static function ratings_images( $ratings_custom, $ratings_max, $post_rating, $ratings_image, $image_alt, $insert_half ) {
		$image_alt = apply_filters( 'wp_postratings_ratings_image_alt', $image_alt );
		$html      = self::start_image( $ratings_image );

		for ( $i = 1; $i <= $ratings_max; $i++ ) {
			$prefix = $ratings_custom ? 'rating_' . $i : 'rating';

			if ( $i <= $post_rating ) {
				$html .= self::image_tag( $ratings_image, $prefix . '_on', $image_alt, array( 'class' => 'post-ratings-image' ) );
			} elseif ( $i === $insert_half ) {
				$file = is_rtl() && self::image_exists( $ratings_image, $prefix . '_half-rtl' )
					? $prefix . '_half-rtl'
					: $prefix . '_half';

				$html .= self::image_tag( $ratings_image, $file, $image_alt, array( 'class' => 'post-ratings-image' ) );
			} else {
				$html .= self::image_tag( $ratings_image, $prefix . '_off', $image_alt, array( 'class' => 'post-ratings-image' ) );
			}
		}

		return $html . self::end_image( $ratings_image );
	}

	/**
	 * The clickable rating strip.
	 *
	 * The hover and click behaviour is carried on data-* attributes and handled
	 * by one delegated listener. It used to be inline onmouseover/onclick, which
	 * meant the per-rating text was escaped as esc_js( esc_attr( ... ) ) and
	 * interpolated into a JS string literal inside an HTML attribute -- two
	 * layers deep, and the place this plugin's XSS lived.
	 *
	 * @param int    $post_id        Post being rated.
	 * @param int    $ratings_custom Whether the set is a custom one.
	 * @param int    $ratings_max    Number of images in the scale.
	 * @param float  $post_rating    Current rating.
	 * @param string $ratings_image  Image set folder name.
	 * @param string $image_alt      Fallback alt text.
	 * @param int    $insert_half    Position of the half image, or 0.
	 * @param array  $ratings_texts  Per-rating labels.
	 *
	 * @return string
	 */
	public static function ratings_images_vote( $post_id, $ratings_custom, $ratings_max, $post_rating, $ratings_image, $image_alt, $insert_half, $ratings_texts ) {
		$html = self::start_image( $ratings_image );

		for ( $i = 1; $i <= $ratings_max; $i++ ) {
			$prefix = $ratings_custom ? 'rating_' . $i : 'rating';

			$half_rtl     = is_rtl() && self::image_exists( $ratings_image, $prefix . '_half-rtl' ) ? 1 : 0;
			$ratings_text = isset( $ratings_texts[ $i - 1 ] ) ? (string) $ratings_texts[ $i - 1 ] : '';
			$alt          = apply_filters( 'wp_postratings_ratings_image_alt', $ratings_text );

			if ( $i <= $post_rating ) {
				$file = $prefix . '_on';
			} elseif ( $i === (int) $insert_half ) {
				$file = $half_rtl ? $prefix . '_half-rtl' : $prefix . '_half';
			} else {
				$file = $prefix . '_off';
			}

			$html .= self::image_tag(
				$ratings_image,
				$file,
				$alt,
				array(
					'id'                => 'rating_' . $post_id . '_' . $i,
					'class'             => 'post-ratings-image post-ratings-vote',
					'style'             => 'cursor: pointer; border: 0px;',
					'data-post-id'      => $post_id,
					'data-rating'       => $i,
					'data-rating-text'  => $ratings_text,
					'data-post-rating'  => $post_rating,
					'data-insert-half'  => (int) $insert_half,
					'data-half-rtl'     => $half_rtl,
					'role'              => 'button',
					'tabindex'          => '0',
				)
			);
		}

		return $html . self::end_image( $ratings_image );
	}

	/**
	 * The rating strip shown beside a comment author.
	 *
	 * @param int    $ratings_custom        Whether the set is a custom one.
	 * @param int    $ratings_max           Number of images in the scale.
	 * @param int    $comment_author_rating Rating the author gave.
	 * @param string $ratings_image         Image set folder name.
	 * @param string $image_alt             Alt text.
	 *
	 * @return string
	 */
	public static function ratings_images_comment_author( $ratings_custom, $ratings_max, $comment_author_rating, $ratings_image, $image_alt ) {
		$html = self::start_image( $ratings_image );

		if ( $ratings_custom && 2 === (int) $ratings_max ) {
			$file  = $comment_author_rating > 0 ? 'rating_2_on' : 'rating_1_on';
			$html .= self::image_tag( $ratings_image, $file, $image_alt, array( 'class' => 'post-ratings-image' ) );
		} else {
			for ( $i = 1; $i <= $ratings_max; $i++ ) {
				$prefix = $ratings_custom ? 'rating_' . $i : 'rating';
				$state  = $i <= $comment_author_rating ? '_on' : '_off';

				$html .= self::image_tag( $ratings_image, $prefix . $state, $image_alt, array( 'class' => 'post-ratings-image' ) );
			}
		}

		return $html . self::end_image( $ratings_image );
	}

	/**
	 * Work out where the half image goes for a given average.
	 *
	 * @param float $average     Average rating.
	 * @param float $post_rating Rounded rating.
	 *
	 * @return int
	 */
	private static function half_position( $average, $post_rating ) {
		$average_diff = abs( floor( $average ) - $post_rating );

		if ( $average_diff >= 0.25 && $average_diff <= 0.75 ) {
			return (int) ceil( $average );
		}

		if ( $average_diff > 0.75 ) {
			return (int) ceil( $post_rating );
		}

		return 0;
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

		$insert_half = self::half_position( $post_ratings_average, $post_ratings );

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
			$post_title = Postratings_Template::snippet( $post_title, $max_post_title_chars );
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
