<?php
/**
 * The checks every one of the nineteen plugins carries.
 *
 * They are about the things a release gets wrong quietly: a readme header that
 * lost its line breaks and renders as one run-on paragraph on wordpress.org, a
 * version that agrees with itself in two places out of three, an option row
 * that outlives the plugin that made it. None of them need a browser and none
 * of them need judgement, so they belong in the suite rather than in a
 * pre-release checklist nobody reads.
 *
 * @package WP-PostRatings
 */

/**
 * Plugin metadata, layout and the option rows.
 */
class WP_PostRatings_Metadata_Test extends WP_PostRatings_TestCase {

	/**
	 * The nine README header fields, in order.
	 *
	 * @var array
	 */
	private static $header_fields = array(
		'Contributors:',
		'Donate link:',
		'Tags:',
		'Requires at least:',
		'Tested up to:',
		'Stable tag:',
		'Requires PHP:',
		'License:',
		'License URI:',
	);

	/**
	 * The h2 headings a README may carry, in the order they must appear.
	 *
	 * @var array
	 */
	private static $sections = array(
		'## Description',
		'## Usage',
		'## Frequently Asked Questions',
		'## Screenshots',
		'## Changelog',
		'## Upgrade Notice',
	);

	/**
	 * The README, as shipped.
	 *
	 * @return string
	 */
	private function readme() {
		return (string) file_get_contents( dirname( __DIR__ ) . '/README.md' );
	}

	/**
	 * The main plugin file, as shipped.
	 *
	 * @return string
	 */
	private function plugin_file() {
		return (string) file_get_contents( dirname( __DIR__ ) . '/wp-postratings.php' );
	}

	/**
	 * One value from the README header.
	 *
	 * @param string $field Field name, including the colon.
	 *
	 * @return string
	 */
	private function readme_field( $field ) {
		preg_match( '/^' . preg_quote( $field, '/' ) . '\s*(.+?)\s*$/m', $this->readme(), $matches );

		return isset( $matches[1] ) ? $matches[1] : '';
	}

	/**
	 * One value from the plugin file header.
	 *
	 * @param string $field Field name, including the colon.
	 *
	 * @return string
	 */
	private function header_field( $field ) {
		preg_match( '/^\s*\*\s*' . preg_quote( $field, '/' ) . '\s*(.+?)\s*$/m', $this->plugin_file(), $matches );

		return isset( $matches[1] ) ? $matches[1] : '';
	}

	// --- the README header ------------------------------------------------

	/**
	 * Eight of the nine header lines end in two spaces, and the ninth does not.
	 *
	 * Markdown turns a line ending in two spaces into a line break. Without
	 * them wordpress.org renders the whole header as one paragraph, which is
	 * the single most common thing to get wrong in a plugin readme.
	 *
	 * @return void
	 */
	public function test_every_readme_header_line_keeps_its_line_break() {
		$lines  = explode( "\n", $this->readme() );
		$header = array();

		foreach ( array_slice( $lines, 1 ) as $line ) {
			if ( '' === trim( $line ) ) {
				break;
			}

			$header[] = $line;
		}

		$this->assertCount( 9, $header, 'the README header should be exactly nine fields' );

		foreach ( self::$header_fields as $index => $field ) {
			$this->assertStringStartsWith( $field, trim( $header[ $index ] ), 'header field ' . ( $index + 1 ) . ' is out of order' );
		}

		foreach ( array_slice( $header, 0, 8 ) as $line ) {
			$this->assertStringEndsWith( '  ', $line, trim( $line ) . ' has lost its two trailing spaces' );
		}

		$last = $header[8];
		$this->assertSame( rtrim( $last ), $last, 'the last header line must not have trailing spaces' );
	}

	/**
	 * Every URL in the metadata points at lesterchan.net or the GNU licence.
	 *
	 * @return void
	 */
	public function test_canonical_lesterchan_urls() {
		$this->assertSame( 'https://lesterchan.net/portfolio/programming/php/', $this->header_field( 'Plugin URI:' ) );
		$this->assertSame( 'https://lesterchan.net', $this->header_field( 'Author URI:' ) );
		$this->assertSame( 'https://lesterchan.net/site/donation/', $this->readme_field( 'Donate link:' ) );
		$this->assertSame( 'https://www.gnu.org/licenses/gpl-2.0.html', $this->readme_field( 'License URI:' ) );
		$this->assertSame( 'https://www.gnu.org/licenses/gpl-2.0.html', $this->header_field( 'License URI:' ) );
	}

	/**
	 * The Contributors field names one person and no one else.
	 *
	 * @return void
	 */
	public function test_contributors_is_gamerz_only() {
		$this->assertSame( 'GamerZ', $this->readme_field( 'Contributors:' ) );
	}

