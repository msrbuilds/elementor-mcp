import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { AppProviders } from '@emcp/ui';
import { SnippetsScreen } from './SnippetsScreen';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const API = '/emcp-tools/v1/admin/sandbox';

const row = ( id, over = {} ) => ( {
	id,
	kind: 'snippet',
	title: `Snippet ${ id }`,
	ident: `[emcp_snippet id="${ id }"]`,
	active: false,
	lastError: '',
	updatedTs: id,
	review: { level: 'none', text: 'Nothing flagged' },
	cloud: 'none',
	marketplace: null,
	context: 'hook',
	hook: 'init',
	priority: 10,
	runsOn: 'init',
	...over,
} );

const payload = ( over = {} ) => ( {
	type: 'snippets',
	kind: 'snippet',
	items: [
		row( 7, { title: 'Alpha', active: true } ),
		row( 8, {
			title: 'Reader',
			review: { level: 'warning', text: '1 thing worth reading' },
		} ),
	],
	counts: { all: 2, active: 1, inactive: 1, review: 2 },
	total: 2,
	page: 1,
	pages: 1,
	perPage: 20,
	query: { status: 'all', search: '', page: 1 },
	cloud: { connected: false, connectUrl: '/c' },
	elementor: true,
	aiChatUrl: '',
	canEdit: true,
	backUrl: '/w',
	codeEditor: false,
	...over,
} );

const detail = ( id, over = {} ) => ( {
	row: row( id ),
	tabs: [
		{
			id: 'code',
			label: `snippet-${ id }.php`,
			language: 'php',
			value: 'return 1;',
		},
	],
	snippet: {
		title: 'Alpha',
		code: 'return 1;',
		context: 'hook',
		hook: 'init',
		priority: 10,
	},
	validation: {
		valid: true,
		safe: true,
		findings: [
			{
				severity: 'warning',
				line: 3,
				message: 'Reads a file or a remote URL.',
			},
		],
	},
	summary: {
		blocked: false,
		headline: 'Safe to activate',
		detail: '1 thing worth reading first.',
	},
	...over,
} );

function mount( data = payload() ) {
	window.history.replaceState(
		{},
		'',
		'/wp-admin/admin.php?page=emcp-tools-widgets&view=snippets'
	);
	return render(
		<AppProviders>
			<SnippetsScreen data={ data } />
		</AppProviders>
	);
}

const calls = () => apiFetch.mock.calls.map( ( [ o ] ) => o );

