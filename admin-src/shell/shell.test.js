import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { AppProviders } from '@emcp/ui';
import { ShellApp } from './ShellApp';
import { buildIndex, searchPalette } from './palette-search';

jest.mock( '@wordpress/api-fetch', () =>
	jest.fn( () => Promise.resolve( { unread: 0, dismissed: [ 'cloud' ] } ) )
);

const data = {
	nav: [
		{
			id: 'tools',
			label: 'Tools',
			group: 'Setup',
			url: '/wp-admin/admin.php?page=emcp-tools-tools',
		},
		{
			id: 'redirects',
			label: 'Redirects',
			group: 'Safety',
			url: '/wp-admin/admin.php?page=emcp-tools-redirects',
		},
	],
	tools: [
		{
			slug: 'emcp-tools/menu-read',
			name: 'Menu Read',
			category: 'Navigation Menus',
		},
	],
	settings: [
		{
			label: 'OAuth sign-in',
			url: '/wp-admin/admin.php?page=emcp-tools-connection',
		},
	],
	notifications: [
		{
			id: 'n1',
			title: 'Cloud is live',
			body: 'Back up your work.',
			url: '',
			cta: '',
			unread: true,
		},
	],
	unread: 1,
};

function frame() {
	document.body.innerHTML =
		'<div data-emcp-promo><button data-emcp-promo-dismiss="cloud">x</button></div>' +
		'<button data-emcp-palette-open>Search</button>' +
		'<button data-emcp-notifications-open>Bell<span data-emcp-unread></span></button>' +
		'<a data-emcp-nav href="/wp-admin/admin.php?page=emcp-tools-modules">Modules</a>' +
		'<div id="emcp-shell-root"></div>';
}

function mount( navigate = jest.fn() ) {
	frame();
	render(
		<AppProviders>
			<ShellApp data={ data } navigate={ navigate } doc={ document } />
		</AppProviders>,
		{ container: document.getElementById( 'emcp-shell-root' ) }
	);
	return navigate;
}

describe( 'palette search', () => {
	it( 'indexes screens, tools and settings', () => {
		const index = buildIndex( data );
		expect( index.map( ( i ) => i.kind ) ).toEqual( [
			'screen',
			'screen',
			'tool',
			'setting',
		] );
		expect( index[ 2 ].url ).toContain( 'page=emcp-tools-tools' );
		expect( index[ 2 ].url ).toContain(
			'q=' + encodeURIComponent( 'emcp-tools/menu-read' )
		);
	} );

	it( 'finds by fuzzy label and by slug', () => {
		const index = buildIndex( data );
		expect( searchPalette( index, 'redir' )[ 0 ].label ).toBe(
			'Redirects'
		);
		expect( searchPalette( index, 'menu-read' )[ 0 ].label ).toBe(
			'Menu Read'
		);
		expect( searchPalette( index, '' ) ).toHaveLength( 2 );
	} );
} );

describe( 'ShellApp', () => {
	beforeEach( () => apiFetch.mockClear() );

	it( 'opens the palette with Ctrl+K and navigates with Enter', async () => {
		const navigate = mount();
		await userEvent.keyboard( '{Control>}k{/Control}' );
		const input = await screen.findByRole( 'combobox', { name: /Search/ } );
		await userEvent.type( input, 'redir' );
		expect(
			screen.getByRole( 'option', { name: /Redirects/ } )
		).toHaveAttribute( 'aria-selected', 'true' );
		await userEvent.keyboard( '{Enter}' );
		expect( navigate ).toHaveBeenCalledWith(
			'/wp-admin/admin.php?page=emcp-tools-redirects'
		);
	} );

	it( 'opens the palette from the sidebar search button', async () => {
		mount();
		await userEvent.click(
			document.querySelector( '[data-emcp-palette-open]' )
		);
		expect(
			await screen.findByRole( 'combobox', { name: /Search/ } )
		).toBeInTheDocument();
	} );

	it( 'opens notifications, marks them read and hides the dot', async () => {
		mount();
		await userEvent.click(
			document.querySelector( '[data-emcp-notifications-open]' )
		);
		expect(
			await screen.findByRole( 'dialog', { name: 'Notifications' } )
		).toHaveTextContent( 'Cloud is live' );
		await waitFor( () =>
			expect(
				document.querySelector( '[data-emcp-unread]' ).hidden
			).toBe( true )
		);
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/emcp-tools/v1/admin/notifications/read',
				data: { ids: [ 'n1' ] },
			} )
		);
	} );

	it( 'asks before leaving a page with unsaved changes', async () => {
		const navigate = mount();
		document.documentElement.dataset.emcpDirty = '1';
		await userEvent.click( screen.getByText( 'Modules' ) );
		expect(
			await screen.findByRole( 'dialog', {
				name: 'Leave without saving?',
			} )
		).toBeInTheDocument();
		await userEvent.click( screen.getByRole( 'button', { name: 'Stay' } ) );
		expect( navigate ).not.toHaveBeenCalled();
		await userEvent.click( screen.getByText( 'Modules' ) );
		await userEvent.click(
			await screen.findByRole( 'button', { name: 'Leave' } )
		);
		expect( navigate ).toHaveBeenCalledWith(
			'/wp-admin/admin.php?page=emcp-tools-modules'
		);
		delete document.documentElement.dataset.emcpDirty;
	} );

	it( 'lets clean pages navigate normally', () => {
		const navigate = mount();
		const link = screen.getByText( 'Modules' );
		const event = new window.MouseEvent( 'click', {
			bubbles: true,
			cancelable: true,
		} );
		// Record what the shell decided when the click reached the window, then
		// stop jsdom's own navigation (it cannot navigate and logs an error).
		let preventedByShell = null;
		const observe = ( e ) => {
			preventedByShell = e.defaultPrevented;
			e.preventDefault();
		};
		window.addEventListener( 'click', observe );
		act( () => {
			link.dispatchEvent( event );
		} );
		window.removeEventListener( 'click', observe );
		expect( preventedByShell ).toBe( false );
		expect( navigate ).not.toHaveBeenCalled();
	} );

	it( 'dismisses the promo bar', async () => {
		mount();
		await userEvent.click(
			document.querySelector( '[data-emcp-promo-dismiss]' )
		);
		await waitFor( () =>
			expect( document.querySelector( '[data-emcp-promo]' ) ).toBeNull()
		);
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/emcp-tools/v1/admin/promo/dismiss',
				data: { id: 'cloud' },
			} )
		);
	} );
} );
