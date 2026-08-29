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

	/**
	 * A blogroll on the page must not win against the site's own h-card.
	 *
	 * The blogroll block marks every entry up as an h-card, so the first
	 * h-card on a page is often somebody else.
	 */
	public function test_own_hcard_wins_over_blogroll_hcards() {
		$result = \Blockroll\Discovery::from_html( $this->fixture( 'blogroll-hcards.html' ), 'https://ann.example/' );
		$this->assertSame( 'Ann Example', $result['name'] );
		$this->assertSame( 'https://ann.example/photo.jpg', $result['photo'] );
		$this->assertSame( 'Writes about the open web.', $result['description'] );
	}

	/**
	 * A tracking parameter on the pasted URL does not break the match.
	 */
	public function test_own_hcard_wins_despite_query_string() {
		$result = \Blockroll\Discovery::from_html( $this->fixture( 'blogroll-hcards.html' ), 'https://ann.example/?utm_source=newsletter' );
		$this->assertSame( 'Ann Example', $result['name'] );
	}

	/**
	 * An h-card linked from a rel="me" URL is the site's own one too.
	 */
	public function test_rel_me_hcard_wins_over_blogroll_hcards() {
		$result = \Blockroll\Discovery::from_html( $this->fixture( 'hcard-rel-me.html' ), 'https://ann.example/' );
		$this->assertSame( 'Ann Example', $result['name'] );
		$this->assertSame( 'https://ann.example/photo.jpg', $result['photo'] );
	}

	/**
	 * Without an own h-card the blogroll entries are ignored.
	 */
	public function test_blogroll_only_page_falls_back_to_title_and_meta() {
		$result = \Blockroll\Discovery::from_html( $this->fixture( 'blogroll-only.html' ), 'https://ann.example/' );
		$this->assertSame( "Ann's Website", $result['name'] );
		$this->assertSame( 'Writes about the open web.', $result['description'] );
		$this->assertSame( 'https://ann.example/favicon.ico', $result['photo'] );
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

	/**
	 * The discovered icon comes back embedded, not as a foreign URL.
	 */
	public function test_route_embeds_the_icon() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$html = $this->fixture( 'bare.html' );
		$png  = $this->png();
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) use ( $html, $png ) {
				$is_icon = false !== strpos( $url, 'favicon' );
				return array(
					'headers'  => array( 'content-type' => $is_icon ? 'image/png' : 'text/html' ),
					'body'     => $is_icon ? $png : $html,
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
				);
			},
			10,
			3
		);

		$request = new WP_REST_Request( 'POST', '/blockroll/v1/discover' );
		$request->set_param( 'url', home_url( '/' ) );
		$data = rest_do_request( $request )->get_data();

		$this->assertTrue( \Blockroll\Icon::is_data_uri( $data['photo'] ) );
	}

	/**
	 * A real PNG, so the image editor has something to work with.
	 *
	 * @return string PNG bytes.
	 */
	private function png() {
		$image = imagecreatetruecolor( 64, 64 );
		imagefill( $image, 0, 0, imagecolorallocate( $image, 200, 100, 50 ) );
		ob_start();
		imagepng( $image );
		imagedestroy( $image );
		return ob_get_clean();
	}

	public function test_route_requires_auth() {
		$request = new WP_REST_Request( 'POST', '/blockroll/v1/discover' );
		// The site's own address: passes wp_http_validate_url() without a DNS
		// lookup, so the request reaches the permission check. TEST-NET IPs no
		// longer work, WordPress rejects the special-purpose ranges.
		$request->set_param( 'url', home_url( '/' ) );
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
