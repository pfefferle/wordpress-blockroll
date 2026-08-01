<?php
/**
 * OPML tests.
 *
 * @package Blockroll
 */

/**
 * Test OPML generation and the discovery link.
 */
class Test_Opml extends WP_UnitTestCase {
	const BLOCK = '<!-- wp:blockroll/blogroll {"links":[{"url":"https://a.example/","name":"A","feedUrl":"https://a.example/feed/","description":"desc"}]} /-->';

	public function test_extract_links() {
		$post  = self::factory()->post->create_and_get( array( 'post_content' => self::BLOCK ) );
		$links = \Blockroll\Opml::extract_links( $post );
		$this->assertCount( 1, $links );
		$this->assertSame( 'https://a.example/', $links[0]['url'] );
	}

	public function test_page_opml_contains_outline() {
		$post    = self::factory()->post->create_and_get( array( 'post_content' => self::BLOCK ) );
		$xml     = \Blockroll\Opml::for_post( $post );
		$doc     = new SimpleXMLElement( $xml );
		$outline = $doc->body->outline[0];
		$this->assertSame( 'A', (string) $outline['text'] );
		$this->assertSame( 'https://a.example/feed/', (string) $outline['xmlUrl'] );
		$this->assertSame( 'https://a.example/', (string) $outline['htmlUrl'] );
		$this->assertSame( 'rss', (string) $outline['type'] );
	}

	public function test_directory_lists_pages_not_links() {
		self::factory()->post->create( array( 'post_content' => self::BLOCK ) );
		$xml     = \Blockroll\Opml::directory();
		$doc     = new SimpleXMLElement( $xml );
		$outline = $doc->body->outline[0];
		$this->assertSame( 'link', (string) $outline['type'] );
		$this->assertStringContainsString( 'feed/opml', (string) $outline['url'] );
		$this->assertStringNotContainsString( 'a.example', $xml ); // No inlined links.
	}

	public function test_discovery_link_on_singular_with_block() {
		$id = self::factory()->post->create( array( 'post_content' => self::BLOCK ) );
		$this->go_to( get_permalink( $id ) );
		ob_start();
		\Blockroll\Opml::discovery_link();
		$head = ob_get_clean();
		$this->assertStringContainsString( 'rel="blogroll"', $head );
		$this->assertStringContainsString( 'feed/opml', $head );
	}

	public function test_front_page_advertises_blogroll_pages() {
		$id = self::factory()->post->create( array( 'post_content' => self::BLOCK ) );
		$this->go_to( home_url( '/' ) );
		ob_start();
		\Blockroll\Opml::discovery_link();
		$head = ob_get_clean();
		// The front page points at the blogroll page's own OPML, not the directory.
		$this->assertStringContainsString( 'rel="blogroll"', $head );
		$this->assertStringContainsString( trailingslashit( get_permalink( $id ) ) . 'feed/opml', $head );
	}

	public function test_rss_head_advertises_blogroll() {
		$id = self::factory()->post->create( array( 'post_content' => self::BLOCK ) );
		ob_start();
		\Blockroll\Opml::rss_blogroll();
		$head = ob_get_clean();
		$this->assertStringContainsString( '<source:blogroll>', $head );
		$this->assertStringContainsString( trailingslashit( get_permalink( $id ) ) . 'feed/opml', $head );
	}

	public function test_rss_namespace() {
		ob_start();
		\Blockroll\Opml::rss_namespace();
		$this->assertStringContainsString( 'xmlns:source="http://source.scripting.com/"', ob_get_clean() );
	}

	public function test_singular_without_block_has_no_discovery_link() {
		$id = self::factory()->post->create( array( 'post_content' => 'plain' ) );
		$this->go_to( get_permalink( $id ) );
		ob_start();
		\Blockroll\Opml::discovery_link();
		$this->assertSame( '', ob_get_clean() );
	}
}
