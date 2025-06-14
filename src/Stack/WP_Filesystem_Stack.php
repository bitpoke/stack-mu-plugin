<?php
namespace Stack;

use WP_Error;
use WP_Filesystem_Base;
use WP_Filesystem_Direct;

/**
 * Class WP_Filesystem
 *
 * This class extends the WP_Filesystem_Base class to provide a custom filesystem implementation
 * that uses Stack's BlobStorage for file operations.
 * It is designed to work with WordPress and integrates with the WordPress filesystem API.
 *
 * Inspired by https://github.com/Automattic/vip-go-mu-plugins-built/blob/master/files/class-wp-filesystem-vip.php
 *
 * @package Stack
 */

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
class WP_Filesystem_Stack extends WP_Filesystem_Base
{
    public function __construct($credentials = null, $context = null, $debug = false)
    {
        $this->method = 'stack';
        $this->errors = new \WP_Error();

        $this->direct = new WP_Filesystem_Direct(null);
    }

    protected function log_call($method, $args = [], $result = null)
    {
        if (!apply_filters('stack_filesystem_debug', false)) {
            return;
        }
        error_log("WP_Filesystem_Stack::$method called with args: " . json_encode($args) .
            ($result !== null ? " => " . json_encode($result) : ""));
    }

    public function get_contents(...$args)
    {
        $this->log_call("### " . __FUNCTION__, $args);
        return $this->direct->get_contents(...$args);
    }

    public function get_contents_array(...$args)
    {
        $this->log_call(__FUNCTION__, $args);
        return $this->direct->get_contents_array(...$args);
    }

    public function put_contents(...$args)
    {
        $this->log_call(__FUNCTION__, $args);
        return $this->direct->put_contents(...$args);
    }

    public function copy($source, $destination, $overwrite = false, $mode = false)
    {
        $this->log_call(__FUNCTION__, func_get_args());
        if (! $overwrite && $this->exists($destination)) {
            return false;
        }

        $rtval = copy($source, $destination);

        if ($mode) {
            $this->chmod($destination, $mode);
        }

        return $rtval;
    }

    /**
     * @param string $source
     * @param string $destination
     * @param bool $overwrite
     *
     * @return bool
     */
    public function move($source, $destination, $overwrite = false)
    {
        $this->log_call(__FUNCTION__, func_get_args());
        if (! $overwrite && $this->exists($destination)) {
            return false;
        }

        if ($overwrite && $this->exists($destination) && ! $this->delete($destination, true)) {
            // Can't overwrite if the destination couldn't be deleted.
            return false;
        }

        // Try using rename first. if that fails (for example, source is read only) try copy.
        if (@rename($source, $destination)) {
            return true;
        }

        // Backward compatibility: Only fall back to `::copy()` for single files.
        if ($this->is_file($source) && $this->copy($source, $destination, $overwrite) && $this->exists($destination)) {
            $this->delete($source);

            return true;
        } else {
            return false;
        }
    }

    public function delete(...$args)
    {
        $this->log_call(__FUNCTION__, $args);
        return $this->direct->delete(...$args);
    }

    public function size(...$args)
    {
        $this->log_call(__FUNCTION__, $args);
        return $this->direct->size(...$args);
    }

    public function exists(...$args)
    {
        $this->log_call(__FUNCTION__, $args);
        return $this->direct->exists(...$args);
    }

    public function is_file(...$args)
    {
        $this->log_call(__FUNCTION__, $args);
        return $this->direct->is_file(...$args);
    }

    public function is_dir(...$args)
    {
        $this->log_call(__FUNCTION__, $args);
        return $this->direct->is_dir(...$args);
    }

    public function is_readable(...$args)
    {
        $this->log_call(__FUNCTION__, $args);
        return $this->direct->is_readable(...$args);
    }

    public function is_writable(...$args)
    {
        $this->log_call(__FUNCTION__, $args);
        return $this->direct->is_writable(...$args);
    }

    public function atime(...$args)
    {
        $this->log_call(__FUNCTION__, $args);
        return $this->direct->atime(...$args);
    }

    public function mtime(...$args)
    {
        $this->log_call(__FUNCTION__, $args);
        return $this->direct->mtime(...$args);
    }

    public function touch(...$args)
    {
        $this->log_call(__FUNCTION__, $args);
        return $this->direct->touch(...$args);
    }

    public function mkdir(...$args)
    {
        $this->log_call(__FUNCTION__, $args);
        return $this->direct->mkdir(...$args);
    }

    public function rmdir(...$args)
    {
        $this->log_call(__FUNCTION__, $args);
        return $this->direct->rmdir(...$args);
    }

    public function dirlist(...$args)
    {
        $this->log_call(__FUNCTION__, $args);
        return $this->direct->dirlist(...$args);
    }


    public function cwd(...$args)
    {
        $this->log_call(__FUNCTION__, $args);
        return $this->direct->cwd(...$args);
    }

    public function chdir(...$args)
    {
        $this->log_call(__FUNCTION__, $args);
        return $this->direct->chdir(...$args);
    }

    public function chgrp(...$args)
    {
        $this->log_call(__FUNCTION__, $args);
        return $this->direct->chgrp(...$args);
    }

    public function chmod(...$args)
    {
        $this->log_call(__FUNCTION__, $args);
        return $this->direct->chmod(...$args);
    }

    public function chown(...$args)
    {
        $this->log_call(__FUNCTION__, $args);
        return $this->direct->chown(...$args);
    }

    public function owner(...$args)
    {
        $this->log_call(__FUNCTION__, $args);
        return $this->direct->owner(...$args);
    }

    public function getchmod(...$args)
    {
        $this->log_call(__FUNCTION__, $args);
        return $this->direct->getchmod(...$args);
    }


    public function group(...$args)
    {
        $this->log_call(__FUNCTION__, $args);
        return $this->direct->group(...$args);
    }
}
