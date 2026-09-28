import { render } from '@testing-library/react';
import { useRef } from '@wordpress/element';
import { useWheelScroll } from './useWheelScroll';

const fs = require( 'fs' );
const path = require( 'path' );

function Row( { onReady } ) {
	const ref = useRef();
	useWheelScroll( ref );
	return (
		<div
			ref={ ( el ) => {
				ref.current = el;
				onReady( el );
			} }
		/>
	);
}

function row( { scrollWidth, clientWidth } ) {
	let el;
	render( <Row onReady={ ( e ) => ( el = el || e ) } /> );
	Object.defineProperty( el, 'scrollWidth', { value: scrollWidth } );
	Object.defineProperty( el, 'clientWidth', { value: clientWidth } );
	return el;
}

const wheel = ( el, init ) => {
	const e = new window.WheelEvent( 'wheel', {
		bubbles: true,
		cancelable: true,
		...init,
	} );
	el.dispatchEvent( e );
	return e;
};

describe( 'category chips', () => {
	it( 'stay on one row and scroll sideways', () => {
		const css = fs.readFileSync(
			path.join( __dirname, 'prompts.css' ),
			'utf8'
		);
		expect( css ).toMatch(
			/\.eui-prompts__chips\s*\{[^}]*flex-wrap:\s*nowrap[^}]*overflow-x:\s*auto/
		);
		expect( css ).toMatch(
			/\.eui-prompts__chips > \*\s*\{[^}]*flex:\s*none/
		);
	} );

	it( 'a vertical wheel scrolls an overflowing row sideways', () => {
		const el = row( { scrollWidth: 900, clientWidth: 400 } );
		const e = wheel( el, { deltaY: 120 } );
		expect( el.scrollLeft ).toBe( 120 );
		expect( e.defaultPrevented ).toBe( true );
	} );

	it( 'leaves the wheel to the page when nothing overflows or at the end', () => {
		const fits = row( { scrollWidth: 400, clientWidth: 400 } );
		expect( wheel( fits, { deltaY: 120 } ).defaultPrevented ).toBe( false );
		const end = row( { scrollWidth: 900, clientWidth: 400 } );
		end.scrollLeft = 500;
		expect( wheel( end, { deltaY: 120 } ).defaultPrevented ).toBe( false );
		// A trackpad's own sideways swipe is left alone.
		const pad = row( { scrollWidth: 900, clientWidth: 400 } );
		expect( wheel( pad, { deltaX: 40, deltaY: 5 } ).defaultPrevented ).toBe(
			false
		);
	} );
} );
