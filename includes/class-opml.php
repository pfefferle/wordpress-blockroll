<?php
/**
 * OPML feed and blogroll discovery link.
 *
 * @package Blockroll
 */

namespace Blockroll;

/**
 * Serve OPML for blogroll pages and a directory of them at the site root.
 */
class Opml {
	/**
	 * Register the feed and the discovery link.
	 */
	public static function register() {
		\add_feed( 'opml', array( self::class, 'render' ) );
		\add_action( 'wp_head', array( self::class, 'discovery_link' ) );
	}

	/**
	 * Collect normalized links from all blogroll blocks in a post.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array Normalized links.
	 */
	public static function extract_links( $post ) {
		$links  = array();
		$walker = function ( $blocks ) use ( &$walker, &$links ) {
			foreach ( $blocks as $block ) {
				if ( 'blockroll/blogroll' === $block['blockName'] ) {
					foreach ( (array) ( $block['attrs']['links'] ?? array() ) as $link ) {
						$link = Links::normalize( $link );
						if ( $link['url'] ) {
							$links[] = $link;
						}
					}
				}
				if ( ! empty( $block['innerBlocks'] ) ) {
					$walker( $block['innerBlocks'] );
				}
			}
		};
		$walker( \parse_blocks( $post->post_content ) );
		return $links;
	}

	/**
	 * OPML for a single post's blogroll.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string OPML document.
	 */
	public static function for_post( $post ) {
		$blockroll_links = self::extract_links( $post );
		\ob_start();
		require \dirname( BLOCKROLL_PLUGIN_FILE ) . '/templates/opml.php';
		return \ob_get_clean();
	}

	/**
	 * Directory OPML listing every blogroll page's own OPML.
	 *
	 * @return string OPML document.
	 */
	public static function directory() {
		$blockroll_posts = Index::get_posts();
		\ob_start();
		require \dirname( BLOCKROLL_PLUGIN_FILE ) . '/templates/opml-directory.php';
		return \ob_get_clean();
	}

	/**
	 * Feed callback for /feed/opml.
	 */
	public static function render() {
		\header( 'Content-Type: text/xml; charset=' . \get_option( 'blog_charset' ) );
		if ( \is_singular() ) {
			echo self::for_post( \get_queried_object() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo self::directory(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * The OPML feed URL of a post.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string Feed URL.
	 */
	public static function feed_url( $post ) {
		return \trailingslashit( \get_permalink( $post ) ) . 'feed/opml';
	}

	/**
	 * Print the rel="blogroll" link on singular pages that contain the block.
	 *
	 * The root directory is a list of OPMLs, not a blogroll, so it is
	 * never advertised this way.
	 */
	public static function discovery_link() {
		if ( ! \is_singular() ) {
			return;
		}
		$post = \get_queried_object();
		if ( ! $post || ! \has_block( 'blockroll/blogroll', $post ) ) {
			return;
		}
		\printf(
			'<link rel="blogroll" type="text/xml" href="%s" title="%s" />' . "\n",
			\esc_url( self::feed_url( $post ) ),
			/* translators: %s: post title */
			\esc_attr( \sprintf( \__( 'Blogroll: %s', 'blockroll' ), \get_the_title( $post ) ) )
		);
	}
}
