/**
 * Internal dependencies
 */
import metadata from './block.json';
import { createLinkBlock } from './link-block';
import { hasLegacyLinks, migrateLinks } from './utils';

/**
 * Before 1.3 the links were an attribute of the blogroll; now each is a
 * block of its own inside it. The saved markup was empty either way, so
 * the old block is still valid; isEligible makes the editor migrate it
 * anyway. The server keeps reading the attribute for posts nobody opens.
 *
 * The attribute is spelled out here, so this keeps working once it has
 * left block.json.
 */
const v1 = {
	attributes: {
		...metadata.attributes,
		links: { type: 'array', default: [] },
	},
	supports: metadata.supports,
	save: () => null,
	isEligible: ( attributes, innerBlocks ) =>
		hasLegacyLinks( attributes ) && ! innerBlocks?.length,
	migrate: ( attributes ) => {
		const [ next, links ] = migrateLinks( attributes );
		return [ next, links.map( createLinkBlock ) ];
	},
};

export default [ v1 ];
