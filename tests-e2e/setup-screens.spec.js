/**
 * Setup screens against a real site: each save round-trips and is reverted.
 * Same environment as frame.spec.js.
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

async function saveAndWait( page, name ) {
	const done = page.waitForResponse(
		( r ) =>
			r.url().includes( '/emcp-tools/v1/admin/' ) &&
			'POST' === r.request().method()
	);
	await page.getByRole( 'button', { name } ).click();
	expect( ( await done ).status() ).toBe( 200 );
}

test( 'Tools: toggling a tool persists across a reload, then is reverted', async ( {
	page,
} ) => {
	await page.goto(
		'/wp-admin/admin.php?page=emcp-tools-tools&tab=wordpress&q=list-posts'
	);
	const toggle = page.getByRole( 'switch', { name: 'List Posts' } );
	const before = await toggle.isChecked();
	await toggle.click();
	await expect( page.getByText( /1 unsaved change/ ) ).toBeVisible();
	await saveAndWait( page, 'Save changes' );
	await page.reload();
	await expect(
		page.getByRole( 'switch', { name: 'List Posts' } )
	).toBeChecked( { checked: ! before } );
	await page.getByRole( 'switch', { name: 'List Posts' } ).click();
	await saveAndWait( page, 'Save changes' );
} );

test( 'Page Builders: the screen renders its sections', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=emcp-tools-page-builders' );
	await expect(
		page.getByRole( 'radio', { name: /Gutenberg only/ } )
	).toBeVisible();
	await expect(
		page.getByText( '2. Gutenberg block plugins' )
	).toBeVisible();
	await page.getByRole( 'switch', { name: 'Show detected only' } ).click();
	await expect( page ).toHaveURL( /detected=1/ );
} );

test( 'Modules: toggling a module shows its name in the save bar; discard restores it', async ( {
	page,
} ) => {
	await page.goto( '/wp-admin/admin.php?page=emcp-tools-modules' );
	const toggle = page.getByRole( 'switch', { name: 'Redirect Manager' } );
	const before = await toggle.isChecked();
	await toggle.click();
	await expect(
		page.getByText(
			before
				? 'Redirect Manager turned off'
				: 'Redirect Manager turned on'
		)
	).toBeVisible();
	await page.getByRole( 'button', { name: 'Discard' } ).click();
	await expect(
		page.getByRole( 'switch', { name: 'Redirect Manager' } )
	).toBeChecked( { checked: before } );
} );

test( 'Modules: saving a module persists after the reload, then is reverted', async ( {
	page,
} ) => {
	page.on( 'dialog', ( d ) => {
		throw new Error( 'unexpected dialog: ' + d.message() );
	} );
	await page.goto( '/wp-admin/admin.php?page=emcp-tools-modules' );
	const toggle = () =>
		page.getByRole( 'switch', { name: 'Redirect Manager' } );
	const before = await toggle().isChecked();
	for ( const expected of [ ! before, before ] ) {
		await toggle().click();
		const reloaded = page.waitForEvent( 'load' );
		await saveAndWait( page, 'Save modules' );
		await reloaded;
		await expect( toggle() ).toBeChecked( { checked: expected } );
	}
} );

test( 'Setup screens load without console errors', async ( { page } ) => {
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	page.on( 'console', ( m ) => {
		if ( 'error' === m.type() ) {
			errors.push( m.text() );
		}
	} );
	for ( const slug of [
		'emcp-tools-tools',
		'emcp-tools-modules',
		'emcp-tools-page-builders',
	] ) {
		await page.goto( `/wp-admin/admin.php?page=${ slug }` );
		await expect(
			page.locator( '[data-emcp-recovery]:not([hidden])' )
		).toHaveCount( 0 );
		await expect(
			page.locator( '#emcp-screen [data-emcp-root] > *' ).first()
		).toBeVisible();
	}
	expect( errors.filter( ( e ) => ! /favicon/i.test( e ) ) ).toEqual( [] );
} );

test( 'Setup screens pass axe', async ( { page } ) => {
	for ( const slug of [
		'emcp-tools-tools',
		'emcp-tools-modules',
		'emcp-tools-page-builders',
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
