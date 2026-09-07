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
	 * Output buffer level of the feed namespace buffer, 0 if not buffering.
	 *
	 * @var int
	 */
	private static $namespace_level = 0;

	/**
	 * Register the OPML output and the discovery link.
	 *
	 * The `opml` query var itself is declared with the plugin's other
	 * public query vars in blockroll.php.
	 */
	public static function register() {
		self::add_rewrite_rules();
		// Before redirect_canonical, which would send the well-known URL to a
		// trailing-slash version of itself.
		\add_action( 'template_redirect', array( self::class, 'render' ), 9 );
		\add_action( 'wp_head', array( self::class, 'discovery_link' ) );
		foreach ( array( 'rss2', 'atom' ) as $feed ) {
			\add_action( $feed . '_ns', array( self::class, 'feed_namespace_start' ), 1 );
			\add_action( $feed . '_ns', array( self::class, 'feed_namespace' ), PHP_INT_MAX );
			\add_action( $feed . '_head', array( self::class, 'feed_blogroll' ) );
		}
	}

	/**
	 * Map the well-known URL to the directory OPML and `.opml` to `?opml`.
	 *
	 * Also called on activation, before the rules are flushed. Unlike the
	 * `?opml` query var, the well-known path has no page to fall back to,
	 * so it needs a rule of its own.
	 */
	public static function add_rewrite_rules() {
		\add_rewrite_rule(
			// WordPress matches rewrite rules with "#" as the delimiter.
			\sprintf( '^%s$', \preg_quote( self::WELL_KNOWN, '#' ) ),
			\sprintf( 'index.php?opml=%s', self::DIRECTORY ),
			'top'
		);
		// A `.opml` suffix as an alias for `?opml` on pages, so the URL
		// looks like a file: /blogroll.opml. The query var stays the
		// canonical form; it also works with plain permalinks.
		// This rule matches the well-known path too. Rules keep the order
		// they were added in, so the well-known rule above has to stay
		// above; the well-known test covers that.
		\add_rewrite_rule(
			'^(.+?)\\.opml$',
			'index.php?pagename=$matches[1]&opml=',
			'top'
		);
	}

	/**
	 * Start buffering the namespaces other plugins and themes print.
	 *
	 * A method rather than `ob_start` itself: `do_action()` passes an empty
	 * string to every callback, and `ob_start( '' )` is a PHP warning.
	 */
	public static function feed_namespace_start() {
		if ( \ob_start() ) {
			self::$namespace_level = \ob_get_level();
		}
	}

	/**
	 * Add the source namespace to a feed, unless another plugin or
	 * theme already did.
	 *
	 * See https://source.scripting.com/
	 */
	public static function feed_namespace() {
		$namespaces = '';

		// Only close the buffer this class opened. Somebody else's buffer,
		// a caching plugin's for example, has to stay untouched.
		if ( self::$namespace_level && \ob_get_level() === self::$namespace_level ) {
			$namespaces            = (string) \ob_get_clean();
			self::$namespace_level = 0;
		}

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
		$links = array();
		foreach ( self::extract_groups( $post ) as $group ) {
			$links = \array_merge( $links, $group['links'] );
		}
		return $links;
	}

	/**
	 * Collect the blogroll blocks of a post, each with its own name and links.
	 *
	 * The name is the one WordPress keeps when a block is renamed in the
	 * editor, so a page with several blogrolls can say what each one is
	 * without the block needing a title of its own.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array List of arrays with a "name" and a "links" key.
	 */
	public static function extract_groups( $post ) {
		$groups = array();
		$walker = function ( $blocks ) use ( &$walker, &$groups ) {
			foreach ( $blocks as $block ) {
				if ( 'blockroll/blogroll' === $block['blockName'] ) {
					$links = array();
					foreach ( (array) ( $block['attrs']['links'] ?? array() ) as $link ) {
						$link = Links::normalize( $link );
						if ( $link['url'] ) {
							$links[] = $link;
						}
					}
					if ( $links ) {
						$groups[] = array(
							'name'  => \sanitize_text_field( (string) ( $block['attrs']['metadata']['name'] ?? '' ) ),
							'links' => $links,
						);
					}
				}
				if ( ! empty( $block['innerBlocks'] ) ) {
					$walker( $block['innerBlocks'] );
				}
			}
		};
		$walker( \parse_blocks( $post->post_content ) );
		return $groups;
	}

	/**
	 * Build the outline elements for a page's blogrolls.
	 *
	 * Several blogrolls on one page become groups, named after the block, so
	 * a reader can keep them apart. A single blogroll stays a plain list, and
	 * so do blogrolls that were never given a name.
	 *
	 * @param array $groups Groups as returned by extract_groups().
	 * @return string The escaped elements.
	 */
	public static function outlines( $groups ) {
		$grouped = \count( $groups ) > 1;
		$lines   = array();

		foreach ( $groups as $group ) {
			if ( $grouped && $group['name'] ) {
				$lines[] = "\t\t" . '<outline text="' . \esc_attr( $group['name'] ) . '">';
				foreach ( $group['links'] as $link ) {
					$lines[] = self::link_outline( $link, "\t\t\t" );
				}
				$lines[] = "\t\t" . '</outline>';
				continue;
			}

			foreach ( $group['links'] as $link ) {
				$lines[] = self::link_outline( $link );
			}
		}

		return $lines ? \implode( "\n", $lines ) . "\n" : '';
	}

	/**
	 * Build the outline element of a single link.
	 *
	 * @param array  $link   Normalized link.
	 * @param string $indent Leading whitespace.
	 * @return string The escaped element.
	 */
	private static function link_outline( $link, $indent = "\t\t" ) {
		$attributes = \sprintf( ' text="%s" type="rss"', \esc_attr( $link['name'] ? $link['name'] : $link['url'] ) );
		if ( $link['description'] ) {
			$attributes .= \sprintf( ' description="%s"', \esc_attr( $link['description'] ) );
		}
		if ( $link['feedUrl'] ) {
			$attributes .= \sprintf( ' xmlUrl="%s"', \esc_url( $link['feedUrl'] ) );
		}
		$attributes .= \sprintf( ' htmlUrl="%s"', \esc_url( $link['url'] ) );

		return $indent . '<outline' . $attributes . ' />';
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
				'post'   => $post,
				'groups' => self::extract_groups( $post ),
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
		$post = self::blogroll_post();
		if ( $post ) {
			self::print_discovery_link( $post );
		}

		// A static front page is singular as well, so it gets both: its own
		// blogroll, if it has one, and the ones on the other pages.
		if ( ! self::is_blogroll_root() ) {
			return;
		}

		foreach ( Index::get_posts() as $blogroll ) {
			if ( $post && $post->ID === $blogroll->ID ) {
				continue;
			}
			self::print_discovery_link( $blogroll );
		}
	}

	/**
	 * Print the rel="blogroll" links of one page: the OPML and the page
	 * itself, so readers that want the HTML rather than the file find
	 * it too.
	 *
	 * @param \WP_Post $post Post with a blogroll block.
	 */
	private static function print_discovery_link( $post ) {
		$title = self::title( $post );
		\printf(
			'<link rel="blogroll" type="text/xml" href="%s" title="%s" />' . "\n",
			\esc_url( self::opml_url( $post ) ),
			\esc_attr( $title )
		);
		\printf(
			'<link rel="blogroll" type="text/html" href="%s" title="%s" />' . "\n",
			\esc_url( \get_permalink( $post ) ),
			\esc_attr( $title )
		);
	}
}
