/**
 * Regression test for the Plan 1A final review: a screen that throws a
 * non-Error value must still show the error card, never a blank screen.
 */
import { act, screen } from '@testing-library/react';
import { mountScreen } from './mount';

it( 'shows the error card when a screen throws a string', async () => {
	const consoleSpy = jest
		.spyOn( console, 'error' )
		.mockImplementation( () => {} );
	document.body.innerHTML =
		'<div id="emcp-screen" data-screen="tools"><div data-emcp-fallback>Loading</div><div data-emcp-root></div></div>';
	window.emcpBoot = { data: {} };
	function Broken() {
		throw 'plain string failure';
	}
	await act( async () => {
		mountScreen( 'tools', Broken );
	} );
	expect( screen.getByRole( 'alert' ) ).toHaveTextContent(
		'plain string failure'
	);
	consoleSpy.mockRestore();
} );
