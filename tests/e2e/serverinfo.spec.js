/**
 * The Server Information report, and its dashboard widget.
 *
 * This plugin is two read-only screens and nothing else: no settings, no stored
 * options, no front end. What can go wrong is therefore entirely about the
 * screens -- a tab that renders nothing, a panel that is offered when the
 * extension behind it is missing, a capability that lets the wrong person read
 * the server's configuration.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const REPORT_URL = '/wp-admin/tools.php?page=wp-serverinfo';

/**
 * Create a user with a role, or return the existing one.
 *
 * @param {Object} requestUtils The e2e request helper.
 * @param {string} username     Login name.
 * @param {string} role         Role slug.
 * @return {Promise<Object>} The user record.
 */
async function ensureUser( requestUtils, username, role ) {
	const existing = await requestUtils.rest( {
		path: '/wp/v2/users',
		params: { search: username, context: 'edit' },
	} );

	if ( existing.length ) {
		return existing[ 0 ];
	}

	return requestUtils.rest( {
		method: 'POST',
		path: '/wp/v2/users',
		data: {
			username,
			email: `${ username }@example.com`,
			password: 'correct-horse-battery-staple',
			roles: [ role ],
		},
	} );
}

/**
 * A browser context logged in as somebody else.
 *
 * @param {import('@playwright/test').Browser} browser  Playwright browser.
 * @param {string}                             baseURL  Site root.
 * @param {string}                             username Login name.
 * @return {Promise<Object>} The context and its page.
 */
async function loginAs( browser, baseURL, username ) {
	const context = await browser.newContext( { storageState: undefined } );
	const page = await context.newPage();

	await page.goto( `${ baseURL }/wp-login.php` );

	// wp-login.php focuses and selects #user_login on a 200ms timer, so that a
	// visitor can start typing. Filling across that moment puts the password
	// into the username box: Playwright focuses #user_pass, the timer takes
	// focus back and selects what is there, and the typed text replaces the
	// selection. Waiting for the timer's own effect is the signal that it has
	// already fired -- a sleep would only make the race less likely.
	await expect( page.locator( '#user_login' ) ).toBeFocused();

	await page.locator( '#user_login' ).fill( username );
	await page.locator( '#user_pass' ).fill( 'correct-horse-battery-staple' );
	await page.locator( '#wp-submit' ).click();

	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();

	return { context, page };
}

