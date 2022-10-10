<?php
namespace Stack\Tests;

use PHPUnit\Framework\TestCase;
use Stack\CDNOffloader;

class CDNOffloaderUnitTest extends TestCase
{

    private $cdn_host = "cdn.example.org";
    private $offload_hosts = array("example.org", "abc.example.org", "shop.example.org");
    private $offload_paths = array("wp-content", "wp-includes", "wp/wp-includes");
    private $non_offloaded_hosts = array("external.com", "external.org");
    private $non_offloaded_paths = array("", "not-offloaded", ".internal", "api");

    public function setUp()
    {
        parent::setUp();
        unset($_SERVER['HTTPS']); // reset https

        $this->assertEmpty(array_intersect($this->offload_hosts, $this->non_offloaded_hosts));
        $this->assertEmpty(array_intersect($this->offload_paths, $this->non_offloaded_paths));

        add_filter('bpk_cdn_host', function () {
            return $this->cdn_host;
        });
        add_filter('bpk_cdn_offload_hosts', function () {
            return $this->offload_hosts;
        });
        add_filter('bpk_cdn_offload_paths', function () {
            return $this->offload_paths;
        });
    }

    public function dataSkipOffloading()
    {
        $dataset = array(
            array("http://example.org"),
            array("http://abc.example.org"),
            array("http://shop.example.org"),
            array("https://example.org"),
            array("https://abc.example.org"),
            array("https://shop.example.org"),
            array("//example.org"),
            array("//abc.example.org"),
            array("//shop.example.org"),

            array("./wp-content/relative.css"),

            array("http://unlinked.example.org/wp-content/test.css"),
            array("https://unlinked.example.org/wp-content/test.css"),
            array("//unlinked.example.org/wp-content/test.css"),

            array("http://user@pass:example.org/wp-content/test.css"),
            array("https://user@pass:example.org/wp-content/test.css"),
            array("//user@pass:example.org/wp-content/test.css"),
            array("http://example.org/☺.css", "http://example.org/☺.css"),
        );

        $escaped_dataset = array_map(function ($entry) {
            return array_map('json_encode', $entry);
        }, $dataset);

        return array_merge($dataset, $escaped_dataset);
    }

    public function dataOffloadedURLs()
    {
        $dataset = array(
            array(
                "https://example.org/wp-content/test.css",
                "https://cdn.example.org/wp-content/test.css"
            ),
            array(
                "https://example.org/wp-content/uploads/2022/10/foo.jpeg",
                "https://cdn.example.org/wp-content/uploads/2022/10/foo.jpeg"
            ),
            array(
                "https://example.org/wp/wp-includes/test.js",
                "https://cdn.example.org/wp/wp-includes/test.js"
            ),
            array(
                "http://example.org/wp-content/☺.css",
                "http://cdn.example.org/wp-content/☺.css"
            ),
        );

        $escaped_dataset = array_map(function ($entry) {
            return array_map('json_encode', $entry);
        }, $dataset);

        return array_merge($dataset, $escaped_dataset);
    }

    /**
     * @dataProvider dataSkipOffloading
     */
    public function testSkipOffloading($input)
    {
        $this->assertSame($input, CDNOffloader::offload($input));
    }

    public function testMultilineOffloading()
    {

        // data here is line-by-line
        $data = array(
            // test we don't match partial content by mistake
            array(
                "https://example.org/wp-content/test.csshttps://example.org/wp-content/test-sub/test.css",
                "https://example.org/wp-content/test.csshttps://example.org/wp-content/test-sub/test.css",
            ),
            // test matching multiple urls on a single line
            array(
                "https://example.org/wp-content/test.css https://example.org/wp-content/test-sub/test.css",
                "https://cdn.example.org/wp-content/test.css https://cdn.example.org/wp-content/test-sub/test.css",
            ),
            // test that we properly stop at then end of a url in a text corpus
            array(
                "http://example.org/wp-includes/foo.jpg-foo-bar",
                "http://cdn.example.org/wp-includes/foo.jpg-foo-bar",
            ),
        );
        $input = implode("\n", array_column($data, 0));
        $expected = implode("\n", array_column($data, 1));

        // plain text
        $this->assertSame($expected, CDNOffloader::offload($input));
        // json encoded content
        $this->assertSame(json_encode($expected), CDNOffloader::offload(json_encode($input)));
    }



    /**
     * @dataProvider dataOffloadedURLs
     */
    public function testOffloadedURLs($input, $expected)
    {
        $this->assertSame($expected, CDNOffloader::offload($input));
    }

    public function dataMarkupOffload()
    {
        $dataset = array(
            // html
            array(
                '<a href="">http://example.org/wp-content/test.css</a>',
                '<a href="">http://cdn.example.org/wp-content/test.css</a>'
            ),
            // css
            array(
                'background-image: url(http://example.org/wp-content/test.css);',
                'background-image: url(http://cdn.example.org/wp-content/test.css);'
            ),
            // Markdown
            array(
                '[http://example.org/wp-content/test.css](http://example.org/wp-content/test.css)',
                '[http://cdn.example.org/wp-content/test.css](http://cdn.example.org/wp-content/test.css)'
            )
        );

        return $dataset;
    }


    /**
     * @dataProvider dataMarkupOffload
     */
    public function testMarkupOffload($input, $expected)
    {
        $this->assertSame($expected, CDNOffloader::offload($input));
    }
}
