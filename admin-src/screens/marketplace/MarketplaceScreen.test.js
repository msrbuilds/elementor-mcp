import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { AppProviders } from '@emcp/ui';
import { MarketplaceScreen } from './MarketplaceScreen';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const boot = {
	connected: true,
	connectUrl: '/wp-admin/admin.php?page=emcp-tools-connection&section=cloud',
	sandboxUrl: '/wp-admin/admin.php?page=emcp-tools-widgets',
	kinds: {
		block: 'Block',
		widget: 'Widget',
		snippet: 'Snippet',
		template: 'Template',
	},
};
const listing = ( slug, extra = {} ) => ( {
	slug,
	kind: 'widget',
	kindLabel: 'Widget',
	title: slug.charAt( 0 ).toUpperCase() + slug.slice( 1 ),
	summary: 'About ' + slug,
	category: 'E-commerce',
	access: 'pro',
	thumbnail: '',
	previewUrl: '',
	installCount: 3,
	author: { name: 'MSR Builds', avatar: '', verified: true, profileUrl: '' },
	installed: null,
	...extra,
} );
const page = {
	connected: true,
	items: [
		listing( 'brands', { installed: { id: 4, reviewUrl: '/review/4' } } ),
		listing( 'slider' ),
	],
	total: 2,
	page: 1,
	pages: 1,
	counts: { all: 65, block: 9, widget: 48, snippet: 5, template: 3 },
	categories: [ 'E-commerce', 'Header & Navigation' ],
	error: '',
};

function mount( b = boot, search = '' ) {
	window.history.replaceState(
		{},
		'',
		'/wp-admin/admin.php?page=emcp-tools-marketplace' + search
	);
	return render(
		<AppProviders>
			<MarketplaceScreen data={ b } />
		</AppProviders>
	);
}

const cardOf = async ( name ) =>
	( await screen.findByRole( 'heading', { name } ) ).closest(
		'.eui-mk-card'
	);

describe( 'MarketplaceScreen', () => {
	beforeEach( () => {
		apiFetch.mockReset();
		window.localStorage.clear();
	} );

	it( 'loads the first page after mount and shows type counts', async () => {
		apiFetch.mockResolvedValue( page );
		mount();
		expect(
			await screen.findByRole( 'heading', { name: 'Brands' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'radio', { name: /^Widgets\s*48$/ } )
		).toBeInTheDocument();
		expect( screen.getByText( '2 results' ) ).toBeInTheDocument();
	} );

	it( 'shows Installed with a review link for an installed item', async () => {
		apiFetch.mockResolvedValue( page );
		mount();
		const card = await cardOf( 'Brands' );
		expect(
			within( card ).getByRole( 'link', {
				name: 'Installed, review Brands',
			} )
		).toHaveAttribute( 'href', '/review/4' );
	} );

	it( 'filter chips reflect active filters and Clear all resets them', async () => {
		apiFetch.mockResolvedValue( page );
		mount( boot, '&type=widget&category=E-commerce' );
		expect(
			await screen.findByRole( 'button', {
				name: 'Remove filter Widgets',
			} )
		).toBeInTheDocument();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Clear all' } )
		);
		expect( window.location.search ).not.toContain( 'type=' );
		expect( window.location.search ).not.toContain( 'category=' );
	} );

	it( 'the verified toggle is sent to the server', async () => {
		apiFetch.mockResolvedValue( page );
		mount();
		await screen.findByRole( 'heading', { name: 'Brands' } );
		await userEvent.click(
			screen.getByRole( 'switch', { name: 'Verified authors only' } )
		);
		expect( apiFetch ).toHaveBeenLastCalledWith(
			expect.objectContaining( {
				path: expect.stringContaining( 'verified=1' ),
			} )
		);
	} );

	it( 'installs an item and then shows it as installed', async () => {
		apiFetch
			.mockResolvedValueOnce( page )
			.mockResolvedValueOnce( {
				installed: { id: 9, reviewUrl: '/review/9' },
				message: 'Installed as a draft.',
			} )
			.mockResolvedValueOnce( {
				...page,
				items: [
					page.items[ 0 ],
					listing( 'slider', {
						installed: { id: 9, reviewUrl: '/review/9' },
					} ),
				],
			} );
		mount();
		const card = await cardOf( 'Slider' );
		await userEvent.click(
			within( card ).getByRole( 'button', { name: 'Install Slider' } )
		);
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/emcp-tools/v1/admin/marketplace/slider/install',
				method: 'POST',
			} )
		);
		expect(
			await screen.findByRole( 'link', {
				name: 'Installed, review Slider',
			} )
		).toHaveAttribute( 'href', '/review/9' );
	} );

	it( 'a paid item without a paid plan shows the server message', async () => {
		apiFetch
			.mockResolvedValueOnce( page )
			.mockRejectedValueOnce( {
				code: 'emcp_pro_required',
				message: 'That item needs a paid EMCP Cloud plan.',
			} )
			.mockResolvedValueOnce( page );
		mount();
		const card = await cardOf( 'Slider' );
		await userEvent.click(
			within( card ).getByRole( 'button', { name: 'Install Slider' } )
		);
		expect(
			await screen.findByText( 'That item needs a paid EMCP Cloud plan.' )
		).toBeInTheDocument();
	} );

	it( 'shows the connect card when Cloud is disconnected and makes no request', () => {
		mount( { ...boot, connected: false } );
		expect(
			screen.getByRole( 'link', { name: 'Connect to EMCP Cloud' } )
		).toHaveAttribute( 'href', boot.connectUrl );
		expect( apiFetch ).not.toHaveBeenCalled();
	} );

	it( 'switches to the list view and remembers it', async () => {
		apiFetch.mockResolvedValue( page );
		mount();
		await screen.findByRole( 'heading', { name: 'Brands' } );
		await userEvent.click(
			screen.getByRole( 'radio', { name: 'List view' } )
		);
		expect( screen.getByRole( 'table' ) ).toBeInTheDocument();
		expect( window.localStorage.getItem( 'emcp.marketplace.view' ) ).toBe(
			'list'
		);
	} );

	it( 'My installs opens a drawer with the local installs', async () => {
		apiFetch.mockResolvedValueOnce( page ).mockResolvedValueOnce( {
			installs: [
				{
					slug: 'brands',
					kind: 'widget',
					id: 4,
					title: 'Brands',
					at: 1790000000,
					status: 'draft',
					reviewUrl: '/review/4',
				},
			],
		} );
		mount();
		await screen.findByRole( 'heading', { name: 'Brands' } );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'My installs' } )
		);
		const drawer = await screen.findByRole( 'dialog', {
			name: 'My installs',
		} );
		expect(
			await within( drawer ).findByRole( 'link', {
				name: 'Review Brands',
			} )
		).toHaveAttribute( 'href', '/review/4' );
	} );
} );
