import { registerBlockType, registerBlockVariation } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';
import { select, subscribe } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import Edit from './edit';
import deprecated from './deprecated';
import { postList as icon } from '@wordpress/icons';
import './style.scss';
import './editor.scss';

// The list is rendered on the server; only the link blocks are saved.
registerBlockType( metadata.name, {
	icon,
	edit: Edit,
	save: () => <InnerBlocks.Content />,
	deprecated,
} );

// A list in a blog post is usually about that post, not a list to offer
// for subscription, so one added to a post starts out unlisted. Only what
// the inserter adds: a list already saved keeps what it has, and the
// switch in the sidebar changes it either way. The post type is known
// once the editor is set up, not when this runs.
const unsubscribe = subscribe( () => {
	const postType = select( 'core/editor' )?.getCurrentPostType();
	if ( ! postType ) {
		return;
	}
	unsubscribe();
	if ( 'post' === postType ) {
		registerBlockVariation( metadata.name, {
			name: 'unlisted',
			title: __( 'Blogroll', 'blockroll' ),
			icon,
			attributes: { listed: false },
			isDefault: true,
			scope: [ 'inserter' ],
		} );
	}
}, 'core/editor' );
