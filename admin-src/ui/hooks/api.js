import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

/**
 * REST request through WordPress's api-fetch. Core configures its root URL and
 * nonce middleware whenever the wp-api-fetch script is enqueued in wp-admin,
 * so nothing is configured here.
 *
 * @param {string} path    REST path, for example '/emcp-tools/v1/admin/tools'.
 * @param {Object} options api-fetch options (method, data, ...).
 * @return {Promise} The response.
 */
export function request( path, options = {} ) {
	return apiFetch( { path, ...options } );
}

/**
 * A message to show the user for a failed request.
 *
 * @param {Object} err api-fetch error ({ code, message }).
 * @return {string} Message.
 */
export function errorMessage( err ) {
	if ( err && 'rest_cookie_invalid_nonce' === err.code ) {
		return __( 'Your session expired. Reload the page and try again.', 'emcp-tools' );
	}
	return ( err && err.message ) || __( 'Something went wrong. Please try again.', 'emcp-tools' );
}
