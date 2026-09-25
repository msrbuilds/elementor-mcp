import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { AppProviders } from '@emcp/ui';
import { ConnectionScreen } from './ConnectionScreen';
import { data as setupData } from './fixture';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const data = {
	...setupData,
	status: {
		adapter: 'bundled',
		abilitiesApi: true,
		serverEnabled: true,
		toolsEnabled: 116,
		toolsTotal: 191,
	},
	advanced: {
		server_enabled: true,
		oauth_enabled: true,
		strict_schemas: false,
		public_base_url: '',
	},
	detectedBaseUrl: 'https://ex.test',
	services: [
		{
			key: 'emcp_tools_unsplash_access_key',
			label: 'Unsplash',
			group: 'stock',
			secret: true,
			hasValue: true,
			fromConstant: false,
			value: '',
			hint: '',
			url: '',
		},
		{
			key: 'emcp_tools_pexels_api_key',
			label: 'Pexels',
			group: 'stock',
			secret: true,
			hasValue: false,
			fromConstant: true,
			value: '',
			hint: '',
			url: '',
		},
		{
			key: 'emcp_tools_wpcli_command',
			label: 'WP-CLI command',
			group: 'wpcli',
			secret: false,
			hasValue: false,
			fromConstant: false,
			value: '',
			hint: '',
			url: '',
		},
	],
	cloud: {
		connected: false,
		healthy: false,
		baseUrl: 'https://cloud.test',
		gateway: false,
		connectAction: 'emcp_tools_cloud_connect',
		connectNonce: 'cn',
		disconnectUrl: '/d',
		reissueUrl: '/r',
		sync: null,
	},
	adminPostUrl: '/wp-admin/admin-post.php',
};

function mount( url = '/wp-admin/admin.php?page=emcp-tools-connection' ) {
	window.history.replaceState( {}, '', url );
	return render(
		<AppProviders>
			<ConnectionScreen data={ data } />
		</AppProviders>
	);
}

describe( 'ConnectionScreen', () => {
	beforeEach( () => {
		apiFetch.mockReset();
		apiFetch.mockResolvedValue( {
			setup_id: 'set_1',
			token: 't',
			since: 1,
			expires: 2,
		} );
	} );

	it( 'shows server status and the endpoint', () => {
		mount();
		const rail = screen.getByRole( 'region', { name: 'Server status' } );
		expect( within( rail ).getByText( 'All good' ) ).toBeInTheDocument();
		expect( within( rail ).getByText( '116 / 191' ) ).toBeInTheDocument();
		expect( within( rail ).getByText( data.endpoint ) ).toBeInTheDocument();
	} );

	it( 'explains an empty connected apps list', () => {
		window.history.replaceState(
			{},
			'',
			'/wp-admin/admin.php?page=emcp-tools-connection'
		);
		render(
			<AppProviders>
				<ConnectionScreen data={ { ...data, apps: [] } } />
			</AppProviders>
		);
		expect( screen.getByText( /No apps yet/ ) ).toBeInTheDocument();
	} );

	it( 'saves advanced settings as a diff', async () => {
		apiFetch.mockResolvedValueOnce( {
			advanced: { ...data.advanced, strict_schemas: true },
			status: data.status,
			oauth: data.oauth,
			ignored: [],
		} );
		mount();
		await userEvent.click(
			screen.getByRole( 'switch', { name: 'OpenAI-strict schemas' } )
		);
		await userEvent.click( screen.getByRole( 'button', { name: 'Save' } ) );
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/emcp-tools/v1/admin/connection/advanced',
				data: { strict_schemas: true },
			} )
		);
	} );

	it( 'removing an app asks first', async () => {
		apiFetch.mockResolvedValue( { apps: [] } );
		mount();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Remove Old app' } )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Remove Old app?',
		} );
		await userEvent.click(
			within( dialog ).getByRole( 'button', { name: 'Remove' } )
		);
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/emcp-tools/v1/admin/connection/oauth-clients/cl_1',
				method: 'DELETE',
			} )
		);
	} );

	it( 'services keep masked secrets, lock constants and clear on request', async () => {
		apiFetch.mockResolvedValue( { services: data.services, ignored: [] } );
		mount(
			'/wp-admin/admin.php?page=emcp-tools-connection&section=services'
		);
		expect( screen.getByLabelText( 'Pexels' ) ).toBeDisabled();
		expect(
			screen.getByText( 'Set in wp-config.php' )
		).toBeInTheDocument();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Clear Unsplash' } )
		);
		await userEvent.type( screen.getByLabelText( 'WP-CLI command' ), 'wp' );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		expect( apiFetch ).toHaveBeenLastCalledWith(
			expect.objectContaining( {
				path: '/emcp-tools/v1/admin/connection/services',
				data: {
					values: {
						emcp_tools_unsplash_access_key: null,
						emcp_tools_wpcli_command: 'wp',
					},
				},
			} )
		);
	} );

	it( 'pulling settings from the cloud asks first', async () => {
		window.history.replaceState(
			{},
			'',
			'/wp-admin/admin.php?page=emcp-tools-connection&section=cloud'
		);
		const cloud = {
			...data.cloud,
			connected: true,
			healthy: true,
			sync: { entitled: true, nonce: 'sn', billingUrl: '/b' },
		};
		render(
			<AppProviders>
				<ConnectionScreen data={ { ...data, cloud } } />
			</AppProviders>
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Pull settings from cloud' } )
		);
		const dialog = await screen.findByRole( 'dialog', {
			name: /overwrite this site/,
		} );
		await userEvent.click(
			within( dialog ).getByRole( 'button', { name: 'Cancel' } )
		);
		expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
	} );

	it( 'a Cloud redirect flag opens the Cloud section with a notice', () => {
		mount(
			'/wp-admin/admin.php?page=emcp-tools-connection&cloud_connected=1'
		);
		expect( screen.getByRole( 'radio', { name: 'Cloud' } ) ).toBeChecked();
		expect(
			screen.getByText( /connected to EMCP Cloud/ )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Connect to EMCP Cloud' } )
		).toBeInTheDocument();
	} );
} );
