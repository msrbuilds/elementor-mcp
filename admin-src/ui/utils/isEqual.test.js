import { isEqual } from './isEqual';

it( 'compares nested values', () => {
	expect( isEqual( { a: [ 1, { b: 2 } ] }, { a: [ 1, { b: 2 } ] } ) ).toBe( true );
	expect( isEqual( { a: [ 1, 2 ] }, { a: [ 2, 1 ] } ) ).toBe( false );
	expect( isEqual( { a: 1 }, { a: 1, b: undefined } ) ).toBe( false );
	expect( isEqual( null, {} ) ).toBe( false );
	expect( isEqual( [], {} ) ).toBe( false );
	expect( isEqual( 'x', 'x' ) ).toBe( true );
} );
