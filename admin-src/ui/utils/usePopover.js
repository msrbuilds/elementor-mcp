import {
	useCallback,
	useEffect,
	useLayoutEffect,
	useRef,
	useState,
} from '@wordpress/element';

const GAP = 6;

/**
 * Fixed-position coordinates for a panel under its trigger. Fixed positioning
 * lets the panel escape scroll containers (tables, dialog bodies) without
 * moving it out of the DOM, so focus traps and Tab order keep working.
 *
 * @param {Element} trigger Trigger element.
 * @param {string}  align   'start' or 'end' (logical, RTL aware).
 * @return {Object} Inline style for the panel.
 */
export function panelPosition( trigger, align ) {
	const rect = trigger.getBoundingClientRect();
	const rtl = 'rtl' === document.documentElement.getAttribute( 'dir' );
	const alignRight = 'end' === align ? ! rtl : rtl;
	const style = { position: 'fixed', top: `${ rect.bottom + GAP }px` };
	if ( alignRight ) {
		style.right = `${ Math.max( 0, window.innerWidth - rect.right ) }px`;
	} else {
		style.left = `${ Math.max( 0, rect.left ) }px`;
	}
	return style;
}

/**
 * Open state for a trigger + panel.
 * - Escape closes the panel and returns focus to the trigger. It is handled in
 *   the capture phase and stopped there, so a popover inside a Dialog or
 *   Drawer closes itself without closing the surrounding panel.
 * - A mousedown outside both closes it, leaving focus where the user put it.
 * - `style` holds fixed coordinates, refreshed on scroll and resize.
 *
 * @param {Object} options
 * @param {string} [options.align] 'start' (default) or 'end'.
 * @return {Object} { open, setOpen, toggle, close, triggerRef, panelRef, style }
 */
export function usePopover( { align = 'start' } = {} ) {
	const [ open, setOpen ] = useState( false );
	const [ style, setStyle ] = useState( undefined );
	const triggerRef = useRef( null );
	const panelRef = useRef( null );

	const close = useCallback( ( restoreFocus = true ) => {
		setOpen( false );
		if ( restoreFocus ) {
			triggerRef.current?.focus();
		}
	}, [] );

	useLayoutEffect( () => {
		if ( ! open || ! triggerRef.current ) {
			return undefined;
		}
		const place = () =>
			setStyle( panelPosition( triggerRef.current, align ) );
		place();
		window.addEventListener( 'scroll', place, true );
		window.addEventListener( 'resize', place );
		return () => {
			window.removeEventListener( 'scroll', place, true );
			window.removeEventListener( 'resize', place );
		};
	}, [ open, align ] );

	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}
		const onMouseDown = ( e ) => {
			if (
				! panelRef.current?.contains( e.target ) &&
				! triggerRef.current?.contains( e.target )
			) {
				close( false );
			}
		};
		const onKeyDown = ( e ) => {
			if ( 'Escape' === e.key ) {
				e.stopPropagation();
				e.preventDefault();
				close( true );
			}
		};
		document.addEventListener( 'mousedown', onMouseDown );
		document.addEventListener( 'keydown', onKeyDown, true );
		return () => {
			document.removeEventListener( 'mousedown', onMouseDown );
			document.removeEventListener( 'keydown', onKeyDown, true );
		};
	}, [ open, close ] );

	const toggle = useCallback( () => setOpen( ( o ) => ! o ), [] );
	return { open, setOpen, toggle, close, triggerRef, panelRef, style };
}
