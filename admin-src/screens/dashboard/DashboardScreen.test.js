import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { AppProviders } from '@emcp/ui';
import { DashboardScreen } from './DashboardScreen';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const day = ( date, kept, rolled ) => ( {
	date,
	kept,
	rolled,
	calls: 0,
	errors: 0,
} );

const activity = ( range = 14 ) => ( {
	range,
	days: Array.from( { length: range }, ( _, i ) =>
		day( `2026-09-${ String( i + 1 ).padStart( 2, '0' ) }`, i, 0 )
	),
	kpis: [
		{
			key: 'changes',
			label: 'Changes recorded',
			value: 196,
			sub: 'Last 14 days',
		},
		{
			key: 'rolled',
			label: 'Rolled back',
			value: 58,
			sub: '30% of changes',
		},
		{ key: 'calls', label: 'Tool calls', value: 10, sub: 'Last 14 days' },
		{ key: 'errors', label: 'Errors', value: 1, sub: '10% of calls' },
	],
	mostUsed: [ { tool: 'emcp-tools/get-post', count: 32 } ],
} );

const row = ( over = {} ) => ( {
	id: 'c1',
	seq: 1,
	type: 'edit',
	kind: 'content',
	title: 'Home',
	description: 'post:1',
	tool: 'update-post',
	time: Math.floor( Date.now() / 1000 ) - 60,
	rolledBack: false,
	diffable: false,
	reversible: true,
	reason: '',
	client: 'Claude Desktop',
	...over,
} );

const data = ( over = {} ) => ( {
	header: {
		premium: false,
		aiChatUrl: '',
		connectUrl: '/c',
		site: 'msrplugins.test',
	},
	health: {
		server: {
			online: true,
			label: 'MCP server online',
			sub: 'Abilities API enabled',
		},
		clients: {
			count: 1,
			label: '1 client connected',
			sub: 'Claude Desktop · OAuth',
		},
		tools: {
			enabled: 116,
			total: 191,
			disabled: 75,
			label: '116 of 191 tools',
			sub: '75 disabled',
		},
		version: {
			current: '3.18.0',
			latest: '3.18.0',
			update: false,
			url: '/p',
			label: 'v3.18.0',
			sub: 'You’re on the latest version',
		},
	},
	activity: activity(),
	recent: [ row() ],
	attention: [
		{
			id: 'tools-disabled',
			icon: 'wrench',
			title: '75 tools are disabled',
			body: 'Your AI can’t call them.',
			action_label: 'Review tools',
			action_url: '/t',
			severity: 'info',
		},
	],
	features: [
		{
			icon: 'wrench',
			title: 'MCP Tools',
			desc: 'Tools',
			url: '/t',
			pro: false,
		},
	],
	videos: [
		{
			title: 'V1',
			channel: 'WP Academy',
			url: 'https://youtube.com/watch?v=x',
			thumb: 'https://i.ytimg.com/vi/x/hqdefault.jpg',
		},
	],
	tutorials: 'https://emcptools.com/tutorials',
	help: [
		{
			icon: 'scroll-text',
			title: 'Documentation',
			desc: 'Guides',
			url: 'https://emcptools.com/docs',
		},
	],
	cloud: { connected: false, url: '/c&section=cloud' },
	historyUrl: '/h',
	logUrl: '/l',
	...over,
} );

const mount = ( d = data() ) =>
	render(
		<AppProviders>
			<DashboardScreen data={ d } />
		</AppProviders>
	);

