import {
	useCallback,
	useEffect,
	useId,
	useLayoutEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { IconButton, prefersReducedMotion } from '@emcp/ui';
import { useWheelScroll } from './useWheelScroll';

/**
 * A single row of chips that scrolls sideways with previous and next
 * buttons instead of a scrollbar. The buttons appear only while the chips
 * overflow and are disabled at each end; swipe, trackpad and the mouse
 * wheel scroll the row too.
 *
 * @param {Object}  props
 * @param {string}  props.label    The group's accessible name.
 * @param {Element} props.children The chips.
 */
export function ChipCarousel( { label, children } ) {
	const row = useRef();
	const id = useId();
	const [ edges, setEdges ] = useState( {
		overflow: false,
		start: true,
		end: true,
	} );
	useWheelScroll( row );

	const measure = useCallback( () => {
		const el = row.current;
		if ( ! el ) {
			return;
		}
		const max = el.scrollWidth - el.clientWidth;
		const next = {
			overflow: max > 1,
			start: el.scrollLeft <= 1,
			end: el.scrollLeft >= max - 1,
		};
		setEdges( ( old ) =>
			old.overflow === next.overflow &&
			old.start === next.start &&
			old.end === next.end
				? old
				: next
		);
	}, [] );

	useLayoutEffect( measure, [ measure, children ] );
	useEffect( () => {
		const el = row.current;
		if ( ! el ) {
			return undefined;
		}
		el.addEventListener( 'scroll', measure, { passive: true } );
		const ro =
			'undefined' === typeof window.ResizeObserver
				? null
				: new window.ResizeObserver( measure );
		ro?.observe( el );
		return () => {
			el.removeEventListener( 'scroll', measure );
			ro?.disconnect();
		};
	}, [ measure ] );

	// A page is 80% of the visible width, so the chip at the edge stays in view.
	const page = ( dir ) => {
		const el = row.current;
		el.scrollBy( {
			left: dir * Math.round( el.clientWidth * 0.8 ),
			behavior: prefersReducedMotion() ? 'auto' : 'smooth',
		} );
	};

	return (
		<div className="eui-chips-carousel">
			{ edges.overflow && (
				<IconButton
					icon="chevron-left"
					className="eui-chips-carousel__arrow"
					label={ sprintf(
						/* translators: %s: the row's name, such as Categories. */
						__( 'Scroll %s back', 'emcp-tools' ),
						label
					) }
					aria-controls={ id }
					disabled={ edges.start }
					onClick={ () => page( -1 ) }
				/>
			) }
			<div
				ref={ row }
				id={ id }
				className="eui-prompts__chips"
				role="group"
				aria-label={ label }
			>
				{ children }
			</div>
			{ edges.overflow && (
				<IconButton
					icon="chevron-right"
					className="eui-chips-carousel__arrow"
					label={ sprintf(
						/* translators: %s: the row's name, such as Categories. */
						__( 'Scroll %s forward', 'emcp-tools' ),
						label
					) }
					aria-controls={ id }
					disabled={ edges.end }
					onClick={ () => page( 1 ) }
				/>
			) }
		</div>
	);
}
