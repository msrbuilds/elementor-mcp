/**
 * Reads the customizable parts of a library prompt and writes a customized
 * copy. Every prompt in the library shares one shape: a "**Page builder:**"
 * first line, an H1 "# Business name — what it is", a palette table of
 * `| Role | `#HEX` (note) |` rows, a "**Typography:**" line naming the
 * Display and Body fonts in bold, and a "## Content facts" section of
 * "**Label:** value" lines. Anything missing simply offers no control.
 */

/** Targets the prompt can name on its first line. */
export const CUSTOM_BUILDERS = [
	'Elementor',
	'Gutenberg',
	'Bricks',
	'Beaver Builder',
	'Divi',
	'Breakdance',
	'Oxygen',
	'Avada',
	'WPBakery',
	'Thrive Architect',
	'Kirki',
	'Visual Composer',
	'BeBuilder',
	'Plain HTML/CSS',
];

const BUILDER_LINE = /^\*\*Page builder:\*\*[ \t]*(.*)$/m;
const NAME_LINE = /^# (.+?) — /m;
const COLOR_ROW =
	/^\|\s*([^|]+?)\s*\|\s*`(#[0-9A-Fa-f]{3,8})`(?:\s*\(([^)]*)\))?/gm;
const TYPE_LINE = /^\*\*Typography:\*\*.*$/m;
const FONT = /(\w+)\s*=\s*\*\*([^*]+)\*\*/g;
const HEX = /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i;

const escape = ( s ) => s.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );

/**
 * The "## Content facts" section: its bounds and "**Label:** value" lines.
 *
 * @param {string} text Prompt.
 * @return {?{start: number, end: number, body: string}} Section or null.
 */
function factsSection( text ) {
	const head = /^## Content facts[^\n]*\n/m.exec( text );
	if ( ! head ) {
		return null;
	}
	const start = head.index + head[ 0 ].length;
	const next = /^## /m.exec( text.slice( start ) );
	const end = next ? start + next.index : text.length;
	return { start, end, body: text.slice( start, end ) };
}

/**
 * The customizable parts of a prompt.
 *
 * @param {string} text Prompt.
 * @return {{builder: string, name: string, colors: Array, fonts: Array, facts: Array}} Parts.
 */
export function parsePrompt( text ) {
	const out = { builder: '', name: '', colors: [], fonts: [], facts: [] };
	const builder = BUILDER_LINE.exec( text );
	out.builder = builder ? builder[ 1 ].trim() : '';
	const name = NAME_LINE.exec( text );
	out.name = name ? name[ 1 ].trim() : '';

	const byHex = new Map();
	for ( const m of text.matchAll( COLOR_ROW ) ) {
		const key = m[ 2 ].toLowerCase();
		if ( ! byHex.has( key ) ) {
			byHex.set( key, {
				hex: m[ 2 ].toUpperCase(),
				roles: [],
				note: ( m[ 3 ] || '' ).trim(),
			} );
		}
		const entry = byHex.get( key );
		entry.roles.push( m[ 1 ].trim() );
		if ( ! entry.note && m[ 3 ] ) {
			entry.note = m[ 3 ].trim();
		}
	}
	out.colors = Array.from( byHex.values() );

	const type = TYPE_LINE.exec( text );
	if ( type ) {
		for ( const m of type[ 0 ].matchAll( FONT ) ) {
			out.fonts.push( { role: m[ 1 ], font: m[ 2 ].trim() } );
		}
	}

	const facts = factsSection( text );
	if ( facts ) {
		for ( const m of facts.body.matchAll(
			/^\*\*([^*]+?):\*\*[ \t]*(.*)$/gm
		) ) {
			out.facts.push( { label: m[ 1 ].trim(), value: m[ 2 ].trim() } );
		}
	}
	return out;
}

/**
 * The prompt with the user's choices written in. Unchanged, empty or
 * invalid values are skipped. Facts go first and the rename last, so a
 * rename also reaches an edited fact.
 *
 * @param {string} text   Prompt.
 * @param {Object} parsed parsePrompt( text ).
 * @param {Object} values { builder, name, colors: {hex: hex}, fonts: {role: font}, facts: {label: value} }.
 * @return {string} Customized prompt.
 */
export function applyCustomization( text, parsed, values ) {
	let out = text;

	const facts = values.facts || {};
	const section = factsSection( out );
	if ( section ) {
		let body = section.body;
		parsed.facts.forEach( ( f ) => {
			const next = ( facts[ f.label ] ?? '' ).trim();
			if ( ! next || next === f.value ) {
				return;
			}
			body = body.replace(
				new RegExp(
					'^(\\*\\*' + escape( f.label ) + ':\\*\\*[ \\t]*).*$',
					'm'
				),
				( m, lead ) => lead + next
			);
		} );
		out = out.slice( 0, section.start ) + body + out.slice( section.end );
	}

	const fonts = values.fonts || {};
	out = out.replace( TYPE_LINE, ( line ) =>
		line.replace( FONT, ( m, role, font ) => {
			const next = ( fonts[ role ] ?? '' ).trim();
			return next && next !== font.trim()
				? `${ role } = **${ next }**`
				: m;
		} )
	);

	const builder = ( values.builder ?? '' ).trim();
	if ( builder && builder !== parsed.builder ) {
		out = out.replace( BUILDER_LINE, () => '**Page builder:** ' + builder );
	}

	const colors = values.colors || {};
	parsed.colors.forEach( ( c ) => {
		const next = ( colors[ c.hex.toLowerCase() ] ?? '' ).trim();
		if (
			! HEX.test( next ) ||
			next.toLowerCase() === c.hex.toLowerCase()
		) {
			return;
		}
		out = out.replace(
			new RegExp( escape( c.hex ) + '(?![0-9a-f])', 'gi' ),
			() => next.toUpperCase()
		);
	} );

	const name = ( values.name ?? '' ).trim();
	if ( name && parsed.name && name !== parsed.name ) {
		out = out.split( parsed.name ).join( name );
	}
	return out;
}
