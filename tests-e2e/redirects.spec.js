/**
 * Redirects screen against a real site: adds a redirect under a unique path,
 * toggles it, deletes it, and runs axe. Each viewport project uses its own
 * path because the projects run in parallel.
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

test( 'Redirects: add, toggle and delete a redirect; axe is clean', async ( {
	page,
}, info ) => {
	const src = `/emcp-e2e-${ info.project.name }-${ Date.now() }`;
	await page.goto( '/wp-admin/admin.php?page=emcp-tools-redirects' );
	await expect(
		page.getByRole( 'heading', { level: 1, name: 'Redirects' } )
	).toBeVisible();
	const form = page.getByRole( 'form', { name: 'Add redirect' } );
	await form.getByLabel( 'From' ).fill( src );
	await form.getByLabel( 'To' ).fill( '/' );
	await form.getByRole( 'button', { name: 'Add redirect' } ).click();
	const toggle = page.getByRole( 'switch', { name: `Enabled: ${ src }` } );
	await expect( toggle ).toHaveAttribute( 'aria-checked', 'true' );
	await toggle.click();
	await expect( toggle ).toHaveAttribute( 'aria-checked', 'false' );

	await form
		.getByRole( 'checkbox', { name: 'Match regardless of query string' } )
		.uncheck();
	await form.getByLabel( 'From' ).fill( '/no-query-here' );
	await form.getByLabel( 'To' ).fill( '/' );
	await form.getByRole( 'button', { name: 'Add redirect' } ).click();
	await expect(
		form.getByText(
			'Add the query string to match, for example /page?ref=ad.'
		)
	).toBeVisible();

	const results = await new AxeBuilder( { page } )
		.include( '#emcp-screen' )
		.analyze();
	expect( results.violations ).toEqual( [] );

	await page
		.getByRole( 'button', { name: `Delete redirect: ${ src }` } )
		.click();
	await page
		.getByRole( 'dialog' )
		.getByRole( 'button', { name: 'Delete' } )
		.click();
	await expect(
		page.getByRole( 'switch', { name: `Enabled: ${ src }` } )
	).toHaveCount( 0 );
} );
