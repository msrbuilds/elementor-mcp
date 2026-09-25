/**
 * Keyboard movement for a group with one tab stop (radiogroup, tablist, menu).
 * Returns true when the key was handled.
 *
 * @param {KeyboardEvent} event
 * @param {Object}        options
 * @param {number}        options.index         Current item index.
 * @param {number}        options.count         Number of items.
 * @param {Function}      options.onMove        Called with the target index.
 * @param {string}        [options.orientation] 'horizontal' (default) or 'vertical'.
 * @param {Function}      [options.isDisabled]  ( index ) => boolean; disabled items are skipped.
 * @return {boolean} Whether the key was handled.
 */
export function rovingKeyDown(
	event,
	{
		index,
		count,
		onMove,
		orientation = 'horizontal',
		isDisabled = () => false,
	}
) {
	const rtl =
		typeof document !== 'undefined' &&
		'rtl' === document.documentElement.getAttribute( 'dir' );
	let next = 'ArrowDown';
	let prev = 'ArrowUp';
	if ( 'horizontal' === orientation ) {
		next = rtl ? 'ArrowLeft' : 'ArrowRight';
		prev = rtl ? 'ArrowRight' : 'ArrowLeft';
	}
	const step = ( from, dir ) => {
		for ( let n = 1; n <= count; n++ ) {
			const i = ( from + dir * n + count * n ) % count;
			if ( ! isDisabled( i ) ) {
				return i;
			}
		}
		return null;
	};
	let target = null;
	if ( event.key === next ) {
		target = step( index, 1 );
	} else if ( event.key === prev ) {
		target = step( index, -1 );
	} else if ( 'Home' === event.key ) {
		target = step( -1, 1 );
	} else if ( 'End' === event.key ) {
		target = step( count, -1 );
	}
	if ( null === target || target === index ) {
		return false;
	}
	event.preventDefault();
	onMove( target );
	return true;
}
