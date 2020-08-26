<?php
/** @var string Directory containing all of the site's files */
$root_dir = dirname(__DIR__);

require_once $root_dir . '/vendor/autoload.php';

use Roots\WPConfig\Config;
/**
 * Use env() from oscarotero/env
 */
use function Env\env;

/**
 * Use Dotenv to set required environment variables and load .env file in root
 */
$dotenv = Dotenv\Dotenv::createImmutable($root_dir);
if (file_exists($root_dir . '/.env')) {
    $dotenv->load();
    if (!env('DATABASE_TEST_URL')) {
        $dotenv->required(['DB_TEST_NAME', 'DB_TEST_USER', 'DB_TEST_PASSWORD']);
    }
}

/*
 * Path to the theme to test with.
 *
 * The 'default' theme is symlinked from test/phpunit/data/themedir1/default into
 * the themes directory of the WordPress installation defined above.
 */
Config::define('WP_DEFAULT_THEME', env('WP_DEFAULT_THEME') ?: 'default');

$tempfile=tempnam(sys_get_temp_dir(),'');
unlink($tempfile);
mkdir($tempfile);
register_shutdown_function(function() use ($tempfile) { `rm -rf "$tempfile"`; });

Config::define('STACK_MEDIA_BUCKET', "file://$tempfile");

if (!defined('STACK_RUN_CORE_TESTS')) {
	define('STACK_METRICS_ENABLED', false);
}

// Test with multisite enabled.
// Alternatively, use the tests/phpunit/multisite.xml configuration file.
// define( 'WP_TESTS_MULTISITE', true );

// Force known bugs to be run.
// Tests with an associated Trac ticket that is still open are normally skipped.
Config::define('WP_TESTS_FORCE_KNOWN_BUGS', env('WP_TESTS_FORCE_KNOWN_BUGS') ?: false);

// Test with WordPress debug mode (default).
Config::define('WP_DEBUG', true);

// ** MySQL settings ** //

// This configuration file will be used by the copy of WordPress being tested.
// wordpress/wp-config.php will be ignored.

// WARNING WARNING WARNING!
// These tests will DROP ALL TABLES in the database with the prefix named below.
// DO NOT use a production database or one that is shared with something else.
Config::define('DB_NAME', env('DB_TEST_NAME') ?: 'wordpress_tests');
Config::define('DB_USER', env('DB_TEST_USER') ?: 'wordpress');
Config::define('DB_PASSWORD', env('DB_TEST_PASSWORD') ?: '');
Config::define('DB_HOST', env('DB_TEST_HOST') ?: '127.0.0.1');
Config::define('DB_CHARSET', 'utf8mb4');
Config::define('DB_COLLATE', '');
$table_prefix = env('DB_TEST_PREFIX') ?: 'wptests_';

Config::define('MEMCACHED_HOST', env('MEMCACHED_TEST_HOST') ?: 'localhost');
Config::define('UPLOADS_FTP_HOST', env('UPLOADS_FTP_TEST_HOST') ?: 'localhost');

Config::define('WP_TESTS_DOMAIN', env('WP_TESTS_DOMAIN') ?: 'example.org');
Config::define('WP_TESTS_EMAIL', env('WP_TESTS_EMAIL') ?: 'admin@example.org');
Config::define('WP_TESTS_TITLE', env('WP_TESTS_TITLE') ?: 'Test Blog');

Config::define('WP_PHP_BINARY', env('WP_PHP_BINARY') ?: 'php');

Config::define('WPLANG', env('WPLANG') ?: '');

Config::apply();

/**
 * Bootstrap WordPress
 */
if (!defined('ABSPATH')) {
    define('ABSPATH', $root_dir. '/wordpress-develop/build/');
}

define('WP_OEM_DIR', $root_dir);
