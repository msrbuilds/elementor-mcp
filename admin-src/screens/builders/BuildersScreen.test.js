import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { AppProviders } from '@emcp/ui';
import { BuildersScreen } from './BuildersScreen';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const data = {
	selected: '',
	gutenberg: { available: true, reason: '' },
	builders: [
		{
			id: 'elementor',
			label: 'Elementor',
			description: 'Pages and widgets.',
			requirement: 'Needs Elementor 3.20+',
			available: true,
		},
		{
			id: 'bricks',
			label: 'Bricks',
			description: 'Native elements.',
			requirement: 'Needs Bricks 2.3.13-2.4.x',
			available: false,
		},
	],
	packs: [
		{
			id: 'spectra',
			label: 'Spectra',
			enabled: false,
			available: true,
			requirement: 'Requires the active Spectra plugin.',
		},
		{
			id: 'otter',
			label: 'Otter Blocks',
			enabled: false,
			available: false,
			requirement: 'Needs Otter Blocks 3.2.5-3.2.x',
		},
	],
};

function mount( over = {} ) {
	window.history.replaceState(
		{},
		'',
		'/wp-admin/admin.php?page=emcp-tools-page-builders'
	);
	return render(
		<AppProviders>
			<BuildersScreen data={ { ...data, ...over } } />
		</AppProviders>
	);
}

describe( 'BuildersScreen', () => {
	beforeEach( () => apiFetch.mockReset() );

	it( 'never lists Gutenberg as a builder to pick: it is always on', () => {
		mount( { selected: 'elementor' } );
		expect(
			screen.getByText( 'Gutenberg is always on' )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'radio', { name: /Gutenberg/ } )
		).toBeNull();
	} );

	it( 'marks Gutenberg unavailable while the Classic Editor is active', () => {
		mount( {
			selected: 'elementor',
			gutenberg: {
				available: false,
				reason: 'The Classic Editor plugin is active.',
			},
		} );
		expect(
			screen.getByText( 'Gutenberg is unavailable' )
		).toBeInTheDocument();
		expect( screen.queryByText( 'Gutenberg is always on' ) ).toBeNull();
		expect(
			screen.getByText( 'The Classic Editor plugin is active.' )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'radio', { name: /Gutenberg/ } )
		).toBeNull();
	} );

	it( 'Use Gutenberg only clears the standalone builder', async () => {
		apiFetch.mockResolvedValue( { ...data, selected: '' } );
		mount( { selected: 'elementor' } );
		expect(
			screen.getByRole( 'radio', { name: /Elementor/ } )
		).toBeChecked();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Use Gutenberg only' } )
		);
		expect(
			screen.getByRole( 'radio', { name: /Elementor/ } )
		).not.toBeChecked();
		expect(
			screen.queryByRole( 'button', { name: 'Use Gutenberg only' } )
		).toBeNull();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				data: expect.objectContaining( { builder: '' } ),
			} )
		);
	} );

	it( 'with no builder picked, nothing is checked and each card shows its status', () => {
		mount();
		screen
			.getAllByRole( 'radio' )
			.forEach( ( r ) => expect( r ).not.toBeChecked() );
		expect(
			screen.getByText( 'No standalone builder: only Gutenberg is used.' )
		).toBeInTheDocument();
		expect( screen.getByText( 'Detected' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Not detected' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'radio', { name: /Bricks/ } )
		).toBeDisabled();
		expect(
			screen.getByText( 'Needs Bricks 2.3.13-2.4.x' )
		).toBeInTheDocument();
	} );

	it( 'selecting a detected builder and a pack saves a diff', async () => {
		apiFetch.mockResolvedValue( {
			...data,
			selected: 'elementor',
			packs: [ { ...data.packs[ 0 ], enabled: true }, data.packs[ 1 ] ],
		} );
		mount();
		await userEvent.click(
			screen.getByRole( 'radio', { name: /Elementor/ } )
		);
		await userEvent.click(
			screen.getByRole( 'switch', { name: 'Spectra' } )
		);
		expect( screen.getByText( /2 unsaved changes/ ) ).toBeInTheDocument();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/emcp-tools/v1/admin/builders',
				method: 'POST',
				data: {
					builder: 'elementor',
					packs_enable: [ 'spectra' ],
					packs_disable: [],
				},
			} )
		);
	} );

	it( 'show detected only hides undetected builders', async () => {
		mount();
		await userEvent.click(
			screen.getByRole( 'switch', { name: 'Show detected only' } )
		);
		expect(
			screen.queryByRole( 'radio', { name: /Bricks/ } )
		).not.toBeInTheDocument();
		expect( window.location.search ).toContain( 'detected=1' );
	} );

	it( 'warns when the saved builder is no longer active', () => {
		window.history.replaceState(
			{},
			'',
			'/wp-admin/admin.php?page=emcp-tools-page-builders'
		);
		render(
			<AppProviders>
				<BuildersScreen data={ { ...data, selected: 'bricks' } } />
			</AppProviders>
		);
		expect(
			screen.getByText(
				/Bricks is selected but is not active on this site/
			)
		).toBeInTheDocument();
	} );

	it( 'an unavailable pack is locked with its requirement', () => {
		mount();
		expect(
			screen.getByRole( 'switch', { name: 'Otter Blocks' } )
		).toBeDisabled();
		expect(
			screen.getByText( 'Needs Otter Blocks 3.2.5-3.2.x' )
		).toBeInTheDocument();
	} );
} );
