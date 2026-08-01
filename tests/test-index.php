<?php
/**
 * Index taxonomy tests.
 *
 * @package Blockroll
 */

/**
 * Test the blockroll_has index taxonomy.
 */
class Test_Index extends WP_UnitTestCase {
	const BLOCK = '<!-- wp:blockroll/blogroll {"links":[{"url":"https://a.example/","name":"A"}]} /-->';

	public function test_term_added_on_save_with_block() {
		$id = self::factory()->post->create( array( 'post_content' => self::BLOCK ) );
		$this->assertTrue( has_term( 'blogroll', \Blockroll\Index::TAXONOMY, $id ) );
	}

	public function test_term_removed_when_block_removed() {
		$id = self::factory()->post->create( array( 'post_content' => self::BLOCK ) );
		wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => 'no block anymore',
			)
		);
		$this->assertFalse( has_term( 'blogroll', \Blockroll\Index::TAXONOMY, $id ) );
	}

	public function test_get_posts_returns_only_published_tagged() {
		$id = self::factory()->post->create( array( 'post_content' => self::BLOCK ) );
		self::factory()->post->create( array( 'post_content' => 'plain' ) );
		self::factory()->post->create(
			array(
				'post_content' => self::BLOCK,
				'post_status'  => 'draft',
			)
		);
		$found = wp_list_pluck( \Blockroll\Index::get_posts(), 'ID' );
		$this->assertSame( array( $id ), $found );
	}

	public function test_taxonomy_not_public() {
		$tax = get_taxonomy( \Blockroll\Index::TAXONOMY );
		$this->assertFalse( $tax->public );
		$this->assertFalse( $tax->show_ui );
	}
}
