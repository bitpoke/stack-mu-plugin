<?php
namespace Stack\Tests;

class TestObjectCache extends \WP_UnitTestCase {
    function setUp() {
        wp_cache_init(); // reinitialize the cache
        wp_cache_flush();
        parent::setUp();
    }

    function testClassWPObjectCacheExists() {
        $this->assertTrue( class_exists( 'Stack_Object_Cache' ) );
    }

    function testObjectCacheBackendIsMemcache() {
        global $wp_object_cache;
        $this->assertEquals( $wp_object_cache->getBackendClass(), "Stack\ObjectCache\Memcached" );
    }

    function testAlloptionsDataraceCondition() {
        global $wpdb;
        mysqli_close( $wpdb->dbh ); //disconnect from db before fork

        $pid = pcntl_fork();
        $wpdb->db_connect(); // reconnect to the db after fork in both processes

        if ( $pid == -1 ) {
            $this->fail('could not fork');
        } elseif ( $pid ) {
            pcntl_waitpid( $pid, $status );
            $this->assertEquals( 0, $status ); // child exited successfully
            $this->assertTrue( add_option( 'one', 1 ) );
        } else {
            wp_cache_init(); // reinitialize the cache for child subprocess
            $status = add_option( 'two', 2 );
            exit($status ? 0 : 1 );
        }
        $this->assertEquals( 1, get_option( 'one' ) );
        $this->assertEquals( 2, get_option( 'two' ) );
    }

    function testWPObjectCacheReplace() {
        global $wp_object_cache;

        $key = __FUNCTION__;
        $val1 = 'first-val';
        $val2 = 'second-val';

        $fake_key = 'my-fake-key';

        // Save the first value to cache and verify
        $this->assertTrue( wp_cache_set( $key, $val1 ) );
        $this->assertEquals( $val1, wp_cache_get( $key ) );

        // Replace the value and verify
        $this->assertTrue( wp_cache_replace( $key, $val2 ) );
        $this->assertEquals( $val2, wp_cache_get( $key ) );

        // Non-existent key should fail
        $this->assertFalse( wp_cache_replace( $fake_key, $val1 ) );

        // Make sure $fake_key is not stored
        $this->assertFalse( wp_cache_get( $fake_key ) );
    }

}

class TestObjectCachePreload extends \WP_UnitTestCase {
    function setUp() {
        wp_cache_init(); // reinitialize the cache

        global $wp_object_cache;
        $wp_object_cache->preloadEnabled = true;

        wp_cache_flush();

        parent::setUp();
    }

    function testCachePreloading() {
        global $wp_object_cache;

        $this->assertTrue( wp_cache_set( 'key1', 'val1', 'default' ) );
        $this->assertTrue( wp_cache_set( 'key2', 'val2', 'users' ) );

        wp_cache_close(); // this nukes the local cache
        wp_cache_init(); // reinitialize the cache

        $this->assertEquals( 'val1', wp_cache_get( 'key1', 'default' ) );
        $this->assertEquals( 'val2', wp_cache_get( 'key2', 'users' ) );

        wp_cache_close(); // this nukes the local cache and saves the preload keys
        wp_cache_init(); // reinitialize the cache

        $before = $wp_object_cache->stats();
        $this->assertEquals('val1', wp_cache_get('key1', 'default'));
        $this->assertEquals('val2', wp_cache_get('key2', 'users'));
        $after = $wp_object_cache->stats();

        // Assert that two cache requests have actually arrived
        $this->assertEquals(2, $after['get'] - $before['get']);
        // Assert that no extra request has been made to memcache after preloading
        $this->assertEquals($before['mc_get'], $after['mc_get']);

    }
}
