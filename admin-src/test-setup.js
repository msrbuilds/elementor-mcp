import '@testing-library/jest-dom';
import { toHaveNoViolations } from 'jest-axe';

expect.extend( toHaveNoViolations );

// Screen tests assert final values, so they run as a visitor who asks for
// reduced motion (numbers do not count, bars do not grow). Motion.test.js
// installs its own matchMedia to test the animated path.
beforeEach( () => {
	window.matchMedia = ( query ) => ( {
		matches: query.includes( 'prefers-reduced-motion: reduce' ),
		media: query,
		onchange: null,
		addListener() {},
		removeListener() {},
		addEventListener() {},
		removeEventListener() {},
		dispatchEvent: () => false,
	} );
} );
