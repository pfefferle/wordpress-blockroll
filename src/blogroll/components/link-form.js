/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Modal,
	Notice,
	TextControl,
	TextareaControl,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import XfnControl from './xfn-control';
import { fetchPhoto, mergeDiscovered, needsEmbedding } from '../utils';

const EMPTY = {
	url: '',
	name: '',
	description: '',
	feedUrl: '',
	photo: '',
	xfn: [],
	added: '',
};

/**
 * Modal to add or edit a single link.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.link     Link to edit, or undefined for a new one.
 * @param {Function} props.onSave   Called with the finished link.
 * @param {Function} props.onCancel Called when the user cancels.
 */
export default function LinkForm( { link, onSave, onCancel } ) {
	const [ draft, setDraft ] = useState( { ...EMPTY, ...link } );
	const [ isFetching, setIsFetching ] = useState( false );
	const [ isEmbedding, setIsEmbedding ] = useState( false );
	const [ photoUrl, setPhotoUrl ] = useState( '' );
	const [ error, setError ] = useState( null );

	const set = ( field ) => ( fieldValue ) =>
		setDraft( ( current ) => ( { ...current, [ field ]: fieldValue } ) );

	const fetchDetails = () => {
		setIsFetching( true );
		setError( null );
		apiFetch( {
			path: '/blockroll/v1/discover',
			method: 'POST',
			data: { url: draft.url },
		} )
			.then( ( found ) =>
				setDraft( ( current ) => mergeDiscovered( current, found ) )
			)
			.catch( ( fetchError ) =>
				setError(
					fetchError.message ||
						__( 'The site could not be reached.', 'blockroll' )
				)
			)
			.finally( () => setIsFetching( false ) );
	};

	// The image is copied into the page, so a visitor never asks the
	// other site for it.
	const embedPhoto = ( source ) => {
		setIsEmbedding( true );
		setError( null );
		fetchPhoto( source )
			.then( ( photo ) => {
				if ( ! photo ) {
					setError(
						__(
							'That address did not give us an image we can use.',
							'blockroll'
						)
					);
					return;
				}
				setDraft( ( current ) => ( { ...current, photo } ) );
				setPhotoUrl( '' );
			} )
			.finally( () => setIsEmbedding( false ) );
	};

	const save = () =>
		onSave( {
			...draft,
			added: draft.added || new Date().toISOString().slice( 0, 10 ),
		} );

	return (
		<Modal
			title={
				link
					? __( 'Edit link', 'blockroll' )
					: __( 'Add link', 'blockroll' )
			}
			size="medium"
			onRequestClose={ onCancel }
		>
			<div className="blockroll-form">
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }
				<div className="blockroll-form__row">
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Address', 'blockroll' ) }
						placeholder="https://example.com/"
						type="url"
						value={ draft.url }
						onChange={ set( 'url' ) }
					/>
					<Button
						__next40pxDefaultSize
						variant="secondary"
						isBusy={ isFetching }
						disabled={ ! draft.url || isFetching }
						onClick={ fetchDetails }
					>
						{ __( 'Fetch details', 'blockroll' ) }
					</Button>
				</div>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Name', 'blockroll' ) }
					value={ draft.name }
					onChange={ set( 'name' ) }
				/>
				<TextareaControl
					__nextHasNoMarginBottom
					label={ __( 'Description', 'blockroll' ) }
					rows={ 2 }
					value={ draft.description }
					onChange={ set( 'description' ) }
				/>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Feed address', 'blockroll' ) }
					type="url"
					value={ draft.feedUrl }
					onChange={ set( 'feedUrl' ) }
				/>
				{ draft.photo && (
					<div className="blockroll-form__photo">
						<img src={ draft.photo } alt="" width="48" />
						{ needsEmbedding( draft.photo ) ? (
							<Button
								__next40pxDefaultSize
								variant="secondary"
								isBusy={ isEmbedding }
								disabled={ isEmbedding }
								onClick={ () => embedPhoto( draft ) }
							>
								{ __(
									'Copy the image into the page',
									'blockroll'
								) }
							</Button>
						) : (
							<Button
								__next40pxDefaultSize
								variant="tertiary"
								isDestructive
								onClick={ () => set( 'photo' )( '' ) }
							>
								{ __( 'Remove image', 'blockroll' ) }
							</Button>
						) }
					</div>
				) }
				<div className="blockroll-form__row">
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Image address', 'blockroll' ) }
						help={ __(
							'The image is copied into the page, so visitors never load it from another site.',
							'blockroll'
						) }
						type="url"
						value={ photoUrl }
						onChange={ setPhotoUrl }
					/>
					<Button
						__next40pxDefaultSize
						variant="secondary"
						isBusy={ isEmbedding }
						disabled={ ! photoUrl || isEmbedding }
						onClick={ () => embedPhoto( { photo: photoUrl } ) }
					>
						{ __( 'Use image', 'blockroll' ) }
					</Button>
				</div>
				<XfnControl value={ draft.xfn } onChange={ set( 'xfn' ) } />
				<div className="blockroll-form__footer">
					<Button
						__next40pxDefaultSize
						variant="tertiary"
						onClick={ onCancel }
					>
						{ __( 'Cancel', 'blockroll' ) }
					</Button>
					<Button
						__next40pxDefaultSize
						variant="primary"
						disabled={ ! draft.url }
						onClick={ save }
					>
						{ __( 'Save', 'blockroll' ) }
					</Button>
				</div>
			</div>
		</Modal>
	);
}
