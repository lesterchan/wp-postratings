<?php
/**
 * WP-PostRatings class-wp-postratings-rating.php
 *
 * @package WP-PostRatings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records votes and decides who may cast one.
 *
 * @since 2.0.0
 */
class WP_PostRatings_Rating {

	/**
	 * User agents treated as crawlers rather than voters.
	 *
	 * @var array
	 */
	private static $bots = array(
		'googlebot',
		'google',
		'msnbot',
		'ia_archiver',
		'lycos',
		'jeeves',
		'scooter',
		'fast-webcrawler',
		'slurp@inktomi',
		'turnitinbot',
		'technorati',
		'yahoo',
		'findexa',
		'findlinks',
		'gaisbo',
		'zyborg',
		'surveybot',
		'bloglines',
		'blogsearch',
		'ubsub',
		'syndic8',
		'userland',
		'gigabot',
		'become.com',
	);

	/**
	 * Hook the AJAX endpoints.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_wp_postratings', array( __CLASS__, 'handle_vote' ) );
		add_action( 'wp_ajax_nopriv_wp_postratings', array( __CLASS__, 'handle_vote' ) );
	}

	/**
	 * Whether the current visitor is allowed to rate at all.
	 *
	 * @return bool
	 */
	public static function can_rate() {
		switch ( (int) WP_PostRatings_Options::get( 'allowtorate' ) ) {
			case 0:
				// Guests only.
				return ! is_user_logged_in();
			case 1:
				// Logged-in users only.
				return is_user_logged_in();
			case 3:
				// Users registered on this site of the network.
				return is_user_member_of_blog();
			case 2:
			default:
				return true;
		}
	}

	/**
	 * Whether the visitor has already rated a post.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return bool
	 */
	public static function has_rated( $post_id ) {
		$rated = false;

		switch ( (int) WP_PostRatings_Options::get( 'check_method' ) ) {
			case 0:
				// Do not log.
				$rated = false;
				break;
			case 1:
				$rated = self::rated_by_cookie( $post_id );
				break;
			case 2:
				$rated = (bool) self::rated_by_ip( $post_id );
				break;
			case 3:
				$rated = self::rated_by_cookie( $post_id ) ? true : (bool) self::rated_by_ip( $post_id );
				break;
			case 4:
				$rated = (bool) self::rated_by_username( $post_id );
				break;
		}

		/**
		 * Filters whether the visitor has already rated a post.
		 *
		 * @since 1.80
		 *
		 * @param bool $rated   Whether the logging method says they have.
		 * @param int  $post_id Post being checked.
		 */
		return apply_filters( 'wp_postratings_check_rated', $rated, $post_id );
	}

	/**
	 * Whether a rating cookie is set for a post.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return bool
	 */
	public static function rated_by_cookie( $post_id ) {
		return isset( $_COOKIE[ 'rated_' . $post_id ] );
	}

