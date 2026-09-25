import { createRoot, useEffect } from '@wordpress/element';
import { ToastProvider } from '../components/Toast';
import { ConfirmProvider } from '../components/Dialog';
import { ScreenBoundary } from './ScreenBoundary';

export function AppProviders( { children } ) {
	return (
		<ToastProvider>
			<ConfirmProvider>{ children }</ConfirmProvider>
		</ToastProvider>
	);
}

/**
 * Tell the frame the screen rendered: set the flag and remove the fallback panel.
 *
 * @param {Element} container The #emcp-screen element.
 */
export function markReady( container ) {
	window.emcpScreenReady = true;
	const fallback =
		container && container.querySelector( '[data-emcp-fallback]' );
	if ( fallback ) {
		fallback.remove();
	}
}

function Ready( { onReady, children } ) {
	useEffect( () => {
		onReady();
		// Run once, after the first successful commit.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );
	return children;
}

/**
 * Mount a screen into the frame's container (Plan 1B prints it):
 * #emcp-screen[data-screen="{id}"] > [data-emcp-root] + [data-emcp-fallback].
 * Returns false when the container is missing or belongs to another screen.
 *
 * @param {string}   id              Screen id.
 * @param {Function} ScreenComponent Component receiving { boot, data }.
 * @return {boolean} Whether the screen was mounted.
 */
export function mountScreen( id, ScreenComponent ) {
	const container = document.getElementById( 'emcp-screen' );
	if ( ! container || container.getAttribute( 'data-screen' ) !== id ) {
		return false;
	}
	const rootEl = container.querySelector( '[data-emcp-root]' );
	if ( ! rootEl ) {
		return false;
	}
	const boot = window.emcpBoot || {};
	const ready = () => markReady( container );
	createRoot( rootEl ).render(
		<AppProviders>
			<ScreenBoundary onError={ ready }>
				<Ready onReady={ ready }>
					<ScreenComponent boot={ boot } data={ boot.data } />
				</Ready>
			</ScreenBoundary>
		</AppProviders>
	);
	return true;
}
