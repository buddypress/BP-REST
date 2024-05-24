<?php
/**
 * PHPUnit bootstrap file
 *
 * @package BP_REST
 */

// Setting PHPUnit polyfills.
const WP_TESTS_PHPUNIT_POLYFILLS_PATH = __DIR__ . '/../vendor/yoast/phpunit-polyfills';

// Define constants.
require( dirname( __FILE__ ) . '/define-constants.php' );

if ( ! file_exists( WP_TESTS_DIR . '/includes/functions.php' ) ) {
	die( "The WordPress PHPUnit test suite could not be found.\n" );
}

if ( ! file_exists( BP_TESTS_DIR . '/includes/loader.php' ) ) {
	die( "The BuddyPress plugin could not be found.\n" );
}

// Give access to tests_add_filter() function.
require_once WP_TESTS_DIR . '/includes/functions.php';

/**
 * Manually load the plugins being tested.
 */
function _manually_load_plugins() {

	// Make sure BP is installed and loaded first.
	require_once BP_TESTS_DIR . '/includes/loader.php';

	// Load our plugin.
	require_once dirname( __FILE__ ) . '/../bp-rest.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugins' );

echo "Loading WP Testing environment...\n";
require_once WP_TESTS_DIR . '/includes/bootstrap.php';

echo "Loading BuddyPress testcases...\n";
require_once BP_TESTS_DIR . '/includes/testcase.php';
require_once BP_TESTS_DIR . '/includes/testcase-emails.php';

/**
 * Set component visibility.
 *
 * @param bool $visibility Visibility.
 */
function toggle_component_visibility( $visibility = true ) {
	$visibility = $visibility ? 'members' : 'anyone';

	update_option(
		'_bp_community_visibility',
		array(
			'global'   => $visibility,
			'activity' => $visibility,
			'members'  => $visibility,
			'groups'   => $visibility,
			'blogs'    => $visibility,
		)
	);
}
