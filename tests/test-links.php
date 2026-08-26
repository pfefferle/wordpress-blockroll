<?php
/**
 * Link normalizing tests.
 *
 * @package Blockroll
 */

/**
 * Test how link data is sanitized.
 */
class Test_Links extends WP_UnitTestCase {
	const DATA_URI = 'data:image/png;base64,iVBORw0KGgo=';

	public function test_keeps_an_embedded_photo() {
		$link = \Blockroll\Links::normalize( array( 'photo' => self::DATA_URI ) );
		$this->assertSame( self::DATA_URI, $link['photo'] );
	}

	public function test_still_escapes_a_photo_url() {
		$link = \Blockroll\Links::normalize( array( 'photo' => 'javascript:alert(1)' ) );
		$this->assertSame( '', $link['photo'] );

		$link = \Blockroll\Links::normalize( array( 'photo' => 'https://ann.example/a.png' ) );
		$this->assertSame( 'https://ann.example/a.png', $link['photo'] );
	}

	public function test_drops_a_data_uri_that_is_not_an_image() {
		$link = \Blockroll\Links::normalize( array( 'photo' => 'data:text/html;base64,PHNjcmlwdD4=' ) );
		$this->assertSame( '', $link['photo'] );
	}
}
