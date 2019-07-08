<?php
namespace Stack;

class FTPStorage
{
    // URI of the FTP server.
    private $ftpHost = null;

    // Used mainly for testing, in order to separate the environments.
    public $prefix = null;

    public function __construct(string $ftpHost = '', string $prefix = '')
    {
        $this->prefix = $prefix;
        $this->ftpHost = 'ftp://' . $ftpHost;

        stream_context_set_default(array(
            'ftp' => array(
                'overwrite' => true
            )
        ));

        \add_filter('upload_dir', [$this, 'filterUploadDir']);
        \add_filter('wp_delete_file', [$this, 'filterDeleteFile']);
    }

    /**
     * Filter used to set the uplaods directory.
     * We use it in order to append the ftp://<host>:<port> in front of file's path.
     */
    public function filterUploadDir(array $uploads) : array
    {
        $basedir = $this->getUploadsDir();

        $uploads['basedir'] = $basedir;
        $uploads['path'] = untrailingslashit($basedir . $uploads['subdir']);

        return $uploads;
    }

     /**
     * Unlink files starting with 'ftp://'
     *
     * This is needed because WordPress thinks a path starts with 'ftp://' is
     * not an absolute path and manipulate it in a wrong way before unlinking
     * intermediate files.
     *
     * TODO: Use `path_is_absolute` filter when a bug below is resolved:
     *       https://core.trac.wordpress.org/ticket/38907#ticket
     *
     * Because path_join() doesn't recognize ftp:// as an absolute path, we need
     * to remove ftp://<host>:<port>/ since for thumbnail will multiple such prefixes
     * eg: ftp://<host>:<port>/wp-content/uploads/ftp://<host>:<port>/wp-content/uploads/...
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

        if ($this->startsWith($filePath, 'ftp://')) {
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
     *  Creates the new prefix, by appending it to the old one. After that, set it as the new prefix.
     *  Used in tests.
     */
    public function appendPrefix(string $prefix)
    {
        $this->createDir($prefix);
        $this->prefix = sprintf("%s/%s", $this->prefix, $prefix);
    }

    /**
     *  Create the given prefix and set it as the new prefix.
     *  Used in tests.
     */
    public function setPrefix(string $prefix)
    {
        $this->prefix = '';
        $this->createDir($prefix);
        $this->prefix = $prefix;
    }

    /**
     *  Helper method that creates a new directory, using the current prefix.
     *  Used in tests.
     */
    public function createDir(string $dir)
    {
        $recursive = substr_count($dir, '/') > 0;

        if (!mkdir($this->getFTPPath($dir), 0777, $recursive)) {
            die("Couldn't create directory $dir\n");
        }
    }

    /**
     * Returns a remote FTP path, using the current prefix.
     */
    public function getFTPPath(string $remotePath) : string
    {
        $ftpPath = $this->ftpHost;

        if ($this->prefix) {
            $ftpPath = sprintf('%s/%s', $ftpPath, $this->prefix);
        }

        return sprintf("%s/%s", $ftpPath, $remotePath);
    }

    /**
     * Returns the remote FTP upload path, using the current prefix.
     */
    private function getUploadsDir() : string
    {
        $uploadsDir = defined('UPLOADS') ? trim(UPLOADS, '/') : 'wp-content/uploads';
        $path = $this->getFTPPath($uploadsDir);
        return $path;
    }
}
