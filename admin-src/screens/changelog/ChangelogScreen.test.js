import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { AppProviders } from '@emcp/ui';
import { ChangelogScreen } from './ChangelogScreen';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const item = ( over = {} ) => ( {
	type: 'fixed',
	tag: 'FIXED',
	title: 'Kit wiped',
	html: 'Merge before save.',
	issues: [ { number: 145, url: 'https://github.com/x/issues/145' } ],
	...over,
} );

const release = ( version, items, over = {} ) => ( {
	version,
	unreleased: false,
	summary: 'Patch release.',
	items,
	counts: {
		all: items.length,
		new: items.filter( ( i ) => 'new' === i.type ).length,
		fixed: items.filter( ( i ) => 'fixed' === i.type ).length,
	},
	...over,
} );

const latest = release( '3.17.1', [
	item(),
	item( { type: 'new', tag: 'NEW', title: 'Visibility SEO', issues: [] } ),
] );

const index = [
	'3.17.1',
	'3.17.0',
	'3.16.2',
	'3.16.1',
	'3.16.0',
	'3.15.0',
	'3.14.2',
].map( ( v ) => ( {
	version: v,
	unreleased: false,
	counts: { all: 1, new: 0, fixed: 1 },
} ) );

const mount = ( url = '' ) => {
	window.history.replaceState(
		{},
		'',
		'/wp-admin/admin.php?page=emcp-tools-changelog' + url
	);
	return render(
		<AppProviders>
			<ChangelogScreen data={ { index, latest, current: '3.17.1' } } />
		</AppProviders>
	);
};

describe( 'ChangelogScreen', () => {
	beforeEach( () => apiFetch.mockReset() );

	it( 'shows the latest release with its items and issue links', () => {
		mount();
		expect(
			screen.getByRole( 'heading', { name: /Version 3\.17\.1/ } )
		).toBeInTheDocument();
		expect( screen.getByText( 'Kit wiped' ) ).toBeInTheDocument();
		expect( screen.getByRole( 'link', { name: '#145' } ) ).toHaveAttribute(
			'rel',
			'noopener noreferrer'
		);
		expect( screen.getAllByText( 'LATEST' ).length ).toBeGreaterThan( 0 );
		expect(
			screen.getByRole( 'button', { name: /3\.17\.1/ } )
		).toHaveAttribute( 'aria-current', 'true' );
	} );

	it( 'filters New and Fixed', async () => {
		mount();
		await userEvent.click( screen.getByRole( 'radio', { name: /^New/ } ) );
		expect( screen.queryByText( 'Kit wiped' ) ).toBeNull();
		expect( screen.getByText( 'Visibility SEO' ) ).toBeInTheDocument();
	} );

	it( 'loads an older release and reveals the full list', async () => {
		apiFetch.mockResolvedValueOnce(
			release( '3.14.2', [ item( { title: 'Old fix' } ) ] )
		);
		mount();
		expect(
			screen.queryByRole( 'button', { name: /3\.14\.2/ } )
		).toBeNull();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Older releases' } )
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: /3\.14\.2/ } )
		);
		expect( apiFetch.mock.calls[ 0 ][ 0 ].path ).toBe(
			'/emcp-tools/v1/admin/changelog/3.14.2'
		);
		expect( await screen.findByText( 'Old fix' ) ).toBeInTheDocument();
		expect( window.location.search ).toContain( 'version=3.14.2' );
	} );

	it( 'searches across releases', async () => {
		apiFetch.mockResolvedValueOnce( {
			results: [
				{ version: '3.17.0', items: [ item( { title: 'Found it' } ) ] },
			],
		} );
		mount();
		await userEvent.type(
			screen.getByRole( 'searchbox', {
				name: 'Search changes or #issue',
			} ),
			'#145'
		);
		await waitFor( () =>
			expect( apiFetch.mock.calls[ 0 ][ 0 ].path ).toContain(
				'search=%23145'
			)
		);
		expect( await screen.findByText( 'Found it' ) ).toBeInTheDocument();
	} );

	it( 'shows the empty state when nothing matches', async () => {
		apiFetch.mockResolvedValueOnce( { results: [] } );
		mount();
		await userEvent.type(
			screen.getByRole( 'searchbox', {
				name: 'Search changes or #issue',
			} ),
			'zzz'
		);
		expect(
			await screen.findByText( 'No changes match' )
		).toBeInTheDocument();
	} );
} );
