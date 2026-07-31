<?php
/**
 * WP-PostRatings class-wp-postratings-settings.php
 *
 * @package wp-postratings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The settings screen, built on the Settings API.
 *
 * Replaces the two hand-rolled $_POST forms the plugin used before 2.0.0, and
 * the two registered settings groups that came after them: settings never span
 * more than one page, so the second group is gone and what used to be two menu
 * entries is one page with two tabs. Both tabs write the one consolidated
 * option; the sanitizer keeps whatever the submitted tab did not post, so
 * saving one tab cannot blank the other.
 *
 * @since 2.0.0
 */
class WP_PostRatings_Settings {

	/**
	 * Settings group passed to register_setting() and settings_fields().
	 *
	 * One group, not two: a tab is not a page. Registering a second group
	 * against the same option row was what made the two halves of this screen
	 * reachable by different URLs.
	 */
	const GROUP = 'wp_postratings_options';

	/**
	 * The capability the plugin's screens require.
	 *
	 * Deliberately not manage_options: WP-PostRatings has shipped its own
	 * capability since 1.80, and sites grant it to editors who are trusted with
	 * the rating logs but not with the rest of wp-admin.
	 */
	const CAPABILITY = 'manage_ratings';

	/**
	 * How the rating is drawn.
	 */
	const SECTION_APPEARANCE = 'wp_postratings_appearance';

	/**
	 * The per-rating text and value table.
	 */
	const SECTION_RATINGS = 'wp_postratings_ratings';

	/**
	 * How many steps a shape is previewed at in the picker.
	 *
	 * Fixed, so the rows differ only by shape.
	 *
	 * @var int
	 */
	const PREVIEW_STEPS = 5;

	/**
	 * Who may rate, and how repeat votes are detected.
	 */
	const SECTION_VOTING = 'wp_postratings_voting';

	/**
	 * Google rich snippets.
	 */
	const SECTION_SNIPPETS = 'wp_postratings_snippets';

	/**
	 * What this plugin contributes to WP-Stats.
	 */
	const SECTION_STATS = 'wp_postratings_stats';

	/**
	 * The %TOKEN% templates.
	 */
	const SECTION_TEMPLATES = 'wp_postratings_templates';

	/**
	 * The capability required for a given context.
	 *
	 * @param string $context What the capability is being checked for.
	 *
	 * @return string
	 */
	public static function capability( $context = 'settings' ) {
		/**
		 * Filters the capability required to manage the plugin.
		 *
		 * @since 2.0.0
		 *
		 * @param string $capability The required capability.
		 * @param string $context    What the capability is being checked for.
		 */
		return (string) apply_filters( 'wp_postratings_capability', self::CAPABILITY, $context );
	}

	/**
	 * The settings page slug.
	 *
	 * A submenu of the plugin's own menu rather than a page of its own, so it
	 * hangs off the menu slug rather than repeating it.
	 *
	 * @return string
	 */
	public static function page() {
		return WP_PostRatings_Admin::PAGE . '-settings';
	}

	/**
	 * Register the settings.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'wp_ajax_wp_postratings_rating_fields', array( __CLASS__, 'ajax_rating_fields' ) );
	}

	/**
	 * The tabs on the settings screen.
	 *
	 * @return array
	 */
	private static function tabs() {
		return array(
			'settings'  => __( 'Settings', 'wp-postratings' ),
			'templates' => __( 'Templates', 'wp-postratings' ),
		);
	}

	/**
	 * Which tab is being shown.
	 *
	 * @return string
	 */
	private static function current_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Choosing which tab to draw; nothing is read from the request beyond that and nothing is written.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';

