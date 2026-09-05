<?php
/**
 * The REST API routes.
 *
 * @package WP-PostRatings
 */

defined( 'ABSPATH' ) || exit;

/**
 * Exposes reading a post's rating -- one at a time or several at once -- and
 * casting one, over the REST API.
 *
 * The namespace is the bare noun `postratings/v1` rather than the plugin slug.
 * A `wp-` prefix is a wordpress.org directory convention for keeping one
 * plugin's download page apart from another's; it says nothing about what the
 * plugin should call the things it registers, and the ecosystem names those
 * after the brand rather than the directory entry. Another plugin can claim the
 * same bare noun and WordPress will not detect it; that is the accepted trade.
 *
 * **These routes are added beside `wp_ajax_wp_postratings`, not in place of
 * it.** That action stays registered and stays supported: a theme or a cached
 * script may be calling it, and those are in the position of a post holding a
 * shortcode -- nobody here can survey them.
 *
 * Rating is deliberately open to logged-out visitors, because that is who rates
 * things. `permission_callback` therefore answers true and **eligibility is
 * enforced inside the callback**, by the same checks the AJAX path runs: the
 * per-post nonce, `can_rate()`, the bot check, the repeat-rating guard and the
 * scale bounds.
 */
class WP_PostRatings_API {

	/**
	 * The REST namespace every route is registered under.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'postratings/v1';

	/**
	 * The most posts one batch read will answer for.
	 *
	 * A ceiling rather than an error: the caller is a page listing the ratings
	 * it is showing, and no page shows a hundred.
	 *
	 * @var int
	 */
	const MAX_BATCH = 100;

	/**
	 * Register the routes once the REST API is up.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the plugin's routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		// Existence is not a validate_callback on purpose. A failed validator is
		// a 400, which suits a parameter whose domain is fixed; a post id is
		// well formed and names a resource that may have been deleted, which is
		// a 404. Each callback resolves it through post_or_404().
		$id = array(
			'id' => array(
				'required' => true,
			),
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'post/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_rating' ),
				'permission_callback' => '__return_true',
				'args'                => $id,
			)
		);

		// A page showing ten ratings is one request, not ten. The read route
		// exists so a cached page can correct itself, and an archive is
		// exactly where a page cache pays off most -- charging it a round trip
		// per rating would take back what the cache was for.
		register_rest_route(
			self::REST_NAMESPACE,
			'posts',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_ratings' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'ids' => array(
						'required'    => true,
						'description' => __( 'Post ids to read, comma separated.', 'wp-postratings' ),
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'post/(?P<id>\d+)/rate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rate' ),
				'permission_callback' => '__return_true',
				'args'                => array_merge(
					$id,
					array(
						'rate'  => array(
							'required'    => true,
							'description' => __( 'Position on the rating scale.', 'wp-postratings' ),
						),
						'nonce' => array(
							'required'    => true,
							'description' => __( 'The wp_postratings_<id>-nonce this post was rendered with.', 'wp-postratings' ),
						),
					)
				),
			)
		);
	}

	/**
	 * Resolve an id to a post, or the error the response should carry.
	 *
	 * @param int $post_id Post being asked for.
	 * @return WP_Post|WP_Error The post, or a 404.
	 */
	private function post_or_404( $post_id ) {
		$post_id = (int) $post_id;

		// The same question the AJAX path asks; see is_ratable(). A 404 rather
		// than a 403 for a post that exists but may not be rated, because the
		// distinction would tell a stranger which drafts exist.
		if ( ! WP_PostRatings_Rating::is_ratable( $post_id ) ) {
			return new WP_Error(
				'wp_postratings_not_ratable',
				/* translators: %s: post id. */
				sprintf( __( 'This Post Cannot Be Rated (#%s).', 'wp-postratings' ), $post_id ),
				array( 'status' => 404 )
			);
		}

		return get_post( $post_id );
	}

