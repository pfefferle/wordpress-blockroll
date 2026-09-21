import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';
import metadata from './block.json';
import Edit from './edit';
import deprecated from './deprecated';
import { blogroll as icon } from '../icons';
import './style.scss';
import './editor.scss';

// The list is rendered on the server; only the link blocks are saved.
registerBlockType( metadata.name, {
	icon,
	edit: Edit,
	save: () => <InnerBlocks.Content />,
	deprecated,
} );
