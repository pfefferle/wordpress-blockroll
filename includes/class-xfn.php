<?php
/**
 * XFN token vocabulary and rel helper.
 *
 * @package Blockroll
 */

namespace Blockroll;

/**
 * XFN relationship tokens, grouped per the XFN 1.1 profile.
 */
class Xfn {
	const GROUPS = array(
		'friendship'   => array( 'friend', 'acquaintance', 'contact' ),
		'physical'     => array( 'met' ),
		'professional' => array( 'co-worker', 'colleague' ),
		'geographical' => array( 'co-resident', 'neighbor' ),
		'family'       => array( 'child', 'parent', 'sibling', 'spouse', 'kin' ),
		'romantic'     => array( 'muse', 'crush', 'date', 'sweetheart' ),
		'identity'     => array( 'me' ),
	);

	const EXCLUSIVE = array( 'friendship', 'geographical', 'family' );

	/**
	 * Whitelist tokens against the XFN vocabulary and enforce group rules.
	 *
	 * @param array $tokens Raw tokens.
	 * @return array Clean tokens.
	 */
	public static function sanitize( $tokens ) {
		$tokens = \array_values( \array_intersect( (array) $tokens, \array_merge( ...\array_values( self::GROUPS ) ) ) );
		if ( \in_array( 'me', $tokens, true ) ) {
			// XFN: "me" replaces every other value.
			return array( 'me' );
		}
		foreach ( self::EXCLUSIVE as $group ) {
			$found = \array_intersect( $tokens, self::GROUPS[ $group ] );
			if ( \count( $found ) > 1 ) {
				\array_shift( $found ); // Keep the first, drop the rest.
				$tokens = \array_values( \array_diff( $tokens, $found ) );
			}
		}
		return \array_values( \array_unique( $tokens ) );
	}

	/**
	 * Build a rel attribute value from tokens.
	 *
	 * @param array $tokens Raw tokens.
	 * @return string Space separated rel value.
	 */
	public static function rel_string( $tokens ) {
		return \implode( ' ', self::sanitize( $tokens ) );
	}
}
