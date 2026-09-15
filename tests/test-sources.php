<?php
/**
 * Sources tests.
 *
 * @package Blockroll
 */

/**
 * Test source registry, source links, and the REST route.
 */
class Test_Sources extends WP_UnitTestCase {
	public function test_all_contains_manual_by_default() {
		$sources = \Blockroll\Sources::all();

		$this->assertArrayHasKey( 'manual', $sources );
		$this->assertSame( 'Manual links', $sources['manual'] );
	}

	public function test_all_includes_filtered_source() {
		add_filter(
			'blockroll_sources',
			function ( $sources ) {
				$sources['test-source'] = 'Test Source';
				return $sources;
			}
		);

		$sources = \Blockroll\Sources::all();

		$this->assertArrayHasKey( 'test-source', $sources );
		$this->assertSame( 'Test Source', $sources['test-source'] );
	}

	public function test_all_supports_numeric_source_slugs() {
		add_filter(
			'blockroll_sources',
			function ( $sources ) {
				$sources['123'] = 'Numeric Source';
				return $sources;
			}
		);

		$sources = \Blockroll\Sources::all();

		$this->assertArrayHasKey( '123', $sources );
		$this->assertSame( 'Numeric Source', $sources['123'] );
	}

	public function test_help_returns_filtered_help() {
		$this->register_test_source();
		add_filter(
			'blockroll_source_help',
			function ( $help, $source ) {
				if ( 'test-source' === $source ) {
					return 'Manage this source elsewhere.';
				}

				return $help;
			},
			10,
			2
		);

		$this->assertSame( 'Manage this source elsewhere.', \Blockroll\Sources::help( 'test-source' ) );
	}

	public function test_help_returns_empty_string_for_unknown_source() {
		add_filter(
			'blockroll_source_help',
			function () {
				return 'Unknown help.';
			}
		);

		$this->assertSame( '', \Blockroll\Sources::help( 'missing-source' ) );
	}

	public function test_help_url_returns_filtered_url() {
		$this->register_test_source();
		add_filter(
			'blockroll_source_help_url',
			function ( $url, $source ) {
				if ( 'test-source' === $source ) {
					return 'https://example.com/settings/';
				}

				return $url;
			},
			10,
			2
		);

		$this->assertSame( 'https://example.com/settings/', \Blockroll\Sources::help_url( 'test-source' ) );
	}

	public function test_help_url_returns_empty_string_for_unknown_source() {
		add_filter(
			'blockroll_source_help_url',
			function () {
				return 'https://example.com/settings/';
			}
		);

		$this->assertSame( '', \Blockroll\Sources::help_url( 'missing-source' ) );
	}

	public function test_all_drops_invalid_source() {
		add_filter(
			'blockroll_sources',
			function ( $sources ) {
				$sources['Bad Source']   = 'Bad Source';
				$sources['bad-callback'] = array( 'not', 'scalar' );
				return $sources;
			}
		);

		$sources = \Blockroll\Sources::all();

		$this->assertArrayNotHasKey( 'Bad Source', $sources );
		$this->assertArrayNotHasKey( 'bad-callback', $sources );
	}

	public function test_links_uses_manual_attribute_links() {
		$links = \Blockroll\Sources::links(
			array(
				'links' => array(
					array(
						'url'  => 'https://manual.example/',
						'name' => '<b>Manual</b>',
					),
				),
			)
		);

		$this->assertCount( 1, $links );
		$this->assertSame( 'https://manual.example/', $links[0]['url'] );
		$this->assertSame( 'Manual', $links[0]['name'] );
	}

	public function test_links_uses_registered_source_links() {
		$this->register_test_source();

		$links = \Blockroll\Sources::links(
			array(
				'source' => 'test-source',
				'links'  => array(
					array(
						'url'  => 'https://manual.example/',
						'name' => 'Manual Link',
					),
				),
			)
		);

		$this->assertCount( 1, $links );
		$this->assertSame( 'https://source.example/', $links[0]['url'] );
		$this->assertSame( 'Source Link', $links[0]['name'] );
		$this->assertSame( 'https://source.example/feed/', $links[0]['feedUrl'] );
	}

	public function test_links_supports_numeric_source_slugs() {
		add_filter(
			'blockroll_sources',
			function ( $sources ) {
				$sources['123'] = 'Numeric Source';
				return $sources;
			}
		);
		add_filter(
			'blockroll_source_links',
			function ( $links, $source ) {
				if ( '123' !== $source ) {
					return $links;
				}

				return array(
					array(
						'url'  => 'https://numeric.example/',
						'name' => 'Numeric Source Link',
					),
				);
			},
			10,
			2
		);

		$links = \Blockroll\Sources::links(
			array(
				'source' => '123',
			)
		);

		$this->assertCount( 1, $links );
		$this->assertSame( 'https://numeric.example/', $links[0]['url'] );
	}

	public function test_links_falls_back_to_manual_for_unknown_source() {
		$links = \Blockroll\Sources::links(
			array(
				'source' => 'missing-source',
				'links'  => array(
					array(
						'url'  => 'https://manual.example/',
						'name' => 'Manual Link',
					),
				),
			)
		);

		$this->assertCount( 1, $links );
		$this->assertSame( 'https://manual.example/', $links[0]['url'] );
	}

	public function test_links_drops_items_without_url() {
		$links = \Blockroll\Sources::links(
			array(
				'links' => array(
					array( 'name' => 'No URL' ),
					array(
						'url'  => 'https://manual.example/',
						'name' => 'Manual Link',
					),
				),
			)
		);

		$this->assertCount( 1, $links );
		$this->assertSame( 'https://manual.example/', $links[0]['url'] );
	}

	public function test_route_requires_auth() {
		$request  = new WP_REST_Request( 'GET', '/blockroll/v1/sources' );
		$response = rest_do_request( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	public function test_route_returns_manual_by_default() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$request  = new WP_REST_Request( 'GET', '/blockroll/v1/sources' );
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $data );
		$this->assertSame( 'manual', $data[0]['value'] );
		$this->assertSame( 'Manual links', $data[0]['label'] );
		$this->assertSame( '', $data[0]['help'] );
		$this->assertSame( '', $data[0]['helpUrl'] );
	}

	public function test_route_includes_filtered_source() {
		$this->register_test_source();
		add_filter(
			'blockroll_source_help',
			function ( $help, $source ) {
				return 'test-source' === $source ? 'Test help.' : $help;
			},
			10,
			2
		);
		add_filter(
			'blockroll_source_help_url',
			function ( $url, $source ) {
				return 'test-source' === $source ? 'https://example.com/settings/' : $url;
			},
			10,
			2
		);
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$request  = new WP_REST_Request( 'GET', '/blockroll/v1/sources' );
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertContains(
			array(
				'value'   => 'test-source',
				'label'   => 'Test Source',
				'help'    => 'Test help.',
				'helpUrl' => 'https://example.com/settings/',
			),
			$data
		);
	}

	public function test_links_route_requires_auth() {
		$this->register_test_source();
		$request  = new WP_REST_Request( 'GET', '/blockroll/v1/sources/test-source/links' );
		$response = rest_do_request( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	public function test_links_route_returns_registered_source_links() {
		$this->register_test_source();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$request  = new WP_REST_Request( 'GET', '/blockroll/v1/sources/test-source/links' );
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $data );
		$this->assertSame( 'https://source.example/', $data[0]['url'] );
		$this->assertSame( 'Source Link', $data[0]['name'] );
		$this->assertSame( 'https://source.example/feed/', $data[0]['feedUrl'] );
	}

	public function test_links_route_passes_block_attributes_to_source_provider() {
		$this->register_test_source();
		add_filter(
			'blockroll_source_links',
			function ( $links, $source, $attributes ) {
				if ( 'test-source' !== $source ) {
					return $links;
				}

				return array(
					array(
						'url'  => 'https://source.example/',
						'name' => $attributes['metadata']['name'] ?? '',
					),
				);
			},
			20,
			3
		);
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$request = new WP_REST_Request( 'GET', '/blockroll/v1/sources/test-source/links' );
		$request->set_param( 'attributes', wp_json_encode( array( 'metadata' => array( 'name' => 'Preview Name' ) ) ) );
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Preview Name', $data[0]['name'] );
	}

	public function test_links_route_rejects_unknown_source() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$request  = new WP_REST_Request( 'GET', '/blockroll/v1/sources/missing-source/links' );
		$response = rest_do_request( $request );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'blockroll_unknown_source', $response->as_error()->get_error_code() );
	}

	/**
	 * Register a source and its link provider for tests.
	 */
	private function register_test_source() {
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
						'url'     => 'https://source.example/',
						'name'    => 'Source Link',
						'feedUrl' => 'https://source.example/feed/',
					),
				);
			},
			10,
			2
		);
	}
}
