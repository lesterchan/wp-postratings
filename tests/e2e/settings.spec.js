/**
 * The appearance half of the settings screen, driven the way a person drives it.
 *
 * Every assertion here corresponds to a bug that shipped past a green PHPUnit
 * run, because the unit test called the renderer directly and the renderer was
 * correct on its own terms. What was wrong was what the browser did with it.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const {
	COLORS,
	chooseShape,
	chooseType,
	openSettings,
	saveSettings,
	swatches,
} = require( './helpers.js' );

test.describe( 'Ratings appearance', () => {
	test.beforeEach( async ( { page } ) => {
		await openSettings( page );
		await chooseType( page, 'scale' );
		await chooseShape( page, 'star', 5 );
		await saveSettings( page );
		await openSettings( page );
	} );

	test( 'a scale defaults to five steps in the built-in orange', async ( { page } ) => {
		await expect( page.locator( '#wp-postratings-rating-fields tbody tr' ) ).toHaveCount( 5 );

		expect( await swatches( page ) ).toEqual( Array( 5 ).fill( COLORS.rated ) );
		expect( await swatches( page, '--wp-postratings-color-off' ) ).toEqual(
			Array( 5 ).fill( COLORS.unrated ),
		);

		await expect(
			page.locator( '#wp-postratings-rating-fields input[name$="[text][]"]' ).first(),
		).toHaveValue( '1 Star' );
	} );

	test( 'choosing Up or down rebuilds the table as a signed pair in green and red', async ( {
		page,
	} ) => {
		// The reported bug in full: a five point orange scale is saved, and
		// tapping the type shows a pair of thumbs wearing the scale's orange.
		await chooseType( page, 'updown', 2 );

		const texts = page.locator( '#wp-postratings-rating-fields input[name$="[text][]"]' );
		await expect( texts.nth( 0 ) ).toHaveValue( 'Vote Down' );
		await expect( texts.nth( 1 ) ).toHaveValue( 'Vote Up' );

		const values = page.locator( '#wp-postratings-rating-fields input[name$="[value][]"]' );
		await expect( values.nth( 0 ) ).toHaveValue( '-1' );
		await expect( values.nth( 1 ) ).toHaveValue( '1' );

		expect( await swatches( page ) ).toEqual( [ COLORS.down, COLORS.up ] );
	} );

	test( 'switching back to a scale restores its own defaults', async ( { page } ) => {
		await chooseType( page, 'updown', 2 );
		await chooseType( page, 'scale', 5 );

		const texts = page.locator( '#wp-postratings-rating-fields input[name$="[text][]"]' );
		await expect( texts.nth( 0 ) ).toHaveValue( '1 Star' );

		const values = page.locator( '#wp-postratings-rating-fields input[name$="[value][]"]' );
		await expect( values.nth( 0 ) ).toHaveValue( '1' );

		// Not -1, which is what carrying the up/down pair's signed value across
		// left in the first row of every scale.
		expect( await swatches( page ) ).toEqual( Array( 5 ).fill( COLORS.rated ) );
	} );

	test( 'changing one scale shape for another keeps the length', async ( { page } ) => {
		await page.locator( '#wp-postratings-add-step' ).click();
		await expect( page.locator( '#wp-postratings-rating-fields tbody tr' ) ).toHaveCount( 6 );

		// A shape change resets the table to the type's default length, which for
		// a scale is five -- the shapes within a type are interchangeable, so this
		// is the one case where the rows are thrown away and rebuilt.
		await chooseShape( page, 'heart', 5 );

		await expect(
			page.locator( '#wp-postratings-rating-fields input[name$="[text][]"]' ).first(),
		).toHaveValue( '1 Star' );
	} );

	test( 'the shape picker previews every shape in its own built-in colours', async ( {
		page,
	} ) => {
		// Saved orange must not reach the picker: it compares shapes, so its rows
		// have to differ only by shape.
		await chooseType( page, 'updown', 2 );

		const thumbPreview = page
			.locator( '.wp-postratings-shape-row[data-rating-type="updown"]' )
			.filter( { has: page.locator( '.wp-postratings-shape-choice[value="thumb"]' ) } )
			.locator( '.wp-postratings-strip' );

		await expect( thumbPreview ).toHaveCount( 2 );

		const upColor = await thumbPreview
			.first()
			.evaluate( ( el ) => el.style.getPropertyValue( '--wp-postratings-color-on' ).trim() );

		expect( upColor ).toBe( COLORS.up );
	} );

	test( 'a scale preview carries no colour of its own, so the row decides', async ( {
		page,
	} ) => {
		// The settings table's previews take their colours from the cell around
		// them, which the script repaints as a swatch moves. A colour emitted on
		// the glyph would sit inside that cell and win.
		const preview = page
			.locator( '#wp-postratings-rating-fields .wp-postratings-strip' )
			.first();

		const inline = await preview.evaluate( ( el ) =>
			el.style.getPropertyValue( '--wp-postratings-color-on' ).trim(),
		);

		expect( inline ).toBe( '' );
	} );

	test( 'a colour follows its swatch as the picker moves', async ( { page } ) => {
		const swatch = page
			.locator( '.wp-postratings-color[data-property="--wp-postratings-color-on"]' )
			.first();

		await swatch.fill( '#ff00ff' );

		const cell = page.locator( '#wp-postratings-rating-fields .wp-postratings-rating-preview' ).first();

		await expect( cell ).toHaveAttribute( 'style', /--wp-postratings-color-on:\s*#ff00ff/ );
	} );

	test( 'a colour chosen right after switching type survives the first save', async ( {
		page,
	} ) => {
		await chooseType( page, 'updown', 2 );

		// The sanitizer used to reset the table because the type had changed,
		// discarding whatever had been typed into it -- which was always this.
		await page
			.locator( '.wp-postratings-color[data-property="--wp-postratings-color-on"]' )
			.nth( 1 )
			.fill( '#00a3ff' );

		await saveSettings( page );
		await openSettings( page );

		expect( await swatches( page ) ).toEqual( [ COLORS.down, '#00a3ff' ] );
	} );

	test( 'a step can be added and removed, keeping what is already typed', async ( { page } ) => {
		const rows = page.locator( '#wp-postratings-rating-fields tbody tr' );
		const texts = page.locator( '#wp-postratings-rating-fields input[name$="[text][]"]' );

		await texts.first().fill( 'Dreadful' );

		await page.locator( '#wp-postratings-add-step' ).click();
		await expect( rows ).toHaveCount( 6 );

		// Adding a step is the same rating one row longer, so the first row is
		// still the label just typed. It used to come back as the last row's.
		await expect( texts.first() ).toHaveValue( 'Dreadful' );

		await page.locator( '.wp-postratings-remove-step' ).last().click();
		await expect( rows ).toHaveCount( 5 );
		await expect( texts.first() ).toHaveValue( 'Dreadful' );
	} );

	test( 'a scale cannot be shortened below three steps', async ( { page } ) => {
		const rows = page.locator( '#wp-postratings-rating-fields tbody tr' );

		await page.locator( '.wp-postratings-remove-step' ).last().click();
		await expect( rows ).toHaveCount( 4 );

		await page.locator( '.wp-postratings-remove-step' ).last().click();
		await expect( rows ).toHaveCount( 3 );

		// One or two points is not a scale; that is an up/down pair, which is a
		// different type with its own shapes and its own renderer.
		await expect( page.locator( '.wp-postratings-remove-step' ).last() ).toBeDisabled();
	} );

	test( 'an up or down pair offers no way to change its length', async ( { page } ) => {
		await chooseType( page, 'updown', 2 );

		await expect( page.locator( '#wp-postratings-add-step' ) ).toHaveCount( 0 );
		await expect( page.locator( '.wp-postratings-remove-step' ) ).toHaveCount( 0 );
	} );

	test( 'a longer scale and its labels survive a save', async ( { page } ) => {
		const texts = page.locator( '#wp-postratings-rating-fields input[name$="[text][]"]' );

		await page.locator( '#wp-postratings-add-step' ).click();
		await expect( page.locator( '#wp-postratings-rating-fields tbody tr' ) ).toHaveCount( 6 );

		await texts.nth( 5 ).fill( 'Perfect' );

		await saveSettings( page );
		await openSettings( page );

		await expect( page.locator( '#wp-postratings-rating-fields tbody tr' ) ).toHaveCount( 6 );
		await expect( texts.nth( 5 ) ).toHaveValue( 'Perfect' );
	} );

	test( 'Reset to default puts every swatch back', async ( { page } ) => {
		await page
			.locator( '.wp-postratings-color[data-property="--wp-postratings-color-on"]' )
			.first()
			.fill( '#ff00ff' );

		await page.locator( '#wp-postratings-reset-colors' ).click();

		expect( await swatches( page ) ).toEqual( Array( 5 ).fill( COLORS.rated ) );
	} );

	test( 'Add a step stops at the maximum', async ( { page } ) => {
		const add = page.locator( '#wp-postratings-add-step' );
		const rows = page.locator( '#wp-postratings-rating-fields tbody tr' );

		// Ten by default. Every point is a glyph a visitor has to aim at, so a
		// scale of fifty is a misconfiguration rather than a preference -- and it
		// used to be accepted silently, the only bound being a floor of one.
		for ( let i = 5; i < 10; i++ ) {
			await add.click();
			await expect( rows ).toHaveCount( i + 1 );
		}

		await expect( add ).toBeDisabled();
	} );

	test( 'the proxy header field saves what is typed into it', async ( { page } ) => {
		const field = page.locator( '#wp_postratings_ip_header' );

		await field.fill( 'HTTP_CF_CONNECTING_IP' );
		await saveSettings( page );
		await openSettings( page );

		await expect( field ).toHaveValue( 'HTTP_CF_CONNECTING_IP' );

		// And the row names the constant and filter that do the same job more
		// broadly, so a site behind a proxy can find them.
		await expect(
			page.locator( 'tr' ).filter( { has: page.locator( '#wp_postratings_ip_header' ) } ),
		).toContainText( 'WP_POSTRATINGS_TRUST_PROXY' );

		await field.fill( '' );
		await saveSettings( page );
	} );

	test( 'a header that is not a header name is refused', async ( { page } ) => {
		const field = page.locator( '#wp_postratings_ip_header' );

		// A header name, not a value: anything outside the shape PHP uses for
		// $_SERVER keys cannot match and is dropped rather than stored.
		await field.fill( 'not a header!' );
		await saveSettings( page );
		await openSettings( page );

		await expect( field ).toHaveValue( '' );
	} );

	test( 'the WP-Stats settings save, including the unticked box', async ( { page } ) => {
		const box = page.locator( '#wp_postratings_stats_display' );

		await box.uncheck();
		await page.locator( '#wp_postratings_stats_most_limit' ).fill( '7' );

		await saveSettings( page );
		await openSettings( page );

		// An unticked checkbox posts nothing at all, so this is the case a
		// sanitizer that only looks at what arrived gets wrong: it reads the
		// absence as "leave it alone" and the box can never be turned off.
		await expect( box ).not.toBeChecked();
		await expect( page.locator( '#wp_postratings_stats_most_limit' ) ).toHaveValue( '7' );

		await box.check();
		await saveSettings( page );
		await openSettings( page );

		await expect( box ).toBeChecked();
	} );

	test( 'the two tabs are both reachable', async ( { page } ) => {
		const tabs = page.locator( '.nav-tab-wrapper' );

		await tabs.getByRole( 'link', { name: 'Templates' } ).click();

		await expect( page.locator( '#wp_postratings_template_vote' ) ).toBeVisible();

		await tabs.getByRole( 'link', { name: 'Settings' } ).click();

		await expect( page.locator( '#wp_postratings_check_method' ) ).toBeVisible();
	} );

	test( 'the repeat-vote check offers the renamed choices', async ( { page } ) => {
		const select = page.locator( '#wp_postratings_check_method' );

		const labels = await select
			.locator( 'option' )
			.evaluateAll( ( options ) => options.map( ( option ) => option.textContent.trim() ) );

		expect( labels ).toEqual( [
			'Do Not Check',
			'Check By Cookie',
			'Check By IP Address',
			'Check By Cookie And IP Address',
			'Check By Username',
		] );
	} );
} );
