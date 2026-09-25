import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { request } from './api';

/**
 * A REST resource with optional boot data. With `initial` the first paint needs
 * no request (spec 5.2); without it the resource is fetched on mount. A later
 * path change always refetches, and only the newest request may update state,
 * so a slow response can never overwrite a newer one.
 *
 * @param {string} path    REST path of the resource.
 * @param {*}      initial Boot data, or undefined to fetch on mount.
 * @return {Object} { data, loading, error, refresh, mutate, setData }
 */
export function useResource( path, initial ) {
	const [ data, setData ] = useState( initial );
	const [ loading, setLoading ] = useState( undefined === initial );
	const [ error, setError ] = useState( null );
	const latest = useRef( 0 );
	const firstRun = useRef( true );

	const refresh = useCallback( async () => {
		latest.current += 1;
		const id = latest.current;
		setLoading( true );
		setError( null );
		try {
			const fresh = await request( path );
			if ( id === latest.current ) {
				setData( fresh );
			}
			return fresh;
		} catch ( e ) {
			if ( id === latest.current ) {
				setError( e );
			}
			throw e;
		} finally {
			if ( id === latest.current ) {
				setLoading( false );
			}
		}
	}, [ path ] );

	useEffect( () => {
		const skip = firstRun.current && undefined !== initial;
		firstRun.current = false;
		if ( ! skip ) {
			refresh().catch( () => {} );
		}
		// Boot data covers the first path only; every later path is fetched.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ path ] );

	const mutate = useCallback(
		async ( method, body, { subPath = '', replace = true } = {} ) => {
			const result = await request( path + subPath, {
				method,
				data: body,
			} );
			if ( replace ) {
				setData( result );
			}
			return result;
		},
		[ path ]
	);

	return { data, loading, error, refresh, mutate, setData };
}
