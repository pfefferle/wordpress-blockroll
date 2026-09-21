/**
 * WordPress dependencies
 */
import { createBlock } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import metadata from './block.json';
import { hasLegacyLinks, migrateLinks } from './utils';

/**
 * Before 1.3 the links were an attribute of the blogroll; now each is a
 * block of its own inside it. The saved markup was empty either way, so
 * the old block is still valid; isEligible makes the editor migrate it
 * anyway. The server keeps reading the attribute for posts nobody opens.
 */
const v1 = {
	attributes: metadata.attributes,
	supports: metadata.supports,
	save: () => null,
	isEligible: ( attributes, innerBlocks ) =>
		hasLegacyLinks( attributes ) && ! innerBlocks?.length,
	migrate: ( attributes, innerBlocks ) => {
		const [ next, links ] = migrateLinks( attributes );
		return [
			next,
			[
				...( innerBlocks || [] ),
				...links.map( ( link ) =>
					createBlock( 'blockroll/link', link )
				),
			],
		];
	},
};

export default [ v1 ];
