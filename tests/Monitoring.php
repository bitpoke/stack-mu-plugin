<?php

namespace Stack\Tests;

use PHPUnit\Framework\TestCase;
use Stack\MetricsCollector;
use Stack\MetricsRegistry;

use WP_REST_Request;
use Spy_REST_Server;

class MonitoringUnitTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        // Override the normal server with the spying server.
        $GLOBALS['wp_rest_server'] = new Spy_REST_Server();
        do_action('rest_api_init', $GLOBALS['wp_rest_server']);
    }

    public function tearDown()
    {
        remove_filter('wp_rest_server_class', array( $this, 'filter_wp_rest_server_class' ));
        parent::tearDown();
    }

    public function testMetricsRendering()
    {
        $mr = new MetricsRegistry(
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
                'wpdb.slow_query_treshold' => array(
                    'gauge',
                    'The treshold for counting slow queries, in seconds',
                    []
                ),
            )
        );

        $mr->getCounter('wp.requests')->incBy(
            1,
            ["request_type"]
        );

        $mr->getHistogram('wp.page_generation_time')->observe(
            1.23,
            ["request_type"]
        );

        $mr->getGauge('wpdb.slow_query_treshold')->set(
            4,
        );

        $output = $mr->render();
        $this->assertContains('php_info{version=', $output);
        $this->assertContains('wp_requests{request_type="request_type"} 1', $output);
        $this->assertContains('wp_page_generation_time_bucket{request_type="request_type",le="1"} 0', $output);
        $this->assertContains('wp_page_generation_time_bucket{request_type="request_type",le="2.5"} 1', $output);
        $this->assertContains('wpdb_slow_query_treshold 4', $output);
    }

    public function testMetricsHooks()
    {
        $mc = new MetricsCollector();

        $this->assertTrue(has_action('rest_api_init', [$mc, 'registerEndpoint']) > 0);
        $this->assertTrue(has_action('shutdown', [$mc, 'collectRequestMetrics']) > 0);
    }

    public function testMetricsRegisteredEndpoint()
    {
        $mc = new MetricsCollector();

        $endpoints = $GLOBALS['wp_rest_server']->get_raw_endpoint_data();

        $this->assertArrayHasKey('/stack/v1/metrics', $endpoints);
    }

    public function testMetricsEndpointRender()
    {
        $mc = new MetricsCollector();


        $request  = new WP_REST_Request('GET', '/stack/v1/metrics');
        $response = rest_get_server()->dispatch($request);

        $this->assertContains('php_info{version=', $response->data);
    }

    public function testMetricsPreEchoResponse()
    {
        $mc = new MetricsCollector();

        $out = $mc->preEchoResponse(
            "test",
            $GLOBALS['wp_rest_server'],
            new WP_REST_Request('GET', '/stack/v1/metrics')
        );
        $this->assertNull($out);


        $out = $mc->preEchoResponse("test", $GLOBALS['wp_rest_server'], new WP_REST_Request('GET', '/wp/v2/'));
        $this->assertEquals("test", $out);
    }
}
