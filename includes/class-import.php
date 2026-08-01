<?php
/**
 * OPML import.
 *
 * @package Blockroll
 */

namespace Blockroll;

/**
 * Turn an OPML file into blogroll links.
 *
 * The REST side lives in Rest\Import_Controller; this class is
 * only the parser.
 */
class Import {
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