	/**
	 * Whether the visitor's IP has rated a post.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return int
	 */
	public static function rated_by_ip( $post_id ) {
		global $wpdb;

		// Deliberately uncached: a stale answer here lets the same address
		// rate the same post twice.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->ratings} WHERE rating_postid = %d AND rating_ip = %s",
				$post_id,
				self::get_ip()
			)
		);
	}

	/**
	 * Whether the logged-in user has rated a post.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return int
	 */
	public static function rated_by_username( $post_id ) {
		global $wpdb;

		if ( ! is_user_logged_in() ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT rating_userid FROM {$wpdb->ratings} WHERE rating_postid = %d AND rating_userid = %d",
				$post_id,
				get_current_user_id()
			)
		);
	}

	/**
	 * Pick the first valid address out of a proxy header.
	 *
	 * X-Forwarded-For and friends are a chain -- "client, proxy1, proxy2" -- and
	 * the visitor controls the left of it. Using the whole string as an identity
	 * means appending one more hop yields a different value, so the vote
	 * limiting it keys is defeated even on a site that opted in on purpose.
	 *
	 * @param string $value Raw header value.
	 *
	 * @return string First address that validates, or an empty string.
	 */
	public static function parse_ip_header( $value ) {
		foreach ( explode( ',', $value ) as $candidate ) {
			$candidate = filter_var( trim( $candidate ), FILTER_VALIDATE_IP );

			if ( false !== $candidate ) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * The visitor's address, before hashing.
	 *
	 * REMOTE_ADDR unless the site says otherwise, because a proxy header is
	 * whatever the client typed into it: a visitor who can vary X-Forwarded-For
	 * can vote as many times as they can be bothered to, and the repeat check
	 * that reads this is the only thing standing in the way.
	 *
	 * A site genuinely behind a proxy opts in the same three ways as the other
	 * plugins in this collection: by naming the header on the settings screen,
	 * by defining WP_POSTRATINGS_TRUST_PROXY, or through the
	 * wp_postratings_trust_proxy filter. Naming the header is the narrow
	 * version -- exactly one header, the one the site's own load balancer sets
	 * -- and the constant is the broad one, meaning "the usual seven".
	 *
	 * @return string
	 */
	public static function get_raw_ip() {
		// esc_attr() used to stand in as the sanitizer here, for a value headed
		// to a hash and the database. Validating REMOTE_ADDR on the way through
		// is safe for data already recorded: it is a bare address, so it and its
		// hash come out unchanged.
		$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip          = (string) filter_var( $remote_addr, FILTER_VALIDATE_IP );

		$ip_header = (string) WP_PostRatings_Options::get( 'ip_header' );

		/**
		 * Filters whether the usual proxy headers may be trusted.
		 *
		 * Lets the decision be made per request -- trusting the header only when
		 * the request actually arrives from a known load balancer, say -- rather
		 * than once in wp-config.php.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $trust Defaults to the WP_POSTRATINGS_TRUST_PROXY constant.
		 */
		$trust_proxy = (bool) apply_filters(
			'wp_postratings_trust_proxy',
			defined( 'WP_POSTRATINGS_TRUST_PROXY' ) && WP_POSTRATINGS_TRUST_PROXY
		);

		if ( '' !== $ip_header && ! empty( $_SERVER[ $ip_header ] ) ) {
			$forwarded = self::parse_ip_header( sanitize_text_field( wp_unslash( $_SERVER[ $ip_header ] ) ) );

			if ( '' !== $forwarded ) {
				$ip = $forwarded;
			}
		} elseif ( $trust_proxy ) {
			// The named header wins when there is one: it is the site telling us
			// exactly which hop to believe, and guessing after that would be
			// second-guessing an answer we already have.
			$headers = array(
				'HTTP_CF_CONNECTING_IP',
				'HTTP_CLIENT_IP',
				'HTTP_X_FORWARDED_FOR',
				'HTTP_X_FORWARDED',
				'HTTP_X_CLUSTER_CLIENT_IP',
				'HTTP_FORWARDED_FOR',
				'HTTP_FORWARDED',
			);

			foreach ( $headers as $name ) {
				if ( empty( $_SERVER[ $name ] ) ) {
					continue;
				}

				$candidate = self::parse_ip_header( sanitize_text_field( wp_unslash( $_SERVER[ $name ] ) ) );

				if ( '' !== $candidate ) {
					$ip = $candidate;
					break;
				}
			}
		}

		return $ip;
	}

	/**
	 * The visitor's address as stored: hashed.
	 *
	 * @return string
	 */
	public static function get_ip() {
		/**
		 * Filters the address as it is stored in the rating log.
		 *
		 * @since 1.87
		 *
		 * @param string $ipaddress Hashed address.
		 */
		return apply_filters( 'wp_postratings_ipaddress', wp_hash( self::get_raw_ip() ) );
	}

	/**
	 * The visitor's host name, anonymized when it does not resolve.
	 *
	 * @return string
	 */
	public static function get_hostname() {
		$ip = self::get_raw_ip();

		// gethostbyaddr() warns on anything that is not an address, which is
		// what an absent or unroutable REMOTE_ADDR resolves to here.
		if ( '' === $ip ) {
			/** This filter is documented in includes/class-wp-postratings-rating.php */
			return apply_filters( 'wp_postratings_hostname', '' );
		}

		$hostname = gethostbyaddr( $ip );

		if ( $hostname === $ip ) {
			$hostname = wp_privacy_anonymize_ip( $ip );
		}

		if ( false !== $hostname ) {
			$hostname = substr( $hostname, strpos( $hostname, '.' ) + 1 );
		}

		/**
		 * Filters the host name recorded beside a rating.
		 *
		 * @since 1.87
		 *
		 * @param string $hostname Resolved host name, anonymised when it does not resolve.
		 */
		return apply_filters( 'wp_postratings_hostname', $hostname );
	}

	/**
	 * Path of the advisory lock file for a post.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return string
	 */
	public static function lock_file( $post_id ) {
		/**
		 * Filters the path of a post's advisory lock file.
		 *
		 * @since 1.90
		 *
		 * @param string $path    Lock file path.
		 * @param int    $post_id Post being rated.
		 */
		return apply_filters(
			'wp_postratings_lock_file',
			get_temp_dir() . '/wp-blog-' . get_current_blog_id() . '-wp-postratings-' . $post_id . '.lock',
			$post_id
		);
	}

	/**
	 * Take the advisory lock for a post.
	 *
	 * WP_Filesystem has no locking primitive -- it abstracts over FTP and SSH --
	 * so there is nothing to port these calls to.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return resource|false
	 */
	public static function acquire_lock( $post_id ) {
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- WP_Filesystem abstracts over FTP and SSH and has no locking primitive, so there is nothing to port flock() to.
		$handle = fopen( self::lock_file( $post_id ), 'w+' );

		if ( false === $handle ) {
			return false;
		}

		if ( ! flock( $handle, LOCK_EX | LOCK_NB ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- See above; the handle taken by fopen() has to be closed with fclose().
			fclose( $handle );

			return false;
		}

		ftruncate( $handle, 0 );
		fwrite( $handle, microtime( true ) );
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		return $handle;
	}

	/**
	 * Release the advisory lock for a post.
	 *
	 * @param resource $handle  Lock file handle.
	 * @param int      $post_id Post id.
	 *
	 * @return bool
	 */
	public static function release_lock( $handle, $post_id ) {
		if ( ! is_resource( $handle ) ) {
			return false;
		}

		fflush( $handle );
		flock( $handle, LOCK_UN );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- The handle came from fopen() in acquire_lock(); see the note there.
		fclose( $handle );
		wp_delete_file( self::lock_file( $post_id ) );

		return true;
	}

	/**
	 * Whether a user agent looks like a crawler.
	 *
	 * @param string $useragent User agent string.
	 *
	 * @return bool
	 */
	private static function is_bot( $useragent ) {
		foreach ( self::$bots as $bot ) {
			if ( false !== stristr( $useragent, $bot ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Handle a vote posted to admin-ajax.php.
	 *
	 * Reachable logged out, so everything is validated before anything is
	 * written. A nonce is near-worthless here -- logged-out visitors share one
	 * anonymous session, so the token is the same for everybody -- which is why
	 * the real limits are the logging method and the per-post advisory lock.
	 *
	 * @return void
	 */
	public static function handle_vote() {
		$rate    = isset( $_REQUEST['rate'] ) ? (int) $_REQUEST['rate'] : 0;
		$post_id = isset( $_REQUEST['pid'] ) ? (int) $_REQUEST['pid'] : 0;

		if ( ! check_ajax_referer( 'wp_postratings_' . $post_id . '-nonce', 'wp_postratings_' . $post_id . '_nonce', false ) ) {
			esc_html_e( 'Failed To Verify Referrer', 'wp-postratings' );
			exit;
		}

		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );

		WP_PostRatings_Template::render( self::process_vote( $post_id, $rate ) );
		exit;
	}

	/**
	 * Apply a vote and build the response.
	 *
	 * Split out from handle_vote() so it can be driven from a test: the handler
	 * ends in exit(), which takes the runner with it.
	 *
	 * @param int $post_id Post being rated.
	 * @param int $rate    Position on the scale.
	 *
	 * @return string Markup to send back, or '' to say nothing.
	 */
	public static function process_vote( $post_id, $rate ) {
		$post_id = (int) $post_id;
		$rate    = (int) $rate;

		if ( $rate <= 0 || $post_id <= 0 || ! self::can_rate() ) {
			return '';
		}

		$useragent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		if ( self::is_bot( $useragent ) ) {
			return '';
		}

		$ratings_max    = (int) WP_PostRatings_Options::get( 'max' );
		$ratings_values = (array) WP_PostRatings_Options::get_nested( 'ratings', 'value' );

		// Validated before the write, not clamped after it. Out of range used to
		// become $ratings_values[-1]: a warning, and a vote worth nothing.
		if ( $rate > $ratings_max || ! isset( $ratings_values[ $rate - 1 ] ) ) {
			/* translators: %s: rating that was submitted. */
			return sprintf( esc_html__( 'Invalid Rating (#%s).', 'wp-postratings' ), $rate );
		}

		$lock = self::acquire_lock( $post_id );

		if ( false === $lock ) {
			return esc_html__( 'Unable to obtain lock', 'wp-postratings' );
		}

		// The lock is released on every path below. A single release at the end
		// of the old handler was unreachable, because each branch exited first,
		// so every rating left its lock file behind in the temp directory.
		try {
			if ( self::has_rated( $post_id ) ) {
				/* translators: %s: post id. */
				return sprintf( esc_html__( 'You Had Already Rated This Post. Post ID #%s.', 'wp-postratings' ), $post_id );
			}

			$post = get_post( $post_id );

			if ( ! $post || wp_is_post_revision( $post ) ) {
				/* translators: %s: post id. */
				return sprintf( esc_html__( 'Invalid Post ID (#%s).', 'wp-postratings' ), $post_id );
			}

			$totals = self::record( $post, $rate, $ratings_values[ $rate - 1 ] );

			return WP_PostRatings_Template::expand(
				WP_PostRatings_Options::template( 'text' ),
				$post_id,
				(object) $totals
			);
		} finally {
			self::release_lock( $lock, $post_id );
		}
	}

	/**
	 * Apply a vote: update the meta, set the cookie, write the log row.
	 *
	 * @param WP_Post $post         Post being rated.
	 * @param int     $rate         Position on the scale.
	 * @param int     $rating_value Score that position is worth.
	 *
	 * @return array Updated totals.
	 */
	private static function record( $post, $rate, $rating_value ) {
		global $wpdb, $user_identity;

		$post_id = (int) $post->ID;
		$meta    = get_post_custom( $post_id );

		$users = ! empty( $meta['ratings_users'] ) ? (int) $meta['ratings_users'][0] : 0;
		$score = ! empty( $meta['ratings_score'] ) ? (int) $meta['ratings_score'][0] : 0;

		++$users;
		$score  += (int) $rating_value;
		$average = round( $score / $users, 2 );

		update_post_meta( $post_id, 'ratings_users', $users );
		update_post_meta( $post_id, 'ratings_score', $score );
		update_post_meta( $post_id, 'ratings_average', $average );

		// No addslashes(): $wpdb->prepare() escapes these already, so slashing
		// first stored a literal backslash. The read path still stripslashes(),
		// so rows written either way display correctly.
		if ( ! empty( $user_identity ) ) {
			$rate_user = $user_identity;
		} elseif ( ! empty( $_COOKIE[ 'comment_author_' . COOKIEHASH ] ) ) {
			$rate_user = sanitize_text_field( wp_unslash( $_COOKIE[ 'comment_author_' . COOKIEHASH ] ) );
		} else {
			$rate_user = __( 'Guest', 'wp-postratings' );
		}

		/**
		 * Filters the name recorded against a rating.
		 *
		 * @since 1.80
		 *
		 * @param string $rate_user Display name, comment author name, or "Guest".
		 */
		$rate_user = apply_filters( 'wp_postratings_process_ratings_user', $rate_user );

		/**
		 * Filters the user id recorded against a rating.
		 *
		 * @since 1.80
		 *
		 * @param int $rate_userid User id, 0 for a guest.
		 */
		$rate_userid = apply_filters( 'wp_postratings_process_ratings_userid', get_current_user_id() );

		$check_method = (int) WP_PostRatings_Options::get( 'check_method' );

		/*
		 * headers_sent() as well as the setting: a cookie cannot be set once
		 * anything has been written to the response, so the call is guaranteed to
		 * fail and its only effect is a "headers already sent" warning. A theme
		 * or another plugin echoing before this runs is enough to trigger it, and
		 * a warning printed into a JSON response is what breaks the vote.
		 */
		if ( ( 1 === $check_method || 3 === $check_method ) && ! headers_sent() ) {
			/**
			 * Filters when the "already rated" cookie expires.
			 *
			 * @since 1.84
			 *
			 * @param int $expiration Unix timestamp.
			 */
			$expiration = apply_filters( 'wp_postratings_cookie_expiration', time() + 30000000 );

			/**
			 * Filters the path the "already rated" cookie is set on.
			 *
			 * @since 1.78
			 *
			 * @param string $path Cookie path.
			 */
			$cookie_path = apply_filters( 'wp_postratings_cookiepath', SITECOOKIEPATH );

			setcookie( 'rated_' . $post_id, $rating_value, $expiration, $cookie_path );
		}

		/*
		 * Every vote is recorded, whatever the repeat-vote check is set to.
		 *
		 * Those were one setting until 2.0.0: choosing "Do Not Log" or "Logged
		 * By Cookie" -- now "Nothing" and "Cookie" -- meant no row was written,
		 * so the ratings log, the logs screen and the WP-Stats numbers were all
		 * empty on a site that had simply picked a lighter check. The two are
		 * not the same question. What a returning visitor is matched against is
		 * a matter of how strict the site wants to be; whether its votes are
		 * recorded at all is not something that should follow from it.
		 *
		 * The 1.87 filter wp_postratings_always_log asked for exactly this and
		 * is gone: it is now the behaviour, and a filter that forces the default
		 * is a filter that does nothing. The one below is its opposite, for
		 * sites that would rather not keep the record.
		 */

		/**
		 * Filters whether this vote is written to the ratings log.
		 *
		 * The post's own score and vote count are post meta and are updated
		 * either way; this is the per-vote row behind the logs screen, the
		 * IP and username checks, and the WP-Stats figures. Returning false
		 * leaves those without anything to read.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $log     Whether to record the vote.
		 * @param int  $post_id Post being rated.
		 */
		if ( apply_filters( 'wp_postratings_log_rating', true, $post_id ) ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->ratings} VALUES (%d, %d, %s, %d, %d, %s, %s, %s, %d)",
					0,
					$post_id,
					$post->post_title,
					$rating_value,
					// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp -- rating_timestamp holds a site-local timestamp in every row ever written; changing it would reinterpret stored data.
					current_time( 'timestamp' ),
					self::get_ip(),
					self::get_hostname(),
					$rate_user,
					$rate_userid
				)
			);
		}

		/**
		 * Fires after a post has been rated.
		 *
		 * Renamed from the unprefixed rate_post in 2.0.0, which had been public
		 * since 2005 and is gone rather than deprecated.
		 *
		 * @since 2.0.0
		 *
		 * @param int $rate_userid  User id that rated, 0 for a guest.
		 * @param int $post_id      Post that was rated.
		 * @param int $rating_value Score the rating was worth.
		 */
		do_action( 'wp_postratings_rate_post', $rate_userid, $post_id, $rating_value );

		return array(
			'ratings_users'   => $users,
			'ratings_score'   => $score,
			'ratings_average' => $average,
		);
	}
}
