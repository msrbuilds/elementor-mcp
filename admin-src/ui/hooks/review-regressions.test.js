/**
 * Regression tests for the Plan 1A final review findings in the data hooks.
 */
import { act, renderHook, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import { useSettingsForm } from './useSettingsForm';
import { useResource } from './useResource';

jest.mock( '@wordpress/api-fetch' );

describe( 'review finding 3: edits made while a save is in flight', () => {
	it( 'keeps a change made after submit and stays dirty', async () => {
		let resolve;
		const save = jest.fn( () => new Promise( ( r ) => ( resolve = r ) ) );
		const { result } = renderHook( () =>
			useSettingsForm( { a: 1, b: 1 }, save )
		);
		act( () => result.current.setValue( 'a', 2 ) );
		let pending;
		act( () => {
			pending = result.current.submit();
		} );
		act( () => result.current.setValue( 'b', 5 ) );
		await act( async () => {
			resolve( { a: 2, b: 1 } );
			await pending;
		} );
		expect( result.current.values ).toEqual( { a: 2, b: 5 } );
		expect( result.current.dirty ).toBe( true );
		expect( result.current.diff ).toEqual( { b: 5 } );
	} );
} );

describe( 'review finding 8: useResource and changing paths', () => {
	beforeEach( () => apiFetch.mockReset() );

	it( 'refetches when the path changes, even with boot data', async () => {
		apiFetch.mockResolvedValue( { page: 2 } );
		const { result, rerender } = renderHook(
			( { path } ) => useResource( path, { page: 1 } ),
			{ initialProps: { path: '/x?page=1' } }
		);
		expect( apiFetch ).not.toHaveBeenCalled();
		rerender( { path: '/x?page=2' } );
		await waitFor( () =>
			expect( result.current.data ).toEqual( { page: 2 } )
		);
		expect( apiFetch ).toHaveBeenCalledWith( { path: '/x?page=2' } );
	} );

	it( 'ignores a slow response that a newer request superseded', async () => {
		const resolvers = {};
		apiFetch.mockImplementation(
			( { path } ) => new Promise( ( r ) => ( resolvers[ path ] = r ) )
		);
		const { result, rerender } = renderHook(
			( { path } ) => useResource( path ),
			{ initialProps: { path: '/q=a' } }
		);
		rerender( { path: '/q=ab' } );
		await act( async () => {
			resolvers[ '/q=ab' ]( { q: 'ab' } );
		} );
		await act( async () => {
			resolvers[ '/q=a' ]( { q: 'a' } );
		} );
		expect( result.current.data ).toEqual( { q: 'ab' } );
		expect( result.current.loading ).toBe( false );
	} );
} );
