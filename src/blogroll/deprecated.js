/**
 * Internal dependencies
 */
import metadata from './block.json';
import { createLinkBlock, LINK_BLOCK } from './link-block';
import { hasLegacyLinks, migrateLinks } from './utils';

/**
 * Before 2.0 the links were an attribute of the blogroll; now each is a
 * block of its own inside it. The saved markup was empty either way, so
 * the old block is still valid; isEligible makes the editor migrate it
 * anyway. The server keeps reading the attribute for posts nobody opens.
 *
 * isEligible only decides for a block the editor finds valid; for an
 * invalid one the editor tries every deprecation on its markup alone.
 * Both versions save nothing, so v1 could match a block that has its
 * links as blocks already, which is why migrate keeps them.
 */

/**
 * Whether a blogroll holds its links as blocks already.
 *
 * Only link blocks count. Anything else a hand-edited post has in there
 * stays where it is; the server ignores it, too.
 *
 * @param {Object[]} innerBlocks Inner blocks of the blogroll.
 * @return {boolean} True when there is a link block.
 */
const hasLinkBlocks = ( innerBlocks ) =>
	( innerBlocks || [] ).some( ( block ) => LINK_BLOCK === block.name );

const v1 = {
	attributes: metadata.attributes,
	supports: metadata.supports,
	save: () => null,
	isEligible: ( attributes, innerBlocks ) =>
		hasLegacyLinks( attributes ) && ! hasLinkBlocks( innerBlocks ),
	migrate: ( attributes, innerBlocks ) => {
		const [ next, links ] = migrateLinks( attributes );
		const children = innerBlocks || [];
		return [
			next,
			hasLinkBlocks( children )
				? children
				: [ ...children, ...links.map( createLinkBlock ) ],
		];
	},
};

export default [ v1 ];
