import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import { link as icon } from '../icons';
import './editor.scss';

// The blogroll renders the whole list on the server, so a link has no
// output of its own.
registerBlockType( metadata.name, { icon, edit: Edit, save: () => null } );