	/**
	 * Return a post's rating totals and the markup a visitor should see.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_rating( $request ) {
		$post_id = (int) $request['id'];
		$post    = $this->post_or_404( $post_id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return $this->no_store( rest_ensure_response( $this->rating_payload( $post_id ) ) );
	}

	/**
	 * Return several posts' ratings in one response.
	 *
	 * An id naming nothing ratable is left out rather than failing the batch.
	 * The caller is a page correcting the ratings it is showing, and one post
	 * deleted since the page was cached must not cost the other nine theirs.
	 *
	 * @since 2.1.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_ratings( $request ) {
		$ids = array_slice(
			array_unique(
				array_filter(
					array_map( 'absint', explode( ',', (string) $request['ids'] ) )
				)
			),
			0,
			self::MAX_BATCH
		);

		$posts = array();

		foreach ( $ids as $post_id ) {
			if ( ! WP_PostRatings_Rating::is_ratable( $post_id ) ) {
				continue;
			}

			$posts[] = $this->rating_payload( $post_id );
		}

		return $this->no_store( rest_ensure_response( array( 'posts' => $posts ) ) );
	}

	/**
	 * One post's rating, as both routes report it.
	 *
	 * @since 2.1.0
	 *
	 * @param int $post_id Post being read.
	 * @return array
	 */
	private function rating_payload( $post_id ) {
		$rating = WP_PostRatings_Data::get( $post_id );
		$block  = WP_PostRatings_Template::block( $post_id );

		return array(
			'post_id'      => $post_id,
			'users'        => $rating['users'],
			'score'        => $rating['score'],
			'average'      => $rating['average'],
			'has_rated'    => (bool) WP_PostRatings_Rating::has_rated( $post_id ),
			'can_rate'     => (bool) WP_PostRatings_Rating::can_rate(),
			// The markup is returned as well as the numbers because the
			// rating templates and the shape are the site's to change: a
			// client rebuilding the stars itself would ignore both.
			'html'         => WP_PostRatings_Template::expand(
				WP_PostRatings_Options::template( 'text' ),
				$post_id,
				(object) array(
					'ratings_users'   => $rating['users'],
					'ratings_score'   => $rating['score'],
					'ratings_average' => $rating['average'],
				)
			),
			// `html` is the read-only result and stays that, because it has
			// been that since 2.0.0 and something is reading it. This is the
			// other question: not "what does this rating say" but "what should
			// this visitor be looking at" -- which for somebody who has not
			// rated yet is the control, and a control needs its nonce.
			'visitor_html' => $block['html'],
			'nonce'        => $block['nonce'],
		);
	}

	/**
	 * Forbid storing a read response.
	 *
	 * Core sends no-cache headers on a REST response only when the request is
	 * logged in -- `rest_send_nocache_headers` defaults to
	 * `is_user_logged_in()` -- and these routes are read by exactly the
	 * logged-out visitors that default excludes. Everything in the payload is
	 * answered for one visitor: whether they have rated, whether they may, and
	 * a nonce. A CDN holding one copy of that and serving it to the next
	 * visitor would undo the whole point of asking.
	 *
	 * @since 2.1.0
	 *
	 * @param WP_REST_Response $response Response to mark.
	 * @return WP_REST_Response
	 */
	private function no_store( $response ) {
		$response->header( 'Cache-Control', 'no-store, private' );

		return $response;
	}

	/**
	 * Record a rating and return the markup that replaces the control.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rate( $request ) {
		$post_id = (int) $request['id'];
		$post    = $this->post_or_404( $post_id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		// The same nonce the AJAX path checks, and the same one the rendered
		// control already carries. Weakening this because the caller is REST
		// rather than admin-ajax would be a security change dressed as a port.
		if ( ! wp_verify_nonce( (string) $request['nonce'], 'wp_postratings_' . $post_id . '-nonce' ) ) {
			return new WP_Error(
				'wp_postratings_bad_nonce',
				__( 'Failed To Verify Referrer', 'wp-postratings' ),
				array( 'status' => 403 )
			);
		}

		// process_vote() returns on success and throws on every refusal, so the
		// two are separate channels and this route does not have to infer an
		// outcome from a side effect. It used to watch ratings_users move,
		// because the old contract answered with a string either way.
		try {
			$html = WP_PostRatings_Rating::process_vote( $post_id, (int) $request['rate'] );
		} catch ( InvalidArgumentException $e ) {
			// Empty for the refusals that say nothing to a browser -- a bot, or
			// a visitor not allowed to rate. A client asking over REST gets a
			// reason rather than an empty body.
			return new WP_Error(
				'wp_postratings_rating_refused',
				'' === $e->getMessage() ? __( 'This rating was not accepted.', 'wp-postratings' ) : $e->getMessage(),
				array( 'status' => 403 )
			);
		}

		$after = WP_PostRatings_Data::get( $post_id );

		return rest_ensure_response(
			array(
				'post_id' => $post_id,
				'users'   => $after['users'],
				'score'   => $after['score'],
				'average' => $after['average'],
				'html'    => $html,
			)
		);
	}
}
