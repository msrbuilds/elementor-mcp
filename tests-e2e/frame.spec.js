/**
 * Browser smoke of the admin frame (spec 14). See playwright.config.js for
 * the environment it needs.
 */
const fs = require( 'fs' );
const { test, expect } = require( '@playwright/test' );
const AxeBuilder = require( '@axe-core/playwright' ).default;

const { EMCP_E2E_URL, EMCP_E2E_USER, EMCP_E2E_PASS, EMCP_E2E_COOKIES } =
	process.env;
const hasLogin = !! ( EMCP_E2E_USER && EMCP_E2E_PASS );
const hasCookies = !! EMCP_E2E_COOKIES;

test.skip(
	! EMCP_E2E_URL || ( ! hasLogin && ! hasCookies ),
	'Set EMCP_E2E_URL and either EMCP_E2E_USER + EMCP_E2E_PASS or EMCP_E2E_COOKIES'
);

test.beforeEach( async ( { page, context } ) => {
	if ( hasCookies ) {
		const c = JSON.parse( fs.readFileSync( EMCP_E2E_COOKIES, 'utf8' ) );
		const base = {
			domain: c.host,
			httpOnly: true,
			secure: c.secure,
			sameSite: 'Lax',
		};
		await context.addCookies( [
			{
				...base,
				name: c.auth[ 0 ],
				value: c.auth[ 1 ],
				path: '/wp-admin',
			},
			{ ...base, name: c.logged[ 0 ], value: c.logged[ 1 ], path: '/' },
		] );
		return;
	}
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', EMCP_E2E_USER );
	await page.fill( '#user_pass', EMCP_E2E_PASS );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );
} );

function watchConsole( page ) {
	const errors = [];
	page.on( 'console', ( m ) => {
		if ( 'error' === m.type() ) {
			errors.push( m.text() );
		}
	} );
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	return errors;
}

const ignorable = ( e ) => /favicon|net::ERR_BLOCKED/i.test( e );

test( 'every sidebar screen renders in the frame without console errors', async ( {
	page,
} ) => {
	const errors = watchConsole( page );
	await page.goto( '/wp-admin/admin.php?page=emcp-tools' );
	const links = await page.$$eval(
		'.eui-frame-nav a[data-emcp-nav]',
		( as ) =>
			as
				.map( ( a ) => a.getAttribute( 'href' ) )
				.filter( ( h ) => h.includes( 'page=emcp-tools' ) )
	);
	expect( links.length ).toBeGreaterThan( 10 );
	for ( const href of links ) {
		await page.goto( href );
		await expect( page.locator( '.eui-frame__sidebar' ) ).toBeVisible();
		await expect(
			page.locator( '.eui-frame__topbar [aria-current="page"]' )
		).toBeVisible();
		const overflow = await page.evaluate(
			() =>
				document.documentElement.scrollWidth >
				document.documentElement.clientWidth
		);
		expect( overflow, `horizontal scroll on ${ href }` ).toBe( false );
		await expect(
			page.locator( '[data-emcp-recovery]:not([hidden])' )
		).toHaveCount( 0 );
	}
	expect( errors.filter( ( e ) => ! ignorable( e ) ) ).toEqual( [] );
} );

test( 'frame chrome passes axe', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=emcp-tools-tools' );
	const results = await new AxeBuilder( { page } )
		.include( '.eui-frame__sidebar' )
		.include( '.eui-frame__topbar' )
		.include( '.eui-frame-promo' )
		.analyze();
	expect( results.violations ).toEqual( [] );
} );

