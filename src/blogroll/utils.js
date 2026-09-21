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
 * A bare domain is fine; it becomes an https address.
 *
 * @param {string} input What was typed.
 * @return {string} An address with a scheme.
 */
export function toUrl( input ) {
	const value = input.trim();
	return /^[a-z][a-z0-9+.-]*:/i.test( value ) ? value : `https://${ value }`;
}
