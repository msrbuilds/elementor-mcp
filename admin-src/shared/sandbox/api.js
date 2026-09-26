export const API = '/emcp-tools/v1/admin/sandbox';

/**
 * List path with only the parameters that differ from the defaults: a REST
 * boolean or integer argument refuses an empty value.
 *
 * @param {string} type         widgets | blocks | snippets.
 * @param {Object} query        { status, search, page }.
 * @param {string} query.status Filter.
 * @param {string} query.search Search text.
 * @param {number} query.page   Page number.
 * @return {string} REST path.
 */
export function listPath( type, { status, search, page } ) {
	const p = new URLSearchParams();
	if ( status && 'all' !== status ) {
		p.set( 'status', status );
	}
	if ( search ) {
		p.set( 'search', search );
	}
	if ( Number( page ) > 1 ) {
		p.set( 'page', String( page ) );
	}
	const qs = p.toString();
	return `${ API }/${ type }${ qs ? `?${ qs }` : '' }`;
}

/**
 * Save an exported bundle as a JSON file.
 *
 * @param {Object} res          The export endpoint's answer.
 * @param {string} res.filename File name.
 * @param {Object} res.bundle   Bundle.
 */
export function downloadBundle( res ) {
	const blob = new window.Blob( [ JSON.stringify( res.bundle, null, 2 ) ], {
		type: 'application/json',
	} );
	const url = window.URL.createObjectURL( blob );
	const a = document.createElement( 'a' );
	a.href = url;
	a.download = res.filename;
	document.body.appendChild( a );
	a.click();
	a.remove();
	window.URL.revokeObjectURL( url );
}
