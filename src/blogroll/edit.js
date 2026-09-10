/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';
import { useDispatch, useRegistry, useSelect } from '@wordpress/data';
import {
	InspectorControls,
	store as blockEditorStore,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	Button,
	Notice,
	PanelBody,
	Placeholder,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { arrowDown, arrowUp, pencil, trash } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import LinkForm from './components/link-form';
import ImportModal from './components/import-modal';
import { move } from './utils';
import { slugOf, uniqueAnchor } from './anchors';

/**
 * Block edit component.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @param {string}   props.clientId      Client ID of the block.
 */
export default function Edit( { attributes, setAttributes, clientId } ) {
	const {
		anchor,
		links,
		sortBy,
		perPage,
		showAvatars,
		showSort,
		showOpml,
		metadata,
	} = attributes;

	// The name lives where the editor's own "Rename" keeps it, so both write
	// the same value and a page never ends up with two names for one list.
	const setName = ( value ) => {
		const next = { ...metadata };
		if ( value ) {
			next.name = value;
		} else {
			delete next.name;
		}
		setAttributes( {
			metadata: Object.keys( next ).length ? next : undefined,
		} );
	};

	// The anchor is the address of this list: the id of the block and the
	// group of its OPML. It is generated from the name, like the Heading block
	// derives its anchor from the heading text, and made unique against every
	// other anchor on the page. An anchor set by hand under Advanced is left
	// alone. The server does the same for pages saved before this existed.
	const name = metadata?.name || '';
	const takenAnchors = ( select ) => {
		const { getClientIdsWithDescendants, getBlockAttributes } =
			select( blockEditorStore );
		return getClientIdsWithDescendants()
			.filter( ( id ) => id !== clientId )
			.map( ( id ) => getBlockAttributes( id )?.anchor )
			.filter( Boolean );
	};
	const taken = useSelect( takenAnchors, [ clientId ] );
	const registry = useRegistry();
	const { __unstableMarkNextChangeAsNotPersistent } =
		useDispatch( blockEditorStore );
	// Reads the store at the time it runs, not at render time: when several
	// blocks mount in one pass, each has to see the anchors the ones before
	// it just set, or two lists with the same name end up with the same one.
	const generate = ( forName ) =>
		uniqueAnchor( slugOf( forName ), takenAnchors( registry.select ) );

	// A block without an anchor gets one: a fresh block, or one saved before
	// anchors existed. Not an undo step, nobody typed anything.
	useEffect( () => {
		if ( ! anchor ) {
			__unstableMarkNextChangeAsNotPersistent();
			setAttributes( { anchor: generate( name ) } );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ anchor ] );

	// A rename, from the field below or from the block's own Rename, moves
	// the anchor along while it is still the generated one.
	const previousName = useRef( name );
	useEffect( () => {
		if ( previousName.current === name ) {
			return;
		}
		const wasGenerated =
			! anchor || anchor === generate( previousName.current );
		previousName.current = name;
		if ( wasGenerated ) {
			setAttributes( { anchor: generate( name ) } );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ name ] );

	const hasTwin = !! anchor && taken.includes( anchor );

	const [ editing, setEditing ] = useState( null ); // Index, 'new', or null.
	const [ isImporting, setIsImporting ] = useState( false );

	const saveLink = ( link ) => {
		const next = [ ...links ];
		if ( 'new' === editing ) {
			next.push( link );
		} else {
			next[ editing ] = link;
		}
		setAttributes( { links: next } );
		setEditing( null );
	};

	const importLinks = ( imported ) => {
		const known = new Set( links.map( ( link ) => link.url ) );
		setAttributes( {
			links: [
				...links,
				...imported.filter( ( link ) => ! known.has( link.url ) ),
			],
		} );
	};

	const actions = (
		<div className="blockroll-editor-actions">
			<Button variant="primary" onClick={ () => setEditing( 'new' ) }>
				{ __( 'Add link', 'blockroll' ) }
			</Button>
			<Button
				variant="secondary"
				onClick={ () => setIsImporting( true ) }
			>
				{ __( 'Import links', 'blockroll' ) }
			</Button>
		</div>
	);

	return (
		<div { ...useBlockProps() }>
			<InspectorControls>
				<PanelBody title={ __( 'Blogroll settings', 'blockroll' ) }>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Name', 'blockroll' ) }
						help={ sprintf(
							/* translators: %s: the anchor of the block */
							__(
								'Groups this list when a page has more than one blogroll, and gives it its address: #%s. Renaming the block does the same. The address can be changed under Advanced.',
								'blockroll'
							),
							anchor || generate( name )
						) }
						placeholder={ __( 'Blogroll', 'blockroll' ) }
						value={ name }
						onChange={ setName }
					/>
					{ hasTwin && (
						<Notice status="warning" isDismissible={ false }>
							{ sprintf(
								/* translators: %s: the anchor of the block */
								__(
									'The address #%s is already used by another block on this page.',
									'blockroll'
								),
								anchor
							) }
						</Notice>
					) }
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Sort by', 'blockroll' ) }
						value={ sortBy }
						options={ [
							{ label: __( 'Name', 'blockroll' ), value: 'name' },
							{
								label: __( 'Newest first', 'blockroll' ),
								value: 'added',
							},
							{
								label: __( 'List order', 'blockroll' ),
								value: 'manual',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { sortBy: value } )
						}
					/>
					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Links per page', 'blockroll' ) }
						help={ __(
							'0 shows all links on one page.',
							'blockroll'
						) }
						min={ 0 }
						max={ 50 }
						value={ perPage }
						onChange={ ( value ) =>
							setAttributes( { perPage: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show images', 'blockroll' ) }
						checked={ showAvatars }
						onChange={ ( value ) =>
							setAttributes( { showAvatars: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Let visitors sort the list',
							'blockroll'
						) }
						checked={ showSort }
						onChange={ ( value ) =>
							setAttributes( { showSort: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show download link', 'blockroll' ) }
						help={ __(
							'Links to the list as an OPML file.',
							'blockroll'
						) }
						checked={ showOpml }
						onChange={ ( value ) =>
							setAttributes( { showOpml: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			{ null !== editing && (
				<LinkForm
					link={ 'new' === editing ? undefined : links[ editing ] }
					onSave={ saveLink }
					onCancel={ () => setEditing( null ) }
				/>
			) }

			{ isImporting && (
				<ImportModal
					onImport={ importLinks }
					onClose={ () => setIsImporting( false ) }
				/>
			) }

			{ ! links.length ? (
				<Placeholder
					icon="admin-links"
					label={ __( 'Blogroll', 'blockroll' ) }
					instructions={ __(
						'Share a list of the blogs and sites you follow.',
						'blockroll'
					) }
				>
					{ actions }
				</Placeholder>
			) : (
				<div className="blockroll-editor">
					<ul className="blockroll-editor-list">
						{ links.map( ( link, index ) => (
							<li key={ link.url }>
								{ showAvatars &&
									( link.photo ? (
										<img
											src={ link.photo }
											alt=""
											width="32"
											height="32"
										/>
									) : (
										<span className="blockroll-editor-list__no-photo" />
									) ) }
								<span className="blockroll-editor-list__text">
									<strong>{ link.name || link.url }</strong>
									<small>
										{ link.url }
										{ link.xfn?.length > 0 &&
											' · ' + link.xfn.join( ' ' ) }
									</small>
								</span>
								<span className="blockroll-editor-list__actions">
									<Button
										size="compact"
										icon={ arrowUp }
										label={ __( 'Move up', 'blockroll' ) }
										disabled={ 0 === index }
										onClick={ () =>
											setAttributes( {
												links: move(
													links,
													index,
													index - 1
												),
											} )
										}
									/>
									<Button
										size="compact"
										icon={ arrowDown }
										label={ __( 'Move down', 'blockroll' ) }
										disabled={ index === links.length - 1 }
										onClick={ () =>
											setAttributes( {
												links: move(
													links,
													index,
													index + 1
												),
											} )
										}
									/>
									<Button
										size="compact"
										icon={ pencil }
										label={ __( 'Edit', 'blockroll' ) }
										onClick={ () => setEditing( index ) }
									/>
									<Button
										size="compact"
										icon={ trash }
										label={ __( 'Remove', 'blockroll' ) }
										isDestructive
										onClick={ () =>
											setAttributes( {
												links: links.filter(
													( unused, i ) => i !== index
												),
											} )
										}
									/>
								</span>
							</li>
						) ) }
					</ul>
					{ actions }
				</div>
			) }
		</div>
	);
}