	/**
	 * The text domain is the slug, and the translations live in /languages.
	 *
	 * @return void
	 */
	public function test_text_domain_is_the_plugin_slug() {
		$this->assertSame( 'wp-postratings', $this->header_field( 'Text Domain:' ) );
		$this->assertSame( '/languages', $this->header_field( 'Domain Path:' ) );
		$this->assertSame( 'wp-postratings', WP_POSTRATINGS_SLUG );
	}

	/**
	 * The version agrees in the header, the README and the constant.
	 *
	 * @return void
	 */
	public function test_version_matches_everywhere() {
		$this->assertSame( WP_POSTRATINGS_VERSION, $this->header_field( 'Version:' ) );
		$this->assertSame( WP_POSTRATINGS_VERSION, $this->readme_field( 'Stable tag:' ) );
	}

	/**
	 * The supported floors agree between the header and the README.
	 *
	 * @return void
	 */
	public function test_requires_headers_match_readme() {
		$this->assertSame( '6.8', $this->header_field( 'Requires at least:' ) );
		$this->assertSame( '6.8', $this->readme_field( 'Requires at least:' ) );
		$this->assertSame( '8.2', $this->header_field( 'Requires PHP:' ) );
		$this->assertSame( '8.2', $this->readme_field( 'Requires PHP:' ) );
	}

	// --- the README body --------------------------------------------------

	/**
	 * The level two headings are the canonical set, in the canonical order.
	 *
	 * Level three headings are free; this is only about the h2s.
	 *
	 * @return void
	 */
	public function test_readme_sections_are_the_canonical_set() {
		preg_match_all( '/^## .+$/m', $this->readme(), $matches );

		$found = array_map( 'rtrim', $matches[0] );

		foreach ( $found as $heading ) {
			$this->assertContains( $heading, self::$sections, $heading . ' is not one of the canonical headings' );
		}

		$expected = array_values(
			array_filter(
				self::$sections,
				static function ( $section ) use ( $found ) {
					return in_array( $section, $found, true );
				}
			)
		);

		$this->assertSame( $expected, $found, 'the sections are out of order' );

		foreach ( array( '## Description', '## Changelog', '## Upgrade Notice' ) as $required ) {
			$this->assertContains( $required, $found, $required . ' is missing' );
		}
	}

	/**
	 * Every changelog bullet opens with one of the five allowed prefixes.
	 *
	 * @return void
	 */
	public function test_changelog_prefixes_are_canonical() {
		$readme    = $this->readme();
		$changelog = substr( $readme, strpos( $readme, '## Changelog' ) );
		$end       = strpos( $changelog, '## Upgrade Notice' );
		$changelog = false === $end ? $changelog : substr( $changelog, 0, $end );

		preg_match_all( '/^\* (.*)$/m', $changelog, $matches );

		$this->assertNotEmpty( $matches[1], 'the changelog has no entries at all' );

		foreach ( $matches[1] as $entry ) {
			$this->assertMatchesRegularExpression(
				'/^(BREAKING|NEW|CHANGED|FIXED|NOTE): /',
				$entry,
				'changelog entry does not start with an allowed prefix: ' . $entry
			);
		}
	}

	// --- what ships -------------------------------------------------------

	/**
	 * No script the plugin registers depends on jQuery.
	 *
	 * @return void
	 */
	public function test_no_jquery_is_enqueued() {
		WP_PostRatings::get_instance()->scripts();
		WP_PostRatings_Admin::scripts( 'toplevel_page_' . WP_PostRatings_Admin::PAGE );

		$scripts = wp_scripts();

		foreach ( array( 'wp-postratings', 'wp-postratings-admin' ) as $handle ) {
			$this->assertArrayHasKey( $handle, $scripts->registered, $handle . ' was not registered' );
			$this->assertNotContains( 'jquery', $scripts->registered[ $handle ]->deps, $handle . ' still depends on jQuery' );
		}

		foreach ( glob( dirname( __DIR__ ) . '/js/*.js' ) as $file ) {
			$this->assertDoesNotMatchRegularExpression( '/\bjQuery\b|\$\(/', (string) file_get_contents( $file ), basename( $file ) . ' uses jQuery' );
		}
	}

	/**
	 * Every shipped directory carries a silence-is-golden index.php.
	 *
	 * @return void
	 */
	public function test_every_directory_has_an_index_php() {
		$root = dirname( __DIR__ );

		$directories = array( '', '/bin', '/css', '/includes', '/js', '/tests', '/tests/js' );

		foreach ( $directories as $directory ) {
			$this->assertFileExists( $root . $directory . '/index.php', ( '' === $directory ? '/' : $directory ) . ' has no index.php' );
		}
	}

