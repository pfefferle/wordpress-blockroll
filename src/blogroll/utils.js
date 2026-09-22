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
	// A colon followed by digits is a port, not a scheme: example.com:8080.
	const hasScheme =
		/^[a-z][a-z0-9+.-]*:/i.test( value ) && ! /^[^:/?#]+:\d/.test( value );
	return hasScheme ? value : `https://${ value }`;
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
 * What makes an address the address of a site: scheme, "www.", case and
 * a trailing slash do not.
 *
 * @param {string} url An address.
 * @return {string} The key, empty for no address.
 */
export function siteKey( url ) {
	return String( url || '' )
		.trim()
		.toLowerCase()
		.replace( /^[a-z][a-z0-9+.-]*:\/\//, '' )
		.replace( /^www\./, '' )
		.replace( /\/+$/, '' );
}
