<?php
/**
 * OPML output and blogroll discovery link.
 *
 * @package Blockroll
 */

namespace Blockroll;

/**
 * Serve OPML for blogroll pages and a directory of them at the site root
 * and at /.well-known/recommendations.opml.
 */
class Opml {
	/**
	 * Path of the well-known directory OPML, without the leading slash.
	 *
	 * See https://opml.org/
	 */
	const WELL_KNOWN = '.well-known/recommendations.opml';

	/**
	 * Value of the `opml` query var that always asks for the directory.
	 */
	const DIRECTORY = 'directory';

	/**
	 * Register the OPML output and the discovery link.
	 *
	 * The `opml` query var itself is declared with the plugin's other
	 * public query vars in blockroll.php.
	 */
	public static function register() {
		self::add_rewrite_rules();
		\add_action( 'template_redirect', array( self::class, 'render' ) );
		\add_action( 'wp_head', array( self::class, 'discovery_link' ) );
		foreach ( array( 'rss2', 'atom' ) as $feed ) {
			\add_action( $feed . '_ns', 'ob_start', 1 );
			\add_action( $feed . '_ns', array( self::class, 'feed_namespace' ), PHP_INT_MAX );
			\add_action( $feed . '_head', array( self::class, 'feed_blogroll' ) );
		}
	}

	/**
	 * Map the well-known URL to the directory OPML.
	 *
	 * Also called on activation, before the rules are flushed. Unlike the
	 * `?opml` query var, this path has no page to fall back to, so it needs
	 * a rule of its own.
	 */
	public static function add_rewrite_rules() {
		\add_rewrite_rule(
			// WordPress matches rewrite rules with "#" as the delimiter.
			\sprintf( '^%s$', \preg_quote( self::WELL_KNOWN, '#' ) ),
			\sprintf( 'index.php?opml=%s', self::DIRECTORY ),
			'top'
		);
	}

	/**
	 * Add the source namespace to a feed, unless another plugin or
	 * theme already did.
	 *
	 * See https://source.scripting.com/
	 */
	public static function feed_namespace() {
		$namespaces = \ob_get_clean();
		if ( false === \strpos( $namespaces, 'xmlns:source' ) ) {
			$namespaces .= ' xmlns:source="http://source.scripting.com/" ';
		}
		echo $namespaces; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Advertise the blogroll OPMLs in the feed head.
	 */
	public static function feed_blogroll() {
		foreach ( Index::get_posts() as $post ) {
			\printf(
				'<source:blogroll>%s</source:blogroll>' . PHP_EOL,
				\esc_url( self::opml_url( $post ) )
			);
		}
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
	 * Print the OPML for a single post's blogroll.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public static function for_post( $post ) {
		\load_template(
			\dirname( BLOCKROLL_PLUGIN_FILE ) . '/templates/opml.php',
			false,
			array(
				'post'  => $post,
				'links' => self::extract_links( $post ),
			)
		);
	}

	/**
	 * Print the directory OPML listing every blogroll page's own OPML.
	 *
	 * @param \WP_Post[]|null $posts Blogroll posts, or null to look them up.
	 */
	public static function directory( $posts = null ) {
		\load_template(
			\dirname( BLOCKROLL_PLUGIN_FILE ) . '/templates/opml-directory.php',
			false,
			array( 'posts' => null === $posts ? Index::get_posts() : $posts )
		);
	}

	/**
	 * Print the XML prolog and stylesheet line of an OPML document.
	 */
	public static function prolog() {
		echo '<?xml version="1.0" encoding="' . \esc_attr( \get_option( 'blog_charset' ) ) . '"?>' . "\n";
		\printf(
			'<?xml-stylesheet type="text/xsl" href="%s"?>' . "\n",
			\esc_url( \plugins_url( 'templates/opml.xsl', BLOCKROLL_PLUGIN_FILE ) )
		);
	}

	/**
	 * The queried post, when it has a blogroll.
	 *
	 * @return \WP_Post|null Post with a blogroll block, or null.
	 */
	private static function blogroll_post() {
		if ( ! \is_singular() ) {
			return null;
		}

		$post = \get_queried_object();

		return Index::has_blogroll( $post ) ? $post : null;
	}

	/**
	 * Whether this request is the site root, where the directory lives.
	 *
	 * @return bool True on the front page or the blog home.
	 */
	private static function is_blogroll_root() {
		return \is_front_page() || \is_home();
	}

	/**
	 * Serve the opml request.
	 *
	 * Without a blogroll the query var is simply ignored and the normal
	 * page loads, just like when the plugin is disabled.
	 */
	public static function render() {
		// A bare ?opml parses to an empty string, so test presence, not value.
		$opml = \get_query_var( 'opml', null );
		if ( null === $opml ) {
			return;
		}

		// The well-known URL asks for the directory, whatever page it lands on.
		$directory = self::DIRECTORY === $opml;
		$post      = $directory ? null : self::blogroll_post();
		$posts     = ( ! $post && ( $directory || self::is_blogroll_root() ) ) ? Index::get_posts() : array();

		if ( ! $post && ! $posts ) {
			return;
		}

		\header( 'Content-Type: text/xml; charset=' . \get_option( 'blog_charset' ) );
		if ( $post ) {
			self::for_post( $post );
		} else {
			self::directory( $posts );
		}
		exit;
	}

	/**
	 * The OPML URL of a post.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string OPML URL.
	 */
	public static function opml_url( $post ) {
		return \add_query_arg( 'opml', '', \get_permalink( $post ) );
	}

	/**
	 * Title of a blogroll page: page title plus author.
	 *
	 * Falls back to "Blogroll" for untitled posts.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string Title.
	 */
	public static function title( $post ) {
		$title = \get_the_title( $post );
		if ( '' === $title ) {
			$title = \__( 'Blogroll', 'blockroll' );
		}

		$author = \get_the_author_meta( 'display_name', $post->post_author );
		if ( $author ) {
			/* translators: 1: page title, 2: author name */
			$title = \sprintf( \__( '%1$s by %2$s', 'blockroll' ), $title, $author );
		}

		return $title;
	}

	/**
	 * Print rel="blogroll" discovery links.
	 *
	 * A page that contains the block advertises its own OPML. The front
	 * page advertises the OPML of every blogroll page, so readers can
	 * find the blogroll from the homepage. The root directory OPML is a
	 * list of OPMLs, not a blogroll, so it is never advertised.
	 */
	public static function discovery_link() {
		if ( \is_singular() ) {
			$post = self::blogroll_post();
			if ( $post ) {
				self::print_discovery_link( $post );
			}
			return;
		}

		if ( self::is_blogroll_root() ) {
			foreach ( Index::get_posts() as $post ) {
				self::print_discovery_link( $post );
			}
		}
	}

	/**
	 * Print one rel="blogroll" link.
	 *
	 * @param \WP_Post $post Post with a blogroll block.
	 */
	private static function print_discovery_link( $post ) {
		\printf(
			'<link rel="blogroll" type="text/xml" href="%s" title="%s" />' . "\n",
			\esc_url( self::opml_url( $post ) ),
			\esc_attr( self::title( $post ) )
		);
	}
}
