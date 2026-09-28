/**
 * Skills > Custom skills against a real site: write a skill in the editor,
 * see it in the list, switch it off, delete it. Each viewport project uses
 * its own name because the projects run in parallel.
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

test( 'Custom skills: write, list, switch off and delete a skill', async ( {
	page,
}, info ) => {
	test.setTimeout( 180000 );
	const name = `E2E skill ${ info.project.name } ${ Date.now() }`;
	await page.goto( '/wp-admin/admin.php?page=emcp-tools-skills&view=custom' );
	const heading = page.getByRole( 'heading', {
		level: 1,
		name: /Custom skills/,
	} );
	test.skip(
		0 === ( await heading.count() ),
		'Custom skills need an EMCP Pro licence'
	);
	await expect( page.locator( '.eui-frame-crumbs' ) ).toContainText(
		'Custom skills'
	);

	await page.getByRole( 'button', { name: 'New skill' } ).click();
	await page.getByLabel( 'Name' ).fill( name );
	await page
		.getByLabel( 'When to use it' )
		.fill( 'Use when running the end-to-end test. Nothing else.' );
	const cm = page.locator( '.CodeMirror' );
	if ( await cm.count() ) {
		await cm.click();
		await page.keyboard.type( '# E2E\n\nDo nothing.' );
	} else {
		await page.getByLabel( 'Instructions' ).fill( '# E2E\n\nDo nothing.' );
	}
	const rail = page.getByRole( 'region', { name: 'Skill stats' } );
	await expect( rail ).toContainText(
		'Use when running the end-to-end test.'
	);
	const results = await new AxeBuilder( { page } )
		.include( '#emcp-screen' )
		.analyze();
	expect( results.violations ).toEqual( [] );
	await rail.getByRole( 'button', { name: 'Save skill' } ).click();
	await expect( page ).toHaveURL( /skill=\d+/ );

	await page.getByRole( 'button', { name: 'Back to the list' } ).click();
	const item = page
		.getByRole( 'list', { name: 'Custom skills' } )
		.getByRole( 'listitem' )
		.filter( { hasText: name } );
	await expect( item ).toContainText( 'Injected' );
	await item.getByRole( 'switch', { name: `Inject ${ name }` } ).click();
	await expect( item ).toContainText( 'Off' );

	await item.getByRole( 'button', { name: `Edit ${ name }` } ).click();
	await page.getByRole( 'button', { name: 'Delete' } ).click();
	await page
		.getByRole( 'dialog', { name: 'Delete this skill?' } )
		.getByRole( 'button', { name: 'Delete' } )
		.click();
	await expect( page.getByText( name ) ).toHaveCount( 0 );
} );
