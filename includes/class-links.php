<?php
/**
 * Link normalizing and sorting.
 *
 * @package Blockroll
 */

namespace Blockroll;

/**
 * Normalize and sort blogroll link data.
 */
class Links {
	/**
	 * Fill and sanitize a raw link array.
	 *
	 * @param array $link Raw link data from block attributes.
	 * @return array Normalized link.
	 */
	public static function normalize( $link ) {
		$link = \wp_parse_args(
			(array) $link,
			array(
				'url'         => '',
				'name'        => '',
				'description' => '',
				'feedUrl'     => '',
				'photo'       => '',
				'xfn'         => array(),
				'added'       => '',
			)
		);

		return array(
			'url'         => \esc_url_raw( $link['url'] ),
			'name'        => \sanitize_text_field( $link['name'] ),
			'description' => \sanitize_text_field( $link['description'] ),
			'feedUrl'     => \esc_url_raw( $link['feedUrl'] ),
			'photo'       => Icon::is_data_uri( $link['photo'] ) ? $link['photo'] : \esc_url_raw( $link['photo'] ),
			'xfn'         => Xfn::sanitize( $link['xfn'] ),
			'added'       => \sanitize_text_field( $link['added'] ),
		);
	}

	/**
	 * Sort links.
	 *
	 * @param array  $links   Normalized links.
	 * @param string $sort_by One of "name", "added", "manual".
	 * @return array Sorted copy.
	 */
	public static function sort( $links, $sort_by ) {
		if ( 'name' === $sort_by ) {
			\usort(
				$links,
				function ( $a, $b ) {
					return \strcasecmp( $a['name'], $b['name'] );
				}
			);
		} elseif ( 'added' === $sort_by ) {
			\usort(
				$links,
				function ( $a, $b ) {
					return \strcmp( $b['added'], $a['added'] );
				}
			);
		}
		return $links;
	}
}
