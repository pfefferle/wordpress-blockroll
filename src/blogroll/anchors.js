/**
 * WordPress dependencies
 */
import { cleanForSlug } from '@wordpress/url';

/**
 * Anchor of a block without a usable name. Same as Anchors::FALLBACK in PHP.
 */
export const FALLBACK = 'blogroll';

/**
 * The slug of a block name, or the fallback for an empty one.
 *
 * @param {string} name Block name.
 * @return {string} Slug.
 */
export function slugOf( name ) {
	return cleanForSlug( name || '' ) || FALLBACK;
}

/**
 * A slug that is not taken yet, appending a counter if it is.
 *
 * Same rule as Anchors::unique() in PHP, so the editor and the server
 * agree on the anchors of a page.
 *
 * @param {string}   slug  Wanted slug.
 * @param {string[]} taken Anchors already in use.
 * @return {string} Unique anchor.
 */
export function uniqueAnchor( slug, taken ) {
	let anchor = slug;
	let i = 0;
	while ( taken.includes( anchor ) ) {
		i += 1;
		anchor = `${ slug }-${ i }`;
	}
	return anchor;
}
