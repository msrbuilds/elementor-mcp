import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { AppProviders } from '@emcp/ui';
import { PromptsScreen } from './PromptsScreen';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const item = ( slug, category = 'food-dining', label = 'Food & Dining' ) => ( {
	slug,
	category,
	categoryLabel: label,
	title: slug[ 0 ].toUpperCase() + slug.slice( 1 ),
	description: '',
	content: 'Design and build a complete landing page for ' + slug + '.',
	tier: 'pro',
} );

const data = {
	items: [
		item( 'bakery' ),
		item( 'pizza' ),
		item( 'groomer', 'pets', 'Pets' ),
	],
	total: 3,
	page: 1,
	pages: 2,
	categories: [
		{ slug: 'food-dining', label: 'Food & Dining', count: 2 },
		{ slug: 'pets', label: 'Pets', count: 1 },
	],
	source: 'pro',
	syncedAt: Math.floor( Date.now() / 1000 ) - 3600,
	error: '',
	licensed: true,
	noticeDismissed: false,
	v1Url: '/wp-admin/admin-post.php?action=emcp_tools_download_prompts_v1',
	aiChatUrl: '/wp-admin/admin.php?page=emcp-tools-ai-chat',
	upgradeUrl: 'https://emcptools.com/pricing',
};

function mount(
	d = data,
	url = '/wp-admin/admin.php?page=emcp-tools-prompts'
) {
	window.history.replaceState( {}, '', url );
	return render(
		<AppProviders>
			<PromptsScreen data={ d } />
		</AppProviders>
	);
}

describe( 'PromptsScreen', () => {
	beforeEach( () => {
		apiFetch.mockReset();
		apiFetch.mockResolvedValue( {
			...data,
			items: [ item( 'groomer', 'pets', 'Pets' ) ],
			total: 1,
			pages: 1,
		} );
		Object.assign( navigator, {
			clipboard: { writeText: jest.fn( () => Promise.resolve() ) },
		} );
		Object.defineProperty( window, 'isSecureContext', {
			value: true,
			configurable: true,
		} );
		window.sessionStorage.clear();
	} );

	it( 'shows the library summary, the notice and the category chips with counts', () => {
		mount();
		expect(
			screen.getByText( /3 prompts across 2 categories/ )
		).toBeInTheDocument();
		expect(
			screen.getByText( 'Prompts have been rewritten' )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: /Pets\s*1/ } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: /Download v1 prompts/ } )
		).toHaveAttribute( 'href', data.v1Url );
	} );

	it( 'filters by category through the server and keeps it in the URL', async () => {
		mount();
		await userEvent.click(
			screen.getByRole( 'button', { name: /Pets\s*1/ } )
		);
		expect( apiFetch ).toHaveBeenLastCalledWith(
			expect.objectContaining( {
				path: expect.stringContaining(
					'/emcp-tools/v1/admin/prompts?'
				),
			} )
		);
		expect( apiFetch.mock.calls.at( -1 )[ 0 ].path ).toContain(
			'category=pets'
		);
		expect( window.location.search ).toContain( 'category=pets' );
		expect(
			await screen.findByRole( 'heading', { name: 'Groomer' } )
		).toBeInTheDocument();
	} );

	it( 'copies the full prompt, says Copied and records the copy', async () => {
		mount();
		const card = screen
			.getByRole( 'heading', { name: 'Bakery' } )
			.closest( '.eui-prompt' );
		await userEvent.click(
			within( card ).getByRole( 'button', { name: 'Copy prompt' } )
		);
		expect( navigator.clipboard.writeText ).toHaveBeenCalledWith(
			data.items[ 0 ].content
		);
		expect(
			within( card ).getByRole( 'button', { name: 'Copied' } )
		).toBeInTheDocument();
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/emcp-tools/v1/admin/prompts/food-dining/bakery/copied',
				method: 'POST',
			} )
		);
	} );

	it( 'hands the prompt to AI Chat through sessionStorage', async () => {
		mount();
		const card = screen
			.getByRole( 'heading', { name: 'Pizza' } )
			.closest( '.eui-prompt' );
		const link = within( card ).getByRole( 'link', {
			name: 'Use in AI Chat',
		} );
		expect( link ).toHaveAttribute( 'href', data.aiChatUrl );
		link.addEventListener( 'click', ( e ) => e.preventDefault() );
		await userEvent.click( link );
		expect( window.sessionStorage.getItem( 'emcp.aiChat.prompt' ) ).toBe(
			data.items[ 1 ].content
		);
	} );

	it( 'hides Use in AI Chat when AI Chat is off', () => {
		mount( { ...data, aiChatUrl: '' } );
		expect(
			screen.queryByRole( 'link', { name: 'Use in AI Chat' } )
		).not.toBeInTheDocument();
	} );

	it( 'previews the whole prompt in a drawer', async () => {
		mount();
		const card = screen
			.getByRole( 'heading', { name: 'Bakery' } )
			.closest( '.eui-prompt' );
		await userEvent.click(
			within( card ).getByRole( 'button', { name: 'Preview Bakery' } )
		);
		const drawer = await screen.findByRole( 'dialog', { name: 'Bakery' } );
		expect(
			within( drawer ).getByText( data.items[ 0 ].content )
		).toBeInTheDocument();
	} );

	it( 'dismisses the notice for this user', async () => {
		apiFetch.mockResolvedValueOnce( { dismissed: true } );
		mount();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Dismiss' } )
		);
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/emcp-tools/v1/admin/prompts/notice/dismiss',
				method: 'POST',
			} )
		);
		expect(
			screen.queryByText( 'Prompts have been rewritten' )
		).not.toBeInTheDocument();
	} );

	it( 'syncs the library', async () => {
		apiFetch.mockResolvedValueOnce( {
			...data,
			message: 'Synced 3 prompts across 2 categories.',
		} );
		mount();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Sync library' } )
		);
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/emcp-tools/v1/admin/prompts/sync',
				method: 'POST',
			} )
		);
		expect(
			await screen.findByText( 'Synced 3 prompts across 2 categories.' )
		).toBeInTheDocument();
	} );

	it( 'free builds show the samples and the upgrade call to action, no sync', () => {
		mount( {
			...data,
			source: 'free',
			licensed: false,
			v1Url: '',
			aiChatUrl: '',
			items: [ { ...item( 'car-wash' ), tier: 'free' } ],
			total: 1,
			pages: 1,
		} );
		expect(
			screen.queryByRole( 'button', { name: 'Sync library' } )
		).not.toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: /Get the full library/ } )
		).toHaveAttribute( 'href', data.upgradeUrl );
	} );

	it( 'a failed Pro sync shows the error with the samples', () => {
		mount( {
			...data,
			source: 'free',
			error: 'Offline',
			items: [ { ...item( 'car-wash' ), tier: 'free' } ],
			total: 1,
			pages: 1,
		} );
		expect( screen.getByText( /Offline/ ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'heading', { name: 'Car-wash' } )
		).toBeInTheDocument();
	} );
} );
