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

	const TWO_NAMED_BLOCKS = '<!-- wp:blockroll/blogroll {"metadata":{"name":"Blogs"},"links":[{"url":"https://a.example/","name":"A","feedUrl":"https://a.example/feed/"}]} /--><!-- wp:blockroll/blogroll {"metadata":{"name":"Podcasts"},"links":[{"url":"https://b.example/","name":"B","feedUrl":"https://b.example/feed/"}]} /-->';

	public function test_one_blogroll_stays_flat() {
		$post = self::factory()->post->create_and_get( array( 'post_content' => self::BLOCK ) );
		ob_start();
		\Blockroll\Opml::for_post( $post );
		$doc = new SimpleXMLElement( ob_get_clean() );
		$this->assertCount( 1, $doc->body->outline );
		$this->assertSame( 'A', (string) $doc->body->outline[0]['text'] );
		$this->assertCount( 0, $doc->body->outline[0]->outline );
	}

	public function test_two_blogrolls_become_groups() {
		$post = self::factory()->post->create_and_get( array( 'post_content' => self::TWO_NAMED_BLOCKS ) );
		ob_start();
		\Blockroll\Opml::for_post( $post );
		$doc = new SimpleXMLElement( ob_get_clean() );
		$this->assertCount( 2, $doc->body->outline );
		$this->assertSame( 'Blogs', (string) $doc->body->outline[0]['text'] );
		$this->assertSame( 'Podcasts', (string) $doc->body->outline[1]['text'] );
		$this->assertSame( 'A', (string) $doc->body->outline[0]->outline[0]['text'] );
		$this->assertSame( 'https://a.example/feed/', (string) $doc->body->outline[0]->outline[0]['xmlUrl'] );
		$this->assertSame( 'B', (string) $doc->body->outline[1]->outline[0]['text'] );
	}

	public function test_unnamed_blogroll_falls_back_to_the_block_name() {
		$content = '<!-- wp:blockroll/blogroll {"metadata":{"name":"Blogs"},"links":[{"url":"https://a.example/","name":"A"}]} /--><!-- wp:blockroll/blogroll {"links":[{"url":"https://c.example/","name":"C"}]} /-->';
		$post    = self::factory()->post->create_and_get( array( 'post_content' => $content ) );
		ob_start();
		\Blockroll\Opml::for_post( $post );
		$doc = new SimpleXMLElement( ob_get_clean() );
		$this->assertCount( 2, $doc->body->outline );
		$this->assertSame( 'Blogs', (string) $doc->body->outline[0]['text'] );
		$this->assertSame( 'Blogroll', (string) $doc->body->outline[1]['text'] );
		$this->assertSame( 'C', (string) $doc->body->outline[1]->outline[0]['text'] );
		$this->assertSame( 'https://c.example/', (string) $doc->body->outline[1]->outline[0]['htmlUrl'] );
	}

	public function test_extract_links_still_returns_every_link_flat() {
		$post  = self::factory()->post->create_and_get( array( 'post_content' => self::TWO_NAMED_BLOCKS ) );
		$links = \Blockroll\Opml::extract_links( $post );
		$this->assertCount( 2, $links );
		$this->assertSame( 'https://a.example/', $links[0]['url'] );
		$this->assertSame( 'https://b.example/', $links[1]['url'] );
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

	public function test_opml_suffix_is_an_alias_for_the_query_var() {
		$id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_name'    => 'blogroll',
				'post_content' => self::BLOCK,
			)
		);
		$this->set_permalink_structure( '/%postname%/' );
		$this->go_to( home_url( '/blogroll.opml' ) );
		$this->assertFalse( is_404() );
		$this->assertSame( $id, get_queried_object_id() );
		$this->assertSame( '', get_query_var( 'opml', null ) );
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

	public function test_discovery_link_has_html_alternative() {
		$id = self::factory()->post->create( array( 'post_content' => self::BLOCK ) );
		$this->go_to( get_permalink( $id ) );
		ob_start();
		\Blockroll\Opml::discovery_link();
		$head = ob_get_clean();
		$this->assertStringContainsString(
			'<link rel="blogroll" type="text/html" href="' . esc_url( get_permalink( $id ) ) . '"',
			$head
		);
		$this->assertSame( 1, substr_count( $head, 'type="text/xml"' ) );
		$this->assertSame( 1, substr_count( $head, 'type="text/html"' ) );
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

	/**
	 * A static front page advertises the blogroll pages too.
	 *
	 * It is `is_singular()` as well, so it used to fall into the branch for
	 * single posts and, without a blogroll of its own, print nothing.
	 */
	public function test_static_front_page_advertises_blogroll_pages() {
		$id    = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => self::BLOCK,
			)
		);
		$front = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => 'plain',
			)
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front );

		$this->go_to( get_permalink( $front ) );
		ob_start();
		\Blockroll\Opml::discovery_link();
		$head = ob_get_clean();

		$this->assertTrue( is_front_page() );
		$this->assertStringContainsString( esc_url( \Blockroll\Opml::opml_url( get_post( $id ) ) ), $head );
	}

	/**
	 * A blogroll page that is the front page is advertised once, not twice.
	 */
	public function test_front_page_with_own_blogroll_is_not_duplicated() {
		$front = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => self::BLOCK,
			)
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front );

		$this->go_to( get_permalink( $front ) );
		ob_start();
		\Blockroll\Opml::discovery_link();

		$head = ob_get_clean();
		$this->assertSame( 1, substr_count( $head, 'type="text/xml"' ) );
		$this->assertSame( 1, substr_count( $head, 'type="text/html"' ) );
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

	const TWO_ANCHORED_BLOCKS = '<!-- wp:blockroll/blogroll {"anchor":"blogs","metadata":{"name":"Blogs"},"links":[{"url":"https://a.example/","name":"A","feedUrl":"https://a.example/feed/"}]} /--><!-- wp:blockroll/blogroll {"anchor":"podcasts","metadata":{"name":"Podcasts"},"links":[{"url":"https://b.example/","name":"B","feedUrl":"https://b.example/feed/"}]} /-->';

	public function test_extract_groups_keeps_the_anchor_and_derives_a_missing_one() {
		$post   = self::factory()->post->create_and_get( array( 'post_content' => self::TWO_ANCHORED_BLOCKS ) );
		$groups = \Blockroll\Opml::extract_groups( $post );
		$this->assertSame( 'blogs', $groups[0]['anchor'] );
		$this->assertSame( 'podcasts', $groups[1]['anchor'] );

		// A page saved before anchors existed reads as if it had them.
		$post   = self::factory()->post->create_and_get( array( 'post_content' => self::TWO_NAMED_BLOCKS ) );
		$groups = \Blockroll\Opml::extract_groups( $post );
		$this->assertSame( 'blogs', $groups[0]['anchor'] );
		$this->assertSame( 'podcasts', $groups[1]['anchor'] );
		$this->assertStringNotContainsString( 'anchor', $post->post_content, 'Reading does not write.' );
	}

	public function test_group_opml_url_uses_its_own_query_var() {
		$post = self::factory()->post->create_and_get( array( 'post_content' => self::TWO_ANCHORED_BLOCKS ) );
		$url  = \Blockroll\Opml::opml_url( $post, 'podcasts' );
		$this->assertStringStartsWith( \Blockroll\Opml::opml_url( $post ), $url );
		$this->assertStringContainsString( \Blockroll\Opml::GROUP . '=podcasts', $url );
	}

	public function test_one_group_is_served_flat_with_its_own_title() {
		$post = self::factory()->post->create_and_get( array( 'post_content' => self::TWO_ANCHORED_BLOCKS ) );
		ob_start();
		\Blockroll\Opml::for_post( $post, 'podcasts' );
		$doc = new SimpleXMLElement( ob_get_clean() );
		$this->assertCount( 1, $doc->body->outline );
		$this->assertSame( 'B', (string) $doc->body->outline[0]['text'] );
		$this->assertCount( 0, $doc->body->outline[0]->outline );
		$this->assertStringContainsString( 'Podcasts', (string) $doc->head->title );
		$this->assertStringContainsString( get_the_title( $post ), (string) $doc->head->title );
	}

	public function test_unknown_group_falls_back_to_the_whole_page() {
		$post = self::factory()->post->create_and_get( array( 'post_content' => self::TWO_ANCHORED_BLOCKS ) );
		ob_start();
		\Blockroll\Opml::for_post( $post, 'nope' );
		$doc = new SimpleXMLElement( ob_get_clean() );
		$this->assertCount( 2, $doc->body->outline );
		$this->assertSame( \Blockroll\Opml::title( $post ), (string) $doc->head->title );
	}

	public function test_group_query_var_works_with_the_opml_suffix() {
		$id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_name'    => 'links',
				'post_content' => self::TWO_ANCHORED_BLOCKS,
			)
		);
		$this->set_permalink_structure( '/%postname%/' );
		$this->go_to( home_url( '/links.opml?' . \Blockroll\Opml::GROUP . '=podcasts' ) );
		$this->assertFalse( is_404() );
		$this->assertSame( $id, get_queried_object_id() );
		$this->assertSame( '', get_query_var( \Blockroll\Opml::QUERY_VAR, null ) );
		$this->assertSame( 'podcasts', get_query_var( \Blockroll\Opml::GROUP ) );
		$this->set_permalink_structure( '' );
	}

	public function test_anchored_groups_get_their_own_discovery_links() {
		$id = self::factory()->post->create( array( 'post_content' => self::TWO_ANCHORED_BLOCKS ) );
		$this->go_to( get_permalink( $id ) );
		ob_start();
		\Blockroll\Opml::discovery_link();
		$head = ob_get_clean();
		$post = get_post( $id );
		$this->assertSame( 3, substr_count( $head, 'type="text/xml"' ) );
		$this->assertSame( 3, substr_count( $head, 'type="text/html"' ) );
		$this->assertStringContainsString( 'href="' . esc_url( \Blockroll\Opml::opml_url( $post, 'podcasts' ) ) . '"', $head );
		$this->assertStringContainsString( 'href="' . esc_url( get_permalink( $post ) . '#podcasts' ) . '"', $head );
		$this->assertStringContainsString( 'title="Podcasts', $head );
	}

	public function test_unwritten_anchors_still_get_discovery_links() {
		$id = self::factory()->post->create( array( 'post_content' => self::TWO_NAMED_BLOCKS ) );
		$this->go_to( get_permalink( $id ) );
		ob_start();
		\Blockroll\Opml::discovery_link();
		$head = ob_get_clean();
		$this->assertSame( 3, substr_count( $head, 'type="text/xml"' ) );
		$this->assertStringContainsString( '#podcasts"', $head );
	}
}
