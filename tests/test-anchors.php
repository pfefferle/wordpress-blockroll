<?php
/**
 * Anchor migration tests.
 *
 * @package Blockroll
 */

/**
 * Test the lazy migration that gives existing blogroll blocks an anchor.
 */
class Test_Anchors extends WP_UnitTestCase {
	const LINKS = '"links":[{"url":"https://a.example/","name":"A"}]';

	public function test_slug_of_a_name() {
		$this->assertSame( 'podcasts', \Blockroll\Anchors::slug( 'Podcasts' ) );
		$this->assertSame( 'podcasts-uber-musik', \Blockroll\Anchors::slug( 'Podcasts über Musik' ) );
		$this->assertSame( 'blogroll', \Blockroll\Anchors::slug( '' ) );
		$this->assertSame( 'blogroll', \Blockroll\Anchors::slug( '???' ) );
	}

	public function test_unique_appends_a_counter() {
		$this->assertSame( 'blogs', \Blockroll\Anchors::unique( 'blogs', array() ) );
		$this->assertSame( 'blogs-1', \Blockroll\Anchors::unique( 'blogs', array( 'blogs' ) ) );
		$this->assertSame( 'blogs-2', \Blockroll\Anchors::unique( 'blogs', array( 'blogs', 'blogs-1' ) ) );
	}

	public function test_migrate_adds_anchors_from_the_names_in_document_order() {
		$content = '<!-- wp:blockroll/blogroll {"metadata":{"name":"Blogs"},' . self::LINKS . '} /-->'
			. "\n\n" . '<!-- wp:blockroll/blogroll {"metadata":{"name":"Podcasts"},' . self::LINKS . '} /-->';
		$post    = self::factory()->post->create_and_get( array( 'post_content' => $content ) );

		$this->assertTrue( \Blockroll\Anchors::migrate( $post ) );

		$expected = '<!-- wp:blockroll/blogroll {"anchor":"blogs","metadata":{"name":"Blogs"},' . self::LINKS . '} /-->'
			. "\n\n" . '<!-- wp:blockroll/blogroll {"anchor":"podcasts","metadata":{"name":"Podcasts"},' . self::LINKS . '} /-->';
		$this->assertSame( $expected, $post->post_content, 'The passed object is updated.' );
		$this->assertSame( $expected, get_post( $post->ID )->post_content, 'The database is updated.' );
	}

	public function test_migrate_keeps_everything_else_byte_for_byte() {
		$content = "<!-- wp:paragraph -->\n<p>Hello &amp; welcome — “quotes”</p>\n<!-- /wp:paragraph -->\n\n"
			. "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\" id=\"podcasts\">Podcasts</h2>\n<!-- /wp:heading -->\n\n"
			. "<!-- wp:group {\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group\">"
			. '<!-- wp:blockroll/blogroll {"metadata":{"name":"Podcasts"},' . self::LINKS . '} /-->'
			. "</div>\n<!-- /wp:group -->\n\n"
			. '<!-- wp:blockroll/blogroll {"anchor":"mine","metadata":{"name":"Blogs"},' . self::LINKS . '} /-->' . "\n\n"
			. '<!-- wp:blockroll/blogroll {' . self::LINKS . '} /-->';
		$post    = self::factory()->post->create_and_get( array( 'post_content' => $content ) );

		$this->assertTrue( \Blockroll\Anchors::migrate( $post ) );

		// The heading already owns "podcasts", the second block keeps its own
		// anchor, and the unnamed one falls back to "blogroll".
		$expected = str_replace(
			array(
				'<!-- wp:blockroll/blogroll {"metadata":{"name":"Podcasts"},',
				'<!-- wp:blockroll/blogroll {' . self::LINKS,
			),
			array(
				'<!-- wp:blockroll/blogroll {"anchor":"podcasts-1","metadata":{"name":"Podcasts"},',
				'<!-- wp:blockroll/blogroll {"anchor":"blogroll",' . self::LINKS,
			),
			$content
		);
		$this->assertSame( $expected, $post->post_content );
	}