	/**
	 * The plugin ships no RTL stylesheet and registers no rtl style data.
	 *
	 * A second sheet is a second thing to keep in step, and it only ever
	 * existed because the first one used physical properties.
	 *
	 * @return void
	 */
	public function test_no_rtl_stylesheet_is_registered() {
		$this->assertEmpty( glob( dirname( __DIR__ ) . '/css/*-rtl.css' ), 'an RTL stylesheet is still shipped' );

		WP_PostRatings::get_instance()->scripts();

		$this->assertFalse( wp_styles()->get_data( 'wp-postratings', 'rtl' ), 'the stylesheet still declares rtl data' );

		$css = (string) file_get_contents( dirname( __DIR__ ) . '/css/wp-postratings.css' );

		foreach ( array( 'margin-left', 'margin-right', 'padding-left', 'padding-right', 'text-align: left', 'text-align: right', 'border-left', 'border-right' ) as $physical ) {
			$this->assertStringNotContainsString( $physical, $css, $physical . ' is a physical property; use its logical equivalent' );
		}
	}

	// --- the option rows --------------------------------------------------

	/**
	 * Uninstalling leaves no row of this plugin's behind.
	 *
	 * Runs on a network too, where uninstall.php loops over every site and the
	 * single-site path is never taken.
	 *
	 * @return void
	 */
	public function test_uninstall_removes_every_option_row() {
		global $wpdb;

		WP_PostRatings_Options::update( WP_PostRatings_Options::defaults() );
		WP_PostRatings_Options::update_markers();

		if ( is_multisite() ) {
			$second = self::factory()->blog->create();

			switch_to_blog( $second );
			WP_PostRatings_Install::install();
			restore_current_blog();
		}

		$this->run_uninstall();

		$surviving = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'wp\\_postratings\\_%'" );

		$this->assertSame( array(), $surviving, 'uninstall left rows behind: ' . implode( ', ', (array) $surviving ) );

		if ( is_multisite() ) {
			switch_to_blog( $second );
			$surviving = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'wp\\_postratings\\_%'" );
			restore_current_blog();

			$this->assertSame( array(), $surviving, 'uninstall stopped at the first site of the network' );
		}

		$this->restore_after_uninstall();
	}

	/**
	 * Run uninstall.php the way WordPress does.
	 *
	 * @return void
	 */
	private function run_uninstall() {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wp-postratings/wp-postratings.php' );
		}

		require dirname( __DIR__ ) . '/uninstall.php';
	}

	/**
	 * Put the table and the capability back for whatever runs next.
	 *
	 * Deliberately NOT called from run_uninstall(). install() writes the option
	 * rows as well as creating the table, so restoring inside the helper put
	 * wp_postratings_options and wp_postratings_version straight back and the
	 * caller then asserted that uninstall had left them behind. Restore after
	 * asserting, never before.
	 *
	 * @return void
	 */
	private function restore_after_uninstall() {
		WP_PostRatings_Install::install();
	}

	/**
	 * The marker row holds exactly 'plugin' and 'db'.
	 *
	 * @return void
	 */
	public function test_version_row_holds_exactly_plugin_and_db() {
		WP_PostRatings_Options::update_markers();

		$markers = get_option( WP_PostRatings_Options::VERSION );

		$this->assertIsArray( $markers, 'the marker row should be an array' );
		$this->assertSame( array( 'plugin', 'db' ), array_keys( $markers ), 'the marker row carries exactly two keys' );
		$this->assertSame( WP_POSTRATINGS_VERSION, $markers['plugin'] );
		$this->assertSame( WP_POSTRATINGS_DB_VERSION, $markers['db'] );
	}

	/**
	 * The sanitizer never writes an upgrade marker into the settings row.
	 *
	 * This is the regression guard for the bug wp-useronline shipped: with the
	 * markers inside the settings array, every save had to rescue them by hand,
	 * and the one that forgot made the upgrade re-run on every request. It
	 * fails the moment somebody moves a marker back in.
	 *
	 * @return void
	 */
	public function test_settings_sanitizer_never_stores_version_markers() {
		$clean = WP_PostRatings_Options::sanitize(
			array(
				'max'        => 5,
				'version'    => '9.9.9',
				'db_version' => '99',
				'versions'   => array( 'plugin' => '9.9.9' ),
				'ip_header'  => 'HTTP_X_FORWARDED_FOR',
			)
		);

		foreach ( array( 'version', 'db_version', 'versions' ) as $key ) {
			$this->assertArrayNotHasKey( $key, $clean, $key . ' reached the settings row' );
		}

		WP_PostRatings_Options::update( $clean );

		$this->assertSame( array( 'plugin', 'db' ), array_keys( (array) get_option( WP_PostRatings_Options::VERSION ) ) );
	}
}
