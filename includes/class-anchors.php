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
 * theirs here, lazily, the first time the page or its OPML is loaded.
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
	 * Migrate the page before it is rendered or served as OPML.
	 */
	public static function register() {
		// Before Opml::render() at 9, so the OPML sees the anchors too.
		\add_action( 'template_redirect', array( self::class, 'migrate_queried_post' ), 8 );
	}

	/**
	 * Migrate the post of a singular request.
	 *
	 * Previews render an autosave, which is never migrated, so the page
	 * behind it is left alone as well.
	 */
	public static function migrate_queried_post() {
		if ( ! \is_singular() || \is_preview() ) {
			return;
		}
		$post = \get_queried_object();
		if ( ! $post instanceof \WP_Post || ! self::migrate( $post ) ) {
			return;
		}

		// A page request holds the post twice: the queried object comes from
		// get_page_by_path(), the loop from the posts query. Bring the loop's
		// copies up to date too, so this very view renders the anchors.
		global $wp_query;
		$copies   = $wp_query->posts;
		$copies[] = $wp_query->post;
		$copies[] = $GLOBALS['post'] ?? null;
		foreach ( $copies as $copy ) {
			if ( $copy instanceof \WP_Post && $copy->ID === $post->ID ) {
				$copy->post_content = $post->post_content;
			}
		}
	}

	/**
	 * Write missing anchors into the blogroll blocks of a post.
	 *
	 * Only the block comments of blogroll blocks are touched, everything
	 * else stays byte for byte as it was. The content is written directly,
	 * the way core's upgrade routines do it: no revision, no modified date,
	 * no save hooks for a change nobody made. The passed object is updated
	 * too, so the request that triggered the migration already renders the
	 * anchors.
	 *
	 * @param \WP_Post $post Post object.
	 * @return bool Whether anything was written.
	 */
	public static function migrate( $post ) {
		if ( 'revision' === $post->post_type || ! \has_block( 'blockroll/blogroll', $post ) ) {
			return false;
		}

		$taken   = self::taken( $post->post_content );
		$changed = false;
		$content = \preg_replace_callback(
			self::BLOCK,
			function ( $found ) use ( &$taken, &$changed ) {
				$attributes = isset( $found[1] ) && '' !== $found[1] ? \json_decode( $found[1], true ) : array();
				if ( ! \is_array( $attributes ) || ! empty( $attributes['anchor'] ) ) {
					return $found[0];
				}

				$anchor  = self::unique( self::slug( (string) ( $attributes['metadata']['name'] ?? '' ) ), $taken );
				$taken[] = $anchor;
				$changed = true;

				// Add the anchor to the JSON as it is, rather than encoding
				// the attributes again: PHP escapes slashes and non-ASCII
				// characters differently than the editor does.
				$json  = '{"anchor":' . \wp_json_encode( $anchor );
				$json .= $attributes ? ',' . \substr( $found[1], 1 ) : '}';

				return '<!-- wp:blockroll/blogroll ' . $json . ' ' . $found[2];
			},
			$post->post_content
		);

		if ( ! $changed || null === $content ) {
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
		return \array_values( \array_unique( \array_filter( $taken ) ) );
	}
}
