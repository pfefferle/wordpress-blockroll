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
	/**
	 * The XFN 1.1 profile URI.
	 */
	const PROFILE = 'https://gmpg.org/xfn/11';

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
	 * Register the profile link.
	 */
	public static function register() {
		\add_action( 'wp_head', array( self::class, 'profile_link' ) );
	}

	/**
	 * Print the XFN profile link on every page.
	 *
	 * XFN values are not limited to the blogroll, themes use them in menus
	 * and on comment author links, so the profile belongs on the whole
	 * site. Some themes print it too. A second one does no harm, the
	 * profiles of a document are a set, and there is no way to know what
	 * the theme does before it does it.
	 */
	public static function profile_link() {
		\printf( '<link rel="profile" href="%s" />' . PHP_EOL, \esc_url( self::PROFILE ) );
	}

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
