const pad = ( n ) => String( n ).padStart( 2, '0' );

const fromParts = ( d, zone ) => {
	const parts = new Intl.DateTimeFormat( 'en-CA', {
		timeZone: zone,
		year: 'numeric',
		month: '2-digit',
		day: '2-digit',
		hour: '2-digit',
		minute: '2-digit',
		second: '2-digit',
		hourCycle: 'h23',
	} ).formatToParts( d );
	const get = ( t ) => parts.find( ( p ) => p.type === t )?.value;
	return `${ get( 'year' ) }-${ get( 'month' ) }-${ get( 'day' ) } ${ get( 'hour' ) }:${ get( 'minute' ) }:${ get( 'second' ) }`;
};

const utc = ( d ) =>
	`${ d.getUTCFullYear() }-${ pad( d.getUTCMonth() + 1 ) }-${ pad( d.getUTCDate() ) } ${ pad( d.getUTCHours() ) }:${ pad( d.getUTCMinutes() ) }:${ pad( d.getUTCSeconds() ) }`;

/**
 * A log timestamp for display.
 *
 * @param {number} ts       Unix seconds.
 * @param {string} mode     site | utc | browser.
 * @param {string} siteZone wp_timezone_string(): an IANA zone or '+05:00'.
 * @return {string} YYYY-MM-DD HH:MM:SS.
 */
export function formatWhen( ts, mode, siteZone ) {
	const d = new Date( ts * 1000 );
	if ( 'utc' === mode ) {
		return utc( d );
	}
	if ( 'browser' === mode ) {
		return fromParts( d, undefined );
	}
	const offset = /^([+-])(\d{2}):(\d{2})$/.exec( siteZone || '' );
	if ( offset ) {
		const mins =
			( '-' === offset[ 1 ] ? -1 : 1 ) *
			( Number( offset[ 2 ] ) * 60 + Number( offset[ 3 ] ) );
		return utc( new Date( d.getTime() + mins * 60000 ) );
	}
	try {
		return fromParts( d, siteZone || 'UTC' );
	} catch {
		return utc( d );
	}
}
