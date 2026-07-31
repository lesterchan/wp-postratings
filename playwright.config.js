/**
 * Playwright configuration for the WP-PostRatings end-to-end suite.
 *
 * The suite drives a real browser against the wp-env "tests" environment --
 * this plugin's own, on its own port, so a clean checkout of this repository
 * alone is enough to run it. Every plugin in the collection has its own pair of
 * ports for the same reason, and E2E uses the tests environment rather than the
 * development one because it creates posts and changes settings.
 *
 * These tests exist because the PHPUnit suite could not see the bugs that
 * actually reached the screen. It passed while the settings script was not
 * enqueued, while the bulk nonce was printed twice, and while the shape picker
 * drew a pair of thumbs in a star scale's orange -- each time because the test
 * called a function directly instead of pressing the thing a person presses.
 */

const path = require( 'path' );
const { defineConfig, devices } = require( '@playwright/test' );

// The tests port from .wp-env.json. Overridable, so CI or a second checkout can
// point somewhere else without editing the file.
const baseURL = process.env.WP_BASE_URL || 'http://localhost:8911';

const artifactsPath = process.env.WP_ARTIFACTS_PATH || path.join( __dirname, 'artifacts' );

// Where the logged-in admin session is saved by global-setup.js, so the tests
// do not each log in through the form.
process.env.WP_ARTIFACTS_PATH = artifactsPath;
process.env.STORAGE_STATE_PATH =
	process.env.STORAGE_STATE_PATH || path.join( artifactsPath, 'storage-states/admin.json' );

module.exports = defineConfig( {
	testDir: path.join( __dirname, 'tests/e2e' ),
	globalSetup: require.resolve( './tests/e2e/global-setup.js' ),
	outputDir: path.join( artifactsPath, 'test-results' ),

	// A failing E2E test is far more often a real bug than a flake, so a retry
	// locally would hide the thing the suite exists to catch. CI retries once,
	// because a container that has just started is a different kind of flaky.
	retries: process.env.CI ? 1 : 0,
	forbidOnly: !! process.env.CI,

	// One worker. The tests share one WordPress install and change its
	// settings; running two at once means one test's saved shape decides
	// another's assertions.
	workers: 1,
	fullyParallel: false,

	timeout: 60_000,
	expect: { timeout: 10_000 },

	reporter: process.env.CI ? [ [ 'github' ], [ 'list' ] ] : [ [ 'list' ] ],

	use: {
		baseURL,
		storageState: process.env.STORAGE_STATE_PATH,
		// Kept only for a failure: a passing run leaves nothing behind.
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'off',
	},

	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
