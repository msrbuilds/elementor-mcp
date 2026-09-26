/**
 * Sandbox screens against a real site (Part 4b). The snippet round trip
 * creates, activates, exports, imports and deletes its own snippets only.
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

const SANDBOX = '/wp-admin/admin.php?page=emcp-tools-widgets';

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

async function ready( page ) {
	await expect(
		page.locator( '#emcp-screen [data-emcp-root] > *' ).first()
	).toBeVisible();
}

async function axe( page, label ) {
	const results = await new AxeBuilder( { page } )
		.include( '#emcp-screen' )
		.analyze();
	expect(
		results.violations.map(
			( v ) =>
				label +
				' ' +
				v.id +
				': ' +
				v.nodes.map( ( n ) => n.target.join( ' ' ) ).join( ', ' )
		)
	).toEqual( [] );
}

// The page's own REST nonce, for cleanup calls outside the UI.
async function rest( page, method, path, data ) {
	return page.evaluate(
		async ( [ m, p, d ] ) =>
			window.wp.apiFetch( { path: p, method: m, data: d } ),
		[ method, path, data ]
	);
}

test( 'Overview: cards, review queue and axe', async ( { page } ) => {
	await page.goto( SANDBOX );
	await ready( page );
	await expect(
		page.getByRole( 'heading', { level: 1, name: 'Sandbox' } )
	).toBeVisible();
	for ( const name of [ /^Widgets/, /^PHP Snippets/, /^Blocks/ ] ) {
		await expect(
			page.locator( '.emcp-sbo-cards' ).getByRole( 'link', { name } )
		).toBeVisible();
	}
	await expect(
		page.getByRole( 'heading', { name: 'Awaiting your review' } )
	).toBeVisible();
	await axe( page, 'overview' );
} );

test( 'Unknown views show the overview', async ( { page } ) => {
	await page.goto( `${ SANDBOX }&view=nonsense` );
	await ready( page );
	await expect(
		page.getByRole( 'heading', { level: 1, name: 'Sandbox' } )
	).toBeVisible();
} );

test( 'Snippets: add, activate, export, import and delete', async ( {
	page,
}, testInfo ) => {
	// The viewport projects run in parallel: each run owns its own title.
	const TITLE = `E2E reading time ${ testInfo.project.name }-${ Date.now() }`;
	await page.goto( `${ SANDBOX }&view=snippets` );
	await ready( page );
	await axe( page, 'snippets' );

	await page.getByRole( 'button', { name: 'Add snippet' } ).click();
	const drawer = page.getByRole( 'dialog', { name: 'Add snippet' } );
	await expect( drawer ).toBeVisible();
	await axe( page, 'snippets add drawer' );
	await drawer.getByLabel( 'Title' ).fill( TITLE );
	if ( await drawer.locator( '.CodeMirror' ).count() ) {
		await drawer.locator( '.CodeMirror' ).click();
		await page.keyboard.type( "return 'e2e';" );
	} else {
		await drawer.getByLabel( 'Code' ).fill( "return 'e2e';" );
	}
	await drawer.getByRole( 'button', { name: 'Save draft' } ).click();
	await expect( drawer ).toBeHidden();
	const toggle = page.getByRole( 'switch', {
		name: `Activate ${ TITLE }`,
	} );
	await expect( toggle ).toHaveAttribute( 'aria-checked', 'false' );

	await toggle.click();
	const dialog = page.getByRole( 'dialog', {
		name: 'Activate this snippet?',
	} );
	await dialog.getByRole( 'button', { name: 'Activate' } ).click();
	await expect( toggle ).toHaveAttribute( 'aria-checked', 'true' );

	const downloading = page.waitForEvent( 'download' );
	await page.getByRole( 'button', { name: `Export ${ TITLE }` } ).click();
	const download = await downloading;
	expect( download.suggestedFilename() ).toMatch(
		/^emcp-snippet-\d+\.json$/
	);
	// Upload under the real .json name (the download's temp path has none).
	await page.getByLabel( 'Bundle file' ).setInputFiles( {
		name: download.suggestedFilename(),
		mimeType: 'application/json',
		buffer: fs.readFileSync( await download.path() ),
	} );
	await expect(
		page.getByText( 'Imported as a new inactive draft.' )
	).toBeVisible();
	await expect(
		page.getByRole( 'switch', { name: `Activate ${ TITLE }` } )
	).toHaveCount( 2 );

	// Delete both copies through the UI.
	for ( let i = 0; i < 2; i++ ) {
		await page
			.getByRole( 'button', { name: `More actions for ${ TITLE }` } )
			.first()
			.click();
		await page.getByRole( 'menuitem', { name: 'Delete' } ).click();
		await page
			.getByRole( 'dialog', { name: `Delete ${ TITLE }?` } )
			.getByRole( 'button', { name: 'Delete' } )
			.click();
		await expect(
			page.getByRole( 'switch', { name: `Activate ${ TITLE }` } )
		).toHaveCount( 1 - i );
	}
} );

test( 'Snippets: search sends one request after the pause', async ( {
	page,
} ) => {
	await page.goto( `${ SANDBOX }&view=snippets` );
	await ready( page );
	const seen = [];
	page.on( 'request', ( r ) => {
		if (
			/admin\/sandbox\/snippets[^/]*search=/.test(
				decodeURIComponent( r.url() )
			)
		) {
			seen.push( r.url() );
		}
	} );
	await page
		.getByRole( 'searchbox', { name: 'Search snippets' } )
		.pressSequentially( 'E2E', { delay: 40 } );
	await page.waitForTimeout( 900 );
	expect( seen ).toHaveLength( 1 );
} );

test( 'Widgets and Blocks: lists, code drawer, block preview and axe', async ( {
	page,
} ) => {
	for ( const view of [ 'widgets', 'blocks' ] ) {
		await page.goto( `${ SANDBOX }&view=${ view }` );
		await ready( page );
		await axe( page, view );
		const code = page
			.getByRole( 'button', { name: /^View code of / } )
			.first();
		if ( await code.count() ) {
			await code.click();
			await expect( page.getByRole( 'dialog' ) ).toBeVisible();
			await expect(
				page.getByRole( 'dialog' ).locator( 'pre' ).first()
			).toBeVisible();
			await axe( page, `${ view } code drawer` );
			await page.keyboard.press( 'Escape' );
		}
		if ( 'blocks' === view ) {
			const preview = page
				.getByRole( 'button', { name: /^Preview / } )
				.first();
			if ( await preview.count() ) {
				await preview.click();
				const frame = page.getByTitle( 'Block preview' );
				const error = page
					.getByRole( 'dialog' )
					.locator( '.eui-notice--danger' );
				await expect( frame.or( error ) ).toBeVisible();
				if ( await frame.count() ) {
					await expect( frame ).toHaveAttribute( 'sandbox', '' );
				}
				await page.keyboard.press( 'Escape' );
			}
		}
	}
} );

test( 'Export: reserved slug blocks the build; axe', async ( { page } ) => {
	await page.goto( SANDBOX );
	await ready( page );
	const before = await rest( page, 'GET', '/emcp-tools/v1/admin/modules' );
	const wasOn = ( before.modules || [] ).some(
		( m ) => 'plugin-export' === m.id && m.active
	);
	if ( ! wasOn ) {
		await rest( page, 'POST', '/emcp-tools/v1/admin/modules', {
			activate: [ 'plugin-export' ],
			deactivate: [],
		} );
	}
	try {
		await page.goto( `${ SANDBOX }&view=export` );
		await ready( page );
		await expect(
			page.getByRole( 'heading', { level: 1, name: 'Export as plugin' } )
		).toBeVisible();
		await axe( page, 'export' );
		const slug = page.getByLabel( 'Slug' );
		await slug.fill( 'emcp-tools' );
		await expect(
			page.getByText(
				'The slug "emcp-tools" is reserved for EMCP Tools itself. Choose another.'
			)
		).toBeVisible();
		await expect(
			page.getByRole( 'button', { name: 'Build and download ZIP' } )
		).toBeDisabled();
	} finally {
		if ( ! wasOn ) {
			await rest( page, 'POST', '/emcp-tools/v1/admin/modules', {
				activate: [],
				deactivate: [ 'plugin-export' ],
			} );
		}
	}
} );
