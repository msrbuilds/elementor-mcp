import { act, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { AppProviders } from '@emcp/ui';
import { McpSetup } from './McpSetup';
import { data } from './fixture';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

function mount( url = '/wp-admin/admin.php?page=emcp-tools-connection' ) {
	window.history.replaceState( {}, '', url );
	return render(
		<AppProviders>
			<McpSetup data={ data } />
		</AppProviders>
	);
}

describe( 'McpSetup', () => {
	beforeEach( () => {
		apiFetch.mockReset();
		apiFetch.mockImplementation( ( { path } ) =>
			Promise.resolve(
				path.includes( '/setup' )
					? { setup_id: 'set_1', token: 'tok1', since: 1, expires: 2 }
					: {}
			)
		);
	} );

	it( 'unlocks steps in order and opens a setup when step 3 shows', async () => {
		mount();
		expect(
			screen.getByRole( 'heading', { name: 'Choose your AI client' } )
		).toBeInTheDocument();
		expect(
			screen.queryByText( 'Open Connectors' )
		).not.toBeInTheDocument();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Claude Desktop' } )
		);
		await userEvent.click( screen.getByRole( 'radio', { name: /OAuth/ } ) );
		expect(
			await screen.findByText( 'Open Connectors' )
		).toBeInTheDocument();
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/emcp-tools/v1/admin/connection/setup',
				method: 'POST',
				data: { client: 'claude-desktop', method: 'oauth' },
			} )
		);
		expect( window.location.search ).toContain( 'client=claude-desktop' );
		expect( window.location.search ).toContain( 'method=oauth' );
	} );

	it( 'shows each client with its logo, or an icon when it has none', async () => {
		const clients = [
			{ ...data.clients[ 0 ], image: '/img/claude.png' },
			...data.clients.slice( 1 ).map( ( c ) => ( { ...c, image: '' } ) ),
		];
		window.history.replaceState(
			{},
			'',
			'/wp-admin/admin.php?page=emcp-tools-connection'
		);
		render(
			<AppProviders>
				<McpSetup data={ { ...data, clients } } />
			</AppProviders>
		);
		const chips = await screen.findByRole( 'group', {
			name: 'AI clients',
		} );
		const logo = within( chips )
			.getByRole( 'button', { name: 'Claude Desktop' } )
			.querySelector( 'img.eui-chip__media' );
		expect( logo ).toHaveAttribute( 'src', '/img/claude.png' );
		const plain = within( chips ).getByRole( 'button', {
			name: 'Claude.ai',
		} );
		expect( plain.querySelector( 'svg.eui-chip__media' ) ).not.toBeNull();
	} );

	it( 'gives the steps a section heading so headings do not skip a level', () => {
		mount();
		expect(
			screen.getByRole( 'heading', {
				level: 2,
				name: 'Connect an AI client',
			} )
		).toBeInTheDocument();
	} );

	it( 'locked steps never claim they are done', async () => {
		mount(
			'/wp-admin/admin.php?page=emcp-tools-connection&client=claude-desktop'
		);
		expect( screen.queryByText( 'Added' ) ).not.toBeInTheDocument();
	} );

	it( 'changing the client opens a new setup', async () => {
		mount(
			'/wp-admin/admin.php?page=emcp-tools-connection&client=claude-desktop&method=oauth'
		);
		await screen.findByText( 'Open Connectors' );
		await userEvent.click(
			screen.getAllByRole( 'button', { name: /Edit/ } )[ 0 ]
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Claude.ai' } )
		);
		await screen.findByText( 'Add the connector to claude.ai' );
		const setups = apiFetch.mock.calls.filter( ( [ a ] ) =>
			a.path.includes( '/setup' )
		);
		expect( setups.map( ( [ a ] ) => a.data.client ) ).toEqual( [
			'claude-desktop',
			'claude-ai',
		] );
	} );

	it( 'hides WP-CLI for clients that cannot run it', async () => {
		mount(
			'/wp-admin/admin.php?page=emcp-tools-connection&client=claude-ai'
		);
		expect(
			screen.queryByRole( 'radio', { name: /WP-CLI/ } )
		).not.toBeInTheDocument();
	} );

	it( 'creates an application password, shows it once and binds the setup', async () => {
		apiFetch.mockImplementation( ( { path } ) => {
			if ( path.includes( '/setup' ) ) {
				return Promise.resolve( {
					setup_id: 'set_1',
					token: 'tok1',
					since: 1,
					expires: 2,
				} );
			}
			if ( path.includes( '/app-password' ) ) {
				return Promise.resolve( {
					username: 'admin',
					password: 'abcd efgh',
					name: 'EMCP',
					uuid: 'u1',
				} );
			}
			return Promise.resolve( {} );
		} );
		mount(
			'/wp-admin/admin.php?page=emcp-tools-connection&client=claude-desktop&method=app'
		);
		await userEvent.click(
			await screen.findByRole( 'button', { name: 'Create password' } )
		);
		expect( await screen.findByText( 'abcd efgh' ) ).toBeInTheDocument();
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/emcp-tools/v1/admin/connection/app-password',
				data: { user_id: 1, setup: 'set_1' },
			} )
		);
		expect(
			screen.getByText( 'Manual config: direct HTTP' )
		).toBeInTheDocument();
	} );

	it( 'an existing password asks for its text, then fills the configs with it', async () => {
		apiFetch.mockImplementation( ( { path } ) => {
			if ( path.includes( '/setup' ) ) {
				return Promise.resolve( {
					setup_id: 'set_1',
					token: 'tok1',
					since: 1,
					expires: 2,
				} );
			}
			if ( path.includes( '/app-passwords' ) ) {
				return Promise.resolve( {
					passwords: [ { uuid: 'old1', name: 'Claude Code' } ],
				} );
			}
			return Promise.resolve( {} );
		} );
		mount(
			'/wp-admin/admin.php?page=emcp-tools-connection&client=claude-desktop&method=app'
		);
		await userEvent.click(
			await screen.findByText( 'Use a password I already have' )
		);
		await userEvent.selectOptions(
			screen.getByLabelText( 'Which password' ),
			'old1'
		);
		const field = screen.getByLabelText( 'Its password' );
		expect( field ).toHaveFocus();
		expect(
			screen.queryByText(
				'Create a password to see the config for this client.'
			)
		).not.toBeInTheDocument();
		expect(
			screen.getByText(
				'WordPress keeps only a hash of an application password, so paste the password you saved when you created it into “Its password”; the configs then fill in.'
			)
		).toBeInTheDocument();
		await userEvent.type( field, 'wxyz 1234' );
		expect(
			screen.getByText( 'Manual config: direct HTTP' )
		).toBeInTheDocument();
		expect(
			document.body.textContent.includes( btoa( 'admin:wxyz1234' ) )
		).toBe( true );
	} );

	it( 'the chosen password stays selected while its setup is still opening', async () => {
		let setups = 0;
		apiFetch.mockImplementation( ( { path } ) => {
			if ( path.includes( '/setup' ) ) {
				setups++;
				return 1 === setups
					? Promise.resolve( {
							setup_id: 'set_1',
							token: 'tok1',
							since: 1,
							expires: 2,
						} )
					: new Promise( () => {} );
			}
			if ( path.includes( '/app-passwords' ) ) {
				return Promise.resolve( {
					passwords: [ { uuid: 'old1', name: 'Claude Code' } ],
				} );
			}
			return Promise.resolve( {} );
		} );
		mount(
			'/wp-admin/admin.php?page=emcp-tools-connection&client=claude-desktop&method=app'
		);
		await userEvent.click(
			await screen.findByText( 'Use a password I already have' )
		);
		await userEvent.selectOptions(
			screen.getByLabelText( 'Which password' ),
			'old1'
		);
		expect( screen.getByLabelText( 'Which password' ) ).toHaveValue(
			'old1'
		);
	} );

	it( 'reopening the setup keeps waiting for the password already created', async () => {
		apiFetch.mockImplementation( ( { path } ) => {
			if ( path.includes( '/setup' ) ) {
				return Promise.resolve( {
					setup_id: 'set_1',
					token: 'tok1',
					since: 1,
					expires: 2,
				} );
			}
			if ( path.includes( '/app-password' ) ) {
				return Promise.resolve( {
					username: 'admin',
					password: 'abcd efgh',
					name: 'EMCP',
					uuid: 'u1',
				} );
			}
			return Promise.resolve( {} );
		} );
		mount(
			'/wp-admin/admin.php?page=emcp-tools-connection&client=claude-desktop&method=app'
		);
		await userEvent.click(
			await screen.findByRole( 'button', { name: 'Create password' } )
		);
		await screen.findByText( 'abcd efgh' );
		await userEvent.click(
			screen.getAllByRole( 'button', { name: /Edit/ } )[ 0 ]
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Claude.ai' } )
		);
		await act( async () => {} );
		const setups = apiFetch.mock.calls.filter( ( [ a ] ) =>
			a.path.endsWith( '/setup' )
		);
		expect( setups[ setups.length - 1 ][ 0 ].data ).toEqual( {
			client: 'claude-ai',
			method: 'app',
			expect: 'app:u1',
			user_id: 1,
		} );
	} );

	it( 'choosing the same method again after Back continues', async () => {
		mount(
			'/wp-admin/admin.php?page=emcp-tools-connection&client=claude-desktop&method=app'
		);
		await userEvent.click(
			await screen.findByRole( 'button', { name: 'Back' } )
		);
		expect(
			screen.queryByRole( 'button', { name: 'Create password' } )
		).not.toBeInTheDocument();
		await userEvent.click(
			screen.getByRole( 'radio', { name: /Application password/ } )
		);
		expect(
			await screen.findByRole( 'button', { name: 'Create password' } )
		).toBeInTheDocument();
	} );

	it( 'continue waits for a password in application password mode', async () => {
		mount(
			'/wp-admin/admin.php?page=emcp-tools-connection&client=claude-desktop&method=app'
		);
		await screen.findByRole( 'button', { name: 'Create password' } );
		expect(
			screen.getByRole( 'button', { name: "I've added it, continue" } )
		).toBeDisabled();
	} );

	it( 'WP-CLI shows the stdio config with the setup token', async () => {
		mount(
			'/wp-admin/admin.php?page=emcp-tools-connection&client=claude-desktop&method=cli'
		);
		expect(
			await screen.findByText( /"EMCP_SETUP": "tok1"/ )
		).toBeInTheDocument();
	} );

	it( 'OAuth reconnect of an existing app binds the setup to it', async () => {
		mount(
			'/wp-admin/admin.php?page=emcp-tools-connection&client=claude-desktop&method=oauth'
		);
		await screen.findByText( 'Open Connectors' );
		await userEvent.selectOptions(
			screen.getByLabelText(
				'Reconnect an app that is already connected'
			),
			'cl_1'
		);
		await act( async () => {} );
		expect( apiFetch ).toHaveBeenLastCalledWith(
			expect.objectContaining( {
				data: {
					client: 'claude-desktop',
					method: 'oauth',
					expect: 'oauth:cl_1',
				},
			} )
		);
	} );

	it( 'continue unlocks step 4', async () => {
		mount(
			'/wp-admin/admin.php?page=emcp-tools-connection&client=claude-desktop&method=oauth'
		);
		await userEvent.click(
			await screen.findByRole( 'button', {
				name: "I've added it, continue",
			} )
		);
		expect(
			screen.getByText( /Waiting for Claude Desktop to call the server/ )
		).toBeInTheDocument();
	} );
} );
