/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __experimentalUseFocusOutside as useFocusOutside } from '@wordpress/compose';
import {
	BlockControls,
	InspectorControls,
	LinkControl,
	MediaReplaceFlow,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	Button,
	Icon,
	PanelBody,
	Popover,
	TextControl,
	ToolbarButton,
} from '@wordpress/components';
import { link as linkIcon, rss } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import AddressForm from '../blogroll/components/address-form';
import XfnControl from '../blogroll/components/xfn-control';
import { isAborted, lookUp } from '../blogroll/discover';
import { toUrl, today } from '../blogroll/utils';
import OverlayButton from './overlay-button';

/**
 * Nothing: the link overlay's own focus check is off, the wrapper around
 * the name, the toolbar button and the overlay does it.
 */
const noop = () => {};

/**
 * One link of a blogroll.
 *
 * The card looks like the one on the site and uses its class names, so
 * the frontend stylesheet, which the editor loads as well, styles it.
 * Everything is edited where it shows: name and description in place,
 * the address in a link overlay under the name that opens with a click
 * into the name (or from the toolbar), the feed and the relationship in
 * small overlays on the meta row, like an inline link in a paragraph.
 * A click on the image opens the menu an Image block has in its
 * toolbar: library, upload, address, reset.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @param {Object}   props.context       Values provided by the blogroll.
 * @param {boolean}  props.isSelected    Whether the block is selected.
 */
