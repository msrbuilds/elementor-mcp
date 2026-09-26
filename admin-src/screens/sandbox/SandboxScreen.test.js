import { render, screen, within } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import { AppProviders } from '@emcp/ui';
import { SandboxScreen } from './SandboxScreen';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const data = {
	cards: [
		{
			type: 'widgets',
			available: true,
			active: 10,
			inactive: 1,
			url: '/w?view=widgets',
		},
		{
			type: 'snippets',
			available: true,
			active: 5,
			inactive: 8,
			url: '/w?view=snippets',
		},
		{
			type: 'blocks',
			available: false,
			active: 0,
			inactive: 0,
			url: '/w?view=blocks',
		},
	],
	review: [
		{
			kind: 'widget',
			type: 'widgets',
			id: 3,
			title: 'Countdown Banner',
			review: { level: 'none', text: 'Nothing flagged' },
			author: 'Claude Desktop',
			updatedTs: Math.floor( Date.now() / 1000 ) - 2 * 86400,
			reviewUrl: '/w?view=widgets&review=3',
		},
		{
			kind: 'snippet',
			type: 'snippets',
			id: 9,
			title: 'Register Reviews CPT',
			review: { level: 'warning', text: '15 things worth reading' },
			author: '',
			updatedTs: Math.floor( Date.now() / 1000 ) - 5 * 86400,
			reviewUrl: '/w?view=snippets&review=9',
		},
	],
	export: { show: true, locked: false, url: '/w?view=export' },
};

const mount = ( d = data ) =>
	render(
		<AppProviders>
			<SandboxScreen data={ d } />
		</AppProviders>
	);

describe( 'SandboxScreen', () => {
	beforeEach( () => apiFetch.mockReset() );

	it( 'shows the heading, the sandbox path and the Export button', () => {
		mount();
		expect(
			screen.getByRole( 'heading', { level: 1, name: 'Sandbox' } )
		).toBeInTheDocument();
		expect( screen.getByText( 'wp-content/emcp-sandbox' ).tagName ).toBe(
			'CODE'
		);
		expect(
			screen.getByRole( 'link', { name: 'Export as plugin' } )
		).toHaveAttribute( 'href', '/w?view=export' );
	} );

	it( 'marks a locked Export button and hides it when not shown', () => {
		const { unmount } = mount( {
			...data,
			export: { ...data.export, locked: true },
		} );
		expect(
			screen.getByRole( 'link', { name: 'Export as plugin (Pro)' } )
		).toHaveAttribute( 'href', '/w?view=export' );
		unmount();
		mount( { ...data, export: { ...data.export, show: false } } );
		expect(
			screen.queryByRole( 'link', { name: /Export as plugin/ } )
		).not.toBeInTheDocument();
	} );

	it( 'renders the three cards in order with counts, tiers and links', () => {
		mount();
		const cards = screen.getAllByRole( 'link', {
			name: /Widgets|PHP Snippets|Blocks/,
		} );
		expect( cards.map( ( c ) => c.getAttribute( 'href' ) ) ).toEqual( [
			'/w?view=widgets',
			'/w?view=snippets',
			'/w?view=blocks',
		] );
		const widgets = cards[ 0 ];
		expect( within( widgets ).getByText( 'PRO' ) ).toBeInTheDocument();
		expect( within( widgets ).getByText( '10' ) ).toBeInTheDocument();
		expect( within( widgets ).getByText( 'active' ) ).toBeInTheDocument();
		expect(
			within( widgets ).getByText( 'inactive / drafts' )
		).toBeInTheDocument();
		expect( within( cards[ 1 ] ).getByText( 'FREE' ) ).toBeInTheDocument();
		expect(
			within( cards[ 2 ] ).getByText( 'Requires Pro' )
		).toBeInTheDocument();
	} );

	it( 'lists the review queue with meta, review text and links', () => {
		mount();
		const queue = screen
			.getByRole( 'heading', { name: 'Awaiting your review' } )
			.closest( 'section' );
		expect(
			within( queue ).getByText(
				'AI can only create inactive drafts. Activation is your approval step.'
			)
		).toBeInTheDocument();
		expect( within( queue ).getByText( 'Widget' ) ).toBeInTheDocument();
		expect(
			within( queue ).getByText( 'PHP snippet' )
		).toBeInTheDocument();
		expect(
			within( queue ).getByText( /Drafted by Claude Desktop/ )
		).toBeInTheDocument();
		const warn = within( queue ).getByText( '15 things worth reading' );
		expect( warn.className ).toContain( 'is-warning' );
		const links = within( queue ).getAllByRole( 'link', {
			name: /Review code/,
		} );
		expect( links[ 1 ] ).toHaveAttribute(
			'href',
			'/w?view=snippets&review=9'
		);
	} );

	it( 'says when nothing is waiting', () => {
		mount( { ...data, review: [] } );
		expect(
			screen.getByText( 'Nothing is waiting for you.' )
		).toBeInTheDocument();
	} );
} );
