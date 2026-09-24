/**
 * Dependency-extraction mapping for the shared admin UI library.
 *
 * Screen bundles import shared components as `@emcp/ui`. Mapping it to the
 * `emcpUI` browser global (and the `emcp-admin-ui` script handle) keeps a
 * single copy of the library on the page and makes the generated
 * `*.asset.php` list `emcp-admin-ui` as a dependency.
 */
function requestToExternal( request ) {
	if ( '@emcp/ui' === request ) {
		return 'emcpUI';
	}
	return undefined;
}

function requestToHandle( request ) {
	if ( '@emcp/ui' === request ) {
		return 'emcp-admin-ui';
	}
	return undefined;
}

module.exports = { requestToExternal, requestToHandle };
