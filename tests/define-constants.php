<?php
/**
 * Define constants needed by test suite.
 */

if ( ! defined( 'BP_TESTS_DIR' ) ) {
	define( 'BP_TESTS_DIR', dirname( dirname( dirname( __FILE__ ) ) ) . '/buddypress/tests/phpunit' );
}

/**
 * Determine where the WP test suite lives. Three options are supported:
 *
 * - Define a WP_DEVELOP_DIR environment variable, which points to a checkout
 *   of the develop.svn.wordpress.org repository (this is recommended)
 * - Define a WP_TESTS_DIR environment variable, which points to a checkout of
 *   WordPress test suite
 * - Assume that we are inside of a develop.svn.wordpress.org setup, and walk
 *   up the directory tree
 */
if ( false !== getenv( 'WP_TESTS_DIR' ) ) {
	define( 'WP_TESTS_DIR', getenv( 'WP_TESTS_DIR' ) );
	define( 'WP_ROOT_DIR', WP_TESTS_DIR );
} else {
	// Support WP_DEVELOP_DIR, as used by some plugins
	if ( false !== getenv( 'WP_DEVELOP_DIR' ) ) {
		define( 'WP_ROOT_DIR', getenv( 'WP_DEVELOP_DIR' ) );
	} else {
		define( 'WP_ROOT_DIR', dirname( dirname( dirname( dirname( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) ) ) ) ) );
	}

	define( 'WP_TESTS_DIR', WP_ROOT_DIR . '/tests/phpunit' );
}
