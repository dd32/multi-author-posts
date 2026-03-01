<?php
/**
 * PHPUnit bootstrap for Multi-Author Posts tests.
 *
 * Expects to run inside the wp-env tests-cli container where
 * WordPress test helpers are installed at /tmp/wordpress-tests-lib.
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo 'Could not find WordPress test library at: ' . $_tests_dir . PHP_EOL;
	echo 'Make sure WP_TESTS_DIR is set or run tests via: npx wp-env run tests-cli phpunit' . PHP_EOL;
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
