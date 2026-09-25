import { useCallback, useEffect, useRef, useState } from '@wordpress/element';

/**
 * A string value mirrored into the page's query string with replaceState, so a
 * reload or a shared link restores filters (spec 7). Default values are removed
 * from the URL.
 *
 * @param {string} key                Query parameter name.
 * @param {string} defaultValue       Value used when the parameter is absent.
 * @param {Object} options
 * @param {number} [options.debounce] Milliseconds to wait before writing the URL.
 * @return {Array} [ value, setValue ]
 */
export function useQueryState( key, defaultValue, { debounce = 0 } = {} ) {
	const [ value, setValue ] = useState( () => {
		const v = new URLSearchParams( window.location.search ).get( key );
		return null === v ? defaultValue : v;
	} );
	const timer = useRef();

	const write = useCallback(
		( next ) => {
			const url = new URL( window.location.href );
			if (
				next === defaultValue ||
				'' === next ||
				null === next ||
				undefined === next
			) {
				url.searchParams.delete( key );
			} else {
				url.searchParams.set( key, String( next ) );
			}
			window.history.replaceState(
				window.history.state,
				'',
				url.toString()
			);
		},
		[ key, defaultValue ]
	);

	const set = useCallback(
		( next ) => {
			setValue( next );
			clearTimeout( timer.current );
			if ( debounce > 0 ) {
				timer.current = setTimeout( () => write( next ), debounce );
			} else {
				write( next );
			}
		},
		[ debounce, write ]
	);

	useEffect( () => () => clearTimeout( timer.current ), [] );
	return [ value, set ];
}
