import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { AppProviders } from '@emcp/ui';
import { BrandKitsScreen } from './BrandKitsScreen';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const kit = ( slug, applied = false ) => ( {
	slug,
	category: 'corporate',
	categoryLabel: 'Corporate & Tech',
	title: slug
		.replace( /-/g, ' ' )
		.replace( /\b\w/g, ( c ) => c.toUpperCase() ),
	description: 'A kit.',
	swatches: [ '#1e3a5f', '#334155', '#1f2937', '#2563eb' ],
	headingFont: 'Source Sans 3',
	bodyFont: 'Inter',
	headingColor: '#1e3a5f',
	accentColor: '#2563eb',
	thumbnail: '',
	applied,
} );

const data = {
	items: [ kit( 'enterprise-blue', true ), kit( 'modern-saas' ) ],
	total: 2,
	page: 1,
	pages: 1,
	categories: [ { slug: 'corporate', label: 'Corporate & Tech', count: 2 } ],
	current: {
		slug: 'enterprise-blue',
		category: 'corporate',
		title: 'Enterprise Blue',
		swatches: kit( 'x' ).swatches,
		appliedAt: 1790000000,
		backupId: 9,
	},
	restorable: true,
	source: 'free',
	syncedAt: 0,
	error: '',
	licensed: false,
	elementorActive: true,
	upgradeUrl: 'https://emcptools.com/pricing',
};

function mount( d = data ) {
	window.history.replaceState(
		{},
		'',
		'/wp-admin/admin.php?page=emcp-tools-brand-kits'
	);
	return render(
		<AppProviders>
			<BrandKitsScreen data={ d } />
		</AppProviders>
	);
}

describe( 'BrandKitsScreen', () => {
	beforeEach( () => apiFetch.mockReset() );

	it( 'shows the current kit strip with restore and the applied card', () => {
		mount();
		expect(
			screen.getByText( 'Current kit: Enterprise Blue' )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Restore previous' } )
		).toBeEnabled();
		const card = screen
			.getByRole( 'heading', { name: 'Enterprise Blue' } )
			.closest( '.eui-kit' );
		expect( within( card ).getByText( 'Applied' ) ).toBeInTheDocument();
		expect(
			within( card ).getByText( 'Source Sans 3 · Inter' )
		).toBeInTheDocument();
	} );

	it( 'draws the swatch bar in 50/25/15/10 proportions', () => {
		const { container } = mount();
		const widths = [
			...container.querySelector( '.eui-kit__swatches' ).children,
		].map( ( s ) => s.style.flexGrow );
		expect( widths ).toEqual( [ '50', '25', '15', '10' ] );
	} );

	it( 'applies a kit and refreshes the list', async () => {
		apiFetch.mockResolvedValue( {
			...data,
			items: [ kit( 'enterprise-blue' ), kit( 'modern-saas', true ) ],
			current: {
				...data.current,
				slug: 'modern-saas',
				title: 'Modern Saas',
			},
			applied: {},
			viewUrl: '/',
		} );
		mount();
		const card = screen
			.getByRole( 'heading', { name: 'Modern Saas' } )
			.closest( '.eui-kit' );
		await userEvent.click(
			within( card ).getByRole( 'button', { name: 'Apply kit' } )
		);
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/emcp-tools/v1/admin/brand-kits/corporate/modern-saas/apply',
				method: 'POST',
				data: { backup: true },
			} )
		);
		expect(
			await screen.findByText( 'Current kit: Modern Saas' )
		).toBeInTheDocument();
	} );

	it( 'restore asks first and sends the custom colours choice', async () => {
		apiFetch.mockResolvedValue( {
			...data,
			current: null,
			restored: 'Before',
		} );
		mount();
		await userEvent.click(
			screen.getByRole( 'checkbox', {
				name: 'Also restore custom colors and fonts',
			} )
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Restore previous' } )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Restore the previous kit?',
		} );
		await userEvent.click(
			within( dialog ).getByRole( 'button', { name: 'Restore' } )
		);
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/emcp-tools/v1/admin/brand-kits/restore',
				method: 'POST',
				data: { full_clobber: true },
			} )
		);
	} );

	it( 'without Elementor the apply buttons are disabled with a notice', () => {
		mount( { ...data, elementorActive: false } );
		expect( screen.getByText( /Activate Elementor/ ) ).toBeInTheDocument();
		screen
			.getAllByRole( 'button', { name: 'Apply kit' } )
			.forEach( ( b ) => expect( b ).toBeDisabled() );
	} );

	it( 'without a current kit the strip says so and restore is still offered when a backup exists', () => {
		mount( {
			...data,
			current: null,
			items: data.items.map( ( k ) => ( { ...k, applied: false } ) ),
		} );
		expect(
			screen.getByText( 'No kit applied from this library yet' )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Restore previous' } )
		).toBeEnabled();
	} );

	it( 'previews a kit in a drawer', async () => {
		mount();
		const card = screen
			.getByRole( 'heading', { name: 'Modern Saas' } )
			.closest( '.eui-kit' );
		await userEvent.click(
			within( card ).getByRole( 'button', {
				name: 'Preview Modern Saas',
			} )
		);
		const drawer = await screen.findByRole( 'dialog', {
			name: 'Modern Saas',
		} );
		expect( within( drawer ).getByText( '#2563eb' ) ).toBeInTheDocument();
	} );
} );
