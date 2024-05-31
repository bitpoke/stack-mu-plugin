stack-mu-plugin
===
Bitpoke Stack must use plugin for WordPress.

It provides integration for the [Bitpoke Stack](https://www.bitpoke.io/stack)
functionalities with WordPress and WooCommerce, such as:

* uploading and serving media files from object storage systems, currently with Google Cloud Storage
* object-cache implementation on top of memcached
* offloading assets to a CDN
* unified handling of logs to stderr, by default
* handling of duplicate, incompatible dependencies through [Jetpack Autoloader](https://github.com/Automattic/jetpack-autoloader)
      
## Install

### Bedrock

When using bedrock, just run:

```console
$ composer require bitpoke/stack-mu-plugin
```

### WordPress plugin

To run as WordPress classic mu-plugin, download the plugin archive from
[https://github.com/bitpoke/stack-mu-plugin/releases](https://github.com/bitpoke/stack-mu-plugin/releases)
and extract it into your `wp-content/mu-plugins` folder.

Then you need to activate the mu-plugin, by copying `stack-mu-plugin.php` from
`wp-content/mu-plugins/stakc-mu-plugin` into your `wp-content/mu-plugins`
folder.

```console
$ cp wp-content/mu-plugins/stack-mu-plugin/stack-mu-plugin.php wp-content/mu-plugins/
```

### WordPress Object Cache

In order to use the custom object cache, you'll need to copy it into the root of
`WP_CONTENT_DIR` (usually `wp-content`).

```console
$ cp wp-content/mu-plugins/stack-mu-plugin/src/object-cache.php wp-content/
```

### Enable and use a CDN for static files

All that is needed is setting the `CDN_HOST` variable in wp-config.php and of course a CNAME record in your DNS manager pointing to your CDN provider.

For example, we might use in our config file:

```php
define('CDN_HOST', 'cdn.bitpoke.io');
```

## Development

Clone this repository, copy `.env.example` to `.env` and edit it accordingly.

To install dependencies just run
```console
$ make dependencies
```

### Development server

To start a local development server you need wp-cli installed. To start the
development server, just run

```console
$ wp server
```

### Testing

Running plugin tests:

```console
$ make test-wp
```

Running integration tests:

```console
$ make test-runtime
```
