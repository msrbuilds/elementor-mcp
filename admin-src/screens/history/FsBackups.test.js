import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { AppProviders } from '@emcp/ui';
import { FsBackups, formatBytes } from './FsBackups';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const payload = ( over = {} ) => ( {
	backups: [
		{
			name: '20261120-101010-wp-content-a.php.bak',
			size: 2048,
			time: '2026-11-20T10:10:10+00:00',
		},
		{
			name: '20261119-101010-wp-content-b.php.bak',
			size: 1024,
			time: '2026-11-19T10:10:10+00:00',
		},
	],
	count: 2,
	totalBytes: 3072,
	retention: { days: 30, max_mb: 0 },
	...over,
} );

const mount = () =>
	render(
		<AppProviders>
			<FsBackups />
		</AppProviders>
	);

describe( 'FsBackups', () => {
	beforeEach( () => apiFetch.mockReset() );

	it( 'formats sizes', () => {
		expect( formatBytes( 512 ) ).toBe( '512 B' );
		expect( formatBytes( 2048 ) ).toBe( '2.0 KB' );
		expect( formatBytes( 3 * 1024 * 1024 ) ).toBe( '3.0 MB' );
	} );

	it( 'lists the copies with the total and retention', async () => {
		apiFetch.mockResolvedValueOnce( payload() );
		mount();
		expect(
			await screen.findByRole( 'cell', {
				name: '20261120-101010-wp-content-a.php.bak',
			} )
		).toBeInTheDocument();
		expect( screen.getByText( '2 backups, 3.0 KB' ) ).toBeInTheDocument();
		expect( screen.getByLabelText( 'Keep for (days)' ) ).toHaveValue( 30 );
	} );

	it( 'names the undos a delete costs before deleting', async () => {
		apiFetch
			.mockResolvedValueOnce( payload() )
			.mockResolvedValueOnce( {
				historyLosingUndo: [
					{ id: 'abc123', summary: 'Edited a.php', file: 'x' },
				],
			} )
			.mockResolvedValueOnce( {
				removed: [ '20261120-101010-wp-content-a.php.bak' ],
				bytesFreed: 2048,
				fsBackups: payload( {
					backups: [ payload().backups[ 1 ] ],
					count: 1,
					totalBytes: 1024,
				} ),
			} );
		mount();
		const user = userEvent.setup();
		await screen.findByRole( 'cell', {
			name: '20261120-101010-wp-content-a.php.bak',
		} );
		await user.click(
			screen.getByLabelText(
				'Select 20261120-101010-wp-content-a.php.bak'
			)
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Delete selected' } )
		);
		expect(
			await screen.findByText( /1 History entry loses its undo/ )
		).toBeInTheDocument();
		await user.click( screen.getByRole( 'button', { name: 'Delete' } ) );
		await waitFor( () =>
			expect( screen.getByText( '1 backup, 1.0 KB' ) ).toBeInTheDocument()
		);
		expect( apiFetch.mock.calls[ 2 ][ 0 ].data ).toEqual( {
			names: [ '20261120-101010-wp-content-a.php.bak' ],
			confirm: true,
		} );
	} );
} );
