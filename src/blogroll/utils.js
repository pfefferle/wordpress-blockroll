/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Move an array element.
 *
 * @param {Array}  list Array.
 * @param {number} from Index to move.
 * @param {number} to   Target index.
 * @return {Array} New array.
 */
export function move( list, from, to ) {
	const next = [ ...list ];
	next.splice( to, 0, ...next.splice( from, 1 ) );
	return next;
}

/**
 * Fill empty link fields with discovered values; existing values win.
 *
 * @param {Object} link  The link being edited or imported.
 * @param {Object} found Discovered fields.
 * @return {Object} Merged link.
 */
export function mergeDiscovered( link, found ) {
	return {
		...link,
		name: link.name || found.name,
		description: link.description || found.description,
		feedUrl: link.feedUrl || found.feedUrl,
		photo: link.photo || found.photo,
	};
}

/**
 * Whether an image would be loaded from another site.
 *
 * An image that sits in the page, or one from the site itself, costs a
 * visitor nothing. Everything else hands their address to a stranger,
 * so the frontend does not print it.
 *
 * @param {string} photo Photo field of a link.
 * @return {boolean} True when the image still has to be copied into the page.
 */
export function needsEmbedding( photo ) {
	return (
		typeof photo === 'string' &&
		'' !== photo &&
		! photo.startsWith( 'data:' ) &&
		! photo.startsWith( window.location.origin )
	);
}

/**
 * Fetch the image of a link and get it back as a data URI.
 *
 * The known image address is tried first, that is one request. Icons do
 * move though, so the site itself is asked when that fails.
 *
 * @param {Object} link Link with a photo and a url.
 * @return {Promise<string>} The embedded image, or an empty string.
 */
export function fetchPhoto( link ) {
	const fromPhoto = link.photo
		? apiFetch( {
				path: '/blockroll/v1/icon',
				method: 'POST',
				data: { url: link.photo },
		  } ).then( ( result ) => result.photo )
		: Promise.reject( new Error( 'no image address' ) );

	return fromPhoto.catch( () => {
		if ( ! link.url ) {
			return '';
		}

		return apiFetch( {
			path: '/blockroll/v1/discover',
			method: 'POST',
			data: { url: link.url },
		} )
			.then( ( found ) => found.photo || '' )
			.catch( () => '' );
	} );
}
