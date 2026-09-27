/**
 * Connection screen against a real site. Same environment as frame.spec.js.
 * Nothing persistent is created: the wizard opens a setup record (closed by
 * the next run's setup) and no password or setting is saved.
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

test( 'wizard walks to step 4 and waits for the bound call', async ( {
	page,
} ) => {
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	await page.goto( '/wp-admin/admin.php?page=emcp-tools-connection' );
	await page.getByRole( 'button', { name: 'Claude Code' } ).click();
	// The radio input is visually hidden; people click the card.
	// OAuth: nothing is created, and Continue needs no password.
	await page
		.getByText( 'Sign in through the browser, no password to copy.' )
		.click();
	await expect( page.getByText( 'Run this in your terminal' ) ).toBeVisible();
	await expect(
		page.getByRole( 'button', { name: "I've added it, continue" } )
	).toBeEnabled();
	await page
		.getByRole( 'button', { name: "I've added it, continue" } )
		.click();
	await expect(
		page.getByText( /Waiting for Claude Code to call the server/ )
	).toBeVisible();
	await expect( page ).toHaveURL( /client=claude-code/ );
	expect( errors ).toEqual( [] );
} );

test( 'sections switch and keep the URL', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=emcp-tools-connection' );
	const steps = page.getByText( 'Choose your AI client' );
	await expect( steps ).toBeVisible();
	await page.getByRole( 'radio', { name: '3rd-party services' } ).click();
	await expect( page ).toHaveURL( /section=services/ );
	await expect( page.getByText( 'Stock images' ) ).toBeVisible();
	// The section replaces the MCP setup instead of stacking under it.
	await expect( steps ).toBeHidden();
	await page.getByRole( 'radio', { name: 'MCP' } ).click();
	await expect( steps ).toBeVisible();
	await expect( page.getByText( 'Stock images' ) ).toBeHidden();
	await page.getByRole( 'radio', { name: '3rd-party services' } ).click();
	await page.reload();
	await expect( page.getByText( 'Stock images' ) ).toBeVisible();
} );

test( 'server status rail renders', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=emcp-tools-connection' );
	await expect(
		page.getByRole( 'region', { name: 'Server status' } )
	).toBeVisible();
	await expect( page.getByText( 'Connected apps' ) ).toBeVisible();
} );

test( 'every Connection section passes axe', async ( { page } ) => {
	for ( const q of [
		'client=claude-code&method=app',
		'section=cloud',
		'section=services',
	] ) {
		await page.goto(
			'/wp-admin/admin.php?page=emcp-tools-connection&' + q
		);
		await expect( page.locator( '#emcp-screen .eui-conn' ) ).toBeVisible();
		const results = await new AxeBuilder( { page } )
			.include( '#emcp-screen' )
			.analyze();
		expect(
			results.violations.map(
				( v ) =>
					v.id +
					': ' +
					v.nodes.map( ( n ) => n.target.join( ' ' ) ).join( ', ' )
			)
		).toEqual( [] );
	}
} );
