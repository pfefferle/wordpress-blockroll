<?php
/**
 * Server side link discovery.
 *
 * @package Blockroll
 */

namespace Blockroll;

/**
 * Extract feed, name, description, and photo from a fetched HTML page.
 *
 * The REST side lives in Rest\Discovery_Controller; this class is
 * only the parser.
 */
class Discovery {
	/**
	 * Extract link details from an HTML document.
	 *
	 * Priority: h-card properties, then feed data, then generic HTML.
	 *
	 * @param string $html     HTML document.
	 * @param string $base_url URL the document was fetched from, for
	 *                         resolving relative URLs.
	 * @return array Extracted fields: name, description, feedUrl, photo.
	 */
	public static function from_html( $html, $base_url ) {
		$result = array(
			'name'        => '',
			'description' => '',
			'feedUrl'     => '',
			'photo'       => '',
		);

		if ( '' === \trim( $html ) ) {
			return $result;
		}

		$previous = \libxml_use_internal_errors( true );
		$doc      = new \DOMDocument();
		$doc->loadHTML( '<?xml encoding="UTF-8">' . $html );
		\libxml_clear_errors();
		\libxml_use_internal_errors( $previous );

		$xpath = new \DOMXPath( $doc );

		// Feed: <link rel="alternate" type="application/rss+xml|atom+xml">.
		foreach ( $xpath->query( '//link[@rel and @href and @type]' ) as $node ) {
			if ( self::has_token( $node->getAttribute( 'rel' ), 'alternate' ) && \preg_match( '#application/(rss|atom)\+xml#i', $node->getAttribute( 'type' ) ) ) {
				$result['feedUrl'] = self::absolute( $node->getAttribute( 'href' ), $base_url );
				break;
			}
		}

		// The site's own h-card, not one of the h-cards a blogroll on the
		// page is made of.
		$hcard = self::representative_hcard( $xpath, $base_url );

		if ( $hcard ) {
			$result['name']        = self::hcard_text( $xpath, $hcard, 'p-name' );
			$result['description'] = self::hcard_text( $xpath, $hcard, 'p-note' );
			$result['photo']       = self::hcard_url( $xpath, $hcard, 'u-photo', $base_url );
		}

		// Fallbacks.
		if ( '' === $result['name'] ) {
			$title          = $xpath->query( '//title' )->item( 0 );
			$result['name'] = $title ? \sanitize_text_field( $title->textContent ) : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}
		if ( '' === $result['description'] ) {
			foreach ( $xpath->query( '//meta[@name="description"][@content]' ) as $node ) {
				$result['description'] = \sanitize_text_field( $node->getAttribute( 'content' ) );
				break;
			}
		}
		if ( '' === $result['photo'] ) {
			foreach ( $xpath->query( '//link[@rel and @href]' ) as $node ) {
				if ( self::has_token( $node->getAttribute( 'rel' ), 'icon' ) ) {
					$result['photo'] = self::absolute( $node->getAttribute( 'href' ), $base_url );
					break;
				}
			}
		}

		return $result;
	}

	/**
	 * Whether a space separated attribute contains a token.
	 *
	 * @param string $value Attribute value.
	 * @param string $token Token to look for.
	 * @return bool True when present.
	 */
	private static function has_token( $value, $token ) {
		return \in_array( $token, \preg_split( '/\s+/', $value ), true );
	}

	/**
	 * The h-card that represents the page itself.
	 *
	 * A page can carry many h-cards: a blogroll marks up every entry as
	 * one, and picking the first would describe somebody else's site. This
	 * follows the representative h-card parsing rules: an h-card that links
	 * to the page itself wins, then one that links to a rel="me" URL. If
	 * neither is there, only a page with a single h-card is unambiguous
	 * enough to use, otherwise there is none and the generic HTML
	 * fallbacks take over.
	 *
	 * See https://microformats.org/wiki/representative-h-card-parsing
	 *
	 * @param \DOMXPath $xpath    XPath helper.
	 * @param string    $base_url URL the document was fetched from.
	 * @return \DOMNode|null The representative h-card, or null.
	 */
	private static function representative_hcard( $xpath, $base_url ) {
		$hcards = self::find_all_by_class( $xpath, null, 'h-card' );

		if ( ! $hcards ) {
			return null;
		}

		$page = self::normalize_url( $base_url );
		foreach ( $hcards as $hcard ) {
			if ( \in_array( $page, self::hcard_urls( $xpath, $hcard, $base_url ), true ) ) {
				return $hcard;
			}
		}

		$rel_me = self::rel_urls( $xpath, 'me', $base_url );
		if ( $rel_me ) {
			foreach ( $hcards as $hcard ) {
				if ( \array_intersect( $rel_me, self::hcard_urls( $xpath, $hcard, $base_url ) ) ) {
					return $hcard;
				}
			}
		}

		return 1 === \count( $hcards ) ? $hcards[0] : null;
	}