		return array_key_exists( $tab, self::tabs() ) ? $tab : 'settings';
	}

	/**
	 * The Settings API page a tab's sections are registered against.
	 *
	 * Each tab is its own settings page as far as do_settings_sections() is
	 * concerned, which is what stops one tab drawing the other's fields.
	 *
	 * @param string $tab Tab slug.
	 *
	 * @return string
	 */
	private static function tab_page( $tab ) {
		return self::page() . '-' . $tab;
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
		$name = WP_PostRatings_Options::OPTION . '[' . $key . ']';

		return '' !== $subkey ? $name . '[' . $subkey . ']' : $name;
	}

	/**
	 * Register the one setting, its sections and its fields.
	 *
	 * @return void
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			WP_PostRatings_Options::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'WP_PostRatings_Options', 'sanitize' ),
				'default'           => WP_PostRatings_Options::defaults(),
			)
		);

		$settings  = self::tab_page( 'settings' );
		$templates = self::tab_page( 'templates' );

		$sections = array(
			array( self::SECTION_APPEARANCE, __( 'Appearance', 'wp-postratings' ), $settings ),
			array( self::SECTION_RATINGS, __( 'Individual Rating Text and Value', 'wp-postratings' ), $settings ),
			array( self::SECTION_VOTING, __( 'Who May Rate', 'wp-postratings' ), $settings ),
			array( self::SECTION_SNIPPETS, __( 'Google Rich Snippets', 'wp-postratings' ), $settings ),
			array( self::SECTION_STATS, __( 'WP-Stats', 'wp-postratings' ), $settings ),
			array( self::SECTION_TEMPLATES, __( 'Ratings Templates', 'wp-postratings' ), $templates ),
		);

		foreach ( $sections as $section ) {
			list( $id, $title, $page ) = $section;

			add_settings_section( $id, $title, array( __CLASS__, 'section_' . self::section_suffix( $id ) ), $page );
		}

		$fields = array(
			array( 'shape', __( 'Ratings Shape:', 'wp-postratings' ), self::SECTION_APPEARANCE, $settings ),
			array( 'allowtorate', __( 'Who Is Allowed To Rate?', 'wp-postratings' ), self::SECTION_VOTING, $settings ),
			array( 'check_method', __( 'Check For Repeat Votes:', 'wp-postratings' ), self::SECTION_VOTING, $settings ),
			array( 'ip_header', __( 'Header That Contains The IP:', 'wp-postratings' ), self::SECTION_VOTING, $settings ),
			array( 'schema_type', __( 'Show ratings in Google results?', 'wp-postratings' ), self::SECTION_SNIPPETS, $settings ),
			array( 'stats_display', __( 'Show A Ratings Section On The Stats Page?', 'wp-postratings' ), self::SECTION_STATS, $settings ),
			array( 'stats_most_limit', __( 'Entries Per Stats List:', 'wp-postratings' ), self::SECTION_STATS, $settings ),
		);

		foreach ( self::template_fields() as $key => $field ) {
			$fields[] = array( 'template_' . $key, $field[0], self::SECTION_TEMPLATES, $templates );
		}

		foreach ( $fields as $field ) {
			list( $key, $title, $section, $page ) = $field;

			add_settings_field( 'wp_postratings_' . $key, $title, array( __CLASS__, 'field_' . $key ), $page, $section );
		}
	}

	/**
	 * The bare name of a section constant's value.
	 *
	 * @param string $id Section id.
	 *
	 * @return string
	 */
	private static function section_suffix( $id ) {
		return substr( $id, strlen( 'wp_postratings_' ) );
	}

	// --- section intros ---------------------------------------------------

	/**
	 * Intro for the appearance section.
	 *
	 * @return void
	 */
	public static function section_appearance() {
		echo '<p>' . esc_html__( 'The shapes are drawn with CSS, so they stay sharp at any size and cost no HTTP requests.', 'wp-postratings' ) . '</p>';
	}

	/**
	 * Intro for the per-rating section.
	 *
	 * @return void
	 */
	public static function section_ratings() {
		echo '<p>' . esc_html__( 'What each step on the scale is called, what it is worth, and how it looks.', 'wp-postratings' ) . '</p>';

		/*
		 * Rendered here rather than as a settings field, so it is not a row of
		 * the form-table and does not sit squeezed into the value column beside
		 * an empty label. It is a table of its own with six columns; the label
		 * "Rating Text / Value:" beside it said less than the column headings
		 * already do.
		 */
		self::field_ratings();
	}

	/**
	 * Intro for the AJAX section.
	 *
	 * @return void
	 */
	public static function section_ajax() {
		echo '<p>' . esc_html__( 'Votes are posted in the background; this is what the visitor sees while that happens.', 'wp-postratings' ) . '</p>';
	}

	/**
	 * Intro for the voting section.
	 *
	 * @return void
	 */
	public static function section_voting() {
		echo '<p>' . esc_html__( 'Who may cast a vote, and how a repeat vote is recognised.', 'wp-postratings' ) . '</p>';
	}

	/**
	 * Intro for the rich snippets section.
	 *
	 * @return void
	 */
	public static function section_snippets() {
		echo '<p>' . esc_html__( 'Structured data describing the post and its rating, for search engines.', 'wp-postratings' ) . '</p>';
	}

	/**
	 * Intro for the WP-Stats section.
	 *
	 * @return void
	 */
	public static function section_stats() {
		echo '<p>' . esc_html__( 'Only used when the WP-Stats plugin is installed. These settings are this plugin\'s own; before 2.0.0 six plugins shared one option row and overwrote each other.', 'wp-postratings' ) . '</p>';
	}

	/**
	 * Intro for the templates section.
	 *
	 * @return void
	 */
	public static function section_templates() {
		echo '<p>' . esc_html__( 'The markup each rating is rendered with. The allowed variables are listed under each box.', 'wp-postratings' ) . '</p>';
	}

	// --- fields -----------------------------------------------------------

	/**
	 * The radio buttons for the rating shapes.
	 *
	 * Reads WP_PostRatings_Shapes::all(), which is the same registry the
	 * sanitizer checks against, so the screen cannot offer a shape that would
	 * not save -- and a shape registered through wp_postratings_shapes appears
	 * here for free.
	 *
	 * @return void
	 */
	public static function field_shape() {
		$options  = WP_PostRatings_Options::get();
		$selected = WP_PostRatings_Template::resolve_shape( $options['shape'] );
		$max      = max( 1, (int) $options['max'] );

		/*
		 * The type is derived from the chosen shape, never stored beside it.
		 * A shape already knows whether it is a scale or a pair of opposing
		 * actions, and two places holding one fact is how they end up
		 * disagreeing.
		 */
		$current_type = WP_PostRatings_Shapes::is_updown( $selected )
			? WP_PostRatings_Shapes::UPDOWN
			: WP_PostRatings_Shapes::SCALE;

		echo '<p class="wp-postratings-type-choice">';

		foreach ( self::rating_types() as $type => $label ) {
			printf(
				'<label><input type="radio" name="wp-postratings-rating-type" value="%1$s" class="wp-postratings-rating-type"%2$s /> %3$s</label> ',
				esc_attr( $type ),
				checked( $current_type, $type, false ),
				esc_html( $label )
			);
		}

		echo '</p>';

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'A scale rates out of several points. Up or down is a pair of opposing actions, so it is always two and shows one glyph.', 'wp-postratings' )
		);

		foreach ( WP_PostRatings_Shapes::all() as $name => $shape ) {
			$is_updown = WP_PostRatings_Shapes::UPDOWN === $shape['type'];

			// Wrapped in .wp-postratings so the preview inherits the colours the
			// site has chosen: they are scoped to the wrapper, not to :root.
			printf(
				'<p class="wp-postratings-shape-row" data-rating-type="%1$s"%2$s>',
				esc_attr( $shape['type'] ),
				$shape['type'] === $current_type ? '' : ' hidden'
			);
			printf(
				'<input type="radio" name="%s" value="%s"%s data-custom="%d" data-max="%d" class="wp-postratings-shape-choice" />',
				esc_attr( self::name( 'shape' ) ),
				esc_attr( $name ),
				checked( $selected, $name, false ),
				$is_updown ? 1 : 0,
				esc_attr( WP_PostRatings_Options::defaults_for_type( $name )['max'] )
			);

			/*
			 * The preview keeps its own .wp-postratings wrapper, which is
			 * inline-flex and is what lays the glyphs out. The row around it is a
			 * separate flex container -- putting both classes on one element made
			 * the row's display win and the shapes vanished.
			 *
			 * Always five steps, in the built-in colours. This is a comparison of
			 * shapes, so every row has to differ only by shape: drawing each at
			 * the site's own scale and colours made a three step set look shorter
			 * than a five step one and both look like whatever had been chosen,
			 * which is the one thing the picker is not asking about.
			 */
			echo '<span class="wp-postratings wp-postratings-shape-preview">';

			WP_PostRatings_Template::render( WP_PostRatings_Template::shape_preview( $name, self::PREVIEW_STEPS ) );

			echo '</span>';

			echo '<span>' . esc_html( $shape['label'] ) . '</span>';
			echo '</p>' . "\n";
		}
	}

	/**
	 * The per-rating text and value table.
	 *
	 * There is no rebuild button. The table follows the shape and the scale on
	 * its own, because it has no independent meaning: a five point scale needs
	 * five rows and a thumbs up/down needs two, so a table that disagrees with
	 * the shape above it is simply wrong. Asking somebody to press a button to
	 * make one part of a form agree with another is asking them to do the
	 * form's job.
	 *
	 * The nonce moves onto the container the rows are written into, since that
	 * is what is left for the script to find.
	 *
	 * @return void
	 */
	public static function field_ratings() {
		$options = WP_PostRatings_Options::get();
		?>
		<div id="wp-postratings-rating-fields"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_postratings_rating_fields' ) ); ?>">
			<?php
			self::render_rating_fields(
				(int) $options['max'],
				$options['shape'],
				(array) $options['ratings']['text'],
				(array) $options['ratings']['value'],
				(array) ( $options['ratings']['color'] ?? array() ),
				(array) ( $options['ratings']['color_off'] ?? array() )
			);
			?>
		</div>
		<?php
	}

	/**
	 * A yes/no select for one key of a nested group.
	 *
	 * @param string $key    Nested key.
	 * @param array  $values Stored group.
	 * @param string $group  Top level key.
	 *
	 * @return void
	 */
	private static function yes_no( $key, $values, $group ) {
		$id      = 'wp_postratings_' . $key;
		$current = isset( $values[ $key ] ) ? (int) $values[ $key ] : 0;
		?>
		<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( self::name( $group, $key ) ); ?>">
			<option value="0" <?php selected( $current, 0 ); ?>><?php esc_html_e( 'No', 'wp-postratings' ); ?></option>
			<option value="1" <?php selected( $current, 1 ); ?>><?php esc_html_e( 'Yes', 'wp-postratings' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Who may cast a vote.
	 *
	 * @return void
	 */
	public static function field_allowtorate() {
		$current = (int) WP_PostRatings_Options::get( 'allowtorate' );
		?>
		<select id="wp_postratings_allowtorate" name="<?php echo esc_attr( self::name( 'allowtorate' ) ); ?>">
			<option value="0" <?php selected( $current, 0 ); ?>><?php esc_html_e( 'Guests Only', 'wp-postratings' ); ?></option>
			<option value="1" <?php selected( $current, 1 ); ?>><?php esc_html_e( 'Logged-in Users Only', 'wp-postratings' ); ?></option>
			<?php if ( is_multisite() ) : ?>
				<option value="3" <?php selected( $current, 3 ); ?>><?php esc_html_e( 'Users Registered On Blog Only', 'wp-postratings' ); ?></option>
			<?php endif; ?>
			<option value="2" <?php selected( $current, 2 ); ?>><?php esc_html_e( 'Logged-in Users And Guests', 'wp-postratings' ); ?></option>
		</select>
		<?php
	}

	/**
	 * How a repeat vote is recognised.
	 *
	 * Called the "Ratings Logging Method" until 2.0.0, with choices reading "Do
	 * Not Log" and "Logged By Cookie". It never chose whether to log: it chose
	 * what a returning visitor is matched against, and "Do Not Log" meant "let
	 * everyone vote as often as they like". The two readings pointed opposite
	 * ways -- a site that wanted a record of its votes picked the setting that
	 * stopped the plugin keeping one.
	 *
	 * @return void
	 */
	public static function field_check_method() {
		$current = (int) WP_PostRatings_Options::get( 'check_method' );

		// The stored numbers are unchanged: 1 has meant the cookie and 2 the
		// address since long before this screen was rewritten, and renumbering
		// them would quietly reinterpret the setting on every existing site.
		$methods = array(
			0 => __( 'Do Not Check', 'wp-postratings' ),
			1 => __( 'Check By Cookie', 'wp-postratings' ),
			2 => __( 'Check By IP Address', 'wp-postratings' ),
			3 => __( 'Check By Cookie And IP Address', 'wp-postratings' ),
			4 => __( 'Check By Username', 'wp-postratings' ),
		);
		?>
		<select id="wp_postratings_check_method" name="<?php echo esc_attr( self::name( 'check_method' ) ); ?>">
			<?php foreach ( $methods as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'Every vote is recorded in the ratings log whichever of these is chosen. This decides only what a returning visitor is matched against. Do Not Check lets anyone rate a post as often as they like.', 'wp-postratings' ); ?>
		</p>
		<?php
	}

	/**
	 * Which request header carries the visitor's address.
	 *
	 * @return void
	 */
	public static function field_ip_header() {
		?>
		<input type="text" id="wp_postratings_ip_header" name="<?php echo esc_attr( self::name( 'ip_header' ) ); ?>"
			value="<?php echo esc_attr( (string) WP_PostRatings_Options::get( 'ip_header' ) ); ?>" class="regular-text" />
		<p class="description">
			<?php esc_html_e( 'Leave this blank unless the site is behind a reverse proxy or CDN. Blank means the address the web server saw is used.', 'wp-postratings' ); ?>
			<br />
			<?php
			printf(
				/* translators: 1: an example header name, 2: the WP_POSTRATINGS_TRUST_PROXY constant, 3: the wp_postratings_trust_proxy filter, all in code spans. */
				esc_html__( 'Example: %1$s. You can also opt in with the %2$s constant or the %3$s filter, which trust the usual proxy headers instead of one you name.', 'wp-postratings' ),
				'<code>HTTP_X_FORWARDED_FOR</code>',
				'<code>WP_POSTRATINGS_TRUST_PROXY</code>',
				'<code>wp_postratings_trust_proxy</code>'
			);
			?>
			<br />
			<strong><?php esc_html_e( 'Only name a header your proxy sets and overwrites. A visitor can send any header they like, so trusting one your stack does not control lets anyone rate as often as they wish.', 'wp-postratings' ); ?></strong>
		</p>
		<?php
	}

	/**
	 * Which schema.org type the rating markup declares, if any.
	 *
	 * One control, not two. There used to be an "Enable Google Rich Snippets?"
	 * toggle and an "Enable Ratings In Rich Snippets?" toggle beside it, and
	 * they had become the same decision: the aggregate rating is the only
	 * reason to emit this markup at all -- everything else in it duplicates
	 * what a theme or an SEO plugin already says, better.
	 *
	 * @return void
	 */
	public static function field_schema_type() {
		$current = (string) WP_PostRatings_Options::get( 'schema_type' );
		?>
		<select id="wp_postratings_schema_type" name="<?php echo esc_attr( self::name( 'schema_type' ) ); ?>">
			<option value="" <?php selected( $current, '' ); ?>><?php esc_html_e( 'No', 'wp-postratings' ); ?></option>
			<?php foreach ( WP_PostRatings_Options::schema_types() as $type => $label ) : ?>
				<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $current, $type ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'Pick the type only if your posts really are that thing. Google shows a rating for these types and no others -- Article, BlogPosting and NewsArticle are not among them, which is why this produced nothing before.', 'wp-postratings' ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'Marking a post as something it is not, to collect stars, is what Google calls spammy structured markup, and it costs a manual action rather than a ranking.', 'wp-postratings' ); ?>
		</p>
		<?php
	}

	/**
	 * Whether to contribute a section to the WP-Stats page.
	 *
	 * @return void
	 */
	public static function field_stats_display() {
		?>
		<?php
		/*
		 * A hidden 0 in front of the box, sharing its name.
		 *
		 * An unticked checkbox posts nothing at all, and the sanitizer keeps
		 * whatever the submission did not mention -- deliberately, because this
		 * screen has two tabs writing one option row and neither may blank the
		 * other. Together those meant this box could be ticked and never
		 * unticked: turning it off posted nothing, the sanitizer read the
		 * silence as "leave it alone", and the box came back ticked.
		 *
		 * With the hidden field the control always says something. PHP keeps the
		 * last of a repeated name, so ticked posts 1 and unticked posts 0.
		 */
		?>
		<input type="hidden" name="<?php echo esc_attr( self::name( 'stats_display' ) ); ?>" value="0" />
		<label>
			<input type="checkbox" id="wp_postratings_stats_display" name="<?php echo esc_attr( self::name( 'stats_display' ) ); ?>"
				value="1" <?php checked( (int) WP_PostRatings_Options::get( 'stats_display' ), 1 ); ?> />
			<?php esc_html_e( 'Show the vote total and the top rated posts on the WP-Stats page', 'wp-postratings' ); ?>
		</label>
		<?php
	}

	/**
	 * How many entries each WP-Stats list shows.
	 *
	 * @return void
	 */
	public static function field_stats_most_limit() {
		?>
		<input type="number" min="1" id="wp_postratings_stats_most_limit"
			name="<?php echo esc_attr( self::name( 'stats_most_limit' ) ); ?>"
			value="<?php echo esc_attr( (string) WP_PostRatings_Options::get( 'stats_most_limit' ) ); ?>" class="small-text" />
		<?php
	}

	/**
	 * The "vote" template.
	 *
	 * @return void
	 */
	public static function field_template_vote() {
		self::render_template_field( 'vote' );
	}

	/**
	 * The "already voted" template.
	 *
	 * @return void
	 */
	public static function field_template_text() {
		self::render_template_field( 'text' );
	}

	/**
	 * The "not allowed to vote" template.
	 *
	 * @return void
	 */
	public static function field_template_permission() {
		self::render_template_field( 'permission' );
	}

	/**
	 * The "no ratings yet" template.
	 *
	 * @return void
	 */
	public static function field_template_none() {
		self::render_template_field( 'none' );
	}

	/**
	 * The highest rated list template.
	 *
	 * @return void
	 */
	public static function field_template_highestrated() {
		self::render_template_field( 'highestrated' );
	}

	/**
	 * The most rated list template.
	 *
	 * @return void
	 */
	public static function field_template_mostrated() {
		self::render_template_field( 'mostrated' );
	}

	/**
	 * One template textarea, its variable list and its two restore buttons.
	 *
	 * @param string $key Template name.
	 *
	 * @return void
	 */
	private static function render_template_field( $key ) {
		$fields    = self::template_fields();
		$templates = (array) WP_PostRatings_Options::get( 'templates' );
		$tokens    = isset( $fields[ $key ] ) ? $fields[ $key ][1] : array();
		$value     = isset( $templates[ $key ] ) ? $templates[ $key ] : '';
		?>
		<textarea rows="6" class="large-text code"
			id="wp_postratings_template_<?php echo esc_attr( $key ); ?>"
			name="<?php echo esc_attr( self::name( 'templates', $key ) ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Allowed variables:', 'wp-postratings' ); ?>
			<?php foreach ( $tokens as $token ) : ?>
				<code><?php echo esc_html( $token ); ?></code>
			<?php endforeach; ?>
		</p>
		<p>
			<?php
			/*
			 * Named for the two rating types, in the same words the type chooser
			 * uses and keyed on the same values it stores. They were "Normal
			 * Rating" and "Up/Down Rating" here, "Scale" and "Up or down" up
			 * there, and 'default' and 'updown' in the markup -- three vocabularies
			 * for one distinction, on one screen.
			 */
			foreach ( self::rating_types() as $type => $label ) :
				?>
				<button type="button" class="button wp-postratings-restore-template"
					data-template="<?php echo esc_attr( $key ); ?>" data-variant="<?php echo esc_attr( $type ); ?>">
					<?php
					/* translators: %s: rating type, such as "Scale" or "Up or down". */
					printf( esc_html__( 'Reset to default (%s)', 'wp-postratings' ), esc_html( $label ) );
					?>
				</button>
			<?php endforeach; ?>
		</p>
		<?php
	}

	// --- shared data ------------------------------------------------------

	/**
	 * The two rating types, as the screen names them.
	 *
	 * One list, because the words appear in the type chooser, on the template
	 * reset buttons and in the script's data, and a screen that calls the same
	 * thing three names is a screen nobody can follow.
	 *
	 * @return array Type constant => label.
	 */
	public static function rating_types() {
		return array(
			WP_PostRatings_Shapes::SCALE  => __( 'Scale', 'wp-postratings' ),
			WP_PostRatings_Shapes::UPDOWN => __( 'Up or down', 'wp-postratings' ),
		);
	}

	/**
	 * The default templates for a rating type.
	 *
	 * Named to match WP_PostRatings_Options::defaults_for_type(), which answers
	 * the same question about the rating itself.
	 *
	 * @param string $type Either WP_PostRatings_Shapes::SCALE or ::UPDOWN.
	 *
	 * @return array Template key => markup.
	 */
	public static function templates_for_type( $type ) {
		if ( WP_PostRatings_Shapes::UPDOWN !== $type ) {
			return WP_PostRatings_Options::defaults()['templates'];
		}

		return self::updown_templates();
	}

	/**
	 * The default templates for an up/down pair.
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

	// --- the screen -------------------------------------------------------

	/**
	 * Render the settings screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'Access Denied', 'wp-postratings' ) );
		}

		$tab = self::current_tab();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Ratings Settings', 'wp-postratings' ); ?></h1>

			<?php
			/*
			 * Unscoped, so "Settings saved." actually appears.
			 *
			 * options.php registers that notice against the 'general' slug, not
			 * against this screen, so settings_errors( self::PAGE ) -- which is
			 * what the logs screen wants for its own delete notice -- filtered
			 * out the one message this page exists to show. Pressing Save
			 * Changes sent the browser back here and said nothing at all.
			 */
			settings_errors();
			?>

			<nav class="nav-tab-wrapper">
				<?php foreach ( self::tabs() as $slug => $label ) : ?>
					<?php
					$tab_url = add_query_arg(
						array(
							'page' => self::page(),
							'tab'  => $slug,
						),
						admin_url( 'admin.php' )
					);
					?>
					<a href="<?php echo esc_url( $tab_url ); ?>"
						class="nav-tab<?php echo $slug === $tab ? ' nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php
				settings_fields( self::GROUP );

				// The tab is carried through the save so options.php sends the
				// browser back to the tab it was submitted from rather than to
				// the first one.
				printf(
					'<input type="hidden" name="_wp_http_referer" value="%s" />',
					esc_url(
						add_query_arg(
							array(
								'page' => self::page(),
								'tab'  => $tab,
							),
							admin_url( 'admin.php' )
						)
					)
				);

				do_settings_sections( self::tab_page( $tab ) );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * The per-rating text, value and colour table.
	 *
	 * A widefat table rather than a form-table: it lists one row per step on
	 * the scale, so it is a small data table inside a field, not a second
	 * settings form. do_settings_sections() owns the form-table on this screen.
	 *
	 * @param int    $max        Number of steps on the scale.
	 * @param string $image      Shape name.
	 * @param array  $texts      Per-rating labels.
	 * @param array  $values     Per-rating scores.
	 * @param array  $colors     Per-rating rated colours, each '' for the built-in one.
	 * @param array  $colors_off Per-rating unrated colours, same convention.
	 *
	 * @return void
	 */
	public static function render_rating_fields( $max, $image, $texts, $values, $colors = array(), $colors_off = array() ) {
		/*
		 * What an empty per-rating colour falls back to, and what an untouched
		 * swatch shows -- a colour input cannot be empty, and one left at #000000
		 * reads as a choice nobody made. These match the stylesheet's own var()
		 * fallbacks, so an empty value and the swatch agree.
		 *
		 * The swatches are always enabled. They used to be disabled beside a
		 * "Default" checkbox, which meant clicking the thing that looks like a
		 * colour picker did nothing at all. One Reset button below the table
		 * puts every row back to the built-in colours instead.
		 *
		 * There is no site-wide pair any more: a colour per rating says
		 * everything the pair did and more, and two ways to set the same thing is
		 * how a screen ends up with a setting that quietly loses.
		 */
		$rated_color   = WP_PostRatings_Options::COLOR_RATED;
		$unrated_color = WP_PostRatings_Options::COLOR_UNRATED;

		// An up/down pair is two opposing actions, not two points on a scale, and
		// the convention for them is green up and red down -- which is what the
		// stylesheet already falls back to on hover. So the table offers those as
		// the defaults rather than the one rated colour twice, which would have
		// shown a pair of identical swatches for the case this column exists for.
		$is_updown = WP_PostRatings_Shapes::is_updown( WP_PostRatings_Template::resolve_shape( $image ) );
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Rating', 'wp-postratings' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Rating Text', 'wp-postratings' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Rating Value', 'wp-postratings' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Rated', 'wp-postratings' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Not rated', 'wp-postratings' ); ?></th>
					<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'wp-postratings' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php for ( $i = 1; $i <= $max; $i++ ) : ?>
					<?php
					// Raising Max Ratings leaves these arrays shorter than the
					// loop, so neither index is guaranteed to exist.
					$text      = isset( $texts[ $i - 1 ] ) ? $texts[ $i - 1 ] : '';
					$value     = isset( $values[ $i - 1 ] ) ? (int) $values[ $i - 1 ] : $i;
					$color     = isset( $colors[ $i - 1 ] ) ? (string) $colors[ $i - 1 ] : '';
					$color_off = isset( $colors_off[ $i - 1 ] ) ? (string) $colors_off[ $i - 1 ] : '';

					/*
					 * From the shape being rendered, not from what is stored.
					 *
					 * This table is drawn for a shape the site may not have saved
					 * yet -- switching type rebuilds it before anything is saved --
					 * so consulting stored colours here showed a scale's orange in
					 * an up/down pair that had never had one. The stored values
					 * arrive as $colors and $colors_off; this is only what an
					 * empty one falls back to.
					 */
					if ( $is_updown ) {
						$step_default = 2 === $i ? WP_PostRatings_Options::COLOR_UP : WP_PostRatings_Options::COLOR_DOWN;
					} else {
						$step_default = $rated_color;
					}
					?>
					<tr>
						<?php
						/*
						 * The effective colours go on the cell, so the preview and
						 * the two swatches beside it are fed by the same values.
						 *
						 * Without this the preview showed only what was stored,
						 * and a step with nothing stored fell through to the
						 * stylesheet -- which is not the built-in colour under a
						 * dark colour scheme or increased contrast, both of which
						 * move --wp-postratings-color-off. So a newly added step
						 * rendered darker than the swatch beside it said it would.
						 */
						$preview_style = sprintf(
							'--wp-postratings-color-on:%1$s;--wp-postratings-color-off:%2$s',
							'' !== $color ? $color : $step_default,
							'' !== $color_off ? $color_off : $unrated_color
						);
						?>
						<td class="wp-postratings wp-postratings-rating-preview" style="<?php echo esc_attr( $preview_style ); ?>">
							<?php
							// Preview of this step: an up/down set shows the one
							// glyph for this position, anything else shows the
							// strip lit up to it. Its colours come from the cell
							// above, which is fed by the two inputs in this row.
							WP_PostRatings_Template::render( WP_PostRatings_Template::step_preview( $image, $max, $i ) );
							?>
						</td>
						<td>
							<label class="screen-reader-text" for="wp_postratings_ratingstext_<?php echo esc_attr( $i ); ?>"><?php esc_html_e( 'Rating Text', 'wp-postratings' ); ?></label>
							<input type="text" id="wp_postratings_ratingstext_<?php echo esc_attr( $i ); ?>"
								name="<?php echo esc_attr( self::name( 'ratings' ) ); ?>[text][]"
								value="<?php echo esc_attr( $text ); ?>" maxlength="50" class="regular-text" />
						</td>
						<td>
							<label class="screen-reader-text" for="wp_postratings_ratingsvalue_<?php echo esc_attr( $i ); ?>"><?php esc_html_e( 'Rating Value', 'wp-postratings' ); ?></label>
							<input type="number" id="wp_postratings_ratingsvalue_<?php echo esc_attr( $i ); ?>"
								name="<?php echo esc_attr( self::name( 'ratings' ) ); ?>[value][]"
								value="<?php echo esc_attr( $value ); ?>" class="small-text" />
						</td>
						<td>
							<label class="screen-reader-text" for="wp_postratings_ratingscolor_<?php echo esc_attr( $i ); ?>"><?php esc_html_e( 'Rated', 'wp-postratings' ); ?></label>
							<input type="color" id="wp_postratings_ratingscolor_<?php echo esc_attr( $i ); ?>"
								class="wp-postratings-color" data-default="<?php echo esc_attr( $step_default ); ?>"
								data-property="--wp-postratings-color-on"
								name="<?php echo esc_attr( self::name( 'ratings' ) ); ?>[color][]"
								value="<?php echo esc_attr( '' !== $color ? $color : $step_default ); ?>" />
						</td>
						<td>
							<label class="screen-reader-text" for="wp_postratings_ratingscoloroff_<?php echo esc_attr( $i ); ?>"><?php esc_html_e( 'Not rated', 'wp-postratings' ); ?></label>
							<input type="color" id="wp_postratings_ratingscoloroff_<?php echo esc_attr( $i ); ?>"
								class="wp-postratings-color" data-default="<?php echo esc_attr( $unrated_color ); ?>"
								data-property="--wp-postratings-color-off"
								name="<?php echo esc_attr( self::name( 'ratings' ) ); ?>[color_off][]"
								value="<?php echo esc_attr( '' !== $color_off ? $color_off : $unrated_color ); ?>" />
						</td>
						<td>
							<?php if ( ! $is_updown ) : ?>
								<button type="button" class="button-link wp-postratings-remove-step"
									<?php disabled( $max <= WP_PostRatings_Options::MIN_SCALE ); ?>>
									<?php esc_html_e( 'Remove', 'wp-postratings' ); ?>
									<span class="screen-reader-text">
										<?php
										/* translators: %s: position on the rating scale. */
										printf( esc_html__( 'step %s', 'wp-postratings' ), esc_html( number_format_i18n( $i ) ) );
										?>
									</span>
								</button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endfor; ?>
			</tbody>
		</table>
		<p>
			<?php if ( ! $is_updown ) : ?>
				<button type="button" class="button" id="wp-postratings-add-step"
					<?php disabled( $max >= WP_PostRatings_Options::max_scale() ); ?>>
					<?php esc_html_e( 'Add a step', 'wp-postratings' ); ?>
				</button>
			<?php endif; ?>
			<button type="button" class="button" id="wp-postratings-reset-colors">
				<?php esc_html_e( 'Reset to default', 'wp-postratings' ); ?>
			</button>
			<?php
			// Beside the buttons that cause the rebuild, rather than in a
			// paragraph of its own above the table -- where it was an empty line
			// on every page load, since a spinner is invisible until the script
			// marks it active.
			?>
			<span class="spinner" id="wp-postratings-spinner"></span>
		</p>
		<?php if ( $is_updown ) : ?>
			<p class="description">
				<?php esc_html_e( 'An up or down rating is always two steps, so there is nothing to add or remove.', 'wp-postratings' ); ?>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Rebuild the rating text/value table for a different shape.
	 *
	 * @return void
	 */
	public static function ajax_rating_fields() {
		check_ajax_referer( 'wp_postratings_rating_fields' );

		if ( ! current_user_can( self::capability() ) ) {
			wp_die( -1, 403 );
		}

		$max   = isset( $_GET['max'] ) ? (int) $_GET['max'] : 0;
		$image = isset( $_GET['shape'] ) ? sanitize_text_field( wp_unslash( $_GET['shape'] ) ) : '';
		$image = WP_PostRatings_Template::resolve_shape_strict( $image );

		if ( '' === $image ) {
			wp_die( -1, 400 );
		}

		$is_updown = WP_PostRatings_Shapes::is_updown( $image );
		$max       = $is_updown
			? 2
			: max( WP_PostRatings_Options::MIN_SCALE, min( WP_PostRatings_Options::max_scale(), $max ) );

		/*
		 * What the screen already has, rather than defaults for everything.
		 *
		 * Adding or removing a step re-renders this table, and rebuilding it
		 * from defaults would throw away every label, value and colour the site
		 * had typed -- for the rows that are not changing as well as the one that
		 * is. So the current state comes with the request and is padded or
		 * trimmed to the new length.
		 */
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- check_ajax_referer() ran at the top of this handler.
		$texts      = isset( $_GET['text'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_GET['text'] ) ) : array();
		$values     = isset( $_GET['value'] ) ? array_map( 'intval', (array) wp_unslash( $_GET['value'] ) ) : array();
		$colors     = isset( $_GET['color'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_GET['color'] ) ) : array();
		$colors_off = isset( $_GET['color_off'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_GET['color_off'] ) ) : array();
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$fresh = WP_PostRatings_Options::defaults_for_type( $image );

		if ( $is_updown ) {
			$texts  = array_pad( array_slice( $texts, 0, 2 ), 2, '' );
			$values = $fresh['ratings']['value'];

			foreach ( array( 0, 1 ) as $step ) {
				if ( '' === $texts[ $step ] ) {
					$texts[ $step ] = $fresh['ratings']['text'][ $step ];
				}
			}
		} else {
			for ( $i = 1; $i <= $max; $i++ ) {
				if ( ! isset( $texts[ $i - 1 ] ) || '' === $texts[ $i - 1 ] ) {
					/* translators: %s: position on the rating scale. */
					$texts[ $i - 1 ] = sprintf( _n( '%s Star', '%s Stars', $i, 'wp-postratings' ), number_format_i18n( $i ) );
				}

				if ( ! isset( $values[ $i - 1 ] ) || 0 === $values[ $i - 1 ] ) {
					$values[ $i - 1 ] = $i;
				}
			}

			$texts  = array_slice( $texts, 0, $max );
			$values = array_slice( $values, 0, $max );
		}

		self::render_rating_fields(
			$max,
			$image,
			$texts,
			$values,
			array_slice( $colors, 0, $max ),
			array_slice( $colors_off, 0, $max )
		);

		// wp_die() rather than exit: an AJAX handler ending in a bare exit is
		// untestable, because it takes the test runner with it. With no message
		// the AJAX die handler emits nothing extra.
		wp_die();
	}
}
