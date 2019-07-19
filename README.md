# stack-mu-plugin
A Must Use plugin, that integrates all Stack's functionalities:
    * patches the ftop directory in order to redirect uploads to a FTP server (rclone ftp server that manages a storage cloud bucket)
    * custom object-cache that uses Memcached as backend 

## Install

```console
$ composer require presslabs-stack/wordpress-mu-plugin
```

In order to use the custom object-cache, you'll need to copy it into the root of wp-content

```console
$ cp src/object-cache.php <your-wp-install>/wp-content/
```

## Tests

In order to run the tests locally, you can use Docker
``` shell
docker run -p 3306:3306 -e MYSQL_DATABASE=wordpress_tests -e MYSQL_USER=wordpress -e MYSQL_PASSWORD=wordpress -e MYSQL_ROOT_PASSWORD=wordpress mysql:5.7
docker run -p 11211:11211 memcached:alpine
rclone serve ftp . --addr 0.0.0.0:2121
make test
```
