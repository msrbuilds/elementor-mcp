import { installFallback } from './index';

function frame() {
	document.body.innerHTML =
		'<div id="emcp-screen" data-screen="tools"><div data-emcp-fallback>' +
		'<div data-emcp-recovery hidden><pre data-emcp-recovery-details></pre>' +
		'<button data-emcp-reload>Reload</button><button data-emcp-copy>Copy details</button></div>' +
		'</div><div data-emcp-root></div></div>';
	return document.querySelector( '[data-emcp-recovery]' );
}

function scriptError( src ) {
	const s = document.createElement( 'script' );
	s.src = src;
	document.body.appendChild( s );
	s.dispatchEvent( new Event( 'error' ) );
}

describe( 'installFallback', () => {
	beforeEach( () => {
		jest.useFakeTimers();
		delete window.emcpScreenReady;
	} );
	afterEach( () => jest.useRealTimers() );

	it( 'reveals the recovery panel when our screen bundle fails to load', () => {
		const panel = frame();
		const fb = installFallback( window, document, 15000 );
		scriptError(
			'http://example.test/wp-content/plugins/emcp-tools/assets/admin/build/screen-tools.js'
		);
		expect( panel.hidden ).toBe( false );
		expect(
			document.querySelector( '[data-emcp-recovery-details]' ).textContent
		).toContain( 'screen-tools.js' );
		fb.stop();
	} );

	it( 'ignores failures of unrelated scripts', () => {
		const panel = frame();
		const fb = installFallback( window, document, 15000 );
		scriptError( 'http://example.test/wp-content/plugins/other/app.js' );
		expect( panel.hidden ).toBe( true );
		fb.stop();
	} );

	it( 'reveals after the timeout unless the screen reported ready', () => {
		let panel = frame();
		let fb = installFallback( window, document, 15000 );
		jest.advanceTimersByTime( 15000 );
		expect( panel.hidden ).toBe( false );
		fb.stop();

		panel = frame();
		fb = installFallback( window, document, 15000 );
		window.emcpScreenReady = true;
		jest.advanceTimersByTime( 15000 );
		expect( panel.hidden ).toBe( true );
		fb.stop();
	} );

	it( 'reveals when the screen bundle throws while evaluating', () => {
		const panel = frame();
		const fb = installFallback( window, document, 15000 );
		window.dispatchEvent(
			new ErrorEvent( 'error', {
				message: 'x is not defined',
				filename:
					'http://example.test/wp-content/plugins/emcp-tools/assets/admin/build-pro/screen-chat.js',
			} )
		);
		expect( panel.hidden ).toBe( false );
		fb.stop();
	} );

	it( 'does nothing without the frame', () => {
		document.body.innerHTML = '';
		expect( installFallback( window, document, 15000 ) ).toBeNull();
	} );

	it( 'wires the reload button', () => {
		frame();
		const reload = jest.fn();
		const fb = installFallback( window, document, 15000, reload );
		document.querySelector( '[data-emcp-reload]' ).click();
		expect( reload ).toHaveBeenCalled();
		fb.stop();
	} );
} );
