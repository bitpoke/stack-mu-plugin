<?php

namespace Stack\Integrations\WooCommerce;

/**
 * WooCommerce log handler for logging to console using error_log.
 */

/**
 * Class WC_Console_Log_Handler
 */
class WC_Console_Log_Handler extends \WC_Log_Handler
{
    /**
     * Handle a log entry.
     *
     * @param int    $timestamp Log timestamp.
     * @param string $level emergency|alert|critical|error|warning|notice|info|debug.
     * @param string $message Log message.
     * @param array  $context {
     *      Additional information for log handlers.
     *
     *     @type string $source Optional. Determines log file to write to. Default 'log'.
     *     @type bool $_legacy Optional. Default false. True to use outdated log format
     *         originally used in deprecated WC_Logger::add calls.
     * }
     *
     * @return bool False if value was not handled and true if value was handled.
     */
    public function handle($timestamp, $level, $message, $context)
    {
        $entry = self::format_entry($timestamp, $level, $message, $context);

        error_log($entry);
    }

    protected static function format_context($context)
    {
        $ctx = '';
        foreach ($context as $key => $value) {
            if ($key == 'backtrace') {
                continue;
            }
            $ctx .= " $key=" . wp_json_encode($value);
        }
        return $ctx;
    }

    /**
     * Builds a log entry text from level, timestamp and message.
     *
     * @param int    $timestamp Log timestamp.
     * @param string $level emergency|alert|critical|error|warning|notice|info|debug.
     * @param string $message Log message.
     * @param array  $context Additional information for log handlers.
     *
     * @return string Formatted log entry.
     */
    protected static function format_entry($timestamp, $level, $message, $context)
    {
        if (defined('STACK_JSON_LOG') && true === STACK_JSON_LOG) {
            $time_string  = self::format_time($timestamp);
            $level_string = strtoupper($level);
            $entry        = array(
                'timestamp' => $time_string,
                'severity'  => strtoupper($level_string),
                'message'   => $message,
                'labels'    => $context,
            );
            return wp_json_encode($entry);
        } else {
            return parent::format_entry($timestamp, $level, $message, $context) . self::format_context($context);
        }
    }
}
