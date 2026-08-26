<?php
/**
 * Icon tests.
 *
 * @package Blockroll
 */

/**
 * Test fetching and embedding icons.
 */
class Test_Icon extends WP_UnitTestCase {
	/**
	 * Answer every outgoing request with the given body.
	 *
	 * @param string $body         Response body.
	 * @param string $content_type Content type header.
	 */
	private function fake_response( $body, $content_type ) {
		add_filter(
			'pre_http_request',
			function () use ( $body, $content_type ) {
				return array(
					'headers'  => array( 'content-type' => $content_type ),
					'body'     => $body,
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
				);
			}
		);
	}

	/**
	 * A real PNG, so the image editor has something to work with.
	 *
	 * @param int $size Edge length in pixels.
	 * @return string PNG bytes.
	 */
	private function png( $size = 64 ) {
		$image = imagecreatetruecolor( $size, $size );
		imagefill( $image, 0, 0, imagecolorallocate( $image, 200, 100, 50 ) );
		ob_start();
		imagepng( $image );
		imagedestroy( $image );
		return ob_get_clean();
	}

	public function test_embeds_an_image_as_data_uri() {
		$this->fake_response( $this->png(), 'image/png' );
		$data = \Blockroll\Icon::embed( 'https://ann.example/favicon.png' );
		$this->assertStringStartsWith( 'data:image/png;base64,', $data );
		$this->assertTrue( \Blockroll\Icon::is_data_uri( $data ) );
	}

	public function test_resizes_to_the_icon_size() {
		$this->fake_response( $this->png( 512 ), 'image/png' );
		$data = \Blockroll\Icon::embed( 'https://ann.example/favicon.png' );
		$size = getimagesizefromstring( base64_decode( substr( $data, strlen( 'data:image/png;base64,' ) ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$this->assertSame( 48, $size[0] );
	}

	/**
	 * A photo would be several times the size as a PNG.
	 */
	public function test_keeps_a_photo_a_jpeg() {
		$image = imagecreatetruecolor( 150, 150 );
		imagefill( $image, 0, 0, imagecolorallocate( $image, 30, 60, 90 ) );
		ob_start();
		imagejpeg( $image );
		imagedestroy( $image );

		$this->fake_response( ob_get_clean(), 'image/jpeg' );
		$this->assertStringStartsWith( 'data:image/jpeg;base64,', \Blockroll\Icon::embed( 'https://ann.example/photo.jpg' ) );
	}

	/**
	 * An .ico is embedded as it came, GD cannot read those.
	 */
	public function test_embeds_an_icon_the_editor_cannot_read() {
		$this->fake_response( str_repeat( 'x', 2 * KB_IN_BYTES ), 'image/vnd.microsoft.icon' );
		$data = \Blockroll\Icon::embed( 'https://ann.example/favicon.ico' );
		$this->assertStringStartsWith( 'data:image/vnd.microsoft.icon;base64,', $data );
		$this->assertTrue( \Blockroll\Icon::is_data_uri( $data ) );
	}

	public function test_ignores_a_page_that_is_not_an_image() {
		$this->fake_response( '<html><body>nope</body></html>', 'text/html' );
		$this->assertSame( '', \Blockroll\Icon::embed( 'https://ann.example/favicon.png' ) );
	}

	public function test_ignores_svg() {
		$this->fake_response( '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'image/svg+xml' );
		$this->assertSame( '', \Blockroll\Icon::embed( 'https://ann.example/icon.svg' ) );
	}

	public function test_ignores_an_image_that_stays_too_big() {
		// Not readable for the image editor, so it cannot be shrunk either.
		$this->fake_response( str_repeat( 'x', 40 * KB_IN_BYTES ), 'image/x-icon' );
		$this->assertSame( '', \Blockroll\Icon::embed( 'https://ann.example/favicon.ico' ) );
	}

	public function test_ignores_a_failed_request() {
		add_filter( 'pre_http_request', array( $this, 'error_response' ) );
		$this->assertSame( '', \Blockroll\Icon::embed( 'https://ann.example/favicon.png' ) );
	}

	/**
	 * Filter callback: every request fails.
	 *
	 * @return \WP_Error Error.
	 */
	public function error_response() {
		return new WP_Error( 'http_request_failed', 'nope' );
	}

	public function test_keeps_a_data_uri_it_is_given() {
		$this->fake_response( $this->png(), 'image/png' );
		$data = \Blockroll\Icon::embed( 'https://ann.example/favicon.png' );
		$this->assertSame( $data, \Blockroll\Icon::embed( $data ) );
	}

	public function test_is_data_uri_only_accepts_images() {
		$this->assertTrue( \Blockroll\Icon::is_data_uri( 'data:image/png;base64,iVBORw0KGgo=' ) );
		$this->assertFalse( \Blockroll\Icon::is_data_uri( 'data:text/html;base64,PHNjcmlwdD4=' ) );
		$this->assertFalse( \Blockroll\Icon::is_data_uri( 'data:image/svg+xml;base64,PHN2Zz4=' ) );
		$this->assertFalse( \Blockroll\Icon::is_data_uri( 'https://ann.example/photo.jpg' ) );
		$this->assertFalse( \Blockroll\Icon::is_data_uri( "data:image/png;base64,iVBO\nRw0K" ) );
		$this->assertFalse( \Blockroll\Icon::is_data_uri( 'data:image/png,<script>' ) );
		$this->assertFalse( \Blockroll\Icon::is_data_uri( '' ) );
	}

	public function test_route_requires_auth() {
		$request = new WP_REST_Request( 'POST', '/blockroll/v1/icon' );
		$request->set_param( 'url', home_url( '/favicon.png' ) );
		$this->assertSame( 401, rest_do_request( $request )->get_status() );
	}

	public function test_route_rejects_bad_url() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$request = new WP_REST_Request( 'POST', '/blockroll/v1/icon' );
		$request->set_param( 'url', 'not-a-url' );
		$this->assertSame( 400, rest_do_request( $request )->get_status() );
	}

	public function test_route_returns_the_embedded_image() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->fake_response( $this->png(), 'image/png' );

		$request = new WP_REST_Request( 'POST', '/blockroll/v1/icon' );
		$request->set_param( 'url', home_url( '/favicon.png' ) );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( \Blockroll\Icon::is_data_uri( $response->get_data()['photo'] ) );
	}

	public function test_route_says_so_when_there_is_no_image() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->fake_response( 'nope', 'text/html' );

		$request = new WP_REST_Request( 'POST', '/blockroll/v1/icon' );
		$request->set_param( 'url', home_url( '/favicon.png' ) );

		$this->assertSame( 502, rest_do_request( $request )->get_status() );
	}

	public function test_is_local_knows_the_own_site() {
		$this->assertTrue( \Blockroll\Icon::is_local( home_url( '/wp-content/uploads/a.png' ) ) );
		$this->assertFalse( \Blockroll\Icon::is_local( 'https://ann.example/a.png' ) );
		$this->assertFalse( \Blockroll\Icon::is_local( '' ) );
	}
}
