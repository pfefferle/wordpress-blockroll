<?php
/**
 * Blogroll source registry and link resolution.
 *
 * @package Blockroll
 */

namespace Blockroll;

/**
 * Resolve the links for a blogroll block from its selected source.
 */
class Sources {
	/**
	 * The built-in source backed by the block's own attributes.
	 */
	const MANUAL = 'manual';

	/**
	 * Get available source labels.
	 *
	 * Plugins can add a source by filtering this array and then providing its
	 * links through the blockroll_source_links filter.
	 *
	 * @return array Source slug => source label.
	 */
	public static function all() {
		$sources = array(
			self::MANUAL => \__( 'Manual links', 'blockroll' ),
		);

		/**
		 * Filter the blogroll sources available to a block.
		 *
		 * Source slugs are stored in block attributes. Labels are shown in the
		 * editor. A source should provide links through the blockroll_source_links
		 * filter when its slug is selected. See docs/developers.md for an
		 * example source integration.
		 *
		 * @param array $sources Source slug => source label.
		 */
		$sources = \apply_filters( 'blockroll_sources', $sources );

		return \array_filter(
			(array) $sources,
			function ( $label, $slug ) {
				return \sanitize_key( $slug ) === $slug && \is_scalar( $label );
			},
			\ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * Get help text for a source.
	 *
	 * @param string $source Source slug.
	 * @return string Source help text.
	 */
	public static function help( $source ) {
		$source = \sanitize_key( $source );
		if ( self::MANUAL === $source || ! isset( self::all()[ $source ] ) ) {
			return '';
		}

		/**
		 * Filter help text shown in the editor for a selected blogroll source.
		 *
		 * @param string $help   Help text.
		 * @param string $source Selected source slug.
		 */
		$help = \apply_filters( 'blockroll_source_help', '', $source );

		return \is_scalar( $help ) ? (string) $help : '';
	}

	/**
	 * Get a help URL for a source.
	 *
	 * @param string $source Source slug.
	 * @return string Source help URL.
	 */
	public static function help_url( $source ) {
		$source = \sanitize_key( $source );
		if ( self::MANUAL === $source || ! isset( self::all()[ $source ] ) ) {
			return '';
		}

		/**
		 * Filter the URL linked from source help text in the editor.
		 *
		 * @param string $url    Help URL.
		 * @param string $source Selected source slug.
		 */
		$url = \apply_filters( 'blockroll_source_help_url', '', $source );

		return \is_scalar( $url ) ? \esc_url_raw( (string) $url ) : '';
	}

	/**
	 * Resolve and normalize the links for a block's selected source.
	 *
	 * @param array $attributes Block attributes.
	 * @return array Normalized links.
	 */
	public static function links( $attributes ) {
		$source = self::source( $attributes );
		if ( self::MANUAL === $source ) {
			$links = (array) ( $attributes['links'] ?? array() );
		} else {
			/**
			 * Provide links for a selected blogroll source.
			 *
			 * Return raw link arrays using the normal Blockroll shape:
			 * url, name, description, feedUrl, photo, xfn, and added.
			 * Blockroll normalizes returned links before rendering or exporting
			 * OPML. See docs/developers.md for an example source integration.
			 *
			 * @param array  $links      Links for the source.
			 * @param string $source     Selected source slug.
			 * @param array  $attributes Block attributes.
			 */
			$links = \apply_filters( 'blockroll_source_links', array(), $source, $attributes );
		}

		$links = \array_map( array( Links::class, 'normalize' ), (array) $links );

		return \array_values(
			\array_filter(
				$links,
				function ( $link ) {
					return '' !== $link['url'];
				}
			)
		);
	}

	/**
	 * Get a block's selected source.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Source slug.
	 */
	private static function source( $attributes ) {
		$source  = isset( $attributes['source'] ) ? \sanitize_key( $attributes['source'] ) : self::MANUAL;
		$sources = self::all();

		return isset( $sources[ $source ] ) ? $source : self::MANUAL;
	}
}
