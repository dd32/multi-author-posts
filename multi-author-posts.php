<?php
/**
 * Plugin Name: Multi-Author Posts
 * Plugin URI:  https://github.com/dd32/multi-author-posts
 * Description: Allow multiple authors to edit a single WordPress post via a shared invite link. Compatible with WordPress collaborative editing.
 * Version:     0.1
 * Requires at least: 7.1
 * Requires PHP: 7.4
 * Author:      dd32
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: multi-author-posts
 */

namespace dd32\WordPress\MultiAuthorPosts;

require_once __DIR__ . '/includes/class-map-co-authors.php';
require_once __DIR__ . '/includes/class-map-capabilities.php';
require_once __DIR__ . '/includes/class-map-invite.php';
require_once __DIR__ . '/includes/class-map-rest-api.php';

add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap' );
/**
 * Bootstrap all plugin sub-systems.
 */
function bootstrap(): void {
	Co_Authors::init();
	Capabilities::init();
	Invite::init();
	Rest_API::init();
}

add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_editor_assets' );
/**
 * Enqueue the block-editor sidebar script and stylesheet.
 */
function enqueue_editor_assets(): void {
	$asset_file = __DIR__ . '/build/index.asset.php';

	if ( ! file_exists( $asset_file ) ) {
		return;
	}

	$asset     = require $asset_file;
	$build_url = plugins_url( 'build/', __FILE__ );

	wp_enqueue_script(
		'multi-author-posts-editor',
		$build_url . 'index.js',
		$asset['dependencies'],
		$asset['version'],
		true
	);

	if ( file_exists( __DIR__ . '/build/index.css' ) ) {
		wp_enqueue_style(
			'multi-author-posts-editor',
			$build_url . 'index.css',
			array( 'wp-components' ),
			$asset['version']
		);
	}
}
