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
 * Typing completes: Enter takes the first match, so "fr" becomes
 * "friend". A word that is no relationship stays in the field instead of
 * being swallowed, the vocabulary is the one of the XFN spec.
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
			__experimentalAutoSelectFirstMatch
			__experimentalValidateInput={ ( token ) =>
				ALL_TOKENS.includes( token )
			}
			label={ __( 'Relationship', 'blockroll' ) }
			value={ value }
			suggestions={ ALL_TOKENS }
			onChange={ ( tokens ) => onChange( sanitizeXfn( tokens ) ) }
		/>
	);
}
