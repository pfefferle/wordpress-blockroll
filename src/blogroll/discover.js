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
 * @param {string} url Address of the site.
 * @return {Promise<Object>} What was found.
 */
export function discover( url ) {
	return apiFetch( {
		path: '/blockroll/v1/discover',
		method: 'POST',
		data: { url },
	} );
}

/**
 * The attributes of a link for an address: what the site says about itself.
 *
 * @param {string} url Address of the site.
 * @return {Promise<Object>} Link attributes.
 */
export function lookUp( url ) {
	return discover( url ).then( ( found ) =>
		mergeDiscovered( { url }, found )
	);
}
