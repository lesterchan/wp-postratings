/**
 * Shared steps for the WP-PostRatings end-to-end suite.
 *
 * Everything here drives the screen rather than the database. A helper that
 * wrote the option row directly would skip the sanitizer and the settings form,
 * which are two of the three places these tests exist to watch -- and it was a
 * unit test doing exactly that, asserting on a renderer nothing called, that let
 * the settings preview stay orange through a green suite.
 *
 * The migration helpers at the bottom are the one exception, and §7.5 draws the
 * line where they sit: data a person could *not* type -- fifteen unprefixed rows
 * as the released 1.91.2 left them -- has no screen to go in through, because
 * the screen that wrote them has not existed since. Those go straight into
 * storage. Everything a person could type still goes through the form.
 */

const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const { expect } = require( '@wordpress/e2e-test-utils-playwright' );

/** The plugin root, which is where wp-env reads .wp-env.json from. */
const PLUGIN_ROOT = path.join( __dirname, '../..' );

const SETTINGS_URL = '/wp-admin/admin.php?page=wp-postratings-settings';
const TEMPLATES_URL = `${ SETTINGS_URL }&tab=templates`;
const LOGS_URL = '/wp-admin/admin.php?page=wp-postratings';

/** The built-in colours, from WP_PostRatings_Options. */
const COLORS = {
	rated: '#f5a623',
	unrated: '#d4d4d8',
	up: '#2e9e4f',
	down: '#e5484d',
};

/** Values the "Who Is Allowed To Rate?" select stores. */
const ALLOW = {
	guestsOnly: '0',
	loggedInOnly: '1',
	everyone: '2',
};

/** Values the "Check For Repeat Votes" select stores. */
const CHECK = {
	never: '0',
	cookie: '1',
	ip: '2',
	cookieAndIp: '3',
	username: '4',
};

/**
 * Open the settings screen.
 *
 * @param {import('@playwright/test').Page} page Page under test.
 * @param {string}                          tab  Which tab to open.
 * @return {Promise<void>} Resolves once the screen is up.
 */
async function openSettings( page, tab = 'settings' ) {
	await page.goto( 'templates' === tab ? TEMPLATES_URL : SETTINGS_URL );

	await expect( page.getByRole( 'heading', { name: 'Ratings Settings' } ) ).toBeVisible();
}

/**
 * Save the settings form and wait for WordPress to confirm it.
 *
 * @param {import('@playwright/test').Page} page Page under test.
 * @return {Promise<void>} Resolves once "Settings saved." is on screen.
 */
async function saveSettings( page ) {
	await page.getByRole( 'button', { name: 'Save Changes' } ).click();

	// The notice, not just the redirect: options.php sends the browser back
	// whether or not anything was written, and this screen showed nothing at all
	// until settings_errors() was called unscoped.
	await expect( page.locator( '.settings-error, .notice-success' ).first() ).toContainText(
		'Settings saved.',
	);
}

/**
 * Choose a rating type.
 *
 * The row count is asserted only when the caller says so, because selecting the
 * type that is already selected rebuilds nothing: the screen listens for a
 * change, and there has not been one. A helper that assumed otherwise spent ten
 * seconds waiting for a table to become a length it had no reason to become,
 * which is what every timeout in the first full run of this suite was.
 *
 * @param {import('@playwright/test').Page} page  Page under test.
 * @param {string}                          type  Either 'scale' or 'updown'.
 * @param {number}                          steps Rows to wait for, if the type is changing.
 * @return {Promise<void>} Resolves once the table has settled.
 */
async function chooseType( page, type, steps = 0 ) {
	await page.locator( `.wp-postratings-rating-type[value="${ type }"]` ).check();

	if ( steps ) {
		await expect( page.locator( '#wp-postratings-rating-fields tbody tr' ) ).toHaveCount(
			steps,
		);
	}
}

/**
 * Choose a shape.
 *
 * The type has to be chosen first: a shape row is hidden unless its type is the
 * selected one, so a shape of the other type is deliberately out of reach.
 *
 * @param {import('@playwright/test').Page} page  Page under test.
 * @param {string}                          shape Shape name.
 * @param {number}                          steps Rows the table should end up with.
 * @return {Promise<void>} Resolves once the table has settled.
 */
