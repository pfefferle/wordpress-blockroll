<?php
/**
 * OPML import.
 *
 * @package Blockroll
 */

namespace Blockroll;

/**
 * Turn an OPML file into blogroll links.
 */
class Import {
	/**
	 * Register the REST route.
	 */
	public static function register_routes() {
		\register_rest_route(
			'blockroll/v1',
			'/import',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'handle' ),
				'permission_callback' => function () {
					return \current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'opml' => array( 'type' => 'string' ),
					'url'  => array(
						'type'              => 'string',
						'validate_callback' => function ( $url ) {
							return (bool) \wp_http_validate_url( $url );
						},
					),
				),
			)
		);
	}

	/**
	 * Handle an import request.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error Parsed links.
	 */
	public static function handle( $request ) {
		$opml = $request->get_param( 'opml' );

		if ( ! $opml && $request->get_param( 'url' ) ) {
			$response = \wp_safe_remote_get(
				$request->get_param( 'url' ),
				array(
					'timeout'             => 10,
					'limit_response_size' => MB_IN_BYTES,
				)
			);
			if ( \is_wp_error( $response ) || 200 !== \wp_remote_retrieve_response_code( $response ) ) {
				return new \WP_Error(
					'blockroll_fetch_failed',
					\__( 'The OPML file could not be fetched.', 'blockroll' ),
					array( 'status' => 502 )
				);
			}
			$opml = \wp_remote_retrieve_body( $response );
		}

		if ( ! $opml ) {
			return new \WP_Error(
				'blockroll_missing_opml',
				\__( 'Provide an OPML document or a URL to one.', 'blockroll' ),
				array( 'status' => 400 )
			);
		}

		$links = self::parse( $opml );
		if ( \is_wp_error( $links ) ) {
			return $links;
		}

		return array( 'links' => $links );
	}

	/**
	 * Parse an OPML document into normalized links.
	 *
	 * @param string $xml OPML document.
	 * @return array|\WP_Error Links, or an error for malformed input.
	 */
	public static function parse( $xml ) {
		$previous = \libxml_use_internal_errors( true );
		$doc      = \simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NONET );
		\libxml_clear_errors();
		\libxml_use_internal_errors( $previous );

		if ( false === $doc || ! isset( $doc->body ) ) {
			return new \WP_Error(
				'blockroll_invalid_opml',
				\__( 'Could not parse the OPML file.', 'blockroll' ),
				array( 'status' => 422 )
			);
		}

		$links = array();
		self::walk( $doc->body->outline, $links );
		return $links;
	}

	/**
	 * Walk outline elements recursively.
	 *
	 * @param \SimpleXMLElement $outlines Outline list.
	 * @param array             $links    Collected links, by reference.
	 */
	private static function walk( $outlines, &$links ) {
		foreach ( $outlines as $outline ) {
			$xml_url  = (string) $outline['xmlUrl'];
			$html_url = (string) $outline['htmlUrl'];

			if ( $xml_url || $html_url ) {
				if ( ! $html_url && $xml_url ) {
					$parts = \wp_parse_url( $xml_url );
					if ( ! empty( $parts['scheme'] ) && ! empty( $parts['host'] ) ) {
						$html_url = $parts['scheme'] . '://' . $parts['host'] . '/';
					}
				}

				$name = (string) $outline['text'];
				if ( ! $name ) {
					$name = (string) $outline['title'];
				}

				$links[] = Links::normalize(
					array(
						'url'         => $html_url,
						'name'        => $name,
						'description' => (string) $outline['description'],
						'feedUrl'     => $xml_url,
						'added'       => \gmdate( 'Y-m-d' ),
					)
				);
			}

			if ( isset( $outline->outline ) ) {
				self::walk( $outline->outline, $links );
			}
		}
	}
}
