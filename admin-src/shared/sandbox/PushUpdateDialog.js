import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Dialog,
	Field,
	Textarea,
	errorMessage,
	request,
	useToast,
} from '@emcp/ui';

/**
 * Send the local copy of a published item to its Marketplace listing for review.
 *
 * @param {Object}                props         Props.
 * @param {Object|null}           props.row     The item, or null when closed.
 * @param {() => void}            props.onClose Close.
 * @param {(res: Object) => void} props.onDone  ( res ) after the update is sent.
 */
export function PushUpdateDialog( { row, onClose, onDone } ) {
	const toast = useToast();
	const [ changelog, setChangelog ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	useEffect( () => {
		if ( row ) {
			setChangelog( '' );
		}
	}, [ row ] );

	const send = async () => {
		setBusy( true );
		try {
			const res = await request(
				`/emcp-tools/v1/admin/marketplace/${ row.kind }/${ row.id }/update`,
				{ method: 'POST', data: { changelog } }
			);
			onDone( res );
		} catch ( e ) {
			toast.error( errorMessage( e ) );
		} finally {
			setBusy( false );
		}
	};

	return (
		<Dialog
			open={ !! row }
			title={ __( 'Push update to Marketplace', 'emcp-tools' ) }
			onClose={ busy ? () => {} : onClose }
			footer={
				<>
					<Button onClick={ onClose } disabled={ busy }>
						{ __( 'Cancel', 'emcp-tools' ) }
					</Button>
					<Button variant="primary" loading={ busy } onClick={ send }>
						{ __( 'Send for review', 'emcp-tools' ) }
					</Button>
				</>
			}
		>
			<Field label={ __( 'What changed', 'emcp-tools' ) } optional>
				{ ( p ) => (
					<Textarea
						{ ...p }
						rows={ 4 }
						value={ changelog }
						onChange={ ( e ) => setChangelog( e.target.value ) }
					/>
				) }
			</Field>
		</Dialog>
	);
}
