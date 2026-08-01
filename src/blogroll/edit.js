/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Button,
	PanelBody,
	Placeholder,
	RangeControl,
	SelectControl,
	ToggleControl,
} from '@wordpress/components';
import { arrowDown, arrowUp, pencil, trash } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import LinkForm from './components/link-form';
import ImportModal from './components/import-modal';
import { move } from './utils';

/**
 * Block edit component.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { links, sortBy, perPage, showAvatars, showSort } = attributes;
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
								label: __( 'Custom order', 'blockroll' ),
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
