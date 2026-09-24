import { act, screen, waitFor } from '@testing-library/react';
import { mountScreen, markReady } from './mount';

function frame( id ) {
	document.body.innerHTML = `<div id="emcp-screen" data-screen="${ id }"><div data-emcp-fallback>Loading</div><div data-emcp-root></div></div>`;
	return document.getElementById( 'emcp-screen' );
}

describe( 'mountScreen', () => {
	beforeEach( () => {
		delete window.emcpScreenReady;
		window.emcpBoot = { screen: 'tools', data: { enabled: 116 } };
	} );

	it( 'renders the screen with boot data and removes the fallback', async () => {
		frame( 'tools' );
		function Tools( { data } ) {
			return <h1>{ data.enabled } tools</h1>;
		}
		await act( async () => {
			expect( mountScreen( 'tools', Tools ) ).toBe( true );
		} );
		expect( screen.getByRole( 'heading', { name: '116 tools' } ) ).toBeInTheDocument();
		await waitFor( () => expect( document.querySelector( '[data-emcp-fallback]' ) ).toBeNull() );
		expect( window.emcpScreenReady ).toBe( true );
	} );

	it( 'shows the error card and still removes the fallback when the screen throws', async () => {
		jest.spyOn( console, 'error' ).mockImplementation( () => {} );
		frame( 'tools' );
		function Broken() {
			throw new Error( 'boom' );
		}
		await act( async () => {
			mountScreen( 'tools', Broken );
		} );
		expect( screen.getByRole( 'alert' ) ).toHaveTextContent( 'boom' );
		expect( screen.getByRole( 'button', { name: 'Reload' } ) ).toBeInTheDocument();
		await waitFor( () => expect( document.querySelector( '[data-emcp-fallback]' ) ).toBeNull() );
		console.error.mockRestore();
	} );

	it( 'returns false when the frame is not on the page or is for another screen', () => {
		document.body.innerHTML = '';
		expect( mountScreen( 'tools', () => null ) ).toBe( false );
		frame( 'modules' );
		expect( mountScreen( 'tools', () => null ) ).toBe( false );
	} );

	it( 'markReady tolerates a missing fallback', () => {
		const el = frame( 'tools' );
		el.querySelector( '[data-emcp-fallback]' ).remove();
		expect( () => markReady( el ) ).not.toThrow();
	} );
} );
