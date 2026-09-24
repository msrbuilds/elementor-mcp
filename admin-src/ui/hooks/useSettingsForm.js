import { useCallback, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { isEqual } from '../utils/isEqual';

/**
 * Working copy of a settings object with a server baseline (spec 7).
 * Settings screens send `diff` so keys the screen did not change are never
 * overwritten on the server. While dirty, a beforeunload guard is registered
 * and `document.documentElement.dataset.emcpDirty` is '1' for the frame.
 *
 * @param {Object}   initial Server values.
 * @param {Function} save    ( diff, values ) => fresh values (or nothing to keep `values`).
 * @return {Object} Form state and actions.
 */
export function useSettingsForm( initial, save ) {
	const [ baseline, setBaseline ] = useState( initial );
	const [ values, setValues ] = useState( initial );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );
	const savingRef = useRef( false );

	const changedKeys = useMemo(
		() => Object.keys( { ...baseline, ...values } ).filter( ( k ) => ! isEqual( baseline[ k ], values[ k ] ) ),
		[ baseline, values ]
	);
	const dirty = changedKeys.length > 0;
	const diff = useMemo( () => Object.fromEntries( changedKeys.map( ( k ) => [ k, values[ k ] ] ) ), [ changedKeys, values ] );

	useEffect( () => {
		if ( ! dirty ) {
			return undefined;
		}
		const guard = ( e ) => {
			e.preventDefault();
			e.returnValue = '';
		};
		window.addEventListener( 'beforeunload', guard );
		document.documentElement.dataset.emcpDirty = '1';
		return () => {
			window.removeEventListener( 'beforeunload', guard );
			delete document.documentElement.dataset.emcpDirty;
		};
	}, [ dirty ] );

	const setValue = useCallback( ( key, next ) => {
		setValues( ( v ) => ( { ...v, [ key ]: 'function' === typeof next ? next( v[ key ] ) : next } ) );
	}, [] );

	const discard = useCallback( () => {
		setValues( baseline );
		setError( null );
	}, [ baseline ] );

	const submit = useCallback( async () => {
		if ( ! dirty || savingRef.current ) {
			return false;
		}
		savingRef.current = true;
		setSaving( true );
		setError( null );
		try {
			const fresh = ( await save( diff, values ) ) ?? values;
			setBaseline( fresh );
			setValues( fresh );
			return true;
		} catch ( e ) {
			setError( e );
			return false;
		} finally {
			savingRef.current = false;
			setSaving( false );
		}
	}, [ dirty, diff, values, save ] );

	return { values, setValue, setValues, baseline, changedKeys, count: changedKeys.length, dirty, diff, discard, submit, saving, error };
}
