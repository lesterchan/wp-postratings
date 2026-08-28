/**
 * The pre-2.0.0 migration, run the way a real site runs it.
 *
 * Activation does not fire when a plugin is merely updated -- a site that
 * updates from the Plugins screen never calls activate() -- so maybe_upgrade()
 * hangs off `init` instead, which every request goes through. Loading a page in
 * a browser is the only way to reach it.
 *
 * Fifteen unprefixed rows fold into one here, two markers collapse into one row,
 * and two settings come out of rows this plugin shared with five siblings. What
 * a browser adds over tests/test-upgrade.php is the far end: the shape a site
 * chose in 2015 is a folder name that no longer exists, and whether the
 * migration turned it into a shape the settings screen can draw -- and the front
 * end can render -- is a question about a page rather than about an array.
 *
 * Every row is read *raw*. WP_PostRatings_Options::get() merges over the
 * defaults, so it answers identically for a row holding the defaults and for no
 * row at all -- the §7.6.1 failure exactly. Ask the database, not the plugin.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	SETTINGS_URL,
	defaultOptions,
	installLegacyRows,
	legacyRowNames,
	rawOptions,
	resetPlugin,
	runningVersions,
	survivingLegacyRows,
	versionRow,
	wpEval,
} = require( './helpers.js' );

/** The Dashboard: an ordinary admin request, which is what an update goes through. */
const DASHBOARD_URL = '/wp-admin/index.php';

/**
 * The rows a 1.91.2 install carries, in the shapes it wrote them.
 *
 * One of each kind the migration handles differently: a flat rename, the two
 * parallel arrays that make up a scale, a template stored slashed, the two
 * markers that collapse into one row, and both shared WP-Stats rows.
 *
 * @param {Object} overrides Anything this particular site had changed.
 * @return {Object} Legacy option name => value.
 */
function legacyInstall( overrides = {} ) {
	return {
		postratings_image: 'stars_crystal',
		postratings_max: 5,
		postratings_customrating: 0,
		postratings_allowtorate: 1,
		postratings_logging_method: 2,
		postratings_ratingstext: [ 'Awful', 'Poor', 'Fair', 'Good', 'Excellent' ],
		postratings_ratingsvalue: [ 1, 2, 3, 4, 5 ],
		postratings_template_vote: "%RATINGS_IMAGES_VOTE% <em>It\\'s your turn</em>",
		postratings_db_version: '1.6',
		postratings_options_version: '1.5',
		...overrides,
	};
}

