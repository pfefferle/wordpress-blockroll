<?php
/**
 * The feeds of all blogrolls of the site, see followspec.org.
 *
 * @package Blockroll
 */

namespace Blockroll;

/**
 * The following list: the feeds of every listed blogroll in one flat
 * list, each feed once, at /.well-known/following.opml.
 *
 * See https://followspec.org/
 */
class Following {
	/**
	 * Path of the well-known following list, without the leading slash.
	 */
	const PATH = '.well-known/following.opml';

	/**
	 * Value of the `opml` query var that always asks for the following list.
	 */
	const QUERY_VALUE = 'following';

	/**
	 * Print the following list.
	 *
	 * @param \WP_Post[]|null $posts Blogroll posts, or null to look them up.
	 */
	public static function render( $posts = null ) {
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
							return (bool) self::links( array( $post ) );
						}
					)
				),
				'links' => self::links( $posts ),
			)
		);
	}

	/**
	 * The links of every listed blogroll that have a feed, each feed once.
	 *
	 * @param \WP_Post[]|null $posts Blogroll posts, or null to look them up.
	 * @return array Normalized links.
	 */
	public static function links( $posts = null ) {
		$links = array();
		foreach ( ( null === $posts ? Index::get_posts() : $posts ) as $post ) {
			foreach ( Opml::page_links( $post ) as $link ) {
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
	public static function add_to_socialspec( $outlines ) {
		$outlines = (array) $outlines;
		$feeds    = \array_column( $outlines, 'xmlUrl' );

		foreach ( self::links() as $link ) {
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
	 * Serve the following list, and exit.
	 *
	 * FollowSpec asks for `text/x-opml`, this sends `text/xml` on purpose:
	 * browsers only apply the XSLT stylesheet to XML, and with it a visitor
	 * sees a page instead of raw OPML, like with every other file of the
	 * plugin. With an ETag, so a reader that asks again with
	 * If-None-Match gets a 304 while nothing changed. A browser on
	 * another site asks with OPTIONS first before it sends that header.
	 *
	 * @param \WP_Post[] $posts Blogroll posts.
	 */
	public static function serve( $posts ) {
		$method = \strtoupper( \sanitize_text_field( \wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) );
		if ( 'OPTIONS' === $method ) {
			\status_header( 204 );
			\header( 'Access-Control-Allow-Methods: GET, HEAD, OPTIONS' );
			\header( 'Access-Control-Allow-Headers: If-None-Match' );
			\header( 'Access-Control-Max-Age: 86400' );
			exit;
		}

		\ob_start();
		self::render( $posts );
		$body = (string) \ob_get_clean();
		$etag = '"' . \md5( $body ) . '"';

		\header( 'Content-Type: text/xml; charset=' . \get_option( 'blog_charset' ) );
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
}
