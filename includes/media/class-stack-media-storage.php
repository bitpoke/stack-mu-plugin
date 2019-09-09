<?php
/**
 * Presslabs Stack media library storage using stream wrappers
 *
 * @package    Stack
 * @subpackage Media
 * @author     Calin Don <calin@presslabs.com>
 * @link       https://github.com/presslabs/stack-mu-plugin
 * @copyright  2019 Pressinfra
 */

namespace Stack\Media;

/**
 * Presslabs Stack media library storage using string wrappers
 *
 * Registers the media:// stream wrapper an configures the storage backend.
 */
class Stack_Media_Storage {

	/**
	 * Path to the uploads dir, relative to the document root
	 *
	 * @var string
	 */
	private $rel_uploads_dir = null;

	/**
	 * Configures the default storage backend for media:// stream wrapper and registers default php action and filters
	 * hooks
	 *
	 * @var string
	 */
	public function __construct() {
		$this->rel_uploads_dir = trim( defined( STACK_MEDIA_PATH ) ? STACK_MEDIA_PATH : 'wp-content/uploads', '/' );

		$parts = wp_parse_url( STACK_MEDIA_BUCKET );

		switch ( $parts['scheme'] ) {
			case 'objcache':
				$blob_store = new \Stack\BlobStore\WordPressObjectCache();
				break;
			case 'gs':
			case 'gcs':
				$blob_store = new \Stack\BlobStore\GoogleCloudStorage( $parts['host'], $parts['path'] ?: '' );
				break;
			case 'file':
			case '':
				$blob_store = $this->local_blob_store( $parts['path'] );
				break;
			default:
				wp_die( 'Invalid protocol <code>' . esc_html( $parts['scheme'] ) . '</code> for media storage.' );
		}

		$fs = Stack_Media_Stream_Wrapper::register( $blob_store, 'media' );
		$this->register();
	}

	/**
	 * Returns a LocalFilesystem BlobStore taking into account WordPress particularities
	 *
	 * @param string $path Optional path to use as backend for local filesystem blob store.
	 */
	private function local_blob_store( string $path = '' ) {
		if ( empty( $path ) ) {
			if ( $this->ends_with( $path, '/' . $this->rel_uploads_dir ) ) {
				$path = substr( $path, 0, -strlen( '/' . $this->rel_uploads_dir ) );
			}
		}
		return new \Stack\Media\Blob_Storage_Filesystem( $path );
	}

	/**
	 * Registers hooks for WordPress actions and filters
	 */
	public function register() {
		add_filter( 'upload_dir', [ $this, 'filter_upload_dir' ] );
		add_filter( 'wp_delete_file', [ $this, 'filter_delete_file' ] );
		add_filter( 'wp_image_editors', [ $this, 'disable_imagick' ] );
		add_action( 'init', [ $this, 'serve_media_file' ] );
	}

	/**
	 * Unregister the registered hooks for WordPress actions and filters
	 */
	public function unregister() {
		remove_filter( 'upload_dir', [ $this, 'filter_upload_dir' ] );
		remove_filter( 'wp_delete_file', [ $this, 'filter_delete_file' ] );
		remove_filter( 'wp_image_editors', [ $this, 'disable_imagick' ] );
		remove_action( 'init', [ $this, 'serve_media_file' ] );
	}

