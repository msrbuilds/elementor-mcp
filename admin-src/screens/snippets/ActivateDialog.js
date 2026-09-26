import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Dialog, Skeleton, request } from '@emcp/ui';
import { Findings } from './SnippetEditor';

/**
 * Confirm before a snippet runs; lists the warning findings when there are any.
 *
 * @param {Object}                props         Props.
 * @param {Object|null}           props.request { row } while asking, else null.
 * @param {(ok: boolean) => void} props.onDone  The answer.
 */
export function ActivateDialog( { request: ask, onDone } ) {
	const [ detail, setDetail ] = useState( null );
	const row = ask ? ask.row : null;
	const warn = !! row && 'warning' === row.review.level;

	useEffect( () => {
		setDetail( null );
		if ( ! warn ) {
			return;
		}
		let live = true;
		request( `/emcp-tools/v1/admin/sandbox/snippets/${ row.id }` )
			.then( ( d ) => live && setDetail( d ) )
			.catch( () => live && setDetail( { validation: null } ) );
		return () => {
			live = false;
		};
	}, [ ask ] ); // eslint-disable-line react-hooks/exhaustive-deps

	return (
		<Dialog
			open={ !! ask }
			title={ __( 'Activate this snippet?', 'emcp-tools' ) }
			onClose={ () => onDone( false ) }
			footer={
				<>
					<Button onClick={ () => onDone( false ) }>
						{ __( 'Cancel', 'emcp-tools' ) }
					</Button>
					<Button variant="primary" onClick={ () => onDone( true ) }>
						{ __( 'Activate', 'emcp-tools' ) }
					</Button>
				</>
			}
		>
			<p>
				{ __(
					'It will run real PHP on your site. Make sure you have read and trust the code.',
					'emcp-tools'
				) }
			</p>
			{ warn && (
				<div className="emcp-sn-activate">
					<p className="emcp-sn-activate__head">
						{ __( 'Worth reading first', 'emcp-tools' ) }
					</p>
					{ detail ? (
						<Findings
							validation={ detail.validation }
							summary={ null }
							only="warning"
						/>
					) : (
						<Skeleton lines={ 2 } />
					) }
				</div>
			) }
		</Dialog>
	);
}
