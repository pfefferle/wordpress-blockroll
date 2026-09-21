/**
 * WordPress dependencies
 */
import { createBlock } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import metadata from '../link/block.json';

/**
 * The block that holds one link of a blogroll.
 */
export const LINK_BLOCK = metadata.name;

/**
 * A link block for a link, the way an import, a lookup or the old
 * attribute delivers it. createBlock keeps the declared attributes only
 * and fills the missing ones.
 *
 * @param {Object} link Raw link.
 * @return {Object} Link block.
 */
export function createLinkBlock( link ) {
	return createBlock( LINK_BLOCK, link );
}
