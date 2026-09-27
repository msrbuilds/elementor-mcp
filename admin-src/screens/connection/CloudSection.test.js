import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AppProviders } from '@emcp/ui';
import { CloudSection } from './CloudSection';

const base = {
	connected: true,
	healthy: true,
	baseUrl: 'https://emcptools.com',
	account: 'mi•••@gmail.com',
	host: 'emcptools.com',
	connectedAt: '2026-09-20',
	gateway: false,
	connectAction: 'emcp_tools_cloud_connect',
	connectNonce: 'cn',
	disconnectUrl: '/d',
	reissueUrl: '/r',
	gatewayOffUrl: '/off',
	sync: null,
};

function mount( cloud = {}, navigate = jest.fn() ) {
	render(
		<AppProviders>
			<CloudSection
				data={ {
					adminPostUrl: '/wp-admin/admin-post.php',
					cloud: { ...base, ...cloud },
				} }
				navigate={ navigate }
			/>
		</AppProviders>
	);
	return navigate;
}

describe( 'CloudSection account card', () => {
	it( 'names the linked account, redacted, with the host and date', () => {
		mount();
		expect( screen.getByText( 'mi•••@gmail.com' ) ).toBeInTheDocument();
		const when = new Date( '2026-09-20T00:00:00Z' ).toLocaleDateString(
			undefined,
			{ dateStyle: 'medium', timeZone: 'UTC' }
		);
		expect(
			screen.getByText( `emcptools.com · Connected ${ when }` )
		).toBeInTheDocument();
	} );

	it( 'says how to see the email when the connection predates it', () => {
		mount( { account: '', connectedAt: '', gateway: true } );
		expect( screen.getByText( 'EMCP Cloud account' ) ).toBeInTheDocument();
		expect( screen.getByText( 'emcptools.com' ) ).toBeInTheDocument();
		// Reconnecting keeps gateway access as it is.
		const button = screen.getByRole( 'button', {
			name: 'Reconnect to show the account email',
		} );
		const form = button.closest( 'form' );
		expect( form.querySelector( 'input[name="action"]' ).value ).toBe(
			'emcp_tools_cloud_connect'
		);
		expect(
			form.querySelector( 'input[name="emcp_gateway_optin"]' )
		).not.toBeNull();
	} );

	it( 'switching gateway access on provisions it', async () => {
		const navigate = mount();
		const sw = screen.getByRole( 'switch', { name: 'Gateway access' } );
		expect( sw ).toHaveAttribute( 'aria-checked', 'false' );
		expect(
			screen.queryByRole( 'link', { name: 'Re-issue credential' } )
		).toBeNull();
		await userEvent.click( sw );
		expect( navigate ).toHaveBeenCalledWith( '/r' );
		expect( sw ).toHaveAttribute( 'aria-checked', 'true' );
		expect( sw ).toBeDisabled();
	} );

	it( 'switching gateway access off asks first', async () => {
		const navigate = mount( { gateway: true } );
		const sw = screen.getByRole( 'switch', { name: 'Gateway access' } );
		expect( sw ).toHaveAttribute( 'aria-checked', 'true' );
		await userEvent.click( sw );
		const dialog = await screen.findByRole( 'dialog', {
			name: 'Turn off gateway access?',
		} );
		await userEvent.click(
			within( dialog ).getByRole( 'button', { name: 'Cancel' } )
		);
		expect( navigate ).not.toHaveBeenCalled();
		expect( sw ).toHaveAttribute( 'aria-checked', 'true' );

		await userEvent.click( sw );
		await userEvent.click(
			within(
				await screen.findByRole( 'dialog', {
					name: 'Turn off gateway access?',
				} )
			).getByRole( 'button', { name: 'Turn off' } )
		);
		expect( navigate ).toHaveBeenCalledWith( '/off' );
	} );

	it( 'offers a credential re-issue while the gateway is on', () => {
		mount( { gateway: true } );
		expect(
			screen.getByRole( 'link', { name: 'Re-issue credential' } )
		).toHaveAttribute( 'href', '/r' );
	} );

	it( 'disconnect asks first and then leaves', async () => {
		const navigate = mount();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Disconnect' } )
		);
		await userEvent.click(
			within(
				await screen.findByRole( 'dialog', {
					name: 'Disconnect EMCP Cloud?',
				} )
			).getByRole( 'button', { name: 'Disconnect' } )
		);
		expect( navigate ).toHaveBeenCalledWith( '/d' );
	} );

	it( 'connecting uses a gateway switch that is on by default', async () => {
		mount( { connected: false, healthy: false } );
		const sw = screen.getByRole( 'switch', {
			name: 'Let EMCP Cloud manage this site through the gateway',
		} );
		const form = sw.closest( 'form' );
		expect( sw ).toHaveAttribute( 'aria-checked', 'true' );
		expect(
			form.querySelector( 'input[name="emcp_gateway_optin"]' )
		).not.toBeNull();
		await userEvent.click( sw );
		expect(
			form.querySelector( 'input[name="emcp_gateway_optin"]' )
		).toBeNull();
	} );
} );
