WORDPRESS_DEVELOP_REF ?= master
WORDPRESS_DEVELOP_GIT_REPO ?= https://github.com/WordPress/wordpress-develop.git

PHPUNIT ?= $(PWD)/vendor/bin/phpunit
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
