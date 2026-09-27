import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { AppProviders } from '@emcp/ui';
import { HistoryScreen } from './HistoryScreen';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const row = ( over = {} ) => ( {
	id: 'aaaa01',
	type: 'edit',
	kind: 'design',
	title: 'Edited Elementor page #12',
	description: 'Home',
	tool: 'page-edit',
	time: 1790000000,
	rolledBack: false,
	diffable: true,
	reversible: true,
	reason: '',
	...over,
} );

const session = ( over = {} ) => ( {
	key: 's:chat-1-abcd',
	title: 'Edited Elementor page #12',
	client: 'AI Chat',
	userLogin: 'admin',
	start: 1790000000,
	end: 1790000300,
	count: 2,
	shown: 2,
	open: 2,
	rows: [
		row(),
		row( {
			id: 'aaaa02',
			title: 'Renamed the site',
			type: 'settings',
			kind: 'settings',
			diffable: false,
		} ),
	],
	...over,
} );

const data = ( over = {} ) => ( {
	sessions: [ session() ],
	nextCursor: null,
	totals: { changes: 2, rolledBack: 0 },
	retention: 90,
	retentionOptions: [ 30, 90, 180, 365 ],
	clients: [ 'AI Chat', 'WP-CLI' ],
	banners: [],
	filters: { search: '', kind: '', client: '', range: '' },
	...over,
} );

function mount( d = data() ) {
	window.history.replaceState(
		{},
		'',
		'/wp-admin/admin.php?page=emcp-tools-history'
	);
	return render(
		<AppProviders>
			<HistoryScreen data={ d } />
		</AppProviders>
	);
}

const undoButtons = () => screen.getAllByRole( 'button', { name: /^Undo:/ } );

