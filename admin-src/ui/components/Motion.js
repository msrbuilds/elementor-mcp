import {
	useEffect,
	useLayoutEffect,
	useRef,
	useState,
} from '@wordpress/element';

/**
 * True when motion should be skipped: the visitor asks for reduced motion,
 * or the browser cannot say (no matchMedia, as in jsdom).
 */
export function prefersReducedMotion() {
	if ( 'undefined' === typeof window || ! window.matchMedia ) {
		return true;
	}
	return window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
}

const easeOut = ( t ) => 1 - Math.pow( 1 - t, 3 );

/**
 * A number that counts to its value: from zero on mount, from the previous
 * value on change. Non-numeric values and reduced motion render as given.
 *
 * @param {Object}        props
 * @param {number|string} props.value       What to show.
 * @param {number}        [props.duration]  Milliseconds.
 * @param {Function}      [props.format]    ( number ) => display text.
 * @param {string}        [props.className]
 */
export function AnimatedNumber( { value, duration = 700, format, className } ) {
	const numeric = 'number' === typeof value && Number.isFinite( value );
	const animate = numeric && ! prefersReducedMotion();
	const [ shown, setShown ] = useState( animate ? 0 : value );
	const from = useRef( animate ? 0 : value );

	useEffect( () => {
		if ( ! numeric || prefersReducedMotion() ) {
			from.current = value;
			setShown( value );
			return undefined;
		}
		const start = 'number' === typeof from.current ? from.current : 0;
		if ( start === value ) {
			setShown( value );
			return undefined;
		}
		const t0 = window.performance.now();
		let raf = 0;
		const step = ( now ) => {
			const p = Math.min( 1, ( now - t0 ) / duration );
			const v = p < 1 ? start + ( value - start ) * easeOut( p ) : value;
			from.current = v;
			setShown( v );
			if ( p < 1 ) {
				raf = window.requestAnimationFrame( step );
			}
		};
		raf = window.requestAnimationFrame( step );
		return () => window.cancelAnimationFrame( raf );
	}, [ value, numeric, duration ] );

	const decimals =
		numeric && ! Number.isInteger( value )
			? ( String( value ).split( '.' )[ 1 ] || '' ).length
			: 0;
	let text = shown;
	if ( 'number' === typeof shown ) {
		const n = Number( shown.toFixed( decimals ) );
		text = format ? format( n ) : n.toFixed( decimals );
	}
	return <span className={ className }>{ text }</span>;
}

/**
 * Measures the selected item of a group so one indicator can slide under it
 * (Segmented's thumb, Tabs' ink bar). Returns null until something has a
 * width, and the group keeps its static active style meanwhile.
 *
 * @param {Object} refs     Ref holding the item elements by index.
 * @param {number} selected Selected index, or -1.
 * @param {Array}  deps     Anything that changes item widths.
 * @return {?{x: number, w: number}} Offset and width in px.
 */
export function useIndicator( refs, selected, deps ) {
	const [ box, setBox ] = useState( null );
	useLayoutEffect( () => {
		const measure = () => {
			const el = selected >= 0 ? refs.current[ selected ] : null;
			const w = el ? el.offsetWidth : 0;
			setBox( ( old ) => {
				if ( ! w ) {
					return null;
				}
				// The indicator sits at the inline start; in RTL that is the
				// right edge, so it moves left by the distance from there.
				const rtl =
					'rtl' ===
					window.getComputedStyle( el.parentNode ).direction;
				const x = rtl
					? -( el.parentNode.clientWidth - el.offsetLeft - w )
					: el.offsetLeft;
				return old && old.x === x && old.w === w ? old : { x, w };
			} );
		};
		measure();
		const el = refs.current[ selected ];
		const parent = el?.parentNode;
		if ( ! parent || 'undefined' === typeof window.ResizeObserver ) {
			return undefined;
		}
		// Web fonts and responsive layouts change item widths after mount.
		const ro = new window.ResizeObserver( measure );
		ro.observe( parent );
		return () => ro.disconnect();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ selected, ...deps ] );
	return box;
}
