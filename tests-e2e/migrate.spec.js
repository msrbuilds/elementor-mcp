/**
 * Backup & Migrate screen against a real site. Every view renders and passes
 * axe; the 1440 project also runs a real database backup through the screen
 * and deletes it from History (one backup at a time is enough, and the
 * projects run in parallel).
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

const axe = async ( page ) => {
	const results = await new AxeBuilder( { page } )
		.include( '#emcp-screen' )
		.analyze();
	expect( results.violations ).toEqual( [] );
};

test( 'Backup & Migrate: every view renders and axe is clean', async ( {
	page,
} ) => {
	await page.goto( '/wp-admin/admin.php?page=emcp-tools-migrate' );
	await expect(
		page.getByRole( 'heading', { level: 1, name: 'Backup & Migrate' } )
	).toBeVisible();
	await expect(
		page.getByRole( 'button', { name: 'Start backup' } )
	).toBeVisible();
	await axe( page );
	for ( const [ label, marker ] of [
		[ 'Restore', 'Server upload limits' ],
		[ 'Migrate', 'Step 2: Pair the live site' ],
		[ 'Sync', null ],
		[ 'History', 'Backup history' ],
	] ) {
		await page.getByRole( 'radio', { name: label, exact: true } ).click();
		await expect( page ).toHaveURL(
			new RegExp( 'view=' + label.toLowerCase() )
		);
		if ( marker ) {
			await expect(
				page.getByRole( 'heading', { name: marker } )
			).toBeVisible();
		}
		await axe( page );
	}
} );

test( 'Backup & Migrate: a database backup through the screen', async ( {
	page,
}, info ) => {
	test.skip( '1440' !== info.project.name, 'One backup run is enough.' );
	test.setTimeout( 240000 );
	const name = `e2e backup ${ Date.now() }`;
	await page.goto( '/wp-admin/admin.php?page=emcp-tools-migrate' );
	await page.getByRole( 'radio', { name: /Database only/ } ).check();
	await page.getByLabel( 'Backup name' ).fill( name );
	await page.getByRole( 'button', { name: 'Start backup' } ).click();
	await expect( page.getByText( 'Backup complete.' ) ).toBeVisible( {
		timeout: 180000,
	} );
	await page.goto(
		'/wp-admin/admin.php?page=emcp-tools-migrate&view=history'
	);
	const del = page.getByRole( 'button', {
		name: `Delete backup: ${ name }`,
	} );
	await expect( del ).toBeVisible();
	await del.click();
	await page
		.getByRole( 'dialog' )
		.getByRole( 'button', { name: 'Delete' } )
		.click();
	await expect( del ).toHaveCount( 0 );
} );