export default function Edit( {
	attributes,
	setAttributes,
	context,
	isSelected,
} ) {
	const { url, name, description, photo, feedUrl, xfn, added } = attributes;
	const [ draftUrl, setDraftUrl ] = useState( '' );
	const [ isLookingUp, setIsLookingUp ] = useState( false );
	// A lookup still running when the block is gone is cancelled.
	const controller = useRef( new AbortController() );
	useEffect( () => () => controller.current.abort(), [] );
	// The link overlay opened from the name leaves the focus there, the
	// one opened from the toolbar takes it. It closes when the focus
	// leaves the name, the toolbar button and the overlay; a click on the
	// toolbar button only toggles.
	const [ linkOverlay, setLinkOverlay ] = useState( null ); // 'name' | 'toolbar' | null
	const closeLinkOverlay = () => setLinkOverlay( null );
	const linkFocusOutside = useFocusOutside( closeLinkOverlay );
	const [ popoverAnchor, setPopoverAnchor ] = useState();
	// The meta row: 'feed' or 'xfn' while one of its overlays is open.
	const [ metaOverlay, setMetaOverlay ] = useState( null );
	const closeMeta = () => setMetaOverlay( null );
	const toggleMeta = ( key ) =>
		setMetaOverlay( key === metaOverlay ? null : key );
	// The image menu's toggle, as the menu hands it over on each render.
	const photoMenu = useRef( {} );

	// The image is in the editor's iframe, the menu and the media library
	// are in the parent document. The menu's own "focus left" check looks
	// at the iframe's document only, takes the library for outside and
	// closes; that unmounts the media control, which removes the library
	// on unmount and leaves an empty modal. So the same check is done
	// here against the document the focus went to, and the menu is closed
	// through its own toggle.
	const closePhotoMenuIfFocusOutside = ( event ) => {
		const active = event.target.ownerDocument.activeElement;
		if (
			! active?.closest( '[role="dialog"]' ) &&
			photoMenu.current.isOpen
		) {
			photoMenu.current.onToggle();
		}
	};
	const showAvatar = context[ 'blockroll/showAvatars' ] ?? true;
	const showFeed = isSelected || !! feedUrl;
	const showXfn = isSelected || xfn.length > 0;
	const blockProps = useBlockProps( {
		className: url ? 'h-card' : undefined,
	} );

	const avatar = photo ? (
		<img className="u-photo" src={ photo } alt="" />
	) : (
		<span className="blockroll-no-photo" />
	);

	const inspector = (
		<InspectorControls>
			<PanelBody title={ __( 'Link settings', 'blockroll' ) }>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Added', 'blockroll' ) }
					help={ __(
						'Used when the list is sorted newest first.',
						'blockroll'
					) }
					type="date"
					value={ added }
					onChange={ ( value ) => setAttributes( { added: value } ) }
				/>
			</PanelBody>
		</InspectorControls>
	);

	// A link block without an address, from the inserter or after the
	// address was removed: the same lookup as "Add link" runs.
	if ( ! url ) {
		const add = () => {
			const value = toUrl( draftUrl );
			setIsLookingUp( true );
			lookUp( value, controller.current.signal )
				.catch( ( error ) => {
					if ( isAborted( error ) ) {
						throw error;
					}
					return { url: value };
				} )
				.then( ( link ) =>
					setAttributes( { ...link, added: added || today() } )
				)
				.catch( noop )
				.finally( () => setIsLookingUp( false ) );
		};
		return (
			<div { ...blockProps }>
				{ inspector }
				<AddressForm
					value={ draftUrl }
					onChange={ setDraftUrl }
					onSubmit={ add }
					isBusy={ isLookingUp }
				/>
			</div>
		);
	}

	return (
		<>
			{ inspector }
			<div { ...blockProps }>
				{ showAvatar && (
					<MediaReplaceFlow
						mediaURL={ photo }
						allowedTypes={ [ 'image' ] }
						accept="image/*"
						onSelect={ ( media ) =>
							setAttributes( { photo: media.url } )
						}
						onSelectURL={ ( value ) =>
							setAttributes( { photo: value } )
						}
						onReset={ () => setAttributes( { photo: '' } ) }
						popoverProps={ {
							onFocusOutside: closePhotoMenuIfFocusOutside,
						} }
						renderToggle={ ( { children, ...toggleProps } ) => {
							photoMenu.current = {
								isOpen: toggleProps[ 'aria-expanded' ],
								onToggle: toggleProps.onClick,
							};
							return (
								<Button
									{ ...toggleProps }
									className="blockroll-link__photo"
									label={ __( 'Image', 'blockroll' ) }
								>
									{ avatar }
								</Button>
							);
						} }
					/>
				) }
				<span className="blockroll-link__name" { ...linkFocusOutside }>
					<BlockControls group="block">
						<ToolbarButton
							icon={ linkIcon }
							title={ __( 'Link', 'blockroll' ) }
							isActive={ !! linkOverlay }
							onClick={ () =>
								setLinkOverlay( linkOverlay ? null : 'toolbar' )
							}
						/>
					</BlockControls>
					{ isSelected && linkOverlay && (
						<Popover
							anchor={ popoverAnchor }
							placement="bottom-start"
							shift
							onClose={ closeLinkOverlay }
							onFocusOutside={ noop }
							focusOnMount={
								'toolbar' === linkOverlay
									? 'firstElement'
									: false
							}
						>
							<LinkControl
								value={ { url, title: name } }
								settings={ [] }
								hasTextControl
								forceIsEditingLink
								showInitialSuggestions={ false }
								onChange={ ( next ) => {
									setAttributes( {
										url: next.url || '',
										name: next.title ?? name,
									} );
									closeLinkOverlay();
								} }
								onCancel={ closeLinkOverlay }
							/>
						</Popover>
					) }
					<RichText
						ref={ setPopoverAnchor }
						onFocus={ () => setLinkOverlay( 'name' ) }
						disableLineBreaks
						tagName="a"
						className="u-url p-name"
						href={ url }
						value={ name }
						allowedFormats={ [] }
						withoutInteractiveFormatting
						placeholder={ __( 'Site name', 'blockroll' ) }
						onChange={ ( value ) =>
							setAttributes( { name: value } )
						}
					/>
				</span>
				{ ( isSelected || description ) && (
					<RichText
						disableLineBreaks
						tagName="p"
						className="p-note"
						value={ description }
						allowedFormats={ [] }
						withoutInteractiveFormatting
						placeholder={ __( 'Description', 'blockroll' ) }
						onChange={ ( value ) =>
							setAttributes( { description: value } )
						}
					/>
				) }
				{ /* Like on the site, what is empty shows only while the block
				     is selected, as what could be added. */ }
				{ ( showFeed || showXfn ) && (
					<div className="blockroll-meta">
						{ showFeed && (
							<OverlayButton
								className="blockroll-feed blockroll-link__meta-button"
								isOpen={ 'feed' === metaOverlay }
								onToggle={ () => toggleMeta( 'feed' ) }
								onClose={ closeMeta }
								label={
									<>
										<Icon
											icon={ rss }
											className="blockroll-feed-icon"
										/>
										{ feedUrl
											? __( 'feed', 'blockroll' )
											: __( 'Add feed', 'blockroll' ) }
									</>
								}
							>
								<TextControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									label={ __( 'Feed address', 'blockroll' ) }
									help={ __(
										'The RSS or Atom feed of the site, for readers who subscribe to your list.',
										'blockroll'
									) }
									type="url"
									value={ feedUrl }
									onChange={ ( value ) =>
										setAttributes( { feedUrl: value } )
									}
								/>
							</OverlayButton>
						) }
						{ showFeed && showXfn && (
							<span
								className="blockroll-divider"
								aria-hidden="true"
							>
								&#183;
							</span>
						) }
						{ /* The first focusable thing in the token field is a
					     token's remove button, so the input is focused by
					     hand once the overlay is placed. focusOnMount stays
					     on, for the focus trap and the focus return. */ }
						{ showXfn && (
							<OverlayButton
								className="blockroll-link__meta-button"
								isOpen={ 'xfn' === metaOverlay }
								onToggle={ () => toggleMeta( 'xfn' ) }
								onClose={ closeMeta }
								focusOnMount
								label={
									xfn.length > 0 ? (
										<ul className="blockroll-xfn">
											{ xfn.map( ( token ) => (
												<li key={ token }>{ token }</li>
											) ) }
										</ul>
									) : (
										__( 'Add relationship', 'blockroll' )
									)
								}
							>
								<div
									ref={ ( node ) =>
										node &&
										node.ownerDocument.defaultView.requestAnimationFrame(
											() =>
												node
													.querySelector( 'input' )
													?.focus()
										)
									}
								>
									<XfnControl
										value={ xfn }
										onChange={ ( value ) =>
											setAttributes( { xfn: value } )
										}
									/>
								</div>
							</OverlayButton>
						) }
					</div>
				) }
			</div>
		</>
	);
}
