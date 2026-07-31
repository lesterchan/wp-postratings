/**
 * Shared steps for the WP-PostRatings end-to-end suite.
 *
 * Everything here drives the screen rather than the database. A helper that
 * wrote the option row directly would skip the sanitizer and the settings form,
 * which are two of the three places these tests exist to watch -- and it was a
 * unit test doing exactly that, asserting on a renderer nothing called, that let
 * the settings preview stay orange through a green suite.
 */

const { expect } = require( '@wordpress/e2e-test-utils-playwright' );

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
	openSettings,
	resetTemplates,
	saveSettings,
	swatches,
	uniqueTitle,
};
