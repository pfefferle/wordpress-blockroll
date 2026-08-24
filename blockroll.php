<?php
/**
 * Plugin Name: Blockroll
 * Description: A blogroll block: share a list of the blogs and sites you follow.
 * Version: 0.1.0
 * Requires PHP: 7.4
 * Author: Matthias Pfefferle
 * License: GPL-2.0-or-later
 * Text Domain: blockroll
 * Update URI: https://github.com/pfefferle/wordpress-blockroll
 *
 * @package Blockroll
 */

namespace Blockroll;

defined( 'ABSPATH' ) || exit;

define( 'BLOCKROLL_PLUGIN_FILE', __FILE__ );

\spl_autoload_register(
	function ( $class ) {
		if ( 0 !== \strpos( $class, 'Blockroll\\' ) ) {
			return;
		}
		$parts = \explode( '\\', \strtolower( \str_replace( array( 'Blockroll\\', '_' ), array( '', '-' ), $class ) ) );
		$name  = \array_pop( $parts );
		$path  = __DIR__ . '/includes/' . ( $parts ? \implode( '/', $parts ) . '/' : '' );
		foreach ( array( 'class-', 'trait-' ) as $prefix ) {
			if ( \file_exists( $path . $prefix . $name . '.php' ) ) {
				require $path . $prefix . $name . '.php';
				return;
			}
		}
	}
);

/**
 * Initialize the plugin.
 */
function init() {
	Index::register();
	Opml::register();
	Xfn::register();
	\register_block_type( __DIR__ . '/build/blogroll' );
}
\add_action( 'init', __NAMESPACE__ . '\init' );

\add_filter(
	'query_vars',
	function ( $vars ) {
		// Frontend sorting and paging of the blogroll block.
		$vars[] = 'blockroll-sort';
		$vars[] = 'blockroll-page';
		// OPML output. A query var rather than a rewrite endpoint: no rewrite
		// flush, and when the plugin is disabled the URL falls back to the
		// page itself instead of a 404.
		$vars[] = 'opml';
		return $vars;
	}
);

\add_action(
	'rest_api_init',
	function () {
		( new Rest\Discovery_Controller() )->register_routes();
		( new Rest\Import_Controller() )->register_routes();
	}
);
