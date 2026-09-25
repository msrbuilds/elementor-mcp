/**
 * Join truthy class names.
 * @param {...any} parts
 */
export function cx( ...parts ) {
	return parts.filter( Boolean ).join( ' ' );
}
