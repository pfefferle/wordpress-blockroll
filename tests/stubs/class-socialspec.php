<?php
/**
 * Stand-in for the SocialSpec plugin.
 *
 * @package Blockroll
 */

namespace Socialspec; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound -- The namespace of the plugin it stands in for.

/**
 * SocialSpec with no documents, unless a test sets some.
 */
class Socialspec {
	/**
	 * The documents, keyed by their file name in /.well-known/.
	 *
	 * @var array
	 */
	public static $documents = array();

	/**
	 * The documents.
	 *
	 * @return array The documents.
	 */
	public static function get_documents() {
		return self::$documents;
	}
}
