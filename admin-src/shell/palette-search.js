import Fuse from 'fuse.js';

/**
 * Flatten the shell data into palette items (spec 7), plus the library
 * (prompt and template titles) once it has been fetched.
 *
 * @param {Object} data    window.emcpShell.
 * @param {Array}  library Items from GET admin/palette/library.
 * @return {Array<{kind: string, label: string, hint: string, url: string}>} Items.
 */
export function buildIndex( data, library = [] ) {
	const toolsUrl = ( data.nav || [] ).find( ( n ) => 'tools' === n.id )?.url;
	return [
		...( data.nav || [] ).map( ( n ) => ( {
			kind: 'screen',
			label: n.label,
			hint: n.group,
			url: n.url,
		} ) ),
		...( toolsUrl
			? ( data.tools || [] ).map( ( t ) => ( {
					kind: 'tool',
					label: t.name,
					hint: t.slug,
					url: `${ toolsUrl }&q=${ encodeURIComponent( t.slug ) }`,
				} ) )
			: [] ),
		...( data.settings || [] ).map( ( s ) => ( {
			kind: 'setting',
			label: s.label,
			hint: '',
			url: s.url,
		} ) ),
		...library,
	];
}

// Added to a result's fuzzy score (0 = perfect) so that, for comparable
// matches, a screen outranks a setting and a setting outranks a tool: someone
// typing "redirect" is usually looking for the Redirects screen.
const KIND_PENALTY = {
	screen: 0,
	setting: 0.1,
	tool: 0.2,
	prompt: 0.3,
	template: 0.3,
};

/**
 * Fuzzy search; an empty query lists the screens.
 *
 * @param {Array}  index From buildIndex().
 * @param {string} query User input.
 * @param {number} limit Maximum results.
 * @return {Array} Matching items.
 */
export function searchPalette( index, query, limit = 12 ) {
	const q = ( query || '' ).trim();
	if ( ! q ) {
		return index.filter( ( i ) => 'screen' === i.kind ).slice( 0, limit );
	}
	const fuse = new Fuse( index, {
		keys: [
			{ name: 'label', weight: 0.7 },
			{ name: 'hint', weight: 0.3 },
		],
		threshold: 0.4,
		ignoreLocation: true,
		includeScore: true,
	} );
	return fuse
		.search( q )
		.map( ( r ) => ( {
			item: r.item,
			rank: r.score + ( KIND_PENALTY[ r.item.kind ] ?? 0.2 ),
		} ) )
		.sort( ( a, b ) => a.rank - b.rank )
		.slice( 0, limit )
		.map( ( r ) => r.item );
}
