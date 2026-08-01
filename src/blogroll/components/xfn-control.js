/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { FormTokenField } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { ALL_TOKENS, sanitizeXfn } from './xfn';

/**
 * Compact relationship picker.
 *
 * A token field over the XFN vocabulary; the group rules are applied
 * on every change, so conflicting tokens replace each other.
 *
 * @param {Object}   props          Component props.
 * @param {string[]} props.value    Selected tokens.
 * @param {Function} props.onChange Change handler.
 */
export default function XfnControl( { value = [], onChange } ) {
	return (
		<FormTokenField
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			__experimentalExpandOnFocus
			label={ __( 'Relationship', 'blockroll' ) }
			value={ value }
			suggestions={ ALL_TOKENS }
			onChange={ ( tokens ) => onChange( sanitizeXfn( tokens ) ) }
		/>
	);
}
