/**
 * AI Chat in the admin screen and both editor panels (Part 4c), with provider
 * replies mocked (tests-e2e/support/mock-llm.js). Tool calls run for real
 * (list-post-types: read-only and registered whatever the page builder). Every conversation a run saves is deleted, and
 * each editor test works on its own draft page, deleted afterwards.
 * Same environment as frame.spec.js.
 */
const fs = require( 'fs' );
const { test, expect } = require( '@playwright/test' );
const AxeBuilder = require( '@axe-core/playwright' ).default;
const { mockLlm } = require( './support/mock-llm' );

const { EMCP_E2E_URL, EMCP_E2E_COOKIES } = process.env;
test.skip(
	! EMCP_E2E_URL || ! EMCP_E2E_COOKIES,
	'Set EMCP_E2E_URL and EMCP_E2E_COOKIES'
);

const CHAT = '/wp-admin/admin.php?page=emcp-tools-ai-chat';
const KEY = /^[a-z0-9-]{8,64}$/;

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

// Capture tool calls and conversation saves; the saved ones are deleted afterwards.
function recorder( page ) {
	const seen = { execute: [], saves: [] };
	page.on( 'request', ( r ) => {
		if ( 'POST' !== r.method() ) {
			return;
		}
		if ( r.url().includes( '/emcp-tools/v1/execute-ability' ) ) {
			seen.execute.push( r.postDataJSON() );
		}
		if ( /\/emcp-tools\/v1\/conversations(\?|$)/.test( r.url() ) ) {
			seen.saves.push( r );
		}
	} );
	return seen;
}

async function cleanup( page, seen ) {
	const ids = new Set();
	for ( const req of seen.saves ) {
		const res = await req.response();
		const body = res ? await res.json().catch( () => null ) : null;
		if ( body && body.id ) {
			ids.add( body.id );
		}
	}
	for ( const id of ids ) {
		await page.evaluate( async ( cid ) => {
			const cfg =
				window.emcpAiChat || ( window.emcpBoot && window.emcpBoot.data );
			await fetch( cfg.restBase + '/conversations/' + cid, {
				method: 'DELETE',
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': cfg.nonce },
			} );
		}, id );
	}
}

async function axe( page, include ) {
	const results = await new AxeBuilder( { page } ).include( include ).analyze();
	expect(
		results.violations.map(
			( v ) =>
				v.id + ': ' + v.nodes.map( ( n ) => n.target.join( ' ' ) ).join( ', ' )
		)
	).toEqual( [] );
}

// The screen waits for the tool list (/abilities), which is slow on some sites.
async function ready( page ) {
	await expect(
		page.getByRole( 'textbox', { name: 'Message' } )
	).toBeVisible( { timeout: 60000 } );
}

test( 'Admin: greeting, a tool loop with a conversation key, history, settings, axe', async ( {
	page,
}, testInfo ) => {
	test.setTimeout( 120000 );
	const ask = `E2E list my pages ${ testInfo.project.name }`;
	const llm = await mockLlm( page, [
		{ tool: { name: 'list-post-types', input: {} } },
		{ text: 'E2E mocked reply' },
	] );
	const seen = recorder( page );
	await page.goto( CHAT );
	await ready( page );
	await expect(
		page.getByRole( 'heading', { name: /what should we build\?/ } )
	).toBeVisible();
	await expect(
		page.getByRole( 'group', { name: 'Suggested prompts' } ).getByRole( 'button' )
	).toHaveCount( 6 );
	await axe( page, '#emcp-screen' );
	try {
		await page.getByRole( 'textbox', { name: 'Message' } ).fill( ask );
		await page.getByRole( 'button', { name: 'Send' } ).click();
		await expect( page.getByText( 'E2E mocked reply' ) ).toBeVisible( {
			timeout: 30000,
		} );
		// The tool really ran on the site, not only the mocked reply.
		await expect( page.locator( '.emcp-chat-tool.is-ok' ) ).toContainText(
			'list-post-types'
		);
		expect( llm.requests.length ).toBeGreaterThanOrEqual( 2 );
		expect( seen.execute ).toHaveLength( 1 );
		expect( seen.execute[ 0 ].conversation_key ).toMatch( KEY );
		await expect(
			page.getByRole( 'button', { name: ask, exact: true } )
		).toBeVisible();
		const saved = seen.saves.at( -1 ).postDataJSON();
		expect( saved.conversation_key ).toBe(
			seen.execute[ 0 ].conversation_key
		);
		await axe( page, '#emcp-screen' );
		await page.getByRole( 'button', { name: 'Settings' } ).click();
		await expect(
			page.getByRole( 'heading', { name: 'AI Chat settings' } )
		).toBeVisible();
		await axe( page, '#emcp-screen' );
		await page.getByRole( 'button', { name: 'Back to chat' } ).click();
	} finally {
		await cleanup( page, seen );
	}
} );

test( 'Admin: ?prompt= prefills without sending', async ( { page } ) => {
	const llm = await mockLlm( page, [ { text: 'never' } ] );
	await page.goto(
		CHAT + '&prompt=' + encodeURIComponent( 'Build a page for [my shop]' )
	);
	await ready( page );
	await expect( page.getByRole( 'textbox', { name: 'Message' } ) ).toHaveValue(
		'Build a page for [my shop]'
	);
	expect( page.url() ).not.toContain( 'prompt=' );
	expect( llm.requests ).toHaveLength( 0 );
} );