test( 'frame controls have at least 32px hit areas', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=emcp-tools-tools' );
	const small = await page.$$eval(
		'.eui-frame a, .eui-frame button',
		( els ) =>
			els
				.filter(
					( el ) => el.offsetParent && ! el.closest( '.emcp-legacy' )
				)
				.map( ( el ) => {
					// Controls may extend their hit area with an absolutely
					// positioned ::before (Toggle, IconButton); count it.
					const r = el.getBoundingClientRect();
					const b = window.getComputedStyle( el, '::before' );
					const px = ( v ) => Math.min( 0, parseFloat( v ) || 0 );
					const pseudo =
						'none' !== b.content && 'absolute' === b.position;
					return {
						el: el.outerHTML.slice( 0, 80 ),
						w:
							r.width -
							( pseudo ? px( b.left ) + px( b.right ) : 0 ),
						h:
							r.height -
							( pseudo ? px( b.top ) + px( b.bottom ) : 0 ),
					};
				} )
				.filter( ( { w, h } ) => w < 32 || h < 32 )
				.map( ( { el } ) => el )
	);
	expect( small ).toEqual( [] );
} );

test( 'deep link highlights the parent and shows the child crumb', async ( {
	page,
} ) => {
	await page.goto(
		'/wp-admin/admin.php?page=emcp-tools-widgets&view=snippets'
	);
	await expect(
		page.locator( '.eui-frame-nav [aria-current="page"]' )
	).toContainText( 'Sandbox' );
	await expect( page.locator( '.eui-frame-crumbs' ) ).toContainText(
		'PHP Snippets'
	);
} );

test( 'palette opens with Ctrl+K and navigates', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=emcp-tools' );
	await page.waitForFunction( () => !! window.emcpShell );
	await page.keyboard.press( 'Control+k' );
	// WordPress core has its own Ctrl+K palette; ours must be the one that opens.
	await expect(
		page.getByRole( 'combobox', { name: 'Search commands and settings' } )
	).toHaveCount( 0 );
	await page
		.getByRole( 'combobox', {
			name: 'Search screens, tools, settings, prompts and templates',
		} )
		.fill( 'redirect' );
	await page.keyboard.press( 'Enter' );
	await page.waitForURL( /page=emcp-tools-redirects/ );
} );

test( 'frame stays usable when the shell bundle is blocked', async ( {
	page,
} ) => {
	await page.route( /assets\/admin\/build\/shell\.js/, ( route ) =>
		route.abort()
	);
	await page.goto( '/wp-admin/admin.php?page=emcp-tools' );
	await page.click( '.eui-frame-nav a[href*="page=emcp-tools-modules"]' );
	await page.waitForURL( /page=emcp-tools-modules/ );
	await expect( page.locator( '.eui-frame__sidebar' ) ).toBeVisible();
} );

test( 'frame keeps its layout with an admin notice', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=emcp-tools-tools' );
	await page.evaluate( () => {
		const n = document.createElement( 'div' );
		n.className = 'notice notice-warning';
		const p = document.createElement( 'p' );
		p.textContent = 'Another plugin says hello.';
		n.appendChild( p );
		document.querySelector( '#wpbody-content' ).prepend( n );
	} );
	const sidebar = await page.locator( '.eui-frame__sidebar' ).boundingBox();
	const main = await page.locator( '.eui-frame__main' ).boundingBox();
	expect( main.x ).toBeGreaterThanOrEqual( sidebar.x + sidebar.width - 1 );
	const overflow = await page.evaluate(
		() =>
			document.documentElement.scrollWidth >
			document.documentElement.clientWidth
	);
	expect( overflow ).toBe( false );
} );

test( 'legacy Redirects screen has no console errors and its toggles respond', async ( {
	page,
} ) => {
	const errors = watchConsole( page );
	await page.goto( '/wp-admin/admin.php?page=emcp-tools-redirects' );
	const toggle = page
		.locator( '.emcp-legacy input[type="checkbox"]' )
		.first();
	const before = await toggle.isChecked();
	await toggle.click( { force: true } );
	expect( await toggle.isChecked() ).toBe( ! before );
	expect( errors.filter( ( e ) => ! ignorable( e ) ) ).toEqual( [] );
} );