	/**
	 * Serves media files trough PHP in case we run `wp server`
	 */
	public function serve_media_file() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$request = ( is_ssl() ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		$upload  = wp_upload_dir();
		if ( $this->starts_with( $request, $upload['baseurl'] ) ) {
			$path     = substr( $request, strlen( $upload['baseurl'] ) );
			$filetype = wp_check_filetype( $path );

			if ( empty( $filetype['ext'] ) ) {
				wp_die( 'Directory listing disabled.', 'UNAUTHORIZED', 403 );
			} else {
				header( 'Content-Type: ' . $filetype['type'] );
				readfile( 'media://' . $this->rel_uploads_dir . $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
				die();
			}
		}
	}

	/**
	 * Disables imagick since there is no support in WordPress.
	 * https://github.com/presslabs/stack-mu-plugin/issues/1
	 *
	 * @param array $image_editors The list of available image editors.
	 */
	public function disable_imagick( array $image_editors ) : array {
		$editors = array();
		foreach ( $image_editors as $editor ) {
			if ( 'WP_Image_Editor_Imagick' !== $editor ) {
				$editors [] = $editor;
			}
		}
		return $editors;
	}

	/**
	 * Filter used to set the uplaods directory.
	 * We use it in order to append the ://<host>:<port> in front of file's path.
	 *
	 * @param array $uploads The uploads array according to
	 *                       https://developer.wordpress.org/reference/functions/wp_upload_dir/.
	 */
	public function filter_upload_dir( array $uploads ) : array {
		$basedir = $this->uploads_dir();

		$uploads['basedir'] = $basedir;
		$uploads['path']    = untrailingslashit( $basedir . $uploads['subdir'] );

		return $uploads;
	}

	/**
	 * Unlink files starting with 'media://'
	 *
	 * This is needed because WordPress thinks a path starts with '://' is
	 * not an absolute path and manipulate it in a wrong way before unlinking
	 * intermediate files.
	 *
	 * TODO: Use `path_is_absolute` filter when a bug below is resolved:
	 *       https://core.trac.wordpress.org/ticket/38907#ticket
	 *
	 * Because path_join() doesn't recognize :// as an absolute path, we need
	 * to remove ://<host>:<port>/ since for thumbnail will multiple such prefixes
	 * eg: ://<host>:<port>/wp-content/uploads/ftp://<host>:<port>/wp-content/uploads/...
	 *
	 * @param string $path The path to delete.
	 */
	public function filter_delete_file( string $path ) : string {
		$basedir = $this->uploads_dir();

		if ( $this->starts_with( $path, $basedir ) ) {
			while ( $this->starts_with( $path, $basedir ) ) {
				$path = $this->remove_prefix( $path, $basedir );
			}
			$path = $basedir . $path;
		}

		if ( $this->starts_with( $path, 'media://' ) ) {
			@unlink( $path ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			// unlink() does not clear the cache if you are performing file_exists()
			// http://php.net/manual/en/function.clearstatcache.php#105888.
			clearstatcache();
			return '';
		}

		return $path;
	}

	/**
	 * Checks that a string starts with a substring.
	 *
	 * @param string $haystack The string to check.
	 * @param string $needle   The string to look for.
	 * @return bool            TRUE if the $haystack starts with $needed. FALSE otherwise.
	 */
	private function starts_with( string $haystack, string $needle ) : bool {
		$length = strlen( $needle );
		return ( substr( $haystack, 0, $length ) === $needle );
	}

	/**
	 * Checks that a string end with a substring.
	 *
	 * @param string $haystack The string to check.
	 * @param string $needle   The string to look for.
	 * @return bool            TRUE if the $haystack ends with $needed. FALSE otherwise.
	 */
	private function ends_with( string $haystack, string $needle ) : bool {
		$length = strlen( $needle );
		if ( 0 === $length ) {
			return true;
		}

		return ( substr( $haystack, -$length ) === $needle );
	}


	/**
	 * Remove a prefix from a string
	 *
	 * @param string $s      The string to remove prefix from.
	 * @param string $prefix The string to look for.
	 * @return string        The string with prefix removed
	 */
	private function remove_prefix( string $s, string $prefix ) : string {
		return str_replace( $prefix, '', $s );
	}

	/**
	 * Returns a remote  path, using the current prefix.
	 *
	 * @param string $path The path to get the remote path for.
	 * @return string      The fully qualified path, including media:// prefix
	 */
	public function remote_path( string $path ) : string {
		return sprintf( 'media://%s', $path );
	}

	/**
	 * Returns the remote  upload path, using the current prefix.
	 */
	private function uploads_dir() : string {
		return $this->remote_path( $this->rel_uploads_dir );
	}
}
