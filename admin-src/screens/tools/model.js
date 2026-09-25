/**
 * Pure helpers for the Tools screen (spec 8.3). Form values are flat so
 * useSettingsForm can diff them: `tool:<slug>` booleans plus `dispatcher`
 * and `themerPhp`.
 */
const PREFIX = 'tool:';

export const key = ( slug ) => PREFIX + slug;

export function valuesFromPayload( data ) {
	const values = {
		dispatcher: !! data.dispatcher,
		themerPhp: data.themerPhp ?? null,
	};
	Object.entries( data.enabled || {} ).forEach( ( [ slug, on ] ) => {
		values[ key( slug ) ] = !! on;
	} );
	return values;
}

export function payloadFromDiff( diff ) {
	const out = { enable: [], disable: [] };
	Object.entries( diff ).forEach( ( [ k, v ] ) => {
		if ( k.startsWith( PREFIX ) ) {
			( v ? out.enable : out.disable ).push( k.slice( PREFIX.length ) );
		} else if ( 'dispatcher' === k ) {
			out.dispatcher_mode = !! v;
		} else if ( 'themerPhp' === k && null !== v ) {
			out.themer_php = !! v;
		}
	} );
	return out;
}

function matches( tool, { search, risk, status }, values ) {
	const q = ( search || '' ).trim().toLowerCase();
	if (
		q &&
		! `${ tool.name } ${ tool.slug } ${ tool.description }`
			.toLowerCase()
			.includes( q )
	) {
		return false;
	}
	if ( risk && 'all' !== risk && tool.risk !== risk ) {
		return false;
	}
	const on = !! values[ key( tool.slug ) ];
	if ( 'enabled' === status ) {
		return tool.available && on;
	}
	if ( 'disabled' === status ) {
		return tool.available && ! on;
	}
	if ( 'unavailable' === status ) {
		return ! tool.available;
	}
	return true;
}

export function filterCategories( categories, filters, values ) {
	return categories
		.filter( ( c ) => c.platform === filters.tab )
		.map( ( c ) => ( {
			...c,
			tools: c.tools.filter( ( t ) => matches( t, filters, values ) ),
		} ) )
		.filter( ( c ) => c.tools.length > 0 );
}

export function groupCounts( category, values ) {
	return {
		on: category.tools.filter(
			( t ) => t.available && values[ key( t.slug ) ]
		).length,
		total: category.tools.length,
	};
}

export function tabCounts( categories, values ) {
	const out = {};
	categories.forEach( ( c ) => {
		const counts = groupCounts( c, values );
		const tab = out[ c.platform ] || { on: 0, total: 0 };
		out[ c.platform ] = {
			on: tab.on + counts.on,
			total: tab.total + counts.total,
		};
	} );
	return out;
}

export function setMany( values, tools, enabled ) {
	const next = { ...values };
	tools.forEach( ( t ) => {
		if ( t.available ) {
			next[ key( t.slug ) ] = enabled;
		}
	} );
	return next;
}

export function resetToDefaults( categories, defaults, values ) {
	const next = { ...values };
	categories.forEach( ( c ) =>
		c.tools.forEach( ( t ) => {
			if ( t.available ) {
				next[ key( t.slug ) ] = ! defaults.includes( t.slug );
			}
		} )
	);
	return next;
}

export function countTurnedOff( before, after ) {
	return Object.keys( after ).filter(
		( k ) => k.startsWith( PREFIX ) && before[ k ] && ! after[ k ]
	).length;
}
