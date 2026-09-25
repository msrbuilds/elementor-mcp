/**
 * Shell entry: mounts the shell app on #emcp-shell-root and loads the frame CSS.
 */
import { createRoot } from '@wordpress/element';
import { AppProviders } from '@emcp/ui';
import { ShellApp } from './ShellApp';
import './shell.css';
import './palette.css';

const root = document.getElementById( 'emcp-shell-root' );
if ( root ) {
	createRoot( root ).render(
		<AppProviders>
			<ShellApp data={ window.emcpShell || {} } />
		</AppProviders>
	);
}
