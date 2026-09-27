import { render, screen, act, fireEvent } from '@testing-library/react';
import { AnimatedNumber } from './Motion';
import { Segmented, Tabs } from './Choice';
import { BarChart, HBarList } from './Charts';

const fs = require( 'fs' );
const path = require( 'path' );

const css = ( rel ) => fs.readFileSync( path.join( __dirname, rel ), 'utf8' );

/**
 * Motion is on only when matchMedia says the visitor has no reduced-motion
 * preference; jsdom has no matchMedia, so these tests install one.
 *
 * @param {boolean} reduce Whether the visitor asks for reduced motion.
 */
function motion( reduce ) {
	window.matchMedia = ( q ) => ( {
		matches: reduce && q.includes( 'reduce' ),
		media: q,
		addEventListener() {},
		removeEventListener() {},
	} );
}

describe( 'AnimatedNumber', () => {
	let frames;
	let now;
	beforeEach( () => {
		frames = [];
		now = 0;
		jest.spyOn( window, 'requestAnimationFrame' ).mockImplementation(
			( cb ) => frames.push( cb )
		);
		jest.spyOn( window, 'cancelAnimationFrame' ).mockImplementation(
			() => {}
		);
		jest.spyOn( window.performance, 'now' ).mockImplementation( () => now );
	} );
	afterEach( () => {
		jest.restoreAllMocks();
		delete window.matchMedia;
	} );
	const tick = ( ms ) =>
		act( () => {
			now += ms;
			const run = frames;
			frames = [];
			run.forEach( ( cb ) => cb( now ) );
		} );

	it( 'shows the value at once without matchMedia or with reduced motion', () => {
		delete window.matchMedia;
		const { rerender } = render( <AnimatedNumber value={ 184 } /> );
		expect( screen.getByText( '184' ) ).toBeTruthy();
		motion( true );
		rerender( <AnimatedNumber value={ 57 } /> );
		expect( screen.getByText( '57' ) ).toBeTruthy();
		expect( frames ).toHaveLength( 0 );
	} );

	it( 'counts up from zero on mount and lands on the exact value', () => {
		motion( false );
		const { container } = render(
			<AnimatedNumber value={ 184 } duration={ 600 } />
		);
		expect( container.textContent ).toBe( '0' );
		tick( 300 );
		const mid = Number( container.textContent );
		expect( mid ).toBeGreaterThan( 0 );
		expect( mid ).toBeLessThan( 184 );
		tick( 400 );
		expect( container.textContent ).toBe( '184' );
	} );

	it( 'counts from the old value to the new one on change', () => {
		motion( false );
		const { container, rerender } = render(
			<AnimatedNumber value={ 10 } duration={ 100 } />
		);
		tick( 200 );
		expect( container.textContent ).toBe( '10' );
		rerender( <AnimatedNumber value={ 20 } duration={ 100 } /> );
		expect( container.textContent ).toBe( '10' );
		tick( 50 );
		const mid = Number( container.textContent );
		expect( mid ).toBeGreaterThan( 10 );
		expect( mid ).toBeLessThan( 20 );
		tick( 100 );
		expect( container.textContent ).toBe( '20' );
	} );

	it( 'formats every frame with the format callback', () => {
		motion( false );
		const { container } = render(
			<AnimatedNumber
				value={ 1500 }
				duration={ 100 }
				format={ ( n ) => `${ n } ms` }
			/>
		);
		expect( container.textContent ).toBe( '0 ms' );
		tick( 200 );
		expect( container.textContent ).toBe( '1500 ms' );
	} );

	it( 'leaves non-numeric values alone', () => {
		motion( false );
		const { container } = render( <AnimatedNumber value="31%" /> );
		expect( container.textContent ).toBe( '31%' );
		expect( frames ).toHaveLength( 0 );
	} );
} );

describe( 'sliding indicators', () => {
	const opts = [
		{ value: 'a', label: 'A' },
		{ value: 'b', label: 'B' },
	];
	let spies;
	beforeEach( () => {
		// jsdom lays nothing out; give every button a 40px box in a row.
		spies = [
			jest
				.spyOn( window.HTMLElement.prototype, 'offsetWidth', 'get' )
				.mockReturnValue( 40 ),
			jest
				.spyOn( window.HTMLElement.prototype, 'offsetLeft', 'get' )
				.mockImplementation( function () {
					return this.parentNode
						? Array.from( this.parentNode.children )
								.filter( ( c ) => 'BUTTON' === c.tagName )
								.indexOf( this ) * 50
						: 0;
				} ),
		];
	} );
	afterEach( () => spies.forEach( ( s ) => s.mockRestore() ) );

	it( 'Segmented moves one thumb under the selected option', () => {
		const onChange = jest.fn();
		const { container, rerender } = render(
			<Segmented
				label="Range"
				options={ opts }
				value="a"
				onChange={ onChange }
			/>
		);
		const group = container.querySelector( '.eui-seg' );
		const thumb = container.querySelector( '.eui-seg__thumb' );
		expect( group.classList.contains( 'has-thumb' ) ).toBe( true );
		expect( thumb.getAttribute( 'aria-hidden' ) ).toBe( 'true' );
		expect( thumb.style.transform ).toBe( 'translateX(0px)' );
		expect( thumb.style.width ).toBe( '40px' );
		rerender(
			<Segmented
				label="Range"
				options={ opts }
				value="b"
				onChange={ onChange }
			/>
		);
		expect( container.querySelector( '.eui-seg__thumb' ) ).toBe( thumb );
		expect( thumb.style.transform ).toBe( 'translateX(50px)' );
		fireEvent.click( screen.getByRole( 'radio', { name: 'A' } ) );
		expect( onChange ).toHaveBeenCalledWith( 'a' );
	} );

	it( 'Tabs moves one ink bar under the selected tab', () => {
		const { container, rerender } = render(
			<Tabs
				label="T"
				idPrefix="t"
				options={ opts }
				value="a"
				onChange={ () => {} }
			/>
		);
		const ink = container.querySelector( '.eui-tabs__ink' );
		expect(
			container
				.querySelector( '.eui-tabs' )
				.classList.contains( 'has-ink' )
		).toBe( true );
		rerender(
			<Tabs
				label="T"
				idPrefix="t"
				options={ opts }
				value="b"
				onChange={ () => {} }
			/>
		);
		expect( ink.style.transform ).toBe( 'translateX(50px)' );
	} );

	it( 'keeps the static active style when nothing can be measured', () => {
		spies[ 0 ].mockReturnValue( 0 );
		const { container } = render(
			<Segmented
				label="Range"
				options={ opts }
				value="a"
				onChange={ () => {} }
			/>
		);
		expect(
			container
				.querySelector( '.eui-seg' )
				.classList.contains( 'has-thumb' )
		).toBe( false );
		expect( container.querySelector( '.eui-seg__thumb' ) ).toBeNull();
	} );
} );

