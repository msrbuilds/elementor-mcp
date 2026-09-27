import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { AppProviders } from '@emcp/ui';
import { RedirectsScreen } from './RedirectsScreen';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const redirect = ( over = {} ) => ( {
	id: 4,
	source: '/old-page',
	sourcePath: '/old-page',
	sourceQuery: '',
	ignoreQuery: true,
	target: '/new-page',
	targetPostId: 0,
	targetTitle: '',
	targetUrl: '/new-page',
	code: 301,
	hits: 12,
	lastHit: null,
	enabled: true,
	created: '2026-09-01 10:00:00',
	...over,
} );

const data = ( over = {} ) => ( {
	redirects: [ redirect() ],
	total: 1,
	page: 1,
	pages: 1,
	search: '',
	suggestions: [
		{
			key: 'aaaaaaaaaaaa',
			oldPath: '/about-us',
			reason: 'slug-changed',
			suggestedTarget: { postId: 7, title: 'About', url: '/about' },
			at: '2026-09-02 10:00:00',
		},
	],
	...over,
} );

function mount( d = data() ) {
	window.history.replaceState(
		{},
		'',
		'/wp-admin/admin.php?page=emcp-tools-redirects'
	);
	return render(
		<AppProviders>
			<RedirectsScreen data={ d } />
		</AppProviders>
	);
}

const addForm = () => screen.getByRole( 'form', { name: 'Add redirect' } );

