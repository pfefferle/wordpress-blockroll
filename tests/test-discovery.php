<?php
/**
 * Discovery tests.
 *
 * @package Blockroll
 */

/**
 * Test link discovery extraction and the REST route.
 */
class Test_Discovery extends WP_UnitTestCase {
	/**
	 * Load a fixture file.
	 *
	 * @param string $name File name.
	 * @return string File contents.
	 */
	private function fixture( $name ) {
		return file_get_contents( __DIR__ . '/fixtures/' . $name ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	public function test_hcard_wins() {
		$result = \Blockroll\Discovery::from_html( $this->fixture( 'hcard.html' ), 'https://ann.example/' );
		$this->assertSame( 'Ann Example', $result['name'] );
		$this->assertSame( 'https://ann.example/feed/', $result['feedUrl'] );
		$this->assertSame( 'https://ann.example/photo.jpg', $result['photo'] );
		$this->assertSame( 'Writes about the open web.', $result['description'] );
	}

	public function test_falls_back_to_title_and_meta() {
		$result = \Blockroll\Discovery::from_html( $this->fixture( 'feed-only.html' ), 'https://b.example/' );
		$this->assertSame( 'Feed Only', $result['name'] );
		$this->assertSame( 'https://b.example/atom.xml', $result['feedUrl'] );
		$this->assertSame( 'A site with a feed but no h-card.', $result['description'] );
	}

	public function test_bare_page_favicon_fallback() {
		$result = \Blockroll\Discovery::from_html( $this->fixture( 'bare.html' ), 'https://c.example/' );
		$this->assertSame( 'Bare', $result['name'] );
		$this->assertSame( '', $result['feedUrl'] );
		$this->assertSame( 'https://c.example/favicon.ico', $result['photo'] );
	}

	public function test_route_requires_auth() {
		$request = new WP_REST_Request( 'POST', '/blockroll/v1/discover' );
		// A public TEST-NET IP: passes wp_http_validate_url() without DNS,
		// so the request reaches the permission check.
		$request->set_param( 'url', 'https://203.0.113.5/' );
		$response = rest_do_request( $request );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_route_rejects_bad_url() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$request = new WP_REST_Request( 'POST', '/blockroll/v1/discover' );
		$request->set_param( 'url', 'not-a-url' );
		$response = rest_do_request( $request );
		$this->assertSame( 400, $response->get_status() );
	}
}
