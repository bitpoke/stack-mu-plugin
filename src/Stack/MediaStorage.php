<?php
namespace Stack;

use \add_filter;
use \remove_filter;

class MediaStorage
{
    public function __construct()
    {
        $blobStore = new \Stack\BlobStore\WordPressObjectCache();
        $fs = \Stack\MediaFilesystem\StreamWrapper::register($blobStore);
    }

    public function register()
    {
        add_filter('upload_dir', [$this, 'filterUploadDir']);
        add_filter('wp_delete_file', [$this, 'filterDeleteFile']);
    }

    public function unregister()
    {
        remove_filter('upload_dir', [$this, 'filterUploadDir']);
        remove_filter('wp_delete_file', [$this, 'filterDeleteFile']);
    }

    /**
     * Filter used to set the uplaods directory.
     * We use it in order to append the ://<host>:<port> in front of file's path.
     */
    public function filterUploadDir(array $uploads) : array
    {
        $basedir = $this->getUploadsDir();

        $uploads['basedir'] = $basedir;
        $uploads['path'] = untrailingslashit($basedir . $uploads['subdir']);

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
     */
    public function filterDeleteFile(string $filePath) : string
    {
        $baseDir = $this->getUploadsDir();

        if ($this->startsWith($filePath, $baseDir)) {
            while ($this->startsWith($filePath, $baseDir)) {
                $filePath = $this->removePrefix($filePath, $baseDir);
            }
            $filePath = $baseDir . $filePath;
        }

        if ($this->startsWith($filePath, 'media://')) {
            @unlink($filePath);
            // unlink() does not clear the cache if you are performing file_exists()
            // http://php.net/manual/en/function.clearstatcache.php#105888
            clearstatcache();
            return '';
        }

        return $filePath;
    }

    private function startsWith(string $path, string $prefix) : bool
    {
        return substr($path, 0, strlen($prefix)) == $prefix;
    }

    private function removePrefix(string $path, string $prefix) : string
    {
        return str_replace($prefix, '', $path);
    }

    /**
     * Returns a remote  path, using the current prefix.
     */
    public function getPath(string $remotePath) : string
    {
        return sprintf("media://%s", $remotePath);
    }

    /**
     * Returns the remote  upload path, using the current prefix.
     */
    private function getUploadsDir() : string
    {
        $uploadsDir = defined('UPLOADS') ? trim(UPLOADS, '/') : 'wp-content/uploads';
        $path = $this->getPath($uploadsDir);
        return $path;
    }
}
