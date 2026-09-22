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
	 * @param array $attrs        Block attributes.
	 * @param array $inner_blocks Parsed inner blocks.
	 * @return string Rendered HTML.
	 */
	private function render_block_html( $attrs, $inner_blocks = array() ) {
		$block = array(
			'blockName'    => 'blockroll/blogroll',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => '',
			'innerContent' => array_fill( 0, count( $inner_blocks ), null ),
		);
		return render_block( $block );
	}

	/**
	 * A parsed link block, the way a child of the blogroll is stored.
	 *
	 * @param array $attrs Link attributes.
	 * @return array Parsed block.
	 */
	private function link_block( $attrs ) {
		return array(
			'blockName'    => 'blockroll/link',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}

	public function test_renders_link_blocks() {
		$html = $this->render_block_html(
			array( 'sortBy' => 'manual' ),
			array(
				$this->link_block(
					array(
						'url'         => 'https://b.example/',
						'name'        => 'B',
						'description' => 'Second',
						'xfn'         => array( 'friend' ),
					)
				),
				$this->link_block( array( 'url' => 'https://a.example/' ) ),
			)
		);

		$this->assertSame( 2, substr_count( $html, 'class="h-card"' ) );
		$this->assertStringContainsString( 'rel="friend noopener"', $html );
		$this->assertStringContainsString( '<p class="p-note">Second</p>', $html );
		// Manual order is the order of the blocks.
		$this->assertLessThan( strpos( $html, 'a.example' ), strpos( $html, 'b.example' ) );
	}

	public function test_link_blocks_win_over_links_attribute() {
		$html = $this->render_block_html(
			array(
				'links' => array(
					array(
						'url'  => 'https://old.example/',
						'name' => 'Old',
					),
				),
			),
			array( $this->link_block( array( 'url' => 'https://new.example/' ) ) )
		);

		$this->assertStringContainsString( 'new.example', $html );
		$this->assertStringNotContainsString( 'old.example', $html );
	}

	public function test_links_attribute_still_renders_without_link_blocks() {
		$html = $this->render_block_html(
			array(
				'links' => array(
					array(
						'url'  => 'https://old.example/',
						'name' => 'Old',
					),
				),
			)
		);

		$this->assertStringContainsString( 'old.example', $html );
	}

	public function test_other_inner_blocks_are_ignored() {
		$paragraph = array(
			'blockName'    => 'core/paragraph',
			'attrs'        => array( 'url' => 'https://not-a-link.example/' ),
			'innerBlocks'  => array(),
			'innerHTML'    => '<p>Hi</p>',
			'innerContent' => array( '<p>Hi</p>' ),
		);
		$html      = $this->render_block_html(
			array(),
			array( $paragraph, $this->link_block( array( 'url' => 'https://a.example/' ) ) )
		);

		$this->assertSame( 1, substr_count( $html, 'class="h-card"' ) );
		$this->assertStringNotContainsString( 'not-a-link', $html );
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
		$this->assertStringContainsString( 'rel="friend met noopener"', $html );
		$this->assertStringContainsString( 'class="u-url p-name"', $html );
		$this->assertStringContainsString( 'class="p-note"', $html );
		$this->assertStringContainsString( 'class="u-feed"', $html );
		$this->assertStringContainsString( 'class="u-photo"', $html );
		$this->assertStringContainsString( 'class="blockroll-list xoxo blogroll"', $html );
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

	public function test_renders_registered_source_links() {
		add_filter(
			'blockroll_sources',
			function ( $sources ) {
				$sources['test-source'] = 'Test Source';
				return $sources;
			}
		);
		add_filter(
			'blockroll_source_links',
			function ( $links, $source ) {
				if ( 'test-source' !== $source ) {
					return $links;
				}

				return array(
					array(
						'url'  => 'https://source.example/',
						'name' => 'Source Link',
					),
				);
			},
			10,
			2
		);

		$html = $this->render_block_html(
			array(
				'source' => 'test-source',
			)
		);

		$this->assertStringContainsString( 'https://source.example/', $html );
		$this->assertStringContainsString( 'Source Link', $html );
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
		$this->assertStringContainsString( esc_url( \Blockroll\Opml::opml_url( $post ) ), $html );

		$attrs['showOpml'] = false;
		$html              = $this->render_block_html( $attrs );
		$this->assertStringNotContainsString( 'opml', $html );
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

	public function test_anchor_becomes_the_id_of_the_block() {
		$html = $this->render_block_html(
			array(
				'anchor' => 'podcasts',
				'links'  => array(
					array(
						'url'  => 'https://a.example/',
						'name' => 'A',
					),
				),
			)
		);
		$this->assertStringContainsString( 'id="podcasts"', $html );

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
		$this->assertStringNotContainsString( ' id=', $html );
	}

	public function test_sort_and_pager_links_jump_back_to_the_anchor() {
		$attrs = array(
			'anchor'  => 'podcasts',
			'perPage' => 1,
			'links'   => array(
				array(
					'url'   => 'https://a.example/',
					'name'  => 'A',
					'added' => '2026-08-01',
				),
				array(
					'url'   => 'https://b.example/',
					'name'  => 'B',
					'added' => '2026-08-02',
				),
			),
		);
		$html  = $this->render_block_html( $attrs );
		$this->assertMatchesRegularExpression( '/href="[^"]*blockroll-sort=added[^"]*#podcasts"/', $html );
		$this->assertMatchesRegularExpression( '/href="[^"]*blockroll-page=2[^"]*#podcasts"/', $html );
	}

	public function test_opml_link_points_at_the_group_when_the_page_has_several() {
		global $post;
		$content = '<!-- wp:blockroll/blogroll {"anchor":"blogs","links":[{"url":"https://a.example/","name":"A"}]} /--><!-- wp:blockroll/blogroll {"anchor":"podcasts","links":[{"url":"https://b.example/","name":"B"}]} /-->';
		$post    = self::factory()->post->create_and_get( array( 'post_content' => $content ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$attrs   = array(
			'anchor' => 'podcasts',
			'links'  => array(
				array(
					'url'  => 'https://b.example/',
					'name' => 'B',
				),
			),
		);

		$html = $this->render_block_html( $attrs );
		$this->assertStringContainsString( esc_url( \Blockroll\Opml::opml_url( $post, 'podcasts' ) ), $html );

		// A single blogroll on the page is the page's OPML, anchor or not.
		$post = self::factory()->post->create_and_get( array( 'post_content' => '<!-- wp:blockroll/blogroll {"anchor":"podcasts","links":[{"url":"https://b.example/","name":"B"}]} /-->' ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$html = $this->render_block_html( $attrs );
		$this->assertStringContainsString( esc_url( \Blockroll\Opml::opml_url( $post ) ), $html );
		$this->assertStringNotContainsString( \Blockroll\Opml::GROUP, $html );
	}

	public function test_download_is_named_after_the_group() {
		global $post;
		$content = '<!-- wp:blockroll/blogroll {"anchor":"blogs","links":[{"url":"https://a.example/","name":"A"}]} /--><!-- wp:blockroll/blogroll {"anchor":"podcasts","links":[{"url":"https://b.example/","name":"B"}]} /-->';
		$post    = self::factory()->post->create_and_get( array( 'post_content' => $content ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$attrs   = array(
			'anchor' => 'podcasts',
			'links'  => array(
				array(
					'url'  => 'https://b.example/',
					'name' => 'B',
				),
			),
		);

		$html = $this->render_block_html( $attrs );
		$this->assertStringContainsString( 'download="podcasts.opml"', $html );

		// The whole page keeps the generic name.
		$post = self::factory()->post->create_and_get( array( 'post_content' => '<!-- wp:blockroll/blogroll {"anchor":"podcasts","links":[{"url":"https://b.example/","name":"B"}]} /-->' ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$html = $this->render_block_html( $attrs );
		$this->assertStringContainsString( 'download="blogroll.opml"', $html );
	}

	public function test_wrapper_carries_style_supports() {
		$html = $this->render_block_html(
			array(
				'links'           => array(
					array(
						'url'  => 'https://a.example/',
						'name' => 'A',
					),
				),
				'textColor'       => 'contrast',
				'backgroundColor' => 'base',
				'gradient'        => 'vivid-cyan-blue-to-vivid-purple',
				'fontSize'        => 'small',
				'style'           => array(
					'elements'   => array( 'link' => array( 'color' => array( 'text' => '#c00' ) ) ),
					'spacing'    => array( 'padding' => array( 'top' => '1em' ) ),
					'typography' => array( 'lineHeight' => '1.8' ),
				),
			)
		);

		$this->assertStringContainsString( 'has-contrast-color', $html );
		$this->assertStringContainsString( 'has-base-background-color', $html );
		$this->assertStringContainsString( 'has-vivid-cyan-blue-to-vivid-purple-gradient-background', $html );
		$this->assertStringContainsString( 'has-small-font-size', $html );
		// Link color is not a class: the wrapper gets a wp-elements-* class and
		// the rule goes into the block supports stylesheet.
		$this->assertMatchesRegularExpression( '/class="[^"]*wp-elements-[a-f0-9]+/', $html );
		$css = wp_style_engine_get_stylesheet_from_context( 'block-supports' );
		$this->assertMatchesRegularExpression( '/\.wp-elements-[a-f0-9]+ a[^{]*\{color:#c00;?\}/', $css );
		$this->assertStringContainsString( 'padding-top:1em', $html );
		$this->assertStringContainsString( 'line-height:1.8', $html );
	}
}
