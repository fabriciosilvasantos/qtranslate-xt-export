FROM php:8.4-cli

RUN apt-get update \
	&& apt-get install -y --no-install-recommends git libicu-dev unzip \
	&& docker-php-ext-install intl \
	&& rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/test-entrypoint.sh /usr/local/bin/qtx-test-entrypoint

RUN chmod +x /usr/local/bin/qtx-test-entrypoint

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_CACHE_DIR=/tmp/composer-cache

WORKDIR /app

ENTRYPOINT ["qtx-test-entrypoint"]
