<?php
/**
 * Render tests.
 *
 * @package Blockroll
 */

/**
 * Test the frontend render output.
 */
class Test_Render extends WP_UnitTestCase {
	/**
	 * Render the block with the given attributes.
	 *
	 * @param array $attrs Block attributes.
	 * @return string Rendered HTML.
	 */
	private function render_block_html( $attrs ) {
		$block = array(
			'blockName'    => 'blockroll/blogroll',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
		return render_block( $block );
	}

	public function test_renders_h_card_with_xfn() {
		$html = $this->render_block_html(
			array(
				'links' => array(
					array(
						'url'         => 'https://example.com/',
						'name'        => 'Example',
						'description' => 'A blog',
						'feedUrl'     => 'https://example.com/feed/',
						'photo'       => 'https://example.com/a.jpg',
						'xfn'         => array( 'friend', 'met' ),
						'added'       => '2026-08-01',
					),
				),
			)
		);
		$this->assertStringContainsString( 'class="h-card"', $html );
		$this->assertStringContainsString( 'rel="friend met"', $html );
		$this->assertStringContainsString( 'class="u-url p-name"', $html );
		$this->assertStringContainsString( 'class="p-note"', $html );
		$this->assertStringContainsString( 'class="u-feed"', $html );
		$this->assertStringContainsString( 'class="u-photo"', $html );
	}

	public function test_sorts_by_name_by_default() {
		$html = $this->render_block_html(
			array(
				'links' => array(
					array(
						'url'  => 'https://b.example/',
						'name' => 'Beta',
					),
					array(
						'url'  => 'https://a.example/',
						'name' => 'alpha',
					),
				),
			)
		);
		$this->assertLessThan( strpos( $html, 'Beta' ), strpos( $html, 'alpha' ) );
	}

	public function test_no_photo_no_img_and_no_rel_attr_when_no_xfn() {
		$html = $this->render_block_html(
			array(
				'links'       => array(
					array(
						'url'  => 'https://a.example/',
						'name' => 'A',
					),
				),
				'showAvatars' => true,
			)
		);
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( 'rel=""', $html );
	}

	public function test_escapes_output() {
		$html = $this->render_block_html(
			array(
				'links' => array(
					array(
						'url'  => 'https://a.example/',
						'name' => '<script>x</script>',
					),
				),
			)
		);
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_controls_hidden_without_js() {
		$html = $this->render_block_html(
			array(
				'links' => array(
					array(
						'url'  => 'https://a.example/',
						'name' => 'A',
					),
				),
			)
		);
		$this->assertStringContainsString( 'class="blockroll-controls" hidden', $html );
	}

	public function test_empty_links_renders_nothing() {
		$this->assertSame( '', trim( $this->render_block_html( array( 'links' => array() ) ) ) );
	}
}
