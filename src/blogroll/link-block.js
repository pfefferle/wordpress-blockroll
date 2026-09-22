/**
 * WordPress dependencies
 */
import { createBlock } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import { LINK_BLOCK } from './utils';

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
