/**
 * MCP Log screen against a real site: stats and rows render, the Errors
 * filter reaches the URL and the export link, a row expands, axe is clean.
 * It never clears the log.
 */
const fs = require( 'fs' );
const { test, expect } = require( '@playwright/test' );
const AxeBuilder = require( '@axe-core/playwright' ).default;

const { EMCP_E2E_URL, EMCP_E2E_COOKIES } = process.env;
test.skip(
	! EMCP_E2E_URL || ! EMCP_E2E_COOKIES,
	'Set EMCP_E2E_URL and EMCP_E2E_COOKIES'
);

test.beforeEach( async ( { context } ) => {
	const c = JSON.parse( fs.readFileSync( EMCP_E2E_COOKIES, 'utf8' ) );
	const base = {
		domain: c.host,
		httpOnly: true,
		secure: c.secure,
		sameSite: 'Lax',
	};
	await context.addCookies( [
		{ ...base, name: c.auth[ 0 ], value: c.auth[ 1 ], path: '/wp-admin' },
		{ ...base, name: c.logged[ 0 ], value: c.logged[ 1 ], path: '/' },
	] );
} );

test( 'MCP Log: stats, filter, export link, row details, axe', async ( {
	page,
} ) => {
	await page.goto( '/wp-admin/admin.php?page=emcp-tools-mcp-log' );
	await expect(
		page.getByRole( 'heading', { level: 1, name: 'MCP Log' } )
	).toBeVisible();
	await expect( page.getByText( 'Requests', { exact: true } ) ).toBeVisible();
	await page.getByRole( 'radio', { name: 'Errors' } ).click();
	await expect( page ).toHaveURL( /status=error/ );
	const [ download ] = await Promise.all( [
		page.waitForEvent( 'download' ),
		page.getByRole( 'button', { name: 'Export CSV' } ).click(),
	] );
	expect( download.suggestedFilename() ).toMatch(
		/^emcp-mcp-log-\d{8}-\d{6}\.csv$/
	);
	const csv = fs.readFileSync( await download.path(), 'utf8' );
	expect( csv.split( '\n' )[ 0 ] ).toContain( 'time_utc,method,tool,status' );
	expect( csv ).not.toContain( ',success,' );
	const more = page
		.getByRole( 'button', { name: /^Show (more|less):/ } )
		.first();
	if ( await more.count() ) {
		await more.click();
		await expect( more ).toHaveAttribute( 'aria-expanded', 'true' );
	}
	const results = await new AxeBuilder( { page } )
		.include( '#emcp-screen' )
		.analyze();
	expect( results.violations ).toEqual( [] );
} );