describe( 'RedirectsScreen', () => {
	beforeEach( () => apiFetch.mockReset() );

	it( 'shows suggestions, the add form and the table', () => {
		mount();
		expect(
			screen.getByRole( 'heading', { level: 1, name: 'Redirects' } )
		).toBeInTheDocument();
		expect( screen.getByText( '/about-us' ) ).toBeInTheDocument();
		const form = addForm();
		expect( within( form ).getByLabelText( 'From' ) ).toBeInTheDocument();
		expect(
			within( form ).getByRole( 'checkbox', {
				name: 'Match regardless of query string',
			} )
		).toBeChecked();
		const table = screen.getByRole( 'table' );
		expect( within( table ).getByText( '/old-page' ) ).toBeInTheDocument();
		expect( within( table ).getByText( '12' ) ).toBeInTheDocument();
	} );

	it( 'creates a redirect to a URL and shows the shadow warning', async () => {
		apiFetch.mockResolvedValue( {
			result: {
				row: redirect( { id: 9 } ),
				warning: 'Heads up: this path is a live page.',
			},
			list: data(),
		} );
		mount();
		const form = addForm();
		await userEvent.type(
			within( form ).getByLabelText( 'From' ),
			'/promo'
		);
		await userEvent.type(
			within( form ).getByLabelText( 'To' ),
			'/landing'
		);
		await userEvent.click(
			within( form ).getByRole( 'button', { name: 'Add redirect' } )
		);
		expect( apiFetch.mock.calls[ 0 ][ 0 ] ).toMatchObject( {
			path: '/emcp-tools/v1/admin/redirects',
			method: 'POST',
			data: {
				source: '/promo',
				target: '/landing',
				code: 301,
				ignoreQuery: true,
			},
		} );
		expect(
			await screen.findByText( 'Heads up: this path is a live page.' )
		).toBeInTheDocument();
		expect( within( addForm() ).getByLabelText( 'From' ) ).toHaveValue(
			''
		);
	} );

	it( 'refuses a query rule without a query', async () => {
		mount();
		const form = addForm();
		await userEvent.type(
			within( form ).getByLabelText( 'From' ),
			'/promo'
		);
		await userEvent.type(
			within( form ).getByLabelText( 'To' ),
			'/landing'
		);
		await userEvent.click(
			within( form ).getByRole( 'checkbox', {
				name: 'Match regardless of query string',
			} )
		);
		await userEvent.click(
			within( form ).getByRole( 'button', { name: 'Add redirect' } )
		);
		expect(
			screen.getByText(
				'Add the query string to match, for example /page?ref=ad.'
			)
		).toBeInTheDocument();
		expect( apiFetch ).not.toHaveBeenCalled();
	} );

	it( 'refuses a typed page title that was never picked', async () => {
		apiFetch.mockResolvedValue( { items: [] } );
		mount();
		const form = addForm();
		await userEvent.type( within( form ).getByLabelText( 'From' ), '/old' );
		await userEvent.type(
			within( form ).getByLabelText( 'To' ),
			'About us'
		);
		await userEvent.click(
			within( form ).getByRole( 'button', { name: 'Add redirect' } )
		);
		expect(
			screen.getByText(
				'Choose a page from the list, or enter a path such as /new-page or a full URL.'
			)
		).toBeInTheDocument();
		expect(
			apiFetch.mock.calls.filter( ( c ) => 'POST' === c[ 0 ].method )
		).toHaveLength( 0 );
	} );

	it( 'searches pages for the target and sends the post id', async () => {
		apiFetch
			.mockResolvedValueOnce( {
				items: [
					{ id: 7, title: 'About', type: 'page', url: '/about' },
				],
			} )
			.mockResolvedValueOnce( {
				result: { row: redirect(), warning: '' },
				list: data(),
			} );
		mount();
		const form = addForm();
		await userEvent.type(
			within( form ).getByLabelText( 'From' ),
			'/old-about'
		);
		await userEvent.type( within( form ).getByLabelText( 'To' ), 'Abo' );
		await userEvent.click(
			await screen.findByRole( 'option', { name: /About/ } )
		);
		await userEvent.click(
			within( form ).getByRole( 'button', { name: 'Add redirect' } )
		);
		expect( apiFetch.mock.calls[ 0 ][ 0 ].path ).toBe(
			'/emcp-tools/v1/admin/redirects/targets?search=Abo'
		);
		expect( apiFetch.mock.calls[ 1 ][ 0 ].data ).toMatchObject( {
			source: '/old-about',
			targetPostId: 7,
		} );
		expect( apiFetch.mock.calls[ 1 ][ 0 ].data.target ).toBeUndefined();
	} );

	it( 'toggles a redirect', async () => {
		apiFetch.mockResolvedValue( {
			result: { row: redirect( { enabled: false } ), warning: '' },
			list: data( { redirects: [ redirect( { enabled: false } ) ] } ),
		} );
		mount();
		await userEvent.click(
			screen.getByRole( 'switch', { name: 'Enabled: /old-page' } )
		);
		expect( apiFetch.mock.calls[ 0 ][ 0 ] ).toMatchObject( {
			path: '/emcp-tools/v1/admin/redirects/4',
			method: 'POST',
			data: { enabled: false },
		} );
		await waitFor( () =>
			expect(
				screen.getByRole( 'switch', { name: 'Enabled: /old-page' } )
			).toHaveAttribute( 'aria-checked', 'false' )
		);
	} );

	it( 'deletes only after confirming', async () => {
		apiFetch.mockResolvedValue( {
			result: { deleted: true },
			list: data( { redirects: [], total: 0 } ),
		} );
		mount();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Delete redirect: /old-page' } )
		);
		const dialog = await screen.findByRole( 'dialog' );
		expect( apiFetch ).not.toHaveBeenCalled();
		await userEvent.click(
			within( dialog ).getByRole( 'button', { name: 'Delete' } )
		);
		expect( apiFetch.mock.calls[ 0 ][ 0 ] ).toMatchObject( {
			path: '/emcp-tools/v1/admin/redirects/4',
			method: 'DELETE',
			data: { confirm: true },
		} );
		expect(
			await screen.findByText( 'No redirects yet' )
		).toBeInTheDocument();
	} );

	it( 'edits in a drawer', async () => {
		apiFetch.mockResolvedValue( {
			result: { row: redirect( { code: 302 } ), warning: '' },
			list: data(),
		} );
		mount();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Edit redirect: /old-page' } )
		);
		const drawer = await screen.findByRole( 'dialog', {
			name: 'Edit redirect',
		} );
		await userEvent.selectOptions(
			within( drawer ).getByLabelText( 'Type' ),
			'302'
		);
		await userEvent.click(
			within( drawer ).getByRole( 'button', { name: 'Save' } )
		);
		expect( apiFetch.mock.calls[ 0 ][ 0 ] ).toMatchObject( {
			path: '/emcp-tools/v1/admin/redirects/4',
			method: 'POST',
			data: {
				code: 302,
				source: '/old-page',
				target: '/new-page',
				ignoreQuery: true,
			},
		} );
		await waitFor( () =>
			expect(
				screen.queryByRole( 'dialog', { name: 'Edit redirect' } )
			).toBeNull()
		);
	} );

	it( 'accepts a suggestion with its suggested target', async () => {
		apiFetch.mockResolvedValueOnce( {
			result: { row: redirect(), warning: '' },
			list: data( { suggestions: [] } ),
		} );
		mount();
		await userEvent.click(
			screen.getByRole( 'button', {
				name: 'Create redirect for /about-us',
			} )
		);
		expect( apiFetch.mock.calls[ 0 ][ 0 ] ).toMatchObject( {
			path: '/emcp-tools/v1/admin/redirects/suggestions/aaaaaaaaaaaa/accept',
			method: 'POST',
			data: { targetPostId: 7, code: 301 },
		} );
		await waitFor( () =>
			expect( screen.queryByText( '/about-us' ) ).toBeNull()
		);
	} );

	it( 'prefills From for a suggestion without a target', async () => {
		mount(
			data( {
				suggestions: [
					{
						key: 'bbbbbbbbbbbb',
						oldPath: '/gone',
						reason: 'post-deleted',
						suggestedTarget: null,
						at: '',
					},
				],
			} )
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Create redirect for /gone' } )
		);
		expect( apiFetch ).not.toHaveBeenCalled();
		expect( within( addForm() ).getByLabelText( 'From' ) ).toHaveValue(
			'/gone'
		);
		expect( within( addForm() ).getByLabelText( 'To' ) ).toHaveFocus();
	} );

	it( 'dismisses a suggestion', async () => {
		apiFetch.mockResolvedValueOnce( {
			result: { dismissed: true },
			list: data( { suggestions: [] } ),
		} );
		mount();
		await userEvent.click(
			screen.getByRole( 'button', {
				name: 'Dismiss suggestion for /about-us',
			} )
		);
		expect( apiFetch.mock.calls[ 0 ][ 0 ].path ).toBe(
			'/emcp-tools/v1/admin/redirects/suggestions/aaaaaaaaaaaa/dismiss'
		);
		await waitFor( () =>
			expect( screen.queryByText( '/about-us' ) ).toBeNull()
		);
	} );

	it( 'shows the server message when a write fails', async () => {
		apiFetch.mockRejectedValue( {
			code: 'duplicate_source',
			message: 'A redirect for this source already exists.',
		} );
		mount();
		const form = addForm();
		await userEvent.type(
			within( form ).getByLabelText( 'From' ),
			'/old-page'
		);
		await userEvent.type( within( form ).getByLabelText( 'To' ), '/x' );
		await userEvent.click(
			within( form ).getByRole( 'button', { name: 'Add redirect' } )
		);
		expect(
			await screen.findByText(
				'A redirect for this source already exists.'
			)
		).toBeInTheDocument();
		expect( within( addForm() ).getByLabelText( 'From' ) ).toHaveValue(
			'/old-page'
		);
	} );

	it( 'searches and pages through the server', async () => {
		apiFetch.mockResolvedValue(
			data( { total: 45, pages: 3, page: 2, search: 'old' } )
		);
		mount( data( { total: 45, pages: 3 } ) );
		await userEvent.click( screen.getByRole( 'button', { name: '2' } ) );
		expect( apiFetch.mock.calls[ 0 ][ 0 ].path ).toBe(
			'/emcp-tools/v1/admin/redirects?page=2'
		);
		expect( window.location.search ).toContain( 'paged=2' );
	} );
} );