describe( 'charts', () => {
	const series = [ { key: 'k', label: 'Kept', colorVar: '--emcp-primary' } ];
	const data = ( n ) =>
		[ 1, 2, 3 ].map( ( v, i ) => ( {
			label: `d${ i }`,
			values: { k: v * n },
		} ) );

	it( 'staggers the bars and replays the grow when the data changes', () => {
		const { container, rerender } = render(
			<BarChart label="C" data={ data( 1 ) } series={ series } />
		);
		const svg = container.querySelector( 'svg' );
		const cols = container.querySelectorAll( '.eui-chart__col' );
		expect( cols ).toHaveLength( 3 );
		expect( cols[ 2 ].style.getPropertyValue( '--i' ) ).toBe( '2' );
		rerender( <BarChart label="C" data={ data( 1 ) } series={ series } /> );
		expect( container.querySelector( 'svg' ) ).toBe( svg );
		rerender( <BarChart label="C" data={ data( 2 ) } series={ series } /> );
		expect( container.querySelector( 'svg' ) ).not.toBe( svg );
	} );

	it( 'staggers the horizontal bars', () => {
		const { container } = render(
			<HBarList
				label="H"
				items={ [
					{ label: 'a', value: 2 },
					{ label: 'b', value: 1 },
				] }
			/>
		);
		const fills = container.querySelectorAll( '.eui-hbars__fill' );
		expect( fills[ 1 ].style.getPropertyValue( '--i' ) ).toBe( '1' );
	} );
} );

describe( 'motion styles', () => {
	const tokens = css( '../styles/tokens.css' );
	const base = css( '../styles/base.css' );
	it( 'defines shared easing and durations', () => {
		expect( tokens ).toMatch( /--emcp-ease:/ );
		expect( tokens ).toMatch( /--emcp-dur-fast:/ );
		expect( tokens ).toMatch( /--emcp-dur:/ );
		expect( tokens ).toMatch( /--emcp-dur-slow:/ );
	} );
	it( 'keeps the reduced-motion kill switch', () => {
		expect( base ).toMatch(
			/prefers-reduced-motion: reduce[\s\S]*animation-duration: 0\.01ms !important[\s\S]*transition-duration: 0\.01ms !important/
		);
	} );
	it.each( [
		[ 'Layout.css', /\.eui-card\s*\{[^}]*animation:\s*eui-rise/ ],
		[ 'Choice.css', /\.eui-seg__thumb\s*\{[^}]*transition:[^}]*transform/ ],
		[ 'Choice.css', /\.eui-tabs__ink\s*\{[^}]*transition:[^}]*transform/ ],
		[ 'Charts.css', /\.eui-chart__col\s*\{[^}]*animation:\s*eui-bar-grow/ ],
		[ 'Charts.css', /\.eui-hbars__fill\s*\{[^}]*animation:\s*eui-grow-x/ ],
		[ 'Charts.css', /\.eui-meter__fill\s*\{[^}]*transition:[^}]*width/ ],
		[ 'Dialog.css', /\.eui-overlay\s*\{[^}]*animation:\s*eui-fade/ ],
		[ 'Dialog.css', /\.eui-drawer\s*\{[^}]*animation:\s*eui-slide-in/ ],
		[ 'Dialog.css', /\.eui-dialog\s*\{[^}]*animation:\s*eui-pop-in/ ],
		[ 'Popover.css', /\.eui-pop\s*\{[^}]*animation:\s*eui-pop-in/ ],
		[ 'Toast.css', /\.eui-toast\s*\{[^}]*animation:\s*eui-toast-in/ ],
		[ 'Table.css', /\.eui-table tbody tr\s*\{[^}]*animation:\s*eui-fade/ ],
		[ 'Button.css', /\.eui-btn:active[^{]*\{[^}]*transform/ ],
	] )( '%s animates (%s)', ( file, rule ) => {
		expect( css( file ) ).toMatch( rule );
	} );
	it( 'defines every keyframe it uses once, in base.css', () => {
		[
			'eui-rise',
			'eui-fade',
			'eui-pop-in',
			'eui-slide-in',
			'eui-toast-in',
			'eui-bar-grow',
			'eui-grow-x',
		].forEach( ( k ) =>
			expect( base ).toMatch( new RegExp( `@keyframes ${ k }\\b` ) )
		);
	} );
} );
