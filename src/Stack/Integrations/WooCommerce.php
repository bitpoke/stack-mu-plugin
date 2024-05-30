<?php
/**
 * WooCommerce integration plugin.
 *
 * @package Stack\Integrations
 */

namespace Stack\Integrations;

class WooCommerce
{
    public function __construct()
    {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
        if (!\is_plugin_active('woocommerce/woocommerce.php')) {
            return;
        }

        add_filter('woocommerce_register_log_handlers', array($this, 'setupLogging'));
    }

    public function setupLogging($handlers)
    {
        return array(
            new \Stack\Integrations\WooCommerce\WC_Console_Log_Handler(),
        );
    }
}
