<?php
namespace Stack;

class MetricsCollector
{
    private $registry;
    private $metrics;
    private $wpdbStats;

    public function __construct()
    {
        if (!defined('STACK_METRICS_ENABLED') || !STACK_METRICS_ENABLED) {
            return;
        }

        if (defined('WP_CLI') && WP_CLI) {
            return;
        }

        $this->metrics = new MetricsRegistry(
            array(
                'wp.requests' => array(
                    'counter',
                    'Number of requests',
                    ['host_name', 'site_name', 'request_type']
                ),
                'wp.page_generation_time' => array(
                    'histogram',
                    'Page generation time, in seconds',
                    ['host_name', 'site_name', 'request_type']
                ),
                'wp.peak_memory' => array(
                    'histogram',
                    'Peak memory per request, in bytes',
                    ['host_name', 'site_name', 'request_type']
                ),
                'wpdb.query_time' => array(
                    'histogram',
                    'Total MySQL query time per request, in seconds',
                    ['host_name', 'site_name', 'request_type']
                ),
                'wpdb.num_queries' => array(
                    'histogram',
                    'Total number of MySQL queries per request',
                    ['host_name', 'site_name', 'request_type']
                ),
                'wpdb.num_slow_queries' => array(
                    'histogram',
                    'Number of MySQL slow queries per request',
                    ['host_name', 'site_name', 'request_type']
                ),
                'wpdb.slow_query_treshold' => array(
                    'gauge',
                    'The treshold for counting slow queries, in seconds',
                    ['host_name', 'site_name']
                ),
                'woocommerce.orders' => array(
                    'counter',
                    'Number of completed WooCommerce orders',
                    ['host_name', 'site_name']
                ),
                'woocommerce.checkouts' => array(
                    'counter',
                    'Number of started WooCommerce checkouts',
                    ['host_name', 'site_name']
                )
            )
        );

        $this->registerHooks();
        $this->initWpdbStats();
    }

    public function initWpdbStats()
    {
        $this->wpdbStats['slow_query_treshold'] = defined('SLOW_QUERY_THRESHOLD') ? SLOW_QUERY_THRESHOLD : 2000;
        $this->wpdbStats['query_time'] = 0;
        $this->wpdbStats['num_queries'] = 0;
        $this->wpdbStats['num_slow_queries'] = 0;
    }

    public function collectRequestMetrics()
    {
        $requestType = $this::getRequestType();
        $siteName = $this::getSiteName();
        $hostName = gethostname();
        $requestTime = timer_stop(0, 12);
        $peakMemory  = memory_get_peak_usage();

        $this->metrics->getCounter('wp.requests')->incBy(
            1,
            [$hostName, $siteName, $requestType]
        );
        $this->metrics->getHistogram('wp.peak_memory')->observe(
            $peakMemory,
            [$hostName, $siteName, $requestType]
        );
        $this->metrics->getHistogram('wp.page_generation_time')->observe(
            $requestTime,
            [$hostName, $siteName, $requestType]
        );

        if ($this::canCollectWpdbMetrics()) {
            $this->metrics->getHistogram('wpdb.query_time')->observe(
                $this->wpdbStats['query_time'],
                [$hostName, $siteName, $requestType]
            );
            $this->metrics->getHistogram('wpdb.num_queries')->observe(
                $this->wpdbStats['num_queries'],
                [$hostName, $siteName, $requestType]
            );
            $this->metrics->getHistogram('wpdb.num_slow_queries')->observe(
                $this->wpdbStats['num_slow_queries'],
                [$hostName, $siteName, $requestType]
            );
            $this->metrics->getGauge('wpdb.slow_query_treshold')->set(
                $this->wpdbStats['slow_query_treshold'],
                [$hostName, $siteName]
            );
        }
    }

    public function registerEndpoint()
    {
        $namespace = 'stack/v' . STACK_REST_API_VERSION;
        $base      = 'metrics';

        register_rest_route($namespace, '/' . $base, array(
            array(
                'methods'  => \WP_REST_Server::READABLE,
                'callback' => [$this->metrics, 'render']
            )
        ));
    }

    public function collectWpdbStats($queryData, $query, $queryTime, $queryCallstack, $queryStart)
    {
        if (!$this::canCollectWpdbMetrics()) {
            return;
        }

        $this->wpdbStats['num_queries'] += 1;
        $this->wpdbStats['query_time'] += $queryTime;

        if ($queryTime > $this->wpdbStats['slow_query_treshold']) {
            $this->wpdbStats['num_slow_queries'] += 1;
        }

        return $queryData;
    }

    public function trackWoocomerceOrder($orderId, $oldStatus, $newStatus)
    {
        $siteName = $this::getSiteName();
        $hostName = gethostname();

        if ($new_status == 'completed') {
            $this->metrics['woocommerce.orders']->incBy(1, [$hostName, $siteName]);
        }
    }

    public function trackWoocomerceCheckout()
    {
        $siteName = $this::getSiteName();
        $hostName = gethostname();

        $this->metrics['woocommerce.checkouts']->incBy(1, [$hostName, $siteName]);
    }

    private function registerHooks()
    {
        add_action('rest_api_init', [$this, 'registerEndpoint']);
        add_action('shutdown', [$this, 'collectRequestMetrics']);

        if ($this::canCollectWpdbMetrics()) {
            add_filter('log_query_custom_data', [$this, 'collectWpdbStats'], 10, 5);
        }

        if ($this::canCollectWoocommerceMetrics()) {
            add_action('woocommerce_checkout_billing', [$this, 'trackWoocomerceCheckout']);
            add_action('woocommerce_order_status_changed', [$this, 'trackWoocomerceOrder'], 10, 3);
        }
    }

    private function canCollectWpdbMetrics()
    {
        return defined('SAVEQUERIES') && SAVEQUERIES;
    }

    private function canCollectWoocommerceMetrics()
    {
        return function_exists('is_woocommerce') && is_woocommerce();
    }

    private function getSiteName()
    {
        return defined('STACK_SITE_NAME') ? STACK_SITE_NAME : "";
    }

    private function getRequestType()
    {
        if (defined('DOING_CRON') && DOING_CRON) {
            return 'cron';
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return 'api';
        }
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return 'admin-ajax';
        }
        if (is_admin()) {
            return 'admin';
        }
        if (is_search()) {
            return 'search';
        }
        if (is_front_page() || is_home()) {
            return 'frontpage';
        }
        if (is_singular()) {
            return 'singular';
        }
        if (is_archive()) {
            return 'archive';
        }

        return 'other';
    }
}
