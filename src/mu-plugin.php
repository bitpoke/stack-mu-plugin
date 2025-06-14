<?php
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

class WP_Filesystem_Stack extends Stack\WP_Filesystem_Stack
{
}

Stack\Config::loadDefaults();

new Stack\URLFixer();
new Stack\MediaStorage();
new Stack\QuerySplit();
new Stack\NginxHelperActivator();
new Stack\MetricsCollector();
new Stack\ContentFilter();
new Stack\Integrations\WooCommerce();
