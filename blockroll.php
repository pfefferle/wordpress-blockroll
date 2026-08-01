<?php
/**
 * Plugin Name: Blockroll
 * Description: A blogroll block: share a list of the blogs and sites you follow.
 * Version: 0.1.0
 * Requires PHP: 7.4
 * Author: Matthias Pfefferle
 * License: GPL-2.0-or-later
 * Text Domain: blockroll
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
		$path  = $parts ? \implode( '/', $parts ) . '/' : '';
		$file  = __DIR__ . '/includes/' . $path . 'class-' . $name . '.php';
		if ( \file_exists( $file ) ) {
			require $file;
		}
	}
);

/**
 * Initialize the plugin.
 */
function init() {
	Index::register();
	Opml::register();
	\register_block_type( __DIR__ . '/build/blogroll' );

	if ( \get_option( 'blockroll_flush_rewrite_rules' ) ) {
		\delete_option( 'blockroll_flush_rewrite_rules' );
		\flush_rewrite_rules();
	}
}
\add_action( 'init', __NAMESPACE__ . '\init' );

\add_filter(
	'query_vars',
	function ( $vars ) {
		// Frontend sorting and paging of the blogroll block.
		$vars[] = 'blockroll-sort';
		$vars[] = 'blockroll-page';
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

\register_activation_hook(
	__FILE__,
	function () {
		// The opml feed adds a rewrite rule; flush once on the init after activation.
		\update_option( 'blockroll_flush_rewrite_rules', 1 );
	}
);

\register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
