/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';
import { Popover } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { isAborted, lookUp } from '../discover';
import { toUrl } from '../utils';
import AddressForm from './address-form';

/**
 * Nothing: the overlay's own focus check is off, the wrapper around the
 * button and the overlay does it.
 */
const noop = () => {};

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
	// A lookup still running when the overlay closes is cancelled.
	const controller = useRef( new AbortController() );
	useEffect( () => () => controller.current.abort(), [] );
	// A line under the field, like the notes core's overlays show.
	const message = isDuplicate
		? __( 'This site is in the list already.', 'blockroll' )
		: error;

	const add = () => {
		const url = toUrl( input );
		if ( isKnown( url ) ) {
			setIsDuplicate( true );
			return;
		}
		if ( error ) {
			onAdd( { url } );
			return;
		}
		setIsBusy( true );
		lookUp( url, controller.current.signal )
			.then( onAdd )
			.catch( ( fetchError ) => {
				if ( isAborted( fetchError ) ) {
					return;
				}
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
			onFocusOutside={ noop }
			focusOnMount="firstElement"
			className="blockroll-add-link"
		>
			<AddressForm
				value={ input }
				onChange={ ( next ) => {
					setInput( next );
					setError( null );
					setIsDuplicate( false );
				} }
				onSubmit={ add }
				isBusy={ isBusy }
				message={ message }
				buttonLabel={
					error ? __( 'Add anyway', 'blockroll' ) : undefined
				}
			/>
		</Popover>
	);
}
