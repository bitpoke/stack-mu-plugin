WORDPRESS_DEVELOP_REF ?= $(shell hack/wp-version-to-develop-ref.php)
WORDPRESS_DEVELOP_GIT_REPO ?= https://github.com/WordPress/wordpress-develop.git

PHPUNIT ?= $(PWD)/vendor/bin/phpunit
SKIPPED_TESTS := $(shell paste -s -d'|' hack/skip-wp-tests)

GIT_VERSION = $(shell git describe --always --abbrev=7 --dirty)

ARGS ?=

.PHONY: lint
lint:
	composer lint

.PHONY: dep
dep:
	composer install

.PHONY: test
test: test-runtime test-wp

.PHONY: test-runtime
test-runtime: wordpress-develop wordpress-develop/wp-tests-config.php wordpress-develop/src/wp-content/object-cache.php
	composer test -- --verbose \
		$(ARGS)

.PHONY: test-wp
test-wp: wordpress-develop/wp-tests-config.php
	cd wordpress-develop && $(PHPUNIT) --verbose \
		--exclude-group ajax,ms-files,ms-required,external-http,import \
		--filter "^(?!($(SKIPPED_TESTS))).*$$" \
		$(ARGS)

wordpress-develop/wp-tests-config.php: hack/wp-tests-config.php
	cp $< $@

wordpress-develop/src/wp-content/object-cache.php: src/object-cache.php
	cp $< $@

.PHONY: wordpress-develop
wordpress-develop: wordpress-develop/.git/.make-$(WORDPRESS_DEVELOP_REF)
	cp -a web/wp/ wordpress-develop/build/
	rm -rf wordpress-develop/build/wp-content
	cp -a wordpress-develop/src/wp-content wordpress-develop/build/

wordpress-develop/.git/.make-$(WORDPRESS_DEVELOP_REF):
	rm -rf wordpress-develop
	git clone --single-branch --depth=1 --branch "$(WORDPRESS_DEVELOP_REF)" "$(WORDPRESS_DEVELOP_GIT_REPO)" wordpress-develop
	touch $@

.PHONY: bundle
bundle:
	rm -rf bundle dist
	test -d dist || mkdir dist
	mkdir -p bundle/stack-mu-plugin
	cp -a README.md LICENSE composer.json composer.lock src bundle/stack-mu-plugin
	sed 's/Version: .*/Version: $(GIT_VERSION:v%=%)/g' stack-mu-plugin.php > bundle/stack-mu-plugin/stack-mu-plugin.php
	cd bundle/stack-mu-plugin && composer install --no-dev --no-scripts --prefer-dist --ignore-platform-reqs -o
	cd bundle && tar -zcf ../dist/stack-mu-plugin.tar.gz stack-mu-plugin
	cd bundle && zip -r ../dist/stack-mu-plugin.zip stack-mu-plugin