test.describe( 'The pre-2.0.0 upgrade', () => {
	test.afterEach( async () => {
		// Back to a current install: markers stamped, settings shipped, no
		// legacy rows anywhere. Every other spec in this suite starts from that,
		// and this is the only file that takes it apart.
		resetPlugin();
	} );

	test( 'the scattered rows fold into one, every old row goes, and the markers are stamped', async ( {
		page,
	} ) => {
		// The fixture is asserted from what the seeding call itself saw, not
		// from a second one. maybe_upgrade() runs on `init`, which a WP-CLI
		// request reaches too -- ask again through another `wp eval` and the
		// rows have already moved, the browser request below has nothing left to
		// do, and the test quietly becomes a test of WP-CLI.
		const before = installLegacyRows( legacyInstall() );

		expect( before.legacy ).toContain( 'postratings_max' );
		expect( before.options ).toBe( false );
		expect( before.version ).toBe( false );

		await page.goto( DASHBOARD_URL );

		const stored = rawOptions();

		// Written, not merely readable through the defaults.
		expect( stored ).not.toBe( false );
		expect( stored.ratings.text[ 0 ] ).toBe( 'Awful' );

		// Strings, not integers, and that is WordPress rather than this plugin:
		// a scalar option round-trips through the database as text, so what the
		// fold-in reads out of postratings_max is "5" whatever was written. The
		// rows that were arrays -- the two parallel ratings lists -- keep their
		// types, because those are serialised. Every reader casts, which is why
		// it has never mattered, and why a test asserting 5 here would be
		// asserting something untrue of every install in the world.
		expect( stored.max ).toBe( '5' );
		expect( stored.allowtorate ).toBe( '1' );

		// logging_method became check_method: the setting never chose whether to
		// log anything, only how a repeat vote is recognised.
		expect( stored.check_method ).toBe( '2' );

		// The template comes across unslashed. Up to 1.91.2 it was stored with
		// the backslashes $_POST arrived with and stripped on every read, so a
		// row corrected here is one the new code can read straight.
		expect( stored.templates.vote ).toContain( "It's your turn" );
		expect( stored.templates.vote ).not.toContain( '\\' );

		// Every old row gone rather than left to rot, read through the plugin's
		// own list -- the two shared WP-Stats rows included, because the
		// migration deletes those once it has folded them in even though
		// uninstall must not.
		expect( survivingLegacyRows() ).toEqual( [] );

		// Two markers become one row, in one write, matching the running code.
		expect( versionRow() ).toEqual( runningVersions() );
	} );

	test( 'a shape that is now a folder name resolves to one the screen can draw', async ( {
		page,
	} ) => {
		// The 16 image folders became 9 SVG shapes: stars, stars_crystal,
		// stars_dark, stars_png and stars_flat_png were all one star, and a
		// folder a site added itself has nothing left to point at. Unresolved,
		// the settings screen offers a shape it cannot render and the front end
		// shows nothing at all.
		installLegacyRows( legacyInstall( { postratings_image: 'stars_crystal' } ) );

		await page.goto( DASHBOARD_URL );

		expect( rawOptions().shape ).toBe( 'star' );

		// Present is not alive: the resolved shape has to be one the picker
		// actually offers, or the screen shows a list with nothing selected.
		await page.goto( SETTINGS_URL );

		await expect(
			page.locator( '.wp-postratings-shape-choice[value="star"]' ),
		).toBeChecked();
	} );

	test( 'a shape nobody recognises lands on stars rather than nothing', async ( { page } ) => {
		installLegacyRows( legacyInstall( { postratings_image: 'a_folder_this_site_added' } ) );

		await page.goto( DASHBOARD_URL );

		expect( rawOptions().shape ).toBe( 'star' );
	} );

	test( "this plugin's share of the WP-Stats rows is folded in and the rows deleted", async ( {
		page,
	} ) => {
		// The two rows as the last of the seven plugins to save that screen left
		// them: a flag per plugin, and a shared row saying how many entries a
		// "most" list carries. This plugin owned four of the flags and one
		// question survives, because WP-Stats collects whole sections now.
		installLegacyRows(
			legacyInstall( {
				stats_display: { ratings: 0, polls: 1 },
				stats_mostlimit: 4,
			} ),
		);

		await page.goto( DASHBOARD_URL );

		const stored = rawOptions();

		expect( stored.stats_display ).toBe( 0 );
		expect( stored.stats_most_limit ).toBe( 4 );

		// Deleted by the migration that folded them in -- and by nothing else.
		// §13.2 splits the two jobs, and this plugin is the reference for it:
		// uninstall must leave them alone, because up to five siblings that have
		// not upgraded are still reading them.
		expect( survivingLegacyRows() ).toEqual( [] );
	} );

	test( 'an absent shared row means on, not off', async ( { page } ) => {
		// A sibling upgraded first and took the row with it. Reading that as a
		// deliberate opt-out would take the ratings block off the WP-Stats page
		// of any site that updated a sibling first, with nothing to say why.
		const before = installLegacyRows( legacyInstall() );

		expect( before.legacy ).not.toContain( 'stats_display' );

		await page.goto( DASHBOARD_URL );

		expect( rawOptions().stats_display ).toBe( 1 );
	} );

	test( 'the rows the migration reads are the rows it deletes', async () => {
		// Not a behaviour test: a list test. The fold-in and the cleanup are
		// driven from the same names, so a row that is read and not deleted
		// would be migrated again on the next schema bump, and one deleted
		// without being read would take its setting with it.
		const names = legacyRowNames();

		expect( names.length ).toBeGreaterThan( 0 );
		expect( names ).toContain( 'postratings_options' );
		expect( names ).toContain( 'stats_display' );
		expect( names ).not.toContain( 'wp_postratings_options' );
	} );

	test( 'a stored permission template gains the reason variable', async ( {
		page,
	} ) => {
		// The settings row as an already-migrated 2.0.0 site holds it: the
		// defaults were written into it when that version installed, so the
		// sentence is frozen there and changing the shipped default reaches
		// nobody. This is the step that corrects the stored copy.
		//
		// Seeded and stamped in one call for the reason the fixture above gives:
		// a second `wp eval` would run the upgrade itself and leave the browser
		// request below with nothing to do.
		wpEval(
			`$options = WP_PostRatings_Options::get();
			$options['templates']['permission'] = '%RATINGS_IMAGES%<br /><em>' . __( 'You need to be a registered member to rate this.', 'wp-postratings' ) . '</em>';
			WP_PostRatings_Options::update( $options );
			update_option( WP_PostRatings_Options::VERSION, array(
				'plugin' => '2.0.0',
				'db'     => WP_POSTRATINGS_DB_VERSION,
			) );
			echo '<<<done>>>';`,
		);

		// A front-end request, not the dashboard: the upgrade runs on `init`,
		// and the whole point of it being there is that a site nobody logs into
		// still migrates.
		await page.goto( '/' );

		const template = rawOptions().templates.permission;

		expect( template ).toContain( '%RATINGS_PERMISSION%' );
		expect( template ).not.toContain( 'registered member' );
	} );

	test( 'a permission template the site reworded is left as it is', async ( {
		page,
	} ) => {
		const reworded = '%RATINGS_IMAGES%<br /><em>Sorry, no rating for you.</em>';

		wpEval(
			`$options = WP_PostRatings_Options::get();
			$options['templates']['permission'] = '${ reworded }';
			WP_PostRatings_Options::update( $options );
			update_option( WP_PostRatings_Options::VERSION, array(
				'plugin' => '2.0.0',
				'db'     => WP_POSTRATINGS_DB_VERSION,
			) );
			echo '<<<done>>>';`,
		);

		await page.goto( '/' );

		expect( rawOptions().templates.permission ).toBe( reworded );
	} );

	test( 'an install already on this version is left alone', async ( { page } ) => {
		// A legacy row that should never be read, alongside markers saying the
		// upgrade has already happened. maybe_upgrade() returning early is what
		// keeps every request from being an option write, and the proof it
		// returned early is that this row survives untouched.
		//
		// Stamped in the same call that writes the row: with the markers already
		// current, the WP-CLI request doing the writing cannot migrate it on the
		// way in, and neither can the browser.
		wpEval(
			`update_option( WP_PostRatings_Options::VERSION, array(
				'plugin' => WP_POSTRATINGS_VERSION,
				'db'     => WP_POSTRATINGS_DB_VERSION,
			) );
			update_option( 'postratings_max', 9 );
			echo '<<<done>>>';`,
		);

		await page.goto( DASHBOARD_URL );

		expect( survivingLegacyRows() ).toContain( 'postratings_max' );
		expect( String( rawOptions().max ) ).toBe( String( defaultOptions().max ) );
	} );
} );
