import { act, renderHook } from '@testing-library/react';
import { useSettingsForm } from './useSettingsForm';

describe( 'useSettingsForm', () => {
	afterEach( () => delete document.documentElement.dataset.emcpDirty );

	it( 'tracks changed keys and the diff', () => {
		const { result } = renderHook( () => useSettingsForm( { a: true, b: false }, jest.fn() ) );
		act( () => result.current.setValue( 'b', true ) );
		expect( result.current.count ).toBe( 1 );
		expect( result.current.diff ).toEqual( { b: true } );
		act( () => result.current.setValue( 'b', false ) );
		expect( result.current.dirty ).toBe( false );
	} );

	it( 'sets and clears the dirty flag and the unload guard', () => {
		const add = jest.spyOn( window, 'addEventListener' );
		const remove = jest.spyOn( window, 'removeEventListener' );
		const { result } = renderHook( () => useSettingsForm( { a: 1 }, jest.fn() ) );
		act( () => result.current.setValue( 'a', 2 ) );
		expect( document.documentElement.dataset.emcpDirty ).toBe( '1' );
		expect( add ).toHaveBeenCalledWith( 'beforeunload', expect.any( Function ) );
		act( () => result.current.discard() );
		expect( document.documentElement.dataset.emcpDirty ).toBeUndefined();
		expect( remove ).toHaveBeenCalledWith( 'beforeunload', expect.any( Function ) );
	} );

	it( 'saves once on a double submit and adopts the server response', async () => {
		let resolve;
		const save = jest.fn( () => new Promise( ( r ) => ( resolve = r ) ) );
		const { result } = renderHook( () => useSettingsForm( { a: 1 }, save ) );
		act( () => result.current.setValue( 'a', 2 ) );
		let first;
		act( () => {
			first = result.current.submit();
			result.current.submit();
		} );
		expect( save ).toHaveBeenCalledTimes( 1 );
		expect( save ).toHaveBeenCalledWith( { a: 2 }, { a: 2 } );
		await act( async () => {
			resolve( { a: 3 } );
			await first;
		} );
		expect( result.current.values ).toEqual( { a: 3 } );
		expect( result.current.dirty ).toBe( false );
	} );

	it( 'stays dirty and exposes the error when saving fails', async () => {
		const save = jest.fn().mockRejectedValue( new Error( 'nope' ) );
		const { result } = renderHook( () => useSettingsForm( { a: 1 }, save ) );
		act( () => result.current.setValue( 'a', 2 ) );
		let ok;
		await act( async () => {
			ok = await result.current.submit();
		} );
		expect( ok ).toBe( false );
		expect( result.current.dirty ).toBe( true );
		expect( result.current.error.message ).toBe( 'nope' );
	} );
} );