async function chooseShape( page, shape, steps ) {
	// click(), not check(): a click on the shape already chosen still resets the
	// table, and check() on an already-checked radio does nothing at all. This is
	// how a test gets back to a known length whatever the last one left behind.
	await page.locator( `.wp-postratings-shape-choice[value="${ shape }"]` ).click();

	await expect( page.locator( '#wp-postratings-spinner' ) ).not.toHaveClass( /is-active/ );

	if ( steps ) {
		await expect( page.locator( '#wp-postratings-rating-fields tbody tr' ) ).toHaveCount(
			steps,
		);
	}
}

/**
 * Put the plugin into a known rating and save it.
 *
 * @param {import('@playwright/test').Page} page              Page under test.
 * @param {Object}                          settings          What to select.
 * @param {string}                          [settings.type]   'scale' or 'updown'.
 * @param {string}                          [settings.shape]  Shape name.
 * @param {number}                          [settings.steps]  Expected row count.
 * @param {string}                          [settings.allow]  A value from ALLOW.
 * @param {string}                          [settings.check]  A value from CHECK.
 * @param {string}                          [settings.schema] A schema.org type, or ''.
 * @return {Promise<void>} Resolves once the settings are saved.
 */
async function configure( page, settings = {} ) {
	const {
		type = 'scale',
		shape = 'star',
		steps = 'updown' === type ? 2 : 5,
		allow = ALLOW.everyone,
		check = CHECK.never,
		schema = '',
	} = settings;

	await openSettings( page );

	await chooseType( page, type );
	await chooseShape( page, shape, steps );

	await page.locator( '#wp_postratings_allowtorate' ).selectOption( allow );
	await page.locator( '#wp_postratings_check_method' ).selectOption( check );
	await page.locator( '#wp_postratings_schema_type' ).selectOption( schema );

	await saveSettings( page );
}

/**
 * Put every template back to the scale defaults.
 *
 * A test that edits a template has to undo it. The templates are what the front
 * end prints around the rating, so one left holding "KEEP ME" makes every later
 * assertion about a post's score a test of the Templates tab instead -- which is
 * exactly what happened, and it read as the vote not registering at all.
 *
 * @param {import('@playwright/test').Page} page Page under test.
 * @return {Promise<void>} Resolves once the defaults are saved.
 */
async function resetTemplates( page ) {
	await openSettings( page, 'templates' );

	const buttons = page.locator( '.wp-postratings-restore-template[data-variant="scale"]' );
	const count = await buttons.count();

	for ( let i = 0; i < count; i++ ) {
		await buttons.nth( i ).click();
	}

	await saveSettings( page );
}

/**
 * Run PHP inside the tests environment and hand back what it printed.
 *
 * The code is base64'd rather than passed as itself: a stored template holding
 * quotes and angle brackets is exactly the sort of string that arrives at the
 * other end subtly different, and a fixture that is not the payload byte for
 * byte proves nothing about what the migration did to it.
 *
 * @param {string} code PHP to evaluate, without an opening tag.
 * @return {string} Whatever the code echoed between its markers.
 */
function wpEval( code ) {
	const encoded = Buffer.from( code, 'utf8' ).toString( 'base64' );

	const output = execFileSync(
		'npx',
		[
			'--yes',
			'@wordpress/env',
			'run',
			'tests-cli',
			'wp',
			'eval',
			`eval( base64_decode( '${ encoded }' ) );`,
		],
		{ cwd: PLUGIN_ROOT, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] },
	);

	// wp-env prints its own progress around the command's output, so the code
	// wraps what it wants to return in markers rather than the caller trying to
	// tell the two apart by position.
	const matched = output.match( /<<<([\s\S]*?)>>>/ );

	return matched ? matched[ 1 ] : '';
}

/**
 * Run PHP and read back a JSON value, so types survive the round trip.
 *
 * @param {string} expression PHP expression to encode and return.
 * @return {*} The decoded value.
 */
function wpEvalJson( expression ) {
	return JSON.parse( wpEval( `echo '<<<' . wp_json_encode( ${ expression } ) . '>>>';` ) );
}

/**
 * The settings row as the database holds it, with no defaults merged in.
 *
 * WP_PostRatings_Options::get() merges over the defaults, so it answers
 * identically for a row holding the defaults and for no row at all -- which is
 * what a migration that read, deleted and never wrote leaves behind, and the
 * whole of §7.6.1. Ask the database when the question is "was it written".
 *
 * @return {Object|false} The stored array, or false when there is no row.
 */
function rawOptions() {
	return wpEvalJson( 'get_option( WP_PostRatings_Options::OPTION )' );
}

/**
 * The defaults the running code would fall back to.
 *
 * @return {Object} The default settings.
 */
