<?php
/**
 * Icons that live in the page instead of on a foreign server.
 *
 * @package Blockroll
 */

namespace Blockroll;

/**
 * Fetch an icon once and turn it into a data URI.
 *
 * Deep linking an icon means every visitor of the blogroll asks every
 * listed site for it, which hands their IP address to all of them. The
 * icon is fetched once, by the server, and travels with the post from
 * then on.
 */
class Icon {
	/**
	 * Edge length the icon is scaled down to.
	 */
	const SIZE = 48;

	/**
	 * Longest data URI worth putting into the post content.
	 */
	const MAX_LENGTH = 20 * KB_IN_BYTES;

	/**
	 * Largest response worth reading at all.
	 */
	const MAX_RESPONSE = 512 * KB_IN_BYTES;

	/**
	 * Image types a browser renders and a data URI may carry.
	 *
	 * SVG is missing on purpose: it can carry scripts, and WordPress
	 * does not accept it as an upload either.
	 *
	 * @var string[]
	 */
	const TYPES = array(
		'image/png',
		'image/jpeg',
		'image/gif',
		'image/webp',
		'image/x-icon',
		'image/vnd.microsoft.icon',
	);

	/**
	 * Fetch an image and return it as a data URI.
	 *
	 * @param string $url Image URL, or a data URI that is already embedded.
	 * @return string Data URI, or an empty string when there is nothing to embed.
	 */
	public static function embed( $url ) {
		if ( self::is_data_uri( $url ) ) {
			return $url;
		}

		// No validation of our own: wp_safe_remote_get() below rejects
		// local and otherwise unsafe addresses already.
		if ( ! \preg_match( '#^https?://#i', (string) $url ) ) {
			return '';
		}

		$response = \wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 10,
				'limit_response_size' => self::MAX_RESPONSE,
			)
		);

		if ( \is_wp_error( $response ) || 200 !== \wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		$type  = \strtolower( \trim( \strtok( (string) \wp_remote_retrieve_header( $response, 'content-type' ), ';' ) ) );
		$bytes = \wp_remote_retrieve_body( $response );

		if ( '' === $bytes || ! \in_array( $type, self::TYPES, true ) ) {
			return '';
		}

		// An icon the image editor cannot read, an .ico with GD for example,
		// is embedded as it came. Browsers show it, it is just not smaller.
		$resized = self::resize( $bytes, $type );
		if ( $resized ) {
			list( $type, $bytes ) = $resized;
		}

		$data = 'data:' . $type . ';base64,' . \base64_encode( $bytes ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- A data URI is base64 by definition.

		return \strlen( $data ) <= self::MAX_LENGTH ? $data : '';
	}

	/**
	 * Scale an image down to the icon size.
	 *
	 * @param string $bytes Image bytes.
	 * @param string $type  Content type of the image.
	 * @return array|null Type and bytes, or null when the image cannot be read.
	 */
	private static function resize( $bytes, $type ) {
		if ( ! \function_exists( '\wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$file = \wp_tempnam( 'blockroll-icon' );
		if ( ! $file ) {
			return null;
		}

		\file_put_contents( $file, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A temporary file, not the uploads directory.

		$editor = \wp_get_image_editor( $file );
		if ( \is_wp_error( $editor ) ) {
			\wp_delete_file( $file );
			return null;
		}

		// A photo stays a JPEG, as a PNG it would be several times the
		// size. Everything else becomes a PNG.
		$target = ( 'image/jpeg' === $type && $editor->supports_mime_type( $type ) ) ? $type : 'image/png';

		$editor->resize( self::SIZE, self::SIZE, false );
		$saved = $editor->save( null, $target );
		\wp_delete_file( $file );

		if ( \is_wp_error( $saved ) || empty( $saved['path'] ) ) {
			return null;
		}

		$smaller = \file_get_contents( $saved['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A temporary file, not a remote request.
		\wp_delete_file( $saved['path'] );

		return $smaller ? array( $target, $smaller ) : null;
	}

	/**
	 * Whether a value is an image data URI the plugin would print.
	 *
	 * @param string $value Attribute value.
	 * @return bool True for an embedded image.
	 */
	public static function is_data_uri( $value ) {
		$types = \implode( '|', \array_map( 'preg_quote', self::TYPES ) );

		return (bool) \preg_match( '#^data:(?:' . $types . ');base64,[A-Za-z0-9+/]+={0,2}$#', (string) $value );
	}

	/**
	 * The image source to print for a link, if any.
	 *
	 * Only an embedded image or one from the site itself is printed. A
	 * foreign URL would make every visitor of the blogroll ask every
	 * listed site for its icon, so it becomes the placeholder instead.
	 * Sites that want the old behaviour back can say so:
	 *
	 *     add_filter( 'blockroll_allow_remote_photo', '__return_true' );
	 *
	 * @param string $photo The link's photo attribute.
	 * @return string Value for the src attribute, empty when nothing is printed.
	 */
	public static function printable( $photo ) {
		if ( self::is_data_uri( $photo ) ) {
			return $photo;
		}

		if ( ! $photo ) {
			return '';
		}

		/**
		 * Filters whether an icon may be loaded from a foreign server.
		 *
		 * @param bool   $allowed Whether the URL may be printed. Default false.
		 * @param string $photo   The photo URL.
		 */
		if ( self::is_local( $photo ) || \apply_filters( 'blockroll_allow_remote_photo', false, $photo ) ) {
			return \esc_url_raw( $photo );
		}

		return '';
	}

	/**
	 * Whether a URL points at the site itself.
	 *
	 * An image from the own media library is fine, it costs the visitor
	 * no foreign request.
	 *
	 * @param string $url URL.
	 * @return bool True when the host is the site's own.
	 */
	public static function is_local( $url ) {
		if ( ! $url ) {
			return false;
		}

		$host = \wp_parse_url( $url, \PHP_URL_HOST );

		return $host && \strtolower( $host ) === \strtolower( (string) \wp_parse_url( \home_url(), \PHP_URL_HOST ) );
	}
}
