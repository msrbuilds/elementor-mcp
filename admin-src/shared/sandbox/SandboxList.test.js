import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { AppProviders } from '@emcp/ui';
import { SandboxList } from './SandboxList';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const API = '/emcp-tools/v1/admin/sandbox';

const row = ( id, over = {} ) => ( {
	id,
	kind: 'widget',
	title: `Widget ${ id }`,
	ident: `emcp_custom_${ id }`,
	active: true,
	lastError: '',
	updatedTs: id,
	review: { level: 'none', text: 'Nothing flagged' },
	cloud: 'none',
	marketplace: null,
	...over,
} );

const payload = ( over = {} ) => ( {
	type: 'widgets',
	kind: 'widget',
	items: [
		row( 1, { cloud: 'synced' } ),
		row( 2, { active: false, cloud: 'changed' } ),
	],
	counts: { all: 2, active: 1, inactive: 1, review: 0 },
	total: 2,
	page: 1,
	pages: 1,
	perPage: 20,
	query: { status: 'all', search: '', page: 1 },
	cloud: {
		connected: true,
		connectUrl: '/wp-admin/admin.php?page=emcp-tools-connection',
	},
	elementor: true,
	aiChatUrl: '/wp-admin/admin.php?page=emcp-tools-ai-chat',
	canEdit: true,
	backUrl: '/wp-admin/admin.php?page=emcp-tools-widgets',
	...over,
} );

const config = {
	title: 'Widgets',
	tier: 'pro',
	description: 'Custom Elementor widgets.',
	notice: { tone: 'warning', text: 'The plugin compiles these widgets.' },
	noun: { one: 'widget', many: 'widgets' },
	noMatch: 'No widgets match this filter.',
	searchLabel: 'Search widgets',
	nameHeader: 'Widget',
	identHeader: 'Machine name',
	deleteMessage: 'Pages using it will lose it.',
	emptyTitle: 'No widgets yet',
	emptyText: 'Ask your AI agent to create one.',
	emptyPrompt: 'Create an Elementor widget for a pricing table',
};

function mount( data = payload(), cfg = config, search = '' ) {
	window.history.replaceState(
		{},
		'',
		'/wp-admin/admin.php?page=emcp-tools-widgets&view=widgets' + search
	);
	return render(
		<AppProviders>
			<SandboxList data={ data } config={ cfg } />
		</AppProviders>
	);
}

const calls = () => apiFetch.mock.calls.map( ( [ o ] ) => o );
const paths = () => calls().map( ( o ) => o.path );
// The Cloud library count arrives after mount; wait for it so no update lands after the test.
const settled = () =>
	screen.findByRole( 'button', { name: /Cloud library\s*\d/ } );
const libraryReply = ( count = 0 ) => ( {
	connected: true,
	count,
	artifacts: [],
	site: 's',
} );

// The library count request fires on mount when Cloud is connected.
function answer( handlers ) {
	apiFetch.mockImplementation( ( o ) => {
		if ( o.path.startsWith( `${ API }/cloud/library` ) ) {
			return Promise.resolve( libraryReply( handlers.library ?? 0 ) );
		}
		const h = handlers.other;
		return h ? h( o ) : Promise.resolve( payload() );
	} );
}

