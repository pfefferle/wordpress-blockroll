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
		$post = self::factory()->post->create_and_get( array( 'post_content' => self::BLOCK ) );
		ob_start();
		\Blockroll\Opml::for_post( $post );
		$xml     = ob_get_clean();
		$doc     = new SimpleXMLElement( $xml );
		$outline = $doc->body->outline[0];
		$this->assertSame( 'A', (string) $outline['text'] );
		$this->assertSame( 'https://a.example/feed/', (string) $outline['xmlUrl'] );
		$this->assertSame( 'https://a.example/', (string) $outline['htmlUrl'] );
		$this->assertSame( 'rss', (string) $outline['type'] );
	}

	public function test_directory_lists_pages_not_links() {
		$id = self::factory()->post->create( array( 'post_content' => self::BLOCK ) );
		ob_start();
		\Blockroll\Opml::directory();
		$xml     = ob_get_clean();
		$doc     = new SimpleXMLElement( $xml );
		$outline = $doc->body->outline[0];
		$this->assertSame( 'include', (string) $outline['type'] );
		$this->assertSame( \Blockroll\Opml::opml_url( get_post( $id ) ), (string) $outline['url'] );
		$this->assertStringNotContainsString( 'a.example', $xml ); // No inlined links.
	}

	public function test_opml_without_author_has_no_empty_owner() {
		$id = self::factory()->post->create(
			array(
				'post_content' => self::BLOCK,
				'post_author'  => 0,
			)
		);
		ob_start();
		\Blockroll\Opml::for_post( get_post( $id ) );
		$xml = ob_get_clean();
		$this->assertStringNotContainsString( '<ownerName>', $xml );
		$this->assertInstanceOf( SimpleXMLElement::class, new SimpleXMLElement( $xml ) );
	}

	public function test_well_known_url_asks_for_the_directory() {
		$this->set_permalink_structure( '/%postname%/' );
		$this->go_to( home_url( '/' . \Blockroll\Opml::WELL_KNOWN ) );
		$this->assertSame( \Blockroll\Opml::DIRECTORY, get_query_var( 'opml' ) );
		$this->assertFalse( is_404() );
		$this->set_permalink_structure( '' );
	}

	public function test_directory_has_date_modified() {
		$id = self::factory()->post->create( array( 'post_content' => self::BLOCK ) );
		ob_start();
		\Blockroll\Opml::directory();
		$xml = ob_get_clean();
		$doc = new SimpleXMLElement( $xml );
		$this->assertSame(
			get_post_modified_time( 'r', true, get_post( $id ) ),
			(string) $doc->head->dateModified
		);
		$this->assertSame( get_bloginfo( 'name' ), (string) $doc->head->ownerName );
		$this->assertSame( home_url( '/' ), (string) $doc->head->ownerId );
	}

	public function test_discovery_link_on_singular_with_block() {
		$id = self::factory()->post->create( array( 'post_content' => self::BLOCK ) );
		$this->go_to( get_permalink( $id ) );
		ob_start();
		\Blockroll\Opml::discovery_link();
		$head = ob_get_clean();
		$this->assertStringContainsString( 'rel="blogroll"', $head );
		$this->assertStringContainsString( esc_url( \Blockroll\Opml::opml_url( get_post( $id ) ) ), $head );
	}

	public function test_front_page_advertises_blogroll_pages() {
		$id = self::factory()->post->create( array( 'post_content' => self::BLOCK ) );
		$this->go_to( home_url( '/' ) );
		ob_start();
		\Blockroll\Opml::discovery_link();
		$head = ob_get_clean();
		// The front page points at the blogroll page's own OPML, not the directory.
		$this->assertStringContainsString( 'rel="blogroll"', $head );
		$this->assertStringContainsString( esc_url( \Blockroll\Opml::opml_url( get_post( $id ) ) ), $head );
	}

	public function test_feed_head_advertises_blogroll() {
		$id = self::factory()->post->create( array( 'post_content' => self::BLOCK ) );
		ob_start();
		\Blockroll\Opml::feed_blogroll();
		$head = ob_get_clean();
		$this->assertStringContainsString( '<source:blogroll>', $head );
		$this->assertStringContainsString( esc_url( \Blockroll\Opml::opml_url( get_post( $id ) ) ), $head );
	}

	public function test_feed_namespace() {
		ob_start();
		\Blockroll\Opml::feed_namespace_start();
		\Blockroll\Opml::feed_namespace();
		$this->assertStringContainsString( 'xmlns:source="http://source.scripting.com/"', ob_get_clean() );
	}

	public function test_feed_namespace_not_duplicated() {
		ob_start();
		\Blockroll\Opml::feed_namespace_start();
		echo 'xmlns:source="http://source.scripting.com/"';
		\Blockroll\Opml::feed_namespace();
		$this->assertSame( 1, substr_count( ob_get_clean(), 'xmlns:source' ) );
	}

	/**
	 * The hooks run as WordPress calls them, without a PHP warning.
	 *
	 * `do_action()` passes an empty string to every callback, so hooking
	 * `ob_start` directly made PHP complain about an invalid callback.
	 */
	public function test_feed_ns_hooks_do_not_warn() {
		ob_start();
		do_action( 'rss2_ns' );
		$this->assertSame( 1, substr_count( ob_get_clean(), 'xmlns:source' ) );
	}

	/**
	 * A buffer that is not ours is left alone.
	 *
	 * Without the start callback there is nothing to clean up, and
	 * cleaning anyway would swallow another plugin's output, like a
	 * caching plugin's feed cache.
	 */
	public function test_feed_namespace_keeps_foreign_buffer() {
		ob_start();
		echo 'not ours';
		\Blockroll\Opml::feed_namespace();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'not ours', $output );
		$this->assertStringContainsString( 'xmlns:source="http://source.scripting.com/"', $output );
	}

	public function test_render_falls_through_without_blogroll() {
		$id = self::factory()->post->create( array( 'post_content' => 'plain' ) );
		$this->go_to( add_query_arg( 'opml', '', get_permalink( $id ) ) );
		ob_start();
		\Blockroll\Opml::render();
		// No OPML, no exit: the normal page loads.
		$this->assertSame( '', ob_get_clean() );
		$this->assertFalse( is_404() );
	}

	public function test_singular_without_block_has_no_discovery_link() {
		$id = self::factory()->post->create( array( 'post_content' => 'plain' ) );
		$this->go_to( get_permalink( $id ) );
		ob_start();
		\Blockroll\Opml::discovery_link();
		$this->assertSame( '', ob_get_clean() );
	}
}
