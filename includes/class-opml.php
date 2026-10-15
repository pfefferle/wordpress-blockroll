<?php
/**
 * OPML output and blogroll discovery link.
 *
 * @package Blockroll
 */

namespace Blockroll;

/**
 * Serve OPML for blogroll pages and a directory of them at the site root
 * and at /.well-known/recommendations.opml, and their feeds at
 * /.well-known/following.opml.
 */
class Opml {
	/**
	 * Path of the well-known directory OPML, without the leading slash.
	 *
	 * See https://opml.org/
	 */
	const RECOMMENDATIONS_PATH = '.well-known/recommendations.opml';

	/**
	 * Path of the well-known following list, without the leading slash.
	 *
	 * See https://followspec.org/
	 */
	const FOLLOWING_PATH = '.well-known/following.opml';

	/**
	 * Query var that asks for the OPML of a page instead of its HTML.
	 *
	 * Declared with the plugin's other public query vars in blockroll.php.
	 */
	const QUERY_VAR = 'opml';

	/**
	 * Value of the `opml` query var that always asks for the directory.
	 */
	const DIRECTORY = 'directory';

	/**
	 * Value of the `opml` query var that always asks for the following list.
	 */
	const FOLLOWING = 'following';

	/**
	 * Query var that picks one blogroll of a page by its HTML anchor.
	 *
	 * A var of its own next to `opml`, so the address reads as a file with
	 * a parameter: /links.opml?group=podcasts. It only has a meaning next
	 * to `opml`, so the plain name is safe.
	 */
	const GROUP = 'group';

	/**
	 * Output buffer level of the feed namespace buffer, 0 if not buffering.
	 *
	 * @var int
	 */
	private static $namespace_level = 0;

	/**
	 * Register the OPML output and the discovery link.
	 *
	 * The query vars themselves are declared with the plugin's other
	 * public query vars in blockroll.php.
	 */
	public static function register() {
		self::add_rewrite_rules();
		// After `init`, when every plugin has added its rules.
		\add_action( 'wp_loaded', array( self::class, 'flush_stale_rewrite_rules' ) );
		// Before redirect_canonical, which would send the well-known URLs to
		// a trailing-slash version of themselves.
		\add_action( 'template_redirect', array( self::class, 'render' ), 9 );
		\add_action( 'wp_head', array( self::class, 'discovery_link' ) );
		\add_filter( 'socialspec_following', array( self::class, 'socialspec_following' ) );
		foreach ( array( 'rss2', 'atom' ) as $feed ) {
			\add_action( $feed . '_ns', array( self::class, 'feed_namespace_start' ), 1 );
			\add_action( $feed . '_ns', array( self::class, 'feed_namespace' ), PHP_INT_MAX );
			\add_action( $feed . '_head', array( self::class, 'feed_blogroll' ) );
		}
	}

	/**
	 * Map the well-known URLs to the directory and the following list,
	 * and `.opml` to `?opml`.
	 *
	 * Also called on activation, before the rules are flushed. Unlike the
	 * `?opml` query var, the well-known paths have no page to fall back
	 * to, so they need rules of their own.
	 */
	public static function add_rewrite_rules() {
		foreach ( \array_filter( self::well_known_rules() ) as $regex => $query ) {
			\add_rewrite_rule( $regex, $query, 'top' );
		}
		// A `.opml` suffix as an alias for `?opml` on pages, so the URL
		// looks like a file: /blogroll.opml. The query var stays the
		// canonical form; it also works with plain permalinks.
		// This rule matches the well-known path too. Rules keep the order
		// they were added in, so the well-known rules above have to stay
		// above; the well-known tests cover that.
		\add_rewrite_rule(
			'^(.+?)\\.opml$',
			\sprintf( 'index.php?pagename=$matches[1]&%s=', self::QUERY_VAR ),
			'top'
		);
	}

	/**
	 * The rewrite rules of the well-known URLs.
	 *
	 * @return array Regex => query, or => null for a URL this plugin
	 *               does not serve on this site.
	 */
	private static function well_known_rules() {
		$well_known = array(
			self::RECOMMENDATIONS_PATH => self::DIRECTORY,
			self::FOLLOWING_PATH       => self::FOLLOWING,
		);

		$rules = array();
		foreach ( $well_known as $path => $value ) {
			// WordPress matches rewrite rules with "#" as the delimiter.
			$regex = \sprintf( '^%s$', \preg_quote( $path, '#' ) );
			// With the SocialSpec plugin (https://socialspec.org/), its
			// following.opml lists the blogroll feeds.
			$rules[ $regex ] = ( self::FOLLOWING === $value && \defined( 'SOCIALSPEC_VERSION' ) )
				? null
				: \sprintf( 'index.php?%s=%s', self::QUERY_VAR, $value );
		}
		return $rules;
	}