describe( 'SandboxList', () => {
	beforeEach( () => {
		apiFetch.mockReset();
		window.URL.createObjectURL = jest.fn( () => 'blob:x' );
		window.URL.revokeObjectURL = jest.fn();
	} );

	it( 'renders counts, Cloud labels and the footer', async () => {
		answer( {} );
		mount();
		const group = screen.getByRole( 'radiogroup', { name: 'Filter' } );
		expect(
			within( group ).getByRole( 'radio', { name: 'All 2' } )
		).toBeInTheDocument();
		expect(
			within( group ).getByRole( 'radio', { name: 'Active 1' } )
		).toBeInTheDocument();
		expect(
			within( group ).getByRole( 'radio', { name: 'Inactive 1' } )
		).toBeInTheDocument();
		expect( screen.getByText( 'Synced' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Changed' ) ).toBeInTheDocument();
		expect(
			screen.getByText( '2 widgets · 1 active' )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Sandbox' } )
		).toHaveAttribute(
			'href',
			'/wp-admin/admin.php?page=emcp-tools-widgets'
		);
		expect(
			screen.getByText( 'The plugin compiles these widgets.' )
		).toBeInTheDocument();
		await settled();
	} );

	it( 'search is debounced and only the latest answer is applied', async () => {
		let slow;
		let n = 0;
		answer( {
			other: () => {
				n++;
				if ( 1 === n ) {
					return new Promise( ( r ) => ( slow = r ) );
				}
				return Promise.resolve(
					payload( {
						items: [ row( 9, { title: 'Pricing Table' } ) ],
						counts: { all: 1, active: 1, inactive: 0, review: 0 },
						query: { status: 'all', search: 'Pricing', page: 1 },
					} )
				);
			},
		} );
		mount();
		await userEvent.type(
			screen.getByRole( 'searchbox', { name: 'Search widgets' } ),
			'Pricing'
		);
		await waitFor( () =>
			expect(
				paths().filter( ( p ) => p.includes( 'search=' ) )
			).toHaveLength( 1 )
		);
		expect( paths().filter( ( p ) => p.includes( 'search=' ) )[ 0 ] ).toBe(
			`${ API }/widgets?search=Pricing`
		);
		// A later request (a filter change) answers first; the slow search must not overwrite it.
		await userEvent.click(
			screen.getByRole( 'radio', { name: 'Active 1' } )
		);
		expect(
			await screen.findByText( 'Pricing Table' )
		).toBeInTheDocument();
		await act( async () =>
			slow(
				payload( {
					items: [ row( 5, { title: 'Stale Row' } ) ],
				} )
			)
		);
		expect( screen.queryByText( 'Stale Row' ) ).not.toBeInTheDocument();
	} );

	it( 'toggle posts a real boolean with the current query', async () => {
		answer( {
			other: () =>
				Promise.resolve(
					payload( {
						items: [ row( 1 ), row( 2, { active: true } ) ],
						counts: { all: 2, active: 2, inactive: 0, review: 0 },
						message: 'Switched on.',
					} )
				),
		} );
		mount();
		await userEvent.click(
			screen.getByRole( 'switch', { name: 'Activate Widget 2' } )
		);
		await waitFor( () =>
			expect( paths() ).toContain( `${ API }/widgets/2/status` )
		);
		const sent = calls().find(
			( o ) => o.path === `${ API }/widgets/2/status`
		);
		expect( sent.method ).toBe( 'POST' );
		expect( sent.data ).toEqual( {
			active: true,
			status: 'all',
			search: '',
			page: 1,
		} );
		expect(
			await screen.findByRole( 'radio', { name: 'Active 2' } )
		).toBeInTheDocument();
	} );

	it( 'keeps the query when a toggle removes the row from the filter', async () => {
		answer( {
			other: () =>
				Promise.resolve(
					payload( {
						items: [],
						counts: { all: 2, active: 0, inactive: 2, review: 0 },
						total: 0,
						query: { status: 'active', search: '', page: 1 },
					} )
				),
		} );
		mount(
			payload( {
				items: [ row( 1 ) ],
				counts: { all: 2, active: 1, inactive: 1, review: 0 },
				query: { status: 'active', search: '', page: 1 },
			} ),
			config,
			'&status=active'
		);
		await userEvent.click(
			screen.getByRole( 'switch', { name: 'Activate Widget 1' } )
		);
		expect(
			await screen.findByText( 'No widgets match this filter.' )
		).toBeInTheDocument();
		expect(
			screen.queryByText( 'No widgets yet' )
		).not.toBeInTheDocument();
		const sent = calls().find(
			( o ) => o.path === `${ API }/widgets/1/status`
		);
		expect( sent.data.status ).toBe( 'active' );
	} );

	it( 'delete asks first and sends confirm', async () => {
		answer( {} );
		mount();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'More actions for Widget 1' } )
		);
		await userEvent.click(
			screen.getByRole( 'menuitem', { name: 'Delete' } )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Delete Widget 1?',
		} );
		await userEvent.click(
			within( dialog ).getByRole( 'button', { name: 'Delete' } )
		);
		await waitFor( () =>
			expect(
				calls().find( ( o ) => o.path === `${ API }/widgets/1` )
			).toBeTruthy()
		);
		const sent = calls().find( ( o ) => o.path === `${ API }/widgets/1` );
		expect( sent.method ).toBe( 'DELETE' );
		expect( sent.data.confirm ).toBe( true );
	} );

	it( 'menu offers the right Cloud action', async () => {
		answer( {} );
		mount(
			payload( {
				items: [
					row( 1, { cloud: 'synced' } ),
					row( 2, { cloud: 'changed' } ),
					row( 3, { cloud: 'none' } ),
					row( 4, {
						cloud: 'changed',
						marketplace: {
							slug: 's',
							status: 'published',
							published: true,
							pending: false,
							viewUrl: 'https://x/s',
							publishUrl: '',
						},
					} ),
				],
			} )
		);
		const items = async ( n ) => {
			await userEvent.click(
				screen.getByRole( 'button', {
					name: `More actions for Widget ${ n }`,
				} )
			);
			const names = screen
				.getAllByRole( 'menuitem' )
				.map( ( el ) => el.textContent );
			await userEvent.keyboard( '{Escape}' );
			return names;
		};
		const one = await items( 1 );
		expect( one ).not.toContain( 'Save to Cloud' );
		expect( one ).not.toContain( 'Update in Cloud' );
		expect( await items( 2 ) ).toContain( 'Update in Cloud' );
		expect( await items( 3 ) ).toContain( 'Save to Cloud' );
		const four = await items( 4 );
		expect( four ).toContain( 'Push update to Marketplace' );
		expect( four ).toContain( 'View on Marketplace' );
	} );

	it( 'shows Connect Cloud instead of the Cloud buttons when disconnected', async () => {
		answer( {} );
		mount(
			payload( {
				cloud: {
					connected: false,
					connectUrl:
						'/wp-admin/admin.php?page=emcp-tools-connection',
				},
			} )
		);
		expect(
			screen.queryByRole( 'button', { name: 'Refresh cloud status' } )
		).not.toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Save all to Cloud' } )
		).not.toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Connect Cloud' } )
		).toHaveAttribute(
			'href',
			'/wp-admin/admin.php?page=emcp-tools-connection'
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'More actions for Widget 2' } )
		);
		expect(
			screen.getAllByRole( 'menuitem' ).map( ( el ) => el.textContent )
		).toEqual( [ 'Delete' ] );
		expect(
			paths().filter( ( p ) => p.includes( '/cloud/library' ) )
		).toHaveLength( 0 );
	} );

	it( 'hides the Cloud buttons on an empty list and shows the first-run empty state', async () => {
		answer( {} );
		const empty = payload( {
			items: [],
			counts: { all: 0, active: 0, inactive: 0, review: 0 },
			total: 0,
		} );
		const { unmount } = mount( empty );
		expect( screen.getByText( 'No widgets yet' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'Create an Elementor widget for a pricing table' )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Open AI Chat' } )
		).toHaveAttribute(
			'href',
			'/wp-admin/admin.php?page=emcp-tools-ai-chat'
		);
		expect(
			screen.getByRole( 'button', { name: 'Browse Cloud library' } )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Save all to Cloud' } )
		).not.toBeInTheDocument();
		await settled();
		unmount();
		mount( { ...empty, aiChatUrl: '' } );
		expect(
			screen.queryByRole( 'link', { name: 'Open AI Chat' } )
		).not.toBeInTheDocument();
		await settled();
	} );

	it( 'export downloads the bundle as a file', async () => {
		answer( {
			other: () =>
				Promise.resolve( {
					filename: 'emcp-widget-1.json',
					bundle: { kind: 'widget' },
				} ),
		} );
		const click = jest
			.spyOn( window.HTMLAnchorElement.prototype, 'click' )
			.mockImplementation( function () {
				this.dataset.clicked = this.download;
			} );
		mount();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Export Widget 1' } )
		);
		await waitFor( () =>
			expect( window.URL.createObjectURL ).toHaveBeenCalled()
		);
		expect( paths() ).toContain( `${ API }/widgets/1/export` );
		expect(
			window.URL.createObjectURL.mock.calls[ 0 ][ 0 ]
		).toBeInstanceOf( window.Blob );
		expect( click.mock.contexts[ 0 ].download ).toBe(
			'emcp-widget-1.json'
		);
		click.mockRestore();
	} );

	it( 'the review deep link opens the code drawer', async () => {
		answer( {
			other: ( o ) =>
				o.path === `${ API }/widgets/2`
					? Promise.resolve( {
							row: row( 2 ),
							tabs: [
								{
									id: 'spec',
									label: 'Spec',
									language: 'json',
									value: '{"meta":{}}',
								},
								{
									id: 'php',
									label: 'emcp_custom_2.php',
									language: 'php',
									value: '<?php',
								},
							],
						} )
					: Promise.resolve( payload() ),
		} );
		mount( payload(), config, '&review=2' );
		const drawer = await screen.findByRole( 'dialog', {
			name: 'Widget 2',
		} );
		expect(
			await within( drawer ).findByRole( 'tab', { name: 'Spec' } )
		).toBeInTheDocument();
		expect(
			within( drawer ).getByRole( 'tab', { name: 'emcp_custom_2.php' } )
		).toBeInTheDocument();
		expect(
			within( drawer ).getByText( '{"meta":{}}' )
		).toBeInTheDocument();
	} );

	it( 'import posts the file and reloads this list', async () => {
		answer( {
			other: ( o ) =>
				o.path === `${ API }/import`
					? Promise.resolve( {
							type: 'widgets',
							id: 5,
							message: 'Imported as a new inactive draft.',
						} )
					: Promise.resolve( payload() ),
		} );
		mount();
		const file = new window.File( [ '{}' ], 'b.json', {
			type: 'application/json',
		} );
		await userEvent.upload( screen.getByLabelText( 'Bundle file' ), file );
		await waitFor( () => expect( paths() ).toContain( `${ API }/import` ) );
		const sent = calls().find( ( o ) => o.path === `${ API }/import` );
		expect( sent.method ).toBe( 'POST' );
		expect( sent.body.get( 'bundle' ) ).toBe( file );
		expect(
			await screen.findByText( 'Imported as a new inactive draft.' )
		).toBeInTheDocument();
		await waitFor( () =>
			expect( paths() ).toContain( `${ API }/widgets` )
		);
	} );

	it( 'the library count appears after mount', async () => {
		answer( { library: 20 } );
		mount();
		expect(
			await screen.findByRole( 'button', { name: /Cloud library\s*20/ } )
		).toBeInTheDocument();
		expect( paths() ).toContain( `${ API }/cloud/library?kind=widget` );
	} );

	it( 'rethrow keeps the caller in charge of the error', async () => {
		answer( {
			other: ( o ) =>
				'/p' === o.path
					? Promise.reject( { code: 'x', message: 'Nope' } )
					: Promise.resolve( payload() ),
		} );
		let caught = null;
		const Probe = ( { h } ) => (
			<button
				type="button"
				onClick={ () =>
					h
						.write( 'x', '/p', { rethrow: true } )
						.catch( ( e ) => ( caught = e ) )
				}
			>
				probe
			</button>
		);
		mount( payload(), { ...config, extra: ( h ) => <Probe h={ h } /> } );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'probe' } )
		);
		await waitFor( () =>
			expect( caught ).toEqual( { code: 'x', message: 'Nope' } )
		);
		expect( screen.queryByText( 'Nope' ) ).not.toBeInTheDocument();
		expect(
			paths().filter( ( p ) => p === `${ API }/widgets` )
		).toHaveLength( 0 );
	} );
} );
