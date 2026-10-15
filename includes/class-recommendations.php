<?php
/**
 * The well-known recommendations of the site.
 *
 * @package Blockroll
 */

namespace Blockroll;

/**
 * The recommendations at /.well-known/recommendations.opml.
 *
 * For now the same file as the Directory at the site root. A class of its
 * own, so the two can go separate ways.
 */
class Recommendations {
	/**
	 * Path of the well-known recommendations, without the leading slash.
	 *
	 * See https://opml.org/
	 */
	const PATH = '.well-known/recommendations.opml';

	/**
	 * Value of the `opml` query var that asks for the recommendations.
	 */
	const QUERY_VALUE = 'recommendations';

	/**
	 * Print the recommendations.
	 *
	 * @param \WP_Post[]|null $posts Blogroll posts, or null to look them up.
	 */
	public static function render( $posts = null ) {
		Directory::render( $posts );
	}

	/**
	 * Serve the recommendations, and exit.
	 *
	 * @param \WP_Post[] $posts Blogroll posts.
	 */
	public static function serve( $posts ) {
		\header( 'Content-Type: text/xml; charset=' . \get_option( 'blog_charset' ) );
		self::render( $posts );
		exit;
	}
}
