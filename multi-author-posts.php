<?php
/**
 * Plugin Name: Multi-Author Posts
 * Plugin URI:  https://github.com/dd32/multi-author-posts
 * Description: Allow multiple authors to edit a single WordPress post via a shared invite link. Compatible with WordPress collaborative editing.
 * Version:     1.0.0
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Author:      dd32
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: multi-author-posts
 */

namespace MultiAuthorPosts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MAP_VERSION', '1.0.0' );
define( 'MAP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MAP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MAP_PLUGIN_FILE', __FILE__ );

require_once MAP_PLUGIN_DIR . 'includes/class-map-co-authors.php';
require_once MAP_PLUGIN_DIR . 'includes/class-map-capabilities.php';
require_once MAP_PLUGIN_DIR . 'includes/class-map-invite.php';
require_once MAP_PLUGIN_DIR . 'includes/class-map-rest-api.php';

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
	$asset_file = MAP_PLUGIN_DIR . 'build/index.asset.php';

	if ( ! file_exists( $asset_file ) ) {
		return;
	}

	$asset = require $asset_file;

	wp_enqueue_script(
		'multi-author-posts-editor',
		MAP_PLUGIN_URL . 'build/index.js',
		$asset['dependencies'],
		$asset['version'],
		true
	);

	if ( file_exists( MAP_PLUGIN_DIR . 'build/index.css' ) ) {
		wp_enqueue_style(
			'multi-author-posts-editor',
			MAP_PLUGIN_URL . 'build/index.css',
			array( 'wp-components' ),
			$asset['version']
		);
	}
}
