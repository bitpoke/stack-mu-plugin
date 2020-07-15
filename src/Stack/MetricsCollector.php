<?php
namespace Stack;

class MetricsRegistry {

    public function __construct( $metrics = [] ) {
        $storage = new \Prometheus\Storage\APC();
        $this->registry = new \Prometheus\CollectorRegistry($storage);
        $this->register_metrics($metrics);
    }

    public function register_metrics( $metrics = [] ) {
        foreach ($metrics as $metric => [$kind, $description, $labels]) {
            [$namespace, $name] = explode('.', $metric);

            switch ($kind) {
                case 'histogram':
                    $this->metrics[$metric] = $this->registry->registerHistogram(
                        $namespace,
                        $name,
                        $description,
                        $labels
                    );
                    break;

                case 'counter':
                    $this->metrics[$metric] = $this->registry->registerCounter(
                        $namespace,
                        $name,
                        $description,
                        $labels
                    );
                    break;

                case 'gauge':
                    $this->metrics[$metric] = $this->registry->registerGauge(
                        $namespace,
                        $name,
                        $description,
                        $labels
                    );
                    break;
            }
        }
    }

    public function get_gauge($metric) {
        return $this->metrics[$metric];
    }

    public function get_counter($metric) {
        return $this->metrics[$metric];
    }

    public function get_histogram($metric) {
        return $this->metrics[$metric];
    }

    public function render() {
        $renderer = new \Prometheus\RenderTextFormat();
        $result = $renderer->render($this->registry->getMetricFamilySamples());

        header('Content-type: ' . \Prometheus\RenderTextFormat::MIME_TYPE);

        echo $result;
        exit();
    }
}

class MetricsCollector {
    const REQUEST_TYPES = array( 'cron', 'api', 'admin-ajax', 'admin', 'search', 'frontpage', 'singular', 'archive', 'other' );

    private $registry;
    private $metrics;
    private $wpdb_stats;

    public function __construct() {
        $this->metrics = new MetricsRegistry(
            array(
                'wp.requests'              => array( 'counter',   'Number of requests',                                 ['request_type'] ),
                'wp.page_generation_time'  => array( 'histogram', 'Page generation time, in seconds',                   ['request_type'] ),
                'wp.peak_memory'           => array( 'histogram', 'Peak memory per request, in bytes',                  ['request_type'] ),
                'wpdb.query_time'          => array( 'histogram', 'Total MySQL query time per request, in seconds',     ['request_type'] ),
                'wpdb.num_queries'         => array( 'histogram', 'Total number of MySQL queries per request',          ['request_type'] ),
                'wpdb.num_slow_queries'    => array( 'histogram', 'Number of MySQL slow queries per request',           ['request_type'] ),
                'wpdb.slow_query_treshold' => array( 'gauge',     'The treshold for counting slow queries, in seconds', [] ),
                'woocommerce.orders'       => array( 'counter',   'Number of completed WooCommerce orders',             [] ),
                'woocommerce.checkouts'    => array( 'counter',   'Number of started WooCommerce checkouts',            [] )
            )
        );

        $this->register_hooks();
        $this->init_wpdb_stats();
    }


    public function init_wpdb_stats() {
        $this->wpdb_stats['slow_query_treshold'] = defined( 'SLOW_QUERY_THRESHOLD' ) ? SLOW_QUERY_THRESHOLD : 2000;
        $this->wpdb_stats['query_time'] = 0;
        $this->wpdb_stats['num_queries'] = 0;
        $this->wpdb_stats['num_slow_queries'] = 0;
    }

    public function collect_request_metrics() {
        $request_type = $this::_get_request_type();
        $display      = 0;
        $precision    = 12;
        $request_time = timer_stop($display, $precision);
        $peak_memory  = memory_get_peak_usage();

        $this->metrics->get_counter('wp.requests')->incBy(1, [$request_type]);
        $this->metrics->get_histogram('wp.peak_memory')->observe($peak_memory, [$request_type]);
        $this->metrics->get_histogram('wp.page_generation_time')->observe($request_time, [$request_type]);

        if ( $this::_do_collect_wpdb_metrics() ) {
            $this->metrics->get_histogram('wpdb.query_time')->observe($this->wpdb_stats['query_time'], [$request_type]);
            $this->metrics->get_histogram('wpdb.num_queries')->observe($this->wpdb_stats['num_queries'], [$request_type]);
            $this->metrics->get_histogram('wpdb.num_slow_queries')->observe($this->wpdb_stats['num_slow_queries'], [$request_type]);
        }
    }

    public function register_endpoint() {
        $version   = '1';
        $namespace = 'stack/v' . $version;
        $base      = 'metrics';

        register_rest_route($namespace, '/' . $base, array(
            array(
                'methods'  => \WP_REST_Server::READABLE,
                'callback' => [$this->metrics, 'render']
            )
        ) );
    }

    public function collect_wpdb_stats($query_data, $query, $query_time, $query_callstack, $query_start) {
        if ( ! $this::_do_collect_wpdb_metrics() ) {
            return;
        }

        $query_duration = $query_time - $query_start;
        $this->wpdb_stats['num_queries'] += 1;
        $this->wpdb_stats['query_time'] += $query_time;

        if ($query_time > $this->wpdb_stats['slow_query_treshold']) {
            $this->wpdb_stats['num_slow_queries'] += 1;
        }

        return $query_data;
    }

    public function track_woocomerce_order($order_id, $old_status, $new_status){
        if ($new_status == 'completed') {
            $this->metrics['woocommerce.orders']->inc();
        }
    }

    public function track_woocomerce_checkout() {
        $this->metrics['woocommerce.checkouts']->inc();
    }

    private function register_hooks() {
        add_action('rest_api_init', [$this, 'register_endpoint']);
        add_action('shutdown', [$this, 'collect_request_metrics']);

        if ( $this::_do_collect_wpdb_metrics() ) {
            add_filter('log_query_custom_data', [$this, 'collect_wpdb_stats'], 10, 5);
        }

        if ( $this::_do_collect_woocommerce_metrics() ) {
            add_action('woocommerce_checkout_billing', [$this, 'track_woocomerce_checkout']);
            add_action('woocommerce_order_status_changed', [$this, 'track_woocomerce_order'], 10, 3);
        }
    }

    private function _do_collect_wpdb_metrics() {
        return isset($this->wpdb_stats);
    }

    private function _do_collect_woocommerce_metrics() {
        return function_exists('is_woocommerce') && is_woocommerce();
    }

    private function _get_request_type() {
        if ( defined('DOING_CRON') && DOING_CRON ) {
            return 'cron';
        }
        if ( defined('REST_REQUEST') && REST_REQUEST ) {
            return 'api';
        }
        if ( defined('DOING_AJAX') && DOING_AJAX ) {
            return 'admin-ajax';
        }
        if ( is_admin() ) {
            return 'admin';
        }
        if ( is_search() ) {
            return 'search';
        }
        if ( is_front_page() || is_home() ) {
            return 'frontpage';
        }
        if ( is_singular() ) {
            return 'singular';
        }
        if ( is_archive() ) {
            return 'archive';
        }

        return 'other';
    }

}

?>
