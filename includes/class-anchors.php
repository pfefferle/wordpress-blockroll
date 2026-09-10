<?php
/**
 * Anchors of blogroll blocks.
 *
 * @package Blockroll
 */

namespace Blockroll;

/**
 * Give every blogroll block an anchor.
 *
 * The anchor is the address of one list on a page: the `id` of the block
 * and the value of the `group` query var of its OPML. The editor generates
 * it from the name of the block, the same way the Heading block derives
 * its anchor from the heading text. Pages saved before that existed get
 * theirs here: added on the fly wherever the content is read, and written
 * into the page once, the first time it is viewed.
 */
class Anchors {
	/**
	 * Anchor of a block without a usable name.
	 */
	const FALLBACK = 'blogroll';

	/**
	 * Block comment of a blogroll block, with its attributes in group 1.
	 *
	 * The attributes cannot contain "-->": the serializer escapes every
	 * "--", so the first "}" followed by the closing delimiter ends them.
	 */
	const BLOCK = '/<!--\s+wp:blockroll\/blogroll(?:\s+(\{.*?\}))?\s+(\/?-->)/s';

	/**
	 * Add the anchors wherever content is rendered, and write them once.
	 */
	public static function register() {
		// Before do_blocks() at 9, so the blocks render with their anchors
		// whether or not the page has been written yet.
		\add_filter( 'the_content', array( self::class, 'add' ), 8 );
		\add_action( 'template_redirect', array( self::class, 'migrate_queried_post' ) );
	}

	/**
	 * Write the anchors into the post of a singular request.
	 *
	 * Previews render an autosave, which is never written, so the page
	 * behind it is left alone as well.
	 */
	public static function migrate_queried_post() {
		if ( \is_singular() && ! \is_preview() ) {
			self::migrate( \get_queried_object() );
		}
	}

	/**
	 * Write missing anchors into the blogroll blocks of a post.
	 *
	 * The content is written directly, the way core's upgrade routines do
	 * it: no revision, no modified date, no save hooks for a change nobody
	 * made.
	 *
	 * @param \WP_Post $post Post object.
	 * @return bool Whether anything was written.
	 */
	public static function migrate( $post ) {
		if ( ! $post instanceof \WP_Post || 'revision' === $post->post_type || ! Index::has_blogroll( $post ) ) {
			return false;
		}

		$content = self::add( $post->post_content );
		if ( $content === $post->post_content ) {
			return false;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Deliberate: no revision, modified date or save hooks for a change nobody made.
		$wpdb->update( $wpdb->posts, array( 'post_content' => $content ), array( 'ID' => $post->ID ) );
		\clean_post_cache( $post->ID );
		$post->post_content = $content;

		return true;
	}

	/**
	 * Add an anchor to every blogroll block that has none.
	 *
	 * Only the block comments of blogroll blocks are touched, everything
	 * else stays byte for byte as it is. The anchor is the slug of the
	 * block name, made unique against every id already on the page, in
	 * document order: the same rules the editor applies.
	 *
	 * @param string $content Post content.
	 * @return string Content with an anchor on every blogroll block.
	 */
	public static function add( $content ) {
		if ( ! \is_string( $content ) || false === \strpos( $content, 'wp:blockroll/blogroll' ) ) {
			return $content;
		}

		// Collected only when a block actually needs an anchor, so a page
		// that has them all costs one pass over its block comments.
		$taken = null;

		return \preg_replace_callback(
			self::BLOCK,
			function ( $found ) use ( &$taken, $content ) {
				$attributes = isset( $found[1] ) && '' !== $found[1] ? \json_decode( $found[1], true ) : array();
				if ( ! \is_array( $attributes ) || ! empty( $attributes['anchor'] ) ) {
					return $found[0];
				}

				if ( null === $taken ) {
					$taken = self::taken( $content );
				}
				$anchor  = self::unique( self::slug( (string) ( $attributes['metadata']['name'] ?? '' ) ), $taken );
				$taken[] = $anchor;

				// Add the anchor to the JSON as it is, rather than encoding
				// the attributes again: PHP escapes slashes and non-ASCII
				// characters differently than the editor does.
				$json  = '{"anchor":' . \wp_json_encode( $anchor );
				$json .= $attributes ? ',' . \substr( $found[1], 1 ) : '}';

				return '<!-- wp:blockroll/blogroll ' . $json . ' ' . $found[2];
			},
			$content
		);
	}

	/**
	 * The slug of a block name, or the fallback for an empty one.
	 *
	 * @param string $name Block name.
	 * @return string Slug.
	 */
	public static function slug( $name ) {
		$slug = \sanitize_title( $name );
		return '' !== $slug ? $slug : self::FALLBACK;
	}

	/**
	 * A slug that is not taken yet, appending a counter if it is.
	 *
	 * @param string   $slug  Wanted slug.
	 * @param string[] $taken Slugs already in use.
	 * @return string Unique slug.
	 */
	public static function unique( $slug, $taken ) {
		$anchor = $slug;
		$i      = 0;
		while ( \in_array( $anchor, $taken, true ) ) {
			++$i;
			$anchor = $slug . '-' . $i;
		}
		return $anchor;
	}

	/**
	 * Every id already used on a page.
	 *
	 * Dynamic blocks keep their anchor in the block comment, static blocks
	 * in the id attribute of their markup, so both are collected.
	 *
	 * @param string $content Post content.
	 * @return string[] Ids in use.
	 */
	private static function taken( $content ) {
		$taken = array();
		if ( \preg_match_all( '/"anchor":"((?:[^"\\\\]|\\\\.)*)"/', $content, $matches ) ) {
			foreach ( $matches[1] as $anchor ) {
				$taken[] = \json_decode( '"' . $anchor . '"' );
			}
		}
		if ( \preg_match_all( '/\sid="([^"]*)"/', $content, $matches ) ) {
			$taken = \array_merge( $taken, $matches[1] );
		}
		return $taken;
	}
}
