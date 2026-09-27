import { formatWhen } from './time';

describe( 'formatWhen', () => {
	const ts = Date.UTC( 2026, 8, 27, 10, 5, 9 ) / 1000;
	it( 'formats UTC', () => {
		expect( formatWhen( ts, 'utc', 'Asia/Karachi' ) ).toBe(
			'2026-09-27 10:05:09'
		);
	} );
	it( 'formats an IANA site zone', () => {
		expect( formatWhen( ts, 'site', 'Asia/Karachi' ) ).toBe(
			'2026-09-27 15:05:09'
		);
	} );
	it( 'formats a fixed offset site zone', () => {
		expect( formatWhen( ts, 'site', '-03:30' ) ).toBe(
			'2026-09-27 06:35:09'
		);
		expect( formatWhen( ts, 'site', '+00:00' ) ).toBe(
			'2026-09-27 10:05:09'
		);
	} );
	it( 'falls back to UTC for an unknown zone', () => {
		expect( formatWhen( ts, 'site', 'Not/AZone' ) ).toBe(
			'2026-09-27 10:05:09'
		);
	} );
} );
