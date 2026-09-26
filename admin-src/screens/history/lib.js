import { diffLines } from 'diff';
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Line diff with context: changed lines plus `context` unchanged lines around
 * each change; longer unchanged runs become one `skip` entry.
 *
 * @param {string} before  Saved text.
 * @param {string} after   Current text.
 * @param {number} context Unchanged lines kept around a change.
 * @return {Array} Lines: { type: add|del|same|skip, text, count? }.
 */
export function lineDiff( before, after, context = 3 ) {
	const lines = [];
	for ( const part of diffLines( before || '', after || '' ) ) {
		let type = 'same';
		if ( part.added ) {
			type = 'add';
		} else if ( part.removed ) {
			type = 'del';
		}
		const text = part.value.endsWith( '\n' )
			? part.value.slice( 0, -1 )
			: part.value;
		for ( const line of text.split( '\n' ) ) {
			lines.push( { type, text: line } );
		}
	}
	const keep = lines.map( () => false );
	lines.forEach( ( l, i ) => {
		if ( 'same' !== l.type ) {
			const end = Math.min( lines.length - 1, i + context );
			for ( let j = Math.max( 0, i - context ); j <= end; j++ ) {
				keep[ j ] = true;
			}
		}
	} );
	const out = [];
	let skipped = 0;
	lines.forEach( ( l, i ) => {
		if ( ! keep[ i ] ) {
			skipped++;
			return;
		}
		if ( skipped ) {
			out.push( { type: 'skip', count: skipped, text: '' } );
			skipped = 0;
		}
		out.push( l );
	} );
	if ( skipped ) {
		out.push( { type: 'skip', count: skipped, text: '' } );
	}
	return out;
}

const DEFAULTS = { kind: 'all', range: 'all' };

function entries( values ) {
	return Object.entries( values ).filter(
		( [ k, v ] ) =>
			'' !== v && null !== v && undefined !== v && DEFAULTS[ k ] !== v
	);
}

/**
 * @param {Object} filters { search, kind, client, range }.
 * @param {Object} extra   Extra parameters (before, limit).
 * @return {string} Query string without empty or default values.
 */
export function toQuery( filters, extra = {} ) {
	const params = new URLSearchParams();
	for ( const [ k, v ] of entries( { ...filters, ...extra } ) ) {
		params.set( k, String( v ) );
	}
	return params.toString();
}

/**
 * Body for a write: the filters plus how many sessions are shown.
 *
 * @param {Object} filters Filters.
 * @param {number} shown   Sessions currently shown.
 * @return {Object} Body.
 */
export function writeBody( filters, shown ) {
	return {
		...Object.fromEntries( entries( filters ) ),
		limit: Math.min( 100, Math.max( 20, shown ) ),
	};
}

/**
 * @param {Object} result { undone, remaining, total, stoppedTitle, reason }.
 * @return {Object} { tone, text }.
 */
export function sessionResultMessage( result ) {
	const done = result.undone.length;
	if ( ! result.remaining ) {
		return {
			tone: 'success',
			text: sprintf(
				/* translators: %d: number of changes undone. */
				_n(
					'Undid %d change.',
					'Undid %d changes.',
					done,
					'emcp-tools'
				),
				done
			),
		};
	}
	return {
		tone: 'error',
		text: sprintf(
			/* translators: 1: changes undone, 2: changes in the session, 3: title of the change it stopped at, 4: why it stopped. */
			__(
				"Undid %1$d of %2$d changes; stopped at '%3$s': %4$s",
				'emcp-tools'
			),
			done,
			result.total,
			result.stoppedTitle,
			result.reason
		),
	};
}

/**
 * @param {number} ts Unix seconds.
 * @return {string} Locale date and time.
 */
export function formatTime( ts ) {
	return new Intl.DateTimeFormat( undefined, {
		dateStyle: 'medium',
		timeStyle: 'short',
	} ).format( new Date( ts * 1000 ) );
}

/**
 * @param {number} ts  Unix seconds.
 * @param {number} now Milliseconds.
 * @return {string} Relative time in the browser's locale.
 */
export function relativeTime( ts, now = Date.now() ) {
	const diff = Math.round( ts - now / 1000 );
	const rtf = new Intl.RelativeTimeFormat( undefined, { numeric: 'auto' } );
	const abs = Math.abs( diff );
	if ( abs < 3600 ) {
		return rtf.format( Math.round( diff / 60 ), 'minute' );
	}
	if ( abs < 86400 ) {
		return rtf.format( Math.round( diff / 3600 ), 'hour' );
	}
	return rtf.format( Math.round( diff / 86400 ), 'day' );
}
