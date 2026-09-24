import { createContext, createPortal, useCallback, useContext, useEffect, useId, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useFocusTrap } from '../utils/useFocusTrap';
import { cx } from '../utils/cx';
import { Button, IconButton } from './Button';
import { Field, TextInput } from './Form';
import './Dialog.css';

function Panel( { open, title, onClose, children, footer, variant, size, width } ) {
	const ref = useRef( null );
	const titleId = useId();
	useFocusTrap( ref, open, onClose );
	if ( ! open ) {
		return null;
	}
	return createPortal(
		<div
			className={ cx( 'eui-portal', 'eui-overlay', `eui-overlay--${ variant }` ) }
			onMouseDown={ ( e ) => {
				if ( e.target === e.currentTarget ) {
					onClose();
				}
			} }
		>
			<div ref={ ref } role="dialog" aria-modal="true" aria-labelledby={ titleId } tabIndex={ -1 } className={ cx( `eui-${ variant }`, size && `eui-${ variant }--${ size }` ) } style={ width ? { width } : undefined }>
				<header className="eui-panel__head">
					<h2 id={ titleId } className="eui-panel__title">{ title }</h2>
					<IconButton icon="x" label={ __( 'Close', 'emcp-tools' ) } onClick={ onClose } />
				</header>
				<div className="eui-panel__body">{ children }</div>
				{ footer && <footer className="eui-panel__foot">{ footer }</footer> }
			</div>
		</div>,
		document.body
	);
}

export function Dialog( { size = 'md', ...props } ) {
	return <Panel { ...props } variant="dialog" size={ size } />;
}

export function Drawer( { width, ...props } ) {
	return <Panel { ...props } variant="drawer" width={ width } />;
}

export function ConfirmDialog( { open, title, message, confirmLabel, cancelLabel, tone = 'default', requireText, onConfirm, onCancel } ) {
	const [ typed, setTyped ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	useEffect( () => {
		if ( open ) {
			setTyped( '' );
			setBusy( false );
		}
	}, [ open ] );
	const blocked = !! requireText && typed !== requireText;
	const confirm = async () => {
		setBusy( true );
		try {
			await onConfirm();
		} finally {
			setBusy( false );
		}
	};
	return (
		<Dialog
			open={ open }
			title={ title }
			size="sm"
			onClose={ busy ? () => {} : onCancel }
			footer={
				<>
					<Button onClick={ onCancel } disabled={ busy }>{ cancelLabel || __( 'Cancel', 'emcp-tools' ) }</Button>
					<Button variant={ 'danger' === tone ? 'danger' : 'primary' } onClick={ confirm } disabled={ blocked } loading={ busy }>
						{ confirmLabel || __( 'Confirm', 'emcp-tools' ) }
					</Button>
				</>
			}
		>
			{ message && <p className="eui-confirm__message">{ message }</p> }
			{ requireText && (
				<Field label={ sprintf( /* translators: %s: the word to type. */ __( 'Type %s to confirm', 'emcp-tools' ), requireText ) }>
					{ ( p ) => <TextInput { ...p } value={ typed } onChange={ ( e ) => setTyped( e.target.value ) } autoComplete="off" /> }
				</Field>
			) }
		</Dialog>
	);
}

const ConfirmContext = createContext( null );

export function ConfirmProvider( { children } ) {
	const [ request, setRequest ] = useState( null );
	const confirm = useCallback( ( options ) => new Promise( ( resolve ) => setRequest( { options, resolve } ) ), [] );
	const finish = ( result ) => {
		request?.resolve( result );
		setRequest( null );
	};
	return (
		<ConfirmContext.Provider value={ confirm }>
			{ children }
			<ConfirmDialog open={ !! request } { ...( request?.options || {} ) } onConfirm={ () => finish( true ) } onCancel={ () => finish( false ) } />
		</ConfirmContext.Provider>
	);
}

export function useConfirm() {
	const confirm = useContext( ConfirmContext );
	if ( ! confirm ) {
		throw new Error( 'useConfirm must be used inside ConfirmProvider' );
	}
	return confirm;
}
