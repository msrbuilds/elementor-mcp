/**
 * Never-blank fallback (spec 5.2 layer 1). Printed inline by the frame, before
 * any screen bundle loads, so it works when a bundle is blocked, missing or
 * throws before React mounts. No dependencies.
 */
const OURS = /\/assets\/admin\/build(-pro)?\//;

/**
 * Watch for screen-bundle failures and reveal the recovery panel.
 *
 * @param {Window}   win       Window.
 * @param {Document} doc       Document.
 * @param {number}   timeoutMs Reveal when the screen has not reported ready by then.
 * @param {() => void} reload    Called by the Reload button.
 * @return {?{reveal: () => void, stop: () => void}} Controls, or null without the frame.
 */
export function installFallback(
	win,
	doc,
	timeoutMs = 15000,
	reload = () => win.location.reload()
) {
	const container = doc.getElementById( 'emcp-screen' );
	const panel =
		container && container.querySelector( '[data-emcp-recovery]' );
	if ( ! panel ) {
		return null;
	}
	const details = container.querySelector( '[data-emcp-recovery-details]' );
	const failures = [];

	const reveal = () => {
		if ( win.emcpScreenReady ) {
			return;
		}
		panel.hidden = false;
		if ( details ) {
			details.textContent = failures.length
				? failures.join( '\n' )
				: 'The screen script did not finish loading in time.';
		}
	};
	const onResourceError = ( e ) => {
		const t = e.target;
		if ( t && 'SCRIPT' === t.tagName && OURS.test( t.src || '' ) ) {
			failures.push( 'Failed to load ' + t.src );
			reveal();
		}
	};
	const onScriptError = ( e ) => {
		if ( OURS.test( e.filename || '' ) ) {
			failures.push( ( e.message || 'Error' ) + ' (' + e.filename + ')' );
			reveal();
		}
	};

	doc.addEventListener( 'error', onResourceError, true );
	win.addEventListener( 'error', onScriptError );
	const timer = win.setTimeout( reveal, timeoutMs );

	const reloadBtn = container.querySelector( '[data-emcp-reload]' );
	const copyBtn = container.querySelector( '[data-emcp-copy]' );
	if ( reloadBtn ) {
		reloadBtn.addEventListener( 'click', () => reload() );
	}
	if ( copyBtn && details ) {
		copyBtn.addEventListener( 'click', () => {
			const text = details.textContent || '';
			if ( win.navigator.clipboard && win.isSecureContext ) {
				win.navigator.clipboard.writeText( text ).catch( () => {} );
				return;
			}
			const range = doc.createRange();
			range.selectNodeContents( details );
			const selection = win.getSelection();
			selection.removeAllRanges();
			selection.addRange( range );
		} );
	}

	return {
		reveal,
		stop() {
			win.clearTimeout( timer );
			doc.removeEventListener( 'error', onResourceError, true );
			win.removeEventListener( 'error', onScriptError );
		},
	};
}

if (
	'undefined' !== typeof window &&
	'undefined' !== typeof document &&
	! window.__emcpFallbackInstalled
) {
	window.__emcpFallbackInstalled = true;
	installFallback( window, document );
}
