<?php
/**
 * WP-PostRatings class-postratings-settings.php
 *
 * @package wp-postratings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The settings screen, built on the Settings API.
 *
 * Replaces the two hand-rolled $_POST forms the plugin used before 2.0.0. Both
 * tabs write the one consolidated option; the sanitizer keeps whatever the
 * submitted tab did not post, so saving one tab cannot blank the other.
 *
 * @since 2.0.0
 */
class Postratings_Settings {

	/**
	 * Settings group for the main tab.
	 */
	const GROUP_SETTINGS = 'wp_postratings_settings';

	/**
	 * Settings group for the templates tab.
	 */
	const GROUP_TEMPLATES = 'wp_postratings_templates';

	/**
	 * Register the settings.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'wp_ajax_postratings_rating_fields', array( __CLASS__, 'ajax_rating_fields' ) );
	}

	/**
	 * Register both groups against the single option row.
	 *
	 * @return void
	 */
	public static function register() {
		$args = array(
			'type'              => 'array',
			'sanitize_callback' => array( 'Postratings_Options', 'sanitize' ),
			'default'           => Postratings_Options::defaults(),
		);

		register_setting( self::GROUP_SETTINGS, Postratings_Options::OPTION, $args );
		register_setting( self::GROUP_TEMPLATES, Postratings_Options::OPTION, $args );
	}

	/**
	 * The tabs on the settings screen.
	 *
	 * @return array
	 */
	private static function tabs() {
		return array(
			'settings'  => __( 'Ratings Settings', 'wp-postratings' ),
			'templates' => __( 'Ratings Templates', 'wp-postratings' ),
		);
	}

	/**
	 * Which tab is being shown.
	 *
	 * @return string
	 */
	private static function current_tab() {
		// Reading the request to decide what to render; nothing is written here.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';

		return array_key_exists( $tab, self::tabs() ) ? $tab : 'settings';
	}

	/**
	 * A field name inside the option array.
	 *
	 * @param string $key    Top level key.
	 * @param string $subkey Optional nested key.
	 *
	 * @return string
	 */
	private static function name( $key, $subkey = '' ) {
		$name = Postratings_Options::OPTION . '[' . $key . ']';

		return '' !== $subkey ? $name . '[' . $subkey . ']' : $name;
	}

	/**
	 * The default templates for an up/down (two point) scale.
	 *
	 * Offered beside the normal defaults by the "Restore Default" buttons.
	 *
	 * @return array
	 */
	public static function updown_templates() {
		$comma  = __( ',', 'wp-postratings' );
		$rating = __( 'rating', 'wp-postratings' );
		$votes  = __( 'votes', 'wp-postratings' );
		$rated  = __( 'rated', 'wp-postratings' );

		return array(
			'vote'         => '%RATINGS_IMAGES_VOTE% (<strong>%RATINGS_SCORE%</strong> ' . $rating . $comma . ' <strong>%RATINGS_USERS%</strong> ' . $votes . ')<br />%RATINGS_TEXT%',
			'text'         => '%RATINGS_IMAGES% (<em><strong>%RATINGS_SCORE%</strong> ' . $rating . $comma . ' <strong>%RATINGS_USERS%</strong> ' . $votes . $comma . ' <strong>' . $rated . '</strong></em>)',
			'permission'   => '%RATINGS_IMAGES% (<em><strong>%RATINGS_SCORE%</strong> ' . $rating . $comma . ' <strong>%RATINGS_USERS%</strong> ' . $votes . $comma . ' <strong>' . $rated . '</strong></em>)<br /><em>' . __( 'You need to be a registered member to rate this.', 'wp-postratings' ) . '</em>',
			'none'         => '%RATINGS_IMAGES_VOTE% (' . __( 'No Ratings Yet', 'wp-postratings' ) . ')<br />%RATINGS_TEXT%',
			'highestrated' => '<li><a href="%POST_URL%" title="%POST_TITLE%">%POST_TITLE%</a> (%RATINGS_SCORE% ' . $rating . $comma . ' %RATINGS_USERS% ' . $votes . ')</li>',
			'mostrated'    => '<li><a href="%POST_URL%"  title="%POST_TITLE%">%POST_TITLE%</a> - %RATINGS_USERS% ' . $votes . '</li>',
		);
	}