	public function test_migrate_handles_a_block_without_attributes() {
		$post = self::factory()->post->create_and_get( array( 'post_content' => '<!-- wp:blockroll/blogroll /-->' ) );
		$this->assertTrue( \Blockroll\Anchors::migrate( $post ) );
		$this->assertSame( '<!-- wp:blockroll/blogroll {"anchor":"blogroll"} /-->', $post->post_content );
	}

	public function test_two_unnamed_blocks_get_different_anchors() {
		$content = '<!-- wp:blockroll/blogroll {' . self::LINKS . '} /--><!-- wp:blockroll/blogroll {' . self::LINKS . '} /-->';
		$post    = self::factory()->post->create_and_get( array( 'post_content' => $content ) );
		\Blockroll\Anchors::migrate( $post );
		$this->assertStringContainsString( '{"anchor":"blogroll",', $post->post_content );
		$this->assertStringContainsString( '{"anchor":"blogroll-1",', $post->post_content );
	}

	public function test_migrate_is_idempotent_and_leaves_complete_pages_alone() {
		$content = '<!-- wp:blockroll/blogroll {"anchor":"x","metadata":{"name":"Blogs"},' . self::LINKS . '} /-->';
		$post    = self::factory()->post->create_and_get( array( 'post_content' => $content ) );
		$this->assertFalse( \Blockroll\Anchors::migrate( $post ) );
		$this->assertSame( $content, $post->post_content );

		$post = self::factory()->post->create_and_get( array( 'post_content' => '<!-- wp:blockroll/blogroll {' . self::LINKS . '} /-->' ) );
		$this->assertTrue( \Blockroll\Anchors::migrate( $post ) );
		$migrated = $post->post_content;
		$this->assertFalse( \Blockroll\Anchors::migrate( $post ) );
		$this->assertSame( $migrated, $post->post_content );
	}

	public function test_migrate_does_not_create_a_revision_or_touch_the_modified_date() {
		$post = self::factory()->post->create_and_get( array( 'post_content' => '<!-- wp:blockroll/blogroll {' . self::LINKS . '} /-->' ) );
		$modified  = $post->post_modified_gmt;
		$revisions = count( wp_get_post_revisions( $post->ID ) );
		\Blockroll\Anchors::migrate( $post );
		$fresh = get_post( $post->ID );
		$this->assertSame( $modified, $fresh->post_modified_gmt );
		$this->assertCount( $revisions, wp_get_post_revisions( $post->ID ) );
	}

	public function test_migrate_skips_revisions_and_posts_without_the_block() {
		$post = self::factory()->post->create_and_get( array( 'post_content' => '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->' ) );
		$this->assertFalse( \Blockroll\Anchors::migrate( $post ) );

		$parent   = self::factory()->post->create( array( 'post_content' => '<!-- wp:blockroll/blogroll {' . self::LINKS . '} /-->' ) );
		$revision = get_post( wp_save_post_revision( $parent ) );
		$this->assertFalse( \Blockroll\Anchors::migrate( $revision ) );
	}

	public function test_a_page_view_migrates_before_rendering() {
		$id = self::factory()->post->create( array( 'post_content' => '<!-- wp:blockroll/blogroll {"metadata":{"name":"Podcasts"},' . self::LINKS . '} /-->' ) );
		$this->go_to( get_permalink( $id ) );
		\Blockroll\Anchors::migrate_queried_post();
		$this->assertStringContainsString( '"anchor":"podcasts"', get_queried_object()->post_content );
		$this->assertStringContainsString( '"anchor":"podcasts"', get_post( $id )->post_content );
	}

	public function test_a_preview_is_not_migrated() {
		$id = self::factory()->post->create( array( 'post_content' => '<!-- wp:blockroll/blogroll {' . self::LINKS . '} /-->' ) );
		$this->go_to( add_query_arg( 'preview', 'true', get_permalink( $id ) ) );
		\Blockroll\Anchors::migrate_queried_post();
		$this->assertStringNotContainsString( 'anchor', get_post( $id )->post_content );
	}
}
