include common.Makefile

WORDPRESS_DEVELOP_REF ?= $(shell hack/wp-version-to-develop-ref.php)

WORDPRESS_FILES := $(shell find web/wp -type f)

PHPUNIT ?= $(PWD)/hack/php-noxdebug $(PWD)/vendor/bin/phpunit
PHPCS ?= $(PWD)/hack/php-noxdebug $(PWD)/vendor/bin/phpcs

SKIPPED_TESTS := $(shell paste -s -d'|' hack/skip-wp-tests)

GIT_VERSION = $(shell git describe --always --abbrev=7 --dirty)

ARGS ?=

.PHONY: lint
lint:
	$(PHPCS) --ignore=$(PWD)/src/Stack/NginxHelper/*

.PHONY: dependencies
dependencies:
	composer install

.PHONY: test
test: test-runtime test-wp

.PHONY: test-runtime
test-runtime: setup-wordpress-develop
	$(PHPUNIT) --verbose $(ARGS)

.PHONY: test-wp
test-wp: setup-wordpress-develop
	cd wordpress-develop && $(PHPUNIT) --verbose \
		--exclude-group ajax,ms-files,ms-required,external-http,import \
		--filter "^(?!($(SKIPPED_TESTS))).*$$" \
		$(ARGS)

.PHONY: setup-wordpress-develop
setup-wordpress-develop: wordpress-develop \
                         wordpress-develop/build/wp-content/object-cache.php \
                         wordpress-develop/build/wp-content/mu-plugins/stack-mu-plugin.php \
                         wordpress-develop/wp-tests-config.php

wordpress-develop/build/wp-content/mu-plugins/stack-mu-plugin.php:
	test -d $(dir $@) || mkdir -p $(dir $@)
	ln -sf ../../../../stack-mu-plugin.php $@

wordpress-develop/wp-tests-config.php: hack/wp-tests-config.php wordpress-develop
	cp $< $@

wordpress-develop/build/wp-content/object-cache.php: src/object-cache.php wordpress-develop
	cp $< $@

.build/cache:
	mkdir -p "$@"

.build/cache/wordpress-develop-%.tar.gz: .build/var/WORDPRESS_DEVELOP_REF | .build/cache
	curl -sL -o "$@" "https://github.com/WordPress/wordpress-develop/archive/$*.tar.gz"

wordpress-develop: .build/var/WORDPRESS_DEVELOP_REF \
                   .build/cache/wordpress-develop-$(WORDPRESS_DEVELOP_REF).tar.gz \
                   $(WORDPRESS_FILES)
	rm -rf wordpress-develop
	mkdir wordpress-develop
	tar -zxf .build/cache/wordpress-develop-$(WORDPRESS_DEVELOP_REF).tar.gz --strip-components 1 -C wordpress-develop
	cp -a web/wp/ wordpress-develop/build/
	rm -rf wordpress-develop/build/wp-content
	cp -a wordpress-develop/src/wp-content wordpress-develop/build/

.PHONY: bundle
bundle:
	rm -rf bundle dist
	test -d dist || mkdir dist
	mkdir -p bundle/stack-mu-plugin
	cp -a README.md LICENSE composer.json composer.lock src bundle/stack-mu-plugin
	sed 's/Version: .*/Version: $(GIT_VERSION:v%=%)/g' stack-mu-plugin.php > bundle/stack-mu-plugin/stack-mu-plugin.php
	sed 's/Version: .*/Version: $(GIT_VERSION:v%=%)/g' src/object-cache.php > bundle/stack-mu-plugin/src/object-cache.php
	cd bundle/stack-mu-plugin && composer install --no-dev --no-scripts --prefer-dist --ignore-platform-reqs -o
	cd bundle && tar -zcf ../dist/stack-mu-plugin.tar.gz stack-mu-plugin
	cd bundle && zip -r ../dist/stack-mu-plugin.zip stack-mu-plugin
