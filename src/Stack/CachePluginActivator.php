<?php
namespace Stack;

class CachePluginActivator
{
    public function __construct()
    {
        add_action('admin_init', [$this, 'autoActivatePlugin']);
    }

    public static function autoActivatePlugin()
    {
        if (STACK_PAGE_CACHE_AUTOMATIC_PLUGIN_ON_OFF) {
            $isActive = \is_plugin_active('nginx-helper/nginx-helper.php');

            if (!STACK_PAGE_CACHE_ENABLED) {
                if ($isActive) {
                    \deactivate_plugins('nginx-helper/nginx-helper.php');
                }

                return;
            }

            $nginxHelperShouldBeActive = STACK_PAGE_CACHE_BACKEND == "redis" || STACK_PAGE_CACHE_BACKEND == "memcached";

            if ($nginxHelperShouldBeActive && !$isActive) {
                \activate_plugin('nginx-helper/nginx-helper.php');
            } elseif (!$nginxHelperShouldBeActive && $isActive) {
                \deactivate_plugins('nginx-helper/nginx-helper.php');
            }
        }
    }
}
