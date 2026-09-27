import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { AppProviders } from '@emcp/ui';
import { LogScreen } from './LogScreen';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const row = ( over = {} ) => ( {
	key: '100-0',
	ts: 1790000000,
	method: 'tools/call',
	tool: 'emcp-tools-list-pages',
	status: 'success',
	ms: 40,
	reqId: 'req-1',
	client: 'Claude Code',
	session: 'sess-1',
	credential: 'app:abc',
	stage: '',
	reason: '',
	error: '',
	ledger: '',
	...over,
} );

const data = ( over = {} ) => ( {
	rows: [
		row(),
		row( {
			key: '101-1',
			status: 'error',
			ms: 900,
			reqId: 'req-2',
			tool: 'emcp-tools-build-page',
			reason: 'Page not found',
			stage: 'execute',
		} ),
	],
	total: 2,
	page: 1,
	pages: 1,
	status: 'all',
	search: '',
	stats: {
		requests: 2,
		errors: 1,
		medianMs: 470,
		slowest: { ms: 900, tool: 'emcp-tools-build-page' },
		since: 1790000000,
	},
	debug: false,
	timezone: 'Asia/Karachi',
	exportPath: '/emcp-tools/v1/admin/log/export.csv',
	...over,
} );

function mount( d = data() ) {
	window.history.replaceState(
		{},
		'',
		'/wp-admin/admin.php?page=emcp-tools-mcp-log'
	);
	return render(
		<AppProviders>
			<LogScreen data={ d } />
		</AppProviders>
	);
}

describe( 'LogScreen', () => {
	beforeEach( () => apiFetch.mockReset() );

	it( 'shows the stats, the rows and the WP_DEBUG hint', () => {
		mount();
		expect(
			screen.getByRole( 'heading', { level: 1, name: 'MCP Log' } )
		).toBeInTheDocument();
		const stats = document.querySelector( '.emcp-log__stats' );
		expect( within( stats ).getByText( '470 ms' ) ).toBeInTheDocument();
		expect( within( stats ).getByText( '900 ms' ) ).toBeInTheDocument();
		expect(
			within( stats ).getByText( 'emcp-tools-build-page' )
		).toBeInTheDocument();
		expect(
			screen.getByText( 'Turn on WP_DEBUG to keep full error messages.' )
		).toBeInTheDocument();
		const table = screen.getByRole( 'table' );
		expect( within( table ).getAllByText( 'tools/call' ) ).toHaveLength(
			2
		);
		expect( within( table ).getByText( 'Error' ) ).toBeInTheDocument();
	} );

	it( 'filters by status through the server', async () => {
		apiFetch.mockResolvedValue(
			data( { rows: [ data().rows[ 1 ] ], total: 1, status: 'error' } )
		);
		mount();
		await userEvent.click(
			screen.getByRole( 'radio', { name: 'Errors' } )
		);
		expect( apiFetch.mock.calls[ 0 ][ 0 ].path ).toBe(
			'/emcp-tools/v1/admin/log?status=error'
		);
		await waitFor( () =>
			expect( screen.queryByText( 'req-1' ) ).toBeNull()
		);
		expect( window.location.search ).toContain( 'status=error' );
	} );

	it( 'exports with the filters in view through a header-authenticated fetch', async () => {
		const blob = { size: 3 };
		window.URL.createObjectURL = jest.fn( () => 'blob:csv' );
		window.URL.revokeObjectURL = jest.fn();
		const click = jest
			.spyOn( window.HTMLAnchorElement.prototype, 'click' )
			.mockImplementation( () => {} );
		apiFetch
			.mockResolvedValueOnce( data( { status: 'error' } ) )
			.mockResolvedValueOnce( {
				blob: () => Promise.resolve( blob ),
				headers: {
					get: () => 'attachment; filename="emcp-mcp-log-1.csv"',
				},
			} );
		mount();
		await userEvent.click(
			screen.getByRole( 'radio', { name: 'Errors' } )
		);
		expect(
			screen.queryByRole( 'link', { name: 'Export CSV' } )
		).toBeNull();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Export CSV' } )
		);
		await waitFor( () => expect( click ).toHaveBeenCalled() );
		expect( apiFetch.mock.calls[ 1 ][ 0 ] ).toMatchObject( {
			path: '/emcp-tools/v1/admin/log/export.csv?status=error',
			parse: false,
		} );
		expect( window.URL.createObjectURL ).toHaveBeenCalledWith( blob );
		click.mockRestore();
	} );

	it( 'a pending search never overrides a newer status choice', async () => {
		apiFetch.mockResolvedValue( data() );
		mount();
		await userEvent.type(
			screen.getByRole( 'searchbox', { name: 'Search requests' } ),
			'abc'
		);
		await userEvent.click(
			screen.getByRole( 'radio', { name: 'Errors' } )
		);
		await act( () => new Promise( ( r ) => setTimeout( r, 350 ) ) );
		const paths = apiFetch.mock.calls.map( ( c ) => c[ 0 ].path );
		expect( paths[ paths.length - 1 ] ).toBe(
			'/emcp-tools/v1/admin/log?status=error&search=abc'
		);
	} );

	it( 'expands a row to show client and error details', async () => {
		mount();
		const more = screen.getAllByRole( 'button', {
			name: /Show more/,
		} )[ 1 ];
		expect( more ).toHaveAttribute( 'aria-expanded', 'false' );
		await userEvent.click( more );
		expect( more ).toHaveAttribute( 'aria-expanded', 'true' );
		expect( screen.getByText( 'Page not found' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'app:abc', { exact: false } )
		).toBeInTheDocument();
	} );

	it( 'clears only after confirming', async () => {
		apiFetch.mockResolvedValue(
			data( {
				rows: [],
				total: 0,
				stats: {
					requests: 0,
					errors: 0,
					medianMs: 0,
					slowest: null,
					since: 0,
				},
			} )
		);
		mount();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Clear log' } )
		);
		const dialog = await screen.findByRole( 'dialog' );
		expect( apiFetch ).not.toHaveBeenCalled();
		await userEvent.click(
			within( dialog ).getByRole( 'button', { name: 'Clear log' } )
		);
		expect( apiFetch.mock.calls[ 0 ][ 0 ] ).toMatchObject( {
			path: '/emcp-tools/v1/admin/log',
			method: 'DELETE',
			data: { confirm: true },
		} );
		expect(
			await screen.findByText( 'No requests yet' )
		).toBeInTheDocument();
	} );

	it( 'switches the time zone for display only', async () => {
		mount();
		await userEvent.selectOptions(
			screen.getByLabelText( 'Time zone' ),
			'utc'
		);
		expect( apiFetch ).not.toHaveBeenCalled();
		expect(
			screen.getAllByText( '2026-09-21 14:13:20' ).length
		).toBeGreaterThan( 0 );
	} );

	it( 'marks slow requests', () => {
		mount();
		const bars = document.querySelectorAll( '.emcp-log__bar' );
		expect( bars[ 0 ] ).not.toHaveClass( 'is-slow' );
		expect( bars[ 1 ] ).toHaveClass( 'is-slow' );
	} );
} );