function defaultOptions() {
	return wpEvalJson( 'WP_PostRatings_Options::defaults()' );
}

/**
 * The upgrade markers, as the database holds them.
 *
 * @return {Object|false} The stored array, or false when there is no row.
 */
function versionRow() {
	return wpEvalJson( 'get_option( WP_PostRatings_Options::VERSION )' );
}

/**
 * The version numbers the running code expects to find stamped.
 *
 * @return {{plugin: string, db: string}} The two markers.
 */
function runningVersions() {
	return wpEvalJson(
		`array(
			'plugin' => WP_POSTRATINGS_VERSION,
			'db'     => WP_POSTRATINGS_DB_VERSION,
		)`,
	);
}

/**
 * The PHP expression naming every pre-2.0.0 row the migration deletes.
 *
 * The plugin's own list, minus the four rows that are not legacy at all, plus
 * the two WP-Stats rows by name. Those two are *deliberately* absent from
 * all_option_names(): §13.2 keeps them off the uninstall list because up to five
 * siblings that have not upgraded are still reading them, while the migration
 * still has to delete them once it has folded them in. Naming them here rather
 * than deriving them is the same distinction the plugin draws.
 *
 * @type {string}
 */
const LEGACY_ROW_NAMES_PHP = `array_values( array_merge(
	array_diff(
		WP_PostRatings_Options::all_option_names(),
		array(
			WP_PostRatings_Options::OPTION,
			WP_PostRatings_Options::VERSION,
			'widget_ratings',
			'widget_ratings-widget',
			'widget_ratings_highest_rated',
			'widget_ratings_most_rated',
		)
	),
	array( 'stats_display', 'stats_mostlimit' )
) )`;

/**
 * Every legacy row name, as the running code lists them.
 *
 * @return {string[]} Row names.
 */
function legacyRowNames() {
	return wpEvalJson( LEGACY_ROW_NAMES_PHP );
}

/**
 * Put the install back into the shape a pre-2.0.0 site is in.
 *
 * The prefixed rows go away and whichever unprefixed ones the caller names take
 * their place: fifteen postratings_* rows, plus the two WP-Stats rows this
 * plugin shared with five siblings.
 *
 * **It hands back what it can see, and that is not a convenience.**
 * maybe_upgrade() is hooked to `init`, which a WP-CLI request reaches like any
 * other. So the moment this call ends, the next `wp eval` boots WordPress with
 * the markers missing and performs the upgrade itself, before running a line of
 * the code it was given -- and a test that read the rows back through another
 * helper would be asserting on WP-CLI's run rather than on the browser's, with
 * nothing left for the browser to do.
 *
 * @param {Object} rows Legacy option name => value, stored exactly as given.
 * @return {{legacy: string[], options: *, version: *}} The state as just seeded.
 */
function installLegacyRows( rows ) {
	const encoded = Buffer.from( JSON.stringify( rows ), 'utf8' ).toString( 'base64' );

	return JSON.parse(
		wpEval(
			`delete_option( WP_PostRatings_Options::OPTION );
			delete_option( WP_PostRatings_Options::VERSION );
			foreach ( ${ LEGACY_ROW_NAMES_PHP } as $row ) {
				delete_option( $row );
			}
			foreach ( json_decode( base64_decode( '${ encoded }' ), true ) as $name => $value ) {
				update_option( $name, $value );
			}

			$alive = array();
			foreach ( ${ LEGACY_ROW_NAMES_PHP } as $row ) {
				if ( false !== get_option( $row, false ) ) {
					$alive[] = $row;
				}
			}

			echo '<<<' . wp_json_encode( array(
				'legacy'  => array_values( $alive ),
				'options' => get_option( WP_PostRatings_Options::OPTION ),
				'version' => get_option( WP_PostRatings_Options::VERSION ),
			) ) . '>>>';`,
		),
	);
}

/**
 * Which pre-2.0.0 rows are still in the database.
 *
 * @return {string[]} The legacy rows that survive.
 */
function survivingLegacyRows() {
	return wpEvalJson(
		`array_values( array_filter(
			${ LEGACY_ROW_NAMES_PHP },
			static function ( $name ) {
				return false !== get_option( $name, false );
			}
		) )`,
	);
}

/**
 * Put the install back to a current one: markers stamped, settings shipped.
 *
 * @return {void}
 */
