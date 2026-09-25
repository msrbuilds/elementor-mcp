/**
 * Scope legacy admin CSS under .emcp-legacy (spec 5.6). Legacy views render
 * inside the new frame until each screen is ported; scoping keeps their rules
 * from reaching the frame and the new screens.
 */
const PREFIX = '.emcp-legacy';
// Elements legacy scripts append straight to <body> (admin.js code viewer and
// Brand Kits toast); scoping their rules would leave them unstyled.
const PORTALS =
	/^\.(?:emcp-code-overlay|elementor-mcp-bk-toast)(?:__[\w-]*)?(?![\w-])/;
const ANCESTORS =
	/^(html|body|\.wp-core-ui|#wpwrap|#wpcontent|#wpbody|#wpbody-content)(?=$|[\s>+~.:#[])/;

/**
 * Split a selector list on top-level commas (not inside parentheses).
 *
 * @param {string} list Selector list.
 * @return {string[]} Selectors, untrimmed.
 */
function splitTopLevel( list ) {
	const out = [];
	let depth = 0;
	let start = 0;
	for ( let i = 0; i < list.length; i++ ) {
		const ch = list[ i ];
		if ( '(' === ch ) {
			depth++;
		} else if ( ')' === ch ) {
			depth--;
		} else if ( ',' === ch && 0 === depth ) {
			out.push( list.slice( start, i ) );
			start = i + 1;
		}
	}
	out.push( list.slice( start ) );
	return out;
}

/**
 * Index where the first compound selector ends.
 *
 * @param {string} sel Selector.
 * @return {number} Index of the first top-level whitespace or combinator.
 */
function firstCompoundEnd( sel ) {
	let depth = 0;
	for ( let i = 0; i < sel.length; i++ ) {
		const ch = sel[ i ];
		if ( '(' === ch || '[' === ch ) {
			depth++;
		} else if ( ')' === ch || ']' === ch ) {
			depth--;
		} else if ( 0 === depth && /[\s>+~]/.test( ch ) ) {
			return i;
		}
	}
	return sel.length;
}

function scopeOne( raw ) {
	const sel = raw.trim();
	if (
		'' === sel ||
		sel.startsWith( PREFIX ) ||
		sel.startsWith( ':root' ) ||
		PORTALS.test( sel )
	) {
		return sel;
	}
	if ( ANCESTORS.test( sel ) ) {
		const end = firstCompoundEnd( sel );
		const rest = sel.slice( end ).trim();
		if ( '' === rest ) {
			return sel;
		}
		return `${ sel.slice( 0, end ) } ${ PREFIX } ${ rest }`;
	}
	return `${ PREFIX } ${ sel }`;
}

/**
 * Scope every selector in a list.
 *
 * @param {string} selector Selector list.
 * @return {string} Scoped list.
 */
function scopeSelector( selector ) {
	return splitTopLevel( selector ).map( scopeOne ).join( ', ' );
}

module.exports = { scopeSelector, splitTopLevel, PREFIX };
