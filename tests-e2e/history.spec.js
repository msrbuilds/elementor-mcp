/**
 * History screen against a real site. Read-only: it filters, opens a diff
 * and runs axe; writes are covered by pro/tests/smoke/history-rest-smoke.php.
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

test( 'History: sessions render, Design filters via the URL, a diff opens, axe is clean', async ( {
	page,
} ) => {
	await page.goto( '/wp-admin/admin.php?page=emcp-tools-history' );
	await expect(
		page.getByRole( 'heading', { level: 1, name: 'History' } )
	).toBeVisible();
	await expect( page.getByRole( 'group' ).first() ).toBeVisible();
	await page.getByRole( 'radio', { name: 'Design', exact: true } ).click();
	await expect( page ).toHaveURL( /kind=design/ );
	const view = page
		.getByRole( 'button', { name: /^View difference:/ } )
		.first();
	await expect( view ).toBeVisible();
	await view.click();
	const drawer = page.getByRole( 'dialog' );
	await expect(
		drawer
			.getByRole( 'region', { name: 'Difference' } )
			.or( drawer.getByText( 'No preview' ) )
			.or( drawer.getByText( 'The current value matches the saved one.' ) )
	).toBeVisible();
	await page.keyboard.press( 'Escape' );
	await expect( drawer ).toBeHidden();
	const results = await new AxeBuilder( { page } )
		.include( '#emcp-screen' )
		.analyze();
	expect( results.violations ).toEqual( [] );
} );

test( 'History: a reload keeps the filters', async ( { page } ) => {
	await page.goto(
		'/wp-admin/admin.php?page=emcp-tools-history&kind=settings&range=30d'
	);
	await expect(
		page.getByRole( 'radio', { name: 'Settings', exact: true } )
	).toHaveAttribute( 'aria-checked', 'true' );
	await expect( page.getByLabel( 'Time range' ) ).toHaveValue( '30d' );
} );
