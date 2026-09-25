import { useCallback, useEffect, useState } from '@wordpress/element';
import { request } from './api';

/**
 * A REST resource with optional boot data. With `initial` the first paint needs
 * no request (spec 5.2); without it the resource is fetched on mount.
 *
 * @param {string} path    REST path of the resource.
 * @param {*}      initial Boot data, or undefined to fetch on mount.
 * @return {Object} { data, loading, error, refresh, mutate, setData }
 */
export function useResource( path, initial ) {
	const [ data, setData ] = useState( initial );
	const [ loading, setLoading ] = useState( undefined === initial );
	const [ error, setError ] = useState( null );

	const refresh = useCallback( async () => {
		setLoading( true );
		setError( null );
		try {
			const fresh = await request( path );
			setData( fresh );
			return fresh;
		} catch ( e ) {
			setError( e );
			throw e;
		} finally {
			setLoading( false );
		}
	}, [ path ] );

	useEffect( () => {
		if ( undefined === initial ) {
			refresh().catch( () => {} );
		}
		// Fetch once per path; `initial` is boot data and must not refetch on change.
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
