import { initPromo } from './promo';

const bar = ( n ) => {
	const slides = Array.from(
		{ length: n },
		( _, i ) =>
			`<div class="eui-frame-promo__slide${
				0 === i ? ' is-active' : ''
			}"${ 0 === i ? '' : ' hidden' }>Slide ${ i + 1 }</div>`
	).join( '' );
	const dots = Array.from(
		{ length: n },
		( _, i ) =>
			`<button type="button" data-emcp-promo-dot="${ i }"${
				0 === i ? ' aria-current="true"' : ''
			}></button>`
	).join( '' );
	document.body.innerHTML = `<div data-emcp-promo>${ slides }<button type="button" data-emcp-promo-prev></button>${ dots }<button type="button" data-emcp-promo-next></button></div>`;
	return document.querySelector( '[data-emcp-promo]' );
};

const active = () =>
	Array.from( document.querySelectorAll( '.eui-frame-promo__slide' ) ).map(
		( s ) => ( s.hidden ? 0 : 1 )
	);

describe( 'initPromo', () => {
	beforeEach( () => jest.useFakeTimers() );
	afterEach( () => jest.useRealTimers() );

	it( 'rotates automatically and wraps around', () => {
		initPromo( bar( 3 ), { interval: 7000 } );
		expect( active() ).toEqual( [ 1, 0, 0 ] );
		jest.advanceTimersByTime( 7000 );
		expect( active() ).toEqual( [ 0, 1, 0 ] );
		jest.advanceTimersByTime( 14000 );
		expect( active() ).toEqual( [ 1, 0, 0 ] );
	} );

	it( 'moves with next, previous and the dots, keeping aria-current in step', () => {
		initPromo( bar( 3 ), { interval: 7000 } );
		document.querySelector( '[data-emcp-promo-next]' ).click();
		expect( active() ).toEqual( [ 0, 1, 0 ] );
		document.querySelector( '[data-emcp-promo-prev]' ).click();
		document.querySelector( '[data-emcp-promo-prev]' ).click();
		expect( active() ).toEqual( [ 0, 0, 1 ] );
		document.querySelector( '[data-emcp-promo-dot="0"]' ).click();
		expect( active() ).toEqual( [ 1, 0, 0 ] );
		expect(
			document
				.querySelector( '[data-emcp-promo-dot="0"]' )
				.getAttribute( 'aria-current' )
		).toBe( 'true' );
		expect(
			document
				.querySelector( '[data-emcp-promo-dot="2"]' )
				.hasAttribute( 'aria-current' )
		).toBe( false );
	} );

	it( 'pauses while hovered or focused', () => {
		const root = bar( 2 );
		initPromo( root, { interval: 7000 } );
		root.dispatchEvent( new window.MouseEvent( 'mouseenter' ) );
		jest.advanceTimersByTime( 20000 );
		expect( active() ).toEqual( [ 1, 0 ] );
		root.dispatchEvent( new window.MouseEvent( 'mouseleave' ) );
		jest.advanceTimersByTime( 7000 );
		expect( active() ).toEqual( [ 0, 1 ] );
		root.dispatchEvent( new window.FocusEvent( 'focusin' ) );
		jest.advanceTimersByTime( 20000 );
		expect( active() ).toEqual( [ 0, 1 ] );
	} );

	it( 'does not auto-rotate for reduced motion, but the buttons still work', () => {
		initPromo( bar( 2 ), { interval: 7000, reducedMotion: true } );
		jest.advanceTimersByTime( 30000 );
		expect( active() ).toEqual( [ 1, 0 ] );
		document.querySelector( '[data-emcp-promo-next]' ).click();
		expect( active() ).toEqual( [ 0, 1 ] );
	} );

	it( 'gives the bar the colour of the slide it shows', () => {
		const root = bar( 3 );
		root.querySelectorAll( '.eui-frame-promo__slide' ).forEach( ( s, i ) =>
			s.setAttribute(
				'data-emcp-promo-tone',
				[ 'cloud', 'pro', 'ltd' ][ i ]
			)
		);
		initPromo( root, { interval: 7000 } );
		expect( root.dataset.tone ).toBe( 'cloud' );
		document.querySelector( '[data-emcp-promo-next]' ).click();
		expect( root.dataset.tone ).toBe( 'pro' );
		jest.advanceTimersByTime( 7000 );
		expect( root.dataset.tone ).toBe( 'ltd' );
		document.querySelector( '[data-emcp-promo-dot="0"]' ).click();
		expect( root.dataset.tone ).toBe( 'cloud' );
	} );

	it( 'leaves a single announcement alone', () => {
		initPromo( bar( 1 ), { interval: 7000 } );
		jest.advanceTimersByTime( 30000 );
		expect( active() ).toEqual( [ 1 ] );
	} );
} );
