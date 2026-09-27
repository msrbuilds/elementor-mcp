/**
 * Dashboard against a real site: every section renders, the range switch
 * refetches, no horizontal scroll, axe is clean. It never undoes or
 * dismisses anything.
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

test( 'Dashboard: sections, range switch, layout and axe', async ( {
	page,
} ) => {
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	await page.goto( '/wp-admin/admin.php?page=emcp-tools' );
	await expect(
		page.getByRole( 'heading', { level: 1, name: 'Dashboard' } )
	).toBeVisible();
	for ( const name of [
		'AI activity',
		'Most used tools',
		'Recent changes',
		'Needs your attention',
		'Jump to a feature',
		'Video guides',
		'Help & resources',
	] ) {
		await expect( page.getByRole( 'heading', { name } ) ).toBeVisible();
	}
	const res = page.waitForResponse(
		( r ) =>
			r.url().includes( 'admin%2Fdashboard' ) ||
			( r.url().includes( 'admin/dashboard' ) &&
				r.url().includes( 'range=30' ) )
	);
	await page.getByRole( 'radio', { name: '30d' } ).click();
	expect( ( await res ).status() ).toBe( 200 );
	await expect( page.getByText( /over the last 30 days/ ) ).toBeVisible();
	const overflow = await page.evaluate(
		() => document.documentElement.scrollWidth > window.innerWidth
	);
	expect( overflow ).toBe( false );
	const results = await new AxeBuilder( { page } )
		.include( '#emcp-screen' )
		.analyze();
	expect( results.violations ).toEqual( [] );
	expect( errors ).toEqual( [] );
} );

test.describe( 'with motion', () => {
	test.use( { contextOptions: { reducedMotion: 'no-preference' } } );

	test( 'Dashboard: numbers count up, bars grow and the range thumb slides', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/admin.php?page=emcp-tools' );
		const kpi = page.locator( '.emcp-dash__kpi-value' ).first();
		await expect( kpi ).toBeVisible();
		// The count settles on the server's number.
		const settled = await page.evaluate(
			() =>
				new Promise( ( resolve ) => {
					let last = '';
					let same = 0;
					const el = document.querySelector(
						'.emcp-dash__kpi-value'
					);
					const t = setInterval( () => {
						same = el.textContent === last ? same + 1 : 0;
						last = el.textContent;
						if ( same >= 5 ) {
							clearInterval( t );
							resolve( last );
						}
					}, 100 );
				} )
		);
		expect( settled ).toMatch( /^\d+$/ );
		await expect( page.locator( '.eui-chart__col' ).first() ).toHaveCSS(
			'animation-name',
			'eui-bar-grow'
		);
		const thumb = page.locator( '.emcp-dash .eui-seg__thumb' );
		await expect( thumb ).toHaveCount( 1 );
		await expect( thumb ).toHaveCSS( 'transition-property', /transform/ );
		const before = await thumb.evaluate( ( e ) => e.style.transform );
		await page.getByRole( 'radio', { name: '7d' } ).click();
		await expect
			.poll( () => thumb.evaluate( ( e ) => e.style.transform ) )
			.not.toBe( before );
		// The thumb ends under the chosen option.
		await page.waitForTimeout( 400 );
		const [ t, o ] = await Promise.all( [
			thumb.boundingBox(),
			page.getByRole( 'radio', { name: '7d' } ).boundingBox(),
		] );
		expect( Math.abs( t.x - o.x ) ).toBeLessThan( 1 );
		expect( Math.abs( t.width - o.width ) ).toBeLessThan( 1 );
	} );
} );
