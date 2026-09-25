import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { AppProviders } from '@emcp/ui';
import { FirstCallStep } from './FirstCallStep';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const props = {
	setupId: 'set_1',
	clientLabel: 'Cursor',
	method: 'app',
	conn: { username: 'admin', password: 'pw' },
	onRestart: jest.fn(),
	interval: 3000,
	maxPolls: 3,
};
const mount = () =>
	render(
		<AppProviders>
			<FirstCallStep { ...props } />
		</AppProviders>
	);

describe( 'FirstCallStep', () => {
	beforeEach( () => {
		jest.useFakeTimers();
		apiFetch.mockReset();
	} );
	afterEach( () => jest.useRealTimers() );

	const tick = async () => {
		await act( async () => {
			jest.advanceTimersByTime( 3000 );
		} );
	};

	it( 'polls the bound setup and shows success', async () => {
		apiFetch
			.mockResolvedValueOnce( { waiting: true, seen_failures: [] } )
			.mockResolvedValueOnce( {
				matched: true,
				method: 'tools/list',
				tool: '',
				client: 'Cursor',
				time: 1700000000,
			} );
		mount();
		await act( async () => {} );
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/emcp-tools/v1/admin/connection/first-call?setup=set_1',
			} )
		);
		expect(
			screen.getByText( /Waiting for Cursor to call the server/ )
		).toBeInTheDocument();
		await tick();
		expect( screen.getByText( /Cursor is connected/ ) ).toBeInTheDocument();
		expect( screen.getByText( /tools\/list/ ) ).toBeInTheDocument();
		await tick();
		expect( apiFetch ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'shows a failed call from the expected client', async () => {
		apiFetch.mockResolvedValue( {
			waiting: true,
			seen_failures: [
				{
					method: 'initialize',
					failure_reason: 'Unauthorized',
					time: 1,
				},
			],
		} );
		mount();
		await act( async () => {} );
		expect(
			screen.getByText(
				/We saw a call from Cursor, but it failed: Unauthorized/
			)
		).toBeInTheDocument();
	} );

	it( 'stops polling on unmount and times out after five minutes', async () => {
		apiFetch.mockResolvedValue( { waiting: true, seen_failures: [] } );
		const { unmount } = mount();
		await act( async () => {} );
		await tick();
		await tick();
		expect( apiFetch ).toHaveBeenCalledTimes( 3 );
		await tick();
		expect( screen.getByText( /No call yet/ ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Run a server test' } )
		).toBeInTheDocument();
		await tick();
		expect( apiFetch ).toHaveBeenCalledTimes( 3 );
		unmount();
		await tick();
		expect( apiFetch ).toHaveBeenCalledTimes( 3 );
	} );

	it( 'an expired setup offers a restart', async () => {
		apiFetch.mockRejectedValue( {
			code: 'emcp_setup_gone',
			message: 'This setup has expired.',
		} );
		mount();
		await act( async () => {} );
		await userEvent
			.setup( { advanceTimers: jest.advanceTimersByTime } )
			.click( screen.getByRole( 'button', { name: 'Start again' } ) );
		expect( props.onRestart ).toHaveBeenCalled();
	} );

	it( 'the server test for an application password posts the credentials', async () => {
		apiFetch
			.mockResolvedValueOnce( { waiting: true, seen_failures: [] } )
			.mockResolvedValue( { waiting: true, seen_failures: [] } );
		mount();
		for ( let i = 0; i < 4; i++ ) {
			await tick();
		}
		apiFetch.mockResolvedValueOnce( {
			ok: true,
			message: 'Handshake OK',
			tool_count: 12,
		} );
		await userEvent
			.setup( { advanceTimers: jest.advanceTimersByTime } )
			.click(
				screen.getByRole( 'button', { name: 'Run a server test' } )
			);
		expect( apiFetch ).toHaveBeenLastCalledWith(
			expect.objectContaining( {
				path: '/emcp-tools/v1/admin/connection/test',
				data: { username: 'admin', password: 'pw' },
			} )
		);
		expect( await screen.findByText( 'Handshake OK' ) ).toBeInTheDocument();
	} );
} );
