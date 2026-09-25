/**
 * Dependency-extraction mapping for the shared admin UI library.
 *
 * Screen bundles import shared components as `@emcp/ui`. Mapping it to the
 * `emcpUI` browser global (and the `emcp-admin-ui` script handle) keeps a
 * single copy of the library on the page and makes the generated
 * `*.asset.php` list `emcp-admin-ui` as a dependency.
 *
 * @param {string} request Module request being bundled.
 * @return {string|undefined} Global name, or undefined for the default mapping.
 */
function requestToExternal( request ) {
	if ( '@emcp/ui' === request ) {
		return 'emcpUI';
	}
	return undefined;
}

/**
 * @param {string} request Module request being bundled.
 * @return {string|undefined} Script handle, or undefined for the default mapping.
 */
function requestToHandle( request ) {
	if ( '@emcp/ui' === request ) {
		return 'emcp-admin-ui';
	}
	return undefined;
}

module.exports = { requestToExternal, requestToHandle };
