<?php
namespace Stack;

class MetricsCollector
{
    const REQUEST_TYPES = array(
        'cron', 'api', 'admin-ajax', 'admin', 'search', 'frontpage', 'singular', 'archive', 'other'
    );

    private $registry;
    private $metrics;
    private $wpdbStats;

    public function __construct()
    {
        $this->metrics = new MetricsRegistry(
            array(
                'wp.requests' => array(
                    'counter',
                    'Number of requests',
                    ['request_type']
                ),
                'wp.page_generation_time' => array(
                    'histogram',
                    'Page generation time, in seconds',
                    ['request_type']
                ),
                'wp.peak_memory' => array(
                    'histogram',
                    'Peak memory per request, in bytes',
                    ['request_type']
                ),
                'wpdb.query_time' => array(
                    'histogram',
                    'Total MySQL query time per request, in seconds',
                    ['request_type']
                ),
                'wpdb.num_queries' => array(
                    'histogram',
                    'Total number of MySQL queries per request',
                    ['request_type']
                ),
                'wpdb.num_slow_queries' => array(
                    'histogram',
                    'Number of MySQL slow queries per request',
                    ['request_type']
                ),
                'wpdb.slow_query_treshold' => array(
                    'gauge',
                    'The treshold for counting slow queries, in seconds',
                    []
                ),
                'woocommerce.orders' => array(
                    'counter',
                    'Number of completed WooCommerce orders',
                    []
                ),
                'woocommerce.checkouts' => array(
                    'counter',
                    'Number of started WooCommerce checkouts',
                    []
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
        $requestType  = $this::getRequestType();
        $display      = 0;
        $precision    = 12;
        $request_time = timer_stop($display, $precision);
        $peak_memory  = memory_get_peak_usage();

        $this->metrics->getCounter('wp.requests')->incBy(1, [$requestType]);
        $this->metrics->getHistogram('wp.peak_memory')->observe($peak_memory, [$requestType]);
        $this->metrics->getHistogram('wp.page_generation_time')->observe($request_time, [$requestType]);

        if ($this::canCollectWpdbMetrics()) {
            $this->metrics->getHistogram('wpdb.query_time')->observe(
                $this->wpdbStats['query_time'],
                [$requestType]
            );
            $this->metrics->getHistogram('wpdb.num_queries')->observe(
                $this->wpdbStats['num_queries'],
                [$requestType]
            );
            $this->metrics->getHistogram('wpdb.num_slow_queries')->observe(
                $this->wpdbStats['num_slow_queries'],
                [$requestType]
            );
        }
    }

    public function registerEndpoint()
    {
        $version   = '1';
        $namespace = 'stack/v' . $version;
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

        $query_duration = $queryTime - $queryStart;
        $this->wpdbStats['num_queries'] += 1;
        $this->wpdbStats['query_time'] += $queryTime;

        if ($queryTime > $this->wpdbStats['slow_query_treshold']) {
            $this->wpdbStats['num_slow_queries'] += 1;
        }

        return $queryData;
    }

    public function trackWoocomerceOrder($order_id, $old_status, $new_status)
    {
        if ($new_status == 'completed') {
            $this->metrics['woocommerce.orders']->inc();
        }
    }

    public function trackWoocomerceCheckout()
    {
        $this->metrics['woocommerce.checkouts']->inc();
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
