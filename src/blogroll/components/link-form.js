/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Flex,
	FlexItem,
	Notice,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import XfnControl from './xfn-control';

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
 * Form to add or edit a single link.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.link     Link to edit, or undefined for a new one.
 * @param {Function} props.onSave   Called with the finished link.
 * @param {Function} props.onCancel Called when the user cancels.
 */
export default function LinkForm( { link, onSave, onCancel } ) {
	const [ draft, setDraft ] = useState( { ...EMPTY, ...link } );
	const [ isFetching, setIsFetching ] = useState( false );
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
				setDraft( ( current ) => ( {
					...current,
					name: current.name || found.name,
					description: current.description || found.description,
					feedUrl: current.feedUrl || found.feedUrl,
					photo: current.photo || found.photo,
				} ) )
			)
			.catch( ( fetchError ) =>
				setError(
					fetchError.message ||
						__( 'The site could not be reached.', 'blockroll' )
				)
			)
			.finally( () => setIsFetching( false ) );
	};

	const save = () =>
		onSave( {
			...draft,
			added: draft.added || new Date().toISOString().slice( 0, 10 ),
		} );

	return (
		<div className="blockroll-link-form">
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<Flex align="flex-end">
				<FlexItem isBlock>
					<TextControl
						__nextHasNoMarginBottom
						label={ __( 'Address', 'blockroll' ) }
						placeholder="https://example.com/"
						type="url"
						value={ draft.url }
						onChange={ set( 'url' ) }
					/>
				</FlexItem>
				<FlexItem>
					<Button
						variant="secondary"
						disabled={ ! draft.url || isFetching }
						onClick={ fetchDetails }
					>
						{ isFetching ? (
							<Spinner />
						) : (
							__( 'Fetch details', 'blockroll' )
						) }
					</Button>
				</FlexItem>
			</Flex>
			<TextControl
				__nextHasNoMarginBottom
				label={ __( 'Name', 'blockroll' ) }
				value={ draft.name }
				onChange={ set( 'name' ) }
			/>
			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'Description', 'blockroll' ) }
				value={ draft.description }
				onChange={ set( 'description' ) }
			/>
			<TextControl
				__nextHasNoMarginBottom
				label={ __( 'Feed address', 'blockroll' ) }
				type="url"
				value={ draft.feedUrl }
				onChange={ set( 'feedUrl' ) }
			/>
			<TextControl
				__nextHasNoMarginBottom
				label={ __( 'Image address', 'blockroll' ) }
				type="url"
				value={ draft.photo }
				onChange={ set( 'photo' ) }
			/>
			<XfnControl value={ draft.xfn } onChange={ set( 'xfn' ) } />
			<Flex justify="flex-start">
				<Button
					variant="primary"
					disabled={ ! draft.url }
					onClick={ save }
				>
					{ __( 'Save link', 'blockroll' ) }
				</Button>
				<Button variant="tertiary" onClick={ onCancel }>
					{ __( 'Cancel', 'blockroll' ) }
				</Button>
			</Flex>
		</div>
	);
}
