/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { Button, Popover, TextControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { lookUp } from '../discover';
import { toUrl } from '../utils';

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
 * @param {Function} props.isKnown Whether an address is in the list already.
 * @param {Function} props.onClose Called when the overlay closes.
 */
export default function AddLink( { anchor, onAdd, isKnown, onClose } ) {
	const [ input, setInput ] = useState( '' );
	const [ isBusy, setIsBusy ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ isDuplicate, setIsDuplicate ] = useState( false );
	const value = input.trim();
	// A line under the field, like the notes core's overlays show.
	const message = isDuplicate
		? __( 'This site is in the list already.', 'blockroll' )
		: error;

	const add = () => {
		const url = toUrl( value );
		if ( isKnown?.( url ) ) {
			setIsDuplicate( true );
			return;
		}
		if ( error ) {
			onAdd( { url } );
			return;
		}
		setIsBusy( true );
		lookUp( url )
			.then( onAdd )
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
				<div className="blockroll-form__row">
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Address', 'blockroll' ) }
						hideLabelFromVision
						help={ message }
						className={ message ? 'has-message' : undefined }
						placeholder="example.com"
						type="text"
						inputMode="url"
						autoComplete="off"
						spellCheck={ false }
						value={ input }
						disabled={ isBusy }
						onChange={ ( next ) => {
							setInput( next );
							setError( null );
							setIsDuplicate( false );
						} }
					/>
					<Button
						__next40pxDefaultSize
						variant="primary"
						type="submit"
						isBusy={ isBusy }
						disabled={ ! value || isBusy || isDuplicate }
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
