# Everything runs in docker — no local PHP/composer required.
#
#   make test                  run the suite on the default (latest) PHP
#   make test PHP=8.2          run on a specific version
#   make test-all              run on every supported version (8.1–8.5)
#   make stan                  phpstan static analysis
#   make install               composer install only
#   make shell                 interactive shell in the PHP container
#
# Extra phpunit args: make test ARGS="--filter RouterTest"

PHP ?= 8.5
SUPPORTED_PHP := 8.4 8.5

UID := $(shell id -u)
GID := $(shell id -g)
DOCKER_RUN := docker run --rm -u "$(UID):$(GID)" -v "$(CURDIR)":/app -w /app

.PHONY: test test-all stan install shell

install:
	$(DOCKER_RUN) -e COMPOSER_CACHE_DIR=/app/.composer-cache composer:2 \
		composer update --no-interaction --quiet

test: install
	$(DOCKER_RUN) php:$(PHP)-cli php vendor/bin/phpunit $(ARGS)

test-all: install
	@for version in $(SUPPORTED_PHP); do \
		echo "=== PHP $$version ==="; \
		$(DOCKER_RUN) php:$$version-cli php vendor/bin/phpunit $(ARGS) || exit 1; \
	done

stan: install
	$(DOCKER_RUN) php:$(PHP)-cli php vendor/bin/phpstan analyse --memory-limit=512M $(ARGS)

shell:
	docker run --rm -it -u "$(UID):$(GID)" -v "$(CURDIR)":/app -w /app php:$(PHP)-cli bash
