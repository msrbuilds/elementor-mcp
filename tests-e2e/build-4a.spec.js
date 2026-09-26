/**
 * Context, Agent Skills and Project Memory against a real site (read-only:
 * nothing is saved). Same environment as frame.spec.js.
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

test( 'Context: a section switch changes the preview, Discard restores it, nothing is saved', async ( {
	page,
} ) => {
	await page.goto( '/wp-admin/admin.php?page=emcp-tools-context' );
	const theme = page.getByRole( 'switch', { name: 'Theme' } );
	await expect( theme ).toBeVisible();
	const was = await theme.getAttribute( 'aria-checked' );
	const preview = page.locator( '.eui-ctx__preview-text' );
	const before = await preview.textContent();
	await theme.click();
	await expect(
		page.getByRole( 'region', { name: 'Unsaved changes' } )
	).toBeVisible();
	await expect( preview ).not.toHaveText( before );
	await page.getByRole( 'button', { name: 'Discard' } ).click();
	await expect( theme ).toHaveAttribute( 'aria-checked', was );
	await page.reload();
	await expect(
		page.getByRole( 'switch', { name: 'Theme' } )
	).toHaveAttribute( 'aria-checked', was );
} );

test( 'Skills: the install guide switches to Cursor', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=emcp-tools-skills' );
	await expect(
		page.getByRole( 'heading', { level: 1, name: /Agent Skills/ } )
	).toBeVisible();
	await page
		.getByRole( 'radiogroup', { name: 'Install guide' } )
		.getByRole( 'radio', { name: 'Cursor' } )
		.click();
	await expect( page.getByLabel( 'Rules folder' ) ).toContainText(
		'.cursor/rules'
	);
} );

test( 'Memory: switching to Pending review keeps the view in the URL', async ( {
	page,
} ) => {
	await page.goto( '/wp-admin/admin.php?page=emcp-tools-memory' );
	await expect(
		page.getByRole( 'heading', { level: 1, name: /Project Memory/ } )
	).toBeVisible();
	await page.getByRole( 'radio', { name: /Pending review/ } ).click();
	await expect( page ).toHaveURL( /tab=pending/ );
} );

test( 'Context, Skills and Memory pass axe', async ( { page } ) => {
	for ( const slug of [
		'emcp-tools-context',
		'emcp-tools-skills',
		'emcp-tools-memory',
	] ) {
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
