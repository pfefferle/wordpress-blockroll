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

/**
 * The attributes of a link block, from a link the way the block stored
 * it in its own attributes before links became blocks, or the way an
 * import or a source delivers it. Only the known fields, filled in.
 *
 * @param {Object} link Raw link.
 * @return {Object} Link block attributes.
 */
export function linkBlockAttributes( link ) {
	return {
		url: link?.url || '',
		name: link?.name || '',
		description: link?.description || '',
		feedUrl: link?.feedUrl || '',
		photo: link?.photo || '',
		xfn: Array.isArray( link?.xfn ) ? link.xfn : [],
		added: link?.added || '',
	};
}

/**
 * Whether a blogroll block still has its links in the `links` attribute.
 *
 * @param {Object} attributes Block attributes.
 * @return {boolean} True for a block saved before links were blocks.
 */
export function hasLegacyLinks( attributes ) {
	return Array.isArray( attributes?.links ) && attributes.links.length > 0;
}

/**
 * Move the links of a block's `links` attribute out of it.
 *
 * @param {Object} attributes Block attributes.
 * @return {Array} The attributes without links, and the link block attributes.
 */
export function migrateLinks( attributes ) {
	return [
		{ ...attributes, links: [] },
		( attributes.links || [] )
			.filter( ( link ) => link?.url )
			.map( linkBlockAttributes ),
	];
}
