<?php
/**
 * The REST API routes.
 *
 * @package WP-PostRatings
 */

defined( 'ABSPATH' ) || exit;

/**
 * Exposes reading a post's rating and casting one over the REST API.
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
		$post = get_post( (int) $post_id );

		if ( ! $post || wp_is_post_revision( $post ) ) {
			return new WP_Error(
				'wp_postratings_no_such_post',
				/* translators: %s: post id. */
				sprintf( __( 'Invalid Post ID (#%s).', 'wp-postratings' ), (int) $post_id ),
				array( 'status' => 404 )
			);
		}

		return $post;
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

		$rating = WP_PostRatings_Data::get( $post_id );

		return rest_ensure_response(
			array(
				'post_id'   => $post_id,
				'users'     => $rating['users'],
				'score'     => $rating['score'],
				'average'   => $rating['average'],
				'has_rated' => (bool) WP_PostRatings_Rating::has_rated( $post_id ),
				'can_rate'  => (bool) WP_PostRatings_Rating::can_rate(),
				// The markup is returned as well as the numbers because the
				// rating templates and the shape are the site's to change: a
				// client rebuilding the stars itself would ignore both.
				'html'      => WP_PostRatings_Template::expand(
					WP_PostRatings_Options::template( 'text' ),
					$post_id,
					(object) array(
						'ratings_users'   => $rating['users'],
						'ratings_score'   => $rating['score'],
						'ratings_average' => $rating['average'],
					)
				),
			)
		);
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

		// process_vote() answers with a string either way -- the rendered result
		// when the rating landed, a plain sentence when it was refused, and an
		// empty string for the refusals that deliberately say nothing (a bot, a
		// visitor who may not rate). Nothing in that return distinguishes the
		// two, so the vote is detected by what it did rather than by what it
		// said: a rating that counted always moves ratings_users by one.
		//
		// Changing process_vote() to return a structured result would be
		// tidier and is deliberately not done here: the AJAX handler and its
		// tests share that contract, and a REST route is not a reason to
		// rewrite the path every existing site votes through.
		$before = WP_PostRatings_Data::get( $post_id );
		$html   = WP_PostRatings_Rating::process_vote( $post_id, (int) $request['rate'] );
		$after  = WP_PostRatings_Data::get( $post_id );

		if ( $after['users'] === $before['users'] ) {
			return new WP_Error(
				'wp_postratings_rating_refused',
				'' === $html ? __( 'This rating was not accepted.', 'wp-postratings' ) : $html,
				array( 'status' => 403 )
			);
		}

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
