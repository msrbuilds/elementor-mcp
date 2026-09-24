/**
 * Keyboard movement for a group with one tab stop (radiogroup, tablist, menu).
 * Returns true when the key was handled.
 *
 * @param {KeyboardEvent} event
 * @param {Object}        options
 * @param {number}        options.index       Current item index.
 * @param {number}        options.count       Number of items.
 * @param {Function}      options.onMove      Called with the target index.
 * @param {string}        [options.orientation] 'horizontal' (default) or 'vertical'.
 * @return {boolean} Whether the key was handled.
 */
export function rovingKeyDown( event, { index, count, onMove, orientation = 'horizontal' } ) {
	const rtl = typeof document !== 'undefined' && 'rtl' === document.documentElement.getAttribute( 'dir' );
	let next = 'ArrowDown';
	let prev = 'ArrowUp';
	if ( 'horizontal' === orientation ) {
		next = rtl ? 'ArrowLeft' : 'ArrowRight';
		prev = rtl ? 'ArrowRight' : 'ArrowLeft';
	}
	let target = null;
	if ( event.key === next ) {
		target = ( index + 1 ) % count;
	} else if ( event.key === prev ) {
		target = ( index - 1 + count ) % count;
	} else if ( 'Home' === event.key ) {
		target = 0;
	} else if ( 'End' === event.key ) {
		target = count - 1;
	}
	if ( null === target ) {
		return false;
	}
	event.preventDefault();
	onMove( target );
	return true;
}
