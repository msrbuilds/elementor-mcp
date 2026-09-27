/**
 * Shell entry: mounts the shell app on #emcp-shell-root and loads the frame CSS.
 */
import { createRoot } from '@wordpress/element';
import { AppProviders } from '@emcp/ui';
import { ShellApp } from './ShellApp';
import { initPromo } from './promo';
import { initSidebarToggle } from './sidebar';
import './shell.css';
import './palette.css';

const promo = document.querySelector( '[data-emcp-promo]' );
if ( promo ) {
	initPromo( promo, {
		interval: 7000,
		reducedMotion:
			!! window.matchMedia &&
			window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches,
	} );
}

const frame = document.querySelector( '.eui-frame' );
if ( frame ) {
	initSidebarToggle( frame );
}

const root = document.getElementById( 'emcp-shell-root' );
if ( root ) {
	createRoot( root ).render(
		<AppProviders>
			<ShellApp data={ window.emcpShell || {} } />
		</AppProviders>
	);
}
