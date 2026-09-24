import { act, renderHook, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import { errorMessage } from './api';
import { useQueryState } from './useQueryState';
import { useResource } from './useResource';

jest.mock( '@wordpress/api-fetch' );

describe( 'errorMessage', () => {
	it( 'explains an expired session', () => {
		expect( errorMessage( { code: 'rest_cookie_invalid_nonce', message: 'x' } ) ).toMatch( /session expired/i );
	} );
	it( 'uses the server message, else a generic one', () => {
		expect( errorMessage( { message: 'Slug is reserved.' } ) ).toBe( 'Slug is reserved.' );
		expect( errorMessage( undefined ) ).toMatch( /Something went wrong/ );
	} );
} );

describe( 'useQueryState', () => {
	beforeEach( () => window.history.replaceState( null, '', '/wp-admin/admin.php?page=emcp-tools-tools&risk=writes' ) );

	it( 'reads from and writes to the URL, dropping defaults', () => {
		const { result } = renderHook( () => useQueryState( 'risk', 'all' ) );
		expect( result.current[ 0 ] ).toBe( 'writes' );
		act( () => result.current[ 1 ]( 'destructive' ) );
		expect( window.location.search ).toContain( 'risk=destructive' );
		act( () => result.current[ 1 ]( 'all' ) );
		expect( window.location.search ).not.toContain( 'risk=' );
		expect( window.location.search ).toContain( 'page=emcp-tools-tools' );
	} );

	it( 'debounces URL writes', () => {
		jest.useFakeTimers();
		const { result } = renderHook( () => useQueryState( 'q', '', { debounce: 250 } ) );
		act( () => result.current[ 1 ]( 'menu' ) );
		expect( result.current[ 0 ] ).toBe( 'menu' );
		expect( window.location.search ).not.toContain( 'q=menu' );
		act( () => jest.advanceTimersByTime( 250 ) );
		expect( window.location.search ).toContain( 'q=menu' );
		jest.useRealTimers();
	} );
} );

describe( 'useResource', () => {
	beforeEach( () => apiFetch.mockReset() );

	it( 'uses initial data without fetching, and mutate replaces it', async () => {
		apiFetch.mockResolvedValue( { enabled: 5 } );
		const { result } = renderHook( () => useResource( '/emcp-tools/v1/admin/tools', { enabled: 4 } ) );
		expect( apiFetch ).not.toHaveBeenCalled();
		await act( async () => {
			await result.current.mutate( 'POST', { enable: [ 'x' ] } );
		} );
		expect( apiFetch ).toHaveBeenCalledWith( { path: '/emcp-tools/v1/admin/tools', method: 'POST', data: { enable: [ 'x' ] } } );
		expect( result.current.data ).toEqual( { enabled: 5 } );
	} );

	it( 'fetches when there is no initial data and records errors', async () => {
		apiFetch.mockRejectedValue( { code: 'rest_forbidden', message: 'No.' } );
		const { result } = renderHook( () => useResource( '/emcp-tools/v1/admin/log' ) );
		await waitFor( () => expect( result.current.loading ).toBe( false ) );
		expect( result.current.error ).toEqual( { code: 'rest_forbidden', message: 'No.' } );
	} );

	it( 'can run an action without replacing data', async () => {
		apiFetch.mockResolvedValue( { ok: true } );
		const { result } = renderHook( () => useResource( '/emcp-tools/v1/admin/history', { rows: [] } ) );
		await act( async () => {
			await result.current.mutate( 'POST', {}, { subPath: '/abc/undo', replace: false } );
		} );
		expect( apiFetch ).toHaveBeenCalledWith( { path: '/emcp-tools/v1/admin/history/abc/undo', method: 'POST', data: {} } );
		expect( result.current.data ).toEqual( { rows: [] } );
	} );
} );
