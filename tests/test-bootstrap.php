<?php
/**
 * Class Test_Bootstrap
 *
 * @package Blockroll
 */

/**
 * Tests that the plugin bootstrap registers the block.
 */
class Test_Bootstrap extends WP_UnitTestCase {

	/**
	 * Tests that the blockroll/blogroll block is registered.
	 */
	public function test_block_registered() {
		$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'blockroll/blogroll' ) );
	}
}
