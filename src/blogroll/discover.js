/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { mergeDiscovered } from './utils';

/**
 * Ask the site behind an address for its name, description, feed and image.
 *
 * @param {string}      url    Address of the site.
 * @param {AbortSignal} signal Aborts the request, when the asking side is gone.
 * @return {Promise<Object>} What was found.
 */
export function discover( url, signal ) {
	return apiFetch( {
		path: '/blockroll/v1/discover',
		method: 'POST',
		data: { url },
		signal,
	} );
}

/**
 * The attributes of a link for an address: what the site says about itself.
 *
 * @param {string}      url    Address of the site.
 * @param {AbortSignal} signal Aborts the request.
 * @return {Promise<Object>} Link attributes.
 */
export function lookUp( url, signal ) {
	return discover( url, signal ).then( ( found ) =>
		mergeDiscovered( { url }, found )
	);
}

/**
 * Whether a request failed because it was aborted, not because of the site.
 *
 * @param {Error} error The error.
 * @return {boolean} True when aborted.
 */
export function isAborted( error ) {
	return 'AbortError' === error?.name;
}
