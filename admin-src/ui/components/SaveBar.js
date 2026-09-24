import { __, _n, sprintf } from '@wordpress/i18n';
import { Button } from './Button';
import './SaveBar.css';

export function SaveBar( { count, hint, onDiscard, onSave, saving = false, saveLabel } ) {
	if ( count <= 0 ) {
		return null;
	}
	return (
		<div className="eui-savebar" role="region" aria-label={ __( 'Unsaved changes', 'emcp-tools' ) }>
			<span className="eui-savebar__dot" aria-hidden="true" />
			<strong className="eui-savebar__count" aria-live="polite">
				{ sprintf( /* translators: %d: number of unsaved changes. */ _n( '%d unsaved change', '%d unsaved changes', count, 'emcp-tools' ), count ) }
			</strong>
			{ hint && <span className="eui-savebar__hint">{ hint }</span> }
			<Button variant="ghost-inverse" onClick={ onDiscard } disabled={ saving }>{ __( 'Discard', 'emcp-tools' ) }</Button>
			<Button variant="primary" onClick={ onSave } loading={ saving }>{ saveLabel || __( 'Save changes', 'emcp-tools' ) }</Button>
		</div>
	);
}
