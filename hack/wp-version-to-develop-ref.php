#!/usr/bin/env php
<?php
// vim: set ft=php:
@$wp_version = $argv[1];

if (empty($wp_version) && file_exists(dirname(__DIR__) . "/web/wp/wp-includes/version.php") ) {
	require dirname(__DIR__) . "/web/wp/wp-includes/version.php";
}

if (empty($wp_version)) {
    $composer = json_decode(file_get_contents(dirname(__DIR__) . '/composer.lock'), true);
	if (!empty($composer["packages-dev"])) {
		foreach($composer["packages-dev"] as $req) {
			if (in_array(@$req["name"], array("bitpoke-stack/wordpress","roots/wordpress"))) {
				@$wp_version = $req["version"];
				break;
			}
		}
	}
}

if (empty($wp_version)) {
	fwrite(STDERR, "Could not determine WordPress version from composer.lock and no version specified on command line.\n");
	exit(2);
}

@list( $major, $minor, $patch ) = explode('.', $wp_version, 3);

if ( $patch == null ) {
	echo "$major.$minor.0\n";
} else {
	echo "$wp_version\n";
}
