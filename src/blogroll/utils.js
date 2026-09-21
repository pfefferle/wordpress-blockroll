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
 * @return {Array} The attributes without links, and the links with an address.
 */
export function migrateLinks( attributes ) {
	return [
		{ ...attributes, links: [] },
		( attributes.links || [] ).filter( ( link ) => link?.url ),
	];
}

/**
 * Today, as the date a link was added.
 *
 * @return {string} YYYY-MM-DD.
 */
export function today() {
	return new Date().toISOString().slice( 0, 10 );
}

/**
 * Whether two addresses point at the same site: scheme, "www.", case
 * and a trailing slash do not make a difference.
 *
 * @param {string} a One address.
 * @param {string} b Another address.
 * @return {boolean} True for the same site.
 */
export function sameSite( a, b ) {
	const key = ( url ) =>
		String( url || '' )
			.trim()
			.toLowerCase()
			.replace( /^[a-z][a-z0-9+.-]*:\/\//, '' )
			.replace( /^www\./, '' )
			.replace( /\/+$/, '' );
	return key( a ) !== '' && key( a ) === key( b );
}
