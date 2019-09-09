<?php
/**
 * Stack configuration defaults
 *
 * Loads the default constants from environment for Stack.
 *
 * @link URL
 *
 * @package Stack
 * @subpackage Config
 * @since 0.1.0
 */

namespace Stack;

/**
 * Handles the setup for Stack mu-plugin
 *
 * @since 0.1.0
 */
class Stack_Config {

	/**
	 * Sets up Stack constants from environment.
	 *
	 * @since 0.1.0
	 */
	public static function load() {
		static $run_count = 0;

		if ( $run_count > 0 ) {
			return;
		}

		// Expose global env() function from oscarotero/env.
		\Env::init();

		$uploads = wp_upload_dir( null, false, false );
		$siteurl = get_option( 'siteurl' );

		/*
		 * uploads dir relative to webroot
		 * this takes into account CONTENT_DIR (defined by bedrock setups)
		 * and defaults to `wp-content/uploads`
		 */
		if ( substr( $uploads['baseurl'], 0, strlen( $siteurl ) ) === $siteurl ) {
			$rel_uploads_dir = substr( $uploads['baseurl'], strlen( $siteurl ) );
		} else {
			$rel_uploads_dir = ( defined( 'CONTENT_DIR' ) ? CONTENT_DIR : '/wp-content' ) . '/uploads';
		}
		$rel_uploads_dir = ltrim( $rel_uploads_dir, '/' );
		self::define_from_env( 'STACK_MEDIA_PATH', env( 'MEDIA_PATH' ) ?: $rel_uploads_dir, '/' );
		self::define_from_env( 'STACK_MEDIA_BUCKET', env( 'MEDIA_BUCKET' ) ?: 'file://' . $uploads['basedir'] );

		self::define_from_env( 'DOBJECT_CACHE_PRELOAD', false );

		self::define_from_env( 'MEMCACHED_HOST', '' );
		self::define_from_env( 'MEMCACHED_DISCOVERY_HOST', '' );

		self::define_path( 'GIT_DIR', env( 'SRC_DIR' ) ?: '/var/run/presslabs.org/code/src' );
		self::define_path( 'GIT_KEY_FILE', '/var/run/secrets/presslabs.org/instance/id_rsa' );
		self::define_path( 'GIT_KEY_FILE', ( rtrim( env( 'HOME' ), '/' ) ?: '/var/www' ) . '/.ssh/id_rsa' );

		$run_count++;
	}

	/**
	 * Defines a constant from environment
	 *
	 * @since 0.1.0
	 *
	 * @param string $name The name of the constant.
	 * @param any    $default_value The default value to use for the constant if it's not defined in environment.
	 * @param string $env_name The name of the environment variable to use. If not specified, $name is used.
	 */
	public static function define_from_env( string $name, $default_value, string $env_name = '' ) {
		$env_name = $env_name ?: $name;
		$value    = env( $env_name ) ?: $default_value;
		self::define( $name, $value );
	}

	/**
	 * Defines a constant to a path, if that path exists
	 *
	 * @since 0.1.0
	 *
	 * @param string $name The name of the constant.
	 * @param string $path That path of the file to check.
	 * @param string $default_path Define the constants as this path, if $path does not exists.
	 */
	public static function define_path( string $name, string $path, string $default_path = '' ) {
		if ( file_exists( $path ) ) {
			self::define( $name, $path );
		} elseif ( ! empty( $default_path ) ) {
			self::define( $name, $default_path );
		}
	}

	/**
	 * Defines a constant if it's not already defined.
	 *
	 * @since 0.1.0
	 *
	 * @param string $name The name of the constant.
	 * @param any    $value The value of the constant.
	 */
	public static function define( string $name, $value ) {
		if ( ! defined( $name ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
			define( $name, $value );
		}
	}
}
