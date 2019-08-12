<?php

namespace Stack\BlobStore;

use \Google\Cloud\Core\Exception\NotFoundException;
use \Google\Cloud\Storage\Bucket;
use \Google\Cloud\Storage\StorageClient;
use \Stack\BlobStore;

class GoogleCloudStorage implements BlobStore
{
    /**
     * Google Cloud Storage bucket name
     *
     * @var string
     */
    private $bucket;

    /**
     * Google Cloud Storage bucket prefix
     *
     * @var string
     */
    private $prefix;

    /**
     * Google Cloud Storage client
     *
     * @var StorageClient
     */
    private $client;

    public function __construct(string $bucket, string $prefix = '')
    {
        if (!defined('STDERR')) {
            define('STDERR', fopen('php://stderr', 'w'));
        }
        putenv('SUPPRESS_GCLOUD_CREDS_WARNING=true');
        $this->client = new StorageClient([
            'suppressKeyFileNotice' => true,
        ]);
        $this->bucket = $bucket;
        $this->prefix = $prefix;
    }

    private function bt()
    {
        ob_start();
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($bt as $frame) {
            echo @$frame["class"] . @$frame["type"] . @$frame["function"] . PHP_EOL;
        }
        // fprintf(STDERR, ob_get_clean());
    }

    private function normalizePath(string $path) : string
    {
        return ltrim(path_join($this->prefix, $path), '/');
    }

    public function get(string $key) : string
    {
        // fprintf(STDERR, ">>>>>>>>>>>>>>>> get %s [%s] %s\n", $key, @$_REQUEST['action'], $_SERVER['REQUEST_URI']);
        try {
            $bucket = $this->client->bucket($this->bucket);
            $object = $bucket->object($this->normalizePath($key));
            $result = $object->downloadAsString();
            return $result;
        } catch (NotFoundException $e) {
            throw new \Stack\BlobStore\Exceptions\NotFound($e->getMessage());
        }
    }

    public function getMeta(string $key)
    {
        // fprintf(STDERR, ">>>>>>>>>>>>>>>> getMeta %s [%s]\n", $key, @$_REQUEST['action']);
        try {
            $bucket = $this->client->bucket($this->bucket);
            $object = $bucket->object($this->normalizePath($key));
            $info = $object->info();
        } catch (NotFoundException $e) {
            throw new \Stack\BlobStore\Exceptions\NotFound($e->getMessage());
        }
        $now = time();
        return [
            "size" => isset($info['size']) ? $info['size'] : 0,
            "atime" => isset($info['updated']) ? strtotime($info['updated']) : $now,
            "ctime" => isset($info['timeCreated']) ? strtotime($info['timeCreated']) : $now,
            "mtime" => isset($info['updated']) ? strtotime($info['updated']) : $now,
        ];
    }

    public function set(string $key, string $content)
    {
        // fprintf(STDERR, ">>>>>>>>>>>>>>>> set %s len=%d [%s]\n", $key, strlen($content), @$_REQUEST['action']);
        try {
            $bucket = $this->client->bucket($this->bucket);
            $uploader = $bucket->upload($content, [
                'name' => $this->normalizePath($key),
                'resumable' => false,
            ]);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function remove(string $key)
    {
        // fprintf(STDERR, ">>>>>>>>>>>>>>>> remove %s [%s]\n", $key, @$_REQUEST['action']);
        try {
            $bucket = $this->client->bucket($this->bucket);
            $object = $bucket->object($this->normalizePath($key));
            $object->delete();
        } catch (NotFoundException $e) {
            throw new \Stack\BlobStore\Exceptions\NotFound($e->getMessage());
        }
    }
}
