/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	CheckboxControl,
	RadioControl,
	ToggleControl,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import { GROUPS, EXCLUSIVE, applyXfnToken } from './xfn';

const GROUP_LABELS = {
	friendship: __( 'Friendship', 'blockroll' ),
	physical: __( 'In person', 'blockroll' ),
	professional: __( 'Work', 'blockroll' ),
	geographical: __( 'Location', 'blockroll' ),
	family: __( 'Family', 'blockroll' ),
	romantic: __( 'Romantic', 'blockroll' ),
};

const TOKEN_LABELS = {
	friend: __( 'Friend', 'blockroll' ),
	acquaintance: __( 'Acquaintance', 'blockroll' ),
	contact: __( 'Contact', 'blockroll' ),
	met: __( 'We have met', 'blockroll' ),
	'co-worker': __( 'Co-worker', 'blockroll' ),
	colleague: __( 'Colleague', 'blockroll' ),
	'co-resident': __( 'Lives with me', 'blockroll' ),
	neighbor: __( 'Neighbor', 'blockroll' ),
	child: __( 'Child', 'blockroll' ),
	parent: __( 'Parent', 'blockroll' ),
	sibling: __( 'Sibling', 'blockroll' ),
	spouse: __( 'Spouse', 'blockroll' ),
	kin: __( 'Relative', 'blockroll' ),
	muse: __( 'Muse', 'blockroll' ),
	crush: __( 'Crush', 'blockroll' ),
	date: __( 'Dating', 'blockroll' ),
	sweetheart: __( 'Sweetheart', 'blockroll' ),
};

/**
 * Grouped XFN relationship picker.
 *
 * @param {Object}   props          Component props.
 * @param {string[]} props.value    Selected tokens.
 * @param {Function} props.onChange Change handler.
 */
export default function XfnControl( { value = [], onChange } ) {
	const isMe = value.includes( 'me' );

	return (
		<div className="blockroll-xfn-control">
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'This is me', 'blockroll' ) }
				help={ __(
					'Another site of your own. Replaces all other relationships.',
					'blockroll'
				) }
				checked={ isMe }
				onChange={ ( checked ) => onChange( checked ? [ 'me' ] : [] ) }
			/>
			{ ! isMe &&
				Object.entries( GROUP_LABELS ).map( ( [ group, label ] ) =>
					EXCLUSIVE.includes( group ) ? (
						<RadioControl
							key={ group }
							label={ label }
							selected={
								value.find( ( t ) =>
									GROUPS[ group ].includes( t )
								) || ''
							}
							options={ [
								{ label: __( 'None', 'blockroll' ), value: '' },
								...GROUPS[ group ].map( ( token ) => ( {
									label: TOKEN_LABELS[ token ],
									value: token,
								} ) ),
							] }
							onChange={ ( token ) => {
								let next = value.filter(
									( t ) => ! GROUPS[ group ].includes( t )
								);
								if ( token ) {
									next = applyXfnToken( next, token, true );
								}
								onChange( next );
							} }
						/>
					) : (
						<fieldset key={ group }>
							<legend>{ label }</legend>
							{ GROUPS[ group ].map( ( token ) => (
								<CheckboxControl
									__nextHasNoMarginBottom
									key={ token }
									label={ TOKEN_LABELS[ token ] }
									checked={ value.includes( token ) }
									onChange={ ( checked ) =>
										onChange(
											applyXfnToken(
												value,
												token,
												checked
											)
										)
									}
								/>
							) ) }
						</fieldset>
					)
				) }
		</div>
	);
}