describe( 'DashboardScreen', () => {
	beforeEach( () => apiFetch.mockReset() );

	it( 'renders the health strip, KPIs and every section', () => {
		mount();
		expect(
			screen.getByRole( 'heading', { level: 1, name: 'Dashboard' } )
		).toBeInTheDocument();
		expect( screen.getByText( '116 of 191 tools' ) ).toBeInTheDocument();
		expect( screen.getByText( '196' ) ).toBeInTheDocument();
		expect(
			screen.getByText( '75 tools are disabled' )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: /Explore EMCP Cloud/ } )
		).toHaveAttribute( 'href', '/c&section=cloud' );
		expect(
			screen.getByRole( 'heading', { name: 'Jump to a feature' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: /Connect a client/ } )
		).toHaveAttribute( 'href', '/c' );
		expect(
			screen.queryByRole( 'link', { name: /Open AI Chat/ } )
		).toBeNull();
	} );

	it( 'refetches activity when the range changes and drops a stale answer', async () => {
		let resolve7;
		apiFetch.mockImplementationOnce(
			() => new Promise( ( r ) => ( resolve7 = r ) )
		);
		apiFetch.mockResolvedValueOnce( {
			activity: activity( 30 ),
			recent: [ row() ],
			attention: [],
		} );
		mount();
		await userEvent.click( screen.getByRole( 'radio', { name: '7d' } ) );
		await userEvent.click( screen.getByRole( 'radio', { name: '30d' } ) );
		await waitFor( () => expect( apiFetch ).toHaveBeenCalledTimes( 2 ) );
		expect( apiFetch.mock.calls[ 0 ][ 0 ].path ).toContain( 'range=7' );
		expect( apiFetch.mock.calls[ 1 ][ 0 ].path ).toContain( 'range=30' );
		await waitFor( () =>
			expect(
				screen.getByText( 'Nothing needs your attention' )
			).toBeInTheDocument()
		);
		resolve7( { activity: activity( 7 ), recent: [], attention: [] } );
		await waitFor( () =>
			expect( screen.getByText( 'Home' ) ).toBeInTheDocument()
		);
		expect( screen.getByRole( 'radio', { name: '30d' } ) ).toBeChecked();
	} );

	it( 'undoes a change and asks again on a conflict', async () => {
		apiFetch
			.mockRejectedValueOnce( {
				code: 'conflict',
				message: 'Edited since',
			} )
			.mockResolvedValueOnce( { result: {} } )
			.mockResolvedValueOnce( {
				activity: activity(),
				recent: [ row( { rolledBack: true } ) ],
				attention: [],
			} );
		mount();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Undo: Home' } )
		);
		const dialog = await screen.findByRole( 'dialog' );
		await userEvent.click(
			within( dialog ).getByRole( 'button', { name: 'Undo anyway' } )
		);
		await waitFor( () =>
			expect( apiFetch.mock.calls[ 1 ][ 0 ] ).toMatchObject( {
				path: '/emcp-tools/v1/admin/history/c1/undo',
				method: 'POST',
				data: { force: true },
			} )
		);
		const recent = screen.getByRole( 'list', { name: 'Recent changes' } );
		expect(
			await within( recent ).findByText( 'Rolled back' )
		).toBeInTheDocument();
		expect(
			within( recent ).queryByRole( 'button', { name: 'Undo: Home' } )
		).toBeNull();
	} );

	it( 'dismisses an attention item', async () => {
		apiFetch.mockResolvedValueOnce( { attention: [] } );
		mount();
		await userEvent.click(
			screen.getByRole( 'button', {
				name: 'Dismiss: 75 tools are disabled',
			} )
		);
		expect( apiFetch.mock.calls[ 0 ][ 0 ] ).toMatchObject( {
			path: '/emcp-tools/v1/admin/dashboard/attention/tools-disabled/dismiss',
			method: 'POST',
		} );
		expect(
			await screen.findByText( 'Nothing needs your attention' )
		).toBeInTheDocument();
	} );

	it( 'shows empty states on an empty site', () => {
		mount(
			data( {
				recent: [],
				attention: [],
				activity: { ...activity(), mostUsed: [] },
			} )
		);
		expect(
			screen.getByText( 'No changes recorded yet' )
		).toBeInTheDocument();
		expect( screen.getByText( 'No tool calls yet' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'Nothing needs your attention' )
		).toBeInTheDocument();
	} );
} );
