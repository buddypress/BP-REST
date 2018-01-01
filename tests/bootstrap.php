<?php
/**
 * PHPUnit bootstrap file
 *
 * @package BP_REST
 */

/**
 * Determine where the WP test suite lives.
 *
 * Support for:
 * 1. `WP_DEVELOP_DIR` environment variable, which points to a checkout
 *   of the develop.svn.wordpress.org repository (this is recommended)
 * 2. `WP_TESTS_DIR` environment variable, which points to a checkout
 * 3. `WP_ROOT_DIR` environment variable, which points to a checkout
 * 4. Plugin installed inside of WordPress.org developer checkout
 * 5. Tests checked out to /tmp
 */
if ( false !== getenv( 'WP_DEVELOP_DIR' ) ) {
	$test_root = getenv( 'WP_DEVELOP_DIR' ) . '/tests/phpunit';
} elseif ( false !== getenv( 'WP_TESTS_DIR' ) ) {
	$test_root = getenv( 'WP_TESTS_DIR' );
} elseif ( false !== getenv( 'WP_ROOT_DIR' ) ) {
	$test_root = getenv( 'WP_ROOT_DIR' ) . '/tests/phpunit';
} elseif ( file_exists( '../../../../tests/phpunit/includes/bootstrap.php' ) ) {
	$test_root = '../../../../tests/phpunit';
} elseif ( file_exists( '/tmp/wordpress-tests-lib/includes/bootstrap.php' ) ) {
	$test_root = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! defined( 'BP_TESTS_DIR' ) ) {
	$path = dirname( __FILE__ ) . '/../../buddypress/tests/phpunit';

	// BP 2.1 and higher.
	if ( file_exists( realpath( $path ) ) ) {
		define( 'BP_TESTS_DIR', $path );
	}
}

if ( ! defined( 'BP_TESTS_DIR' ) || ! file_exists( BP_TESTS_DIR . '/bootstrap.php' ) ) {
	return;
}

// Give access to tests_add_filter() function.
require_once $test_root . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	// Make sure BP is installed and loaded first.
	require BP_TESTS_DIR . '/includes/loader.php';

	// Load our plugin.
	require dirname( dirname( __FILE__ ) ) . '/bp-rest.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $test_root . '/includes/bootstrap.php';

// Helper classes
if ( ! class_exists( 'WP_Test_REST_TestCase' ) ) {
	require_once dirname( __FILE__ ) . '/class-wp-test-rest-testcase.php';
}

// Load the REST controllers.
require_once $test_root . '/includes/testcase-rest-controller.php';

// Load the BP test files.
require BP_TESTS_DIR . '/includes/testcase.php';

