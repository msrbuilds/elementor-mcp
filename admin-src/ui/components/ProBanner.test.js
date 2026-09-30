import { render, screen, within } from '@testing-library/react';
import { ProBanner } from './ProBanner';

describe( 'ProBanner', () => {
	it( 'is a region named by its title, with the text, points and upgrade link', () => {
		render(
			<ProBanner
				id="kits"
				title="Get 50 premium brand kits with EMCP Pro"
				text="You are using 10 starter kits."
				points={ [ '50 brand kits', '7 styles' ] }
				url="https://emcptools.com/pricing"
			/>
		);
		const banner = screen.getByRole( 'region', {
			name: 'Get 50 premium brand kits with EMCP Pro',
		} );
		expect( banner ).toHaveTextContent( 'You are using 10 starter kits.' );
		expect(
			within( banner )
				.getAllByRole( 'listitem' )
				.map( ( li ) => li.textContent )
		).toEqual( [ '50 brand kits', '7 styles' ] );
		const link = within( banner ).getByRole( 'link', {
			name: /Upgrade to Pro/,
		} );
		expect( link ).toHaveAttribute(
			'href',
			'https://emcptools.com/pricing'
		);
		expect( link ).toHaveAttribute( 'target', '_blank' );
		expect( link ).toHaveAttribute( 'rel', 'noopener noreferrer' );
	} );

	it( 'gives each banner its own heading id', () => {
		render(
			<>
				<ProBanner id="a" title="A" text="" url="#" />
				<ProBanner id="b" title="B" text="" url="#" />
			</>
		);
		expect(
			screen.getByRole( 'region', { name: 'A' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'region', { name: 'B' } )
		).toBeInTheDocument();
		expect( screen.queryAllByRole( 'listitem' ) ).toHaveLength( 0 );
	} );
} );
