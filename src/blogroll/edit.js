/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	createInterpolateElement,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { useDispatch, useRegistry, useSelect } from '@wordpress/data';
import { __experimentalUseFocusOutside as useFocusOutside } from '@wordpress/compose';
import {
	InspectorControls,
	store as blockEditorStore,
	useBlockProps,
	useInnerBlocksProps,
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
import apiFetch from '@wordpress/api-fetch';
import { store as noticesStore } from '@wordpress/notices';
import { escapeHTML } from '@wordpress/escape-html';

/**
 * Internal dependencies
 */
import AddLink from './components/add-link';
import { createLinkBlock, LINK_BLOCK } from './link-block';
import { siteKey, today } from './utils';
import ImportModal from './components/import-modal';
import { isGeneratedFrom, slugOf, uniqueAnchor } from './anchors';

/**
 * The client IDs of a blogroll's link blocks, without anything else that
 * may be in it. Reads the names, not the blocks: this runs on every change
 * of the editor store, and building the blocks would build every link's
 * attributes with them.
 *
 * @param {Object} select   The block editor store.
 * @param {string} clientId Client ID of the blogroll.
 * @return {string[]} Client IDs of the link blocks.
 */
const linkBlockIds = ( select, clientId ) =>
	select
		.getBlockOrder( clientId )
		.filter( ( id ) => LINK_BLOCK === select.getBlockName( id ) );

/**
 * Block edit component.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @param {string}   props.clientId      Client ID of the block.
 * @param {boolean}  props.isSelected    Whether the block is selected.
 */
export default function Edit( {
	attributes,
	setAttributes,
	clientId,
	isSelected,
} ) {
	const {
		anchor,
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
	// other anchor on the page. An anchor set by hand under Advanced is kept,
	// but made unique the same way, like the slug of a post: the user does
	// not have to check the rest of the page. The server does the same for
	// pages saved before this existed.
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

	// A block without an anchor gets one right away: a fresh block, or one
	// saved before anchors existed. A block whose anchor another block has
	// gets a counter once it is not selected any more: on mount, or when the
	// user leaves it after typing under Advanced. Not while typing, the
	// field would change under their fingers. The block that arrives or is
	// edited gives way, the one that had the anchor keeps it. Not an undo
	// step of its own either way.
	useEffect( () => {
		if ( ! anchor ) {
			__unstableMarkNextChangeAsNotPersistent();
			setAttributes( {
				anchor: uniqueAnchor( slugOf( name ), takenAnchors() ),
			} );
			return;
		}
		if ( isSelected ) {
			return;
		}
		const taken = takenAnchors();
		if ( taken.includes( anchor ) ) {
			__unstableMarkNextChangeAsNotPersistent();
			setAttributes( { anchor: uniqueAnchor( anchor, taken ) } );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ anchor, isSelected ] );

	// A rename, from the field below or from the block's own Rename, moves
	// the anchor along while it is still the generated one.
	const previousName = useRef( name );
	useEffect( () => {
		if ( previousName.current === name ) {
			return;
		}
		const wasGenerated =
			! anchor || isGeneratedFrom( anchor, previousName.current );
		previousName.current = name;
		if ( wasGenerated ) {
			setAttributes( {
				anchor: uniqueAnchor( slugOf( name ), takenAnchors() ),
			} );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ name ] );

	// The one collision left: another block taking this anchor while this
	// one is not selected, from its own HTML anchor field, which knows
	// nothing about this one. That goes through the editor's own notices,
	// keyed by the anchor so it is shown once. Neither block being typed
	// in counts, that is the field changing on its way to a value.
	const collision = useSelect(
		( select ) => {
			const {
				getClientIdsWithDescendants,
				getBlockAttributes,
				getSelectedBlockClientId,
			} = select( blockEditorStore );
			if ( ! anchor || isSelected ) {
				return false;
			}
			const selected = getSelectedBlockClientId();
			return getClientIdsWithDescendants().some(
				( id ) =>
					id !== clientId &&
					id !== selected &&
					getBlockAttributes( id )?.anchor === anchor
			);
		},
		[ clientId, anchor, isSelected ]
	);
	const { createWarningNotice, removeNotice } = useDispatch( noticesStore );
	const noticeId = useRef( null );
	useEffect( () => {
		if ( noticeId.current ) {
			removeNotice( noticeId.current );
			noticeId.current = null;
		}
		if ( ! collision ) {
			return;
		}
		noticeId.current = `blockroll-anchor-${ anchor }`;
		createWarningNotice(
			sprintf(
				/* translators: %s: the anchor of the blocks */
				__(
					'A Blogroll block and another block on this page both use the anchor <code>#%s</code>. Change the HTML anchor under Advanced, so that each block has its own.',
					'blockroll'
				),
				escapeHTML( anchor )
			),
			// The store takes a string, so the code element goes in as HTML.
			{ id: noticeId.current, isDismissible: true, __unstableHTML: true }
		);
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ collision, anchor ] );
	// A removed block takes its notice with it.
	useEffect(
		() => () => {
			if ( noticeId.current ) {
				removeNotice( noticeId.current );
			}
		},
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[]
	);

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

	// The links of a manual list are blocks of their own.
	// Two counts: the placeholder stands in for an empty list, which is
	// when the inner blocks render it, and the links are what the server
	// renders. They differ only in a hand-edited post that has something
	// else in here, which both sides ignore.
	const childCount = useSelect(
		( select ) => select( blockEditorStore ).getBlockCount( clientId ),
		[ clientId ]
	);
	const linkCount = useSelect(
		( select ) =>
			linkBlockIds( select( blockEditorStore ), clientId ).length,
		[ clientId ]
	);
	const { insertBlock, insertBlocks } = useDispatch( blockEditorStore );
	const [ isAdding, setIsAdding ] = useState( false );
	const [ addAnchor, setAddAnchor ] = useState();
	// The "Add link" overlay closes when the focus leaves the button and
	// the overlay; a click on the button itself only toggles.
	const addFocusOutside = useFocusOutside( () => setIsAdding( false ) );
	// The sites in the list right now, for the duplicate checks.
	const siteKeys = () => {
		const select = registry.select( blockEditorStore );
		return new Set(
			linkBlockIds( select, clientId ).map( ( id ) =>
				siteKey( select.getBlockAttributes( id )?.url )
			)
		);
	};
	const isKnown = ( url ) => siteKeys().has( siteKey( url ) );
	// A list that has links of its own is a manual one, however they got
	// there: added, imported, pasted or duplicated. Only when its source
	// is one that is not there right now, a plugin that is deactivated:
	// the editor and the server both fall back to manual then, and
	// without this the links would be ignored again the moment it comes
	// back. Not an undo step of its own; it is saved with the edit that
	// brought the links.
	useEffect( () => {
		if (
			hasLoadedSources &&
			! sourceIsAvailable &&
			manualSource !== source &&
			linkCount
		) {
			__unstableMarkNextChangeAsNotPersistent();
			setAttributes( { source: manualSource } );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ hasLoadedSources, sourceIsAvailable, source, linkCount ] );

	const addLink = ( link ) => {
		insertBlock(
			createLinkBlock( { ...link, added: today() } ),
			undefined,
			clientId
		);
		setIsAdding( false );
	};

	// Imported links become link blocks at the end of the list; sites
	// already in it, or twice in the file, are skipped.
	const importLinks = ( imported ) => {
		const seen = siteKeys();
		const blocks = imported
			.filter( ( link ) => {
				const key = siteKey( link.url );
				if ( ! key || seen.has( key ) ) {
					return false;
				}
				seen.add( key );
				return true;
			} )
			.map( createLinkBlock );
		if ( blocks.length ) {
			insertBlocks( blocks, undefined, clientId );
		}
	};

	const actions = (
		<div className="blockroll-editor-actions">
			<span { ...addFocusOutside }>
				<Button
					variant="primary"
					ref={ setAddAnchor }
					aria-expanded={ isAdding }
					onClick={ () => setIsAdding( ! isAdding ) }
				>
					{ __( 'Add link', 'blockroll' ) }
				</Button>
				{ isAdding && (
					<AddLink
						anchor={ addAnchor }
						onAdd={ addLink }
						isKnown={ isKnown }
						onClose={ () => setIsAdding( false ) }
					/>
				) }
			</span>
			<Button
				variant="secondary"
				onClick={ () => setIsImporting( true ) }
			>
				{ __( 'Import links', 'blockroll' ) }
			</Button>
			{ ! linkCount &&
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
	if ( manualSource === currentSource && ! childCount ) {
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

	// The list of link blocks; the placeholder stands in while it is empty.
	// Rendered whenever the source is manual, empty or not: it is what
	// registers the block list settings a link block needs to be inserted.
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'blockroll-editor-links' },
		{
			allowedBlocks: [ LINK_BLOCK ],
			templateLock: false,
			renderAppender: false,
			placeholder: emptyState,
		}
	);

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

	const renderEditorList = ( listLinks ) => (
		<ul className="blockroll-editor-list">
			{ listLinks.map( ( link ) => (
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
						help={ createInterpolateElement(
							sprintf(
								/* translators: %s: the anchor of the block */
								__(
									'Groups this list when a page has more than one blogroll, and sets its anchor: <code>#%s</code>. Renaming the block does the same. The HTML anchor can be changed under Advanced.',
									'blockroll'
								),
								anchor || slugOf( name )
							),
							{ code: <code /> }
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
						renderEditorList( previewLinks ) }
				</div>
			) : (
				<div className="blockroll-editor">
					<div { ...innerBlocksProps } />
					{ ! emptyState && actions }
				</div>
			) }
		</div>
	);
}
