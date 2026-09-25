/**
 * Structural equality for JSON-like values (objects, arrays, primitives).
 *
 * @param {*} a First value.
 * @param {*} b Second value.
 * @return {boolean} Whether they are structurally equal.
 */
export function isEqual( a, b ) {
	if ( a === b ) {
		return true;
	}
	if (
		null === a ||
		null === b ||
		'object' !== typeof a ||
		'object' !== typeof b
	) {
		return false;
	}
	if ( Array.isArray( a ) !== Array.isArray( b ) ) {
		return false;
	}
	const ka = Object.keys( a );
	const kb = Object.keys( b );
	if ( ka.length !== kb.length ) {
		return false;
	}
	return ka.every(
		( k ) =>
			Object.prototype.hasOwnProperty.call( b, k ) &&
			isEqual( a[ k ], b[ k ] )
	);
}
