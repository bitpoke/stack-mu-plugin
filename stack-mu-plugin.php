<?php
/**
 * Plugin Name: Presslabs Stack
 * Plugin URI: http://presslabs.com/stack/
 * Description: Must-Use plugin for Stack
 * Version: git+$Format:%H$
 * Author: Presslabs
 * Author URI: http://presslabs.com/
 *
 * @package Stack
 */

namespace Stack;

if ( file_exists( __DIR__ . '/stack-mu-plugin/' . basename( __FILE__ ) ) ) {
	// We are copied into mu-plugins root.
	require_once __DIR__ . '/stack-mu-plugin/' . basename( __FILE__ );
} else {
	// Load Composer autoloader if bundled.
	if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
		require_once __DIR__ . '/vendor/autoload.php';
	}
	// Loads Stack autoloader.
	require_once __DIR__ . '/includes/autoload.php';

	if ( ! class_exists( 'Composer\Autoload\ClassLoader' ) ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
		trigger_error( 'Presslabs Stack WordPress mu-plugin is not fully installed! Please install with Composer or download full release archive.', E_USER_ERROR );
	}

	load();
}

/**
 * Loads the Presslabs Stack mu-plugin
 */
function load() {
	Stack_Config::load();
	new Media\Stack_Media_Storage();
}

// phpcs:ignore
// vim: set ft=php.wp:
