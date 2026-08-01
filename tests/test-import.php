<?php
/**
 * Import tests.
 *
 * @package Blockroll
 */

/**
 * Test OPML import parsing and the REST route.
 */
class Test_Import extends WP_UnitTestCase {
	/**
	 * Load the OPML fixture.
	 *
	 * @return string OPML document.
	 */
	private function fixture() {
		return file_get_contents( __DIR__ . '/fixtures/subscriptions.opml' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	public function test_parses_outlines_recursively() {
		$links = \Blockroll\Import::parse( $this->fixture() );
		// Ann, Ben (nested), Carla, and the site-less feed. The folder label is skipped.
		$this->assertCount( 4, $links );
		$this->assertSame( 'https://a.example/', $links[0]['url'] );
		$this->assertSame( 'https://a.example/feed/', $links[0]['feedUrl'] );
		$this->assertSame( 'Ann', $links[0]['name'] );
	}

	public function test_html_url_falls_back_to_feed_origin() {
		$links = \Blockroll\Import::parse( $this->fixture() );
		$this->assertSame( 'https://d.example/', $links[3]['url'] );
	}

	public function test_malformed_is_wp_error() {
		$this->assertWPError( \Blockroll\Import::parse( 'this is not xml' ) );
	}

	public function test_route_returns_links() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$request = new WP_REST_Request( 'POST', '/blockroll/v1/import' );
		$request->set_param( 'opml', $this->fixture() );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'links', $response->get_data() );
		$this->assertCount( 4, $response->get_data()['links'] );
	}

	public function test_route_malformed_is_422() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$request = new WP_REST_Request( 'POST', '/blockroll/v1/import' );
		$request->set_param( 'opml', 'nope' );
		$this->assertSame( 422, rest_do_request( $request )->get_status() );
	}

	public function test_route_requires_auth() {
		$request = new WP_REST_Request( 'POST', '/blockroll/v1/import' );
		$request->set_param( 'opml', $this->fixture() );
		$this->assertSame( 401, rest_do_request( $request )->get_status() );
	}
}
