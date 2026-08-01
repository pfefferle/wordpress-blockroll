/**
 * XFN vocabulary and token rules, mirror of the PHP Xfn class.
 *
 * Kept free of WordPress imports so it is plain-unit-testable.
 */

export const GROUPS = {
	friendship: [ 'friend', 'acquaintance', 'contact' ],
	physical: [ 'met' ],
	professional: [ 'co-worker', 'colleague' ],
	geographical: [ 'co-resident', 'neighbor' ],
	family: [ 'child', 'parent', 'sibling', 'spouse', 'kin' ],
	romantic: [ 'muse', 'crush', 'date', 'sweetheart' ],
	identity: [ 'me' ],
};

export const EXCLUSIVE = [ 'friendship', 'geographical', 'family' ];

/**
 * Apply a token change to a token list, honoring the group rules.
 *
 * @param {string[]} tokens Current tokens.
 * @param {string}   token  Token to add or remove.
 * @param {boolean}  add    True to add, false to remove.
 * @return {string[]} New token list.
 */
export function applyXfnToken( tokens, token, add ) {
	if ( ! add ) {
		return tokens.filter( ( t ) => t !== token );
	}
	if ( token === 'me' ) {
		// "me" replaces every other value.
		return [ 'me' ];
	}
	let next = tokens.filter( ( t ) => t !== 'me' );
	for ( const group of EXCLUSIVE ) {
		if ( GROUPS[ group ].includes( token ) ) {
			next = next.filter( ( t ) => ! GROUPS[ group ].includes( t ) );
		}
	}
	return [ ...next, token ];
}
