<?php
/**
 * Plugin Name: Blockroll
 * Description: A blogroll block with XFN, h-cards, OPML import/export, and blogroll discovery.
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
		$file = __DIR__ . '/includes/class-' . \strtolower( \str_replace( array( 'Blockroll\\', '_' ), array( '', '-' ), $class ) ) . '.php';
		if ( \file_exists( $file ) ) {
			require $file;
		}
	}
);

/**
 * Initialize the plugin.
 */
function init() {
	\register_block_type( __DIR__ . '/build/blogroll' );
}
\add_action( 'init', __NAMESPACE__ . '\init' );
