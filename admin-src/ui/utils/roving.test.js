import { rovingKeyDown } from './roving';

function key( k ) {
	return { key: k, preventDefault: jest.fn() };
}

describe( 'rovingKeyDown', () => {
	afterEach( () => document.documentElement.removeAttribute( 'dir' ) );

	it( 'moves and wraps horizontally', () => {
		const onMove = jest.fn();
		expect( rovingKeyDown( key( 'ArrowRight' ), { index: 2, count: 3, onMove } ) ).toBe( true );
		expect( onMove ).toHaveBeenLastCalledWith( 0 );
		rovingKeyDown( key( 'ArrowLeft' ), { index: 0, count: 3, onMove } );
		expect( onMove ).toHaveBeenLastCalledWith( 2 );
	} );

	it( 'supports Home and End', () => {
		const onMove = jest.fn();
		rovingKeyDown( key( 'End' ), { index: 0, count: 4, onMove } );
		expect( onMove ).toHaveBeenLastCalledWith( 3 );
		rovingKeyDown( key( 'Home' ), { index: 3, count: 4, onMove } );
		expect( onMove ).toHaveBeenLastCalledWith( 0 );
	} );

	it( 'mirrors arrows in right-to-left admin', () => {
		document.documentElement.setAttribute( 'dir', 'rtl' );
		const onMove = jest.fn();
		rovingKeyDown( key( 'ArrowRight' ), { index: 1, count: 3, onMove } );
		expect( onMove ).toHaveBeenLastCalledWith( 0 );
	} );

	it( 'uses up and down when vertical and ignores other keys', () => {
		const onMove = jest.fn();
		rovingKeyDown( key( 'ArrowDown' ), { index: 0, count: 2, onMove, orientation: 'vertical' } );
		expect( onMove ).toHaveBeenLastCalledWith( 1 );
		expect( rovingKeyDown( key( 'a' ), { index: 0, count: 2, onMove } ) ).toBe( false );
	} );
} );
