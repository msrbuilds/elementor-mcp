import { useEffect } from '@wordpress/element';

/**
 * Lets a vertical mouse wheel scroll a sideways-scrolling row (the category
 * chips). Touch and trackpad swipes scroll it natively. The wheel goes back
 * to the page when the row fits, has reached its end, or the gesture is
 * already sideways. The listener is not passive, so it may preventDefault.
 *
 * @param {{current: ?HTMLElement}} ref The row.
 */
export function useWheelScroll( ref ) {
	useEffect( () => {
		const el = ref.current;
		if ( ! el ) {
			return undefined;
		}
		const onWheel = ( e ) => {
			if ( Math.abs( e.deltaX ) >= Math.abs( e.deltaY ) ) {
				return;
			}
			const max = el.scrollWidth - el.clientWidth;
			if ( max <= 0 ) {
				return;
			}
			const next = Math.max(
				0,
				Math.min( max, el.scrollLeft + e.deltaY )
			);
			if ( next === el.scrollLeft ) {
				return;
			}
			e.preventDefault();
			el.scrollLeft = next;
		};
		el.addEventListener( 'wheel', onWheel, { passive: false } );
		return () => el.removeEventListener( 'wheel', onWheel );
	}, [ ref ] );
}
