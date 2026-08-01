<?php
/**
 * Server side link discovery.
 *
 * @package Blockroll
 */

namespace Blockroll;

/**
 * Fetch a URL and extract feed, name, description, and photo.
 *
 * The editor cannot fetch foreign sites itself (CORS), so it asks
 * this REST route to do it.
 */
class Discovery {
	/**
	 * Register the REST route.
	 */
	public static function register_routes() {
		\register_rest_route(
			'blockroll/v1',
			'/discover',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'handle' ),
				'permission_callback' => function () {
					return \current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'url' => array(
						'required'          => true,
						'validate_callback' => function ( $url ) {
							return (bool) \wp_http_validate_url( $url );
						},
					),
				),
			)
		);
	}

	/**
	 * Handle a discover request.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error Discovered link data.
	 */
	public static function handle( $request ) {
		$url      = $request->get_param( 'url' );
		$response = \wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 10,
				'limit_response_size' => MB_IN_BYTES,
			)
		);
		if ( \is_wp_error( $response ) || 200 !== \wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error(
				'blockroll_fetch_failed',
				\__( 'The site could not be reached.', 'blockroll' ),
				array( 'status' => 502 )
			);
		}

		return \array_merge(
			self::from_html( \wp_remote_retrieve_body( $response ), $url ),
			array( 'url' => \esc_url_raw( $url ) )
		);
	}

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

		// The first h-card on the page.
		$hcard = null;
		foreach ( $xpath->query( '//*[@class]' ) as $node ) {
			if ( self::has_token( $node->getAttribute( 'class' ), 'h-card' ) ) {
				$hcard = $node;
				break;
			}
		}

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
	 * First text value of a microformats property inside an h-card.
	 *
	 * @param \DOMXPath $xpath XPath helper.
	 * @param \DOMNode  $hcard The h-card root.
	 * @param string    $class Property class name.
	 * @return string Text value.
	 */
	private static function hcard_text( $xpath, $hcard, $class ) {
		foreach ( $xpath->query( 'descendant-or-self::*[@class]', $hcard ) as $node ) {
			if ( self::has_token( $node->getAttribute( 'class' ), $class ) ) {
				return \sanitize_text_field( $node->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			}
		}
		return '';
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
		foreach ( $xpath->query( 'descendant-or-self::*[@class]', $hcard ) as $node ) {
			if ( self::has_token( $node->getAttribute( 'class' ), $class ) ) {
				$url = $node->getAttribute( 'src' );
				if ( ! $url ) {
					$url = $node->getAttribute( 'href' );
				}
				if ( $url ) {
					return self::absolute( $url, $base_url );
				}
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
