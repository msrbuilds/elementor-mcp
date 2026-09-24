import { useCallback, useEffect, useRef, useState } from '@wordpress/element';

/**
 * Open state for a trigger + panel: closes on Escape (returning focus to the
 * trigger) and on a mousedown outside both (leaving focus where the user put it).
 *
 * @return {Object} { open, setOpen, toggle, close( restoreFocus = true ), triggerRef, panelRef }
 */
export function usePopover() {
	const [ open, setOpen ] = useState( false );
	const triggerRef = useRef( null );
	const panelRef = useRef( null );

	const close = useCallback( ( restoreFocus = true ) => {
		setOpen( false );
		if ( restoreFocus ) {
			triggerRef.current?.focus();
		}
	}, [] );

	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}
		const onMouseDown = ( e ) => {
			if ( ! panelRef.current?.contains( e.target ) && ! triggerRef.current?.contains( e.target ) ) {
				close( false );
			}
		};
		const onKeyDown = ( e ) => {
			if ( 'Escape' === e.key ) {
				e.stopPropagation();
				close( true );
			}
		};
		document.addEventListener( 'mousedown', onMouseDown );
		document.addEventListener( 'keydown', onKeyDown );
		return () => {
			document.removeEventListener( 'mousedown', onMouseDown );
			document.removeEventListener( 'keydown', onKeyDown );
		};
	}, [ open, close ] );

	const toggle = useCallback( () => setOpen( ( o ) => ! o ), [] );
	return { open, setOpen, toggle, close, triggerRef, panelRef };
}
