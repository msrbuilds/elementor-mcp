/**
 * Changelog against a real site: the latest release renders, an older one
 * loads through REST, an issue search finds 3.17.1, axe is clean.
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

test( 'Changelog: latest, older release, issue search, axe', async ( {
	page,
} ) => {
	await page.goto( '/wp-admin/admin.php?page=emcp-tools-changelog' );
	await expect(
		page.getByRole( 'heading', { level: 1, name: 'Changelog' } )
	).toBeVisible();
	await expect(
		page.getByRole( 'heading', { level: 2, name: /^Version / } )
	).toBeVisible();
	await page.getByRole( 'button', { name: 'Older releases' } ).click();
	const rail = page.getByRole( 'navigation', { name: 'Releases' } );
	const last = rail.getByRole( 'button' ).last();
	const label = ( await last.innerText() ).trim().replace( /^v/, '' );
	await last.click();
	await expect(
		page.getByRole( 'heading', { level: 2, name: `Version ${ label }` } )
	).toBeVisible();
	await page
		.getByRole( 'searchbox', { name: 'Search changes or #issue' } )
		.fill( '#145' );
	await expect(
		page.getByRole( 'button', { name: 'Version 3.17.1' } )
	).toBeVisible();
	const results = await new AxeBuilder( { page } )
		.include( '#emcp-screen' )
		.analyze();
	expect( results.violations ).toEqual( [] );
	const overflow = await page.evaluate(
		() => document.documentElement.scrollWidth > window.innerWidth
	);
	expect( overflow ).toBe( false );
} );