test.describe( 'The Server Information report', () => {
	test( 'sits under Tools, where WordPress keeps its read-only reports', async ( {
		page,
		admin,
	} ) => {
		await admin.visitAdminPage( 'tools.php' );

		const entry = page.locator( '#adminmenu a[href="tools.php?page=wp-serverinfo"]' );

		await expect( entry ).toHaveCount( 1 );

		await entry.click();

		await expect( page.getByRole( 'heading', { name: 'Server Information' } ) ).toBeVisible();
	} );

	test( 'opens on General, with a table of values', async ( { page } ) => {
		await page.goto( REPORT_URL );

		await expect( page.locator( '.nav-tab-active' ) ).toHaveText( 'General' );
		await expect( page.getByRole( 'heading', { name: 'General Overview' } ) ).toBeVisible();

		const table = page.locator( '#GeneralOverview table.widefat' );

		await expect( table ).toBeVisible();

		// A report with no rows is a report that silently failed to gather
		// anything, which looks identical to one that had nothing to say.
		const rows = await table.locator( 'tbody tr' ).count();
		expect( rows ).toBeGreaterThan( 3 );

		await expect( table ).toContainText( 'OS' );
	} );

	test( 'the PHP tab reports the running version and its directives', async ( { page } ) => {
		await page.goto( REPORT_URL );

		await page.getByRole( 'link', { name: 'PHP', exact: true } ).click();

		await expect( page.locator( '.nav-tab-active' ) ).toHaveText( 'PHP' );

		// The heading carries the version the site is actually running, which is
		// the first thing anybody opens this screen to find out.
		await expect( page.locator( '#PHPinfo h2' ) ).toHaveText( /^PHP \d+\.\d+/ );

		const directives = page.locator( '#PHPinfo table.widefat' ).last();

		await expect( directives ).toContainText( 'memory_limit' );
		await expect( directives ).toContainText( 'Local Value' );
		await expect( directives ).toContainText( 'Master Value' );
	} );

	test( 'the MySQL tab reports the server version and its variables', async ( { page } ) => {
		await page.goto( REPORT_URL );

		await page.getByRole( 'link', { name: 'MySQL', exact: true } ).click();

		await expect( page.locator( '.nav-tab-active' ) ).toHaveText( 'MySQL' );
		await expect( page.locator( '#MYSQLinfo h2' ) ).toHaveText( /^MYSQL \S+/ );

		await expect( page.locator( '#MYSQLinfo' ) ).toContainText( 'max_connections' );
	} );

	test( 'only the tabs whose extension is present are offered', async ( { page } ) => {
		await page.goto( REPORT_URL );

		const tabs = await page
			.locator( '.nav-tab-wrapper a' )
			.evaluateAll( ( links ) => links.map( ( link ) => link.textContent.trim() ) );

		expect( tabs ).toEqual( expect.arrayContaining( [ 'General', 'PHP', 'MySQL' ] ) );

		// wp-env ships neither, so offering either tab would be offering a panel
		// that can only say it could not connect.
		expect( tabs ).not.toContain( 'memcached' );
		expect( tabs ).not.toContain( 'redis' );
	} );

	test( 'an unknown tab falls back to General rather than an empty page', async ( { page } ) => {
		await page.goto( `${ REPORT_URL }&tab=nonsense` );

		await expect( page.locator( '.nav-tab-active' ) ).toHaveText( 'General' );
		await expect( page.getByRole( 'heading', { name: 'General Overview' } ) ).toBeVisible();
	} );

	test( 'the panels read left to right even though the admin need not', async ( { page } ) => {
		await page.goto( REPORT_URL );

		// Every value on these screens -- paths, versions, addresses, ini names
		// -- is latin text whatever the admin language is. Before 3.0.0 this was
		// a <style> block written into the page with a physical text-align and an
		// !important; it is an attribute now, and no stylesheet ships at all.
		await expect( page.locator( '.serverinfo-panel' ).first() ).toHaveAttribute( 'dir', 'ltr' );

		await expect( page.locator( 'link[rel="stylesheet"][href*="wp-serverinfo"]' ) ).toHaveCount( 0 );
	} );

	test( 'the dashboard widget shows the same figures', async ( { page, admin } ) => {
		await admin.visitAdminPage( 'index.php' );

		const widget = page.locator( '#dashboard_serverinfo' );

		await expect( widget ).toBeVisible();
		await expect( widget.locator( '.hndle, h2' ).first() ).toContainText(
			'Server Information',
		);

		await expect( widget ).toContainText( /PHP/ );
		await expect( widget ).toContainText( /MySQL/i );

		// The versions are the point of the widget, so they are emphasised rather
		// than buried in a sentence.
		const bold = await widget.locator( 'strong' ).count();
		expect( bold ).toBeGreaterThan( 1 );
	} );

	test( 'a user without the capability gets neither the screen nor the widget', async ( {
		browser,
		baseURL,
		requestUtils,
	} ) => {
		await ensureUser( requestUtils, 'serverinfo_editor', 'editor' );

		const other = await loginAs( browser, baseURL, 'serverinfo_editor' );

		// This screen lists paths, versions and ini directives -- everything an
		// attacker wants first -- so it is behind a capability an editor does not
		// have, and the widget is behind its own.
		await expect(
			other.page.locator( '#adminmenu a[href="tools.php?page=wp-serverinfo"]' ),
		).toHaveCount( 0 );

		await other.page.goto( `${ baseURL }/wp-admin/index.php` );
		await expect( other.page.locator( '#dashboard_serverinfo' ) ).toHaveCount( 0 );

		await other.page.goto( `${ baseURL }${ REPORT_URL }` );
		await expect( other.page.locator( 'body' ) ).toContainText(
			/sufficient permissions|not allowed to access this page/,
		);

		await other.context.close();
	} );

	test( 'the report stores nothing while being read', async ( { page, requestUtils } ) => {
		await page.goto( REPORT_URL );
		await page.getByRole( 'link', { name: 'PHP', exact: true } ).click();
		await page.getByRole( 'link', { name: 'MySQL', exact: true } ).click();

		const settings = await requestUtils.rest( { path: '/wp/v2/settings' } );

		// A report has nothing to remember. Reading one three times should leave
		// the site exactly as it was.
		expect( Object.keys( settings ).some( ( key ) => key.includes( 'serverinfo' ) ) ).toBe(
			false,
		);
	} );
} );
