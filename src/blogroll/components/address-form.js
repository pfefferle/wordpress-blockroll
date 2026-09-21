/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button, TextControl } from '@wordpress/components';

/**
 * An address field with its button: the "Add link" overlay and a link
 * block that has no address yet.
 *
 * @param {Object}   props             Component props.
 * @param {string}   props.value       The address as typed.
 * @param {Function} props.onChange    Called with the typed address.
 * @param {Function} props.onSubmit    Called when the address is submitted.
 * @param {boolean}  props.isBusy      Whether the address is being looked up.
 * @param {string}   props.message     A line under the field, an error or a note.
 * @param {string}   props.buttonLabel Label of the button, "Add" by default.
 */
export default function AddressForm( {
	value,
	onChange,
	onSubmit,
	isBusy,
	message,
	buttonLabel,
} ) {
	return (
		<form
			className="blockroll-form__row"
			onSubmit={ ( event ) => {
				event.preventDefault();
				onSubmit();
			} }
		>
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
				value={ value }
				disabled={ isBusy }
				onChange={ onChange }
			/>
			<Button
				__next40pxDefaultSize
				variant="primary"
				type="submit"
				isBusy={ isBusy }
				disabled={ ! value.trim() || isBusy }
			>
				{ buttonLabel || __( 'Add', 'blockroll' ) }
			</Button>
		</form>
	);
}
