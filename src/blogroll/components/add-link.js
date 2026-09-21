/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Popover, TextControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { mergeDiscovered, toUrl } from '../utils';

/**
 * The overlay behind "Add link": an address field, anchored at the button.
 *
 * Adding looks the site up and fills in what it finds, the way the old
 * form's "Fetch details" did. When the site can't be reached the error
 * is shown and the link can be added with the address alone.
 *
 * @param {Object}   props         Component props.
 * @param {Element}  props.anchor  Element the popover is anchored at.
 * @param {Function} props.onAdd   Called with the attributes of the new link.
 * @param {Function} props.onClose Called when the overlay closes.
 */
export default function AddLink( { anchor, onAdd, onClose } ) {
	const [ input, setInput ] = useState( '' );
	const [ isBusy, setIsBusy ] = useState( false );
	const [ error, setError ] = useState( null );
	const value = input.trim();

	const add = () => {
		const url = toUrl( value );
		if ( error ) {
			onAdd( { url } );
			return;
		}
		setIsBusy( true );
		apiFetch( {
			path: '/blockroll/v1/discover',
			method: 'POST',
			data: { url },
		} )
			.then( ( found ) => onAdd( mergeDiscovered( { url }, found ) ) )
			.catch( ( fetchError ) => {
				setError(
					fetchError.message ||
						__( 'The site could not be reached.', 'blockroll' )
				);
				setIsBusy( false );
			} );
	};

	return (
		<Popover
			anchor={ anchor }
			placement="bottom-start"
			offset={ 8 }
			onClose={ onClose }
			focusOnMount="firstElement"
			className="blockroll-add-link"
		>
			<form
				className="blockroll-add-link__form"
				onSubmit={ ( event ) => {
					event.preventDefault();
					if ( value && ! isBusy ) {
						add();
					}
				} }
			>
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }
				<div className="blockroll-add-link__row">
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Address', 'blockroll' ) }
						hideLabelFromVision
						placeholder="example.com"
						type="text"
						inputMode="url"
						spellCheck={ false }
						value={ input }
						disabled={ isBusy }
						onChange={ ( next ) => {
							setInput( next );
							setError( null );
						} }
					/>
					<Button
						__next40pxDefaultSize
						variant="primary"
						type="submit"
						isBusy={ isBusy }
						disabled={ ! value || isBusy }
					>
						{ error
							? __( 'Add anyway', 'blockroll' )
							: __( 'Add', 'blockroll' ) }
					</Button>
				</div>
			</form>
		</Popover>
	);
}