function resetPlugin() {
	wpEval(
		`foreach ( ${ LEGACY_ROW_NAMES_PHP } as $row ) {
			delete_option( $row );
		}
		WP_PostRatings_Options::update( WP_PostRatings_Options::defaults() );
		update_option( WP_PostRatings_Options::VERSION, array(
			'plugin' => WP_POSTRATINGS_VERSION,
			'db'     => WP_POSTRATINGS_DB_VERSION,
		) );
		echo '<<<done>>>';`,
	);
}

/**
 * The value of every swatch in one of the two colour columns, in row order.
 *
 * @param {import('@playwright/test').Page} page     Page under test.
 * @param {string}                          property Custom property the column feeds.
 * @return {Promise<string[]>} Colours as the inputs hold them.
 */
function swatches( page, property = '--wp-postratings-color-on' ) {
	return page
		.locator( `.wp-postratings-color[data-property="${ property }"]` )
		.evaluateAll( ( inputs ) => inputs.map( ( input ) => input.value ) );
}

/**
 * How much of each step of a vote control the browser has actually filled.
 *
 * The assertion PHP cannot make. The score is painted into each glyph by a
 * gradient whose stop is a custom property, so PHPUnit can only prove the
 * property was written; whether it turns into paint on the right glyph depends
 * on the stylesheet and on the theme around it -- and the first version of this
 * feature laid one strip over the whole row instead, which passed every unit
 * test and was visibly wrong, because a theme putting padding on a label moves
 * that label's glyph without moving anything the strip could see.
 *
 * Keyed by step rather than taken in document order: the row is laid out
 * reversed so a sibling combinator can reach backwards, so the fifth step comes
 * first in the markup.
 *
 * @param {import('@playwright/test').Page} page Page showing the control.
 * @return {Promise<Object>} Step number to filled percentage.
 */
async function stepFills( page ) {
	const scale = page.locator( '.wp-postratings-scale' ).first();

	await expect( scale ).toBeVisible( { timeout: 15_000 } );

	return scale.evaluate( ( el ) => {
		const fills = {};

		el.querySelectorAll( 'label[for]' ).forEach( ( label ) => {
			const step = Number( label.getAttribute( 'for' ).split( '-' ).pop() );
			const item = label.querySelector( '.wp-postratings-item' );

			if ( ! item ) {
				fills[ step ] = 'no glyph';
				return;
			}

			// The gradient's two stops sit at the same percentage -- one colour up
			// to it, the other from it -- so the first is the fill.
			const painted = getComputedStyle( item ).backgroundImage;
			const stop = painted.match( /([0-9.]+)%/ );

			fills[ step ] = stop ? Number( stop[ 1 ] ) : `unpainted:${ painted }`;
		} );

		return fills;
	} );
}

/**
 * A colour from COLORS, as the browser reports computed colours.
 *
 * @param {string} hex A six-digit hex colour.
 * @return {string} The same colour as an rgb() string.
 */
function rgb( hex ) {
	const [ , r, g, b ] = hex.match( /^#(..)(..)(..)$/ );

	return `rgb(${ parseInt( r, 16 ) }, ${ parseInt( g, 16 ) }, ${ parseInt( b, 16 ) })`;
}

/**
 * A post title no earlier run can have used.
 *
 * Deleting the posts between tests does not delete the log, because a log row is
 * a record of a vote and outlives the post it was cast on -- that is the point
 * of it. So two runs of the same test leave two rows saying the same thing, and
 * an assertion counting them fails the second time for no reason worth chasing.
 *
 * @param {string} base What the title should say.
 * @return {string} That, plus enough to tell this run from the last.
 */
function uniqueTitle( base ) {
	return `${ base } ${ Date.now().toString( 36 ) }`;
}

/**
 * Publish a post carrying the rating shortcode.
 *
 * @param {Object} requestUtils The e2e-test-utils request helper.
 * @param {string} title        Post title.
 * @return {Promise<Object>} The created post.
 */
function createRatedPost( requestUtils, title ) {
	return requestUtils.createPost( {
		title,
		content: '[ratings]',
		status: 'publish',
	} );
}

module.exports = {
	ALLOW,
	CHECK,
	COLORS,
	LOGS_URL,
	SETTINGS_URL,
	TEMPLATES_URL,
	chooseShape,
	chooseType,
	configure,
	createRatedPost,
	defaultOptions,
	installLegacyRows,
	legacyRowNames,
	openSettings,
	rawOptions,
	resetPlugin,
	resetTemplates,
	rgb,
	runningVersions,
	saveSettings,
	stepFills,
	survivingLegacyRows,
	swatches,
	uniqueTitle,
	versionRow,
	wpEval,
	wpEvalJson,
};
