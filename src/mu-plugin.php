<?php
/*
Plugin Name: StackPresslabs
Plugin URI: https://www.presslabs.com/stack
Description: Must-use plugin for Stack.
Author: Presslabs
Version: 1.0
Author URI: https://presslabs.com/
*/

/**
 * Expose global env() function from oscarotero/env
 */
Env::init();

/**
 * Use Dotenv to set required environment variables and load .env file in root
 */
$root_dir = dirname(__DIR__);
$dotenv = Dotenv\Dotenv::create($root_dir);
if (file_exists($root_dir . '/.env')) {
    $dotenv->load();
} else {
    die($root_dir);
}

Stack\Config::loadDefaults();

if (defined('UPLOADS_FTP_HOST') && UPLOADS_FTP_HOST != "") {
    new Presslabs\FTPStorage(UPLOADS_FTP_HOST);
}