	/**
	 * The normalized u-url values of an h-card.
	 *
	 * @param \DOMXPath $xpath    XPath helper.
	 * @param \DOMNode  $hcard    The h-card root.
	 * @param string    $base_url Base for relative URLs.
	 * @return string[] Normalized URLs.
	 */
	private static function hcard_urls( $xpath, $hcard, $base_url ) {
		$urls = array();
		foreach ( $xpath->query( 'descendant-or-self::*[@class]', $hcard ) as $node ) {
			if ( ! self::has_token( $node->getAttribute( 'class' ), 'u-url' ) ) {
				continue;
			}
			$url = $node->getAttribute( 'href' ) ? $node->getAttribute( 'href' ) : $node->getAttribute( 'src' );
			if ( $url ) {
				$urls[] = self::normalize_url( self::absolute( $url, $base_url ) );
			}
		}
		return $urls;
	}

	/**
	 * The normalized URLs of the links carrying a rel value.
	 *
	 * @param \DOMXPath $xpath    XPath helper.
	 * @param string    $rel      Rel token to look for.
	 * @param string    $base_url Base for relative URLs.
	 * @return string[] Normalized URLs.
	 */
	private static function rel_urls( $xpath, $rel, $base_url ) {
		$urls = array();
		foreach ( $xpath->query( '//*[@rel and @href]' ) as $node ) {
			if ( self::has_token( $node->getAttribute( 'rel' ), $rel ) ) {
				$urls[] = self::normalize_url( self::absolute( $node->getAttribute( 'href' ), $base_url ) );
			}
		}
		return $urls;
	}

	/**
	 * Reduce a URL to the part worth comparing.
	 *
	 * Scheme, a www prefix, a fragment, and a trailing slash are all
	 * differences that still mean the same page.
	 *
	 * @param string $url URL.
	 * @return string Comparable URL.
	 */
	private static function normalize_url( $url ) {
		$url = \strtolower( \trim( $url ) );
		$url = \preg_replace( '#^[a-z][a-z0-9+.-]*://#', '', $url );
		$url = \preg_replace( '#^www\.#', '', $url );
		$url = \preg_replace( '/#.*$/', '', $url );
		return \untrailingslashit( $url );
	}

	/**
	 * All elements carrying a class token, inside a scope.
	 *
	 * @param \DOMXPath $xpath XPath helper.
	 * @param \DOMNode  $scope Scope node, or null for the whole document.
	 * @param string    $class Class token.
	 * @return \DOMNode[] Matched elements.
	 */
	private static function find_all_by_class( $xpath, $scope, $class ) {
		$nodes = array();
		foreach ( $xpath->query( 'descendant-or-self::*[@class]', $scope ) as $node ) {
			if ( self::has_token( $node->getAttribute( 'class' ), $class ) ) {
				$nodes[] = $node;
			}
		}
		return $nodes;
	}

	/**
	 * First element carrying a class token, inside a scope.
	 *
	 * @param \DOMXPath $xpath XPath helper.
	 * @param \DOMNode  $scope Scope node, or null for the whole document.
	 * @param string    $class Class token.
	 * @return \DOMNode|null Matched element.
	 */
	private static function find_by_class( $xpath, $scope, $class ) {
		foreach ( $xpath->query( 'descendant-or-self::*[@class]', $scope ) as $node ) {
			if ( self::has_token( $node->getAttribute( 'class' ), $class ) ) {
				return $node;
			}
		}
		return null;
	}

	/**
	 * First text value of a microformats property inside an h-card.
	 *
	 * @param \DOMXPath $xpath XPath helper.
	 * @param \DOMNode  $hcard The h-card root.
	 * @param string    $class Property class name.
	 * @return string Text value.
	 */
	private static function hcard_text( $xpath, $hcard, $class ) {
		$node = self::find_by_class( $xpath, $hcard, $class );
		return $node ? \sanitize_text_field( $node->textContent ) : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * First URL value of a microformats property inside an h-card.
	 *
	 * @param \DOMXPath $xpath    XPath helper.
	 * @param \DOMNode  $hcard    The h-card root.
	 * @param string    $class    Property class name.
	 * @param string    $base_url Base for relative URLs.
	 * @return string Absolute URL.
	 */
	private static function hcard_url( $xpath, $hcard, $class, $base_url ) {
		// Not find_by_class(): a matched element without src/href, like a
		// wrapper span, must not end the search.
		foreach ( $xpath->query( 'descendant-or-self::*[@class]', $hcard ) as $node ) {
			if ( ! self::has_token( $node->getAttribute( 'class' ), $class ) ) {
				continue;
			}
			$url = $node->getAttribute( 'src' ) ? $node->getAttribute( 'src' ) : $node->getAttribute( 'href' );
			if ( $url ) {
				return self::absolute( $url, $base_url );
			}
		}
		return '';
	}

	/**
	 * Resolve a possibly relative URL against a base.
	 *
	 * @param string $url      URL from the document.
	 * @param string $base_url Base URL.
	 * @return string Absolute, sanitized URL.
	 */
	private static function absolute( $url, $base_url ) {
		return \esc_url_raw( \WP_Http::make_absolute_url( $url, $base_url ) );
	}
}
