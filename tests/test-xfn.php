<?php
/**
 * Xfn tests.
 *
 * @package Blockroll
 */

/**
 * Test the Xfn helper.
 */
class Test_Xfn extends WP_UnitTestCase {
	public function test_sanitize_whitelists() {
		$this->assertSame( array( 'friend', 'met' ), \Blockroll\Xfn::sanitize( array( 'friend', 'met', 'evil"onload' ) ) );
	}

	public function test_sanitize_exclusive_groups() {
		// Friendship group is mutually exclusive: first one wins.
		$this->assertSame( array( 'friend' ), \Blockroll\Xfn::sanitize( array( 'friend', 'acquaintance' ) ) );
	}

	public function test_me_is_exclusive_with_everything() {
		$this->assertSame( array( 'me' ), \Blockroll\Xfn::sanitize( array( 'me', 'friend', 'met' ) ) );
	}

	public function test_profile_link_on_every_page() {
		$this->go_to( home_url( '/' ) );
		ob_start();
		\Blockroll\Xfn::profile_link();
		$head = ob_get_clean();
		$this->assertStringContainsString( 'rel="profile"', $head );
		$this->assertStringContainsString( \Blockroll\Xfn::PROFILE, $head );
	}

	public function test_rel_string() {
		$this->assertSame( 'friend met', \Blockroll\Xfn::rel_string( array( 'friend', 'met' ) ) );
		$this->assertSame( '', \Blockroll\Xfn::rel_string( array() ) );
	}
}