	/**
	 * Flush the rewrite rules when the stored well-known rules are out of
	 * date.
	 *
	 * Activation flushes, an update does not, so a new well-known URL
	 * would stay a 404 on existing sites. The rules also change when
	 * SocialSpec is activated or deactivated. Compared with the stored
	 * rules, so a site flushes once and needs no version of its own.
	 */
	public static function flush_stale_rewrite_rules() {
		$stored = \get_option( 'rewrite_rules' );
		// Plain permalinks store no rules, and missing ones are built anew.
		if ( ! \is_array( $stored ) ) {
			return;
		}

		foreach ( self::well_known_rules() as $regex => $query ) {
			if ( ( $stored[ $regex ] ?? null ) !== $query ) {
				\flush_rewrite_rules( false );
				return;
			}
		}
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
	 * The links that make up the file of a page, in one flat list.
	 *
	 * An unlisted list is not part of it; all_groups() has every list.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array Normalized links.
	 */
	public static function page_links( $post ) {
		$links = array();
		foreach ( self::listed_groups( $post ) as $group ) {
			$links = \array_merge( $links, $group['links'] );
		}
		return $links;
	}

	/**
	 * Every blogroll of a post, each with its own name, links and address.
	 *
	 * Listed or not: what goes into the file of the page is what
	 * listed_groups() returns.
	 *
	 * The name is the one WordPress keeps when a block is renamed in the
	 * editor, so a page with several blogrolls can say what each one is
	 * without the block needing a title of its own.
	 *
	 * Anchors missing from pages saved before they existed are added on
	 * the fly, the same way they are when the page renders, so every group
	 * has one. The result is kept for the request: the head, every block
	 * and the OPML ask for it. The content is part of the key, because a
	 * request can hold a post twice, before and after its anchors were
	 * written.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array List of arrays with a "name", an "anchor" and a "links" key.
	 */
	public static function all_groups( $post ) {
		static $cache = array();

		if ( isset( $cache[ $post->ID ] ) && $cache[ $post->ID ]['content'] === $post->post_content ) {
			return $cache[ $post->ID ]['groups'];
		}

		$groups = array();
		$walker = function ( $blocks ) use ( &$walker, &$groups ) {
			foreach ( $blocks as $block ) {
				if ( 'blockroll/blogroll' === $block['blockName'] ) {
					$links = Sources::links( (array) ( $block['attrs'] ?? array() ), $block['innerBlocks'] ?? array() );
					if ( $links ) {
						$groups[] = array(
							'name'   => \sanitize_text_field( (string) ( $block['attrs']['metadata']['name'] ?? '' ) ),
							'anchor' => \trim( (string) ( $block['attrs']['anchor'] ?? '' ) ),
							'links'  => $links,
							// A list can be unlisted, kept out of the file of
							// its page, and still have one of its own.
							'listed' => false !== ( $block['attrs']['listed'] ?? true ),
						);
					}
				}
				if ( ! empty( $block['innerBlocks'] ) ) {
					$walker( $block['innerBlocks'] );
				}
			}
		};
		$walker( \parse_blocks( Anchors::add( $post->post_content ) ) );

		// A list has a file of its own when it is unlisted, since it is then
		// not in the file of the page, and when it is one of several listed
		// ones. A single listed list is the file of the page and shares its
		// address. Every caller asks the group instead of working it out
		// again: the link in the head, the download under the list.
		$listed = \count( \wp_list_filter( $groups, array( 'listed' => true ) ) );
		foreach ( $groups as $index => $group ) {
			$groups[ $index ]['own_file'] = '' !== $group['anchor']
				&& ( ! $group['listed'] || $listed > 1 );
		}

		$cache[ $post->ID ] = array(
			'content' => $post->post_content,
			'groups'  => $groups,
		);
		return $groups;
	}

	/**
	 * The groups that make up the file of a page.
	 *
	 * A list can be unlisted and still be subscribed to on its own, for a
	 * page that has one list for its readers and another one that is only
	 * of interest to whoever is on the page.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array Groups.
	 */
	public static function listed_groups( $post ) {
		return \array_values( \wp_list_filter( self::all_groups( $post ), array( 'listed' => true ) ) );
	}

	/**
	 * The address of the file a group is served under: its own, or the
	 * one of the page it shares.
	 *
	 * Read here and not kept with the group: all_groups() is cached on the
	 * content of a post, and an address also depends on its permalink.
	 *
	 * @param \WP_Post $post  Post object.
	 * @param array    $group Group as returned by all_groups().
	 * @return string OPML URL.
	 */
	public static function group_url( $post, $group ) {
		return self::opml_url( $post, $group['own_file'] ? $group['anchor'] : '' );
	}

	/**
	 * One blogroll of a page, by its HTML anchor.
	 *
	 * @param \WP_Post $post   Post object.
	 * @param string   $anchor HTML anchor of one blogroll block.
	 * @return array|null The group, or null when the page has no such one.
	 */
	public static function group( $post, $anchor ) {
		if ( '' === $anchor ) {
			return null;
		}

		$group = \wp_list_filter( self::all_groups( $post ), array( 'anchor' => $anchor ) );

		return 1 === \count( $group ) ? \reset( $group ) : null;
	}

	/**
	 * The groups an opml request asks for: one list, or the file of the
	 * page. An anchor no block has falls back to the file of the page,
	 * rather than an error.
	 *
	 * @param \WP_Post $post   Post object.
	 * @param string   $anchor HTML anchor of one blogroll block, or empty.
	 * @return array Groups, empty when there is nothing to serve.
	 */
	public static function requested_groups( $post, $anchor = '' ) {
		$group = self::group( $post, $anchor );

		return $group ? array( $group ) : self::listed_groups( $post );
	}

	/**
	 * Build the outline elements for a page's blogrolls.
	 *
	 * Several blogrolls on one page become groups, named after the block, so
	 * a reader can keep them apart. Blogrolls that were never named fall back
	 * to the name of the block itself. A single blogroll stays a plain list,
	 * the page is its own group.
	 *
	 * @param array $groups Groups as returned by all_groups().
	 * @return string The escaped elements.
	 */
	public static function outlines( $groups ) {
		$grouped = \count( $groups ) > 1;
		$lines   = array();

		foreach ( $groups as $group ) {
			if ( $grouped ) {
				$lines[] = "\t\t" . '<outline text="' . \esc_xml( self::group_name( $group ) ) . '">';
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
		$attributes = \sprintf( ' text="%s" type="rss"', \esc_xml( $link['name'] ? $link['name'] : $link['url'] ) );
		if ( $link['description'] ) {
			$attributes .= \sprintf( ' description="%s"', \esc_xml( $link['description'] ) );
		}
		if ( $link['feedUrl'] ) {
			$attributes .= \sprintf( ' xmlUrl="%s"', \esc_url( $link['feedUrl'] ) );
		}
		$attributes .= \sprintf( ' htmlUrl="%s"', \esc_url( $link['url'] ) );

		return $indent . '<outline' . $attributes . ' />';
	}

	/**
	 * Name of a group, falling back to the name of the block itself.
	 *
	 * @param array $group Group as returned by all_groups().
	 * @return string Name.
	 */
	private static function group_name( $group ) {
		return $group['name'] ? $group['name'] : \__( 'Blogroll', 'blockroll' );
	}

	/**
	 * Title of one blogroll of a page: its name, then the page title.
	 *
	 * @param array  $group      Group as returned by all_groups().
	 * @param string $page_title Title of the page, see title().
	 * @return string Title.
	 */
	public static function group_title( $group, $page_title ) {
		/* translators: 1: name of the blogroll, 2: page title with author */
		return \sprintf( \__( '%1$s (%2$s)', 'blockroll' ), self::group_name( $group ), $page_title );
	}

	/**
	 * Print the OPML for a single post's blogroll.
	 *
	 * With an anchor, only the block carrying that anchor is printed, as a
	 * flat list with a title of its own. An anchor no block has falls back
	 * to the whole page, so a reader keeps getting a list when a block is
	 * renamed, instead of an error. So does an anchor several blocks have,
	 * which the editor prevents but a code editor does not: the whole page
	 * rather than a guess at which of them was meant.
	 *
	 * @param \WP_Post $post   Post object.
	 * @param string   $anchor HTML anchor of one blogroll block, or empty for all.
	 */
	public static function for_post( $post, $anchor = '' ) {
		$title  = self::title( $post );
		$group  = self::group( $post, $anchor );
		$groups = $group ? array( $group ) : self::listed_groups( $post );
		if ( $group ) {
			$title = self::group_title( $group, $title );
		}

		\load_template(
			\dirname( BLOCKROLL_PLUGIN_FILE ) . '/templates/opml.php',
			false,
			array(
				'post'   => $post,
				'title'  => $title,
				'groups' => $groups,
			)
		);
	}

	/**
	 * Print the directory OPML listing each listed blogroll's OPML.
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
	 * Print the following list: the feeds of every listed blogroll in
	 * one flat list, each feed once.
	 *
	 * See https://followspec.org/
	 *
	 * @param \WP_Post[]|null $posts Blogroll posts, or null to look them up.
	 */
	public static function following( $posts = null ) {
		$posts = null === $posts ? Index::get_posts() : $posts;

		\load_template(
			\dirname( BLOCKROLL_PLUGIN_FILE ) . '/templates/opml-following.php',
			false,
			array(
				// Only pages with a feed in the list make it change.
				'posts' => \array_values(
					\array_filter(
						$posts,
						function ( $post ) {
							return (bool) self::following_links( array( $post ) );
						}
					)
				),
				'links' => self::following_links( $posts ),
			)
		);
	}

	/**
	 * The links of every listed blogroll that have a feed, each feed once.
	 *
	 * @param \WP_Post[]|null $posts Blogroll posts, or null to look them up.
	 * @return array Normalized links.
	 */
	public static function following_links( $posts = null ) {
		$links = array();
		foreach ( ( null === $posts ? Index::get_posts() : $posts ) as $post ) {
			foreach ( self::page_links( $post ) as $link ) {
				// Only a feed can be followed, and the same feed only once.
				if ( $link['feedUrl'] && ! isset( $links[ $link['feedUrl'] ] ) ) {
					$links[ $link['feedUrl'] ] = $link;
				}
			}
		}
		return \array_values( $links );
	}

	/**
	 * Add the blogroll feeds to the following list of SocialSpec.
	 *
	 * Feeds SocialSpec already lists, from the Link Manager or another
	 * plugin, are not added again.
	 *
	 * @param array $outlines OPML outline attributes, each with an `xmlUrl`.
	 * @return array The outlines.
	 */
	public static function socialspec_following( $outlines ) {
		$outlines = (array) $outlines;
		$feeds    = \array_column( $outlines, 'xmlUrl' );

		foreach ( self::following_links() as $link ) {
			if ( \in_array( $link['feedUrl'], $feeds, true ) ) {
				continue;
			}

			$outlines[] = \array_filter(
				array(
					'type'        => 'rss',
					'text'        => $link['name'] ? $link['name'] : $link['url'],
					'description' => $link['description'],
					'htmlUrl'     => $link['url'],
					'xmlUrl'      => $link['feedUrl'],
				)
			);
		}

		return $outlines;
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
		$opml = \get_query_var( self::QUERY_VAR, null );
		if ( null === $opml ) {
			return;
		}

		// The well-known URLs ask for the directory or the following list,
		// whatever page they land on.
		$directory = self::DIRECTORY === $opml;
		$following = self::FOLLOWING === $opml;
		$anchor    = (string) \get_query_var( self::GROUP, '' );
		$post      = ( $directory || $following ) ? null : Index::queried_post();
		// A page whose lists are all unlisted has no file of its own, and
		// neither has one that is asked for a list it does not hold. It is
		// then a page without a blogroll: the root still answers with the
		// directory, every other page simply loads.
		if ( $post && ! self::requested_groups( $post, $anchor ) ) {
			$post = null;
		}
		$posts = ( ! $post && ( $directory || $following || self::is_blogroll_root() ) ) ? Index::get_posts() : array();

		// The well-known URLs have no page to fall back to, so they answer
		// with an empty list rather than the home page.
		if ( ! $post && ! $posts && ! $directory && ! $following ) {
			return;
		}

		// Every list is public, so apps on other sites may read it in the
		// browser as well.
		\header( 'Access-Control-Allow-Origin: *' );

		if ( $following ) {
			self::serve_following( $posts );
		}

		\header( 'Content-Type: text/xml; charset=' . \get_option( 'blog_charset' ) );
		if ( $post ) {
			self::for_post( $post, $anchor );
		} else {
			self::directory( $posts );
		}
		exit;
	}

	/**
	 * Serve the following list the way FollowSpec readers expect it, and
	 * exit.
	 *
	 * The OPML media type and an ETag, so a reader that asks again with
	 * If-None-Match gets a 304 while nothing changed. A browser on
	 * another site asks with OPTIONS first before it sends that header.
	 *
	 * See https://followspec.org/
	 *
	 * @param \WP_Post[] $posts Blogroll posts.
	 */
	private static function serve_following( $posts ) {
		$method = \strtoupper( \sanitize_text_field( \wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) );
		if ( 'OPTIONS' === $method ) {
			\status_header( 204 );
			\header( 'Access-Control-Allow-Methods: GET, HEAD, OPTIONS' );
			\header( 'Access-Control-Allow-Headers: If-None-Match' );
			\header( 'Access-Control-Max-Age: 86400' );
			exit;
		}

		\ob_start();
		self::following( $posts );
		$body = (string) \ob_get_clean();
		$etag = '"' . \md5( $body ) . '"';

		\header( 'Content-Type: text/x-opml; charset=' . \get_option( 'blog_charset' ) );
		\header( 'ETag: ' . $etag );
		\header( 'Access-Control-Expose-Headers: ETag' );
		\header( 'X-Content-Type-Options: nosniff' );

		if ( self::etag_matches( $etag, \wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only compared.
			\status_header( 304 );
			exit;
		}

		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the template.
		exit;
	}

	/**
	 * Whether an If-None-Match header matches an ETag.
	 *
	 * The weak comparison of RFC 9110, section 13.1.2: a `W/` in front
	 * of a tag is ignored.
	 *
	 * @param string $etag   ETag of the response.
	 * @param string $header If-None-Match request header.
	 * @return bool True if the reader has the current version.
	 */
	public static function etag_matches( $etag, $header ) {
		$header = \trim( (string) $header );
		if ( '*' === $header ) {
			return true;
		}

		foreach ( \explode( ',', $header ) as $candidate ) {
			$candidate = \trim( $candidate );
			if ( 0 === \strpos( $candidate, 'W/' ) ) {
				$candidate = \substr( $candidate, 2 );
			}
			if ( '' !== $candidate && $candidate === $etag ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The OPML URL of a post, or of one of its blogrolls.
	 *
	 * @param \WP_Post $post   Post object.
	 * @param string   $anchor HTML anchor of one blogroll block, or empty for all.
	 * @return string OPML URL.
	 */
	public static function opml_url( $post, $anchor = '' ) {
		$args = array( self::QUERY_VAR => '' );
		if ( '' !== $anchor ) {
			$args[ self::GROUP ] = $anchor;
		}
		return \add_query_arg( $args, \get_permalink( $post ) );
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
		$post = Index::queried_post();
		if ( $post ) {
			$title = self::title( $post );
			if ( self::listed_groups( $post ) ) {
				self::print_links( self::opml_url( $post ), \get_permalink( $post ), $title );
			}
			self::print_group_links( $post, $title );
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
		self::print_links( self::opml_url( $post ), \get_permalink( $post ), self::title( $post ) );
	}

	/**
	 * Print the rel="blogroll" links of the single blogrolls on a page.
	 *
	 * A list that is the file of its page shares its address and needs no
	 * link of its own; every other list gets one.
	 *
	 * @param \WP_Post $post       Post with blogroll blocks.
	 * @param string   $page_title Title of the page, see title().
	 */
	private static function print_group_links( $post, $page_title ) {
		$permalink = \get_permalink( $post );
		foreach ( self::all_groups( $post ) as $group ) {
			// A single listed list is the file of the page, which the page
			// advertises itself; it needs no link of its own.
			if ( ! $group['own_file'] ) {
				continue;
			}

			self::print_links(
				self::group_url( $post, $group ),
				$permalink . '#' . $group['anchor'],
				self::group_title( $group, $page_title )
			);
		}
	}

	/**
	 * Print a pair of rel="blogroll" links, the OPML and its HTML page.
	 *
	 * @param string $opml_url URL of the OPML.
	 * @param string $html_url URL of the page.
	 * @param string $title    Title of both.
	 */
	private static function print_links( $opml_url, $html_url, $title ) {
		\printf(
			'<link rel="blogroll" type="text/xml" href="%s" title="%s" />' . "\n",
			\esc_url( $opml_url ),
			\esc_attr( $title )
		);
		\printf(
			'<link rel="blogroll" type="text/html" href="%s" title="%s" />' . "\n",
			\esc_url( $html_url ),
			\esc_attr( $title )
		);
	}
}
