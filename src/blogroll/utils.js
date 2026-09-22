/**
 * Internal dependencies
 */
import metadata from '../link/block.json';

/**
 * The block that holds one link of a blogroll.
 */
export const LINK_BLOCK = metadata.name;

/**
 * Whether a blogroll holds its links as blocks already. Only link blocks
 * count; anything else a hand-edited post has in there is ignored, by the
 * editor and by the server alike.
 *
 * @param {Object[]} innerBlocks Inner blocks of the blogroll.
 * @return {boolean} True when there is a link block.
 */
export function hasLinkBlocks( innerBlocks ) {
	return ( innerBlocks || [] ).some( ( block ) => LINK_BLOCK === block.name );
}

/**
 * Whether a blogroll still has its links in the `links` attribute and
 * none as blocks: the shape before 2.0, which the editor migrates.
 *
 * @param {Object}   attributes  Block attributes.
 * @param {Object[]} innerBlocks Inner blocks of the blogroll.
 * @return {boolean} True for a list to migrate.
 */
export function isLegacyList( attributes, innerBlocks ) {
	return hasLegacyLinks( attributes ) && ! hasLinkBlocks( innerBlocks );
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
 * Whether an address is one a browser may follow: the web ones. Anything
 * else, a javascript: address above all, is never linked or stored.
 *
 * @param {string} url An address.
 * @return {boolean} True for http and https.
 */
export function isSafeUrl( url ) {
	try {
		const { protocol, hostname } = new URL( String( url || '' ).trim() );
		return /^https?:$/i.test( protocol ) && '' !== hostname;
	} catch {
		return false;
	}
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
