<?php
/**
 * Private taxonomy that indexes the posts with a blogroll to subscribe to.
 *
 * @package Blockroll
 */

namespace Blockroll;

/**
 * Keep track of which posts offer a blogroll, so the directory and the
 * front page can point at them without reading every post.
 *
 * A post is in the index when it has a list that is part of the file of
 * its page; a page of unlisted lists has nothing to offer and stays out.
 * The taxonomy is an index only; the link data lives in the block
 * attributes.
 */
class Index {
	const TAXONOMY = 'blockroll_has';
	const TERM     = 'blogroll';

	/**
	 * Register the taxonomy and keep it in sync on save.
	 */
	public static function register() {
		\register_taxonomy(
			self::TAXONOMY,
			array( 'post', 'page' ),
			array(
				'public'       => false,
				'show_ui'      => false,
				'show_in_rest' => false,
				'rewrite'      => false,
				'hierarchical' => false,
			)
		);
		\add_action( 'save_post', array( self::class, 'sync' ), 10, 2 );
	}

	/**
	 * Add or remove the index term depending on the post content.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public static function sync( $post_id, $post ) {
		if ( \wp_is_post_revision( $post_id ) || ! \is_object_in_taxonomy( $post->post_type, self::TAXONOMY ) ) {
			return;
		}

		\wp_set_object_terms( $post_id, self::has_listed_blogroll( $post ) ? self::TERM : array(), self::TAXONOMY );
	}

	/**
	 * Whether a post contains a blogroll block.
	 *
	 * A cheap string search, no parsing: this runs on every singular
	 * request. Whether any of the lists is offered for subscription is a
	 * question for has_listed_blogroll().
	 *
	 * @param \WP_Post|int $post Post.
	 * @return bool True when it does.
	 */
	public static function has_blogroll( $post ) {
		$post = \get_post( $post );
		return $post && \has_block( 'blockroll/blogroll', $post );
	}

	/**
	 * Whether a post has a file of its own, a listed list.
	 *
	 * An unlisted list keeps out of the file of its page and can still be
	 * subscribed to on its own, so this is not the same question as
	 * whether the page has a blogroll at all. Parses the content, so it is
	 * asked on save only; what the directory lists is the index this
	 * writes.
	 *
	 * @param \WP_Post|int $post Post.
	 * @return bool True when at least one list is in the file of the page.
	 */
	public static function has_listed_blogroll( $post ) {
		$post = \get_post( $post );
		return self::has_blogroll( $post ) && (bool) Opml::listed_groups( $post );
	}

	/**
	 * The queried post of a singular request, when it has a blogroll.
	 *
	 * @return \WP_Post|null Post with a blogroll block, or null.
	 */
	public static function queried_post() {
		if ( ! \is_singular() ) {
			return null;
		}

		$post = \get_queried_object();

		return self::has_blogroll( $post ) ? $post : null;
	}

	/**
	 * All published posts that contain a blogroll block.
	 *
	 * @return \WP_Post[] Posts.
	 */
	public static function get_posts() {
		return \get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => self::TAXONOMY,
						'field'    => 'slug',
						'terms'    => self::TERM,
					),
				),
			)
		);
	}
}
