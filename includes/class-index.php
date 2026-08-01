<?php
/**
 * Private taxonomy that indexes posts containing a blogroll block.
 *
 * @package Blockroll
 */

namespace Blockroll;

/**
 * Keep track of which posts contain a blogroll block.
 *
 * The taxonomy is an index only; the link data lives in the block attributes.
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
		if ( \has_block( 'blockroll/blogroll', $post ) ) {
			\wp_set_object_terms( $post_id, self::TERM, self::TAXONOMY );
		} else {
			\wp_set_object_terms( $post_id, array(), self::TAXONOMY );
		}
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
