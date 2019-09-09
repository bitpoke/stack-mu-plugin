<?php
/**
 * The blob storage interface
 *
 * @package    Stack
 * @subpackage Media
 * @author     Calin Don <calin@presslabs.com>
 * @link       https://github.com/presslabs/stack-mu-plugin
 * @copyright  2019 Pressinfra
 */

namespace Stack\Media;

interface Blob_Storage_Interface {
	/**
	 * Gets an key from the blob storage backend
	 *
	 * @param string $key The key to get.
	 * @return string     The key contents.
	 */
	public function get( string $key ) : string;

	/**
	 * Gets key metadata from blob storage backend
	 *
	 * @param string $key The key to get.
	 * @return array      The key metadata.
	 */
	public function get_metadata( string $key ) : array;

	/**
	 * Sets an key from the blob storage backend to the specified content
	 *
	 * @param string $key     The key to update.
	 * @param string $content The key contents to set.
	 */
	public function set( string $key, string $content );

	/**
	 * Removes a key from the blob storage backend.
	 *
	 * @param string $key     The key to remove.
	 */
	public function remove( string $key );
}
