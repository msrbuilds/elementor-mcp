import { render, screen } from '@testing-library/react';
import { axe } from 'jest-axe';
import { LockedScreen } from './LockedScreen';

const data = {
	title: 'AI Chat',
	description: 'Chat with any AI model in the admin and the editors.',
	bullets: [
		'Build pages by describing them',
		'Edit the page you are on',
		'Bring your own API key',
	],
	upgrade_url: 'https://emcptools.com/pricing?upgrade=1',
	compare_url: 'https://emcptools.com/pricing',
};

it( 'shows the Pro feature with upgrade and compare links', async () => {
	const { container } = render( <LockedScreen data={ data } /> );
	expect( screen.getByRole( 'heading', { level: 1 } ) ).toHaveTextContent(
		'AI Chat'
	);
	expect( screen.getByText( 'Pro' ) ).toHaveClass( 'eui-badge--tier-pro' );
	expect( screen.getAllByRole( 'listitem' ) ).toHaveLength( 3 );
	expect(
		screen.getByRole( 'link', { name: 'Upgrade to Pro' } )
	).toHaveAttribute( 'href', data.upgrade_url );
	expect(
		screen.getByRole( 'link', { name: /Compare plans/ } )
	).toHaveAttribute( 'target', '_blank' );
	expect( await axe( container ) ).toHaveNoViolations();
} );
