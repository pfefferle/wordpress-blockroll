import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
// Not the link icon: the toolbar has that one for the link overlay.
import { listItem as icon } from '@wordpress/icons';
import './editor.scss';

// The blogroll renders the whole list on the server, so a link has no
// output of its own.
registerBlockType( metadata.name, { icon, edit: Edit, save: () => null } );
