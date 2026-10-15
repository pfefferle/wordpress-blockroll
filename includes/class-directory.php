<?php
/**
 * The directory of all blogrolls of the site.
 *
 * @package Blockroll
 */

namespace Blockroll;

/**
 * The directory at the site root, `/?opml`: one entry per listed
 * blogroll, pointing to its file.
 */
class Directory {
	/**
	 * Value of the `opml` query var that asks for the directory.
	 *
	 * A bare `?opml` at the site root asks for it as well.
	 */
	const QUERY_VALUE = 'directory';

	/**
	 * Print the directory OPML listing each listed blogroll's OPML.
	 *
	 * @param \WP_Post[]|null $posts Blogroll posts, or null to look them up.
	 */
	public static function render( $posts = null ) {
		\load_template(
			\dirname( BLOCKROLL_PLUGIN_FILE ) . '/templates/opml-directory.php',
			false,
			array( 'posts' => null === $posts ? Index::get_posts() : $posts )
		);
	}

	/**
	 * Serve the directory, and exit.
	 *
	 * @param \WP_Post[] $posts Blogroll posts.
	 */
	public static function serve( $posts ) {
		\header( 'Content-Type: text/xml; charset=' . \get_option( 'blog_charset' ) );
		// static, so a file that extends this one serves its own render().
		static::render( $posts );
		exit;
	}
}
