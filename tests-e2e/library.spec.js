/**
 * Library screens against a real site (read-only: no kit is applied, no
 * prompt copy is recorded). Same environment as frame.spec.js.
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

test( 'Prompts: filter by category keeps the URL, preview opens', async ( {
	page,
} ) => {
	await page.goto( '/wp-admin/admin.php?page=emcp-tools-prompts' );
	const chips = page
		.getByRole( 'group', { name: 'Categories' } )
		.getByRole( 'button' );
	await chips.nth( 1 ).click();
	await expect( page ).toHaveURL( /category=/ );
	await page
		.getByRole( 'button', { name: /^Preview / } )
		.first()
		.click();
	await expect( page.getByRole( 'dialog' ) ).toBeVisible();
} );

test( 'Prompts: Use in AI Chat fills the composer', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=emcp-tools-prompts' );
	const link = page.getByRole( 'link', { name: 'Use in AI Chat' } ).first();
	test.skip( 0 === ( await link.count() ), 'AI Chat is off on this site' );
	const text = await page
		.locator( '.eui-prompt__preview' )
		.first()
		.textContent();
	await link.click();
	await expect( page.locator( '#emcp-ai-input' ) ).toHaveValue( text );
} );

test( 'Brand Kits: the grid and the current kit strip render', async ( {
	page,
} ) => {
	await page.goto( '/wp-admin/admin.php?page=emcp-tools-brand-kits' );
	await expect( page.locator( '.eui-kit' ).first() ).toBeVisible();
	await expect( page.locator( '.eui-kits__current' ) ).toBeVisible();
} );

test( 'Library screens pass axe', async ( { page } ) => {
	for ( const slug of [ 'emcp-tools-prompts', 'emcp-tools-brand-kits' ] ) {
		await page.goto( `/wp-admin/admin.php?page=${ slug }` );
		await expect(
			page.locator( '#emcp-screen [data-emcp-root] > *' ).first()
		).toBeVisible();
		const results = await new AxeBuilder( { page } )
			.include( '#emcp-screen' )
			.analyze();
		expect(
			results.violations.map(
				( v ) =>
					slug +
					' ' +
					v.id +
					': ' +
					v.nodes.map( ( n ) => n.target.join( ' ' ) ).join( ', ' )
			)
		).toEqual( [] );
	}
} );
