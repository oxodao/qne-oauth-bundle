build:
	@docker build -t qne-oauth-bundle docker/

shell: build
	@docker run --rm -v $(PWD):/app -ti qne-oauth-bundle bash

composer_install: build
	@docker run --rm -v $(PWD):/app -ti qne-oauth-bundle composer install

lint: build
	@docker run --rm -v $(PWD):/app -ti qne-oauth-bundle vendor/bin/php-cs-fixer fix
	@docker run --rm -v $(PWD):/app -ti qne-oauth-bundle vendor/bin/phpstan analyse