async function tempPage( page, title ) {
	await page.goto( CHAT );
	await ready( page );
	return page.evaluate( async ( t ) => {
		const cfg = window.emcpBoot.data;
		const res = await fetch( '/wp-json/wp/v2/pages', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce,
			},
			body: JSON.stringify( { title: t, status: 'draft' } ),
		} );
		return ( await res.json() ).id;
	}, title );
}

// The page builder setting through the admin REST route. The Elementor panel
// loads only while Elementor is the selected builder.
function builders( page, method, body ) {
	return page.evaluate(
		async ( [ m, b ] ) => {
			const cfg = window.emcpBoot.data;
			const r = await fetch( '/wp-json/emcp-tools/v1/admin/builders', {
				method: m,
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': cfg.nonce,
				},
				body: b ? JSON.stringify( b ) : undefined,
			} );
			return { ok: r.ok, data: await r.json() };
		},
		[ method, body ]
	);
}

// Selects Elementor when it is not selected yet. Returns the builder to put
// back afterwards, null when nothing changed, or false when the saved choice
// could not be put back (a builder that is no longer active: the route
// refuses it), in which case nothing is switched.
async function selectElementor( page ) {
	const { data } = await builders( page, 'GET' );
	const saved = data.selected || '';
	if ( 'elementor' === saved ) {
		return null;
	}
	const known = ( data.builders || [] ).find( ( b ) => b.id === saved );
	if ( '' !== saved && ! ( known && known.available ) ) {
		return false;
	}
	await builders( page, 'POST', {
		builder: 'elementor',
		packs_enable: [],
		packs_disable: [],
	} );
	return saved;
}

async function removePage( page, id ) {
	await page.goto( CHAT );
	await ready( page );
	await page.evaluate( async ( pid ) => {
		const cfg = window.emcpBoot.data;
		await fetch( '/wp-json/wp/v2/pages/' + pid + '?force=true', {
			method: 'DELETE',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': cfg.nonce },
		} );
	}, id );
}

for ( const editor of [ 'elementor', 'gutenberg' ] ) {
	test( `Editor (${ editor }): the panel runs a tool and answers on the shared service`, async ( {
		page,
	}, testInfo ) => {
		// The panel's layout does not follow the viewport, and the builder switch
		// is site-wide, so one project runs these.
		test.skip( '1440' !== testInfo.project.name, 'editor panels run once' );
		test.setTimeout( 180000 );
		const id = await tempPage(
			page,
			`E2E chat ${ editor } ${ testInfo.project.name }`
		);
		const previous =
			'elementor' === editor ? await selectElementor( page ) : null;
		if ( false === previous ) {
			await removePage( page, id );
		}
		test.skip(
			false === previous,
			'The saved page builder is no longer active, so it could not be put back after selecting Elementor. Select Elementor on Page Builders to run this.'
		);
		const seen = recorder( page );
		try {
			await mockLlm( page, [
				{ tool: { name: 'list-post-types', input: {} } },
				{ text: 'Editor mocked reply' },
			] );
			await page.goto(
				`/wp-admin/post.php?post=${ id }&action=${
					'elementor' === editor ? 'elementor' : 'edit'
				}`
			);
			if ( 'gutenberg' === editor ) {
				await page.evaluate(
					() =>
						window.wp &&
						window.wp.data &&
						window.wp.data
							.dispatch( 'core/preferences' )
							.set( 'core/edit-post', 'welcomeGuide', false )
				);
			}
			const fab = page.locator( '#emcp-ai-fab' );
			await expect( fab ).toBeVisible( { timeout: 90000 } );
			await fab.click();
			await expect( page.locator( '#emcp-ai-window' ) ).toBeVisible();
			await expect( page.locator( '#emcp-ai-chat' ) ).toHaveAttribute(
				'data-state',
				'chat',
				{ timeout: 60000 }
			);
			await page.locator( '#emcp-ai-input' ).fill( 'E2E hello from the editor' );
			await page.locator( '#emcp-ai-send' ).click();
			await expect(
				page.locator( '.emcp-ai-msg--assistant' ).last()
			).toContainText( 'Editor mocked reply', { timeout: 30000 } );
			// A new page has no thread: the only tool call is this run's.
			await expect(
				page.locator( '.emcp-ai-tool--ok .emcp-ai-tool-name' )
			).toHaveText( 'list-post-types' );
			expect( seen.execute[ 0 ].conversation_key ).toMatch( KEY );
			await expect.poll( () => seen.saves.length ).toBeGreaterThan( 0 );
			const saved = seen.saves.at( -1 ).postDataJSON();
			// Localized config values are strings, as they were for the old client.
			expect( String( saved.post_id ) ).toBe( String( id ) );
			expect( saved.conversation_key ).toBe(
				seen.execute[ 0 ].conversation_key
			);
		} finally {
			await cleanup( page, seen );
			await removePage( page, id );
			if ( 'string' === typeof previous ) {
				await builders( page, 'POST', {
					builder: previous,
					packs_enable: [],
					packs_disable: [],
				} );
			}
		}
	} );
}
