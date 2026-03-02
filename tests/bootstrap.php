<?php
/**
 * PHPUnit bootstrap for Multi-Author Posts tests.
 *
 * Expects to run inside a wp-env container where WordPress test helpers
 * are available.
 */

// Load Composer autoloader (provides PHPUnit Polyfills for the WP test suite).
$_autoloader = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( file_exists( $_autoloader ) ) {
	require_once $_autoloader;
}

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	// wp-env v11+ uses /wordpress-phpunit; older versions use /tmp/wordpress-tests-lib.
	foreach ( array( '/wordpress-phpunit', '/tmp/wordpress-tests-lib' ) as $_candidate ) {
		if ( file_exists( $_candidate . '/includes/functions.php' ) ) {
			$_tests_dir = $_candidate;
			break;
		}
	}
}

if ( ! $_tests_dir || ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo 'Could not find WordPress test library.' . PHP_EOL;
	echo 'Make sure WP_TESTS_DIR is set or run tests via: npx wp-env run cli phpunit' . PHP_EOL;
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Load the plugin under test before WordPress finishes loading.
 */
function _map_manually_load_plugin(): void {
	require dirname( __DIR__ ) . '/multi-author-posts.php';
}
tests_add_filter( 'muplugins_loaded', '_map_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
