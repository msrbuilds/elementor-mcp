import { __, sprintf } from '@wordpress/i18n';

export function FirstCallStep( { clientLabel } ) {
	return (
		<p className="eui-conn__waiting">
			{ sprintf(
				/* translators: %s: client name. */
				__( 'Waiting for %s to call the server', 'emcp-tools' ),
				clientLabel
			) }
		</p>
	);
}
