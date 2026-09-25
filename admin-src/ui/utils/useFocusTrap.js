import { useEffect, useRef } from '@wordpress/element';

const FOCUSABLE =
	'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';

/**
 * While `active`, keep Tab inside `ref`, call `onEscape` on Escape, focus the
 * first focusable element, and restore focus to the previously focused
 * element when deactivated.
 *
 * @param {Object}   ref      React ref to the container.
 * @param {boolean}  active   Whether the trap is on.
 * @param {Function} onEscape Called when Escape is pressed.
 */
export function useFocusTrap( ref, active, onEscape ) {
	const escapeRef = useRef( onEscape );
	escapeRef.current = onEscape;

	useEffect( () => {
		const node = ref.current;
		if ( ! active || ! node ) {
			return undefined;
		}
		const doc = node.ownerDocument;
		const previous = doc.activeElement;
		const focusables = () =>
			Array.from( node.querySelectorAll( FOCUSABLE ) );
		( focusables()[ 0 ] || node ).focus();

		const onKeyDown = ( e ) => {
			if ( 'Escape' === e.key ) {
				e.stopPropagation();
				escapeRef.current?.();
				return;
			}
			if ( 'Tab' !== e.key ) {
				return;
			}
			const list = focusables();
			if ( ! list.length ) {
				e.preventDefault();
				return;
			}
			const first = list[ 0 ];
			const last = list[ list.length - 1 ];
			if ( e.shiftKey && doc.activeElement === first ) {
				e.preventDefault();
				last.focus();
			} else if ( ! e.shiftKey && doc.activeElement === last ) {
				e.preventDefault();
				first.focus();
			}
		};
		node.addEventListener( 'keydown', onKeyDown );
		return () => {
			node.removeEventListener( 'keydown', onKeyDown );
			if ( previous && 'function' === typeof previous.focus ) {
				previous.focus();
			}
		};
	}, [ active, ref ] );
}