describe( 'SnippetsScreen', () => {
	beforeEach( () => {
		apiFetch.mockReset();
		delete window.wp;
	} );

	it( 'shows the FREE header, the danger notice and the three header buttons', () => {
		mount();
		expect(
			screen.getByRole( 'heading', { level: 1, name: /PHP Snippets/ } )
		).toBeInTheDocument();
		expect( screen.getByText( 'Free' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'This runs real PHP on your site.' )
		).toBeInTheDocument();
		for ( const name of [
			'Import bundle',
			/Cloud library/,
			'Add snippet',
		] ) {
			expect(
				screen.getByRole( 'button', { name } )
			).toBeInTheDocument();
		}
	} );

	it( 'adds the Needs reading filter and the snippet columns', () => {
		mount();
		expect(
			screen.getByRole( 'radio', { name: 'Needs reading 2' } )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'columnheader', { name: 'Machine name' } )
		).not.toBeInTheDocument();
		expect(
			screen.getByRole( 'columnheader', { name: 'Runs on' } )
		).toBeInTheDocument();
		expect(
			screen.getByText( '[emcp_snippet id="7"]' )
		).toBeInTheDocument();
		expect( screen.getAllByText( 'init' )[ 0 ].tagName ).toBe( 'CODE' );
		expect(
			screen
				.getByText( '1 thing worth reading' )
				.closest( '.emcp-sn-review' ).className
		).toContain( 'is-warning' );
	} );

	it( 'adds a snippet and keeps the drawer open with the findings on a 400', async () => {
		apiFetch.mockImplementation( ( o ) =>
			o.path === `${ API }/snippets` && 'POST' === o.method
				? Promise.reject( {
						code: 'unsafe_php',
						message:
							'The snippet was blocked by the security validator.',
						data: {
							status: 400,
							validation: {
								findings: [
									{
										severity: 'critical',
										line: 1,
										message: 'Writes to the filesystem.',
									},
								],
							},
							summary: {
								blocked: true,
								headline: 'Cannot be activated',
								detail: '1 thing has to change.',
							},
						},
					} )
				: Promise.resolve( payload() )
		);
		mount();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Add snippet' } )
		);
		const drawer = await screen.findByRole( 'dialog', {
			name: 'Add snippet',
		} );
		expect(
			within( drawer ).queryByLabelText( 'Hook' )
		).not.toBeInTheDocument();
		await userEvent.type(
			within( drawer ).getByLabelText( 'Title' ),
			'Hello'
		);
		await userEvent.selectOptions(
			within( drawer ).getByLabelText( 'Runs as' ),
			'hook'
		);
		await userEvent.type(
			within( drawer ).getByLabelText( 'Hook' ),
			'init'
		);
		await userEvent.type(
			within( drawer ).getByLabelText( 'Code' ),
			'x();'
		);
		await userEvent.click(
			within( drawer ).getByRole( 'button', { name: 'Save draft' } )
		);
		await waitFor( () =>
			expect(
				within( drawer ).getByText( /Writes to the filesystem\./ )
			).toBeInTheDocument()
		);
		expect(
			within( drawer ).getByText( /Must change/ )
		).toBeInTheDocument();
		const sent = calls().find(
			( o ) => o.path === `${ API }/snippets` && 'POST' === o.method
		);
		expect( sent.data ).toMatchObject( {
			title: 'Hello',
			code: 'x();',
			context: 'hook',
			hook: 'init',
			priority: 10,
		} );
		expect(
			screen.getByRole( 'dialog', { name: 'Add snippet' } )
		).toBeInTheDocument();
	} );

	it( 'edits a snippet from its detail', async () => {
		apiFetch.mockImplementation( ( o ) => {
			if ( o.path === `${ API }/snippets/7` && ! o.method ) {
				return Promise.resolve( detail( 7 ) );
			}
			return Promise.resolve( payload() );
		} );
		mount();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Edit Alpha' } )
		);
		const drawer = await screen.findByRole( 'dialog', {
			name: 'Edit snippet',
		} );
		await waitFor( () =>
			expect( within( drawer ).getByLabelText( 'Title' ) ).toHaveValue(
				'Alpha'
			)
		);
		expect( within( drawer ).getByLabelText( 'Hook' ) ).toHaveValue(
			'init'
		);
		await userEvent.click(
			within( drawer ).getByRole( 'button', { name: 'Save' } )
		);
		await waitFor( () =>
			expect(
				calls().find(
					( o ) =>
						o.path === `${ API }/snippets/7` && 'POST' === o.method
				)
			).toBeTruthy()
		);
	} );

	it( 'asks before switching a snippet on and lists warnings', async () => {
		apiFetch.mockImplementation( ( o ) =>
			o.path === `${ API }/snippets/8` && ! o.method
				? Promise.resolve( detail( 8 ) )
				: Promise.resolve( payload() )
		);
		mount();
		await userEvent.click(
			screen.getByRole( 'switch', { name: 'Activate Reader' } )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Activate this snippet?',
		} );
		expect(
			within( dialog ).getByText( /It will run real PHP on your site\./ )
		).toBeInTheDocument();
		expect(
			await within( dialog ).findByText(
				/Reads a file or a remote URL\./
			)
		).toBeInTheDocument();
		await userEvent.click(
			within( dialog ).getByRole( 'button', { name: 'Cancel' } )
		);
		expect(
			calls().find( ( o ) => o.path === `${ API }/snippets/8/status` )
		).toBeUndefined();
		await userEvent.click(
			screen.getByRole( 'switch', { name: 'Activate Reader' } )
		);
		const again = await screen.findByRole( 'dialog', {
			name: 'Activate this snippet?',
		} );
		await userEvent.click(
			within( again ).getByRole( 'button', { name: 'Activate' } )
		);
		await waitFor( () =>
			expect(
				calls().find( ( o ) => o.path === `${ API }/snippets/8/status` )
					.data.active
			).toBe( true )
		);
	} );

	it( 'switches a snippet off without a dialog', async () => {
		apiFetch.mockResolvedValue( payload() );
		mount();
		await userEvent.click(
			screen.getByRole( 'switch', { name: 'Activate Alpha' } )
		);
		await waitFor( () =>
			expect(
				calls().find( ( o ) => o.path === `${ API }/snippets/7/status` )
					.data.active
			).toBe( false )
		);
		expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
	} );

	it( 'saves the code editor value when WordPress provides one', async () => {
		const cm = {
			getValue: () => 'return 3;',
			setValue: jest.fn(),
			on: jest.fn(),
			toTextArea: jest.fn(),
			refresh: jest.fn(),
		};
		window.wp = {
			codeEditor: { initialize: jest.fn( () => ( { codemirror: cm } ) ) },
		};
		apiFetch.mockResolvedValue(
			payload( { codeEditor: { codemirror: {} } } )
		);
		mount( payload( { codeEditor: { codemirror: {} } } ) );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Add snippet' } )
		);
		const drawer = await screen.findByRole( 'dialog', {
			name: 'Add snippet',
		} );
		await waitFor( () =>
			expect( window.wp.codeEditor.initialize ).toHaveBeenCalled()
		);
		await userEvent.click(
			within( drawer ).getByRole( 'button', { name: 'Save draft' } )
		);
		await waitFor( () =>
			expect(
				calls().find(
					( o ) =>
						o.path === `${ API }/snippets` && 'POST' === o.method
				).data.code
			).toBe( 'return 3;' )
		);
	} );

	it( 'a new snippet never starts with the code of the one edited before', async () => {
		const seen = [];
		const cm = {
			getValue: () => '',
			setValue: jest.fn(),
			on: jest.fn(),
			toTextArea: jest.fn(),
			refresh: jest.fn(),
		};
		window.wp = {
			codeEditor: {
				initialize: jest.fn( ( area ) => {
					seen.push( area.value );
					return { codemirror: cm };
				} ),
			},
		};
		apiFetch.mockImplementation( ( o ) =>
			o.path === `${ API }/snippets/7` && ! o.method
				? Promise.resolve( detail( 7 ) )
				: Promise.resolve( payload() )
		);
		const d = payload( { codeEditor: { codemirror: {} } } );
		mount( d );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Edit Alpha' } )
		);
		const edit = await screen.findByRole( 'dialog', {
			name: 'Edit snippet',
		} );
		await waitFor( () => expect( seen ).toContain( 'return 1;' ) );
		await userEvent.click(
			within( edit ).getByRole( 'button', { name: 'Cancel' } )
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Add snippet' } )
		);
		await screen.findByRole( 'dialog', { name: 'Add snippet' } );
		await waitFor( () => expect( seen.length ).toBeGreaterThan( 1 ) );
		expect( seen[ seen.length - 1 ] ).toBe( '' );
		expect( seen.filter( ( v ) => 'return 1;' === v ) ).toHaveLength( 1 );
	} );

	it( 'disables editing without the capabilities', () => {
		mount( payload( { canEdit: false } ) );
		expect(
			screen.getByRole( 'switch', { name: 'Activate Alpha' } )
		).toBeDisabled();
		expect(
			screen.getByRole( 'button', { name: 'Edit Alpha' } )
		).toBeDisabled();
		expect(
			screen.getByText(
				'Managing PHP snippets requires the manage_options and unfiltered_html capabilities.'
			)
		).toBeInTheDocument();
	} );
} );