describe( 'HistoryScreen', () => {
	beforeEach( () => apiFetch.mockReset() );

	it( 'shows sessions, rows, totals and retention', () => {
		mount();
		expect(
			screen.getByRole( 'heading', { level: 1, name: 'History' } )
		).toBeInTheDocument();
		expect(
			screen.getByText( '2 changes, 0 rolled back' )
		).toBeInTheDocument();
		expect( screen.getByLabelText( 'Retention' ) ).toHaveValue( '90' );
		const group = screen.getByRole( 'group', {
			name: /Edited Elementor page #12/,
		} );
		expect(
			within( group ).getByText( 'Renamed the site' )
		).toBeInTheDocument();
		expect(
			within( group ).getAllByRole( 'button', {
				name: /View difference/,
			} )
		).toHaveLength( 1 );
	} );

	it( 'filters by kind through the server and keeps it in the URL', async () => {
		apiFetch.mockResolvedValue( data( { sessions: [] } ) );
		mount();
		await userEvent.click(
			screen.getByRole( 'radio', { name: 'Design' } )
		);
		await waitFor( () => expect( apiFetch ).toHaveBeenCalled() );
		expect( apiFetch.mock.calls[ 0 ][ 0 ].path ).toBe(
			'/emcp-tools/v1/admin/history?kind=design'
		);
		expect( window.location.search ).toContain( 'kind=design' );
		expect(
			await screen.findByText( 'No changes match these filters.' )
		).toBeInTheDocument();
	} );

	it( 'undoes one change and reconciles from the response', async () => {
		apiFetch.mockResolvedValue( {
			result: { rolled_back: 'aaaa01' },
			history: data( {
				sessions: [
					session( {
						open: 1,
						rows: [
							row( { rolledBack: true, reversible: false } ),
						],
					} ),
				],
			} ),
		} );
		mount();
		await userEvent.click( undoButtons()[ 0 ] );
		expect( apiFetch.mock.calls[ 0 ][ 0 ] ).toMatchObject( {
			path: '/emcp-tools/v1/admin/history/aaaa01/undo',
			method: 'POST',
			data: { force: false, limit: 20 },
		} );
		expect( await screen.findByText( 'Rolled back' ) ).toBeInTheDocument();
	} );

	it( 'asks before forcing an undo that conflicts', async () => {
		apiFetch
			.mockRejectedValueOnce( {
				code: 'conflict',
				message: 'This target has changed since the recorded change.',
			} )
			.mockResolvedValueOnce( { result: {}, history: data() } );
		mount();
		await userEvent.click( undoButtons()[ 0 ] );
		const dialog = await screen.findByRole( 'dialog', {
			name: 'This changed since',
		} );
		await userEvent.click(
			within( dialog ).getByRole( 'button', { name: 'Undo anyway' } )
		);
		await waitFor( () => expect( apiFetch ).toHaveBeenCalledTimes( 2 ) );
		expect( apiFetch.mock.calls[ 1 ][ 0 ].data.force ).toBe( true );
	} );

	it( 'cancelling a forced undo sends nothing more', async () => {
		apiFetch.mockRejectedValueOnce( {
			code: 'conflict',
			message: 'Changed.',
		} );
		mount();
		await userEvent.click( undoButtons()[ 0 ] );
		const dialog = await screen.findByRole( 'dialog', {
			name: 'This changed since',
		} );
		await userEvent.click(
			within( dialog ).getByRole( 'button', { name: 'Cancel' } )
		);
		await waitFor( () =>
			expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument()
		);
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'undoes a whole session after confirming and reports where it stopped', async () => {
		apiFetch.mockResolvedValue( {
			result: {
				undone: [ 'aaaa02' ],
				remaining: 1,
				total: 2,
				stoppedTitle: 'Home',
				reason: 'changed since',
			},
			history: data(),
		} );
		mount();
		await userEvent.click(
			screen.getByRole( 'button', { name: /Undo whole session/ } )
		);
		const dialog = await screen.findByRole( 'dialog' );
		await userEvent.click(
			within( dialog ).getByRole( 'button', { name: 'Undo session' } )
		);
		expect( apiFetch.mock.calls[ 0 ][ 0 ] ).toMatchObject( {
			path: '/emcp-tools/v1/admin/history/sessions/undo',
			data: { session: 's:chat-1-abcd', confirm: true },
		} );
		expect(
			await screen.findByText(
				"Undid 1 of 2 changes; stopped at 'Home': changed since"
			)
		).toBeInTheDocument();
	} );

	it( 'disables Undo whole session when nothing is open', () => {
		mount( data( { sessions: [ session( { open: 0 } ) ] } ) );
		expect(
			screen.getByRole( 'button', { name: /Undo whole session/ } )
		).toBeDisabled();
	} );

	it( 'disables Undo whole session when only audit rows are open', () => {
		mount( data( { sessions: [ session( { open: 1, undoable: 0 } ) ] } ) );
		expect(
			screen.getByRole( 'button', { name: /Undo whole session/ } )
		).toBeDisabled();
	} );

	it( 'ignores an older page that lands after the filters changed', async () => {
		let finishOlder;
		apiFetch
			.mockImplementationOnce(
				() =>
					new Promise( ( resolve ) => {
						finishOlder = resolve;
					} )
			)
			.mockResolvedValueOnce(
				data( {
					sessions: [
						session( { key: 's:design', title: 'Design only' } ),
					],
				} )
			);
		mount( data( { nextCursor: 17 } ) );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Load older sessions' } )
		);
		await userEvent.click(
			screen.getByRole( 'radio', { name: 'Design' } )
		);
		expect(
			await screen.findByRole( 'group', { name: /Design only/ } )
		).toBeInTheDocument();
		await act( async () => {
			finishOlder(
				data( {
					sessions: [
						session( { key: 's:older', title: 'Older work' } ),
					],
					nextCursor: 3,
				} )
			);
		} );
		expect(
			screen.queryByRole( 'group', { name: /Older work/ } )
		).toBeNull();
		expect(
			screen.queryByRole( 'button', { name: 'Load older sessions' } )
		).toBeNull();
	} );

	it( 'shows more rows of a long session', async () => {
		apiFetch.mockResolvedValue( {
			rows: [ row( { id: 'bbbb09', seq: 3, title: 'Much older edit' } ) ],
			more: false,
		} );
		mount(
			data( {
				sessions: [
					session( {
						more: true,
						rows: [ row( { seq: 40 } ) ],
					} ),
				],
			} )
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Show more changes' } )
		);
		expect( apiFetch.mock.calls[ 0 ][ 0 ].path ).toBe(
			'/emcp-tools/v1/admin/history/sessions/rows?session=s%3Achat-1-abcd&before=40'
		);
		expect(
			await screen.findByText( 'Much older edit' )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Show more changes' } )
		).toBeNull();
	} );

	it( 'opens the diff in a drawer', async () => {
		apiFetch.mockResolvedValue( { kind: 'text', before: 'a', after: 'b' } );
		mount();
		await userEvent.click(
			screen.getByRole( 'button', { name: /View difference/ } )
		);
		const drawer = await screen.findByRole( 'dialog', {
			name: 'Edited Elementor page #12',
		} );
		expect(
			await within( drawer ).findByRole( 'region', {
				name: 'Difference',
			} )
		).toBeInTheDocument();
		expect( apiFetch.mock.calls[ 0 ][ 0 ].path ).toBe(
			'/emcp-tools/v1/admin/history/aaaa01/diff'
		);
	} );

	it( 'appends older sessions', async () => {
		apiFetch.mockResolvedValue(
			data( {
				sessions: [
					session( { key: 's:older', title: 'Older work' } ),
				],
				nextCursor: null,
			} )
		);
		mount( data( { nextCursor: 17 } ) );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Load older sessions' } )
		);
		expect( apiFetch.mock.calls[ 0 ][ 0 ].path ).toBe(
			'/emcp-tools/v1/admin/history?before=17'
		);
		expect(
			await screen.findByRole( 'group', { name: /Older work/ } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'group', { name: /Edited Elementor page #12/ } )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Load older sessions' } )
		).toBeNull();
	} );

	it( 'saves retention and dismisses a banner', async () => {
		const banners = [
			{
				id: 'unrecorded',
				tone: 'warning',
				title: 'Missing',
				body: '3 changes...',
				dismissible: true,
				action: null,
			},
		];
		apiFetch
			.mockResolvedValueOnce( {
				result: {},
				history: data( { retention: 180, banners } ),
			} )
			.mockResolvedValueOnce( { result: {}, history: data() } );
		mount( data( { banners } ) );
		await userEvent.selectOptions(
			screen.getByLabelText( 'Retention' ),
			'180'
		);
		expect( apiFetch.mock.calls[ 0 ][ 0 ] ).toMatchObject( {
			path: '/emcp-tools/v1/admin/history/retention',
			data: { days: 180 },
		} );
		const notice = screen
			.getByText( '3 changes...' )
			.closest( '.eui-notice' );
		await userEvent.click(
			within( notice ).getByRole( 'button', { name: 'Dismiss' } )
		);
		expect( apiFetch.mock.calls[ 1 ][ 0 ].path ).toBe(
			'/emcp-tools/v1/admin/history/banners/unrecorded/dismiss'
		);
	} );

	it( 'clears history only after confirming', async () => {
		apiFetch.mockResolvedValue( {
			result: { cleared: 2 },
			history: data( {
				sessions: [],
				totals: { changes: 0, rolledBack: 0 },
			} ),
		} );
		mount();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Clear history' } )
		);
		const dialog = await screen.findByRole( 'dialog' );
		await userEvent.type(
			within( dialog ).getByRole( 'textbox' ),
			'CLEAR'
		);
		await userEvent.click(
			within( dialog ).getByRole( 'button', { name: 'Clear history' } )
		);
		expect( apiFetch.mock.calls[ 0 ][ 0 ] ).toMatchObject( {
			path: '/emcp-tools/v1/admin/history',
			method: 'DELETE',
			data: { confirm: true },
		} );
		expect(
			await screen.findByText( 'No changes yet' )
		).toBeInTheDocument();
	} );

	it( 'shows the server message when a write fails', async () => {
		apiFetch.mockRejectedValue( {
			code: 'history_busy',
			message: 'History is busy, try again in a moment.',
		} );
		mount();
		await userEvent.click( undoButtons()[ 0 ] );
		expect(
			await screen.findByText( 'History is busy, try again in a moment.' )
		).toBeInTheDocument();
	} );
} );