	/**
	 * Every template, with its label and the tokens it understands.
	 *
	 * The token lists are emitted as <code> spans beside a translated label
	 * rather than inside the translatable string: phpcbf reads a literal
	 * %TOKEN% as a printf placeholder and renumbers it, which is how
	 * %GUESTS_SEPARATOR% once became %1$GUESTS_SEPARATOR% on screen.
	 *
	 * @return array
	 */
	private static function template_fields() {
		$post_tokens = array( '%POST_ID%', '%POST_TITLE%', '%POST_EXCERPT%', '%POST_CONTENT%', '%POST_URL%', '%POST_THUMBNAIL%' );

		return array(
			'vote'         => array(
				__( 'Ratings Vote Text:', 'wp-postratings' ),
				array( '%RATINGS_IMAGES_VOTE%', '%RATINGS_MAX%', '%RATINGS_SCORE%', '%RATINGS_TEXT%', '%RATINGS_USERS%', '%RATINGS_AVERAGE%', '%RATINGS_PERCENTAGE%' ),
			),
			'text'         => array(
				__( 'Ratings Voted Text:', 'wp-postratings' ),
				array( '%RATINGS_IMAGES%', '%RATINGS_MAX%', '%RATINGS_SCORE%', '%RATINGS_USERS%', '%RATINGS_AVERAGE%', '%RATINGS_PERCENTAGE%' ),
			),
			'permission'   => array(
				__( 'Ratings No Permission Text:', 'wp-postratings' ),
				array( '%RATINGS_IMAGES%', '%RATINGS_MAX%', '%RATINGS_SCORE%', '%RATINGS_USERS%', '%RATINGS_AVERAGE%', '%RATINGS_PERCENTAGE%' ),
			),
			'none'         => array(
				__( 'Ratings None:', 'wp-postratings' ),
				array( '%RATINGS_IMAGES_VOTE%', '%RATINGS_MAX%', '%RATINGS_SCORE%', '%RATINGS_TEXT%', '%RATINGS_USERS%', '%RATINGS_AVERAGE%', '%RATINGS_PERCENTAGE%' ),
			),
			'highestrated' => array(
				__( 'Highest Rated:', 'wp-postratings' ),
				array_merge( array( '%RATINGS_IMAGES%', '%RATINGS_MAX%', '%RATINGS_SCORE%', '%RATINGS_USERS%', '%RATINGS_AVERAGE%', '%RATINGS_PERCENTAGE%' ), $post_tokens ),
			),
			'mostrated'    => array(
				__( 'Most Rated:', 'wp-postratings' ),
				array_merge( array( '%RATINGS_USERS%', '%RATINGS_AVERAGE%' ), $post_tokens ),
			),
		);
	}

