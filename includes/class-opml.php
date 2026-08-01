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
		\add_filter( 'feed_content_type', array( self::class, 'content_type' ), 10, 2 );
		\add_filter( 'pre_handle_404', array( self::class, 'pre_handle_404' ), 10, 2 );
		\add_action( 'wp_head', array( self::class, 'discovery_link' ) );
		foreach ( array( 'rss2', 'atom' ) as $feed ) {
			\add_action( $feed . '_ns', 'ob_start', 1 );
			\add_action( $feed . '_ns', array( self::class, 'feed_namespace' ), PHP_INT_MAX );
			\add_action( $feed . '_head', array( self::class, 'feed_blogroll' ) );
		}
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
				\esc_url( self::feed_url( $post ) )
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
	 * OPML for a single post's blogroll.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string OPML document.
	 */
	public static function for_post( $post ) {
		\ob_start();
		\load_template(
			\dirname( BLOCKROLL_PLUGIN_FILE ) . '/templates/opml.php',
			false,
			array(
				'post'  => $post,
				'links' => self::extract_links( $post ),
			)
		);
		return \ob_get_clean();
	}

	/**
	 * Directory OPML listing every blogroll page's own OPML.
	 *
	 * @return string OPML document.
	 */
	public static function directory() {
		\ob_start();
		\load_template(
			\dirname( BLOCKROLL_PLUGIN_FILE ) . '/templates/opml-directory.php',
			false,
			array(
				'posts' => Index::get_posts(),
			)
		);
		return \ob_get_clean();
	}

	/**
	 * Feed callback for /feed/opml.
	 *
	 * The Content-Type is sent by WP::send_headers(), based on the
	 * feed_content_type filter.
	 */
	public static function render() {
		if ( \is_singular() ) {
			echo self::for_post( \get_queried_object() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo self::directory(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Content type of the opml feed.
	 *
	 * @param string $content_type Current content type.
	 * @param string $type         Feed type.
	 * @return string Content type.
	 */
	public static function content_type( $content_type, $type ) {
		return 'opml' === $type ? 'text/xml' : $content_type;
	}

	/**
	 * Turn OPML requests without a blogroll into a regular 404.
	 *
	 * Runs on pre_handle_404, before WP::send_headers(), so the request
	 * gets the theme's 404 page with normal 404 headers instead of feed
	 * headers, and conditional requests are not answered with a 304.
	 *
	 * @param bool      $handled  Whether the 404 was already handled.
	 * @param \WP_Query $wp_query The main query.
	 * @return bool True when this request was turned into a 404.
	 */
	public static function pre_handle_404( $handled, $wp_query ) {
		if ( ! \is_feed( 'opml' ) ) {
			return $handled;
		}

		if ( \is_singular() ) {
			$post = \get_queried_object();
			if ( $post && \has_block( 'blockroll/blogroll', $post ) ) {
				return $handled;
			}
		} elseif ( Index::get_posts() ) {
			return $handled;
		}

		global $wp;
		$wp_query->set_404();
		// The theme's 404 page should render, not a feed: clear the flag
		// for the template loader and the query var for WP::send_headers(),
		// which would otherwise send feed headers and answer conditional
		// requests with a 304.
		$wp_query->is_feed = false;
		unset( $wp->query_vars['feed'] );
		// Without the feed query var, redirect_canonical() would guess a
		// permalink for the 404 and redirect to the page itself.
		\add_filter( 'do_redirect_guess_404_permalink', '__return_false' );
		\status_header( 404 );
		\nocache_headers();

		return true;
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
			$post = \get_queried_object();
			if ( $post && \has_block( 'blockroll/blogroll', $post ) ) {
				self::print_discovery_link( $post );
			}
			return;
		}

		if ( \is_front_page() || \is_home() ) {
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
			\esc_url( self::feed_url( $post ) ),
			\esc_attr( self::title( $post ) )
		);
	}
}
