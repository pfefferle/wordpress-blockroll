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
	PanelBody,
	Placeholder,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { arrowDown, arrowUp, pencil, trash } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';
import { store as noticesStore } from '@wordpress/notices';

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
		source,
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
	const registry = useRegistry();
	const { __unstableMarkNextChangeAsNotPersistent } =
		useDispatch( blockEditorStore );
	// Read from the store at the time it runs, not at render time: when
	// several blocks mount in one pass, each has to see the anchors the ones
	// before it just set, or two lists with the same name end up with the
	// same one.
	const takenAnchors = () => {
		const { getClientIdsWithDescendants, getBlockAttributes } =
			registry.select( blockEditorStore );
		return getClientIdsWithDescendants()
			.filter( ( id ) => id !== clientId )
			.map( ( id ) => getBlockAttributes( id )?.anchor )
			.filter( Boolean );
	};

	// A block without an anchor gets one when it mounts: a fresh block, or
	// one saved before anchors existed. So does a block that arrives with
	// the anchor of another one, which is what a duplicated block does.
	// Not an undo step either way, nobody typed anything.
	useEffect( () => {
		const taken = takenAnchors();
		if ( ! anchor || taken.includes( anchor ) ) {
			__unstableMarkNextChangeAsNotPersistent();
			setAttributes( { anchor: uniqueAnchor( slugOf( name ), taken ) } );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	// A rename, from the field below or from the block's own Rename, moves
	// the anchor along while it is still the generated one.
	const previousName = useRef( name );
	useEffect( () => {
		if ( previousName.current === name ) {
			return;
		}
		const taken = takenAnchors();
		const wasGenerated =
			! anchor ||
			anchor === uniqueAnchor( slugOf( previousName.current ), taken );
		previousName.current = name;
		if ( wasGenerated ) {
			setAttributes( { anchor: uniqueAnchor( slugOf( name ), taken ) } );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ name ] );

	// Whether an anchor set by hand under Advanced is also the anchor of
	// another block. The generated ones never collide, see above.
	const duplicate = useSelect(
		( select ) => {
			const { getClientIdsWithDescendants, getBlockAttributes } =
				select( blockEditorStore );
			return (
				!! anchor &&
				getClientIdsWithDescendants().some(
					( id ) =>
						id !== clientId &&
						getBlockAttributes( id )?.anchor === anchor
				)
			);
		},
		[ clientId, anchor ]
	);

	// The collision is reported through the editor's own notices, keyed by
	// the anchor: both blocks report it, the notice is shown once. It goes
	// away as soon as one of the anchors changes.
	const { createWarningNotice, removeNotice } = useDispatch( noticesStore );
	useEffect( () => {
		const id = `blockroll-anchor-${ anchor }`;
		if ( ! duplicate ) {
			removeNotice( id );
			return;
		}
		createWarningNotice(
			sprintf(
				/* translators: %s: the anchor of the blocks */
				__(
					'More than one block on this page has the address #%s. Change it under Advanced, so that each one has its own.',
					'blockroll'
				),
				anchor
			),
			{ id, isDismissible: true }
		);
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ duplicate, anchor ] );

	const [ editing, setEditing ] = useState( null ); // Index, 'new', or null.
	const [ isImporting, setIsImporting ] = useState( false );
	const [ sources, setSources ] = useState( [
		{ label: __( 'Manual links', 'blockroll' ), value: 'manual' },
	] );
	const [ previewLinks, setPreviewLinks ] = useState( [] );
	const [ isPreviewLoading, setIsPreviewLoading ] = useState( false );
	const [ previewError, setPreviewError ] = useState( null );
	const [ hasLoadedSources, setHasLoadedSources ] = useState( false );
	const manualSource = 'manual';
	const sourceIsAvailable = sources.some( ( item ) => source === item.value );
	const currentSource =
		! source || ( hasLoadedSources && ! sourceIsAvailable )
			? manualSource
			: source;
	const serializedAttributes = JSON.stringify( attributes );
	const externalSources = sources.filter(
		( item ) => manualSource !== item.value
	);
	const selectedSource =
		sources.find( ( item ) => currentSource === item.value ) ||
		sources[ 0 ];

	useEffect( () => {
		apiFetch( { path: '/blockroll/v1/sources' } )
			.then( ( response ) => {
				if ( Array.isArray( response ) ) {
					setSources( response );
					setHasLoadedSources( true );
				}
			} )
			.catch( () => {} );
	}, [] );

	useEffect( () => {
		let isCurrent = true;
		if ( manualSource === currentSource ) {
			setPreviewLinks( [] );
			setPreviewError( null );
			setIsPreviewLoading( false );
			return () => {
				isCurrent = false;
			};
		}

		setIsPreviewLoading( true );
		setPreviewError( null );
		apiFetch( {
			path:
				`/blockroll/v1/sources/${ currentSource }/links?attributes=` +
				encodeURIComponent( serializedAttributes ),
		} )
			.then( ( response ) => {
				if ( isCurrent ) {
					setPreviewLinks(
						Array.isArray( response ) ? response : []
					);
				}
			} )
			.catch( ( error ) => {
				if ( isCurrent ) {
					setPreviewLinks( [] );
					setPreviewError(
						error.message ||
							__(
								'The source preview could not be loaded.',
								'blockroll'
							)
					);
				}
			} )
			.finally( () => {
				if ( isCurrent ) {
					setIsPreviewLoading( false );
				}
			} );

		return () => {
			isCurrent = false;
		};
	}, [ currentSource, serializedAttributes ] );

	const saveLink = ( link ) => {
		const next = [ ...links ];
		if ( 'new' === editing ) {
			next.push( link );
		} else {
			next[ editing ] = link;
		}
		setAttributes( { links: next, source: manualSource } );
		setEditing( null );
	};

	const importLinks = ( imported ) => {
		const known = new Set( links.map( ( link ) => link.url ) );
		setAttributes( {
			source: manualSource,
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
			{ ! links.length &&
				manualSource === currentSource &&
				externalSources.map( ( item ) => (
					<Button
						key={ item.value }
						variant="secondary"
						onClick={ () =>
							setAttributes( { source: item.value } )
						}
					>
						{ sprintf(
							/* translators: %s: Source name. */
							__( 'Use %s', 'blockroll' ),
							item.label
						) }
					</Button>
				) ) }
		</div>
	);

	let emptyState = null;
	if ( manualSource === currentSource && ! links.length ) {
		emptyState = (
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
		);
	}

	const switchToManualButton = (
		<div className="blockroll-editor-actions">
			<Button
				variant="secondary"
				onClick={ () => setAttributes( { source: manualSource } ) }
			>
				{ __( 'Use manual links', 'blockroll' ) }
			</Button>
		</div>
	);

	const renderEditorList = ( listLinks, isReadOnly = false ) => (
		<ul className="blockroll-editor-list">
			{ listLinks.map( ( link, index ) => (
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
					{ ! isReadOnly && (
						<span className="blockroll-editor-list__actions">
							<Button
								size="compact"
								icon={ arrowUp }
								label={ __( 'Move up', 'blockroll' ) }
								disabled={ 0 === index }
								onClick={ () =>
									setAttributes( {
										links: move( links, index, index - 1 ),
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
										links: move( links, index, index + 1 ),
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
					) }
				</li>
			) ) }
		</ul>
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
							anchor || slugOf( name )
						) }
						placeholder={ __( 'Blogroll', 'blockroll' ) }
						value={ name }
						onChange={ setName }
					/>
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

			{ manualSource !== currentSource ? (
				<div className="blockroll-editor">
					<Placeholder
						icon="admin-links"
						label={ selectedSource.label }
						instructions={
							isPreviewLoading
								? __( 'Loading source preview…', 'blockroll' )
								: sprintf(
										/* translators: %d: Number of previewed links. */
										__(
											'%d links will be shown when the block is rendered.',
											'blockroll'
										),
										previewLinks.length
								  )
						}
					>
						{ switchToManualButton }
					</Placeholder>
					{ selectedSource.help && (
						<p>
							{ selectedSource.help }
							{ selectedSource.helpUrl && (
								<>
									{ ' ' }
									<a href={ selectedSource.helpUrl }>
										{ __( 'Manage source', 'blockroll' ) }
									</a>
								</>
							) }
						</p>
					) }
					{ previewError && <p>{ previewError }</p> }
					{ ! previewError &&
						! isPreviewLoading &&
						!! previewLinks.length &&
						renderEditorList( previewLinks, true ) }
				</div>
			) : (
				emptyState || (
					<div className="blockroll-editor">
						{ renderEditorList( links ) }
						{ actions }
					</div>
				)
			) }
		</div>
	);
}