	/**
	 * Render the settings screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( Postratings_Installer::CAPABILITY ) ) {
			wp_die( esc_html__( 'Access Denied', 'wp-postratings' ) );
		}

		$tab = self::current_tab();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Post Ratings Options', 'wp-postratings' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ( self::tabs() as $slug => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-postratings-options&tab=' . $slug ) ); ?>"
						class="nav-tab<?php echo $slug === $tab ? ' nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php
				if ( 'templates' === $tab ) {
					settings_fields( self::GROUP_TEMPLATES );
					self::render_templates_tab();
				} else {
					settings_fields( self::GROUP_SETTINGS );
					self::render_settings_tab();
				}

				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * The main settings tab.
	 *
	 * @return void
	 */
	private static function render_settings_tab() {
		$options = Postratings_Options::get();
		?>
		<h2><?php esc_html_e( 'Ratings Settings', 'wp-postratings' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Ratings Image:', 'wp-postratings' ); ?></th>
				<td><?php self::render_image_choices( $options ); ?></td>
			</tr>
			<tr>
				<th scope="row">
					<label for="postratings_max"><?php esc_html_e( 'Max Ratings:', 'wp-postratings' ); ?></label>
				</th>
				<td>
					<input type="number" min="1" id="postratings_max" name="<?php echo esc_attr( self::name( 'max' ) ); ?>"
						value="<?php echo esc_attr( $options['max'] ); ?>" class="small-text"
						<?php echo $options['customrating'] ? 'readonly="readonly"' : ''; ?> />
					<input type="hidden" id="postratings_customrating" name="<?php echo esc_attr( self::name( 'customrating' ) ); ?>"
						value="<?php echo esc_attr( $options['customrating'] ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable Google Rich Snippets?', 'wp-postratings' ); ?></th>
				<td>
					<label><input type="radio" id="postratings_richsnippet_on" name="<?php echo esc_attr( self::name( 'richsnippet' ) ); ?>" value="1" <?php checked( $options['richsnippet'], 1 ); ?> />&nbsp;<?php esc_html_e( 'Yes', 'wp-postratings' ); ?></label>
					&nbsp;&nbsp;
					<label><input type="radio" name="<?php echo esc_attr( self::name( 'richsnippet' ) ); ?>" value="0" <?php checked( $options['richsnippet'], 0 ); ?> />&nbsp;<?php esc_html_e( 'No', 'wp-postratings' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable Ratings in Rich Snippets?', 'wp-postratings' ); ?></th>
				<td>
					<label><input type="radio" class="postratings-richsnippet-ratings" name="<?php echo esc_attr( self::name( 'richsnippet_ratings' ) ); ?>" value="1" <?php checked( $options['richsnippet_ratings'], 1 ); ?> <?php disabled( $options['richsnippet'], 0 ); ?> />&nbsp;<?php esc_html_e( 'Yes', 'wp-postratings' ); ?></label>
					&nbsp;&nbsp;
					<label><input type="radio" class="postratings-richsnippet-ratings" name="<?php echo esc_attr( self::name( 'richsnippet_ratings' ) ); ?>" value="0" <?php checked( $options['richsnippet_ratings'], 0 ); ?> <?php disabled( $options['richsnippet'], 0 ); ?> />&nbsp;<?php esc_html_e( 'No', 'wp-postratings' ); ?></label>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Individual Rating Text/Value', 'wp-postratings' ); ?></h2>
		<p>
			<button type="button" class="button" id="postratings-refresh-ratings"
				data-nonce="<?php echo esc_attr( wp_create_nonce( 'postratings_rating_fields' ) ); ?>">
				<?php esc_html_e( 'Update \'Individual Rating Text/Value\' Display', 'wp-postratings' ); ?>
			</button>
			<span class="spinner" id="postratings-spinner"></span>
		</p>
		<div id="postratings-rating-fields">
			<?php self::render_rating_fields( $options['customrating'], (int) $options['max'], $options['image'], (array) $options['ratings']['text'], (array) $options['ratings']['value'] ); ?>
		</div>

		<h2><?php esc_html_e( 'Ratings AJAX Style', 'wp-postratings' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="postratings_loading"><?php esc_html_e( 'Show Loading Image With Text', 'wp-postratings' ); ?></label>
				</th>
				<td>
					<select id="postratings_loading" name="<?php echo esc_attr( self::name( 'ajax_style', 'loading' ) ); ?>">
						<option value="0" <?php selected( $options['ajax_style']['loading'], 0 ); ?>><?php esc_html_e( 'No', 'wp-postratings' ); ?></option>
						<option value="1" <?php selected( $options['ajax_style']['loading'], 1 ); ?>><?php esc_html_e( 'Yes', 'wp-postratings' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="postratings_fading"><?php esc_html_e( 'Show Fading In And Fading Out Of Ratings', 'wp-postratings' ); ?></label>
				</th>
				<td>
					<select id="postratings_fading" name="<?php echo esc_attr( self::name( 'ajax_style', 'fading' ) ); ?>">
						<option value="0" <?php selected( $options['ajax_style']['fading'], 0 ); ?>><?php esc_html_e( 'No', 'wp-postratings' ); ?></option>
						<option value="1" <?php selected( $options['ajax_style']['fading'], 1 ); ?>><?php esc_html_e( 'Yes', 'wp-postratings' ); ?></option>
					</select>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Allow To Rate', 'wp-postratings' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="postratings_allowtorate"><?php esc_html_e( 'Who Is Allowed To Rate?', 'wp-postratings' ); ?></label>
				</th>
				<td>
					<select id="postratings_allowtorate" name="<?php echo esc_attr( self::name( 'allowtorate' ) ); ?>">
						<option value="0" <?php selected( $options['allowtorate'], 0 ); ?>><?php esc_html_e( 'Guests Only', 'wp-postratings' ); ?></option>
						<option value="1" <?php selected( $options['allowtorate'], 1 ); ?>><?php esc_html_e( 'Logged-in Users Only', 'wp-postratings' ); ?></option>
						<?php if ( is_multisite() ) : ?>
							<option value="3" <?php selected( $options['allowtorate'], 3 ); ?>><?php esc_html_e( 'Users Registered On Blog Only', 'wp-postratings' ); ?></option>
						<?php endif; ?>
						<option value="2" <?php selected( $options['allowtorate'], 2 ); ?>><?php esc_html_e( 'Logged-in Users And Guests', 'wp-postratings' ); ?></option>
					</select>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Logging Method', 'wp-postratings' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="postratings_logging_method"><?php esc_html_e( 'Ratings Logging Method:', 'wp-postratings' ); ?></label>
				</th>
				<td>
					<select id="postratings_logging_method" name="<?php echo esc_attr( self::name( 'logging_method' ) ); ?>">
						<option value="0" <?php selected( $options['logging_method'], 0 ); ?>><?php esc_html_e( 'Do Not Log', 'wp-postratings' ); ?></option>
						<option value="1" <?php selected( $options['logging_method'], 1 ); ?>><?php esc_html_e( 'Logged By Cookie', 'wp-postratings' ); ?></option>
						<option value="2" <?php selected( $options['logging_method'], 2 ); ?>><?php esc_html_e( 'Logged By IP', 'wp-postratings' ); ?></option>
						<option value="3" <?php selected( $options['logging_method'], 3 ); ?>><?php esc_html_e( 'Logged By Cookie And IP', 'wp-postratings' ); ?></option>
						<option value="4" <?php selected( $options['logging_method'], 4 ); ?>><?php esc_html_e( 'Logged By Username', 'wp-postratings' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="postratings_ip_header"><?php esc_html_e( 'Header That Contains The IP:', 'wp-postratings' ); ?></label>
				</th>
				<td>
					<input type="text" id="postratings_ip_header" name="<?php echo esc_attr( self::name( 'ip_header' ) ); ?>"
						value="<?php echo esc_attr( $options['ip_header'] ); ?>" class="regular-text" />
					<p class="description">
						<?php esc_html_e( 'Leave this blank unless the site is behind a reverse proxy or CDN. Blank means the address the web server saw is used.', 'wp-postratings' ); ?>
						<br />
						<?php
						printf(
							/* translators: 1, 2: example header names, wrapped in <code>. */
							esc_html__( 'Example: %1$s or %2$s.', 'wp-postratings' ),
							'<code>HTTP_X_FORWARDED_FOR</code>',
							'<code>HTTP_CF_CONNECTING_IP</code>'
						);
						?>
						<br />
						<strong><?php esc_html_e( 'Only name a header your proxy sets and overwrites. A visitor can send any header they like, so trusting one your stack does not control lets anyone rate as often as they wish.', 'wp-postratings' ); ?></strong>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * The radio buttons for the image sets.
	 *
	 * @param array $options Plugin settings.
	 *
	 * @return void
	 */
	private static function render_image_choices( $options ) {
		foreach ( Postratings_Template::image_folders() as $folder ) {
			$info = Postratings_Template::folder_info( $folder );

			if ( empty( $info['images'][0] ) || false === strpos( $info['images'][0], '.' . RATINGS_IMG_EXT ) ) {
				continue;
			}

			echo '<p>';
			printf(
				'<input type="radio" name="%s" value="%s"%s data-custom="%d" data-max="%d" class="postratings-image-choice" />&nbsp;&nbsp;&nbsp;',
				esc_attr( self::name( 'image' ) ),
				esc_attr( $folder ),
				checked( $options['image'], $folder, false ),
				(int) $info['custom'],
				(int) $info['max']
			);

			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered image markup, escaped as it is built.
			echo Postratings_Template::ratings_images(
				(int) $info['custom'],
				(int) $info['max'],
				$info['custom'] ? 0 : 2,
				$folder,
				'',
				$info['custom'] ? 0 : 3
			);
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

			echo '&nbsp;&nbsp;&nbsp;(' . esc_html( $folder ) . ')';
			echo '</p>' . "\n";
		}
	}

	/**
	 * The per-rating text and value table.
	 *
	 * @param int    $custom Whether the set is a custom one.
	 * @param int    $max    Number of steps on the scale.
	 * @param string $image  Image set folder name.
	 * @param array  $texts  Per-rating labels.
	 * @param array  $values Per-rating scores.
	 *
	 * @return void
	 */
	public static function render_rating_fields( $custom, $max, $image, $texts, $values ) {
		?>
		<table class="form-table" role="presentation">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Rating Image', 'wp-postratings' ); ?></th>
					<th><?php esc_html_e( 'Rating Text', 'wp-postratings' ); ?></th>
					<th><?php esc_html_e( 'Rating Value', 'wp-postratings' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php for ( $i = 1; $i <= $max; $i++ ) : ?>
					<?php
					// Raising Max Ratings leaves these arrays shorter than the
					// loop, so neither index is guaranteed to exist.
					$text  = isset( $texts[ $i - 1 ] ) ? $texts[ $i - 1 ] : '';
					$value = isset( $values[ $i - 1 ] ) ? (int) $values[ $i - 1 ] : $i;
					?>
					<tr>
						<td>
							<?php
							// Preview of this step: an up/down set shows the one
							// image for this position, anything else shows the
							// strip lit up to it.
							// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered image markup, escaped as it is built.
							if ( $custom && 2 === (int) $max ) {
								echo Postratings_Template::ratings_images_comment_author( 1, 2, 2 === $i ? 1 : 0, $image, '' );
							} else {
								echo Postratings_Template::ratings_images( $custom, $i, $i, $image, '', 0 );
							}
							// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</td>
						<td>
							<input type="text" id="postratings_ratingstext_<?php echo esc_attr( $i ); ?>"
								name="<?php echo esc_attr( self::name( 'ratings' ) ); ?>[text][]"
								value="<?php echo esc_attr( $text ); ?>" size="20" maxlength="50" />
						</td>
						<td>
							<input type="number" id="postratings_ratingsvalue_<?php echo esc_attr( $i ); ?>"
								name="<?php echo esc_attr( self::name( 'ratings' ) ); ?>[value][]"
								value="<?php echo esc_attr( $value ); ?>" class="small-text" />
						</td>
					</tr>
				<?php endfor; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Rebuild the rating text/value table for a different image set.
	 *
	 * @return void
	 */
	public static function ajax_rating_fields() {
		check_ajax_referer( 'postratings_rating_fields' );

		if ( ! current_user_can( Postratings_Installer::CAPABILITY ) ) {
			wp_die( -1, 403 );
		}

		$custom = isset( $_GET['custom'] ) ? (int) $_GET['custom'] : 0;
		$max    = isset( $_GET['max'] ) ? (int) $_GET['max'] : 0;
		$image  = isset( $_GET['image'] ) ? sanitize_file_name( wp_unslash( $_GET['image'] ) ) : '';

		if ( ! in_array( $image, Postratings_Template::image_folders(), true ) ) {
			wp_die( -1, 400 );
		}

		$max = max( 1, min( 100, $max ) );

		if ( $custom && 2 === $max ) {
			$texts  = array( __( 'Vote Down', 'wp-postratings' ), __( 'Vote Up', 'wp-postratings' ) );
			$values = array( -1, 1 );
		} else {
			$texts  = array();
			$values = array();

			for ( $i = 1; $i <= $max; $i++ ) {
				$texts[] = 1 === $i
					/* translators: %s: number of stars. */
					? sprintf( __( '%s Star', 'wp-postratings' ), number_format_i18n( $i ) )
					/* translators: %s: number of stars. */
					: sprintf( __( '%s Stars', 'wp-postratings' ), number_format_i18n( $i ) );

				$values[] = $i;
			}
		}

		self::render_rating_fields( $custom, $max, $image, $texts, $values );
		exit;
	}

	/**
	 * The templates tab.
	 *
	 * @return void
	 */
	private static function render_templates_tab() {
		$templates = (array) Postratings_Options::get( 'templates' );
		?>
		<h2><?php esc_html_e( 'Ratings Templates', 'wp-postratings' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php foreach ( self::template_fields() as $key => $field ) : ?>
				<?php list( $label, $tokens ) = $field; ?>
				<tr>
					<th scope="row" style="width: 30%;">
						<label for="postratings_template_<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label>
						<p><?php esc_html_e( 'Allowed Variables:', 'wp-postratings' ); ?></p>
						<p>
							<?php foreach ( $tokens as $token ) : ?>
								<code><?php echo esc_html( $token ); ?></code><br />
							<?php endforeach; ?>
						</p>
						<p>
							<button type="button" class="button postratings-restore-template"
								data-template="<?php echo esc_attr( $key ); ?>" data-variant="default">
								<?php esc_html_e( 'Restore Default Template (Normal Rating)', 'wp-postratings' ); ?>
							</button>
						</p>
						<p>
							<button type="button" class="button postratings-restore-template"
								data-template="<?php echo esc_attr( $key ); ?>" data-variant="updown">
								<?php esc_html_e( 'Restore Default Template (Up/Down Rating)', 'wp-postratings' ); ?>
							</button>
						</p>
					</th>
					<td>
						<textarea cols="80" rows="12" class="large-text code"
							id="postratings_template_<?php echo esc_attr( $key ); ?>"
							name="<?php echo esc_attr( self::name( 'templates', $key ) ); ?>"><?php echo esc_textarea( isset( $templates[ $key ] ) ? $templates[ $key ] : '' ); ?></textarea>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}
}
