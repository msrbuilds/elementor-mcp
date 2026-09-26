import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { AppProviders } from '@emcp/ui';
import { ContextScreen } from './ContextScreen';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const data = {
	profile: {
		name: 'MSR Plugins',
		industry: 'Software & SaaS',
		purpose: '',
		voice: [ 'Clear' ],
	},
	industries: [ 'Software & SaaS', 'Other' ],
	voices: [ 'Clear', 'Friendly', 'Technical' ],
	sections: [
		{
			id: 'builder',
			label: 'Page builder',
			icon: 'layout-template',
			summary: 'Elementor 4.2.4',
			enabled: true,
		},
		{
			id: 'theme',
			label: 'Theme',
			icon: 'palette',
			summary: 'Hello Elementor 3.4',
			enabled: true,
		},
		{
			id: 'woocommerce',
			label: 'WooCommerce catalog',
			icon: 'store',
			summary: '142 products, 12 categories',
			enabled: false,
		},
	],
	refreshedAt: 1790000000,
	instructions: 'Always build with Flexbox containers.',
	enabled: true,
	maxChars: 20000,
	preview: '## Environment\n- Theme: Hello Elementor 3.4',
	tokens: 3200,
	budget: 8000,
	memoryUrl: '/wp-admin/admin.php?page=emcp-tools-memory',
};

function mount( d = data ) {
	window.history.replaceState(
		{},
		'',
		'/wp-admin/admin.php?page=emcp-tools-context'
	);
	return render(
		<AppProviders>
			<ContextScreen data={ d } />
		</AppProviders>
	);
}

describe( 'ContextScreen', () => {
	beforeEach( () => apiFetch.mockReset() );

	it( 'shows the profile, detected rows, meter and preview', () => {
		mount();
		expect( screen.getByLabelText( 'Business name' ) ).toHaveValue(
			'MSR Plugins'
		);
		expect(
			screen.getByRole( 'switch', { name: 'Theme' } )
		).toHaveAttribute( 'aria-checked', 'true' );
		expect(
			screen.getByRole( 'switch', { name: 'WooCommerce catalog' } )
		).toHaveAttribute( 'aria-checked', 'false' );
		expect( screen.getByText( /3\.2k of 8k tokens/ ) ).toBeInTheDocument();
		expect(
			screen.getByText( /- Theme: Hello Elementor 3\.4/ )
		).toBeInTheDocument();
	} );

	it( 'a change enables saving and refreshes the preview from the draft, without saving', async () => {
		apiFetch.mockResolvedValue( {
			text: '## Site profile\n- Business: New Co',
			tokens: 10,
			budget: 8000,
		} );
		mount();
		expect(
			screen.getByRole( 'button', { name: 'Save context' } )
		).toBeDisabled();
		const name = screen.getByLabelText( 'Business name' );
		await userEvent.clear( name );
		await userEvent.type( name, 'New Co' );
		expect(
			screen.getByRole( 'button', { name: 'Save context' } )
		).toBeEnabled();
		await waitFor( () =>
			expect( apiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					path: '/emcp-tools/v1/admin/context/preview',
					method: 'POST',
					data: expect.objectContaining( {
						profile: expect.objectContaining( { name: 'New Co' } ),
					} ),
				} )
			)
		);
		expect(
			await screen.findByText( /- Business: New Co/ )
		).toBeInTheDocument();
		expect( apiFetch ).not.toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/emcp-tools/v1/admin/context',
				method: 'POST',
			} )
		);
	} );

	it( 'saves only the changed fields', async () => {
		apiFetch.mockImplementation( ( { path } ) =>
			Promise.resolve(
				path.endsWith( '/preview' )
					? { text: 'x', tokens: 1, budget: 8000 }
					: {
							...data,
							sections: data.sections.map( ( s ) =>
								'theme' === s.id ? { ...s, enabled: false } : s
							),
						}
			)
		);
		mount();
		await userEvent.click(
			screen.getByRole( 'switch', { name: 'Theme' } )
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Save context' } )
		);
		await waitFor( () =>
			expect( apiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					path: '/emcp-tools/v1/admin/context',
					method: 'POST',
					data: { sections: { theme: false } },
				} )
			)
		);
	} );

	it( 'warns past the 8k guide', () => {
		mount( { ...data, tokens: 9100 } );
		expect(
			screen.getByText( /Turn off detected items the AI does not need/ )
		).toBeInTheDocument();
		expect( document.querySelector( '.eui-ctx__meter' ) ).toHaveClass(
			'is-over'
		);
	} );

	it( 'refreshes the detected summaries', async () => {
		apiFetch.mockResolvedValue( {
			...data,
			sections: [ { ...data.sections[ 1 ], summary: 'Astra 4.8' } ],
		} );
		mount();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Refresh' } )
		);
		expect( await screen.findByText( 'Astra 4.8' ) ).toBeInTheDocument();
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/emcp-tools/v1/admin/context/refresh',
				method: 'POST',
			} )
		);
	} );

	it( 'caps brand voice at 8', () => {
		mount( {
			...data,
			profile: {
				...data.profile,
				voice: [ 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h' ],
			},
		} );
		expect( screen.getByLabelText( 'Custom voice' ) ).toBeDisabled();
		expect(
			screen.getByRole( 'button', { name: 'Add voice' } )
		).toBeDisabled();
	} );

	it( 'adds a custom voice', async () => {
		apiFetch.mockResolvedValue( { text: 'x', tokens: 1, budget: 8000 } );
		mount();
		await userEvent.type(
			screen.getByLabelText( 'Custom voice' ),
			'Witty'
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Add voice' } )
		);
		expect(
			screen.getByRole( 'button', { name: 'Witty', pressed: true } )
		).toBeInTheDocument();
	} );

	it( 'opens the full preview in a drawer', async () => {
		mount();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Preview what the AI sees' } )
		);
		expect(
			within( screen.getByRole( 'dialog' ) ).getByText( /## Environment/ )
		).toBeInTheDocument();
	} );
} );
