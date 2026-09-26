import { useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, errorMessage, request, useToast } from '@emcp/ui';
import { API } from './api';

/**
 * "Import bundle": a JSON bundle upload that always lands as a new inactive draft.
 *
 * @param {Object}                props            Props.
 * @param {(res: Object) => void} props.onImported ( res ) after a successful import.
 */
export function ImportButton( { onImported } ) {
	const toast = useToast();
	const input = useRef();
	const [ busy, setBusy ] = useState( false );

	const onChange = async ( e ) => {
		const file = e.target.files && e.target.files[ 0 ];
		if ( ! file ) {
			return;
		}
		const form = new window.FormData();
		form.append( 'bundle', file );
		setBusy( true );
		try {
			const res = await request( `${ API }/import`, {
				method: 'POST',
				body: form,
			} );
			onImported( res );
		} catch ( err ) {
			toast.error( errorMessage( err ) );
		} finally {
			setBusy( false );
			e.target.value = '';
		}
	};

	return (
		<>
			<input
				ref={ input }
				type="file"
				accept="application/json,.json"
				className="eui-visually-hidden"
				aria-label={ __( 'Bundle file', 'emcp-tools' ) }
				tabIndex={ -1 }
				onChange={ onChange }
			/>
			<Button
				icon="upload"
				loading={ busy }
				onClick={ () => input.current && input.current.click() }
			>
				{ __( 'Import bundle', 'emcp-tools' ) }
			</Button>
		</>
	);
}
