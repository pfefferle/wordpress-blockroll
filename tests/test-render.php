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

	const TWO_DATED_LINKS = array(
		array(
			'url'   => 'https://a.example/',
			'name'  => 'A',
			'added' => '2026-01-01',
		),
		array(
			'url'   => 'https://b.example/',
			'name'  => 'B',
			'added' => '2026-06-01',
		),
	);

	public function test_sort_links_rendered() {
		$html = $this->render_block_html( array( 'links' => self::TWO_DATED_LINKS ) );
		$this->assertStringContainsString( 'blockroll-sort', $html );
		$this->assertStringContainsString( 'blockroll-sort=added', $html );
	}

	public function test_manual_sort_only_offered_when_default() {
		$html = $this->render_block_html( array( 'links' => self::TWO_DATED_LINKS ) );
		$this->assertStringNotContainsString( 'blockroll-sort=manual', $html );

		$html = $this->render_block_html(
			array(
				'sortBy' => 'manual',
				'links'  => self::TWO_DATED_LINKS,
			)
		);
		$this->assertStringContainsString( 'aria-current="true">Default', $html );
	}

	public function test_sort_can_be_disabled() {
		set_query_var( 'blockroll-sort', 'added' );
		$html = $this->render_block_html(
			array(
				'showSort' => false,
				'links'    => self::TWO_DATED_LINKS,
			)
		);
		set_query_var( 'blockroll-sort', null );
		$this->assertStringNotContainsString( 'blockroll-sort', $html );
		// The query var is ignored, the default name order stays.
		$this->assertLessThan( strpos( $html, 'b.example' ), strpos( $html, 'a.example' ) );
	}

	public function test_opml_link_toggleable() {
		global $post;
		$post  = self::factory()->post->create_and_get(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$attrs = array(
			'links' => array(
				array(
					'url'  => 'https://a.example/',
					'name' => 'A',
				),
			),
		);

		$html = $this->render_block_html( $attrs );
		$this->assertStringContainsString( 'feed/opml', $html );

		$attrs['showOpml'] = false;
		$html              = $this->render_block_html( $attrs );
		$this->assertStringNotContainsString( 'feed/opml', $html );
	}

	public function test_no_sort_ui_for_single_option() {
		// No dates, default name sort: only one option, so no controls at all.
		$html = $this->render_block_html(
			array(
				'links' => array(
					array(
						'url'  => 'https://a.example/',
						'name' => 'A',
					),
					array(
						'url'  => 'https://b.example/',
						'name' => 'B',
					),
				),
			)
		);
		$this->assertStringNotContainsString( 'blockroll-controls', $html );
	}

	public function test_paging_slices_the_list() {
		$attrs = array(
			'perPage' => 1,
			'links'   => array(
				array(
					'url'  => 'https://a.example/',
					'name' => 'A',
				),
				array(
					'url'  => 'https://b.example/',
					'name' => 'B',
				),
			),
		);

		$html = $this->render_block_html( $attrs );
		$this->assertSame( 1, substr_count( $html, 'class="h-card"' ) );
		$this->assertStringContainsString( 'A', $html );
		$this->assertStringNotContainsString( 'b.example', $html );
		$this->assertStringContainsString( 'blockroll-page', $html ); // Next link.

		set_query_var( 'blockroll-page', 2 );
		$html = $this->render_block_html( $attrs );
		set_query_var( 'blockroll-page', null );
		$this->assertStringContainsString( 'b.example', $html );
		$this->assertStringNotContainsString( 'a.example/"', $html );
	}

	public function test_sort_query_var_overrides_attribute() {
		$attrs = array(
			'sortBy' => 'manual',
			'links'  => array(
				array(
					'url'   => 'https://b.example/',
					'name'  => 'Beta',
					'added' => '2026-01-01',
				),
				array(
					'url'   => 'https://a.example/',
					'name'  => 'alpha',
					'added' => '2026-06-01',
				),
			),
		);

		$html = $this->render_block_html( $attrs );
		$this->assertLessThan( strpos( $html, 'alpha' ), strpos( $html, 'Beta' ) ); // Manual order.

		set_query_var( 'blockroll-sort', 'name' );
		$html = $this->render_block_html( $attrs );
		set_query_var( 'blockroll-sort', null );
		$this->assertLessThan( strpos( $html, 'Beta' ), strpos( $html, 'alpha' ) ); // Sorted by name.
	}

	public function test_empty_links_renders_nothing() {
		$this->assertSame( '', trim( $this->render_block_html( array( 'links' => array() ) ) ) );
	}
}
