/**
 * The announcement bar: server-rendered slides, rotated here. It cannot be
 * dismissed. Auto-rotation pauses while the bar is hovered or focused and
 * never runs for reduced motion; the arrows and dots always work.
 *
 * @param {Element} root                  [data-emcp-promo].
 * @param {Object}  options
 * @param {number}  options.interval      Milliseconds per slide.
 * @param {boolean} options.reducedMotion Skip auto-rotation.
 * @return {{go: (n: number) => void, stop: () => void}} Controller.
 */
export function initPromo( root, { interval = 7000, reducedMotion = false } ) {
	const slides = Array.from(
		root.querySelectorAll( '.eui-frame-promo__slide' )
	);
	const dots = Array.from( root.querySelectorAll( '[data-emcp-promo-dot]' ) );
	let index = Math.max(
		0,
		slides.findIndex( ( s ) => ! s.hidden )
	);
	let timer = null;
	let paused = false;

	const go = ( n ) => {
		if ( ! slides.length ) {
			return;
		}
		index = ( n + slides.length ) % slides.length;
		// Each announcement has its own colour; the bar takes the active one's.
		const tone = slides[ index ].getAttribute( 'data-emcp-promo-tone' );
		if ( tone ) {
			root.dataset.tone = tone;
		}
		slides.forEach( ( s, i ) => {
			s.hidden = i !== index;
			s.classList.toggle( 'is-active', i === index );
		} );
		dots.forEach( ( d, i ) => {
			if ( i === index ) {
				d.setAttribute( 'aria-current', 'true' );
			} else {
				d.removeAttribute( 'aria-current' );
			}
		} );
	};

	const stop = () => {
		if ( timer ) {
			window.clearInterval( timer );
			timer = null;
		}
	};

	const start = () => {
		stop();
		if ( reducedMotion || paused || slides.length < 2 ) {
			return;
		}
		timer = window.setInterval( () => go( index + 1 ), interval );
	};

	root.addEventListener( 'click', ( e ) => {
		const t = e.target instanceof window.Element ? e.target : null;
		if ( ! t ) {
			return;
		}
		if ( t.closest( '[data-emcp-promo-next]' ) ) {
			go( index + 1 );
		} else if ( t.closest( '[data-emcp-promo-prev]' ) ) {
			go( index - 1 );
		} else {
			const dot = t.closest( '[data-emcp-promo-dot]' );
			if ( ! dot ) {
				return;
			}
			go( parseInt( dot.getAttribute( 'data-emcp-promo-dot' ), 10 ) );
		}
		start();
	} );
	const pause = () => {
		paused = true;
		stop();
	};
	const resume = () => {
		paused = false;
		start();
	};
	root.addEventListener( 'mouseenter', pause );
	root.addEventListener( 'mouseleave', resume );
	root.addEventListener( 'focusin', pause );
	root.addEventListener( 'focusout', ( e ) => {
		if ( ! root.contains( e.relatedTarget ) ) {
			resume();
		}
	} );

	go( index );
	start();
	return { go, stop };
}
