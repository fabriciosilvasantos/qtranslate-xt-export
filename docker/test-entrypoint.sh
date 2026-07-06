#!/bin/sh

set -eu

STAMP_FILE="/app/vendor/.qtx-composer-installed"

git config --global --add safe.directory /app >/dev/null 2>&1 || true

needs_install=0

if [ ! -f /app/vendor/autoload.php ] || [ ! -f "${STAMP_FILE}" ] || [ /app/composer.json -nt "${STAMP_FILE}" ]; then
	needs_install=1
fi

if [ -f /app/composer.lock ] && [ /app/composer.lock -nt "${STAMP_FILE}" ]; then
	needs_install=1
fi

if [ "${needs_install}" -eq 1 ]; then
	composer install --no-interaction --ignore-platform-reqs
	touch "${STAMP_FILE}"
fi

exec "$@"
