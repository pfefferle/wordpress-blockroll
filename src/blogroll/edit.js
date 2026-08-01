/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Button,
	Flex,
	FlexItem,
	PanelBody,
	Placeholder,
	RangeControl,
	SelectControl,
	ToggleControl,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import LinkForm from './components/link-form';
import ImportModal from './components/import-modal';

/**
 * Move an array element.
 *
 * @param {Array}  list Array.
 * @param {number} from Index to move.
 * @param {number} to   Target index.
 * @return {Array} New array.
 */
function move( list, from, to ) {
	const next = [ ...list ];
	next.splice( to, 0, ...next.splice( from, 1 ) );
	return next;
}

/**
 * Block edit component.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { links, sortBy, perPage, showAvatars } = attributes;
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
		<Flex justify="flex-start">
			<Button variant="primary" onClick={ () => setEditing( 'new' ) }>
				{ __( 'Add link', 'blockroll' ) }
			</Button>
			<Button variant="secondary" onClick={ () => setIsImporting( true ) }>
				{ __( 'Import links', 'blockroll' ) }
			</Button>
		</Flex>
	);

	return (
		<div { ...useBlockProps() }>
			<InspectorControls>
				<PanelBody title={ __( 'Blogroll settings', 'blockroll' ) }>
					<SelectControl
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
								label: __( 'Custom order', 'blockroll' ),
								value: 'manual',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { sortBy: value } )
						}
					/>
					<RangeControl
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
				</PanelBody>
			</InspectorControls>

			{ isImporting && (
				<ImportModal
					onImport={ importLinks }
					onClose={ () => setIsImporting( false ) }
				/>
			) }

			{ ! links.length && null === editing ? (
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
				<>
					<ul className="blockroll-editor-list">
						{ links.map( ( link, index ) =>
							editing === index ? (
								<li key={ link.url }>
									<LinkForm
										link={ link }
										onSave={ saveLink }
										onCancel={ () => setEditing( null ) }
									/>
								</li>
							) : (
								<li key={ link.url }>
									<Flex justify="flex-start">
										{ showAvatars && link.photo && (
											<img
												src={ link.photo }
												alt=""
												width="24"
												height="24"
											/>
										) }
										<FlexItem isBlock>
											<strong>
												{ link.name || link.url }
											</strong>{ ' ' }
											{ link.xfn?.length > 0 && (
												<small>
													({ link.xfn.join( ' ' ) })
												</small>
											) }
										</FlexItem>
										<Button
											size="small"
											icon="arrow-up-alt2"
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
											size="small"
											icon="arrow-down-alt2"
											label={ __(
												'Move down',
												'blockroll'
											) }
											disabled={
												index === links.length - 1
											}
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
											size="small"
											onClick={ () => setEditing( index ) }
										>
											{ __( 'Edit', 'blockroll' ) }
										</Button>
										<Button
											size="small"
											isDestructive
											onClick={ () =>
												setAttributes( {
													links: links.filter(
														( unused, i ) =>
															i !== index
													),
												} )
											}
										>
											{ __( 'Remove', 'blockroll' ) }
										</Button>
									</Flex>
								</li>
							)
						) }
						{ 'new' === editing && (
							<li>
								<LinkForm
									onSave={ saveLink }
									onCancel={ () => setEditing( null ) }
								/>
							</li>
						) }
					</ul>
					{ null === editing && actions }
				</>
			) }
		</div>
	);
}
