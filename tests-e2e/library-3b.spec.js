/**
 * Templates, Marketplace and the palette library against a real site
 * (read-only: no template is used, nothing is installed). Same environment
 * as frame.spec.js.
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

test( 'Templates: cards, industry filter and preview drawer', async ( {
	page,
} ) => {
	await page.goto( '/wp-admin/admin.php?page=emcp-tools-templates' );
	await expect(
		page.getByRole( 'heading', { level: 1, name: /Templates/ } )
	).toBeVisible();
	await expect( page.locator( '.eui-tpl' ).first() ).toBeVisible();
	await page.getByLabel( 'Industry' ).selectOption( 'automotive' );
	await expect( page ).toHaveURL( /category=automotive/ );
	await expect( page.locator( '.eui-tpl__cat' ).first() ).toHaveText(
		'Automotive'
	);
	await page
		.locator( '.eui-tpl' )
		.first()
		.getByRole( 'button', { name: /^Details for / } )
		.click();
	await expect(
		page
			.getByRole( 'dialog' )
			.getByRole( 'button', { name: 'Add to Elementor library' } )
	).toBeVisible();
} );

test( 'Marketplace: grid or connect card', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=emcp-tools-marketplace' );
	await expect(
		page.getByRole( 'heading', { level: 1, name: 'Marketplace' } )
	).toBeVisible();
	const connect = page.getByRole( 'link', {
		name: 'Connect to EMCP Cloud',
	} );
	if ( await connect.count() ) {
		await expect( connect ).toBeVisible();
	} else {
		await expect( page.locator( '.eui-mk__count' ) ).toContainText(
			/result/
		);
	}
} );

test( 'Palette finds a template by title', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=emcp-tools' );
	await page.keyboard.press( 'Control+k' );
	await page.getByRole( 'combobox' ).fill( 'ironclad' );
	await expect(
		page.getByRole( 'option', { name: /Ironclad Auto Repair/ } )
	).toBeVisible();
} );

test( 'Templates and Marketplace pass axe', async ( { page } ) => {
	for ( const slug of [ 'emcp-tools-templates', 'emcp-tools-marketplace' ] ) {
		await page.goto( `/wp-admin/admin.php?page=${ slug }` );
		await expect(
			page.locator( '#emcp-screen [data-emcp-root] > *' ).first()
		).toBeVisible();
		// Marketplace fills in after mount: wait for results or the connect card.
		if ( 'emcp-tools-marketplace' === slug ) {
			await expect(
				page.locator( '.eui-mk__count, .eui-empty' ).first()
			).toBeVisible();
		}
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
