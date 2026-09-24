import { createContext, createPortal, useCallback, useContext, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Icon } from './Icon';
import { IconButton } from './Button';
import { cx } from '../utils/cx';
import './Toast.css';

const ToastContext = createContext( null );
const ICONS = { success: 'circle-check', error: 'circle-alert', info: 'info' };

export function ToastProvider( { children } ) {
	const [ toasts, setToasts ] = useState( [] );
	const seq = useRef( 0 );
	const timers = useRef( new Map() );

	const dismiss = useCallback( ( id ) => {
		clearTimeout( timers.current.get( id ) );
		timers.current.delete( id );
		setToasts( ( list ) => list.filter( ( t ) => t.id !== id ) );
	}, [] );

	const push = useCallback( ( tone, message ) => {
		seq.current += 1;
		const id = seq.current;
		setToasts( ( list ) => [ ...list, { id, tone, message } ] );
		if ( 'error' !== tone ) {
			timers.current.set( id, setTimeout( () => dismiss( id ), 5000 ) );
		}
		return id;
	}, [ dismiss ] );

	useEffect( () => {
		const pending = timers.current;
		return () => pending.forEach( ( t ) => clearTimeout( t ) );
	}, [] );

	const api = useMemo( () => ( {
		success: ( m ) => push( 'success', m ),
		error: ( m ) => push( 'error', m ),
		info: ( m ) => push( 'info', m ),
		dismiss,
	} ), [ push, dismiss ] );

	return (
		<ToastContext.Provider value={ api }>
			{ children }
			{ createPortal(
				<div className="eui-portal eui-toasts">
					{ toasts.map( ( t ) => (
						<div key={ t.id } className={ cx( 'eui-toast', `eui-toast--${ t.tone }` ) } role={ 'error' === t.tone ? 'alert' : 'status' }>
							<Icon name={ ICONS[ t.tone ] } />
							<span className="eui-toast__message">{ t.message }</span>
							<IconButton icon="x" size="sm" label={ __( 'Dismiss', 'emcp-tools' ) } onClick={ () => dismiss( t.id ) } />
						</div>
					) ) }
				</div>,
				document.body
			) }
		</ToastContext.Provider>
	);
}

export function useToast() {
	const api = useContext( ToastContext );
	if ( ! api ) {
		throw new Error( 'useToast must be used inside ToastProvider' );
	}
	return api;
}
