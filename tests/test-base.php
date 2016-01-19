<?php

class BaseTest extends WP_UnitTestCase {

	function test_sample() {
		// replace this with some actual testing code
		$this->assertTrue( true );
	}

	function test_class_exists() {
		$this->assertTrue( class_exists( 'BP-REST') );
	}
	
	function test_get_instance() {
		$this->assertTrue( bp_rest() instanceof BP-REST );
	}
}
