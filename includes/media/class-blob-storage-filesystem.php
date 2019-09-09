<?php
/**
 * Blog storage implementation using the filesystem as backend.
 *
 * @package    Stack
 * @subpackage Media
 * @author     Calin Don <calin@presslabs.com>
 * @link       https://github.com/presslabs/stack-mu-plugin
 * @copyright  2019 Pressinfra
 */

namespace Stack\Media;

/**
 * Blog storage implementation using the filesystem as backend.
 */
class Blob_Storage_Filesystem implements Blob_Storage_Interface {

	/**
	 * Cache group for storing files
	 *
	 * @var string
	 */
	private $uploads_dir = null;

	/**
	 * Create a new Blob_Storage_Filesystem instance
	 *
	 * @param string $uploads_dir The path for storing blob contents.
	 */
	public function __construct( string $uploads_dir ) {
		$this->uploads_dir = $uploads_dir;
	}

	/**
	 * Gets the key content from the filesystem
	 *
	 * @param string $key The key to get.
	 * @return string     The key contents.
	 */
	public function get( string $key ) : string {
		$path = path_join( $this->uploads_dir, $key );
		if ( ! file_exists( $path ) ) {
			throw new Blob_Storage_Not_Found( sprintf( '%s not found', $key ) );
		}
		$result = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $result ) {
			throw new \Exception( sprintf( '%s get failed', $key ) );
		}
		return $result;
	}

	/**
	 * Gets the key metadata from the filesystem
	 *
	 * @param string $key The key to get.
	 * @return array      The key metadata.
	 */
	public function get_metadata( string $key ) : array {
		$path = path_join( $this->uploads_dir, $key );
		if ( ! is_file( $path ) ) {
			throw new Blob_Storage_Not_Found( sprintf( '%s not found', $key ) );
		}
		$stat    = stat( $path );
		$content = $this->get( $key );
		return $stat;
	}

	/**
	 * Sets the key contents on the filesystem
	 *
	 * @param string $key     The key to update.
	 * @param string $content The key contents to set.
	 */
	public function set( string $key, string $content ) {
		$path = path_join( $this->uploads_dir, $key );
		$dir  = dirname( $path );
		if ( false === wp_mkdir_p( $dir ) ) {
			throw new \Exception( sprintf( "Could not create directory '%s'", $dir ) );
		}
		if ( false === file_put_contents( $path, $content ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
			throw new \Exception( sprintf( "Could not write blob to key '%s'", $key ) );
		}
	}

	/**
	 * Removes a key from the filesystem
	 *
	 * @param string $key     The key to remove.
	 */
	public function remove( string $key ) {
		$path = path_join( $this->uploads_dir, $key );
		if ( false === unlink( $path ) ) {
			throw new \Exception( sprintf( "Could not remove blob at key '%s'", $key ) );
		}
	}
}
